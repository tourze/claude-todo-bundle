<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ClaudeTodoBundle\Command\WorkerCommand;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\UsageLimitException;
use Tourze\ClaudeTodoBundle\Service\ClaudeExecutorInterface;
use Tourze\ClaudeTodoBundle\Service\ConfigManager;
use Tourze\ClaudeTodoBundle\Service\SleepServiceInterface;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(WorkerCommand::class)]
#[RunTestsInSeparateProcesses]
final class WorkerCommandTest extends AbstractCommandTestCase
{
    private MockObject&TodoManagerInterface $todoManager;

    private MockObject&ClaudeExecutorInterface $claudeExecutor;

    private MockObject&ConfigManager $configManager;

    private MockObject&SleepServiceInterface $sleepService;

    private WorkerCommand $command;

    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    protected function onSetUp(): void
    {
        $this->todoManager = $this->createMock(TodoManagerInterface::class);
        $this->claudeExecutor = $this->createMock(ClaudeExecutorInterface::class);
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->sleepService = $this->createMock(SleepServiceInterface::class);

        $container = self::getContainer();
        $container->set(TodoManagerInterface::class, $this->todoManager);
        $container->set(ClaudeExecutorInterface::class, $this->claudeExecutor);
        $container->set(ConfigManager::class, $this->configManager);
        $container->set(SleepServiceInterface::class, $this->sleepService);

        $command = $container->get(WorkerCommand::class);
        $this->assertInstanceOf(WorkerCommand::class, $command);
        $this->command = $command;
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('claude-todo:worker', $this->command->getName());
        $this->assertSame('持续监听并执行待处理的任务', $this->command->getDescription());

        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('group'));
        $groupOption = $definition->getOption('group');
        $this->assertSame('g', $groupOption->getShortcut());
        $this->assertTrue($groupOption->acceptValue());
        $this->assertTrue($groupOption->isValueRequired());
        $this->assertSame('限定任务组', $groupOption->getDescription());

        $this->assertTrue($definition->hasOption('idle-timeout'));
        $idleTimeoutOption = $definition->getOption('idle-timeout');
        $this->assertTrue($idleTimeoutOption->acceptValue());
        $this->assertTrue($idleTimeoutOption->isValueRequired());
        $this->assertSame('空闲超时时间（秒），0表示永不超时', $idleTimeoutOption->getDescription());
        $this->assertSame('0', $idleTimeoutOption->getDefault());

        $this->assertTrue($definition->hasOption('check-interval'));
        $checkIntervalOption = $definition->getOption('check-interval');
        $this->assertTrue($checkIntervalOption->acceptValue());
        $this->assertTrue($checkIntervalOption->isValueRequired());
        $this->assertSame('检查间隔（秒）', $checkIntervalOption->getDescription());
        $this->assertSame('3', $checkIntervalOption->getDefault());

        $this->assertTrue($definition->hasOption('max-attempts'));
        $maxAttemptsOption = $definition->getOption('max-attempts');
        $this->assertTrue($maxAttemptsOption->acceptValue());
        $this->assertTrue($maxAttemptsOption->isValueRequired());
        $this->assertSame('单个任务最大重试次数', $maxAttemptsOption->getDescription());

