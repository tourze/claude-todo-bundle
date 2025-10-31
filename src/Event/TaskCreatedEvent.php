<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;

final class TaskCreatedEvent extends Event
{
    public function __construct(
        private readonly TodoTask $task,
    ) {
    }

    public function getTask(): TodoTask
    {
        return $this->task;
    }
}
