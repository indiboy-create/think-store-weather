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
 * 前缀与中间件来自 config/weather.php：
 * - weather.route.enabled  是否注册内置路由
 * - weather.route.prefix   路由前缀
 * - weather.route.middleware 应用到整组路由的中间件
 */
$prefix     = trim((string) config('weather.route.prefix', 'weather'), '/');
$middleware = (array) config('weather.route.middleware', []);

$register = static function (): void {
    Route::get('', [WeatherController::class, 'index'])->name('weather.index')->completeMatch();
    Route::get('api/provinces', [WeatherController::class, 'provinces'])->name('weather.provinces')->completeMatch();
    Route::get('api/weather', [WeatherController::class, 'weather'])->name('weather.api')->completeMatch();
    Route::get('api/refresh', [WeatherController::class, 'refresh'])->name('weather.refresh')->completeMatch();
    Route::get('music', [WeatherController::class, 'music'])->name('weather.music')->completeMatch();

    // 静态资源直接由路由输出，无需发布到 public
    Route::get('assets/<path>', [AssetController::class, 'file'])
        ->pattern(['path' => '.*'])
        ->name('weather.asset');
};

if ($prefix === '') {
    $register();
} else {
    $group = Route::group($prefix, $register);

    if ($middleware !== []) {
        $group->middleware($middleware);
    }
}