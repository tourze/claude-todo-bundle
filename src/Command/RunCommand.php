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
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;
use Tourze\ClaudeTodoBundle\Exception\UsageLimitException;
use Tourze\ClaudeTodoBundle\Service\ClaudeExecutorInterface;
use Tourze\ClaudeTodoBundle\Service\ConfigManager;
use Tourze\ClaudeTodoBundle\Service\SleepServiceInterface;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;

#[AsCommand(name: self::NAME, description: '执行指定的TODO任务', help: <<<'TXT'
    The <info>%command.name%</info> command executes a specific TODO task:

      <info>php %command.full_name% 123</info>

    You can specify Claude model:

      <info>php %command.full_name% 123 --model=claude-sonnet-4-20250514</info>

    Set max retry attempts for usage limits:

      <info>php %command.full_name% 123 --max-attempts=5</info>
    TXT)]
class RunCommand extends Command
{
    public const NAME = 'claude-todo:run';

    public function __construct(
        private TodoManagerInterface $todoManager,
        private ClaudeExecutorInterface $claudeExecutor,
        private ConfigManager $configManager,
        private SleepServiceInterface $sleepService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('task-id', InputArgument::REQUIRED, '任务ID')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Claude模型')
            ->addOption('max-attempts', null, InputOption::VALUE_REQUIRED, '最大重试次数')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $taskId = (int) $input->getArgument('task-id');
        $model = $input->getOption('model');
        $maxAttempts = (int) ($input->getOption('max-attempts') ?? $this->configManager->getMaxAttempts());

        $task = $this->getTask($io, $taskId);
        if (null === $task) {
            return Command::FAILURE;
        }

        if (!$this->prepareTaskForExecution($io, $task)) {
            return Command::FAILURE;
        }

        $this->displayTaskInfo($io, $task, $model);

        $options = $this->buildExecutionOptions($model);

        return $this->executeTaskWithRetries($io, $task, $options, $maxAttempts);
    }

    private function getTask(SymfonyStyle $io, int $taskId): ?TodoTask
    {
        try {
            return $this->todoManager->getTask($taskId);
        } catch (TaskNotFoundException $e) {
            $io->error($e->getMessage());

            return null;
        }
    }

    private function prepareTaskForExecution(SymfonyStyle $io, TodoTask $task): bool
    {
        if (TaskStatus::PENDING === $task->getStatus()) {
            return $this->transitionTaskToInProgress($io, $task);
        }

        if (TaskStatus::IN_PROGRESS !== $task->getStatus()) {
            $io->warning(sprintf('Task %d cannot be executed (current status: %s)', $task->getId(), $task->getStatus()->value));
            $io->note('Only tasks with status "pending" or "in_progress" can be executed.');

            return false;
        }

        return true;
    }

    private function transitionTaskToInProgress(SymfonyStyle $io, TodoTask $task): bool
    {
        try {
            $this->todoManager->updateTaskStatus($task, TaskStatus::IN_PROGRESS);
            $io->info('Task status updated from pending to in_progress');

            return true;
        } catch (\Exception $e) {
            $io->error('Failed to update task status: ' . $e->getMessage());

            return false;
        }
    }

    private function displayTaskInfo(SymfonyStyle $io, TodoTask $task, ?string $model): void
    {
        $io->info(sprintf('Executing task %d from group "%s"', $task->getId(), $task->getGroupName()));
        $io->section('Task Description');
        $io->writeln($task->getDescription());
        $io->newLine();

        $this->displayExecutionEnvironment($io, $model);
    }

    private function displayExecutionEnvironment(SymfonyStyle $io, ?string $model): void
    {
        $io->section('Execution Environment');
        $projectRoot = $this->configManager->getProjectRoot();
        $io->writeln('<info>Current Directory:</info> ' . ($projectRoot ?? getcwd()));
        $io->writeln('<info>Claude Path:</info> ' . $this->configManager->getClaudePath());
        $io->writeln('<info>Claude Model:</info> ' . ($model ?? $this->configManager->getClaudeModel()));
        $io->writeln('<info>PHP Version:</info> ' . PHP_VERSION);

        $this->displayEnvironmentVariables($io);
    }

