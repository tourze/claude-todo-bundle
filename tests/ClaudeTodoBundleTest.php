<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ClaudeTodoBundle\ClaudeTodoBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(ClaudeTodoBundle::class)]
#[RunTestsInSeparateProcesses]
final class ClaudeTodoBundleTest extends AbstractBundleTestCase
{
}
