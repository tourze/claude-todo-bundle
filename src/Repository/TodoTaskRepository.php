<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<TodoTask>
 */
#[AsRepository(entityClass: TodoTask::class)]
class TodoTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TodoTask::class);
    }

    public function save(TodoTask $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TodoTask $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return TodoTask[]
     */
    public function findPendingByGroup(string $groupName): array
    {
        return $this->findByGroupAndStatus($groupName, TaskStatus::PENDING->value);
    }

    /**
     * @return TodoTask[]
     */
    public function findByGroupAndStatus(string $groupName, string $status): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.groupName = :groupName')
            ->andWhere('t.status = :status')
            ->setParameter('groupName', $groupName)
            ->setParameter('status', $status)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return TodoTask[]
     */
    public function findInProgressByGroup(string $groupName): array
    {
        return $this->findByGroupAndStatus($groupName, TaskStatus::IN_PROGRESS->value);
    }

    /**
     * @return TodoTask[]
     */
    public function findCompletedByGroup(string $groupName): array
    {
        return $this->findByGroupAndStatus($groupName, TaskStatus::COMPLETED->value);
    }

    /**
     * @return TodoTask[]
     */
    public function findFailedByGroup(string $groupName): array
    {
        return $this->findByGroupAndStatus($groupName, TaskStatus::FAILED->value);
    }

    /**
     * @return TodoTask[]
     */
    public function findByGroupAndPriority(string $groupName, string $priority): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.groupName = :groupName')
            ->andWhere('t.priority = :priority')
            ->setParameter('groupName', $groupName)
            ->setParameter('priority', $priority)
            ->orderBy('t.createdTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return TodoTask[]
     */
    public function findRecentByGroup(string $groupName, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.groupName = :groupName')
            ->setParameter('groupName', $groupName)
            ->orderBy('t.createdTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return string[]
     */
    public function findAllGroupNames(): array
    {
        return $this->createQueryBuilder('t')
            ->select('DISTINCT t.groupName')
            ->orderBy('t.groupName', 'ASC')
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }

    public function countByGroupAndStatus(string $groupName, string $status): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.groupName = :groupName')
            ->andWhere('t.status = :status')
            ->setParameter('groupName', $groupName)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByGroup(string $groupName): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT status, COUNT(*) as cnt FROM claude_todo_tasks WHERE group_name = :groupName GROUP BY status';
        $results = $conn->fetchAllAssociative($sql, ['groupName' => $groupName]);

        $stats = [
            TaskStatus::PENDING->value => 0,
            TaskStatus::IN_PROGRESS->value => 0,
            TaskStatus::COMPLETED->value => 0,
            TaskStatus::FAILED->value => 0,
        ];

        foreach ($results as $result) {
            $status = $result['status'];
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int) $result['cnt'];
            }
        }

        return $stats;
    }

    /**
     * @param string[] $statusFilters
     * @return array<string, int>
     */
    public function getStatsByGroupAndStatuses(string $groupName, array $statusFilters): array
    {
        $stats = [
            TaskStatus::PENDING->value => 0,
            TaskStatus::IN_PROGRESS->value => 0,
            TaskStatus::COMPLETED->value => 0,
            TaskStatus::FAILED->value => 0,
        ];

        if ([] === $statusFilters) {
            return $this->getStatsByGroup($groupName);
        }

        $conn = $this->getEntityManager()->getConnection();
        $placeholders = str_repeat('?,', count($statusFilters) - 1) . '?';
        $sql = "SELECT status, COUNT(*) as cnt FROM claude_todo_tasks WHERE group_name = ? AND status IN ({$placeholders}) GROUP BY status";

        $params = [$groupName, ...$statusFilters];
        $results = $conn->fetchAllAssociative($sql, $params);

        foreach ($results as $result) {
            $status = $result['status'];
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int) $result['cnt'];
            }
        }

        return $stats;
    }

    public function deleteOldCompletedTasks(int $daysToKeep = 30): int
    {
        $threshold = new \DateTimeImmutable();
        $threshold = $threshold->modify(sprintf('-%d days', $daysToKeep));

        $qb = $this->createQueryBuilder('t');

        return $qb->delete()
            ->where('t.status = :status')
            ->andWhere('t.executedTime < :threshold')
            ->setParameter('status', TaskStatus::COMPLETED->value)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * @return TodoTask[]
     */
    public function findStuckInProgressTasks(int $hoursThreshold = 24): array
    {
        $threshold = new \DateTimeImmutable();
        $threshold = $threshold->modify(sprintf('-%d hours', $hoursThreshold));

        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.updatedTime < :threshold OR t.updatedTime IS NULL AND t.createdTime < :threshold')
            ->setParameter('status', TaskStatus::IN_PROGRESS->value)
            ->setParameter('threshold', $threshold)
            ->orderBy('t.createdTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return string[]
     */
    public function getGroupsWithInProgressTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->select('DISTINCT t.groupName')
            ->andWhere('t.status = :status')
            ->setParameter('status', TaskStatus::IN_PROGRESS->value)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }

    /**
     * @param array<string, mixed> $excludeGroups
     */
    public function findNextAvailableTask(?string $groupName, array $excludeGroups): ?TodoTask
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', TaskStatus::PENDING->value)
        ;

        if (null !== $groupName) {
            $qb->andWhere('t.groupName = :groupName')
                ->setParameter('groupName', $groupName)
            ;
        }

        if ([] !== $excludeGroups) {
            $qb->andWhere('t.groupName NOT IN (:excludeGroups)')
                ->setParameter('excludeGroups', array_keys($excludeGroups))
            ;
        }

        return $qb->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function clearAll(?string $groupName = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->delete()
        ;

        if (null !== $groupName) {
            $qb->andWhere('t.groupName = :groupName')
                ->setParameter('groupName', $groupName)
            ;
        }

        return $qb->getQuery()->execute();
    }
}
