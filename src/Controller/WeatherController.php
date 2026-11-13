<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Controller;

use think\facade\Config;
use think\Request;
use think\response\Json;
use Youvision\ThinkStoreWeather\Support\AudioRange;
use Youvision\ThinkStoreWeather\Support\RefreshDenied;
use Youvision\ThinkStoreWeather\Support\Station;
use Youvision\ThinkStoreWeather\View;
use Youvision\ThinkStoreWeather\Weather;

/**
 * 天气查询页面与 JSON 接口。
 *
 * 路由由服务提供者统一注册，默认地址：
 * - GET /store/think-store-weather/index            页面（think-view 渲染）
 * - GET /store/think-store-weather/api/provinces    省市联动数据
 * - GET /store/think-store-weather/api/weather      天气 JSON（默认读缓存）
 * - GET /store/think-store-weather/api/refresh      强制刷新天气 JSON（需 token）
 * - GET /store/think-store-weather/music            页面背景音乐（支持 Range）
 * - GET /store/think-store-weather/assets/<path>     包内静态资源（Layui、样式、脚本）
 */
class WeatherController
{
    private const AUDIO_FILE = '/data/zhengzhou.mp3';

    public function __construct(private Weather $weather, private View $view)
    {
    }

    public function index(): \think\Response
    {
        return response($this->view->render('dashboard', [
            'asset'            => $this->assetBase(),
            'api_base'         => $this->routeBase() . '/api',
            'music_url'        => $this->routeBase() . '/music',
            'version'          => $this->version(),
            'default_station'  => Station::DEFAULT_CODE,
            'default_city'     => Station::DEFAULT_CITY,
            'default_province' => Station::DEFAULT_PROVINCE,
        ]));
    }

    public function provinces(): Json
    {
        return json($this->weather->provinces());
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

        try {
            $result = $this->weather->query(
                $requested,
                $refreshEndpoint,
                (string) $request->param('token', '')
            );
        } catch (RefreshDenied $e) {
            return json([
                'code' => -1,
                'msg'  => $e->getMessage(),
                'data' => null,
            ], 403, ['X-Weather-Cache' => 'bypass']);
        } catch (\Throwable $e) {
            $fallback = $requested !== Station::normalize($requested);

            return json([
                'code' => -1,
                'msg'  => $e->getMessage(),
                'data' => null,
            ], 502, ['X-Weather-Fallback' => $fallback ? '1' : '0']);
        }

        return $this->payload(
            $result['body'],
            $result['stationid'],
            $result['fallback'],
            $result['cache']
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

    private function routeBase(): string
    {
        $prefix  = trim((string) Config::get('weather.route.prefix', 'store'), '/');
        $segment = trim((string) Config::get('weather.route.segment', 'think-store-weather'), '/');

        return ($prefix === '' ? '' : '/' . $prefix) . ($segment === '' ? '' : '/' . $segment);
    }

    private function assetBase(): string
    {
        $raw = trim((string) Config::get('weather.assets', 'assets'));

        if ($raw === '') {
            $raw = 'assets';
        }

        if (str_starts_with($raw, '/')) {
            return rtrim($raw, '/');
        }

        $base = $this->routeBase();

        return ($base === '' ? '' : $base) . '/' . trim($raw, '/');
    }

    /**
     * 包版本号，优先读取 Composer 运行时信息。
     */
    private function version(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('youvision/think-store-weather');

                if (is_string($version) && $version !== '') {
                    return $version;
                }
            } catch (\Throwable) {
            }
        }

        $file = dirname(__DIR__, 2) . '/composer.json';

        if (!is_file($file)) {
            return 'dev';
        }

        $json    = json_decode((string) file_get_contents($file), true);
        $version = is_array($json) ? (string) ($json['version'] ?? '') : '';

        return $version !== '' ? $version : 'dev';
    }
}