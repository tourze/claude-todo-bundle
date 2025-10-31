<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Event\TaskExecutedEvent;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(TaskExecutedEvent::class)]
final class TaskExecutedEventTest extends AbstractEventTestCase
{
    public function testConstructorAndGetters(): void
    {
        $task = $this->createMock(TodoTask::class);
        $result = ExecutionResult::success('Test output', 1.5);
        $event = new TaskExecutedEvent($task, $result);

        $this->assertSame($task, $event->getTask());
        $this->assertSame($result, $event->getResult());
    }

    protected function createEvent(): object
    {
        $task = $this->createMock(TodoTask::class);
        $result = ExecutionResult::success('Test output', 1.5);

        return new TaskExecutedEvent($task, $result);
    }
}
