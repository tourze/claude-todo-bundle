<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

final class ClaudeTodoExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function getAlias(): string
    {
        return 'claude_todo';
    }
}
