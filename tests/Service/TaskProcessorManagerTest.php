<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Processor\TaskProcessorInterface;
use Tourze\ClaudeTodoBundle\Service\TaskProcessorManager;
use Tourze\ClaudeTodoBundle\ValueObject\ProcessResult;

/**
 * @internal
 */
#[CoversClass(TaskProcessorManager::class)]
final class TaskProcessorManagerTest extends TestCase
{
    public function testProcessWithSupportingProcessor(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $processor = $this->createMock(TaskProcessorInterface::class);
        $processor->method('supports')->with($task)->willReturn(true);
        $processor->method('process')->with($task)->willReturn(ProcessResult::success($task, 'Processed successfully'));

        $manager = new TaskProcessorManager([$processor]);

        $result = $manager->process($task);

        $this->assertInstanceOf(ProcessResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Processed successfully', $result->getMessage());
    }

    public function testProcessWithNonSupportingProcessor(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $processor = $this->createMock(TaskProcessorInterface::class);
        $processor->method('supports')->with($task)->willReturn(false);

        $manager = new TaskProcessorManager([$processor]);

        $result = $manager->process($task);

        $this->assertNull($result);
    }

    public function testProcessWithMultipleProcessors(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $processor1 = $this->createMock(TaskProcessorInterface::class);
        $processor1->method('supports')->with($task)->willReturn(false);

        $processor2 = $this->createMock(TaskProcessorInterface::class);
        $processor2->method('supports')->with($task)->willReturn(true);
        $processor2->method('process')->with($task)->willReturn(ProcessResult::success($task, 'Processed by second processor'));

        $manager = new TaskProcessorManager([$processor1, $processor2]);

        $result = $manager->process($task);

        $this->assertInstanceOf(ProcessResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Processed by second processor', $result->getMessage());
    }

    public function testHasProcessorWithSupportingProcessor(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $processor = $this->createMock(TaskProcessorInterface::class);
        $processor->method('supports')->with($task)->willReturn(true);

        $manager = new TaskProcessorManager([$processor]);

        $this->assertTrue($manager->hasProcessor($task));
    }

    public function testHasProcessorWithNonSupportingProcessor(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $processor = $this->createMock(TaskProcessorInterface::class);
        $processor->method('supports')->with($task)->willReturn(false);

        $manager = new TaskProcessorManager([$processor]);

        $this->assertFalse($manager->hasProcessor($task));
    }

    public function testHasProcessorWithNoProcessors(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $manager = new TaskProcessorManager([]);

        $this->assertFalse($manager->hasProcessor($task));
    }

    public function testGetProcessors(): void
    {
        $processor1 = $this->createMock(TaskProcessorInterface::class);
        $processor2 = $this->createMock(TaskProcessorInterface::class);

        $manager = new TaskProcessorManager([$processor1, $processor2]);

        $processors = $manager->getProcessors();

        $this->assertCount(2, $processors);
        $this->assertContains($processor1, $processors);
        $this->assertContains($processor2, $processors);
    }

    public function testGetProcessorsWithEmptyArray(): void
    {
        $manager = new TaskProcessorManager([]);

        $processors = $manager->getProcessors();

        $this->assertCount(0, $processors);
    }

    public function testProcessWithEmptyProcessors(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $manager = new TaskProcessorManager([]);

        $result = $manager->process($task);

        $this->assertNull($result);
    }

    public function testProcessReturnsFirstSupportingProcessor(): void
    {
        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test Task');
        $task->setPriority(TaskPriority::NORMAL);

        $processor1 = $this->createMock(TaskProcessorInterface::class);
        $processor1->method('supports')->with($task)->willReturn(true);
        $processor1->method('process')->with($task)->willReturn(ProcessResult::success($task, 'First processor'));

        $processor2 = $this->createMock(TaskProcessorInterface::class);
        $processor2->method('supports')->with($task)->willReturn(true);
        $processor2->method('process')->with($task)->willReturn(ProcessResult::success($task, 'Second processor'));

        $manager = new TaskProcessorManager([$processor1, $processor2]);

        $result = $manager->process($task);

        $this->assertInstanceOf(ProcessResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('First processor', $result->getMessage());
    }
}
