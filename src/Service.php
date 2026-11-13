<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather;

use think\Service as BaseService;

/**
 * 服务提供者。
 *
 * register() 只做配置合并与容器绑定，不产生 IO；boot() 才注册路由。
 */
class Service extends BaseService
{
    public function register(): void
    {
        $this->mergeConfig();
        $this->bindWeather();
        $this->app->instance(View::class, new View($this->app));
    }

    public function boot(): void
    {
        if ($this->app->config->get('weather.route.enabled', true)) {
            $this->loadRoutesFrom(dirname(__DIR__) . '/route/weather.php');
        }
    }

    /**
     * 包内默认配置只负责补齐缺失的键，项目侧配置优先。
     */
    protected function mergeConfig(): void
    {
        /** @var array<string, mixed> $default */
        $default = require dirname(__DIR__) . '/config/weather.php';

        /** @var array<string, mixed> $current */
        $current = (array) $this->app->config->get('weather', []);

        $this->app->config->set(array_replace_recursive($default, $current), 'weather');
    }

    /**
     * 注册核心服务，支持 app('weather') 与 app(Weather::class) 两种取法。
     */
    protected function bindWeather(): void
    {
        $this->app->bind('weather', function (): Weather {
            /** @var array<string, mixed> $config */
            $config = (array) $this->app->config->get('weather', []);

            /** @var array<string, mixed> $api */
            $api = (array) ($config['api'] ?? []);

            return new Weather(
                endpoint: (string) ($api['endpoint'] ?? ''),
                timeout: (int) ($api['timeout'] ?? 8),
                cacheTtl: (int) ($api['cache_ttl'] ?? 28800),
                refreshToken: (string) ($api['refresh_token'] ?? ''),
                referer: (string) ($api['referer'] ?? '')
            );
        });

        $this->app->bind(Weather::class, 'weather');
    }
}