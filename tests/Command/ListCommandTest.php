<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\ClaudeTodoBundle\Command\ListCommand;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ListCommand::class)]
#[RunTestsInSeparateProcesses]
final class ListCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    private TodoTaskRepository $repository;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $kernel = self::$kernel;
        $this->assertInstanceOf(KernelInterface::class, $kernel);

        $application = new Application($kernel);

        $command = $application->find(ListCommand::NAME);
        $this->commandTester = new CommandTester($command);

        $this->repository = self::getService(TodoTaskRepository::class);
        $this->clearTasks();
    }

    public function testListEmptyTasks(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('没有找到符合条件的任务', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testListDefaultStatusFilter(): void
    {
        $this->createTask('test-group', 'Pending task', TaskStatus::PENDING);
        $this->createTask('test-group', 'In progress task', TaskStatus::IN_PROGRESS);
        $this->createTask('test-group', 'Completed task', TaskStatus::COMPLETED);
        $this->createTask('test-group', 'Failed task', TaskStatus::FAILED);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务列表（共 2 个）', $output);
        $this->assertStringContainsString('Pending task', $output);
        $this->assertStringContainsString('In progress task', $output);
        $this->assertStringNotContainsString('Completed task', $output);
        $this->assertStringNotContainsString('Failed task', $output);
    }

    public function testListAllTasks(): void
    {
        $this->createTask('test-group', 'Pending task', TaskStatus::PENDING);
        $this->createTask('test-group', 'In progress task', TaskStatus::IN_PROGRESS);
        $this->createTask('test-group', 'Completed task', TaskStatus::COMPLETED);
        $this->createTask('test-group', 'Failed task', TaskStatus::FAILED);

        $this->commandTester->execute(['--all' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务列表（共 4 个）', $output);
        $this->assertStringContainsString('Pending task', $output);
        $this->assertStringContainsString('In progress task', $output);
        $this->assertStringContainsString('Completed task', $output);
        $this->assertStringContainsString('Failed task', $output);
    }

    public function testListByGroup(): void
    {
        $this->createTask('backend', 'Backend task 1', TaskStatus::PENDING);
        $this->createTask('backend', 'Backend task 2', TaskStatus::PENDING);
        $this->createTask('frontend', 'Frontend task', TaskStatus::PENDING);

        $this->commandTester->execute(['group' => 'backend']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务列表（共 2 个）', $output);
        $this->assertStringContainsString('Backend task 1', $output);
        $this->assertStringContainsString('Backend task 2', $output);
        $this->assertStringNotContainsString('Frontend task', $output);
    }

    public function testListWithSpecificStatus(): void
    {
        $this->createTask('test-group', 'Pending task', TaskStatus::PENDING);
        $this->createTask('test-group', 'Completed task 1', TaskStatus::COMPLETED);
        $this->createTask('test-group', 'Completed task 2', TaskStatus::COMPLETED);

        $this->commandTester->execute(['--status' => ['completed']]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务列表（共 2 个）', $output);
        $this->assertStringContainsString('Completed task 1', $output);
        $this->assertStringContainsString('Completed task 2', $output);
        $this->assertStringNotContainsString('Pending task', $output);
    }

    public function testListWithMultipleStatuses(): void
    {
        $this->createTask('test-group', 'Pending task', TaskStatus::PENDING);
        $this->createTask('test-group', 'In progress task', TaskStatus::IN_PROGRESS);
        $this->createTask('test-group', 'Completed task', TaskStatus::COMPLETED);
        $this->createTask('test-group', 'Failed task', TaskStatus::FAILED);

        $this->commandTester->execute(['--status' => ['pending', 'failed']]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务列表（共 2 个）', $output);
        $this->assertStringContainsString('Pending task', $output);
        $this->assertStringContainsString('Failed task', $output);
        $this->assertStringNotContainsString('In progress task', $output);
        $this->assertStringNotContainsString('Completed task', $output);
    }

    public function testListWithLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->createTask('test-group', sprintf('Task %d', $i), TaskStatus::PENDING);
        }

        $this->commandTester->execute(['--limit' => '3']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务列表（共 3 个）', $output);
        $this->assertStringContainsString('仅显示前 3 条记录', $output);
    }

    public function testListShowsGroupStatistics(): void
    {
        $this->createTask('backend', 'Task 1', TaskStatus::PENDING);
        $this->createTask('backend', 'Task 2', TaskStatus::IN_PROGRESS);
        $this->createTask('frontend', 'Task 3', TaskStatus::COMPLETED);
        $this->createTask('frontend', 'Task 4', TaskStatus::FAILED);

        $this->commandTester->execute(['--all' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('任务分组', $output);
        $this->assertStringContainsString('backend', $output);
        $this->assertStringContainsString('frontend', $output);
    }

    public function testListOrdersByPriorityAndTime(): void
    {
        // Create tasks with different priorities
        $this->createTask('test-group', 'Low priority first', TaskStatus::PENDING, TaskPriority::LOW);
        $this->createTask('test-group', 'High priority second', TaskStatus::PENDING, TaskPriority::HIGH);
        $this->createTask('test-group', 'Normal priority third', TaskStatus::PENDING, TaskPriority::NORMAL);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Since output is ordered by priority DESC then time DESC,
        // we expect: High, Normal, Low (regardless of creation time)
        $highPos = strpos($output, 'High priority second');
        $normalPos = strpos($output, 'Normal priority third');
        $lowPos = strpos($output, 'Low priority first');

        $this->assertNotFalse($highPos);
        $this->assertNotFalse($normalPos);
        $this->assertNotFalse($lowPos);

        // High priority should appear before normal
        $this->assertLessThan($normalPos, $highPos);
        // Normal priority should appear before low
        $this->assertLessThan($lowPos, $normalPos);
    }

    public function testListTruncatesLongDescriptions(): void
    {
        $longDescription = str_repeat('This is a very long description. ', 10);
        $this->createTask('test-group', $longDescription, TaskStatus::PENDING);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('...', $output);
        $this->assertStringNotContainsString($longDescription, $output);
    }

    private function createTask(
        string $group,
        string $description,
        TaskStatus $status = TaskStatus::PENDING,
        TaskPriority $priority = TaskPriority::NORMAL,
    ): TodoTask {
        $task = new TodoTask();
        $task->setGroupName($group);
        $task->setDescription($description);
        $task->setPriority($priority);

        // Set status using direct property access for test purposes
        if (TaskStatus::PENDING !== $status) {
            $reflection = new \ReflectionClass($task);
            $statusProperty = $reflection->getProperty('status');
            $statusProperty->setAccessible(true);
            $statusProperty->setValue($task, $status);
        }

        if (in_array($status, [TaskStatus::IN_PROGRESS, TaskStatus::COMPLETED, TaskStatus::FAILED], true)) {
            $task->setExecutedTime(new \DateTimeImmutable());
        }

        $this->repository->save($task);

        return $task;
    }

    private function clearTasks(): void
    {
        $tasks = $this->repository->findAll();
        foreach ($tasks as $task) {
            $this->repository->remove($task, false);
        }
        self::getEntityManager()->flush();
    }

    public function testArgumentGroup(): void
    {
        $this->createTask('backend', 'Backend task', TaskStatus::PENDING);
        $this->createTask('frontend', 'Frontend task', TaskStatus::PENDING);

        $this->commandTester->execute(['group' => 'backend']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Backend task', $output);
        $this->assertStringNotContainsString('Frontend task', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testOptionStatus(): void
    {
        $this->createTask('test', 'Pending task', TaskStatus::PENDING);
        $this->createTask('test', 'Completed task', TaskStatus::COMPLETED);

        $this->commandTester->execute(['--status' => ['completed']]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Completed task', $output);
        $this->assertStringNotContainsString('Pending task', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testOptionAll(): void
    {
        $this->createTask('test', 'Pending task', TaskStatus::PENDING);
        $this->createTask('test', 'Completed task', TaskStatus::COMPLETED);

        $this->commandTester->execute(['--all' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Pending task', $output);
        $this->assertStringContainsString('Completed task', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testOptionLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->createTask('test', sprintf('Task %d', $i), TaskStatus::PENDING);
        }

        $this->commandTester->execute(['--limit' => '2']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('仅显示前 2 条记录', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }
}
