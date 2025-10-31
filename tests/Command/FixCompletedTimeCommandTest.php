<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ClaudeTodoBundle\Command\FixCompletedTimeCommand;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(FixCompletedTimeCommand::class)]
#[RunTestsInSeparateProcesses]
final class FixCompletedTimeCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(FixCompletedTimeCommand::class);

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // No additional setup needed
    }

    public function testExecuteWithoutCompletedTasksShowsNoTasksMessage(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('没有需要修复的任务', $output);
        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function testExecuteFixesCompletedTasksWithoutCompletedTime(): void
    {
        // Create a completed task without completion time
        $task = new TodoTask();
        $task->setDescription('Test completed task');
        $task->setGroupName('test-group');
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $task->setStatus(TaskStatus::COMPLETED);
        $task->setCompletedTime(null); // Reset to simulate missing completion time
        $task->setPriority(TaskPriority::NORMAL);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);

        // Refresh entity to get updated data
        self::getEntityManager()->refresh($task);

        $this->assertNotNull($task->getCompletedTime());
        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('成功修复', $output);
        $this->assertStringContainsString('个任务的完成时间', $output);
    }

    public function testExecuteWithDryRunDoesNotModifyTasks(): void
    {
        // Create a completed task without completion time
        $task = new TodoTask();
        $task->setDescription('Test completed task');
        $task->setGroupName('test-group');
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $task->setStatus(TaskStatus::COMPLETED);
        $task->setCompletedTime(null); // Reset to simulate missing completion time
        $task->setPriority(TaskPriority::NORMAL);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--dry-run' => true]);

        // Refresh entity
        self::getEntityManager()->refresh($task);

        // Should still be null since we used dry-run
        $this->assertNull($task->getCompletedTime());
        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('试运行模式', $output);
        $this->assertStringContainsString('找到', $output);
        $this->assertStringContainsString('个需要修复的任务', $output);
    }

    public function testOptionDryRun(): void
    {
        // Create a completed task without completion time
        $task = new TodoTask();
        $task->setDescription('Test task for dry run');
        $task->setGroupName('test-group');
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $task->setStatus(TaskStatus::COMPLETED);
        $task->setCompletedTime(null); // Reset to simulate missing completion time
        $task->setPriority(TaskPriority::NORMAL);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['--dry-run' => true]);

        // Refresh entity
        self::getEntityManager()->refresh($task);

        // Should still be null since we used dry-run
        $this->assertNull($task->getCompletedTime());
        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('试运行模式', $output);
    }
}
