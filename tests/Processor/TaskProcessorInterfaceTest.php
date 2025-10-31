<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Processor\TaskProcessorInterface;

/**
 * @internal
 */
#[CoversClass(TaskProcessorInterface::class)]
final class TaskProcessorInterfaceTest extends TestCase
{
    public function testInterfaceStructure(): void
    {
        $reflection = new \ReflectionClass(TaskProcessorInterface::class);

        $this->assertTrue($reflection->hasMethod('process'));
        $this->assertTrue($reflection->hasMethod('supports'));

        $processMethod = $reflection->getMethod('process');
        $this->assertEquals(1, $processMethod->getNumberOfParameters());
        $paramType = $processMethod->getParameters()[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(TodoTask::class, $paramType->getName());

        $supportsMethod = $reflection->getMethod('supports');
        $this->assertEquals(1, $supportsMethod->getNumberOfParameters());
        $paramType = $supportsMethod->getParameters()[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(TodoTask::class, $paramType->getName());
        $returnType = $supportsMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('bool', $returnType->getName());
    }
}
