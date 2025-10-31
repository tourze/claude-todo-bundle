<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\InvalidTaskTransitionException;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(TodoTask::class)]
final class TodoTaskTest extends AbstractEntityTestCase
{
    public function testNewTaskHasCorrectDefaults(): void
    {
        $task = new TodoTask();

        $this->assertNull($task->getId());
        $this->assertEquals(TaskStatus::PENDING, $task->getStatus());
        $this->assertEquals(TaskPriority::NORMAL, $task->getPriority());
        $this->assertInstanceOf(\DateTimeInterface::class, $task->getCreatedTime());
        $this->assertNull($task->getUpdatedTime());
        $this->assertNull($task->getExecutedTime());
        $this->assertNull($task->getCompletedTime());
        $this->assertNull($task->getResult());
        $this->assertEquals(1, $task->getVersion());
    }

    public function testSettersAndGetters(): void
    {
        $task = new TodoTask();

        $task->setGroupName('test-group');
        $this->assertEquals('test-group', $task->getGroupName());

        $task->setDescription('Test description');
        $this->assertEquals('Test description', $task->getDescription());

        $task->setPriority(TaskPriority::HIGH);
        $this->assertEquals(TaskPriority::HIGH, $task->getPriority());
    }

    public function testSetStatusUpdatesTime(): void
    {
        $task = new TodoTask();
        $this->assertNull($task->getUpdatedTime());

        $beforeUpdate = new \DateTime();
        usleep(1000); // 1毫秒延迟

        $task->setStatus(TaskStatus::IN_PROGRESS);

        $this->assertNotNull($task->getUpdatedTime());
        $this->assertGreaterThanOrEqual($beforeUpdate, $task->getUpdatedTime());
    }

    public function testInvalidStatusTransitionThrowsException(): void
    {
        $task = new TodoTask();
        // First transition to IN_PROGRESS, then to COMPLETED
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $task->setStatus(TaskStatus::COMPLETED);

        $this->expectException(InvalidTaskTransitionException::class);
        $this->expectExceptionMessage('Cannot transition from completed to pending');

        $task->setStatus(TaskStatus::PENDING);
    }

    public function testValidStatusTransitions(): void
    {
        $task = new TodoTask();

        // PENDING -> IN_PROGRESS
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->getStatus());

        // IN_PROGRESS -> COMPLETED
        $task->setStatus(TaskStatus::COMPLETED);
        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
    }

    public function testSetters(): void
    {
        $task = new TodoTask();

        // Test each setter individually
        $task->setGroupName('test');
        $task->setDescription('description');
        $task->setStatus(TaskStatus::IN_PROGRESS);
        $task->setPriority(TaskPriority::LOW);
        $task->setResult('success');
        $task->setExecutedTime(new \DateTime());

        // Verify all values were set correctly
        $this->assertSame('test', $task->getGroupName());
        $this->assertSame('description', $task->getDescription());
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertSame(TaskPriority::LOW, $task->getPriority());
        $this->assertSame('success', $task->getResult());
        $this->assertInstanceOf(\DateTimeInterface::class, $task->getExecutedTime());
    }

    public function testCompleteMethodSetsStatusAndCompletedAt(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('test task');
        $task->setStatus(TaskStatus::IN_PROGRESS);

        $beforeComplete = new \DateTime();
        usleep(1000); // 1毫秒延迟

        $result = $task->complete();

        $this->assertSame($task, $result);
        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
        $this->assertNotNull($task->getCompletedTime());
        $this->assertGreaterThanOrEqual($beforeComplete, $task->getCompletedTime());
    }

    public function testSetCompletedTime(): void
    {
        $task = new TodoTask();
        $now = new \DateTime();

        $task->setCompletedTime($now);
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getCompletedTime());
        $this->assertEquals($now->format('Y-m-d H:i:s'), $task->getCompletedTime()->format('Y-m-d H:i:s'));

        $immutableDate = new \DateTimeImmutable();
        $task->setCompletedTime($immutableDate);
        $this->assertSame($immutableDate, $task->getCompletedTime());
    }

    protected function createEntity(): object
    {
        return new TodoTask();
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'groupName' => ['groupName', 'test-group'];
        yield 'description' => ['description', 'Test description'];
        yield 'priority' => ['priority', TaskPriority::HIGH];
        yield 'status' => ['status', TaskStatus::IN_PROGRESS];
        yield 'result' => ['result', 'test result'];
        yield 'executedTime' => ['executedTime', new \DateTimeImmutable()];
        yield 'completedTime' => ['completedTime', new \DateTimeImmutable()];
    }
}
