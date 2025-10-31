<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Exception;

/**
 * 任务状态无效异常
 */
class InvalidTaskStateException extends ClaudeTodoException
{
    public static function forStateTransition(int $taskId, string $currentState, string $targetState): self
    {
        return new self(sprintf(
            'Cannot transition task %d from state "%s" to "%s"',
            $taskId,
            $currentState,
            $targetState
        ));
    }

    public static function forInvalidState(string $state): self
    {
        return new self(sprintf(
            'Invalid task state "%s". Valid states are: pending, in_progress, completed, failed',
            $state
        ));
    }
}
