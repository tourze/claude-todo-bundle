<?php

declare(strict_types=1);

namespace Tourze\ClaudeTodoBundle\Exception;

/**
 * 基础抽象异常类，所有 ClaudeTodoBundle 的异常都应继承此类
 */
abstract class ClaudeTodoException extends \RuntimeException implements TodoBundleExceptionInterface
{
}
