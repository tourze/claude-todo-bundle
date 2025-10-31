<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Attribute\TaskProcessor;

/**
 * @internal
 */
#[CoversClass(TaskProcessor::class)]
final class TaskProcessorTest extends TestCase
{
    public function testAttributeCanBeInstantiated(): void
    {
        $attribute = new TaskProcessor();

        $this->assertInstanceOf(TaskProcessor::class, $attribute);
        $this->assertEquals(0, $attribute->priority);
    }

    public function testAttributeWithCustomPriority(): void
    {
        $attribute = new TaskProcessor(priority: 100);

        $this->assertEquals(100, $attribute->priority);
    }

    public function testAttributeWithNegativePriority(): void
    {
        $attribute = new TaskProcessor(priority: -50);

        $this->assertEquals(-50, $attribute->priority);
    }

    public function testAttributeIsTargetedAtClass(): void
    {
        $reflection = new \ReflectionClass(TaskProcessor::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_CLASS, $attributeInstance->flags);
    }

    public function testAttributeIsFinal(): void
    {
        $reflection = new \ReflectionClass(TaskProcessor::class);

        $this->assertTrue($reflection->isFinal());
    }

    public function testPriorityIsReadonly(): void
    {
        $reflection = new \ReflectionClass(TaskProcessor::class);
        $property = $reflection->getProperty('priority');

        $this->assertTrue($property->isReadOnly());
    }

    public function testAttributeUsageOnClass(): void
    {
        $testClass = new #[TaskProcessor(priority: 10)]
        class {
        };

        $reflection = new \ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(TaskProcessor::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertInstanceOf(TaskProcessor::class, $attribute);
        $this->assertEquals(10, $attribute->priority);
    }
}
