<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;

#[AsCommand(name: self::NAME, description: '添加新的TODO任务', help: <<<'TXT'
    The <info>%command.name%</info> command adds a new TODO task to the queue:

      <info>php %command.full_name% backend "Implement user authentication API"</info>

    You can specify the priority:

      <info>php %command.full_name% frontend "Fix critical bug" --priority=high</info>
    TXT)]
class PushCommand extends Command
{
    public const NAME = 'claude-todo:push';

    public function __construct(
        private TodoManagerInterface $todoManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('group', InputArgument::REQUIRED, '任务分组名称')
            ->addArgument('description', InputArgument::REQUIRED, '任务描述')
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, '任务优先级 (low/normal/high)', TaskPriority::NORMAL->value)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $group = $input->getArgument('group');
        assert(is_string($group));

        $description = $input->getArgument('description');
        assert(is_string($description));

        $priority = $input->getOption('priority');
        assert(is_string($priority));

        try {
            $task = $this->todoManager->push($group, $description, $priority);

            $io->success(sprintf('Task created successfully! ID: %d', $task->getId()));

            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $task->getId()],
                    ['Group', $task->getGroupName()],
                    ['Priority', $task->getPriority()->value],
                    ['Status', $task->getStatus()->value],
                    ['Created', $task->getCreatedTime()->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Failed to create task: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
