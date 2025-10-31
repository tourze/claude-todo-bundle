# 技术设计：claude-todo-bundle

## 技术概览

### 架构模式
- **Repository模式**：封装数据访问逻辑，提供一致的数据操作接口
- **Service层模式**：将业务逻辑与数据访问分离，提高可测试性
- **命令模式**：通过Symfony Console实现命令行接口
- **策略模式**：通过TaskProcessorInterface允许自定义任务处理逻辑

### 核心设计原则
1. **接口驱动**：所有核心组件都基于接口定义，便于扩展和测试
2. **单一职责**：每个组件只负责一个特定功能
3. **依赖注入**：使用Symfony DI容器管理依赖
4. **事务一致性**：确保任务状态转换的原子性

### 技术决策理由
- **Doctrine ORM**：成熟的ORM解决方案，与Symfony集成良好
- **Symfony Process**：安全可靠地调用外部CLI命令
- **事件系统**：利用Symfony EventDispatcher提供灵活的扩展点
- **乐观锁**：处理并发pop操作，避免任务重复执行

## 公共API设计

### 1. 核心接口

#### TodoManagerInterface
```php
namespace Tourze\ClaudeTodoBundle\Service;

interface TodoManagerInterface
{
    /**
     * 创建新的TODO任务
     * 
     * @param string $groupName 任务分组名称
     * @param string $description 任务描述
     * @param string $priority 优先级 (low/normal/high)
     * @return TodoTask 创建的任务实例
     * @throws \InvalidArgumentException 当参数无效时
     */
    public function push(string $groupName, string $description, string $priority = 'normal'): TodoTask;
    
    /**
     * 获取下一个可执行的任务
     * 
     * @param string|null $groupName 可选的分组过滤
     * @return TodoTask|null 可执行的任务，没有时返回null
     * @throws \RuntimeException 当数据库操作失败时
     */
    public function pop(?string $groupName = null): ?TodoTask;
    
    /**
     * 根据ID获取任务
     * 
     * @param int $id 任务ID
     * @return TodoTask 任务实例
     * @throws TaskNotFoundException 当任务不存在时
     */
    public function getTask(int $id): TodoTask;
    
    /**
     * 更新任务状态
     * 
     * @param TodoTask $task 要更新的任务
     * @param string $status 新状态
     * @param string|null $result 执行结果（可选）
     * @throws \InvalidArgumentException 当状态无效时
     */
    public function updateTaskStatus(TodoTask $task, string $status, ?string $result = null): void;
}
```

#### ClaudeExecutorInterface
```php
namespace Tourze\ClaudeTodoBundle\Service;

interface ClaudeExecutorInterface
{
    /**
     * 执行任务
     * 
     * @param TodoTask $task 要执行的任务
     * @param array $options 额外选项 (model, maxAttempts等)
     * @return ExecutionResult 执行结果
     * @throws ExecutionException 当执行失败时
     */
    public function execute(TodoTask $task, array $options = []): ExecutionResult;
    
    /**
     * 检查Claude CLI是否可用
     * 
     * @return bool
     */
    public function isAvailable(): bool;
}
```

#### TaskProcessorInterface
```php
namespace Tourze\ClaudeTodoBundle\Processor;

interface TaskProcessorInterface
{
    /**
     * 处理任务
     * 
     * @param TodoTask $task 要处理的任务
     * @return ProcessResult 处理结果
     */
    public function process(TodoTask $task): ProcessResult;
    
    /**
     * 检查是否支持该任务
     * 
     * @param TodoTask $task
     * @return bool
     */
    public function supports(TodoTask $task): bool;
}
```

### 2. 数据模型

#### TodoTask实体
```php
namespace Tourze\ClaudeTodoBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TodoTaskRepository::class)]
#[ORM\Table(name: 'claude_todo_tasks')]
#[ORM\Index(columns: ['group_name', 'status'], name: 'idx_group_status')]
#[ORM\Index(columns: ['status', 'created_time'], name: 'idx_status_created')]
class TodoTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 100)]
    private string $groupName;
    
    #[ORM\Column(type: 'text')]
    private string $description;
    
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;
    
    #[ORM\Column(type: 'string', length: 10)]
    private string $priority = self::PRIORITY_NORMAL;
    
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTime;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedTime = null;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $executedTime = null;
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $result = null;
    
    #[ORM\Column(type: 'integer')]
    #[ORM\Version]
    private int $version = 1;
    
    // 状态常量
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    
    // 优先级常量
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    
    // Getters/Setters...
}
```

