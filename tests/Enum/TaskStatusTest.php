<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(TaskStatus::class)]
final class TaskStatusTest extends AbstractEnumTestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('pending', TaskStatus::PENDING->value);
        $this->assertSame('in_progress', TaskStatus::IN_PROGRESS->value);
        $this->assertSame('completed', TaskStatus::COMPLETED->value);
        $this->assertSame('failed', TaskStatus::FAILED->value);
    }

    public function testCasesCount(): void
    {
        $cases = TaskStatus::cases();
        $this->assertCount(4, $cases);
        $this->assertContains(TaskStatus::PENDING, $cases);
        $this->assertContains(TaskStatus::IN_PROGRESS, $cases);
        $this->assertContains(TaskStatus::COMPLETED, $cases);
        $this->assertContains(TaskStatus::FAILED, $cases);
    }

    #[TestWith([TaskStatus::PENDING, 'Pending'])]
    #[TestWith([TaskStatus::IN_PROGRESS, 'In Progress'])]
    #[TestWith([TaskStatus::COMPLETED, 'Completed'])]
    #[TestWith([TaskStatus::FAILED, 'Failed'])]
    public function testGetLabel(TaskStatus $status, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $status->getLabel());
    }

    #[TestWith([TaskStatus::PENDING, false])]
    #[TestWith([TaskStatus::IN_PROGRESS, false])]
    #[TestWith([TaskStatus::COMPLETED, true])]
    #[TestWith([TaskStatus::FAILED, true])]
    public function testIsFinished(TaskStatus $status, bool $expectedFinished): void
    {
        $this->assertSame($expectedFinished, $status->isFinished());
    }

    // From PENDING
    #[TestWith([TaskStatus::PENDING, TaskStatus::IN_PROGRESS, true])]
    #[TestWith([TaskStatus::PENDING, TaskStatus::FAILED, true])]
    #[TestWith([TaskStatus::PENDING, TaskStatus::COMPLETED, false])]
    #[TestWith([TaskStatus::PENDING, TaskStatus::PENDING, false])]
    // From IN_PROGRESS
    #[TestWith([TaskStatus::IN_PROGRESS, TaskStatus::COMPLETED, true])]
    #[TestWith([TaskStatus::IN_PROGRESS, TaskStatus::FAILED, true])]
    #[TestWith([TaskStatus::IN_PROGRESS, TaskStatus::PENDING, false])]
    #[TestWith([TaskStatus::IN_PROGRESS, TaskStatus::IN_PROGRESS, false])]
    // From COMPLETED
    #[TestWith([TaskStatus::COMPLETED, TaskStatus::PENDING, false])]
    #[TestWith([TaskStatus::COMPLETED, TaskStatus::IN_PROGRESS, false])]
    #[TestWith([TaskStatus::COMPLETED, TaskStatus::COMPLETED, false])]
    #[TestWith([TaskStatus::COMPLETED, TaskStatus::FAILED, false])]
    // From FAILED
    #[TestWith([TaskStatus::FAILED, TaskStatus::PENDING, false])]
    #[TestWith([TaskStatus::FAILED, TaskStatus::IN_PROGRESS, false])]
    #[TestWith([TaskStatus::FAILED, TaskStatus::COMPLETED, false])]
    #[TestWith([TaskStatus::FAILED, TaskStatus::FAILED, false])]
    public function testCanTransitionTo(TaskStatus $fromStatus, TaskStatus $toStatus, bool $expectedCanTransition): void
    {
        $this->assertSame($expectedCanTransition, $fromStatus->canTransitionTo($toStatus));
    }

    public function testFromValue(): void
    {
        $this->assertSame(TaskStatus::PENDING, TaskStatus::from('pending'));
        $this->assertSame(TaskStatus::IN_PROGRESS, TaskStatus::from('in_progress'));
        $this->assertSame(TaskStatus::COMPLETED, TaskStatus::from('completed'));
        $this->assertSame(TaskStatus::FAILED, TaskStatus::from('failed'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        TaskStatus::from('invalid');
    }

    public function testTryFromValue(): void
    {
        $this->assertSame(TaskStatus::PENDING, TaskStatus::tryFrom('pending'));
        $this->assertSame(TaskStatus::IN_PROGRESS, TaskStatus::tryFrom('in_progress'));
        $this->assertSame(TaskStatus::COMPLETED, TaskStatus::tryFrom('completed'));
        $this->assertSame(TaskStatus::FAILED, TaskStatus::tryFrom('failed'));

        // Test invalid value returns null
        /** @phpstan-ignore-next-line */
        $this->assertNull(TaskStatus::tryFrom('invalid'));
    }

    public function testStatusWorkflow(): void
    {
        $task = TaskStatus::PENDING;

        // PENDING can go to IN_PROGRESS
        $this->assertTrue($task->canTransitionTo(TaskStatus::IN_PROGRESS));
        $this->assertFalse($task->isFinished());

        // IN_PROGRESS can go to COMPLETED
        $task = TaskStatus::IN_PROGRESS;
        $this->assertTrue($task->canTransitionTo(TaskStatus::COMPLETED));
        $this->assertFalse($task->isFinished());

        // COMPLETED is finished and cannot transition
        $task = TaskStatus::COMPLETED;
        $this->assertTrue($task->isFinished());
        $this->assertFalse($task->canTransitionTo(TaskStatus::PENDING));
        $this->assertFalse($task->canTransitionTo(TaskStatus::IN_PROGRESS));
        $this->assertFalse($task->canTransitionTo(TaskStatus::FAILED));
    }

    public function testAllowedTransitions(): void
    {
        // Test that we have a valid state machine
        $pending = TaskStatus::PENDING;
        $inProgress = TaskStatus::IN_PROGRESS;
        $completed = TaskStatus::COMPLETED;
        $failed = TaskStatus::FAILED;

        // Only two valid paths from PENDING
        $validFromPending = array_filter(TaskStatus::cases(), fn ($status) => $pending->canTransitionTo($status));
        $this->assertCount(2, $validFromPending);
        $this->assertContains($inProgress, $validFromPending);
        $this->assertContains($failed, $validFromPending);

        // Only two valid paths from IN_PROGRESS
        $validFromInProgress = array_filter(TaskStatus::cases(), fn ($status) => $inProgress->canTransitionTo($status));
        $this->assertCount(2, $validFromInProgress);
        $this->assertContains($completed, $validFromInProgress);
        $this->assertContains($failed, $validFromInProgress);

        // No valid transitions from terminal states
        $validFromCompleted = array_filter(TaskStatus::cases(), fn ($status) => $completed->canTransitionTo($status));
        $this->assertCount(0, $validFromCompleted);

        $validFromFailed = array_filter(TaskStatus::cases(), fn ($status) => $failed->canTransitionTo($status));
        $this->assertCount(0, $validFromFailed);
    }

    public function testToArray(): void
    {
        $status = TaskStatus::COMPLETED;
        $array = $status->toArray();

        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertSame('completed', $array['value']);
        $this->assertSame('Completed', $array['label']);
    }

    public function testValueUniqueness(): void
    {
        $values = array_map(fn (TaskStatus $case) => $case->value, TaskStatus::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'Enum values must be unique');
    }

    public function testLabelUniqueness(): void
    {
        $labels = array_map(fn (TaskStatus $case) => $case->getLabel(), TaskStatus::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'Enum labels must be unique');
    }
}
