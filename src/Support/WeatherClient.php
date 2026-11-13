<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

use RuntimeException;

/**
 * 远程天气接口客户端。
 *
 * 上游返回的 JSON 原样交给调用方，本类只负责白名单校验与回退，
 * 不做字段裁剪或重新编码。上游地址与 Referer 全部由配置注入。
 */
final class WeatherClient
{
    private string $endpoint;

    private int $timeout;

    private string $referer;

    private ?string $lastError = null;

    /** @var (callable(string): ?string)|null */
    private $fetcher;

    /**
     * @param (callable(string): ?string)|null $fetcher 便于测试注入，签名：url => body|null
     */
    public function __construct(
        string $endpoint = '',
        int $timeout = 8,
        ?callable $fetcher = null,
        string $referer = ''
    ) {
        $this->endpoint = trim($endpoint);
        $this->timeout  = max(1, $timeout);
        $this->referer  = trim($referer);
        $this->fetcher  = $fetcher;
    }

    /**
     * @return array{requested: string, stationid: string, fallback: bool, body: string}
     *
     * @throws RuntimeException 上游地址未配置或不可用时抛出
     */
    public function query(string $stationid): array
    {
        if ($this->endpoint === '') {
            throw new RuntimeException('天气接口地址未配置');
        }

        $requested = trim($stationid);
        $stationid = Station::normalize($requested);
        $url       = sprintf($this->endpoint, rawurlencode($stationid));

        $body = $this->fetcher !== null
            ? ($this->fetcher)($url)
            : $this->download($url);

        if (!is_string($body) || trim($body) === '') {
            throw new RuntimeException($this->lastError ?? '天气服务暂时不可用，请稍后重试');
        }

        return [
            'requested' => $requested,
            'stationid' => $stationid,
            'fallback'  => $requested !== $stationid,
            'body'      => $body,
        ];
    }

    /**
     * 最近一次请求失败的原因，成功时为 null。
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * 优先用 curl，缺失时退回 stream，兼容不同宿主环境。
     */
    private function download(string $url): ?string
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (compatible; think-store-weather)',
        ];

        if ($this->referer !== '') {
            $headers[] = 'Referer: ' . $this->referer;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            if ($ch === false) {
                $this->lastError = '天气服务暂时不可用：curl 初始化失败';

                return null;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => $headers,
            ]);

            $body   = curl_exec($ch);
            $errno  = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($errno !== 0) {
                $this->lastError = $this->describeCurlError($errno);

                return null;
            }

            if ($status < 200 || $status >= 300) {
                $this->lastError = '天气服务暂时不可用：上游返回 HTTP ' . $status;

                return null;
            }

            return is_string($body) ? $body : null;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            $this->lastError = '天气服务暂时不可用：未启用 curl，且 allow_url_fopen 已关闭';

            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
                'header'        => implode("\r\n", $headers) . "\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if (!is_string($body) || !isset($http_response_header[0])) {
            $this->lastError = '天气服务暂时不可用：无法连接上游';

            return null;
        }

        if (!preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $matches)) {
            $this->lastError = '天气服务暂时不可用：无法识别上游响应';

            return null;
        }

        $status = (int) $matches[1];

        if ($status < 200 || $status >= 300) {
            $this->lastError = '天气服务暂时不可用：上游返回 HTTP ' . $status;

            return null;
        }

        return $body;
    }

    private function describeCurlError(int $errno): string
    {
        if ($errno === 60 || $errno === 77) {
            return '天气服务暂时不可用：SSL 证书校验失败，请检查 PHP 的 curl.cainfo 配置';
        }

        if ($errno === 28) {
            return '天气服务暂时不可用：请求上游超时';
        }

        return '天气服务暂时不可用：请求上游失败（curl ' . $errno . '）';
    }
}