    private function displayEnvironmentVariables(SymfonyStyle $io): void
    {
        $relevantEnvVars = [
            'CLAUDE_TODO_CLI_PATH',
            'CLAUDE_TODO_MODEL',
            'CLAUDE_TODO_PROJECT_ROOT',
            'CLAUDE_TODO_EXTRA_ARGS',
            'PATH',
            'HOME',
            'USER',
        ];

        $io->newLine();
        $io->writeln('<info>Environment Variables:</info>');
        foreach ($relevantEnvVars as $var) {
            $envValue = getenv($var);
            $value = false !== $envValue ? $envValue : '<not set>';
            if (strlen($value) > 100) {
                $value = substr($value, 0, 97) . '...';
            }
            $io->writeln(sprintf('  %s: %s', $var, $value));
        }
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

    /** @param array<string, mixed> $options */
    private function executeTaskWithRetries(SymfonyStyle $io, TodoTask $task, array $options, int $maxAttempts): int
    {
        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            $result = $this->executeTaskAttempt($io, $task, $options, $attempt, $maxAttempts);

            if (Command::SUCCESS === $result) {
                return Command::SUCCESS;
            }

            if (Command::FAILURE === $result) {
                return Command::FAILURE;
            }

            // Continue with next attempt for retriable errors
        }

        return Command::FAILURE;
    }

    /** @param array<string, mixed> $options */
    private function executeTaskAttempt(SymfonyStyle $io, TodoTask $task, array $options, int $attempt, int $maxAttempts): int
    {
        try {
            $io->section(sprintf('Execution (Attempt %d/%d)', $attempt, $maxAttempts));
            $io->writeln('<comment>Starting Claude execution...</comment>');
            $io->writeln(str_repeat('-', 80));

            $result = $this->claudeExecutor->execute($task, $options);

            $io->writeln(str_repeat('-', 80));
            $io->newLine();

            $this->todoManager->updateTaskStatus($task, TaskStatus::COMPLETED, $result->getOutput());
            $io->success(sprintf('Task completed successfully! %s', $result->getSummary()));

            return Command::SUCCESS;
        } catch (UsageLimitException $e) {
            return $this->handleUsageLimitException($io, $e, $attempt, $maxAttempts);
        } catch (ExecutionException $e) {
            return $this->handleExecutionException($io, $task, $e);
        } catch (\Exception $e) {
            return $this->handleUnexpectedException($io, $task, $e);
        }
    }

    private function handleUsageLimitException(SymfonyStyle $io, UsageLimitException $e, int $attempt, int $maxAttempts): int
    {
        if ($attempt >= $maxAttempts) {
            $io->error('Max retry attempts reached. Task remains in progress.');

            return Command::FAILURE;
        }

        $io->warning($e->getMessage());
        $this->waitWithCountdown($io, $e->getWaitSeconds());

        $io->info('Adding random delay...');
        $this->sleepService->randomSleep();

        return -1; // Continue to next attempt
    }

    private function handleExecutionException(SymfonyStyle $io, TodoTask $task, ExecutionException $e): int
    {
        $this->todoManager->updateTaskStatus($task, TaskStatus::FAILED, $e->getMessage());
        $io->error('Task execution failed: ' . $e->getMessage());

        return Command::FAILURE;
    }

    private function handleUnexpectedException(SymfonyStyle $io, TodoTask $task, \Exception $e): int
    {
        $this->todoManager->updateTaskStatus($task, TaskStatus::FAILED, 'Unexpected error: ' . $e->getMessage());
        $io->error('Unexpected error: ' . $e->getMessage());

        return Command::FAILURE;
    }

    private function waitWithCountdown(SymfonyStyle $io, int $seconds): void
    {
        $io->section('Waiting for rate limit reset');

        while ($seconds > 0) {
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
        $io->info('Wait time completed. Retrying...');
    }
}
