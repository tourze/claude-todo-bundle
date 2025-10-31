<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Event\TaskCreatedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(TaskCreatedEvent::class)]
final class TaskCreatedEventTest extends AbstractEventTestCase
{
    public function testConstructorAndGetters(): void
    {
        $task = $this->createMock(TodoTask::class);
        $event = new TaskCreatedEvent($task);

        $this->assertSame($task, $event->getTask());
    }

    protected function createEvent(): object
    {
        $task = $this->createMock(TodoTask::class);

        return new TaskCreatedEvent($task);
    }
}
