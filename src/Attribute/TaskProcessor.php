<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Attribute;

#[\Attribute(flags: \Attribute::TARGET_CLASS)]
final class TaskProcessor
{
    public function __construct(
        public readonly int $priority = 0,
    ) {
    }
}
