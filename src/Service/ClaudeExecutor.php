<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Event\TaskExecutedEvent;
use Tourze\ClaudeTodoBundle\Event\TaskFailedEvent;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Exception\UsageLimitException;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;

class ClaudeExecutor implements ClaudeExecutorInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private ConfigManager $configManager,
        private EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function execute(TodoTask $task, array $options = []): ExecutionResult
    {
        $startTime = microtime(true);

        try {
            $process = $this->createProcess($task, $options);
            $this->logCommandExecution($task, $process);

            $processResult = $this->runProcess($process, $options);
            $executionTime = microtime(true) - $startTime;

            $this->checkForUsageLimitErrors($processResult);

            if (!$process->isSuccessful()) {
                return $this->handleProcessFailure($task, $processResult, $executionTime);
            }

            return $this->handleProcessSuccess($task, $processResult, $executionTime);
        } catch (\Exception $e) {
            $this->handleExecutionException($task, $e, $startTime);
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createProcess(TodoTask $task, array $options): Process
    {
        $command = $this->buildCommand($task, $options);
        $process = new Process($command);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $projectRoot = $this->configManager->getProjectRoot();
        if (null !== $projectRoot) {
            $process->setWorkingDirectory($projectRoot);
        }

        /** @var array<string, string> $env */
        $env = $_ENV;
        $process->setEnv($env);

        return $process;
    }

    private function logCommandExecution(TodoTask $task, Process $process): void
    {
        $this->logger->info('Executing Claude CLI command', [
            'task_id' => $task->getId(),
            'command' => $process->getCommandLine(),
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{output: string, errorOutput: string}
     */
    private function runProcess(Process $process, array $options): array
    {
        $output = '';
        $errorOutput = '';
        $streamOutput = (bool) ($options['stream_output'] ?? false);

        $process->run(function (string $type, string $buffer) use (&$output, &$errorOutput, $streamOutput): void {
            $result = $this->handleProcessOutput($type, $buffer, $streamOutput);
            $output .= $result['output'];
            $errorOutput .= $result['errorOutput'];
        });

        return ['output' => $output, 'errorOutput' => $errorOutput];
    }

    /**
     * @return array{output: string, errorOutput: string}
     */
    private function handleProcessOutput(string $type, string $buffer, bool $streamOutput): array
    {
        if (Process::ERR === $type) {
            $this->logger->debug('Claude CLI stderr', ['buffer' => $buffer]);

            return ['output' => '', 'errorOutput' => $buffer];
        }

        if ($streamOutput) {
            echo $buffer;
            flush();
        }

        $this->logger->debug('Claude CLI stdout', ['buffer' => $buffer]);

        return ['output' => $buffer, 'errorOutput' => ''];
    }

    /**
     * @param array{output: string, errorOutput: string} $processResult
     */
    private function checkForUsageLimitErrors(array $processResult): void
    {
        $combinedOutput = $processResult['output'] . "\n" . $processResult['errorOutput'];
        if ($this->isUsageLimitError($combinedOutput)) {
            $waitUntil = $this->parseWaitTime($combinedOutput);
            throw UsageLimitException::withWaitTime($waitUntil);
        }
    }

    /**
     * @param array{output: string, errorOutput: string} $processResult
     */
    private function handleProcessFailure(TodoTask $task, array $processResult, float $executionTime): never
    {
        $exception = ExecutionException::forTask(
            $task->getId() ?? 0,
            '' !== $processResult['errorOutput'] ? $processResult['errorOutput'] : 'Unknown error'
        );

        $this->eventDispatcher->dispatch(new TaskFailedEvent($task, $exception));
        throw $exception;
    }

    /**
     * @param array{output: string, errorOutput: string} $processResult
     */
    private function handleProcessSuccess(TodoTask $task, array $processResult, float $executionTime): ExecutionResult
    {
        if ($this->isUsageLimitError($processResult['output'])) {
            $waitUntil = $this->parseWaitTime($processResult['output']);
            throw UsageLimitException::withWaitTime($waitUntil);
        }

        $textContent = $this->extractTextFromStreamJson($processResult['output']);
        $result = ExecutionResult::success($textContent, $executionTime);

        $this->eventDispatcher->dispatch(new TaskExecutedEvent($task, $result));

        return $result;
    }

    private function handleExecutionException(TodoTask $task, \Exception $e, float $startTime): void
    {
        if (!($e instanceof UsageLimitException)) {
            $this->eventDispatcher->dispatch(new TaskFailedEvent($task, $e));
        }
    }

    public function isAvailable(): bool
    {
        $command = [$this->configManager->getClaudePath(), '--version'];

        $process = new Process($command);
        $process->setTimeout(5);

        // Set working directory to project root
        $projectRoot = $this->configManager->getProjectRoot();
        if (null !== $projectRoot) {
            $process->setWorkingDirectory($projectRoot);
        }

        // Pass current environment variables
        /** @var array<string, string> $env */
        $env = $_ENV;
        $process->setEnv($env);

        try {
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check Claude CLI availability', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    private function buildCommand(TodoTask $task, array $options): array
    {
        $command = [
            $this->configManager->getClaudePath(),
            '--dangerously-skip-permissions',
            '--print',
            '--output-format=stream-json',
            '--model=' . (string) ($options['model'] ?? $this->configManager->getClaudeModel()),
            '--verbose',
        ];

        // Add extra arguments from config
        $extraArgs = $this->configManager->getExtraArgs();
        if ([] !== $extraArgs) {
            array_push($command, ...$extraArgs);
        }

        // Add the task description as prompt
        $command[] = $task->getDescription();

        return $command;
    }

    private function isUsageLimitError(string $output): bool
    {
        // Check for the specific pattern used by Claude CLI
        return str_contains($output, 'Claude AI usage limit reached')
            || str_contains($output, 'Request not allowed')
            || str_contains($output, 'usage limit')
            || str_contains($output, 'rate limit')
            || str_contains($output, 'quota exceeded');
    }

    private function parseWaitTime(string $output): int
    {
        // First, try to parse the specific format: "Claude AI usage limit reached|timestamp"
        if (1 === preg_match('/Claude AI usage limit reached\|(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        // Try to parse wait time from error message
        // Example patterns: "wait 5 minutes", "retry after 300 seconds", "available in 1 hour"
        if (1 === preg_match('/(\d+)\s*(minutes?|mins?)/i', $output, $matches)) {
            return time() + ((int) $matches[1] * 60);
        }

        if (1 === preg_match('/(\d+)\s*(seconds?|secs?)/i', $output, $matches)) {
            return time() + (int) $matches[1];
        }

        if (1 === preg_match('/(\d+)\s*(hours?|hrs?)/i', $output, $matches)) {
            return time() + ((int) $matches[1] * 3600);
        }

        // Default wait time: 5 minutes
        return time() + 300;
    }

    private function extractTextFromStreamJson(string $output): string
    {
        $textContent = '';
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $json = $this->parseJsonLine($line);
            if (null === $json) {
                continue;
            }

            $textContent .= $this->extractTextFromJsonMessage($json);
        }

        return trim($textContent);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonLine(string $line): ?array
    {
        $line = trim($line);
        if ('' === $line) {
            return null;
        }

        $json = @json_decode($line, true);

        return is_array($json) ? $json : null;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function extractTextFromJsonMessage(array $json): string
    {
        if (!isset($json['type'])) {
            return '';
        }

        $type = $json['type'];
        assert(is_string($type));

        return match ($type) {
            'assistant' => $this->extractTextFromAssistantMessage($json),
            'text' => is_string($json['text'] ?? '') ? ($json['text'] ?? '') : '',
            'result' => $this->extractTextFromResultMessage($json),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $json
     */
    private function extractTextFromAssistantMessage(array $json): string
    {
        if (!isset($json['message']['content']) || !is_array($json['message']['content'])) {
            return '';
        }

        $textContent = '';
        foreach ($json['message']['content'] as $content) {
            if (!is_array($content)) {
                continue;
            }
            if (isset($content['type']) && 'text' === $content['type'] && is_string($content['text'])) {
                $textContent .= $content['text'] . "\n";
            }
        }

        return $textContent;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function extractTextFromResultMessage(array $json): string
    {
        if (isset($json['result']) && is_string($json['result'])) {
            return $json['result'] . "\n";
        }

        return '';
    }
}
