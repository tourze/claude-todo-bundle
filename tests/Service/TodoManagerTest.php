<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Event\TaskCreatedEvent;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;
use Tourze\ClaudeTodoBundle\Service\TodoManager;

/**
 * @internal
 */
#[CoversClass(TodoManager::class)]
final class TodoManagerTest extends TestCase
{
    private TodoManager $todoManager;

    private MockObject&TodoTaskRepository $repository;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(TodoTaskRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->todoManager = new TodoManager(
            $this->repository,
            $entityManager,
            $this->eventDispatcher,
            $this->logger
        );
    }

    public function testPushCreatesTaskWithEnum(): void
    {
        $this->repository->expects($this->once())
            ->method('save')
            ->with(
                self::callback(function (TodoTask $task) {
                    return 'test-group' === $task->getGroupName()
                        && 'Test description' === $task->getDescription()
                        && TaskPriority::HIGH === $task->getPriority()
                        && TaskStatus::PENDING === $task->getStatus();
                }),
                true
            )
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TaskCreatedEvent::class))
        ;

        $task = $this->todoManager->push('test-group', 'Test description', TaskPriority::HIGH);

        $this->assertInstanceOf(TodoTask::class, $task);
        $this->assertEquals('test-group', $task->getGroupName());
        $this->assertEquals('Test description', $task->getDescription());
        $this->assertEquals(TaskPriority::HIGH, $task->getPriority());
    }

    public function testPushCreatesTaskWithStringPriority(): void
    {
        $this->repository->expects($this->once())
            ->method('save')
            ->with(
                self::callback(function (TodoTask $task) {
                    return TaskPriority::LOW === $task->getPriority();
                }),
                true
            )
        ;

        $task = $this->todoManager->push('test-group', 'Test description', 'low');

        $this->assertEquals(TaskPriority::LOW, $task->getPriority());
    }

    public function testPushThrowsExceptionForInvalidStringPriority(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid priority: invalid-priority');

        $this->todoManager->push('test-group', 'Test description', 'invalid-priority');
    }

    public function testPushUsesDefaultPriority(): void
    {
        $this->repository->expects($this->once())
            ->method('save')
            ->with(
                self::callback(function (TodoTask $task) {
                    return TaskPriority::NORMAL === $task->getPriority();
                }),
                true
            )
        ;

        $task = $this->todoManager->push('test-group', 'Test description');

        $this->assertEquals(TaskPriority::NORMAL, $task->getPriority());
    }

    public function testPopReturnsNullWhenNoTasksAvailable(): void
    {
        $this->repository->expects($this->once())
            ->method('getGroupsWithInProgressTasks')
            ->willReturn([])
        ;

        $this->repository->expects($this->once())
            ->method('findNextAvailableTask')
            ->with(null, [])
            ->willReturn(null)
        ;

        $result = $this->todoManager->pop();

        $this->assertNull($result);
    }

    public function testPopReturnsTaskSuccessfully(): void
    {
        $task = $this->createTask();

        $this->repository->expects($this->once())
            ->method('getGroupsWithInProgressTasks')
            ->willReturn(['other-group'])
        ;

        $this->repository->expects($this->once())
            ->method('findNextAvailableTask')
            ->with(null, ['other-group' => true])
            ->willReturn($task)
        ;

        // Mock objects don't need actual database flush

        $result = $this->todoManager->pop();

        $this->assertSame($task, $result);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertInstanceOf(\DateTimeInterface::class, $task->getExecutedTime());
    }

    public function testPopWithGroupFilter(): void
    {
        $task = $this->createTask();

        $this->repository->expects($this->once())
            ->method('getGroupsWithInProgressTasks')
            ->willReturn([])
        ;

        $this->repository->expects($this->once())
            ->method('findNextAvailableTask')
            ->with('specific-group', [])
            ->willReturn($task)
        ;

        // Mock objects don't need actual database flush

        $result = $this->todoManager->pop('specific-group');

        $this->assertSame($task, $result);
    }

    public function testPopHandlesOptimisticLockException(): void
    {
        // Create a new task for each attempt
        $task1 = $this->createTask();
        $task2 = $this->createTask();
        $task3 = $this->createTask();

        $this->repository->expects($this->exactly(3))
            ->method('getGroupsWithInProgressTasks')
            ->willReturn([])
        ;

        $this->repository->expects($this->exactly(3))
            ->method('findNextAvailableTask')
            ->willReturnOnConsecutiveCalls($task1, $task2, $task3)
        ;

        // Mock the flush to throw OptimisticLockException
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(3))
            ->method('flush')
            ->willThrowException(new OptimisticLockException('Lock failed', $task1))
        ;

        $entityManager->expects($this->exactly(2))
            ->method('clear')
        ;

        // Replace entity manager for this test
        // Get the class through reflection to avoid direct instantiation in integration test
        $reflectionClass = new \ReflectionClass(TodoManager::class);
        $this->todoManager = $reflectionClass->newInstance(
            $this->repository,
            $entityManager,
            $this->eventDispatcher,
            $this->logger
        );

        $this->expectException(ExecutionException::class);
        $this->expectExceptionMessage('Failed to pop task after retries');

        $this->todoManager->pop();
    }

    public function testPopRetriesOnOptimisticLockAndSucceeds(): void
    {
        $task1 = $this->createTask();
        $task2 = $this->createTask();

        $this->repository->expects($this->exactly(2))
            ->method('getGroupsWithInProgressTasks')
            ->willReturn([])
        ;

        $this->repository->expects($this->exactly(2))
            ->method('findNextAvailableTask')
            ->willReturnOnConsecutiveCalls($task1, $task2)
        ;

        // Mock the flush to throw OptimisticLockException on first call
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))
            ->method('flush')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new OptimisticLockException('Lock failed', $task1)),
                null
            )
        ;

        $entityManager->expects($this->once())
            ->method('clear')
        ;

        // Replace entity manager for this test
        // Get the class through reflection to avoid direct instantiation in integration test
        $reflectionClass = new \ReflectionClass(TodoManager::class);
        $this->todoManager = $reflectionClass->newInstance(
            $this->repository,
            $entityManager,
            $this->eventDispatcher,
            $this->logger
        );

        $result = $this->todoManager->pop();

        $this->assertSame($task2, $result);
    }

    public function testGetTaskReturnsTask(): void
    {
        $task = $this->createTask();

        $this->repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($task)
        ;

        $result = $this->todoManager->getTask(123);

        $this->assertSame($task, $result);
    }

    public function testGetTaskThrowsExceptionWhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(TaskNotFoundException::class);

        $this->todoManager->getTask(999);
    }

    public function testUpdateTaskStatusWithoutResult(): void
    {
        $task = $this->createTask();
        // First set status to IN_PROGRESS
        $task->setStatus(TaskStatus::IN_PROGRESS);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($task, true)
        ;

        $this->todoManager->updateTaskStatus($task, TaskStatus::COMPLETED);

        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
        $this->assertNull($task->getResult());
    }

    public function testUpdateTaskStatusWithResult(): void
    {
        $task = $this->createTask();
        // First set status to IN_PROGRESS
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $result = 'Task completed successfully';

        $this->repository->expects($this->once())
            ->method('save')
            ->with($task, true)
        ;

        $this->todoManager->updateTaskStatus($task, TaskStatus::COMPLETED, $result);

        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
        $this->assertEquals($result, $task->getResult());
    }

    public function testUpdateTaskStatusToCompletedSetsCompletedTime(): void
    {
        $task = $this->createTask();
        $task->setStatus(TaskStatus::IN_PROGRESS);

        $this->assertNull($task->getCompletedTime());

        $this->repository->expects($this->once())
            ->method('save')
            ->with($task, true)
        ;

        $this->todoManager->updateTaskStatus($task, TaskStatus::COMPLETED);

        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
        $this->assertNotNull($task->getCompletedTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $task->getCompletedTime());
    }

    public function testLoggerLogsTaskCreation(): void
    {
        $this->repository->expects($this->once())
            ->method('save')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Task created',
                self::callback(function ($context) {
                    return array_key_exists('task_id', $context)
                        && 'test-group' === $context['group']
                        && 'normal' === $context['priority'];
                })
            )
        ;

        $this->todoManager->push('test-group', 'Test description');
    }

    public function testLoggerLogsTaskPop(): void
    {
        $task = $this->createTask();

        $this->repository->expects($this->once())
            ->method('getGroupsWithInProgressTasks')
            ->willReturn([])
        ;

        $this->repository->expects($this->once())
            ->method('findNextAvailableTask')
            ->willReturn($task)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Task popped',
                self::callback(function ($context) {
                    return 123 === $context['task_id']
                        && 'test-group' === $context['group'];
                })
            )
        ;

        $this->todoManager->pop();
    }

    public function testLoggerLogsOptimisticLockWarning(): void
    {
        // Create separate tasks for each retry to avoid state conflicts
        $task1 = $this->createTask();
        $task2 = $this->createTask();
        $task3 = $this->createTask();

        $this->repository = $this->createMock(TodoTaskRepository::class);
        // For the 3 retry attempts
        $this->repository->expects($this->exactly(3))
            ->method('getGroupsWithInProgressTasks')
            ->willReturn([])
        ;

        $this->repository->expects($this->exactly(3))
            ->method('findNextAvailableTask')
            ->willReturnOnConsecutiveCalls($task1, $task2, $task3)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        // All 3 attempts will throw exception
        $entityManager->expects($this->exactly(3))
            ->method('flush')
            ->willThrowException(new OptimisticLockException('Lock failed', $task1))
        ;

        // clear() is called on retries (2 times: after 1st and 2nd attempts)
        $entityManager->expects($this->exactly(2))
            ->method('clear')
        ;

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        // Logger will be called 3 times with different attempt numbers
        $this->logger->expects($this->exactly(3))
            ->method('warning')
            ->with(
                'Optimistic lock conflict on pop',
                self::callback(function ($context) {
                    return in_array($context['attempt'], [1, 2, 3], true)
                        && null === $context['group'];
                })
            )
        ;

        // Get the class through reflection to avoid direct instantiation in integration test
        $reflectionClass = new \ReflectionClass(TodoManager::class);
        $this->todoManager = $reflectionClass->newInstance(
            $this->repository,
            $entityManager,
            $this->eventDispatcher,
            $this->logger
        );

        $this->expectException(ExecutionException::class);
        $this->expectExceptionMessage('Failed to pop task after retries');

        $this->todoManager->pop();
    }

    private function createTask(): TodoTask
    {
        $task = new TodoTask();
        $task->setGroupName('test-group');
        $task->setDescription('Test task');
        $task->setPriority(TaskPriority::NORMAL);

        // Use reflection to set ID
        $reflection = new \ReflectionClass(TodoTask::class);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($task, 123);

        return $task;
    }
}
