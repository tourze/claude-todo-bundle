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
use Tourze\ClaudeTodoBundle\Service\ConfigManager;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;

#[AsCommand(name: self::NAME, description: '获取下一个可执行的TODO任务', help: <<<'TXT'
    The <info>%command.name%</info> command gets the next available TODO task:

      <info>php %command.full_name%</info>

    Get task from specific group:

      <info>php %command.full_name% backend</info>

    Wait for available tasks:

      <info>php %command.full_name% --wait --max-wait=600</info>
    TXT)]
class PopCommand extends Command
{
    public const NAME = 'claude-todo:pop';

    public function __construct(
        private TodoManagerInterface $todoManager,
        private ConfigManager $configManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('group', InputArgument::OPTIONAL, '指定任务分组（可选）')
            ->addOption('wait', 'w', InputOption::VALUE_NONE, '当没有任务时等待')
            ->addOption('max-wait', null, InputOption::VALUE_REQUIRED, '最大等待时间（秒）', 300)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $group = $input->getArgument('group');
        assert(is_string($group) || is_null($group));

        $wait = $input->getOption('wait');
        $maxWaitOption = $input->getOption('max-wait');
        assert(is_string($maxWaitOption) || is_int($maxWaitOption));
        $maxWait = (int) $maxWaitOption;

        $startTime = time();
        $waitedTime = 0;

        while (true) {
            $task = $this->todoManager->pop($group);

            if (null !== $task) {
                if ($waitedTime > 0) {
                    $io->info(sprintf('Found task after waiting %d seconds', $waitedTime));
                }

                $io->success('Task retrieved successfully!');

                $io->table(
                    ['Property', 'Value'],
                    [
                        ['ID', $task->getId()],
                        ['Group', $task->getGroupName()],
                        ['Priority', $task->getPriority()->value],
                        ['Description', wordwrap($task->getDescription(), 60)],
                        ['Status', $task->getStatus()->value],
                        ['Created', $task->getCreatedTime()->format('Y-m-d H:i:s')],
                    ]
                );

                $io->writeln('Task ID: ' . $task->getId());

                return Command::SUCCESS;
            }

            if (true !== $wait) {
                $io->info('No available tasks at this time.');

                return Command::SUCCESS;
            }

            $waitedTime = time() - $startTime;

            if ($waitedTime >= $maxWait) {
                $io->warning(sprintf('No tasks found after waiting %d seconds.', $maxWait));

                return Command::SUCCESS;
            }

            // Check for stop file
            $stopFile = $this->configManager->getStopFile();
            if (file_exists($stopFile)) {
                $io->warning('Stop file detected. Exiting...');
                unlink($stopFile);

                return Command::SUCCESS;
            }

            $remainingTime = $maxWait - $waitedTime;
            $io->writeln(sprintf(
                "\r<comment>Waiting for tasks... (%d/%d seconds)</comment>",
                $waitedTime,
                $maxWait
            ));

            sleep($this->configManager->getCheckInterval());
        }
    }
}
