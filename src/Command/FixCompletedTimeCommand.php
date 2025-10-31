<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;

#[AsCommand(
    name: self::NAME,
    description: '修复已完成任务缺失的完成时间',
    hidden: false
)]
class FixCompletedTimeCommand extends Command
{
    public const NAME = 'claude-todo:fix-completed-time';

    public function __construct(
        private TodoTaskRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                '只显示将要修复的任务，不执行实际修改'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');

        $io->title('修复已完成任务的完成时间');

        // 查找状态为已完成但完成时间为空的任务
        $qb = $this->repository->createQueryBuilder('t');
        $qb->where('t.status = :status')
            ->andWhere('t.completedTime IS NULL')
            ->setParameter('status', TaskStatus::COMPLETED)
        ;

        $result = $qb->getQuery()->getResult();
        assert(is_array($result));
        /** @var array<TodoTask> $tasksToFix */
        $tasksToFix = $result;

        if ([] === $tasksToFix) {
            $io->success('没有需要修复的任务');

            return Command::SUCCESS;
        }

        $io->info(sprintf('找到 %d 个需要修复的任务', count($tasksToFix)));

        $headers = ['ID', '分组', '描述', '更新时间'];
        $rows = [];

        foreach ($tasksToFix as $task) {
            $rows[] = [
                $task->getId(),
                $task->getGroupName(),
                substr($task->getDescription(), 0, 50) . (strlen($task->getDescription()) > 50 ? '...' : ''),
                null !== $task->getUpdatedTime() ? $task->getUpdatedTime()->format('Y-m-d H:i:s') : '无',
            ];
        }

        $io->table($headers, $rows);

        if (true === $isDryRun) {
            $io->warning('这是试运行模式，没有执行实际修改');

            return Command::SUCCESS;
        }

        if (!$io->confirm('是否继续修复这些任务？', true)) {
            $io->warning('操作已取消');

            return Command::SUCCESS;
        }

        $fixedCount = 0;
        foreach ($tasksToFix as $task) {
            // 使用更新时间作为完成时间，如果没有更新时间则使用当前时间
            $completedTime = $task->getUpdatedTime() ?? new \DateTimeImmutable();
            $task->setCompletedTime($completedTime);
            ++$fixedCount;
        }

        $this->entityManager->flush();

        $io->success(sprintf('成功修复 %d 个任务的完成时间', $fixedCount));

        return Command::SUCCESS;
    }
}
