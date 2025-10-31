<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\ValueObject\ProcessResult;

/**
 * @internal
 */
#[CoversClass(ProcessResult::class)]
final class ProcessResultTest extends TestCase
{
    private TodoTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->task = new TodoTask();
        $this->task->setGroupName('test-group');
        $this->task->setDescription('Test task description');
        $this->task->setPriority(TaskPriority::HIGH);
    }

    public function testConstructorWithAllParameters(): void
    {
        $metadata = ['key1' => 'value1', 'key2' => 123];
        $result = new ProcessResult(
            task: $this->task,
            success: true,
            message: 'Task completed successfully',
            metadata: $metadata
        );

        $this->assertSame($this->task, $result->getTask());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Task completed successfully', $result->getMessage());
        $this->assertSame($metadata, $result->getMetadata());
    }

    public function testConstructorWithoutMetadata(): void
    {
        $result = new ProcessResult(
            task: $this->task,
            success: false,
            message: 'Task failed'
        );

        $this->assertSame($this->task, $result->getTask());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Task failed', $result->getMessage());
        $this->assertNull($result->getMetadata());
    }

    public function testSuccessStaticFactory(): void
    {
        $metadata = ['duration' => 2.5, 'attempts' => 1];
        $result = ProcessResult::success($this->task, 'Success message', $metadata);

        $this->assertSame($this->task, $result->getTask());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Success message', $result->getMessage());
        $this->assertSame($metadata, $result->getMetadata());
    }

    public function testSuccessStaticFactoryWithoutMetadata(): void
    {
        $result = ProcessResult::success($this->task, 'Success without metadata');

        $this->assertSame($this->task, $result->getTask());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Success without metadata', $result->getMessage());
        $this->assertNull($result->getMetadata());
    }

    public function testFailureStaticFactory(): void
    {
        $metadata = ['error_code' => 500, 'error_detail' => 'Internal error'];
        $result = ProcessResult::failure($this->task, 'Failure message', $metadata);

        $this->assertSame($this->task, $result->getTask());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Failure message', $result->getMessage());
        $this->assertSame($metadata, $result->getMetadata());
    }

    public function testFailureStaticFactoryWithoutMetadata(): void
    {
        $result = ProcessResult::failure($this->task, 'Failure without metadata');

        $this->assertSame($this->task, $result->getTask());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Failure without metadata', $result->getMessage());
        $this->assertNull($result->getMetadata());
    }

    public function testGetMetadataValueWithExistingKey(): void
    {
        $metadata = [
            'string_key' => 'string value',
            'int_key' => 42,
            'bool_key' => true,
            'array_key' => ['nested' => 'value'],
            'null_key' => null,
        ];

        $result = new ProcessResult(
            task: $this->task,
            success: true,
            message: 'message',
            metadata: $metadata
        );

        $this->assertSame('string value', $result->getMetadataValue('string_key'));
        $this->assertSame(42, $result->getMetadataValue('int_key'));
        $this->assertTrue($result->getMetadataValue('bool_key'));
        $this->assertSame(['nested' => 'value'], $result->getMetadataValue('array_key'));
        $this->assertNull($result->getMetadataValue('null_key'));
    }

    public function testGetMetadataValueWithNonExistingKey(): void
    {
        $result = new ProcessResult(
            task: $this->task,
            success: true,
            message: 'message',
            metadata: ['existing' => 'value']
        );

        $this->assertNull($result->getMetadataValue('non_existing'));
        $this->assertSame('default', $result->getMetadataValue('non_existing', 'default'));
        $this->assertSame(123, $result->getMetadataValue('non_existing', 123));
        $this->assertSame(['default' => 'array'], $result->getMetadataValue('non_existing', ['default' => 'array']));
    }

    public function testGetMetadataValueWhenMetadataIsNull(): void
    {
        $result = new ProcessResult(
            task: $this->task,
            success: true,
            message: 'message'
        );

        $this->assertNull($result->getMetadataValue('any_key'));
        $this->assertSame('default', $result->getMetadataValue('any_key', 'default'));
    }

    public function testImmutability(): void
    {
        $metadata = ['original' => 'value'];
        $result = ProcessResult::success($this->task, 'Original message', $metadata);

        // 获取所有属性
        $task = $result->getTask();
        $success = $result->isSuccess();
        $message = $result->getMessage();
        $retrievedMetadata = $result->getMetadata();

        // 修改返回的元数据数组
        if (null !== $retrievedMetadata) {
            $retrievedMetadata['modified'] = 'new value';
        }

        // 验证原始对象没有被修改
        $this->assertSame($this->task, $result->getTask());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Original message', $result->getMessage());
        $this->assertSame(['original' => 'value'], $result->getMetadata());
    }

    public function testEmptyMessage(): void
    {
        $result = new ProcessResult(
            task: $this->task,
            success: true,
            message: ''
        );

        $this->assertSame('', $result->getMessage());
    }

    public function testLongMessage(): void
    {
        $longMessage = str_repeat('A', 5000);
        $result = new ProcessResult(
            task: $this->task,
            success: false,
            message: $longMessage
        );

        $this->assertSame($longMessage, $result->getMessage());
        $this->assertSame(5000, strlen($result->getMessage()));
    }

    public function testSpecialCharactersInMessage(): void
    {
        $specialMessage = "Line1\nLine2\tTabbed\r\nWindows line\0Null char 测试中文 🚀";
        $result = new ProcessResult(
            task: $this->task,
            success: true,
            message: $specialMessage
        );

        $this->assertSame($specialMessage, $result->getMessage());
    }

    public function testEmptyMetadata(): void
    {
        $result = ProcessResult::success($this->task, 'message', []);

        $this->assertSame([], $result->getMetadata());
        $this->assertNull($result->getMetadataValue('any_key'));
    }

    public function testComplexMetadata(): void
    {
        $complexMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep value',
                ],
            ],
            'mixed_array' => [1, 'two', 3.0, true, null],
            'object' => new \stdClass(),
            'unicode' => '中文测试 🎯',
        ];

        $result = ProcessResult::success($this->task, 'Complex metadata test', $complexMetadata);

        $this->assertSame($complexMetadata, $result->getMetadata());
        $this->assertSame(['level2' => ['level3' => 'deep value']], $result->getMetadataValue('level1'));
        $this->assertSame([1, 'two', 3.0, true, null], $result->getMetadataValue('mixed_array'));
        $this->assertInstanceOf(\stdClass::class, $result->getMetadataValue('object'));
        $this->assertSame('中文测试 🎯', $result->getMetadataValue('unicode'));
    }

    public function testDifferentTaskStates(): void
    {
        // 测试处于不同状态的任务
        $pendingTask = new TodoTask();
        $pendingTask->setGroupName('pending-group');
        $pendingTask->setDescription('Pending task');

        $resultPending = ProcessResult::success($pendingTask, 'Processed pending task');
        $this->assertSame($pendingTask, $resultPending->getTask());
        $this->assertSame(TaskStatus::PENDING, $resultPending->getTask()->getStatus());

        // 测试带有执行时间的任务
        $executedTask = new TodoTask();
        $executedTask->setGroupName('executed-group');
        $executedTask->setDescription('Executed task');
        $executedTask->setExecutedTime(new \DateTime());

        $resultExecuted = ProcessResult::success($executedTask, 'Processed executed task');
        $this->assertSame($executedTask, $resultExecuted->getTask());
        $this->assertNotNull($resultExecuted->getTask()->getExecutedTime());
    }

    public function testMetadataWithZeroValues(): void
    {
        $metadata = [
            'zero_int' => 0,
            'zero_float' => 0.0,
            'empty_string' => '',
            'false_bool' => false,
        ];

        $result = ProcessResult::success($this->task, 'Zero values test', $metadata);

        $this->assertSame(0, $result->getMetadataValue('zero_int'));
        $this->assertSame(0.0, $result->getMetadataValue('zero_float'));
        $this->assertSame('', $result->getMetadataValue('empty_string'));
        $this->assertFalse($result->getMetadataValue('false_bool'));

        // 确保这些值不会被误认为是不存在的键
        $this->assertSame(0, $result->getMetadataValue('zero_int', 999));
        $this->assertSame('', $result->getMetadataValue('empty_string', 'default'));
    }
}
