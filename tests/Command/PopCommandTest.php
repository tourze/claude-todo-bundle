<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ClaudeTodoBundle\Command\PopCommand;
use Tourze\ClaudeTodoBundle\Entity\TodoTask;
use Tourze\ClaudeTodoBundle\Enum\TaskPriority;
use Tourze\ClaudeTodoBundle\Enum\TaskStatus;
use Tourze\ClaudeTodoBundle\Service\ConfigManager;
use Tourze\ClaudeTodoBundle\Service\TodoManagerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(PopCommand::class)]
#[RunTestsInSeparateProcesses]
final class PopCommandTest extends AbstractCommandTestCase
{
    private MockObject&TodoManagerInterface $todoManager;

    private MockObject&ConfigManager $configManager;

    private PopCommand $command;

    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    protected function onSetUp(): void
    {
        $this->todoManager = $this->createMock(TodoManagerInterface::class);
        $this->configManager = $this->createMock(ConfigManager::class);

        $container = self::getContainer();
        $container->set(TodoManagerInterface::class, $this->todoManager);
        $container->set(ConfigManager::class, $this->configManager);

        $command = $container->get(PopCommand::class);
        $this->assertInstanceOf(PopCommand::class, $command);
        $this->command = $command;
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('claude-todo:pop', $this->command->getName());
        $this->assertSame('获取下一个可执行的TODO任务', $this->command->getDescription());

        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('group'));
        $argument = $definition->getArgument('group');
        $this->assertFalse($argument->isRequired());
        $this->assertSame('指定任务分组（可选）', $argument->getDescription());

        $this->assertTrue($definition->hasOption('wait'));
        $waitOption = $definition->getOption('wait');
        $this->assertSame('w', $waitOption->getShortcut());
        $this->assertFalse($waitOption->acceptValue());
        $this->assertSame('当没有任务时等待', $waitOption->getDescription());

        $this->assertTrue($definition->hasOption('max-wait'));
        $maxWaitOption = $definition->getOption('max-wait');
        $this->assertTrue($maxWaitOption->acceptValue());
        $this->assertTrue($maxWaitOption->isValueRequired());
        $this->assertSame(300, $maxWaitOption->getDefault());
        $this->assertSame('最大等待时间（秒）', $maxWaitOption->getDescription());
    }

    public function testExecuteWithTaskFound(): void
    {
        $task = $this->createTaskEntity();

        $this->todoManager->expects($this->once())
            ->method('pop')
            ->with(null)
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Task retrieved successfully!', $output);
        $this->assertStringContainsString('Task ID: 123', $output);
        $this->assertStringContainsString('backend', $output);
        $this->assertStringContainsString('high', $output);
        $this->assertStringContainsString('Implement user authentication', $output);
    }

    public function testExecuteWithGroupFilter(): void
    {
        $task = $this->createTaskEntity();

        $this->todoManager->expects($this->once())
            ->method('pop')
            ->with('backend')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['group' => 'backend']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithNoTaskAndNoWait(): void
    {
        $this->todoManager->expects($this->once())
            ->method('pop')
            ->with(null)
            ->willReturn(null)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No available tasks at this time.', $output);
    }

    public function testExecuteWithWaitAndTaskFoundAfterWaiting(): void
    {
        $task = $this->createTaskEntity();

        // 模拟第一次调用返回 null，等待后第二次调用返回任务
        $consecutiveCalls = [];
        for ($i = 0; $i < 5; ++$i) {
            $consecutiveCalls[] = null;
        }
        $consecutiveCalls[] = $task;

        $this->todoManager->expects($this->exactly(6))
            ->method('pop')
            ->willReturnOnConsecutiveCalls(...$consecutiveCalls)
        ;

        $this->configManager->expects($this->atLeastOnce())
            ->method('getCheckInterval')
            ->willReturn(1)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--wait' => true, '--max-wait' => '10']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Found task after waiting', $output);
        $this->assertStringContainsString('Task retrieved successfully!', $output);
    }

    public function testExecuteWithWaitTimeout(): void
    {
        $this->todoManager->expects($this->any())
            ->method('pop')
            ->willReturn(null)
        ;

        $this->configManager->expects($this->any())
            ->method('getCheckInterval')
            ->willReturn(0)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--wait' => true, '--max-wait' => '0']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No tasks found after waiting 0 seconds.', $output);
    }

    public function testExecuteWithStopFileDetected(): void
    {
        $stopFile = tempnam(sys_get_temp_dir(), 'claude-todo-stop-test');
        file_put_contents($stopFile, '');

        $this->todoManager->expects($this->once())
            ->method('pop')
            ->willReturn(null)
        ;

        $this->configManager->expects($this->once())
            ->method('getStopFile')
            ->willReturn($stopFile)
        ;

        $this->configManager->expects($this->any())
            ->method('getCheckInterval')
            ->willReturn(0)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--wait' => true, '--max-wait' => '10']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Stop file detected. Exiting...', $output);
        $this->assertFileDoesNotExist($stopFile);
    }

    private function createTaskEntity(): TodoTask
    {
        $task = $this->createMock(TodoTask::class);
        $task->expects($this->any())->method('getId')->willReturn(123);
        $task->expects($this->any())->method('getGroupName')->willReturn('backend');
        $task->expects($this->any())->method('getPriority')->willReturn(TaskPriority::HIGH);
        $task->expects($this->any())->method('getDescription')->willReturn('Implement user authentication');
        $task->expects($this->any())->method('getStatus')->willReturn(TaskStatus::IN_PROGRESS);
        $task->expects($this->any())->method('getCreatedTime')->willReturn(new \DateTime('2024-01-01 10:00:00'));

        return $task;
    }

    public function testArgumentGroup(): void
    {
        $task = $this->createTaskEntity();

        $this->todoManager->expects($this->once())
            ->method('pop')
            ->with('test-group')
            ->willReturn($task)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['group' => 'test-group']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOptionWait(): void
    {
        $this->todoManager->expects($this->any())
            ->method('pop')
            ->willReturn(null)
        ;

        $this->configManager->expects($this->any())
            ->method('getCheckInterval')
            ->willReturn(0)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--wait' => true, '--max-wait' => '0']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No tasks found after waiting', $output);
    }

    public function testOptionMaxWait(): void
    {
        $this->todoManager->expects($this->any())
            ->method('pop')
            ->willReturn(null)
        ;

        $this->configManager->expects($this->any())
            ->method('getCheckInterval')
            ->willReturn(0)
        ;

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--wait' => true, '--max-wait' => '1']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No tasks found after waiting 1 seconds', $output);
    }
}