### 3. 使用示例

```php
// 创建任务
$task = $todoManager->push('backend', '实现用户认证API', 'high');

// 获取任务
$task = $todoManager->pop('backend');
if ($task !== null) {
    // 处理任务
    $result = $claudeExecutor->execute($task);
}

// 自定义处理器
class CustomProcessor implements TaskProcessorInterface
{
    public function process(TodoTask $task): ProcessResult
    {
        // 自定义处理逻辑
    }
    
    public function supports(TodoTask $task): bool
    {
        return str_contains($task->getDescription(), 'custom');
    }
}
```

### 4. 错误处理策略

```php
// 自定义异常
class TaskNotFoundException extends \RuntimeException {}
class ExecutionException extends \RuntimeException {}
class UsageLimitException extends ExecutionException
{
    private int $waitUntil;
    
    public function getWaitUntil(): int
    {
        return $this->waitUntil;
    }
}
```

## 内部架构

### 1. 核心组件划分

```
src/
├── Entity/
│   └── TodoTask.php
├── Repository/
│   └── TodoTaskRepository.php
├── Service/
│   ├── TodoManager.php
│   ├── TodoManagerInterface.php
│   ├── ClaudeExecutor.php
│   └── ClaudeExecutorInterface.php
├── Command/
│   ├── PushCommand.php
│   ├── PopCommand.php
│   └── RunCommand.php
├── Event/
│   ├── TaskCreatedEvent.php
│   ├── TaskExecutedEvent.php
│   └── TaskFailedEvent.php
├── Exception/
│   ├── TaskNotFoundException.php
│   ├── ExecutionException.php
│   └── UsageLimitException.php
├── Processor/
│   └── TaskProcessorInterface.php
├── Attribute/
│   └── TaskProcessor.php
├── DependencyInjection/
│   └── ClaudeTodoExtension.php
└── ClaudeTodoBundle.php
```

### 2. 组件交互图

```
┌─────────────────┐
│ Console Command │
└───────┬────────┘
        │
        ↓
┌───────┴────────┐     ┌───────────────────┐
│  TodoManager   │────▶│ EventDispatcher │
└───────┬────────┘     └───────────────────┘
        │
        ↓
┌───────┴────────┐     ┌──────────────────┐
│  Repository    │────▶│  Doctrine ORM   │
└────────────────┘     └──────────────────┘

┌─────────────────┐     ┌──────────────────┐
│ ClaudeExecutor │────▶│ Process (CLI)   │
└─────────────────┘     └──────────────────┘
```

### 3. 数据流设计

#### Push流程
```
1. PushCommand 接收参数
2. 验证参数有效性
3. 调用 TodoManager->push()
4. TodoManager 创建 TodoTask 实体
5. Repository 保存到数据库
6. 触发 TaskCreatedEvent
7. 返回任务ID和确认信息
```

#### Pop流程
```
1. PopCommand 接收可选分组参数
2. 调用 TodoManager->pop()
3. Repository 查询可用任务：
   a. 获取有进行中任务的分组
   b. 排除这些分组
   c. 获取最早的pending任务
4. 使用乐观锁更新任务状态为in_progress
5. 如果没有可用任务：
   a. 进入等待循环（除非--no-wait）
   b. 每3秒检查一次
   c. 显示等待时间
6. 返回任务信息
```

#### Run流程
```
1. RunCommand 接收任务ID
2. 验证任务存在且状态合适
3. 调用 ClaudeExecutor->execute()
4. ClaudeExecutor：
   a. 构建Claude CLI命令
   b. 使用Process执行
   c. 实时流式输出
   d. 解析stream-json格式
5. 处理使用限制错误：
   a. 解析等待时间
   b. 显示倒计时
   c. 添加随机延迟
   d. 自动重试
6. 更新任务状态和结果
7. 触发相应事件
```

