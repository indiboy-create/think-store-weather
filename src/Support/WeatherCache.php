<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

use think\facade\Cache;

/**
 * 天气响应缓存。
 *
 * 缓存上游原样返回的 JSON 字符串，命中时不再请求 nmc.cn。
 * 宿主缓存未配置或不可用时自动降级为不缓存，不影响接口返回。
 */
final class WeatherCache
{
    public function __construct(
        private string $prefix = 'weather:nmc:',
        private int $ttl = 28800
    ) {
        $this->ttl = max(60, $this->ttl);
    }

    public function key(string $stationid): string
    {
        return $this->prefix . $stationid;
    }

    public function get(string $stationid): ?string
    {
        try {
            $value = Cache::get($this->key($stationid));
        } catch (\Throwable $e) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function set(string $stationid, string $body): void
    {
        try {
            Cache::set($this->key($stationid), $body, $this->ttl);
        } catch (\Throwable $e) {
            // 缓存不可用时跳过写入，接口仍返回上游数据
        }
    }
}