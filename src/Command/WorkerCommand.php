<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\UsageLimitException;
use Tourze\ClaudeTodoBundle\Service\ClaudeExecutorInterface;
use Tourze\ClaudeTodoBundle\Service\ConfigManager;
use Tourze\ClaudeTodoBundle\Service\SleepServiceInterface;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;

#[AsCommand(name: self::NAME, description: '持续监听并执行待处理的任务', help: <<<'TXT'
    The <info>%command.name%</info> command starts a worker that continuously processes tasks:

      <info>php %command.full_name%</info>

    Process tasks from a specific group:

      <info>php %command.full_name% --group=user-bundle</info>

    Set idle timeout (stop after 10 minutes of inactivity):

      <info>php %command.full_name% --idle-timeout=600</info>

    Never timeout (run indefinitely):

      <info>php %command.full_name% --idle-timeout=0</info>

    Set check interval and max attempts:

      <info>php %command.full_name% --check-interval=5 --max-attempts=5</info>

    The worker will:
    - Pop the highest priority pending task
    - Execute it using Claude CLI
    - Mark it as completed or failed
    - Continue to the next task
    - Stop when no tasks are found and idle timeout is reached
    TXT)]
class WorkerCommand extends Command
{
    public const NAME = 'claude-todo:worker';

    private bool $shouldStop = false;

