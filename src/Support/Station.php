<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

/**
 * 站点编码白名单。
 *
 * 只接受 src/data/stationid.php 中存在的编码，避免把任意值拼进上游 URL。
 * 不在白名单内时统一回退到默认城市（河南郑州），保证页面始终有数据。
 */
final class Station
{
    public const DEFAULT_CODE = 'YVItN';

    public const DEFAULT_CITY = '郑州';

    public const DEFAULT_PROVINCE = '河南省';

    /** @var array<string, true>|null */
    private static ?array $whitelist = null;

    /**
     * @return array<string, true>
     */
    public static function whitelist(): array
    {
        if (self::$whitelist === null) {
            /** @var array<int, string> $codes */
            $codes = (array) require dirname(__DIR__) . '/data/stationid.php';
            self::$whitelist = array_fill_keys($codes, true);
        }

        return self::$whitelist;
    }

    public static function isAllowed(string $code): bool
    {
        return $code !== '' && isset(self::whitelist()[$code]);
    }

    /**
     * 规范化站点编码：合法则原样返回，否则回退默认城市。
     */
    public static function normalize(string $code): string
    {
        $code = trim($code);

        return self::isAllowed($code) ? $code : self::DEFAULT_CODE;
    }
}