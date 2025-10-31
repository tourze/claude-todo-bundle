<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\ClaudeTodoBundle\DependencyInjection\ClaudeTodoExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(ClaudeTodoExtension::class)]
final class ClaudeTodoExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private ClaudeTodoExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new ClaudeTodoExtension();
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('claude_todo', $this->extension->getAlias());
    }

    public function testExtensionLoadsServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $this->extension->load([], $container);

        // 验证别名被正确创建
        $this->assertTrue($container->hasAlias('tourze_claude_todo.repository.todo_task'));
        $this->assertEquals('Tourze\ClaudeTodoBundle\Repository\TodoTaskRepository',
            (string) $container->getAlias('tourze_claude_todo.repository.todo_task'));
    }
}
