<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Exception\ExecutionException;
use Tourze\ClaudeTodoBundle\Service\ClaudeExecutorInterface;
use Tourze\ClaudeTodoBundle\ValueObject\ExecutionResult;

/**
 * @internal
 */
#[CoversClass(ClaudeExecutorInterface::class)]
final class ClaudeExecutorInterfaceTest extends TestCase
{
    public function testInterfaceDefinesRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(ClaudeExecutorInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('execute'));
        $this->assertTrue($reflection->hasMethod('isAvailable'));
    }

    public function testExecuteMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ClaudeExecutorInterface::class);
        $method = $reflection->getMethod('execute');

        // Check parameters
        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);

        // First parameter: TodoTask $task
        $taskParam = $parameters[0];
        $this->assertEquals('task', $taskParam->getName());
        $taskType = $taskParam->getType();
        $this->assertNotNull($taskType);
        $this->assertEquals(TodoTask::class, (string) $taskType);
        $this->assertFalse($taskParam->allowsNull());

        // Second parameter: array $options = []
        $optionsParam = $parameters[1];
        $this->assertEquals('options', $optionsParam->getName());
        $optionsType = $optionsParam->getType();
        $this->assertNotNull($optionsType);
        $this->assertEquals('array', (string) $optionsType);
        $this->assertTrue($optionsParam->isDefaultValueAvailable());
        $this->assertEquals([], $optionsParam->getDefaultValue());

        // Check return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(ExecutionResult::class, (string) $returnType);
    }

    public function testIsAvailableMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ClaudeExecutorInterface::class);
        $method = $reflection->getMethod('isAvailable');

        // Check no parameters
        $parameters = $method->getParameters();
        $this->assertCount(0, $parameters);

        // Check return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('bool', (string) $returnType);
    }

    public function testInterfaceImplementation(): void
    {
        // Create a mock implementation to verify interface contract
        $executor = $this->createMock(ClaudeExecutorInterface::class);

        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test task');
        $task->setPriority(TaskPriority::NORMAL);

        $executionResult = ExecutionResult::success('Output', 1.5);

        $executor->expects($this->once())
            ->method('execute')
            ->with($task, ['model' => 'test-model'])
            ->willReturn($executionResult)
        ;

        $executor->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true)
        ;

        // Verify the interface works as expected
        $result = $executor->execute($task, ['model' => 'test-model']);
        $this->assertInstanceOf(ExecutionResult::class, $result);
        $this->assertTrue($result->isSuccess());

        $available = $executor->isAvailable();
        $this->assertTrue($available);
    }

    public function testExecuteMethodCanThrowExecutionException(): void
    {
        $executor = $this->createMock(ClaudeExecutorInterface::class);

        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test task');
        $task->setPriority(TaskPriority::NORMAL);

        $executor->expects($this->once())
            ->method('execute')
            ->with($task)
            ->willThrowException(ExecutionException::forTask(1, 'Test error'))
        ;

        $this->expectException(ExecutionException::class);
        $executor->execute($task);
    }
}