    public function __construct(
        private TodoManagerInterface $todoManager,
        private ClaudeExecutorInterface $claudeExecutor,
        private ConfigManager $configManager,
        private SleepServiceInterface $sleepService,
    ) {
        parent::__construct();
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    protected function configure(): void
    {
        $this
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, '限定任务组')
            ->addOption('idle-timeout', null, InputOption::VALUE_REQUIRED, '空闲超时时间（秒），0表示永不超时', '0')
            ->addOption('check-interval', null, InputOption::VALUE_REQUIRED, '检查间隔（秒）', '3')
            ->addOption('max-attempts', null, InputOption::VALUE_REQUIRED, '单个任务最大重试次数')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Claude模型')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $config = $this->parseConfiguration($input);
        $this->setupSignalHandlers();
        $this->displayWorkerInfo($io, $config);

        $stats = $this->runWorkerLoop($io, $config);
        $this->displaySummary($io, $stats);

        return Command::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function parseConfiguration(InputInterface $input): array
    {
        return [
            'group' => $input->getOption('group'),
            'idleTimeout' => (int) $input->getOption('idle-timeout'),
            'checkInterval' => (int) $input->getOption('check-interval'),
            'maxAttempts' => (int) ($input->getOption('max-attempts') ?? $this->configManager->getMaxAttempts()),
            'model' => $input->getOption('model'),
        ];
    }

    private function setupSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, $this->handleSignal(...));
            pcntl_signal(SIGTERM, $this->handleSignal(...));
        }
    }

    /** @param array<string, mixed> $config */
    private function displayWorkerInfo(SymfonyStyle $io, array $config): void
    {
        $io->title('Claude Todo Worker');
        $io->info('Starting worker...');

        if (null !== $config['group']) {
            $io->comment(sprintf('Processing tasks from group: %s', $config['group']));
        }

        $idleTimeoutText = $config['idleTimeout'] > 0 ? $config['idleTimeout'] . ' seconds' : 'disabled';
        $io->comment(sprintf('Idle timeout: %s', $idleTimeoutText));
        $io->comment(sprintf('Check interval: %d seconds', $config['checkInterval']));
        $io->newLine();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, int>
     */
    private function runWorkerLoop(SymfonyStyle $io, array $config): array
    {
        $stats = ['processed' => 0, 'failed' => 0];
        $lastTaskTime = time();

        while (!$this->shouldStop) {
            try {
                $task = $this->getNextTask($config['group']);

                if (null === $task) {
                    $stopReason = $this->getStopReason($config['idleTimeout'], $lastTaskTime);
                    if (null !== $stopReason) {
                        $io->info($stopReason);
                        break;
                    }

                    $this->sleepService->sleep($config['checkInterval']);
                    continue;
                }

                $lastTaskTime = time();
                $success = $this->processTask($io, $task, $config);

                if ($success) {
                    ++$stats['processed'];
                    $io->success(sprintf('Task #%d completed successfully!', $task->getId()));
                } else {
                    ++$stats['failed'];
                    $io->error(sprintf('Task #%d failed after %d attempts', $task->getId(), $config['maxAttempts']));
                }

                $io->newLine();
            } catch (\Exception $e) {
                $io->error(sprintf('Unexpected error: %s', $e->getMessage()));
                $this->sleepService->sleep($config['checkInterval']);
            }
        }

        return $stats;
    }

    private function getStopReason(int $idleTimeout, int $lastTaskTime): ?string
    {
        if ($idleTimeout > 0 && (time() - $lastTaskTime) >= $idleTimeout) {
            return 'Idle timeout reached. Stopping worker.';
        }

        $stopFile = $this->configManager->getStopFile();
        if (file_exists($stopFile)) {
            return sprintf('Stop file detected (%s). Stopping worker.', $stopFile);
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function processTask(SymfonyStyle $io, TodoTask $task, array $config): bool
    {
        $this->displayTaskInfo($io, $task);

        if (TaskStatus::PENDING === $task->getStatus()) {
            $this->todoManager->updateTaskStatus($task, TaskStatus::IN_PROGRESS);
        }

        $options = $this->buildExecutionOptions($config['model']);

        return $this->executeTaskWithRetry($io, $task, $options, $config['maxAttempts']);
    }

    private function displayTaskInfo(SymfonyStyle $io, TodoTask $task): void
    {
        $io->section(sprintf('Processing Task #%d', $task->getId()));
        $io->text(sprintf('Group: %s', $task->getGroupName()));
        $io->text(sprintf('Priority: %s', $task->getPriority()->value));
        $io->text(sprintf('Description: %s', $task->getDescription()));
        $io->newLine();
    }

    /** @return array<string, mixed> */
    private function buildExecutionOptions(?string $model): array
    {
        $options = ['stream_output' => true];
        if (null !== $model) {
            $options['model'] = $model;
        }

        return $options;
    }

    /** @param array<string, int> $stats */
    private function displaySummary(SymfonyStyle $io, array $stats): void
    {
        $io->section('Worker Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Tasks Processed', $stats['processed']],
                ['Tasks Failed', $stats['failed']],
                ['Total Tasks', $stats['processed'] + $stats['failed']],
            ]
        );
    }

    private function getNextTask(?string $group): ?TodoTask
    {
        return $this->todoManager->pop($group);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeTaskWithRetry(SymfonyStyle $io, TodoTask $task, array $options, int $maxAttempts): bool
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            ++$attempt;

            try {
                $io->comment(sprintf('Execution attempt %d/%d', $attempt, $maxAttempts));
                $io->writeln(str_repeat('-', 80));

                $result = $this->claudeExecutor->execute($task, $options);

                $io->writeln(str_repeat('-', 80));
                $io->newLine();

                // Update task status to completed
                $this->todoManager->updateTaskStatus(
                    $task,
                    TaskStatus::COMPLETED,
                    $result->getOutput()
                );

                return true;
            } catch (UsageLimitException $e) {
                $waitSeconds = $e->getWaitSeconds();

                if ($attempt >= $maxAttempts) {
                    $io->error('Max retry attempts reached for usage limit.');

                    return false;
                }

                $io->warning($e->getMessage());
                $this->waitWithCountdown($io, $waitSeconds);

                // Add random delay to avoid thundering herd
                $io->info('Adding random delay...');
                $this->sleepService->randomSleep(60, 300);
            } catch (ExecutionException $e) {
                $this->todoManager->updateTaskStatus(
                    $task,
                    TaskStatus::FAILED,
                    $e->getMessage()
                );

                $io->error(sprintf('Task execution failed: %s', $e->getMessage()));

                return false;
            } catch (\Exception $e) {
                $this->todoManager->updateTaskStatus(
                    $task,
                    TaskStatus::FAILED,
                    'Unexpected error: ' . $e->getMessage()
                );

                $io->error(sprintf('Unexpected error: %s', $e->getMessage()));

                return false;
            }
        }

        return false;
    }

    private function waitWithCountdown(SymfonyStyle $io, int $seconds): void
    {
        $io->section('Waiting for rate limit reset');

        while ($seconds > 0 && !$this->shouldStop) {
            $minutes = (int) floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            $io->write(sprintf(
                "\r<comment>Waiting... %02d:%02d remaining</comment>",
                $minutes,
                $remainingSeconds
            ));

            $this->sleepService->sleep(1);
            --$seconds;
        }

        $io->writeln('');

        if (!$this->shouldStop) {
            $io->info('Wait time completed. Retrying...');
        }
    }
}
