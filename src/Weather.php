<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather;

use Youvision\ThinkStoreWeather\Support\Province;
use Youvision\ThinkStoreWeather\Support\RefreshDenied;
use Youvision\ThinkStoreWeather\Support\RefreshToken;
use Youvision\ThinkStoreWeather\Support\Station;
use Youvision\ThinkStoreWeather\Support\WeatherCache;
use Youvision\ThinkStoreWeather\Support\WeatherClient;

/**
 * 天气模块核心服务。
 *
 * 控制器只负责 HTTP 细节，站点校验、缓存与回源都在这里完成；
 * 可通过容器别名 weather 获取，也可脱离容器直接实例化。
 */
final class Weather
{
    public const CACHE_PREFIX = 'weather:station:';

    private string $endpoint;

    private int $timeout;

    private int $cacheTtl;

    private string $refreshToken;

    private string $referer;

    /** @var (callable(string): ?string)|null */
    private $fetcher;

    /**
     * @param (callable(string): ?string)|null $fetcher 便于测试注入，签名：url => body|null
     */
    public function __construct(
        string $endpoint = '',
        int $timeout = 8,
        int $cacheTtl = 28800,
        string $refreshToken = '',
        ?callable $fetcher = null,
        string $referer = ''
    ) {
        $this->endpoint     = trim($endpoint);
        $this->timeout      = max(1, $timeout);
        $this->cacheTtl     = max(60, $cacheTtl);
        $this->refreshToken = $refreshToken;
        $this->referer      = trim($referer);
        $this->fetcher      = $fetcher;
    }

    /**
     * 省/市站点树，城市节点 code 即天气接口的 stationid。
     *
     * @return array<int, array<string, mixed>>
     */
    public function provinces(): array
    {
        return Province::tree();
    }

    /**
     * 查询天气 JSON。
     *
     * @return array{body: string, stationid: string, fallback: bool, cache: string}
     *
     * @throws RefreshDenied 强制刷新令牌无效或未配置
     */
    public function query(string $stationid = '', bool $refresh = false, string $token = ''): array
    {
        $requested  = trim($stationid);
        $normalized = Station::normalize($requested);
        $authorized = RefreshToken::matches($token, $this->refreshToken);

        if ($refresh && !$authorized) {
            throw new RefreshDenied('刷新令牌无效或未配置');
        }

        $cache = new WeatherCache(self::CACHE_PREFIX, $this->cacheTtl);

        // 令牌正确时跳过缓存，直接回源并覆盖缓存
        if (!$authorized) {
            $cached = $cache->get($normalized);

            if ($cached !== null) {
                return [
                    'body'      => $cached,
                    'stationid' => $normalized,
                    'fallback'  => $requested !== $normalized,
                    'cache'     => 'hit',
                ];
            }
        }

        $result = (new WeatherClient($this->endpoint, $this->timeout, $this->fetcher, $this->referer))->query($requested);

        $cache->set($result['stationid'], $result['body']);

        return [
            'body'      => $result['body'],
            'stationid' => $result['stationid'],
            'fallback'  => $result['fallback'],
            'cache'     => $authorized ? 'refresh' : 'miss',
        ];
    }
}