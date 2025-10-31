<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;

#[AsCommand(name: self::NAME, description: '清空所有任务数据', help: <<<'TXT'
    The <info>%command.name%</info> command clears all task data:

      <info>php %command.full_name%</info>

    Clear without confirmation:

      <info>php %command.full_name% --force</info>

    Clear tasks from a specific group only:

      <info>php %command.full_name% --group=test</info>

    <warning>Warning: This action cannot be undone!</warning>
    TXT)]
class ClearCommand extends Command
{
    public const NAME = 'claude-todo:clear';

    public function __construct(
        private TodoTaskRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, '跳过确认直接清空')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, '仅清空指定分组的任务')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $group = $this->getGroupOption($input);

        $taskCount = $this->getTaskCount($group);
        if (0 === $taskCount) {
            $this->displayNoTasksMessage($io, $group);

            return Command::SUCCESS;
        }

        $this->displayTaskCountWarning($io, $taskCount, $group);

        if (!$force && !$this->confirmDeletion($io)) {
            $io->info('操作已取消。');

            return Command::SUCCESS;
        }

        return $this->clearTasks($io, $group);
    }

    private function getGroupOption(InputInterface $input): ?string
    {
        $group = $input->getOption('group');

        return (null !== $group && is_string($group)) ? $group : null;
    }

    private function getTaskCount(?string $group): int
    {
        $criteria = null !== $group ? ['groupName' => $group] : [];

        return $this->repository->count($criteria);
    }

    private function displayNoTasksMessage(SymfonyStyle $io, ?string $group): void
    {
        $message = null !== $group ? sprintf('分组 "%s" 中没有任务。', $group) : '没有任务需要清空。';
        $io->info($message);
    }

    private function displayTaskCountWarning(SymfonyStyle $io, int $taskCount, ?string $group): void
    {
        $groupText = null !== $group ? sprintf('分组 "%s" 中的', $group) : '';
        $io->warning(sprintf('即将删除 %d 个%s任务！', $taskCount, $groupText));
    }

    private function confirmDeletion(SymfonyStyle $io): bool
    {
        $question = new ConfirmationQuestion(
            '<question>确定要删除这些任务吗？此操作无法撤销！(yes/no)</question> ',
            false
        );

        return (bool) $io->askQuestion($question);
    }

    private function clearTasks(SymfonyStyle $io, ?string $group): int
    {
        try {
            $deletedCount = $this->repository->clearAll($group);
            $this->displaySuccessMessage($io, $deletedCount, $group);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('清空任务失败：' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function displaySuccessMessage(SymfonyStyle $io, int $deletedCount, ?string $group): void
    {
        $groupText = null !== $group ? sprintf('分组 "%s" 中的', $group) : '';
        $io->success(sprintf('成功删除 %d 个%s任务。', $deletedCount, $groupText));
    }
}
