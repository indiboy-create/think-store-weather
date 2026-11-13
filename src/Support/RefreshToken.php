<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

/**
 * 强制刷新令牌校验。
 *
 * 未配置令牌时一律拒绝，避免默认放行。
 */
final class RefreshToken
{
    public static function matches(string $given, string $expected): bool
    {
        if ($expected === '' || $given === '') {
            return false;
        }

        return hash_equals($expected, $given);
    }
}