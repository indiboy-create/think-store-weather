<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

use RuntimeException;

/**
 * 强制刷新令牌无效或未配置时抛出。
 */
final class RefreshDenied extends RuntimeException
{
}