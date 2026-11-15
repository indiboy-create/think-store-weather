<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$failures    = [];

$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

spl_autoload_register(static function (string $class) use ($packageRoot): void {
    $prefix = 'Youvision\\ThinkStoreWeather\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = $packageRoot . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

$composer = json_decode((string) file_get_contents($packageRoot . '/composer.json'), true);

$check(is_array($composer), 'composer.json 应为有效 JSON');
$check(($composer['name'] ?? '') === 'youvision/think-store-weather', '包名应为 youvision/think-store-weather');
$check(($composer['type'] ?? '') === 'library', 'composer.json 的 type 应为 library');
$check(
    ($composer['require']['topthink/framework'] ?? '') === '^8.0',
    'composer.json 应依赖 topthink/framework ^8.0'
);
$check(
    ($composer['require']['topthink/think-view'] ?? '') === '^2.0',
    'composer.json 应依赖 topthink/think-view ^2.0'
);
$check(
    ($composer['autoload']['psr-4']['Youvision\\ThinkStoreWeather\\'] ?? '') === 'src/',
    'composer.json 应配置 Youvision\\ThinkStoreWeather 的 PSR-4 自动加载'
);
$check(
    in_array('Youvision\\ThinkStoreWeather\\Service', (array) ($composer['extra']['think']['services'] ?? []), true),
    'composer.json 应注册 ThinkPHP 服务'
);
$check(
    ($composer['extra']['think']['config']['store_weather'] ?? '') === 'config/store_weather.php',
    'composer.json 应声明 store_weather 配置发布'
);
$check(
    ($composer['extra']['store']['home'] ?? '') === '/store/think-store-weather/index',
    'composer.json 应声明商店首页路由'
);
$check(
    ($composer['scripts']['test'] ?? '') === '@php tests/verify.php',
    'composer.json 应提供 test 脚本'
);

$configFile = $packageRoot . '/config/store_weather.php';
$check(is_file($configFile), 'config/store_weather.php 应存在');

$config = require $configFile;

$check(($config['route']['enabled'] ?? null) === true, '默认应启用内置路由');
$check(($config['route']['prefix'] ?? '') === 'store', '默认路由前缀应为 store');
$check(($config['route']['segment'] ?? '') === 'think-store-weather', '默认包内命名空间应为 think-store-weather');
$check(($config['route']['middleware'] ?? null) === [], '默认路由中间件应为空数组');
$check(($config['assets'] ?? '') === 'assets', '默认静态资源路径应为 assets');
$check(($config['view'] ?? null) === '', '默认视图配置应为空，使用包内模板');

$check(($config['api']['cache_ttl'] ?? 0) === 28800, '默认缓存时间应为 28800 秒');
$check(str_contains((string) ($config['api']['endpoint'] ?? ''), '%s'), '上游地址应包含 %s 占位符');
$check(($config['api']['referer'] ?? '') !== '', '默认应配置上游 Referer');

foreach ([
    'composer.json',
    'config/store_weather.php',
    'route/store_weather.php',
    'src/Service.php',
    'src/Weather.php',
    'src/View.php',
    'src/Controller/WeatherController.php',
    'src/Controller/AssetController.php',
    'src/Support/RefreshDenied.php',
    'src/Support/WeatherClient.php',
    'src/Support/WeatherCache.php',
    'theme/view/common/layout.html',
    'theme/view/index.html',
    'theme/view/iframe.html',
    'theme/view/iframe/weather.html',
    'theme/assets/css/site.css',
    'theme/assets/js/app.js',
    'theme/assets/iframe/css/iframe.css',
    'theme/assets/iframe/css/weather.css',
    'theme/assets/iframe/js/iframe.js',
    'theme/assets/iframe/js/weather.js',
    'theme/assets/layui/layui.js',
    'theme/assets/layui/css/layui.css',
    'README.md',
    'LICENSE',
] as $file) {
    $check(is_file($packageRoot . '/' . $file), $file . ' 应存在');
}

$check(!is_file($packageRoot . '/src/route/route.php'), '路由应已迁移到顶层 route/store_weather.php');
$check(!is_file($packageRoot . '/theme/view/dashboard.html'), '页面模板应已迁移到 theme/view/index.html');

$upstreamHost = 'nmc' . '.cn';

foreach ([
    'src/Weather.php',
    'src/Service.php',
    'src/Controller/WeatherController.php',
    'src/Support/WeatherClient.php',
    'route/store_weather.php',
    'README.md',
    'CHANGELOG.md',
    'theme/view/common/layout.html',
    'theme/view/index.html',
    'theme/view/iframe.html',
    'theme/view/iframe/weather.html',
] as $file) {
    $check(
        !str_contains((string) file_get_contents($packageRoot . '/' . $file), $upstreamHost),
        $file . ' 不应硬编码上游域名，只允许出现在 config/store_weather.php'
    );
}

$clientSource = (string) file_get_contents($packageRoot . '/src/Support/WeatherClient.php');

$check(!str_contains($clientSource, 'DEFAULT_ENDPOINT'), '上游地址不应提供源码常量默认值');
$check(str_contains($clientSource, 'referer'), '客户端应支持注入 Referer');
$check(str_contains($clientSource, '天气接口地址未配置'), '上游地址缺失时应给出明确错误');

$routeSource = (string) file_get_contents($packageRoot . '/route/store_weather.php');

$check(str_contains($routeSource, "\$segment"), '路由应读取包内命名空间配置');
$check(str_contains($routeSource, "\$base . 'index'"), '路由应注册带命名空间的页面地址');
$check(str_contains($routeSource, "\$base . 'iframe'"), '路由应注册内嵌页面示例地址');
$check(str_contains($routeSource, "\$base . 'iframe/weather'"), '路由应注册内嵌天气内容页地址');
$check(str_contains($routeSource, "\$base . 'api/provinces'"), '路由应注册省市联动接口');
$check(str_contains($routeSource, "\$base . 'api/weather'"), '路由应注册天气接口');
$check(str_contains($routeSource, "\$base . 'api/refresh'"), '路由应注册刷新接口');
$check(str_contains($routeSource, "\$base . 'music'"), '路由应注册音乐地址');
$check(str_contains($routeSource, "Route::get(\$base . 'assets/<path>'"), '路由应注册带命名空间的静态资源路径');
$check(str_contains($routeSource, "pattern(['path' => '.*'])"), '资源路由应匹配多级路径');
$check(str_contains($routeSource, 'Route::group($prefix, $register)'), '路由应统一按前缀分组注册');
$check(str_contains($routeSource, 'middleware($middleware)'), '路由应应用中间件配置');
$check(!str_contains($routeSource, "if (\$prefix === '')"), '空前缀时也应走分组并应用中间件');

$serviceSource = (string) file_get_contents($packageRoot . '/src/Service.php');

$check(str_contains($serviceSource, 'loadRoutesFrom'), '服务应在 boot 阶段加载路由');
$check(str_contains($serviceSource, "bind('think-store-weather'"), '服务应绑定 think-store-weather 容器别名');
$check(str_contains($serviceSource, 'bind(Weather::class'), '服务应绑定 Weather 类名');
$check(str_contains($serviceSource, 'mergeConfig'), '服务应合并包内默认配置');
$check(str_contains($serviceSource, "dirname(__DIR__) . '/route/store_weather.php'"), '服务应加载顶层路由文件');
$check(str_contains($serviceSource, 'instance(View::class'), '服务应注册包内视图渲染器');

$viewSource = (string) file_get_contents($packageRoot . '/src/View.php');

$check(str_contains($viewSource, 'think\\view\\driver\\Think'), '渲染器应使用 think-view 的 Think 驱动');
$check(str_contains($viewSource, "'view_path'"), '渲染器应指定模板目录');
$check(str_contains($viewSource, '/theme/view/'), '渲染器应指向包内 theme/view 目录');
$check(str_contains($viewSource, "'store_weather.view'"), '渲染器应读取视图来源配置');
$check(str_contains($viewSource, 'view.view_path'), '渲染器应读取宿主视图目录');
$check(str_contains($viewSource, 'getRootPath'), '宿主视图目录缺失时应回退根目录');
$check(str_contains($viewSource, "'think-store-weather'"), '渲染器应定位宿主 think-store-weather 目录');
$check(str_contains($viewSource, 'resolvePath'), '渲染器应解析模板目录');
$check(str_contains($viewSource, 'cache_path'), '渲染器应配置模板缓存目录');
$check(str_contains($viewSource, 'getRuntimePath'), '渲染器应使用宿主运行时目录');
$check(str_contains($viewSource, 'cache_prefix'), '渲染器应使用独立缓存前缀');
$check(str_contains($viewSource, 'ob_start'), '渲染器应捕获模板输出');
$check(str_contains($viewSource, 'templateFile'), '渲染器应解析完整模板路径');

$controllerSource = (string) file_get_contents($packageRoot . '/src/Controller/WeatherController.php');

foreach (['index', 'iframe', 'embed', 'provinces', 'weather', 'refresh', 'music'] as $method) {
    $check(str_contains($controllerSource, 'function ' . $method), '控制器应提供 ' . $method . ' 动作');
}

$check(str_contains($controllerSource, 'Weather $weather'), '控制器应注入核心服务');
$check(str_contains($controllerSource, 'View $view'), '控制器应注入包内渲染器');
$check(str_contains($controllerSource, "\$this->view->render('index'"), '控制器应通过包内渲染器输出首页');
$check(str_contains($controllerSource, "\$this->view->render('iframe'"), '控制器应输出内嵌示例页');
$check(str_contains($controllerSource, "\$this->view->render('iframe/weather'"), '控制器应输出内嵌天气内容页');
$check(str_contains($controllerSource, 'pages_json'), '控制器应向内嵌示例页提供可内嵌页面清单');
$check(str_contains($controllerSource, 'embed_code'), '控制器应向内容页提供内嵌代码示例');
$check(str_contains($controllerSource, 'function version'), '控制器应提供版本回退');
$check(str_contains($controllerSource, "'dev'"), '控制器应提供开发版本回退');
$check(str_contains($controllerSource, 'InstalledVersions'), '控制器应从 Composer 运行时读取版本');

if (!class_exists(\Youvision\ThinkStoreWeather\Controller\AssetController::class)) {
    require_once $packageRoot . '/src/Controller/AssetController.php';
}

$check(\Youvision\ThinkStoreWeather\Controller\AssetController::resolve('layui/css/layui.css') !== null, '应能解析包内静态资源');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::resolve('../composer.json') === null, '应拒绝目录穿越');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::resolve('..') === null, '应拒绝上级目录');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::resolve('') === null, '空路径应返回 null');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::resolve('missing.css') === null, '不存在的资源应返回 null');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::mime('a.css') === 'text/css; charset=utf-8', 'CSS MIME 应正确');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::mime('a.js') === 'application/javascript; charset=utf-8', 'JS MIME 应正确');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::mime('a.woff2') === 'font/woff2', '字体 MIME 应正确');
$check(\Youvision\ThinkStoreWeather\Controller\AssetController::mime('a.unknown') === 'application/octet-stream', '未知类型应回退为 octet-stream');