## 扩展机制

### 1. 事件系统设计

```php
// 任务生命周期事件
class TaskCreatedEvent extends Event
{
    public function __construct(
        private TodoTask $task
    ) {}
}

class TaskExecutedEvent extends Event
{
    public function __construct(
        private TodoTask $task,
        private ExecutionResult $result
    ) {}
}

class TaskFailedEvent extends Event
{
    public function __construct(
        private TodoTask $task,
        private \Throwable $exception
    ) {}
}

// 事件监听器示例（使用PHP8注解）
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class TaskEventSubscriber
{
    #[AsEventListener(event: TaskCreatedEvent::class, method: 'onTaskCreated')]
    public function onTaskCreated(TaskCreatedEvent $event): void
    {
        // 自定义逻辑，如发送通知
    }
    
    #[AsEventListener(event: TaskExecutedEvent::class, method: 'onTaskExecuted')]
    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        // 处理执行完成事件
    }
    
    #[AsEventListener(event: TaskFailedEvent::class, method: 'onTaskFailed')]
    public function onTaskFailed(TaskFailedEvent $event): void
    {
        // 处理失败事件
    }
}
```

### 2. 配置管理

#### 环境变量配置

```php
// Service/ConfigManager.php
namespace Tourze\ClaudeTodoBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AsService;

#[AsService]
class ConfigManager
{
    public function getClaudeModel(): string
    {
        return $_ENV['CLAUDE_TODO_MODEL'] ?? 'claude-sonnet-4-20250514';
    }
    
    public function getClaudePath(): string
    {
        return $_ENV['CLAUDE_TODO_CLI_PATH'] ?? 'claude';
    }
    
    public function getMaxAttempts(): int
    {
        return (int) ($_ENV['CLAUDE_TODO_MAX_ATTEMPTS'] ?? 10);
    }
    
    public function getDefaultPriority(): string
    {
        return $_ENV['CLAUDE_TODO_DEFAULT_PRIORITY'] ?? 'normal';
    }
    
    public function getStopFile(): string
    {
        return $_ENV['CLAUDE_TODO_STOP_FILE'] ?? 'claude-runner.stop';
    }
    
    public function getExtraArgs(): array
    {
        $args = $_ENV['CLAUDE_TODO_EXTRA_ARGS'] ?? '';
        return $args ? explode(' ', $args) : [];
    }
}
```

#### 使用环境变量

```bash
# .env
CLAUDE_TODO_MODEL=claude-sonnet-4-20250514
CLAUDE_TODO_CLI_PATH=claude
CLAUDE_TODO_MAX_ATTEMPTS=10
CLAUDE_TODO_DEFAULT_PRIORITY=normal
CLAUDE_TODO_STOP_FILE=claude-runner.stop
CLAUDE_TODO_EXTRA_ARGS="--verbose --debug"
```

### 3. 自定义处理器集成

```php
// 使用PHP8注解注册自定义处理器
namespace App\Processor;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Tourze\ClaudeTodoBundle\Processor\TaskProcessorInterface;

#[AsTaggedItem(tag: 'claude_todo.task_processor', priority: 100)]
class CustomTaskProcessor implements TaskProcessorInterface
{
    public function process(TodoTask $task): ProcessResult
    {
        // 自定义处理逻辑
    }
    
    public function supports(TodoTask $task): bool
    {
        return str_contains($task->getDescription(), 'custom');
    }
}
```

## 集成设计

### 1. Symfony Bundle集成

```php
// ClaudeTodoBundle.php
namespace Tourze\ClaudeTodoBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ClaudeTodoBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        
        // 使用 AutowireIterator 自动发现和注册 TaskProcessor 服务
        // 参见 TaskProcessorManager 的构造函数使用 #[AutowireIterator]
    }
    
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
```

### 2. 服务定义（使用PHP8注解）

