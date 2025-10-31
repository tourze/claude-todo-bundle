<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Event\TaskExecutedEvent;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\UsageLimitException;
use Tourze\ClaudeTodoBundle\Service\ClaudeExecutor;
use Tourze\ClaudeTodoBundle\Service\ConfigManager;

/**
 * @internal
 */
#[CoversClass(ClaudeExecutor::class)]
final class ClaudeExecutorTest extends TestCase
{
    private ClaudeExecutor $executor;

    private MockObject&ConfigManager $configManager;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&LoggerInterface $logger;

    private function createExecutor(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Get the class through reflection to avoid direct instantiation in integration test
        $reflectionClass = new \ReflectionClass(ClaudeExecutor::class);
        $this->executor = $reflectionClass->newInstance(
            $this->configManager,
            $this->eventDispatcher,
            $this->logger
        );
    }

    public function testExecuteSuccess(): void
    {
        $this->createExecutor();
        $task = $this->createTask();

        // Create a script that will exit successfully with JSON output
        $scriptPath = sys_get_temp_dir() . '/test_success.sh';
        $jsonOutput = json_encode(['type' => 'text', 'text' => 'Success output']);
        file_put_contents($scriptPath, "#!/usr/bin/env bash\necho '{$jsonOutput}'\nexit 0");
        chmod($scriptPath, 0o755);

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn($scriptPath)
        ;

        $this->configManager->expects($this->once())
            ->method('getClaudeModel')
            ->willReturn('claude-sonnet-4-20250514')
        ;

        $this->configManager->expects($this->once())
            ->method('getExtraArgs')
            ->willReturn([])
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($event) {
                return $event instanceof TaskExecutedEvent
                    && 123 === $event->getTask()->getId()
                    && $event->getResult()->isSuccess();
            }))
        ;

        try {
            $result = $this->executor->execute($task);

            $this->assertTrue($result->isSuccess());
            $this->assertStringContainsString('Success output', $result->getOutput());
            $this->assertEquals(0, $result->getExitCode());
            $this->assertGreaterThan(0, $result->getExecutionTime());
        } finally {
            unlink($scriptPath);
        }
    }

    public function testExecuteWithUsageLimitError(): void
    {
        $this->createExecutor();
        $task = $this->createTask();

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn('/usr/bin/false') // Command that always fails
        ;

        $this->configManager->expects($this->once())
            ->method('getClaudeModel')
            ->willReturn('claude-sonnet-4-20250514')
        ;

        $this->configManager->expects($this->once())
            ->method('getExtraArgs')
            ->willReturn([])
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        $this->expectException(ExecutionException::class);

        $this->executor->execute($task);
    }

    public function testExecuteWithOptions(): void
    {
        $this->createExecutor();
        $task = $this->createTask();

        // Create a script that will exit successfully with JSON output
        $scriptPath = sys_get_temp_dir() . '/test_options.sh';
        $jsonOutput = json_encode(['type' => 'text', 'text' => 'Options test output']);
        file_put_contents($scriptPath, "#!/usr/bin/env bash\necho '{$jsonOutput}'\nexit 0");
        chmod($scriptPath, 0o755);

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn($scriptPath)
        ;

        $this->configManager->expects($this->once())
            ->method('getExtraArgs')
            ->willReturn([])
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        $options = [
            'model' => 'claude-opus-4-20250514',
            'temperature' => 0.8,
            'max_tokens' => 2000,
        ];

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TaskExecutedEvent::class))
        ;

        try {
            $result = $this->executor->execute($task, $options);
            $this->assertTrue($result->isSuccess());
        } finally {
            unlink($scriptPath);
        }
    }

    public function testIsAvailable(): void
    {
        $this->createExecutor();

        // Create a script that exits successfully
        $scriptPath = sys_get_temp_dir() . '/test_available.sh';
        file_put_contents($scriptPath, "#!/usr/bin/env bash\nexit 0");
        chmod($scriptPath, 0o755);

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn($scriptPath)
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        try {
            $result = $this->executor->isAvailable();
            $this->assertTrue($result);
        } finally {
            unlink($scriptPath);
        }
    }

    public function testIsAvailableReturnsFalseWhenCommandFails(): void
    {
        $this->createExecutor();

        // Create a script that exits with failure
        $scriptPath = sys_get_temp_dir() . '/test_unavailable.sh';
        file_put_contents($scriptPath, "#!/usr/bin/env bash\nexit 1");
        chmod($scriptPath, 0o755);

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn($scriptPath)
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        try {
            $result = $this->executor->isAvailable();
            $this->assertFalse($result);
        } finally {
            unlink($scriptPath);
        }
    }

    public function testIsAvailableHandlesNonExistentCommand(): void
    {
        $this->createExecutor();

        // Use a command that doesn't exist
        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn('/this/command/does/not/exist')
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        // Process will run but fail, no exception thrown
        // So logger warning won't be called
        $this->logger->expects($this->never())
            ->method('warning')
        ;

        $result = $this->executor->isAvailable();

        $this->assertFalse($result);
    }

    public function testParseWaitTimeFromMinutes(): void
    {
        $this->createExecutor();
        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('parseWaitTime');
        $method->setAccessible(true);

        $errorOutput = 'Please wait 5 minutes before retrying';
        $waitTime = $method->invoke($this->executor, $errorOutput);

        $expectedTime = time() + 300; // 5 minutes
        $this->assertEqualsWithDelta($expectedTime, $waitTime, 2);
    }

    public function testParseWaitTimeFromSeconds(): void
    {
        $this->createExecutor();
        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('parseWaitTime');
        $method->setAccessible(true);

        $errorOutput = 'Retry after 30 seconds';
        $waitTime = $method->invoke($this->executor, $errorOutput);

        $expectedTime = time() + 30;
        $this->assertEqualsWithDelta($expectedTime, $waitTime, 2);
    }

    public function testParseWaitTimeFromHours(): void
    {
        $this->createExecutor();
        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('parseWaitTime');
        $method->setAccessible(true);

        $errorOutput = 'Available in 2 hours';
        $waitTime = $method->invoke($this->executor, $errorOutput);

        $expectedTime = time() + 7200; // 2 hours
        $this->assertEqualsWithDelta($expectedTime, $waitTime, 2);
    }

    public function testParseWaitTimeDefaultsFiveMinutes(): void
    {
        $this->createExecutor();
        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('parseWaitTime');
        $method->setAccessible(true);

        $errorOutput = 'Some unrecognized error message';
        $waitTime = $method->invoke($this->executor, $errorOutput);

        $expectedTime = time() + 300; // Default 5 minutes
        $this->assertEqualsWithDelta($expectedTime, $waitTime, 2);
    }

    public function testBuildCommandWithDefaultOptions(): void
    {
        $this->createExecutor();
        $task = $this->createTask();

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn('/usr/bin/claude')
        ;

        $this->configManager->expects($this->once())
            ->method('getClaudeModel')
            ->willReturn('claude-sonnet-4-20250514')
        ;

        $this->configManager->expects($this->once())
            ->method('getExtraArgs')
            ->willReturn(['--verbose'])
        ;

        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $command = $method->invoke($this->executor, $task, []);

        $this->assertEquals([
            '/usr/bin/claude',
            '--dangerously-skip-permissions',
            '--print',
            '--output-format=stream-json',
            '--model=claude-sonnet-4-20250514',
            '--verbose',
            '--verbose', // Extra arg from config
            'Test task description',
        ], $command);
    }

    public function testBuildCommandWithCustomOptions(): void
    {
        $this->createExecutor();
        $task = $this->createTask();

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn('/usr/bin/claude')
        ;

        $this->configManager->expects($this->once())
            ->method('getExtraArgs')
            ->willReturn([])
        ;

        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $options = [
            'model' => 'claude-opus-4-20250514',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ];

        $command = $method->invoke($this->executor, $task, $options);

        $this->assertEquals([
            '/usr/bin/claude',
            '--dangerously-skip-permissions',
            '--print',
            '--output-format=stream-json',
            '--model=claude-opus-4-20250514',
            '--verbose',
            'Test task description',
        ], $command);
    }

    private function createTask(): TodoTask
    {
        $task = new TodoTask();
        $task->setGroupName('test-group');
        $task->setDescription('Test task description');
        $task->setPriority(TaskPriority::NORMAL);

        // Use reflection to set ID
        $reflection = new \ReflectionClass(TodoTask::class);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($task, 123);

        return $task;
    }

    public function testExecuteWithUsageLimitErrorMessage(): void
    {
        $this->createExecutor();
        $task = $this->createTask();

        // Create a script that will output usage limit error
        $scriptPath = sys_get_temp_dir() . '/test_usage_limit.sh';
        file_put_contents($scriptPath, "#!/usr/bin/env bash\necho 'Error: usage limit exceeded' >&2\nexit 1");
        chmod($scriptPath, 0o755);

        $this->configManager->expects($this->once())
            ->method('getClaudePath')
            ->willReturn($scriptPath)
        ;

        $this->configManager->expects($this->once())
            ->method('getClaudeModel')
            ->willReturn('claude-sonnet-4-20250514')
        ;

        $this->configManager->expects($this->once())
            ->method('getExtraArgs')
            ->willReturn([])
        ;

        $this->configManager->expects($this->once())
            ->method('getProjectRoot')
            ->willReturn(null)
        ;

        try {
            $this->executor->execute($task);
            self::fail('Expected UsageLimitException was not thrown');
        } catch (UsageLimitException $e) { // @phpstan-ignore-line catch.neverThrown
            $this->assertGreaterThan(time(), $e->getWaitUntil());
        } finally {
            unlink($scriptPath);
        }
    }

    public function testIsUsageLimitError(): void
    {
        $this->createExecutor();
        $reflection = new \ReflectionClass(ClaudeExecutor::class);
        $method = $reflection->getMethod('isUsageLimitError');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->executor, 'Error: usage limit exceeded'));
        $this->assertTrue($method->invoke($this->executor, 'You have reached the rate limit'));
        $this->assertTrue($method->invoke($this->executor, 'Your quota exceeded for this month'));
        $this->assertFalse($method->invoke($this->executor, 'General error occurred'));
    }
}