$layout = (string) file_get_contents($packageRoot . '/theme/view/common/layout.html');

$check(str_contains($layout, '{$asset}/layui/css/layui.css'), '布局模板应引用本地 Layui');
$check(str_contains($layout, '{$asset}/css/site.css'), '布局模板应引用页面样式');
$check(str_contains($layout, 'layui-nav'), '布局模板的导航应使用 layui 导航组件');
$check(str_contains($layout, 'wx-footer'), '布局模板应包含页脚');
$check(str_contains($layout, '{$base}/iframe'), '布局模板应提供内嵌示例入口');
$check(str_contains($layout, '{$music_url}'), '布局模板应使用音乐地址变量');
$check(str_contains($layout, '{$version}'), '布局模板应输出版本号');
$check(!str_contains($layout, 'cdn'), '布局模板不应使用 CDN 资源');

$template = (string) file_get_contents($packageRoot . '/theme/view/index.html');

$check(str_contains($template, 'extend name="common/layout"'), '首页应复用公共布局');
$check(str_contains($template, '{$asset}/js/app.js'), '首页模板应引用分类后的脚本资源');
$check(str_contains($template, '{$api_base}'), '首页模板应使用接口基址变量');
$check(str_contains($template, '{$music_url}'), '首页模板应使用音乐地址变量');
$check(str_contains($template, '{$version}'), '首页模板应输出版本号');
$check(!str_contains($template, 'cdn'), '首页模板不应使用 CDN 资源');

