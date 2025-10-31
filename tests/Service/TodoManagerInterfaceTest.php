<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Exception\TaskNotFoundException;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;

/**
 * @internal
 */
#[CoversClass(TodoManagerInterface::class)]
final class TodoManagerInterfaceTest extends TestCase
{
    public function testInterfaceDefinesRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(TodoManagerInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('push'));
        $this->assertTrue($reflection->hasMethod('pop'));
        $this->assertTrue($reflection->hasMethod('getTask'));
        $this->assertTrue($reflection->hasMethod('updateTaskStatus'));
    }

    public function testPushMethodSignature(): void
    {
        $reflection = new \ReflectionClass(TodoManagerInterface::class);
        $method = $reflection->getMethod('push');

        // Check parameters
        $parameters = $method->getParameters();
        $this->assertCount(3, $parameters);

        // First parameter: string $groupName
        $groupParam = $parameters[0];
        $this->assertEquals('groupName', $groupParam->getName());
        $groupType = $groupParam->getType();
        $this->assertNotNull($groupType);
        $this->assertEquals('string', (string) $groupType);
        $this->assertFalse($groupParam->allowsNull());

        // Second parameter: string $description
        $descParam = $parameters[1];
        $this->assertEquals('description', $descParam->getName());
        $descType = $descParam->getType();
        $this->assertNotNull($descType);
        $this->assertEquals('string', (string) $descType);
        $this->assertFalse($descParam->allowsNull());

        // Third parameter: TaskPriority|string $priority = TaskPriority::NORMAL
        $priorityParam = $parameters[2];
        $this->assertEquals('priority', $priorityParam->getName());
        $this->assertTrue($priorityParam->isDefaultValueAvailable());
        $this->assertEquals(TaskPriority::NORMAL, $priorityParam->getDefaultValue());

        // Check return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(TodoTask::class, (string) $returnType);
    }

    public function testPopMethodSignature(): void
    {
        $reflection = new \ReflectionClass(TodoManagerInterface::class);
        $method = $reflection->getMethod('pop');

        // Check parameters
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);

        // First parameter: ?string $groupName = null
        $groupParam = $parameters[0];
        $this->assertEquals('groupName', $groupParam->getName());
        $this->assertTrue($groupParam->allowsNull());
        $this->assertTrue($groupParam->isDefaultValueAvailable());
        $this->assertNull($groupParam->getDefaultValue());

        // Check return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
    }

    public function testGetTaskMethodSignature(): void
    {
        $reflection = new \ReflectionClass(TodoManagerInterface::class);
        $method = $reflection->getMethod('getTask');

        // Check parameters
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);

        // First parameter: int $id
        $idParam = $parameters[0];
        $this->assertEquals('id', $idParam->getName());
        $idType = $idParam->getType();
        $this->assertNotNull($idType);
        $this->assertEquals('int', (string) $idType);
        $this->assertFalse($idParam->allowsNull());

        // Check return type
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(TodoTask::class, (string) $returnType);
    }

    public function testUpdateTaskStatusMethodSignature(): void
    {
        $reflection = new \ReflectionClass(TodoManagerInterface::class);
        $method = $reflection->getMethod('updateTaskStatus');

        // Check parameters
        $parameters = $method->getParameters();
        $this->assertCount(3, $parameters);

        // First parameter: TodoTask $task
        $taskParam = $parameters[0];
        $this->assertEquals('task', $taskParam->getName());
        $taskType = $taskParam->getType();
        $this->assertNotNull($taskType);
        $this->assertEquals(TodoTask::class, (string) $taskType);
        $this->assertFalse($taskParam->allowsNull());

        // Second parameter: TaskStatus $status
        $statusParam = $parameters[1];
        $this->assertEquals('status', $statusParam->getName());
        $statusType = $statusParam->getType();
        $this->assertNotNull($statusType);
        $this->assertEquals(TaskStatus::class, (string) $statusType);
        $this->assertFalse($statusParam->allowsNull());

        // Third parameter: ?string $result = null
        $resultParam = $parameters[2];
        $this->assertEquals('result', $resultParam->getName());
        $this->assertTrue($resultParam->allowsNull());
        $this->assertTrue($resultParam->isDefaultValueAvailable());
        $this->assertNull($resultParam->getDefaultValue());

        // Check return type (void)
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', (string) $returnType);
    }

    public function testInterfaceImplementation(): void
    {
        // Create a mock implementation to verify interface contract
        $manager = $this->createMock(TodoManagerInterface::class);

        $task = new TodoTask();
        $task->setGroupName('test');
        $task->setDescription('Test task');
        $task->setPriority(TaskPriority::NORMAL);

        // Test push method
        $manager->expects($this->once())
            ->method('push')
            ->with('test-group', 'Test description', TaskPriority::HIGH)
            ->willReturn($task)
        ;

        $result = $manager->push('test-group', 'Test description', TaskPriority::HIGH);
        $this->assertInstanceOf(TodoTask::class, $result);

        // Test pop method
        $manager->expects($this->once())
            ->method('pop')
            ->with('test-group')
            ->willReturn($task)
        ;

        $poppedTask = $manager->pop('test-group');
        $this->assertInstanceOf(TodoTask::class, $poppedTask);

        // Test getTask method
        $manager->expects($this->once())
            ->method('getTask')
            ->with(123)
            ->willReturn($task)
        ;

        $fetchedTask = $manager->getTask(123);
        $this->assertInstanceOf(TodoTask::class, $fetchedTask);

        // Test updateTaskStatus method
        $manager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::COMPLETED, 'Done')
        ;

        $manager->updateTaskStatus($task, TaskStatus::COMPLETED, 'Done');
    }

    public function testPushMethodCanAcceptStringPriority(): void
    {
        $manager = $this->createMock(TodoManagerInterface::class);

        $task = new TodoTask();
        $task->setPriority(TaskPriority::LOW);

        $manager->expects($this->once())
            ->method('push')
            ->with('test-group', 'Test description', 'low')
            ->willReturn($task)
        ;

        $result = $manager->push('test-group', 'Test description', 'low');
        $this->assertInstanceOf(TodoTask::class, $result);
    }

    public function testPopMethodCanReturnNull(): void
    {
        $manager = $this->createMock(TodoManagerInterface::class);

        $manager->expects($this->once())
            ->method('pop')
            ->willReturn(null)
        ;

        $result = $manager->pop();
        $this->assertNull($result);
    }

    public function testGetTaskMethodCanThrowException(): void
    {
        $manager = $this->createMock(TodoManagerInterface::class);

        $manager->expects($this->once())
            ->method('getTask')
            ->with(999)
            ->willThrowException(TaskNotFoundException::forId(999))
        ;

        $this->expectException(TaskNotFoundException::class);
        $manager->getTask(999);
    }

    public function testUpdateTaskStatusWithoutResult(): void
    {
        $manager = $this->createMock(TodoManagerInterface::class);

        $task = new TodoTask();

        $manager->expects($this->once())
            ->method('updateTaskStatus')
            ->with($task, TaskStatus::FAILED, null)
        ;

        $manager->updateTaskStatus($task, TaskStatus::FAILED);
    }
}
