<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Command;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;

#[AsCommand(name: self::NAME, description: '列出所有任务', help: <<<'TXT'
    The <info>%command.name%</info> command lists all tasks:

    List pending and in-progress tasks (default):
      <info>php %command.full_name%</info>

    List tasks from specific group:
      <info>php %command.full_name% backend</info>

    List all tasks including completed and failed:
      <info>php %command.full_name% --all</info>

    List only completed tasks:
      <info>php %command.full_name% --status=completed</info>

    List multiple statuses:
      <info>php %command.full_name% --status=pending --status=failed</info>
    TXT)]
class ListCommand extends Command
{
    public const NAME = 'claude-todo:list';

    public function __construct(
        private TodoTaskRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('group', InputArgument::OPTIONAL, '指定任务分组（可选）')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '过滤任务状态', ['pending', 'in_progress'])
            ->addOption('all', 'a', InputOption::VALUE_NONE, '显示所有状态的任务')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '限制显示数量', '50')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $group = $input->getArgument('group');
        assert(is_string($group) || is_null($group));

        $statusFilters = $this->getStatusFilters($input);
        $limitOption = $input->getOption('limit');
        assert(is_string($limitOption) || is_null($limitOption));
        $limit = (int) $limitOption;

        $tasks = $this->findTasks($group, $statusFilters, $limit);

        if ([] === $tasks) {
            $io->info('没有找到符合条件的任务。');

            return Command::SUCCESS;
        }

        $this->displayResults($io, $output, $tasks, $statusFilters, $limit);

        return Command::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function getStatusFilters(InputInterface $input): array
    {
        $statusFilters = $input->getOption('status');
        assert(is_array($statusFilters));

        /** @var array<string> $typedStatusFilters */
        $typedStatusFilters = $statusFilters;

        $showAll = (bool) $input->getOption('all');

        if ($showAll) {
            return array_map(fn (TaskStatus $status) => $status->value, TaskStatus::cases());
        }

        return $typedStatusFilters;
    }

    /**
     * @param array<string> $statusFilters
     * @return array<TodoTask>
     */
    private function findTasks(?string $group, array $statusFilters, int $limit): array
    {
        $qb = $this->repository->createQueryBuilder('t');

        $this->applyFilters($qb, $group, $statusFilters);
        $this->applyOrdering($qb);

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        $result = $qb->getQuery()->getResult();
        assert(is_array($result));

        /** @var array<TodoTask> $result */
        return $result;
    }

    /**
     * @param array<string> $statusFilters
     */
    private function applyFilters(QueryBuilder $qb, ?string $group, array $statusFilters): void
    {
        if (null !== $group) {
            $qb->andWhere('t.groupName = :groupName')
                ->setParameter('groupName', $group)
            ;
        }

        if ([] !== $statusFilters) {
            $qb->andWhere('t.status IN (:statuses)')
                ->setParameter('statuses', $statusFilters)
            ;
        }
    }

    private function applyOrdering(QueryBuilder $qb): void
    {
        $qb->orderBy("CASE 
                WHEN t.status = 'in_progress' THEN 1
                WHEN t.status = 'pending' THEN 2
                WHEN t.status = 'completed' THEN 3
                WHEN t.status = 'failed' THEN 4
                ELSE 5
            END", 'ASC')
            ->addOrderBy("CASE 
                WHEN t.priority = 'high' THEN 3 
                WHEN t.priority = 'normal' THEN 2 
                WHEN t.priority = 'low' THEN 1 
                ELSE 0 
            END", 'DESC')
            ->addOrderBy('t.createdTime', 'DESC')
        ;
    }

    /**
     * @param array<TodoTask> $tasks
     * @param array<string> $statusFilters
     */
    private function displayResults(SymfonyStyle $io, OutputInterface $output, array $tasks, array $statusFilters, int $limit): void
    {
        $io->title(sprintf('任务列表（共 %d 个）', count($tasks)));

        $this->displayGroupStatistics($io, $output, $statusFilters);
        $this->displayTaskTable($io, $output, $tasks);
        $this->displayLimitNote($io, $tasks, $limit);
    }

    /**
     * @param array<string> $statusFilters
     */
    private function displayGroupStatistics(SymfonyStyle $io, OutputInterface $output, array $statusFilters): void
    {
        $groups = $this->repository->findAllGroupNames();
        if (count($groups) <= 1) {
            return;
        }

        $io->section('任务分组');
        $groupStats = [];
        foreach ($groups as $groupName) {
            $stats = $this->repository->getStatsByGroupAndStatuses($groupName, $statusFilters);
            $groupStats[] = [
                $groupName,
                $stats[TaskStatus::PENDING->value],
                $stats[TaskStatus::IN_PROGRESS->value],
                $stats[TaskStatus::COMPLETED->value],
                $stats[TaskStatus::FAILED->value],
                array_sum($stats),
            ];
        }

        $groupTable = new Table($output);
        $groupTable->setHeaders(['分组', '待处理', '进行中', '已完成', '失败', '总计']);
        $groupTable->addRows($groupStats);
        $groupTable->render();
    }

    /**
     * @param array<TodoTask> $tasks
     */
    private function displayTaskTable(SymfonyStyle $io, OutputInterface $output, array $tasks): void
    {
        $io->section('任务详情');

        $table = new Table($output);
        $table->setHeaders(['ID', '分组', '优先级', '状态', '描述', '创建时间', '执行时间', '完成时间']);

        foreach ($tasks as $task) {
            $table->addRow($this->formatTaskRow($task));
        }

        $table->render();
    }

    /**
     * @return array<string|int|null>
     */
    private function formatTaskRow(TodoTask $task): array
    {
        $status = TaskStatus::from($task->getStatus()->value);

        return [
            $task->getId(),
            $task->getGroupName(),
            $task->getPriority()->value,
            $this->formatStatus($status),
            $this->truncate($task->getDescription(), 50),
            $task->getCreatedTime()->format('Y-m-d H:i:s'),
            $task->getExecutedTime()?->format('Y-m-d H:i:s') ?? '-',
            $task->getCompletedTime()?->format('Y-m-d H:i:s') ?? '-',
        ];
    }

    /**
     * @param array<TodoTask> $tasks
     */
    private function displayLimitNote(SymfonyStyle $io, array $tasks, int $limit): void
    {
        if ($limit > 0 && count($tasks) === $limit) {
            $io->note(sprintf('仅显示前 %d 条记录。使用 --limit 参数调整显示数量。', $limit));
        }
    }

    private function formatStatus(TaskStatus $status): string
    {
        return $status->getColoredChineseLabel();
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }
}
