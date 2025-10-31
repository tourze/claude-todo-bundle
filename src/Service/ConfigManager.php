<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Service;

class ConfigManager
{
    public function getClaudeModel(): string
    {
        $model = $_ENV['CLAUDE_TODO_MODEL'] ?? 'claude-sonnet-4-20250514';
        assert(is_string($model));

        return $model;
    }

    public function getClaudePath(): string
    {
        $path = $_ENV['CLAUDE_TODO_CLI_PATH'] ?? 'claude';
        assert(is_string($path));

        return $path;
    }

    public function getMaxAttempts(): int
    {
        $attempts = $_ENV['CLAUDE_TODO_MAX_ATTEMPTS'] ?? '10';
        assert(is_string($attempts) || is_int($attempts));

        return (int) $attempts;
    }

    public function getDefaultPriority(): string
    {
        $priority = $_ENV['CLAUDE_TODO_DEFAULT_PRIORITY'] ?? 'normal';
        assert(is_string($priority));

        return $priority;
    }

    public function getStopFile(): string
    {
        $stopFile = $_ENV['CLAUDE_TODO_STOP_FILE'] ?? 'claude-runner.stop';
        assert(is_string($stopFile));

        return $stopFile;
    }

    /**
     * @return array<string>
     */
    public function getExtraArgs(): array
    {
        $args = $_ENV['CLAUDE_TODO_EXTRA_ARGS'] ?? '';
        assert(is_string($args));

        return '' !== $args ? explode(' ', $args) : [];
    }

    public function getWaitTimeout(): int
    {
        $timeout = $_ENV['CLAUDE_TODO_WAIT_TIMEOUT'] ?? '300';
        assert(is_string($timeout) || is_int($timeout));

        return (int) $timeout;
    }

    public function getCheckInterval(): int
    {
        $interval = $_ENV['CLAUDE_TODO_CHECK_INTERVAL'] ?? '3';
        assert(is_string($interval) || is_int($interval));

        return (int) $interval;
    }

    public function getRetryDelay(): int
    {
        $delay = $_ENV['CLAUDE_TODO_RETRY_DELAY'] ?? '5';
        assert(is_string($delay) || is_int($delay));

        return (int) $delay;
    }

    public function getDebugMode(): bool
    {
        $debug = $_ENV['CLAUDE_TODO_DEBUG'] ?? false;

        return is_bool($debug) ? $debug : filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }

    public function getProjectRoot(): ?string
    {
        // Try to get from environment variable first
        if (isset($_ENV['CLAUDE_TODO_PROJECT_ROOT'])) {
            $projectRoot = $_ENV['CLAUDE_TODO_PROJECT_ROOT'];
            assert(is_string($projectRoot));

            return $projectRoot;
        }

        // Try to find the root composer.json (monorepo root)
        $dir = __DIR__;
        $lastComposerDir = null;

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                $lastComposerDir = $dir;
            }
            $dir = dirname($dir);
        }

        // Return the highest level composer.json directory (monorepo root)
        if (null !== $lastComposerDir) {
            return $lastComposerDir;
        }

        // Fallback to current working directory
        $cwd = getcwd();

        return false !== $cwd ? $cwd : null;
    }
}
