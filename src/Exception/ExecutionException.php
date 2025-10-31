<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Exception;

/**
 * 任务执行异常
 */
class ExecutionException extends ClaudeTodoException
{
    private ?int $taskId = null;

    public static function forTask(int $taskId, string $reason, ?\Throwable $previous = null): self
    {
        $exception = new self(
            sprintf('Failed to execute task %d: %s', $taskId, $reason),
            0,
            $previous
        );
        $exception->taskId = $taskId;

        return $exception;
    }

    public function getTaskId(): ?int
    {
        return $this->taskId;
    }
}
