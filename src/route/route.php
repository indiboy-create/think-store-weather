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
 * 规则末尾的 $ 表示完整匹配，避免 /weather 吞掉 /weather/api/*。
 */
Route::get('weather$', [WeatherController::class, 'index'])->name('weather.index');
Route::get('weather/api/provinces$', [WeatherController::class, 'provinces'])->name('weather.provinces');
Route::get('weather/api/weather$', [WeatherController::class, 'weather'])->name('weather.api');
Route::get('weather/api/refresh$', [WeatherController::class, 'refresh'])->name('weather.refresh');

// 静态资源直接由路由输出，无需发布到 public
Route::get('weather/assets/<path>', [AssetController::class, 'file'])
    ->pattern(['path' => '.*'])
    ->name('weather.asset');