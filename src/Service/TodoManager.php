<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Event\TaskCreatedEvent;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\InvalidPriorityException;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;

readonly class TodoManager implements TodoManagerInterface
{
    public function __construct(
        #[Autowire(service: 'tourze_claude_todo.repository.todo_task')] private TodoTaskRepository $repository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function push(string $groupName, string $description, TaskPriority|string $priority = TaskPriority::NORMAL): TodoTask
    {
        // Convert string to enum if needed
        if (is_string($priority)) {
            $priorityEnum = TaskPriority::tryFrom($priority);
            if (null === $priorityEnum) {
                throw new InvalidPriorityException(sprintf('Invalid priority: %s', $priority));
            }
            $priority = $priorityEnum;
        }

        $task = new TodoTask();
        $task->setGroupName($groupName);
        $task->setDescription($description);
        $task->setPriority($priority);

        $this->repository->save($task, true);

        $this->eventDispatcher->dispatch(new TaskCreatedEvent($task));

        $this->logger->info('Task created', [
            'task_id' => $task->getId(),
            'group' => $groupName,
            'priority' => $priority->value,
        ]);

        return $task;
    }

    public function pop(?string $groupName = null): ?TodoTask
    {
        $maxRetries = 3;
        $retryDelay = 100000; // 100ms

        for ($attempt = 1; $attempt <= $maxRetries; ++$attempt) {
            try {
                // 获取有进行中任务的分组
                $groupsWithInProgress = $this->repository->getGroupsWithInProgressTasks();

                // 转换为关联数组格式 (Repository 期望 array<string, mixed>)
                $excludeGroups = array_fill_keys($groupsWithInProgress, true);

                // 查找下一个可用任务
                $task = $this->repository->findNextAvailableTask($groupName, $excludeGroups);

                if (null === $task) {
                    return null;
                }

                // 使用乐观锁更新状态
                $task->setStatus(TaskStatus::IN_PROGRESS);
                $task->setExecutedTime(new \DateTimeImmutable());

                $this->entityManager->flush();

                $this->logger->info('Task popped', [
                    'task_id' => $task->getId(),
                    'group' => $task->getGroupName(),
                ]);

                return $task;
            } catch (OptimisticLockException $e) {
                $this->logger->warning('Optimistic lock conflict on pop', [
                    'attempt' => $attempt,
                    'group' => $groupName,
                ]);

                if ($attempt < $maxRetries) {
                    usleep($retryDelay * $attempt);
                    $this->entityManager->clear();
                    continue;
                }

                throw new ExecutionException('Failed to pop task after retries', 0, $e);
            }
        }

        throw new ExecutionException('Failed to pop task');
    }

    public function getTask(int $id): TodoTask
    {
        $task = $this->repository->find($id);

        if (null === $task) {
            throw TaskNotFoundException::forId($id);
        }

        return $task;
    }

    public function updateTaskStatus(TodoTask $task, TaskStatus $status, ?string $result = null): void
    {
        if (TaskStatus::COMPLETED === $status) {
            $task->complete();
        } else {
            $task->setStatus($status);
        }

        if (null !== $result) {
            $task->setResult($result);
        }

        $this->repository->save($task, true);

        $this->logger->info('Task status updated', [
            'task_id' => $task->getId(),
            'status' => $status->value,
            'has_result' => null !== $result,
        ]);
    }
}
