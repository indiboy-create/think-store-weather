<?php

declare(strict_types=1);

use think\facade\Route;
use Youvision\ThinkStoreWeather\Controller\AssetController;
use Youvision\ThinkStoreWeather\Controller\WeatherController;

/*
 * 天气模块路由。
 *
 * 由 Youvision\ThinkStoreWeather\Service 在 boot 阶段加载，
 * 宿主项目引入本包后无需再复制路由文件。
 *
 * 前缀、命名空间与中间件来自 config/weather.php：
 * - weather.route.enabled    是否注册内置路由
 * - weather.route.prefix     路由前缀，默认 store
 * - weather.route.segment    包内命名空间，默认 think-store-weather
 * - weather.route.middleware 应用到整组路由的中间件
 */
$prefix     = trim((string) config('weather.route.prefix', 'store'), '/');
$segment    = trim((string) config('weather.route.segment', 'think-store-weather'), '/');
$middleware = (array) config('weather.route.middleware', []);

$register = static function () use ($segment): void {
    $base = $segment === '' ? '' : $segment . '/';

    Route::get($base . 'index', [WeatherController::class, 'index'])->name('weather.index')->completeMatch();
    Route::get($base . 'api/provinces', [WeatherController::class, 'provinces'])->name('weather.provinces')->completeMatch();
    Route::get($base . 'api/weather', [WeatherController::class, 'weather'])->name('weather.api')->completeMatch();
    Route::get($base . 'api/refresh', [WeatherController::class, 'refresh'])->name('weather.refresh')->completeMatch();
    Route::get($base . 'music', [WeatherController::class, 'music'])->name('weather.music')->completeMatch();

    // 静态资源直接由路由输出，无需发布到 public
    Route::get('assets/<path>', [AssetController::class, 'file'])
        ->pattern(['path' => '.*'])
        ->name('weather.asset');
};

$group = Route::group($prefix, $register);
$group->middleware($middleware);