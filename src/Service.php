<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather;

use think\Service as BaseService;

/**
 * 服务提供者。
 *
 * register() 只做配置合并，不产生 IO；boot() 才注册路由。
 */
class Service extends BaseService
{
    public function register(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) include __DIR__ . '/../config/weather.php';

        /** @var array<string, mixed> $current */
        $current = (array) $this->app->config->get('weather', []);

        // 项目侧配置优先，包内默认值只负责补齐缺失的键
        $this->app->config->set(['weather' => array_replace_recursive($defaults, $current)]);

        // 页面依赖 think-view 提供的 Think 驱动；宿主已有视图配置时保持原样
        if (!$this->app->config->has('view.type')) {
            $this->app->config->set(['view' => ['type' => 'Think']]);
        }
    }

    public function boot(): void
    {
        $this->loadRoutes();
    }

    /**
     * 加载包内路由定义；关闭 weather.route.enabled 时交给主项目自行注册。
     */
    private function loadRoutes(): void
    {
        if (!$this->app->config->get('weather.route.enabled', true)) {
            return;
        }

        $route = __DIR__ . '/route/route.php';

        if (is_file($route)) {
            include $route;
        }
    }
}