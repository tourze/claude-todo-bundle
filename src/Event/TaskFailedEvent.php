<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;

final class TaskFailedEvent extends Event
{
    public function __construct(
        private readonly TodoTask $task,
        private readonly \Throwable $exception,
    ) {
    }

    public function getTask(): TodoTask
    {
        return $this->task;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
