<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

use RuntimeException;

/**
 * 远程天气接口客户端。
 *
 * 上游返回的 JSON 原样交给调用方，本类只负责白名单校验与回退，
 * 不做字段裁剪或重新编码。
 */
final class WeatherClient
{
    public const DEFAULT_ENDPOINT = 'https://www.nmc.cn/rest/weather?stationid=%s';

    private string $endpoint;

    private int $timeout;

    /** @var (callable(string): ?string)|null */
    private $fetcher;

    /**
     * @param (callable(string): ?string)|null $fetcher 便于测试注入，签名：url => body|null
     */
    public function __construct(string $endpoint = self::DEFAULT_ENDPOINT, int $timeout = 8, ?callable $fetcher = null)
    {
        $this->endpoint = $endpoint;
        $this->timeout  = max(1, $timeout);
        $this->fetcher  = $fetcher;
    }

    /**
     * @return array{requested: string, stationid: string, fallback: bool, body: string}
     *
     * @throws RuntimeException 上游不可用时抛出
     */
    public function query(string $stationid): array
    {
        $requested = trim($stationid);
        $stationid = Station::normalize($requested);
        $url       = sprintf($this->endpoint, rawurlencode($stationid));

        $body = $this->fetcher !== null
            ? ($this->fetcher)($url)
            : self::download($url, $this->timeout);

        if (!is_string($body) || trim($body) === '') {
            throw new RuntimeException('天气服务暂时不可用，请稍后重试');
        }

        return [
            'requested' => $requested,
            'stationid' => $stationid,
            'fallback'  => $requested !== $stationid,
            'body'      => $body,
        ];
    }

    /**
     * 优先用 curl，缺失时退回 stream，兼容不同宿主环境。
     */
    public static function download(string $url, int $timeout): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            if ($ch === false) {
                return null;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Referer: https://www.nmc.cn/',
                    'User-Agent: Mozilla/5.0 (compatible; think-store-weather)',
                ],
            ]);

            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return is_string($body) && $status >= 200 && $status < 300 ? $body : null;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\n"
                    . "Referer: https://www.nmc.cn/\r\n"
                    . "User-Agent: Mozilla/5.0 (compatible; think-store-weather)\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if (!is_string($body) || !isset($http_response_header[0])) {
            return null;
        }

        if (!preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $matches)) {
            return null;
        }

        $status = (int) $matches[1];

        return $status >= 200 && $status < 300 ? $body : null;
    }
}