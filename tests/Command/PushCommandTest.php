<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ClaudeTodoBundle\Command\PushCommand;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(PushCommand::class)]
#[RunTestsInSeparateProcesses]
final class PushCommandTest extends AbstractCommandTestCase
{
    private MockObject&TodoManagerInterface $todoManager;

    private PushCommand $command;

    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    protected function onSetUp(): void
    {
        $this->todoManager = $this->createMock(TodoManagerInterface::class);

        $container = self::getContainer();
        $container->set(TodoManagerInterface::class, $this->todoManager);

        $command = $container->get(PushCommand::class);
        $this->assertInstanceOf(PushCommand::class, $command);
        $this->command = $command;
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('claude-todo:push', $this->command->getName());
        $this->assertSame('添加新的TODO任务', $this->command->getDescription());

        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('group'));
        $groupArg = $definition->getArgument('group');
        $this->assertTrue($groupArg->isRequired());
        $this->assertSame('任务分组名称', $groupArg->getDescription());

        $this->assertTrue($definition->hasArgument('description'));
        $descArg = $definition->getArgument('description');
        $this->assertTrue($descArg->isRequired());
        $this->assertSame('任务描述', $descArg->getDescription());

        $this->assertTrue($definition->hasOption('priority'));
        $priorityOption = $definition->getOption('priority');
        $this->assertSame('p', $priorityOption->getShortcut());
        $this->assertTrue($priorityOption->acceptValue());
        $this->assertTrue($priorityOption->isValueRequired());
        $this->assertSame('normal', $priorityOption->getDefault());
        $this->assertSame('任务优先级 (low/normal/high)', $priorityOption->getDescription());
    }

    public function testExecuteWithValidInputs(): void
    {
        $task = $this->createTaskEntity();

        $this->todoManager->expects($this->once())
            ->method('push')
            ->with('backend', 'Implement user authentication', 'normal')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'backend',
            'description' => 'Implement user authentication',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task created successfully! ID: 123', $output);
        $this->assertStringContainsString('backend', $output);
        $this->assertStringContainsString('normal', $output);
        $this->assertStringContainsString('pending', $output);
    }

    public function testExecuteWithHighPriority(): void
    {
        $task = $this->createTaskEntity(TaskPriority::HIGH);

        $this->todoManager->expects($this->once())
            ->method('push')
            ->with('frontend', 'Fix critical bug', 'high')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'frontend',
            'description' => 'Fix critical bug',
            '--priority' => 'high',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task created successfully!', $output);
        $this->assertStringContainsString('high', $output);
    }

    public function testExecuteWithInvalidArgumentException(): void
    {
        $this->todoManager->expects($this->once())
            ->method('push')
            ->willThrowException(new \InvalidArgumentException('Invalid priority value'))
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'backend',
            'description' => 'Test task',
            '--priority' => 'invalid',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Invalid priority value', $output);
    }

    public function testExecuteWithGenericException(): void
    {
        $this->todoManager->expects($this->once())
            ->method('push')
            ->willThrowException(new \Exception('Database connection failed'))
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'backend',
            'description' => 'Test task',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to create task: Database connection failed', $output);
    }

    private function createTaskEntity(TaskPriority $priority = TaskPriority::NORMAL): TodoTask
    {
        $task = $this->createMock(TodoTask::class);
        $task->expects($this->any())->method('getId')->willReturn(123);
        $task->expects($this->any())->method('getGroupName')->willReturn('backend');
        $task->expects($this->any())->method('getPriority')->willReturn($priority);
        $task->expects($this->any())->method('getStatus')->willReturn(TaskStatus::PENDING);
        $task->expects($this->any())->method('getCreatedTime')->willReturn(new \DateTime('2024-01-01 10:00:00'));

        return $task;
    }

    public function testArgumentGroup(): void
    {
        $task = $this->createTaskEntity();

        $this->todoManager->expects($this->once())
            ->method('push')
            ->with('test-group', 'Test description', 'normal')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'test-group',
            'description' => 'Test description',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testArgumentDescription(): void
    {
        $task = $this->createTaskEntity();

        $this->todoManager->expects($this->once())
            ->method('push')
            ->with('test-group', 'Custom description', 'normal')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'test-group',
            'description' => 'Custom description',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task created successfully!', $output);
    }

    public function testOptionPriority(): void
    {
        $task = $this->createTaskEntity(TaskPriority::HIGH);

        $this->todoManager->expects($this->once())
            ->method('push')
            ->with('test-group', 'Test description', 'high')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([
            'group' => 'test-group',
            'description' => 'Test description',
            '--priority' => 'high',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('high', $output);
    }
}