        $this->assertTrue($definition->hasOption('model'));
        $modelOption = $definition->getOption('model');
        $this->assertSame('m', $modelOption->getShortcut());
        $this->assertTrue($modelOption->acceptValue());
        $this->assertTrue($modelOption->isValueRequired());
        $this->assertSame('Claude模型', $modelOption->getDescription());
    }

    public function testWorkerStopsOnIdleTimeout(): void
    {
        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        // No tasks available
        $this->todoManager->expects($this->atLeast(1))
            ->method('pop')
            ->with(null)
            ->willReturn(null)
        ;

        // Mock sleep service with minimal delay to prevent tight loops
        $this->sleepService->expects($this->any())
            ->method('sleep')
            ->willReturnCallback(function (int $seconds): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $this->sleepService->expects($this->any())
            ->method('randomSleep')
            ->willReturnCallback(function (int $min = 1, int $max = 30): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            '--idle-timeout' => '1',  // 1 second timeout
            '--check-interval' => '1', // 1 second wait between checks
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting worker', $output);
        $this->assertStringContainsString('Idle timeout reached', $output);
        $this->assertStringContainsString('Tasks Processed', $output);
    }

    public function testWorkerProcessesSingleTask(): void
    {
        $task = $this->createTaskEntity(TaskStatus::PENDING);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $this->configManager->expects($this->any())
            ->method('getStopFile')
            ->willReturn('/tmp/nonexistent-stop-file')
        ;

        // First call returns task, subsequent calls return null (to stop worker)
        $this->todoManager->expects($this->atLeast(2))
            ->method('pop')
            ->with(null)
            ->willReturnCallback(function () use ($task) {
                static $callCount = 0;
                ++$callCount;

                return 1 === $callCount ? $task : null;
            })
        ;

        $this->todoManager->expects($this->exactly(2))
            ->method('updateTaskStatus')
            ->willReturnCallback(function ($t, $status) use ($task): void {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame($task, $t);
                    $this->assertSame(TaskStatus::IN_PROGRESS, $status);
                } else {
                    $this->assertSame($task, $t);
                    $this->assertSame(TaskStatus::COMPLETED, $status);
                }
            })
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->with($task, ['stream_output' => true])
            ->willReturn($executionResult)
        ;

        // Mock sleep service with minimal delay to prevent tight loops
        $this->sleepService->expects($this->any())
            ->method('sleep')
            ->willReturnCallback(function (int $seconds): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $this->sleepService->expects($this->any())
            ->method('randomSleep')
            ->willReturnCallback(function (int $min = 1, int $max = 30): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            '--idle-timeout' => '1',
            '--check-interval' => '1',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Processing Task #123', $output);
        $this->assertStringContainsString('Task #123 completed successfully', $output);
        $this->assertStringContainsString('Tasks Processed', $output);
    }

    public function testWorkerProcessesTasksFromSpecificGroup(): void
    {
        $task = $this->createTaskEntity(TaskStatus::PENDING);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $this->todoManager->expects($this->atLeast(2))
            ->method('pop')
            ->with('user-bundle')
            ->willReturnCallback(function () use ($task) {
                static $callCount = 0;
                ++$callCount;

                return 1 === $callCount ? $task : null;
            })
        ;

        $this->todoManager->expects($this->exactly(2))
            ->method('updateTaskStatus')
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->willReturn($executionResult)
        ;

        // Mock sleep service with minimal delay to prevent tight loops
        $this->sleepService->expects($this->any())
            ->method('sleep')
            ->willReturnCallback(function (int $seconds): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $this->sleepService->expects($this->any())
            ->method('randomSleep')
            ->willReturnCallback(function (int $min = 1, int $max = 30): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            '--group' => 'user-bundle',
            '--idle-timeout' => '1',
            '--check-interval' => '1',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Processing tasks from group: user-bundle', $output);
    }

    public function testWorkerHandlesExecutionFailure(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(1)
        ;

        $this->todoManager->expects($this->atLeast(2))
            ->method('pop')
            ->with(null)
            ->willReturnCallback(function () use ($task) {
                static $callCount = 0;
                ++$callCount;

                return 1 === $callCount ? $task : null;
            })
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::FAILED, 'Execution failed')
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new ExecutionException('Execution failed'))
        ;

        // Mock sleep service with minimal delay to prevent tight loops
        $this->sleepService->expects($this->any())
            ->method('sleep')
            ->willReturnCallback(function (int $seconds): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $this->sleepService->expects($this->any())
            ->method('randomSleep')
            ->willReturnCallback(function (int $min = 1, int $max = 30): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            '--idle-timeout' => '1',
            '--check-interval' => '1',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task #123 failed after 1 attempts', $output);
        $this->assertStringContainsString('Tasks Failed', $output);
    }

    public function testWorkerHandlesUsageLimitWithRetry(): void
    {
        $task = $this->createTaskEntity(TaskStatus::PENDING);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->configManager->expects($this->any())
            ->method('getMaxAttempts')
            ->willReturn(2)
        ;

        $this->configManager->expects($this->any())
            ->method('getStopFile')
            ->willReturn('/tmp/nonexistent-stop-file')
        ;

        $this->todoManager->expects($this->atLeast(2))
            ->method('pop')
            ->with(null)
            ->willReturnCallback(function () use ($task) {
                static $callCount = 0;
                ++$callCount;

                return 1 === $callCount ? $task : null;
            })
        ;

        $this->todoManager->expects($this->exactly(2))
            ->method('updateTaskStatus')
        ;

        $usageLimitException = UsageLimitException::withWaitTime(0);

        $this->claudeExecutor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($usageLimitException),
                $executionResult
            )
        ;

        // Mock sleep service with minimal delay to prevent tight loops
        $this->sleepService->expects($this->any())
            ->method('sleep')
            ->willReturnCallback(function (int $seconds): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $this->sleepService->expects($this->any())
            ->method('randomSleep')
            ->willReturnCallback(function (int $min = 1, int $max = 30): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            '--idle-timeout' => '10',
            '--check-interval' => '1',
            '--max-attempts' => '2',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Claude AI usage limit reached', $output);
        $this->assertStringContainsString('Task #123 completed successfully', $output);
    }

    public function testWorkerStopsOnStopFile(): void
    {
        $stopFile = sys_get_temp_dir() . '/test-stop-file';

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $this->configManager->expects($this->atLeast(1))
            ->method('getStopFile')
            ->willReturn($stopFile)
        ;

        // Create stop file
        file_put_contents($stopFile, 'stop');

        try {
            $this->todoManager->expects($this->any())
                ->method('pop')
                ->with(null)
                ->willReturn(null)
            ;

            $commandTester = new CommandTester($this->command);
            $exitCode = $commandTester->execute([
                '--idle-timeout' => '60',
                '--check-interval' => '1',
            ]);

            $this->assertSame(Command::SUCCESS, $exitCode);
            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('Stop file detected', $output);
        } finally {
            // Clean up
            @unlink($stopFile);
        }
    }

    public function testWorkerProcessesMultipleTasks(): void
    {
        $task1 = $this->createTaskEntity(TaskStatus::PENDING, 1);
        $task2 = $this->createTaskEntity(TaskStatus::PENDING, 2);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        // Return tasks one by one
        $this->todoManager->expects($this->atLeast(3))
            ->method('pop')
            ->with(null)
            ->willReturnCallback(function () use ($task1, $task2) {
                static $callCount = 0;
                ++$callCount;
                if (1 === $callCount) {
                    return $task1;
                }
                if (2 === $callCount) {
                    return $task2;
                }

                return null;
            })
        ;

        $this->todoManager->expects($this->exactly(4))
            ->method('updateTaskStatus')
        ;

        $this->claudeExecutor->expects($this->exactly(2))
            ->method('execute')
            ->willReturn($executionResult)
        ;

        // Mock sleep service with minimal delay to prevent tight loops
        $this->sleepService->expects($this->any())
            ->method('sleep')
            ->willReturnCallback(function (int $seconds): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $this->sleepService->expects($this->any())
            ->method('randomSleep')
            ->willReturnCallback(function (int $min = 1, int $max = 30): void {
                usleep(1000); // 1ms delay to prevent tight loop
            })
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            '--idle-timeout' => '1',
            '--check-interval' => '1',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Processing Task #1', $output);
        $this->assertStringContainsString('Processing Task #2', $output);
        $this->assertStringContainsString('Tasks Processed', $output);
        $this->assertStringContainsString('2', $output); // 2 tasks processed
    }

    private function createTaskEntity(TaskStatus $status = TaskStatus::PENDING, int $id = 123): TodoTask
    {
        $task = $this->createMock(TodoTask::class);
        $task->expects($this->any())->method('getId')->willReturn($id);
        $task->expects($this->any())->method('getGroupName')->willReturn('user-bundle');
        $task->expects($this->any())->method('getPriority')->willReturn(TaskPriority::HIGH);
        $task->expects($this->any())->method('getDescription')->willReturn('Test task');
        $task->expects($this->any())->method('getStatus')->willReturn($status);
        $task->expects($this->any())->method('getCreatedTime')->willReturn(new \DateTime('2024-01-01 10:00:00'));

        return $task;
    }

    public function testOptionGroup(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('group'));
        $groupOption = $definition->getOption('group');
        $this->assertSame('g', $groupOption->getShortcut());
        $this->assertTrue($groupOption->acceptValue());
        $this->assertTrue($groupOption->isValueRequired());
        $this->assertSame('限定任务组', $groupOption->getDescription());
    }

    public function testOptionIdleTimeout(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('idle-timeout'));
        $idleTimeoutOption = $definition->getOption('idle-timeout');
        $this->assertTrue($idleTimeoutOption->acceptValue());
        $this->assertTrue($idleTimeoutOption->isValueRequired());
        $this->assertSame('空闲超时时间（秒），0表示永不超时', $idleTimeoutOption->getDescription());
        $this->assertSame('0', $idleTimeoutOption->getDefault());
    }

    public function testOptionCheckInterval(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('check-interval'));
        $checkIntervalOption = $definition->getOption('check-interval');
        $this->assertTrue($checkIntervalOption->acceptValue());
        $this->assertTrue($checkIntervalOption->isValueRequired());
        $this->assertSame('检查间隔（秒）', $checkIntervalOption->getDescription());
        $this->assertSame('3', $checkIntervalOption->getDefault());
    }

    public function testOptionMaxAttempts(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('max-attempts'));
        $maxAttemptsOption = $definition->getOption('max-attempts');
        $this->assertTrue($maxAttemptsOption->acceptValue());
        $this->assertTrue($maxAttemptsOption->isValueRequired());
        $this->assertSame('单个任务最大重试次数', $maxAttemptsOption->getDescription());
    }

    public function testOptionModel(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('model'));
        $modelOption = $definition->getOption('model');
        $this->assertSame('m', $modelOption->getShortcut());
        $this->assertTrue($modelOption->acceptValue());
        $this->assertTrue($modelOption->isValueRequired());
        $this->assertSame('Claude模型', $modelOption->getDescription());
    }

    public function testHandleSignal(): void
    {
        $this->configManager->expects($this->any())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        // Simulate a signal to be handled
        $stopFile = sys_get_temp_dir() . '/test-handle-signal';

        $this->configManager->expects($this->any())
            ->method('getStopFile')
            ->willReturn($stopFile)
        ;

        // We'll use a task that returns null to prevent long execution
        $this->todoManager->expects($this->any())
            ->method('pop')
            ->willReturn(null)
        ;

        // Test handling signal
        $result = $this->command->handleSignal(SIGINT);
        $this->assertFalse($result);

        // Another test with SIGTERM
        $result = $this->command->handleSignal(SIGTERM, 0);
        $this->assertFalse($result);
    }
}