$iframeTemplate = (string) file_get_contents($packageRoot . '/theme/view/iframe.html');

$check(str_contains($iframeTemplate, 'extend name="common/layout"'), '内嵌示例页应复用公共布局');
$check(str_contains($iframeTemplate, '{$asset}/iframe/css/iframe.css'), '内嵌示例页应引用 iframe 目录样式');
$check(str_contains($iframeTemplate, '{$asset}/iframe/js/iframe.js'), '内嵌示例页应引用 iframe 目录脚本');
$check(str_contains($iframeTemplate, 'id="iframe-preview"'), '内嵌示例页应提供 iframe 预览区');
$check(str_contains($iframeTemplate, '{$pages_json|raw}'), '内嵌示例页应输出原始页面清单 JSON');
$check(!str_contains($iframeTemplate, 'cdn'), '内嵌示例页不应使用 CDN 资源');

$embedTemplate = (string) file_get_contents($packageRoot . '/theme/view/iframe/weather.html');

$check(str_contains($embedTemplate, '{$asset}/iframe/css/weather.css'), '天气内容页应引用 iframe 目录样式');
$check(str_contains($embedTemplate, '{$asset}/iframe/js/weather.js'), '天气内容页应引用 iframe 目录脚本');
$check(str_contains($embedTemplate, 'id="iframe-forecast"'), '天气内容页应包含预报表格');
$check(str_contains($embedTemplate, '{$embed_code}'), '天气内容页应展示内嵌代码示例');
$check(!str_contains($embedTemplate, 'wx-header'), '天气内容页不应包含站点导航');
$check(!str_contains($embedTemplate, 'wx-footer'), '天气内容页不应包含站点页脚');
$check(!str_contains($embedTemplate, 'cdn'), '天气内容页不应使用 CDN 资源');

