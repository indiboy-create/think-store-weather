<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Controller;

use think\facade\Config;
use think\Request;
use think\response\View;
use Youvision\ThinkStoreWeather\Support\AudioRange;
use Youvision\ThinkStoreWeather\Support\Province;
use Youvision\ThinkStoreWeather\Support\RefreshToken;
use Youvision\ThinkStoreWeather\Support\Station;
use Youvision\ThinkStoreWeather\Support\WeatherCache;
use Youvision\ThinkStoreWeather\Support\WeatherClient;

/**
 * 天气查询页面与 JSON 接口。
 *
 * 路由由服务提供者统一注册，默认前缀 /weather：
 * - GET /weather                     页面（think-view 渲染）
 * - GET /weather/api/provinces       省市联动数据
 * - GET /weather/api/weather         天气 JSON（默认读缓存）
 * - GET /weather/api/refresh         强制刷新天气 JSON（需 token）
 * - GET /weather/assets/<path>       包内静态资源（Layui、样式、脚本）
 * - GET /weather/music               页面背景音乐（支持 Range）
 */
class WeatherController
{
    private const AUDIO_FILE = '/data/zhengzhou.mp3';

    public function index(): View
    {
        return view(dirname(__DIR__, 2) . '/theme/view/dashboard.html', [
            'asset'            => $this->assetBase(),
            'api_base'         => '/weather/api',
            'music_url'        => '/weather/music',
            'default_station'  => Station::DEFAULT_CODE,
            'default_city'     => Station::DEFAULT_CITY,
            'default_province' => Station::DEFAULT_PROVINCE,
        ]);
    }

    public function provinces(): \think\response\Json
    {
        return json(Province::tree());
    }

    public function weather(Request $request): \think\Response
    {
        return $this->respond($request, false);
    }

    public function refresh(Request $request): \think\Response
    {
        return $this->respond($request, true);
    }

    public function music(Request $request): \think\Response
    {
        $file = dirname(__DIR__) . self::AUDIO_FILE;

        if (!is_file($file)) {
            return response('', 404);
        }

        $size = (int) filesize($file);
        $raw  = $request->header('range');
        $range = AudioRange::parse(is_string($raw) ? $raw : null, $size);

        if ($range === null) {
            $body = (string) file_get_contents($file);

            return response($body, 200, [
                'Content-Type'   => 'audio/mpeg',
                'Content-Length' => (string) $size,
                'Accept-Ranges'  => 'bytes',
                'Cache-Control'  => 'public, max-age=86400',
            ]);
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return response('', 500);
        }

        fseek($handle, $range['start']);
        $body = (string) fread($handle, $range['end'] - $range['start'] + 1);
        fclose($handle);

        return response($body, 206, [
            'Content-Type'   => 'audio/mpeg',
            'Content-Length' => (string) strlen($body),
            'Content-Range'  => sprintf('bytes %d-%d/%d', $range['start'], $range['end'], $size),
            'Accept-Ranges'  => 'bytes',
            'Cache-Control'  => 'public, max-age=86400',
        ]);
    }

    private function respond(Request $request, bool $refreshEndpoint): \think\Response
    {
        $requested = trim((string) $request->param('stationid', ''));
        $stationid = Station::normalize($requested);
        $fallback  = $requested !== $stationid;

        $expectedToken = (string) Config::get('weather.api.refresh_token', '');
        $token         = (string) $request->param('token', '');
        $authorized    = RefreshToken::matches($token, $expectedToken);

        if ($refreshEndpoint && !$authorized) {
            return json([
                'code' => -1,
                'msg'  => '刷新令牌无效或未配置',
                'data' => null,
            ], 403, ['X-Weather-Cache' => 'bypass']);
        }

        $cache = new WeatherCache(
            'weather:station:',
            (int) Config::get('weather.api.cache_ttl', 28800)
        );

        // 令牌正确时跳过缓存，直接回源并覆盖缓存
        if (!$authorized) {
            $cached = $cache->get($stationid);

            if ($cached !== null) {
                return $this->payload($cached, $stationid, $fallback, 'hit');
            }
        }

        $endpoint = (string) Config::get('weather.api.endpoint', WeatherClient::DEFAULT_ENDPOINT);
        $timeout  = (int) Config::get('weather.api.timeout', 8);

        try {
            $result = (new WeatherClient($endpoint, $timeout))->query($requested);
        } catch (\Throwable $e) {
            return json([
                'code' => -1,
                'msg'  => $e->getMessage(),
                'data' => null,
            ], 502, ['X-Weather-Fallback' => $fallback ? '1' : '0']);
        }

        $cache->set($result['stationid'], $result['body']);

        return $this->payload(
            $result['body'],
            $result['stationid'],
            $result['fallback'],
            $authorized ? 'refresh' : 'miss'
        );
    }

    private function payload(string $body, string $stationid, bool $fallback, string $cache): \think\Response
    {
        return response($body, 200, [
            'Content-Type'       => 'application/json; charset=utf-8',
            'X-Weather-Station'  => $stationid,
            'X-Weather-Fallback' => $fallback ? '1' : '0',
            'X-Weather-Cache'    => $cache,
        ]);
    }

    private function assetBase(): string
    {
        $prefix = trim((string) Config::get('weather.assets', AssetController::PREFIX), '/');

        return '/' . ($prefix === '' ? trim(AssetController::PREFIX, '/') : $prefix);
    }
}