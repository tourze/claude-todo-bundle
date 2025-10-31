<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ClaudeTodoBundle\Command\RunCommand;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;
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
#[CoversClass(RunCommand::class)]
#[RunTestsInSeparateProcesses]
final class RunCommandTest extends AbstractCommandTestCase
{
    private MockObject&TodoManagerInterface $todoManager;

    private MockObject&ClaudeExecutorInterface $claudeExecutor;

    private MockObject&ConfigManager $configManager;

    private MockObject&SleepServiceInterface $sleepService;

    private RunCommand $command;

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

        $command = $container->get(RunCommand::class);
        $this->assertInstanceOf(RunCommand::class, $command);
        $this->command = $command;
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('claude-todo:run', $this->command->getName());
        $this->assertSame('执行指定的TODO任务', $this->command->getDescription());

        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('task-id'));
        $taskIdArg = $definition->getArgument('task-id');
        $this->assertTrue($taskIdArg->isRequired());
        $this->assertSame('任务ID', $taskIdArg->getDescription());

        $this->assertTrue($definition->hasOption('model'));
        $modelOption = $definition->getOption('model');
        $this->assertSame('m', $modelOption->getShortcut());
        $this->assertTrue($modelOption->acceptValue());
        $this->assertTrue($modelOption->isValueRequired());
        $this->assertSame('Claude模型', $modelOption->getDescription());

        $this->assertTrue($definition->hasOption('max-attempts'));
        $maxAttemptsOption = $definition->getOption('max-attempts');
        $this->assertTrue($maxAttemptsOption->acceptValue());
        $this->assertTrue($maxAttemptsOption->isValueRequired());
        $this->assertSame('最大重试次数', $maxAttemptsOption->getDescription());
    }

    public function testExecuteWithTaskNotFound(): void
    {
        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->with(123)
            ->willThrowException(new TaskNotFoundException('Task with ID 123 not found'))
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task with ID 123 not found', $output);
    }

    public function testExecuteWithPendingTaskTransitionsToInProgress(): void
    {
        $task = $this->createMock(TodoTask::class);
        $task->expects($this->any())->method('getId')->willReturn(123);
        $task->expects($this->any())->method('getGroupName')->willReturn('backend');
        $task->expects($this->any())->method('getPriority')->willReturn(TaskPriority::HIGH);
        $task->expects($this->any())->method('getDescription')->willReturn('Implement user authentication');

        // First call returns PENDING (for status check)
        $task->expects($this->once())
            ->method('getStatus')
            ->willReturn(TaskStatus::PENDING)
        ;

        $task->expects($this->any())->method('getCreatedTime')->willReturn(new \DateTime('2024-01-01 10:00:00'));

        $executionResult = ExecutionResult::success('1+1=2', 0.5);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->with(123)
            ->willReturn($task)
        ;

        // Expect task status to be updated to IN_PROGRESS
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

        // Set default max attempts
        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $output = $commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Task status updated from pending to in_progress', $output);
        $this->assertStringContainsString('Task completed successfully', $output);
    }

    public function testExecuteWithInvalidTaskStatus(): void
    {
        $task = $this->createTaskEntity(TaskStatus::COMPLETED);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->with(123)
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task 123 cannot be executed (current status: completed)', $output);
        $this->assertStringContainsString('Only tasks with status "pending" or "in_progress" can be executed', $output);
    }

    public function testExecuteSuccessfully(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);
        $executionResult = ExecutionResult::success('Task completed successfully', 2.5);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->with(123)
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->with($task, ['stream_output' => true])
            ->willReturn($executionResult)
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::COMPLETED, 'Task completed successfully')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Executing task 123 from group "backend"', $output);
        $this->assertStringContainsString('Implement user authentication', $output);
        $this->assertStringContainsString('Task completed successfully! Success (2.50s)', $output);
    }

    public function testExecuteWithCustomModel(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->with($task, ['model' => 'claude-sonnet-4-20250514', 'stream_output' => true])
            ->willReturn($executionResult)
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'task-id' => '123',
            '--model' => 'claude-sonnet-4-20250514',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithUsageLimitRetrySuccess(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $usageLimitException = UsageLimitException::withWaitTime(time() + 1);

        $this->claudeExecutor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($usageLimitException),
                $executionResult
            )
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::COMPLETED, 'Task completed')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(2)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Claude AI usage limit reached', $output);
        $this->assertStringContainsString('Adding random delay', $output);
        $this->assertStringContainsString('Task completed successfully!', $output);
    }

    public function testExecuteWithUsageLimitMaxAttemptsReached(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $usageLimitException = UsageLimitException::withWaitTime(time() + 1);

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException($usageLimitException)
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(1)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Max retry attempts reached. Task remains in progress.', $output);
    }

    public function testExecuteWithExecutionException(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new ExecutionException('Claude CLI not found'))
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::FAILED, 'Claude CLI not found')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task execution failed: Claude CLI not found', $output);
    }

    public function testExecuteWithUnexpectedException(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('Network error'))
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::FAILED, 'Unexpected error: Network error')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '123']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Unexpected error: Network error', $output);
    }

    public function testExecuteWithCustomMaxAttempts(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $usageLimitException = UsageLimitException::withWaitTime(time() + 1);

        $this->claudeExecutor->expects($this->exactly(5))
            ->method('execute')
            ->willThrowException($usageLimitException)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'task-id' => '123',
            '--max-attempts' => '5',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Max retry attempts reached', $output);
    }

    public function testArgumentTaskId(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->with(456)
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->willReturn($executionResult)
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['task-id' => '456']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionModel(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);
        $executionResult = ExecutionResult::success('Task completed', 1.0);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->once())
            ->method('execute')
            ->with($task, ['model' => 'claude-3-opus', 'stream_output' => true])
            ->willReturn($executionResult)
        ;

        $this->todoManager->expects($this->once())
            ->method('updateTaskStatus')
        ;

        $this->configManager->expects($this->once())
            ->method('getMaxAttempts')
            ->willReturn(3)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'task-id' => '123',
            '--model' => 'claude-3-opus',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionMaxAttempts(): void
    {
        $task = $this->createTaskEntity(TaskStatus::IN_PROGRESS);
        $usageLimitException = UsageLimitException::withWaitTime(time() + 1);

        $this->todoManager->expects($this->once())
            ->method('getTask')
            ->willReturn($task)
        ;

        $this->claudeExecutor->expects($this->exactly(10))
            ->method('execute')
            ->willThrowException($usageLimitException)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'task-id' => '123',
            '--max-attempts' => '10',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Max retry attempts reached', $output);
    }

    private function createTaskEntity(TaskStatus $status = TaskStatus::IN_PROGRESS): TodoTask
    {
        $task = $this->createMock(TodoTask::class);
        $task->expects($this->any())->method('getId')->willReturn(123);
        $task->expects($this->any())->method('getGroupName')->willReturn('backend');
        $task->expects($this->any())->method('getPriority')->willReturn(TaskPriority::HIGH);
        $task->expects($this->any())->method('getDescription')->willReturn('Implement user authentication');
        $task->expects($this->any())->method('getStatus')->willReturn($status);
        $task->expects($this->any())->method('getCreatedTime')->willReturn(new \DateTime('2024-01-01 10:00:00'));

        return $task;
    }
}