```php
// Service/TodoManager.php
namespace Tourze\ClaudeTodoBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsService]
class TodoManager implements TodoManagerInterface
{
    public function __construct(
        #[Autowire(service: 'claude_todo.repository.todo_task')]
        private TodoTaskRepository $repository,
        private EventDispatcherInterface $eventDispatcher
    ) {}
    
    // 实现方法...
}

// Service/ClaudeExecutor.php
#[AsService]
class ClaudeExecutor implements ClaudeExecutorInterface
{
    public function __construct(
        private ConfigManager $configManager,
        private LoggerInterface $logger
    ) {}
    
    // 实现方法...
}

// Command/PushCommand.php
namespace Tourze\ClaudeTodoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'claude-todo:push',
    description: '添加新的TODO任务'
)]
class PushCommand extends Command
{
    public function __construct(
        private TodoManagerInterface $todoManager
    ) {
        parent::__construct();
    }
    
    // 命令实现...
}
```

#### 自动服务发现

```php
// ClaudeTodoBundle.php
namespace Tourze\ClaudeTodoBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class ClaudeTodoBundle extends Bundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // 使用注解自动发现服务
        $container->services()
            ->load('Tourze\\ClaudeTodoBundle\\', '../src/')
            ->exclude('../src/{Entity,Exception}/')
            ->autowire()
            ->autoconfigure();
    }
}
```

### 3. 独立使用示例

```php
// 不依赖Symfony框架使用
$entityManager = // 创建Doctrine EntityManager
$repository = new TodoTaskRepository($entityManager);
$eventDispatcher = new EventDispatcher();
$todoManager = new TodoManager($repository, $eventDispatcher);

// 配置管理
$configManager = new ConfigManager();
$logger = new NullLogger();
$claudeExecutor = new ClaudeExecutor($configManager, $logger);

// 使用
$task = $todoManager->push('test', 'Test task');
$result = $claudeExecutor->execute($task);
```

## 测试策略

### 1. 单元测试方案

```php
// TodoManagerTest.php
class TodoManagerTest extends TestCase
{
    public function testPushCreatesTask(): void
    {
        $repository = $this->createMock(TodoTaskRepository::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(TodoTask::class));
        
        $manager = new TodoManager($repository);
        $task = $manager->push('test', 'Test description');
        
        $this->assertEquals('test', $task->getGroupName());
        $this->assertEquals(TodoTask::STATUS_PENDING, $task->getStatus());
    }
    
    public function testPopRespectsGroupConcurrency(): void
    {
        $repository = $this->createMock(TodoTaskRepository::class);
        $repository->expects($this->once())
            ->method('getGroupsWithInProgressTasks')
            ->willReturn(['group1', 'group2']);
        
        $repository->expects($this->once())
            ->method('findNextAvailableTask')
            ->with(null, ['group1', 'group2'])
            ->willReturn(null);
        
        $manager = new TodoManager($repository);
        $task = $manager->pop();
        
        $this->assertNull($task);
    }
}
```

### 2. 集成测试方案

```php
// CommandIntegrationTest.php
class PopCommandIntegrationTest extends KernelTestCase
{
    public function testPopCommandWithWaitLoop(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        
        $command = $application->find('claude-todo:pop');
        $commandTester = new CommandTester($command);
        
        // 在另一个进程中添加任务
        $process = new Process(['php', 'tests/fixtures/add_task.php']);
        $process->start();
        
        $commandTester->execute([
            '--max-wait' => 5,
        ]);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task ID:', $output);
    }
}
```

### 3. 性能基准测试

```php
// PerformanceBenchmark.php
class PerformanceBenchmark
{
    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchPopWithConcurrency(): void
    {
        $manager = $this->getManager();
        
        // 模拟1000个分组，每组10个任务
        for ($i = 0; $i < 10000; $i++) {
            $manager->push('group' . ($i % 1000), 'Task ' . $i);
        }
        
        // 测试pop性能
        $task = $manager->pop();
    }
}
```

## 安全考虑

1. **命令注入防护**：使用Process组件的数组形式传递参数
2. **数据库安全**：使用参数化查询，防止SQL注入
3. **敏感信息保护**：不记录Claude CLI的完整输出到日志
4. **并发控制**：使用乐观锁防止任务重复执行
5. **权限控制**：Symfony安全组件集成（可选）
