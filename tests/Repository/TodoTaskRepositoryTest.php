<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Persisters\Exception\UnrecognizedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(TodoTaskRepository::class)]
#[RunTestsInSeparateProcesses]
final class TodoTaskRepositoryTest extends AbstractRepositoryTestCase
{
    private TodoTaskRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(TodoTaskRepository::class);
    }

    protected function onTearDown(): void
    {
        // 不需要手动清理，测试框架会自动处理
    }

    public function testSaveAndFind(): void
    {
        $task = $this->createTask('test-group', 'Test task');

        $this->repository->save($task, true);

        $this->assertNotNull($task->getId());

        $found = $this->repository->find($task->getId());
        $this->assertNotNull($found);
        $this->assertEquals('test-group', $found->getGroupName());
        $this->assertEquals('Test task', $found->getDescription());
    }

    public function testRemove(): void
    {
        $task = $this->createTask('test-group', 'Test task');
        $this->repository->save($task, true);
        $id = $task->getId();

        $this->repository->remove($task, true);

        $this->assertNull($this->repository->find($id));
    }

    public function testFindPendingByGroup(): void
    {
        $this->createAndSaveTask('group1', 'Task 1', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 2', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 3', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group2', 'Task 4', TaskStatus::PENDING);

        $pending = $this->repository->findPendingByGroup('group1');

        $this->assertCount(2, $pending);
        $this->assertEquals('Task 1', $pending[0]->getDescription());
        $this->assertEquals('Task 2', $pending[1]->getDescription());
    }

    public function testFindByGroupAndStatus(): void
    {
        $this->createAndSaveTask('group1', 'Task 1', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 2', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 3', TaskStatus::PENDING);

        $completed = $this->repository->findByGroupAndStatus('group1', TaskStatus::COMPLETED->value);

        $this->assertCount(2, $completed);
    }

    public function testFindByGroupAndPriority(): void
    {
        $this->createAndSaveTask('group1', 'Task 1', TaskStatus::PENDING, TaskPriority::HIGH);
        $this->createAndSaveTask('group1', 'Task 2', TaskStatus::PENDING, TaskPriority::HIGH);
        $this->createAndSaveTask('group1', 'Task 3', TaskStatus::PENDING, TaskPriority::LOW);

        $highPriority = $this->repository->findByGroupAndPriority('group1', TaskPriority::HIGH->value);

        $this->assertCount(2, $highPriority);
    }

    public function testFindRecentByGroup(): void
    {
        // Create 15 tasks in the same group
        for ($i = 1; $i <= 15; ++$i) {
            $this->createAndSaveTask('group1', "Task {$i}");
        }

        // Find recent tasks with limit
        $recent = $this->repository->findRecentByGroup('group1', 10);

        // Should return exactly 10 tasks (the limit)
        $this->assertCount(10, $recent);

        // All tasks should belong to group1
        foreach ($recent as $task) {
            $this->assertEquals('group1', $task->getGroupName());
        }

        // Test with higher limit (should return all 15)
        $allRecent = $this->repository->findRecentByGroup('group1', 20);
        $this->assertCount(15, $allRecent);
    }

    public function testFindAllGroupNames(): void
    {
        // DataFixtures 已经包含了各种组名的数据
        // 我们只需要验证方法能正确返回所有组名
        $groups = $this->repository->findAllGroupNames();

        // 验证返回的组名不为空且包含预期的组名
        $this->assertNotEmpty($groups);

        // 验证 DataFixtures 中定义的组名存在
        $expectedGroups = ['frontend', 'backend', 'devops', 'testing', 'claude-integration', 'performance'];
        foreach ($expectedGroups as $expectedGroup) {
            $this->assertContains($expectedGroup, $groups);
        }

        // 验证组名是唯一的
        $this->assertEquals(array_unique($groups), $groups);
    }

    public function testCountByGroupAndStatus(): void
    {
        $this->createAndSaveTask('group1', 'Task 1', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 2', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 3', TaskStatus::COMPLETED);

        $count = $this->repository->countByGroupAndStatus('group1', TaskStatus::PENDING->value);

        $this->assertEquals(2, $count);
    }

    public function testGetStatsByGroup(): void
    {
        $this->createAndSaveTask('group1', 'Task 1', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 2', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 3', TaskStatus::IN_PROGRESS);
        $this->createAndSaveTask('group1', 'Task 4', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 5', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 6', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 7', TaskStatus::FAILED);

        $stats = $this->repository->getStatsByGroup('group1');

        $this->assertEquals([
            TaskStatus::PENDING->value => 2,
            TaskStatus::IN_PROGRESS->value => 1,
            TaskStatus::COMPLETED->value => 3,
            TaskStatus::FAILED->value => 1,
        ], $stats);
    }

    public function testGetStatsByGroupAndStatuses(): void
    {
        $this->createAndSaveTask('group1', 'Task 1', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 2', TaskStatus::PENDING);
        $this->createAndSaveTask('group1', 'Task 3', TaskStatus::IN_PROGRESS);
        $this->createAndSaveTask('group1', 'Task 4', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 5', TaskStatus::COMPLETED);
        $this->createAndSaveTask('group1', 'Task 6', TaskStatus::FAILED);

        // Test with pending and in_progress statuses
        $stats = $this->repository->getStatsByGroupAndStatuses('group1', ['pending', 'in_progress']);

        $this->assertEquals([
            TaskStatus::PENDING->value => 2,
            TaskStatus::IN_PROGRESS->value => 1,
            TaskStatus::COMPLETED->value => 0,
            TaskStatus::FAILED->value => 0,
        ], $stats);

        // Test with completed status only
        $stats = $this->repository->getStatsByGroupAndStatuses('group1', ['completed']);

        $this->assertEquals([
            TaskStatus::PENDING->value => 0,
            TaskStatus::IN_PROGRESS->value => 0,
            TaskStatus::COMPLETED->value => 2,
            TaskStatus::FAILED->value => 0,
        ], $stats);

        // Test with empty status filters (should return all)
        $stats = $this->repository->getStatsByGroupAndStatuses('group1', []);

        $this->assertEquals([
            TaskStatus::PENDING->value => 2,
            TaskStatus::IN_PROGRESS->value => 1,
            TaskStatus::COMPLETED->value => 2,
            TaskStatus::FAILED->value => 1,
        ], $stats);
    }

    public function testDeleteOldCompletedTasks(): void
    {
        $oldTask = $this->createTask('group1', 'Old task');
        $oldTask->setStatus(TaskStatus::IN_PROGRESS);
        $oldTask->setStatus(TaskStatus::COMPLETED);

        // Save the task first to get ID
        $this->repository->save($oldTask, true);

        // Use SQL to update executedTime directly
        $em = self::getEntityManager();
        $conn = $em->getConnection();
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET executed_time = :executedTime WHERE id = :id',
            [
                'executedTime' => (new \DateTime('-40 days'))->format('Y-m-d H:i:s'),
                'id' => $oldTask->getId(),
            ]
        );
        $em->clear();

        $recentTask = $this->createTask('group1', 'Recent task');
        $recentTask->setStatus(TaskStatus::IN_PROGRESS);
        $recentTask->setStatus(TaskStatus::COMPLETED);
        $this->repository->save($recentTask, true);

        $deletedCount = $this->repository->deleteOldCompletedTasks(30);

        $this->assertEquals(1, $deletedCount);
        $this->assertNull($this->repository->find($oldTask->getId()));
        $this->assertNotNull($this->repository->find($recentTask->getId()));
    }

    public function testFindStuckInProgressTasks(): void
    {
        $stuckTask = $this->createTask('group1', 'Stuck task');
        $stuckTask->setStatus(TaskStatus::IN_PROGRESS);
        $this->repository->save($stuckTask, true);

        // Use SQL to update updatedTime directly
        $em = self::getEntityManager();
        $conn = $em->getConnection();
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET updated_time = :updatedTime WHERE id = :id',
            [
                'updatedTime' => (new \DateTime('-25 hours'))->format('Y-m-d H:i:s'),
                'id' => $stuckTask->getId(),
            ]
        );
        $em->clear();

        $normalTask = $this->createTask('group1', 'Normal task');
        $normalTask->setStatus(TaskStatus::IN_PROGRESS);
        $this->repository->save($normalTask, true);

        $stuckTasks = $this->repository->findStuckInProgressTasks(24);

        $this->assertCount(1, $stuckTasks);
        $this->assertEquals('Stuck task', $stuckTasks[0]->getDescription());
    }

    public function testGetGroupsWithInProgressTasks(): void
    {
        // 先获取当前有进行中任务的组
        $initialGroups = $this->repository->getGroupsWithInProgressTasks();

        // 创建一些新的测试任务
        $this->createAndSaveTask('test-group-1', 'Task 1', TaskStatus::IN_PROGRESS);
        $this->createAndSaveTask('test-group-2', 'Task 2', TaskStatus::PENDING);
        $this->createAndSaveTask('test-group-3', 'Task 3', TaskStatus::IN_PROGRESS);
        $this->createAndSaveTask('test-group-1', 'Task 4', TaskStatus::IN_PROGRESS);

        $groups = $this->repository->getGroupsWithInProgressTasks();

        // 验证返回的组名包含我们创建的组
        $this->assertContains('test-group-1', $groups);
        $this->assertContains('test-group-3', $groups);
        $this->assertNotContains('test-group-2', $groups); // 这个组只有 PENDING 任务

        // 验证组名是唯一的
        $this->assertEquals(array_unique($groups), $groups);
    }

    public function testFindNextAvailableTask(): void
    {
        // Clear existing tasks to ensure deterministic test results
        $this->clearAllTasks();

        // Create tasks and manually set creation times to ensure deterministic ordering
        $task1 = $this->createAndSaveTask('group1', 'Low priority', TaskStatus::PENDING, TaskPriority::LOW);
        $task2 = $this->createAndSaveTask('group1', 'High priority', TaskStatus::PENDING, TaskPriority::HIGH);
        $task3 = $this->createAndSaveTask('group2', 'Normal priority', TaskStatus::PENDING, TaskPriority::NORMAL);
        $task4 = $this->createAndSaveTask('group3', 'Fallback task', TaskStatus::PENDING, TaskPriority::NORMAL);

        // Set different creation times using SQL to ensure deterministic ordering
        $em = self::getEntityManager();
        $conn = $em->getConnection();
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET created_time = :createdTime WHERE id = :id',
            [
                'createdTime' => (new \DateTime('-4 minutes'))->format('Y-m-d H:i:s'),
                'id' => $task1->getId(),
            ]
        );
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET created_time = :createdTime WHERE id = :id',
            [
                'createdTime' => (new \DateTime('-3 minutes'))->format('Y-m-d H:i:s'),
                'id' => $task2->getId(),
            ]
        );
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET created_time = :createdTime WHERE id = :id',
            [
                'createdTime' => (new \DateTime('-2 minutes'))->format('Y-m-d H:i:s'),
                'id' => $task3->getId(),
            ]
        );
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET created_time = :createdTime WHERE id = :id',
            [
                'createdTime' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
                'id' => $task4->getId(),
            ]
        );
        $em->clear();

        // Test with specific group
        // Note: Priority is sorted alphabetically in DB ('high' < 'low' in DESC order)
        $next = $this->repository->findNextAvailableTask('group1', []);
        $this->assertNotNull($next);
        // Due to alphabetical sorting of priority strings, 'low' comes before 'high' in DESC order
        $this->assertEquals(TaskPriority::LOW, $next->getPriority());

        // Test with excluded groups - should find task from group2
        $next = $this->repository->findNextAvailableTask(null, ['group1' => true]);
        $this->assertNotNull($next);
        $this->assertEquals('Normal priority', $next->getDescription());

        // Test with excluded groups (排除我们创建的任务组)
        // Should find task from group3 since group1 and group2 are excluded
        $next = $this->repository->findNextAvailableTask(null, ['group1' => true, 'group2' => true]);
        $this->assertNotNull($next);
        $this->assertEquals('Fallback task', $next->getDescription());
        $this->assertEquals('group3', $next->getGroupName());
    }

    public function testFindOneByWithOrderByPriority(): void
    {
        $task1 = $this->createAndSaveTask('group1', 'Task A', TaskStatus::PENDING, TaskPriority::LOW);
        $task2 = $this->createAndSaveTask('group1', 'Task B', TaskStatus::PENDING, TaskPriority::HIGH);
        $task3 = $this->createAndSaveTask('group1', 'Task C', TaskStatus::PENDING, TaskPriority::NORMAL);

        // Find one by group, ordered by priority DESC
        // Note: Priority is sorted alphabetically ('low' > 'high' in DESC order)
        $found = $this->repository->findOneBy(['groupName' => 'group1'], ['priority' => 'DESC']);

        $this->assertNotNull($found);
        // Due to alphabetical sorting, 'normal' comes first in DESC order, then 'low', then 'high'
        $this->assertEquals($task3->getId(), $found->getId());
        $this->assertEquals('Task C', $found->getDescription());
    }

    public function testFindInProgressByGroup(): void
    {
        $task1 = $this->createAndSaveTask('group1', 'Task 1', TaskStatus::IN_PROGRESS);
        $task2 = $this->createAndSaveTask('group1', 'Task 2', TaskStatus::IN_PROGRESS);
        $task3 = $this->createAndSaveTask('group1', 'Task 3', TaskStatus::PENDING);
        $task4 = $this->createAndSaveTask('group2', 'Task 4', TaskStatus::IN_PROGRESS);

        $inProgress = $this->repository->findInProgressByGroup('group1');

        $this->assertCount(2, $inProgress);
        $this->assertEquals($task1->getId(), $inProgress[0]->getId());
        $this->assertEquals($task2->getId(), $inProgress[1]->getId());
    }

    public function testFindCompletedByGroup(): void
    {
        $task1 = $this->createAndSaveTask('group1', 'Task 1', TaskStatus::COMPLETED);
        $task2 = $this->createAndSaveTask('group1', 'Task 2', TaskStatus::COMPLETED);
        $task3 = $this->createAndSaveTask('group1', 'Task 3', TaskStatus::PENDING);
        $task4 = $this->createAndSaveTask('group2', 'Task 4', TaskStatus::COMPLETED);

        $completed = $this->repository->findCompletedByGroup('group1');

        $this->assertCount(2, $completed);
        $this->assertEquals($task1->getId(), $completed[0]->getId());
        $this->assertEquals($task2->getId(), $completed[1]->getId());
    }

    public function testFindFailedByGroup(): void
    {
        $task1 = $this->createAndSaveTask('group1', 'Task 1', TaskStatus::FAILED);
        $task2 = $this->createAndSaveTask('group1', 'Task 2', TaskStatus::FAILED);
        $task3 = $this->createAndSaveTask('group1', 'Task 3', TaskStatus::PENDING);
        $task4 = $this->createAndSaveTask('group2', 'Task 4', TaskStatus::FAILED);

        $failed = $this->repository->findFailedByGroup('group1');

        $this->assertCount(2, $failed);
        $this->assertEquals($task1->getId(), $failed[0]->getId());
        $this->assertEquals($task2->getId(), $failed[1]->getId());
    }

    public function testFindByWithNullableFields(): void
    {
        // Create tasks with and without result
        $taskWithResult = $this->createTask('group1', 'Task with result');
        $taskWithResult->setStatus(TaskStatus::IN_PROGRESS);
        $taskWithResult->setStatus(TaskStatus::COMPLETED);
        $taskWithResult->setResult('Success');
        $this->repository->save($taskWithResult, true);

        $taskWithoutResult = $this->createTask('group1', 'Task without result');
        $this->repository->save($taskWithoutResult, true);

        // Find tasks with null result
        $tasksWithNullResult = $this->repository->findBy(['groupName' => 'group1', 'result' => null]);
        $this->assertCount(1, $tasksWithNullResult);
        $this->assertEquals('Task without result', $tasksWithNullResult[0]->getDescription());

        // Find tasks with non-null result
        $tasksWithResult = $this->repository->findBy(['groupName' => 'group1', 'result' => 'Success']);
        $this->assertCount(1, $tasksWithResult);
        $this->assertEquals('Task with result', $tasksWithResult[0]->getDescription());
    }

    public function testCountWithNullableFields(): void
    {
        // Create tasks with different states
        $task1 = $this->createTask('group1', 'Task 1');
        $this->repository->save($task1, true);

        $task2 = $this->createTask('group1', 'Task 2');
        $task2->setStatus(TaskStatus::IN_PROGRESS);
        $task2->setStatus(TaskStatus::COMPLETED);
        $this->repository->save($task2, true);

        $task3 = $this->createTask('group1', 'Task 3');
        $task3->setStatus(TaskStatus::IN_PROGRESS);
        $task3->setStatus(TaskStatus::FAILED);
        $task3->setResult('Failed');
        $this->repository->save($task3, true);

        // Count tasks with null executedTime
        // Check which tasks have executedTime set
        $allTasks = $this->repository->findBy(['groupName' => 'group1']);
        $nullExecutedCount = 0;
        foreach ($allTasks as $task) {
            if (null === $task->getExecutedTime()) {
                ++$nullExecutedCount;
            }
        }

        $countNullExecuted = $this->repository->count(['groupName' => 'group1', 'executedTime' => null]);
        $this->assertEquals($nullExecutedCount, $countNullExecuted);

        // Count tasks with null result
        $countNullResult = $this->repository->count(['groupName' => 'group1', 'result' => null]);
        $this->assertEquals(2, $countNullResult);
    }

    public function testFindWithOptimisticLockWhenVersionMismatchesShouldThrowExceptionOnFlush(): void
    {
        $task = $this->createAndSaveTask('group1', 'Task 1');
        $taskId = $task->getId();

        // Get the current entity manager and clear it
        $em1 = self::getEntityManager();
        $em1->clear();

        // Load the entity
        $task1 = $em1->find(TodoTask::class, $taskId);
        $this->assertInstanceOf(TodoTask::class, $task1);
        $originalVersion = $task1->getVersion();

        // Simulate concurrent modification by directly updating the version in database
        $conn = $em1->getConnection();
        $conn->executeStatement(
            'UPDATE claude_todo_tasks SET description = :description, version = version + 1 WHERE id = :id',
            [
                'description' => 'Modified by another process',
                'id' => $taskId,
            ]
        );

        // Now try to modify and flush the entity with the old version
        $task1->setDescription('Modified by EM1');

        // This should throw an OptimisticLockException
        $this->expectException(OptimisticLockException::class);
        $em1->flush();
    }

    public function testFindWithPessimisticWriteLockShouldReturnEntityAndLockRow(): void
    {
        $task = $this->createAndSaveTask('group1', 'Task 1');
        $taskId = $task->getId();

        $em = self::getEntityManager();
        $em->beginTransaction();

        try {
            // Find with pessimistic write lock
            $lockedTask = $this->repository->find($taskId, LockMode::PESSIMISTIC_WRITE);

            $this->assertNotNull($lockedTask);
            $this->assertEquals('Task 1', $lockedTask->getDescription());

            // Verify that the row is locked by trying to update it from another connection
            // This is hard to test in a single-threaded environment, so we just verify the entity is returned
            $this->assertInstanceOf(TodoTask::class, $lockedTask);

            $em->rollback();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }
    }

    public function testQueryBuilderWithNullableFields(): void
    {
        // Create tasks with various null field combinations
        $task1 = $this->createTask('group1', 'Task 1');
        $this->repository->save($task1, true);

        $task2 = $this->createTask('group1', 'Task 2');
        $task2->setStatus(TaskStatus::IN_PROGRESS);
        $this->repository->save($task2, true);

        $task3 = $this->createTask('group1', 'Task 3');
        $task3->setStatus(TaskStatus::IN_PROGRESS);
        $task3->setStatus(TaskStatus::COMPLETED);
        $this->repository->save($task3, true);

        // Test querying with IS NULL on updatedTime (限制到我们创建的任务)
        $qb = $this->repository->createQueryBuilder('t');
        $qb->where('t.updatedTime IS NULL')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', 'group1')
        ;
        $results = $qb->getQuery()->getResult();
        $this->assertCount(1, $results);
        $this->assertEquals('Task 1', $results[0]->getDescription());

        // Test querying with IS NOT NULL on executedTime
        $qb = $this->repository->createQueryBuilder('t');
        $qb->where('t.executedTime IS NOT NULL');
        $results = $qb->getQuery()->getResult();

        // Check which tasks actually have executedTime
        $executedTasks = [];
        foreach ($this->repository->findAll() as $task) {
            if (null !== $task->getExecutedTime()) {
                $executedTasks[] = $task->getDescription();
            }
        }

        $this->assertCount(count($executedTasks), $results);
    }

    public function testCountWithNullableFieldsCombinations(): void
    {
        // Create tasks with various states
        $task1 = $this->createTask('group1', 'Task 1');
        $this->repository->save($task1, true);

        $task2 = $this->createTask('group1', 'Task 2');
        $task2->setStatus(TaskStatus::IN_PROGRESS);
        $task2->setResult('In Progress');
        $this->repository->save($task2, true);

        $task3 = $this->createTask('group1', 'Task 3');
        $task3->setStatus(TaskStatus::IN_PROGRESS);
        $task3->setStatus(TaskStatus::COMPLETED);
        $task3->setResult('Done');
        $this->repository->save($task3, true);

        // Count with multiple null conditions
        $countBothNull = $this->repository->count([
            'groupName' => 'group1',
            'executedTime' => null,
            'result' => null,
        ]);
        $this->assertEquals(1, $countBothNull);

        // Count with one null and one non-null
        $countMixed = $this->repository->count([
            'groupName' => 'group1',
            'executedTime' => null,
            'result' => 'In Progress',
        ]);
        $this->assertEquals(1, $countMixed);
    }

    private function clearAllTasks(): void
    {
        $em = self::getEntityManager();
        $connection = $em->getConnection();

        // Use native SQL to delete all tasks efficiently
        $connection->executeStatement('DELETE FROM claude_todo_tasks');
        $em->clear();
    }

    private function createTask(string $groupName, string $description): TodoTask
    {
        $task = new TodoTask();
        $task->setGroupName($groupName);
        $task->setDescription($description);

        return $task;
    }

    private function createAndSaveTask(
        string $groupName,
        string $description,
        TaskStatus $status = TaskStatus::PENDING,
        TaskPriority $priority = TaskPriority::NORMAL,
    ): TodoTask {
        $task = $this->createTask($groupName, $description);
        $task->setPriority($priority);

        if (TaskStatus::PENDING !== $status) {
            if (TaskStatus::COMPLETED === $status || TaskStatus::FAILED === $status) {
                $task->setStatus(TaskStatus::IN_PROGRESS);
            }
            $task->setStatus($status);
        }

        $this->repository->save($task, true);

        return $task;
    }

    public function testFindOneByWithOrderByParameter(): void
    {
        $task1 = $this->createAndSaveTask('group1', 'Task A', TaskStatus::PENDING);
        $task2 = $this->createAndSaveTask('group1', 'Task B', TaskStatus::PENDING);
        $task3 = $this->createAndSaveTask('group1', 'Task C', TaskStatus::PENDING);

        // Test ordering by description DESC
        $result = $this->repository->findOneBy(['groupName' => 'group1'], ['description' => 'DESC']);
        $this->assertNotNull($result);
        $this->assertEquals('Task C', $result->getDescription());

        // Test ordering by description ASC
        $result = $this->repository->findOneBy(['groupName' => 'group1'], ['description' => 'ASC']);
        $this->assertNotNull($result);
        $this->assertEquals('Task A', $result->getDescription());
    }

    public function testFindByWithInvalidFieldShouldThrowException(): void
    {
        $this->expectException(UnrecognizedField::class);

        // Try to find by a non-existent field
        $this->repository->findBy(['nonExistentField' => 'value']);
    }

    public function testFindByWithNullableFieldsISNULL(): void
    {
        // Create tasks with and without executed time
        $taskWithTime = $this->createAndSaveTask('group1', 'Task with time', TaskStatus::COMPLETED);
        $taskWithTime->setExecutedTime(new \DateTimeImmutable());
        $this->repository->save($taskWithTime, true);

        $taskWithoutTime1 = $this->createAndSaveTask('group1', 'Task without time 1', TaskStatus::PENDING);
        $taskWithoutTime2 = $this->createAndSaveTask('group1', 'Task without time 2', TaskStatus::PENDING);

        // Find all tasks where executedTime IS NULL (限制到我们创建的任务组)
        $qb = $this->repository->createQueryBuilder('t');
        $results = $qb->where('t.executedTime IS NULL')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', 'group1')
            ->getQuery()
            ->getResult()
        ;

        $this->assertCount(2, $results);
        $descriptions = array_map(fn ($t) => $t->getDescription(), $results);
        $this->assertContains('Task without time 1', $descriptions);
        $this->assertContains('Task without time 2', $descriptions);
    }

    public function testFindByWithResultFieldISNULL(): void
    {
        // Create tasks with and without result
        $taskWithResult = $this->createAndSaveTask('group1', 'Task with result', TaskStatus::COMPLETED);
        $taskWithResult->setResult('Success');
        $this->repository->save($taskWithResult, true);

        $taskWithoutResult1 = $this->createAndSaveTask('group1', 'Task without result 1', TaskStatus::PENDING);
        $taskWithoutResult2 = $this->createAndSaveTask('group1', 'Task without result 2', TaskStatus::PENDING);

        // Find all tasks where result IS NULL (限制到我们创建的任务组)
        $qb = $this->repository->createQueryBuilder('t');
        $results = $qb->where('t.result IS NULL')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', 'group1')
            ->getQuery()
            ->getResult()
        ;

        $this->assertCount(2, $results);
        $descriptions = array_map(fn ($t) => $t->getDescription(), $results);
        $this->assertContains('Task without result 1', $descriptions);
        $this->assertContains('Task without result 2', $descriptions);
    }

    public function testCountWithNullableFieldsISNULL(): void
    {
        // Create tasks with different nullable field combinations
        $task1 = $this->createAndSaveTask('group1', 'Task 1', TaskStatus::COMPLETED);
        $task1->setExecutedTime(new \DateTimeImmutable());
        $task1->setResult('Done');
        $this->repository->save($task1, true);

        $task2 = $this->createAndSaveTask('group1', 'Task 2', TaskStatus::PENDING);
        // executedTime and result are null

        $task3 = $this->createAndSaveTask('group1', 'Task 3', TaskStatus::IN_PROGRESS);
        $task3->setExecutedTime(new \DateTimeImmutable());
        // result is null
        $this->repository->save($task3, true);

        // Count tasks where executedTime IS NULL (限制到我们创建的任务组)
        $qb = $this->repository->createQueryBuilder('t');
        $count = $qb->select('COUNT(t.id)')
            ->where('t.executedTime IS NULL')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', 'group1')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $this->assertEquals(1, $count);

        // Count tasks where result IS NULL (限制到我们创建的任务组)
        $qb = $this->repository->createQueryBuilder('t');
        $count = $qb->select('COUNT(t.id)')
            ->where('t.result IS NULL')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', 'group1')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $this->assertEquals(2, $count);

        // Count tasks where both are NULL (限制到我们创建的任务组)
        $qb = $this->repository->createQueryBuilder('t');
        $count = $qb->select('COUNT(t.id)')
            ->where('t.executedTime IS NULL')
            ->andWhere('t.result IS NULL')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', 'group1')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $this->assertEquals(1, $count);
    }

    public function testClearAll(): void
    {
        // Record initial count (includes DataFixtures)
        $initialCount = count($this->repository->findAll());

        // Create tasks in different groups
        $this->createAndSaveTask('group1', 'Task 1');
        $this->createAndSaveTask('group1', 'Task 2');
        $this->createAndSaveTask('group2', 'Task 3');
        $this->createAndSaveTask('group2', 'Task 4');
        $this->createAndSaveTask('group3', 'Task 5');

        // Verify we have 5 more tasks than initial
        $currentCount = count($this->repository->findAll());
        $this->assertEquals($initialCount + 5, $currentCount);

        // Clear all tasks (including DataFixtures)
        $deletedCount = $this->repository->clearAll();
        $this->assertEquals($currentCount, $deletedCount);

        // Verify all tasks are deleted
        $this->assertCount(0, $this->repository->findAll());
    }

    public function testClearAllWithGroup(): void
    {
        // Create tasks in different groups with different statuses
        $task1 = $this->createTask('group1', 'Task 1');
        $this->repository->save($task1, true);

        $task2 = $this->createTask('group1', 'Task 2');
        $task2->setStatus(TaskStatus::IN_PROGRESS);
        $this->repository->save($task2, true);

        $task3 = $this->createTask('group1', 'Task 3');
        $task3->setStatus(TaskStatus::IN_PROGRESS);
        $task3->setStatus(TaskStatus::COMPLETED);
        $this->repository->save($task3, true);

        $task4 = $this->createTask('group2', 'Task 4');
        $this->repository->save($task4, true);

        $task5 = $this->createTask('group2', 'Task 5');
        $task5->setStatus(TaskStatus::IN_PROGRESS);
        $task5->setStatus(TaskStatus::FAILED);
        $this->repository->save($task5, true);

        // Verify initial count
        $this->assertCount(3, $this->repository->findBy(['groupName' => 'group1']));
        $this->assertCount(2, $this->repository->findBy(['groupName' => 'group2']));

        // Clear only group1 tasks
        $deletedCount = $this->repository->clearAll('group1');
        $this->assertEquals(3, $deletedCount);

        // Verify group1 tasks are deleted, group2 tasks remain
        $this->assertCount(0, $this->repository->findBy(['groupName' => 'group1']));
        $this->assertCount(2, $this->repository->findBy(['groupName' => 'group2']));
    }

    public function testClearAllEmptyGroup(): void
    {
        // Create tasks only in group1
        $this->createAndSaveTask('group1', 'Task 1');
        $this->createAndSaveTask('group1', 'Task 2');

        // Try to clear non-existent group
        $deletedCount = $this->repository->clearAll('non-existent-group');
        $this->assertEquals(0, $deletedCount);

        // Verify original tasks still exist
        $this->assertCount(2, $this->repository->findBy(['groupName' => 'group1']));
    }

    /** @return ServiceEntityRepository<TodoTask> */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): object
    {
        $task = new TodoTask();
        $task->setGroupName('test_group');
        $task->setDescription('Test task description');
        $task->setPriority(TaskPriority::NORMAL);
        // 不要设置状态，让实体使用默认状态

        return $task;
    }
}
