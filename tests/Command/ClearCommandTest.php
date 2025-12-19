<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ClaudeTodoBundle\Command\ClearCommand;
use Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ClearCommand::class)]
#[RunTestsInSeparateProcesses]
final class ClearCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    private TodoTaskRepository&MockObject $repository;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $this->repository = $this->createMock(TodoTaskRepository::class);

        $container = self::getContainer();
        $container->set(TodoTaskRepository::class, $this->repository);

        // Get the command from the service container
        /** @var ClearCommand $command */
        $command = $container->get(ClearCommand::class);

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithNoTasks(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(0)
        ;

        $this->repository->expects($this->never())
            ->method('clearAll')
        ;

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('没有任务需要清空。', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithTasksAndConfirmation(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(10)
        ;

        $this->repository->expects($this->once())
            ->method('clearAll')
            ->with(null)
            ->willReturn(10)
        ;

        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('即将删除 10 个任务！', $output);
        $this->assertStringContainsString('确定要删除这些任务吗？', $output);
        $this->assertStringContainsString('成功删除 10 个任务。', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithTasksAndNoConfirmation(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(10)
        ;

        $this->repository->expects($this->never())
            ->method('clearAll')
        ;

        $this->commandTester->setInputs(['no']);
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('即将删除 10 个任务！', $output);
        $this->assertStringContainsString('确定要删除这些任务吗？', $output);
        $this->assertStringContainsString('操作已取消。', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithForceOption(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(5)
        ;

        $this->repository->expects($this->once())
            ->method('clearAll')
            ->with(null)
            ->willReturn(5)
        ;

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('即将删除 5 个任务！', $output);
        $this->assertStringNotContainsString('确定要删除这些任务吗？', $output);
        $this->assertStringContainsString('成功删除 5 个任务。', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithGroupOption(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with(['groupName' => 'test'])
            ->willReturn(3)
        ;

        $this->repository->expects($this->once())
            ->method('clearAll')
            ->with('test')
            ->willReturn(3)
        ;

        $this->commandTester->execute(['--group' => 'test', '--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('即将删除 3 个分组 "test" 中的任务！', $output);
        $this->assertStringContainsString('成功删除 3 个分组 "test" 中的任务。', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithGroupAndNoTasks(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with(['groupName' => 'empty'])
            ->willReturn(0)
        ;

        $this->repository->expects($this->never())
            ->method('clearAll')
        ;

        $this->commandTester->execute(['--group' => 'empty']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('分组 "empty" 中没有任务。', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithException(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(10)
        ;

        $this->repository->expects($this->once())
            ->method('clearAll')
            ->with(null)
            ->willThrowException(new \Exception('Database error'))
        ;

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清空任务失败：Database error', $output);
        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
    }

    public function testOptionForce(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(5)
        ;

        $this->repository->expects($this->once())
            ->method('clearAll')
            ->with(null)
            ->willReturn(5)
        ;

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('成功删除 5 个任务', $output);
        $this->assertStringNotContainsString('确定要删除这些任务吗？', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testOptionGroup(): void
    {
        $this->repository->expects($this->once())
            ->method('count')
            ->with(['groupName' => 'test-group'])
            ->willReturn(3)
        ;

        $this->repository->expects($this->once())
            ->method('clearAll')
            ->with('test-group')
            ->willReturn(3)
        ;

        $this->commandTester->execute(['--group' => 'test-group', '--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('成功删除 3 个分组 "test-group" 中的任务', $output);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }
}
