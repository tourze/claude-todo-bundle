<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;

final class TaskExecutedEvent extends Event
{
    public function __construct(
        private readonly TodoTask $task,
        private readonly ExecutionResult $result,
    ) {
    }

    public function getTask(): TodoTask
    {
        return $this->task;
    }

    public function getResult(): ExecutionResult
    {
        return $this->result;
    }
}
