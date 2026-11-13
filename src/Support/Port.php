<?php

declare(strict_types=1);

namespace Youvision\Lizi\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * 纯逻辑端口工具，不依赖框架，便于单元测试。
 */
final class Port
{
    public const MIN = 1;

    public const MAX = 65535;

    /**
     * 端口是否空闲。连接成功说明已被占用。
     */
    public static function isFree(string $host, int $port): bool
    {
        $conn = @fsockopen($host, $port, $errno, $errstr, 0.2);

        if ($conn === false) {
            return true;
        }

        fclose($conn);

        return false;
    }

    /**
     * 从 preferred 起向后寻找第一个空闲端口。
     *
     * @throws RuntimeException 连续 limit 个端口均被占用
     */
    public static function find(string $host, int $preferred, int $limit = 20): int
    {
        $preferred = self::normalize($preferred);
        $limit     = max(1, $limit);

        $checked = 0;

        for ($candidate = $preferred; $candidate <= self::MAX && $checked < $limit; $candidate++, $checked++) {
            if (self::isFree($host, $candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            sprintf('端口 %d 起连续 %d 个均被占用，请用 -p 指定其他端口', $preferred, $limit)
        );
    }

    /**
     * @throws InvalidArgumentException 超出合法区间
     */
    public static function normalize(int $port): int
    {
        if ($port < self::MIN || $port > self::MAX) {
            throw new InvalidArgumentException(
                sprintf('端口必须在 %d-%d 之间，当前为 %d', self::MIN, self::MAX, $port)
            );
        }

        return $port;
    }
}
