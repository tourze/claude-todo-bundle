<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Event\TaskFailedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(TaskFailedEvent::class)]
final class TaskFailedEventTest extends AbstractEventTestCase
{
    public function testConstructorAndGetters(): void
    {
        $task = $this->createMock(TodoTask::class);
        $exception = new \RuntimeException('Test exception');
        $event = new TaskFailedEvent($task, $exception);

        $this->assertSame($task, $event->getTask());
        $this->assertSame($exception, $event->getException());
    }

    protected function createEvent(): object
    {
        $task = $this->createMock(TodoTask::class);
        $exception = new \RuntimeException('Test exception');

        return new TaskFailedEvent($task, $exception);
    }
}