$readme = (string) file_get_contents($packageRoot . '/README.md');

$check(str_contains($readme, '/store/think-store-weather/index'), 'README 应说明默认页面地址');
$check(str_contains($readme, '/store/think-store-weather/iframe'), 'README 应说明内嵌页面示例地址');
$check(str_contains($readme, '/store/think-store-weather/iframe/weather'), 'README 应说明内嵌天气内容页地址');
$check(str_contains($readme, '/store/think-store-weather/assets/<path>'), 'README 应说明静态资源地址');
$check(str_contains($readme, "app('think-store-weather')"), 'README 应说明容器调用方式');
$check(str_contains($readme, 'extra.store.home'), 'README 应说明商店首页声明');
$check(str_contains($readme, 'composer test'), 'README 应说明 test 脚本');

if (!class_exists(\Youvision\ThinkStoreWeather\Weather::class)) {
    foreach ($failures as $failure) {
        echo 'FAIL: ' . $failure . "\n";
    }

    exit(1);
}

$weather = new \Youvision\ThinkStoreWeather\Weather(
    'https://example.test/weather?stationid=%s',
    3,
    28800,
    'secret-token',
    static fn (): ?string => '{"code":0,"msg":"success"}'
);

$result = $weather->query(\Youvision\ThinkStoreWeather\Support\Station::DEFAULT_CODE);

$check($result['body'] === '{"code":0,"msg":"success"}', '核心服务应原样返回上游 JSON');
$check($result['cache'] === 'miss', '未命中缓存时应标记 miss');
$check($result['fallback'] === false, '白名单站点不应回退');

$fallback = $weather->query('INVALID_CODE');

$check($fallback['fallback'] === true, '非法站点应回退默认城市');
$check($fallback['stationid'] === \Youvision\ThinkStoreWeather\Support\Station::DEFAULT_CODE, '回退后站点应为默认城市');

$refresh = $weather->query(\Youvision\ThinkStoreWeather\Support\Station::DEFAULT_CODE, true, 'secret-token');

$check($refresh['cache'] === 'refresh', '正确令牌应强制刷新');

$denied = false;

try {
    $weather->query(\Youvision\ThinkStoreWeather\Support\Station::DEFAULT_CODE, true, 'wrong-token');
} catch (\Youvision\ThinkStoreWeather\Support\RefreshDenied) {
    $denied = true;
}

$check($denied, '错误令牌应拒绝强制刷新');
$check($weather->provinces() !== [], '核心服务应返回省市树');

if ($failures === []) {
    echo "PASS\n";
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . "\n";
}

exit(1);