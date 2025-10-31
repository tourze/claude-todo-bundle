<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Exception;

/**
 * 任务未找到异常
 */
class TaskNotFoundException extends ClaudeTodoException
{
    private ?int $taskId = null;

    public static function forId(int $id): self
    {
        $exception = new self(sprintf('Task with ID %d not found', $id));
        $exception->taskId = $id;

        return $exception;
    }

    public static function forGroupAndStatus(string $group, string $status): self
    {
        return new self(sprintf('No %s task found in group "%s"', $status, $group));
    }

    public function getTaskId(): ?int
    {
        return $this->taskId;
    }
}
