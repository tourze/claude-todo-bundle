<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(TaskPriority::class)]
final class TaskPriorityTest extends AbstractEnumTestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('low', TaskPriority::LOW->value);
        $this->assertSame('normal', TaskPriority::NORMAL->value);
        $this->assertSame('high', TaskPriority::HIGH->value);
    }

    public function testCasesCount(): void
    {
        $cases = TaskPriority::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(TaskPriority::LOW, $cases);
        $this->assertContains(TaskPriority::NORMAL, $cases);
        $this->assertContains(TaskPriority::HIGH, $cases);
    }

    #[TestWith([TaskPriority::LOW, 'Low'])]
    #[TestWith([TaskPriority::NORMAL, 'Normal'])]
    #[TestWith([TaskPriority::HIGH, 'High'])]
    public function testGetLabel(TaskPriority $priority, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $priority->getLabel());
    }

    #[TestWith([TaskPriority::LOW, 1])]
    #[TestWith([TaskPriority::NORMAL, 2])]
    #[TestWith([TaskPriority::HIGH, 3])]
    public function testGetWeight(TaskPriority $priority, int $expectedWeight): void
    {
        $this->assertSame($expectedWeight, $priority->getWeight());
    }

    #[TestWith([TaskPriority::LOW, 'gray'])]
    #[TestWith([TaskPriority::NORMAL, 'blue'])]
    #[TestWith([TaskPriority::HIGH, 'red'])]
    public function testGetColor(TaskPriority $priority, string $expectedColor): void
    {
        $this->assertSame($expectedColor, $priority->getColor());
    }

    public function testFromValue(): void
    {
        $this->assertSame(TaskPriority::LOW, TaskPriority::from('low'));
        $this->assertSame(TaskPriority::NORMAL, TaskPriority::from('normal'));
        $this->assertSame(TaskPriority::HIGH, TaskPriority::from('high'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        TaskPriority::from('invalid');
    }

    public function testTryFromValue(): void
    {
        $this->assertSame(TaskPriority::LOW, TaskPriority::tryFrom('low'));
        $this->assertSame(TaskPriority::NORMAL, TaskPriority::tryFrom('normal'));
        $this->assertSame(TaskPriority::HIGH, TaskPriority::tryFrom('high'));

        // Test invalid value returns null
        /** @phpstan-ignore-next-line */
        $this->assertNull(TaskPriority::tryFrom('invalid'));
    }

    public function testPriorityComparison(): void
    {
        $low = TaskPriority::LOW;
        $normal = TaskPriority::NORMAL;
        $high = TaskPriority::HIGH;

        // Verify priority weights are in correct order
        // assertLessThan($expected, $actual) means $actual < $expected
        $this->assertLessThan($normal->getWeight(), $low->getWeight());  // 1 < 2
        $this->assertLessThan($high->getWeight(), $normal->getWeight());  // 2 < 3
        $this->assertLessThan($high->getWeight(), $low->getWeight());     // 1 < 3
    }

    public function testToArray(): void
    {
        $priority = TaskPriority::HIGH;
        $array = $priority->toArray();

        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertSame('high', $array['value']);
        $this->assertSame('High', $array['label']);
    }

    public function testValueUniqueness(): void
    {
        $values = array_map(fn (TaskPriority $case) => $case->value, TaskPriority::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'Enum values must be unique');
    }

    public function testLabelUniqueness(): void
    {
        $labels = array_map(fn (TaskPriority $case) => $case->getLabel(), TaskPriority::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'Enum labels must be unique');
    }
}
