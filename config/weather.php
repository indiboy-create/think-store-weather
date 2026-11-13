<?php

// 复制到项目 config/weather.php 后即可覆盖下列默认值
return [
    // 远程天气接口
    'api' => [
        'endpoint' => 'https://www.nmc.cn/rest/weather?stationid=%s',
        'timeout'  => 8,
        // 响应缓存时间（秒），默认 8 小时
        'cache_ttl' => 28800,
        // 强制刷新令牌，留空表示禁用刷新接口
        'refresh_token' => '',
    ],

    // 路由配置
    'route' => [
        // 是否启用内置路由；关闭后由主项目自行注册
        'enabled' => true,

        // 路由前缀
        'prefix' => 'weather',

        // 应用到全部路由上的中间件，例如 [\app\middleware\Auth::class]
        // 生产环境请务必配置登录鉴权中间件，避免权限问题
        'middleware' => [],
    ],

    // 静态资源路径：相对路径会拼在路由前缀后，也可填写以 / 开头的绝对路径
    'assets' => 'assets',
];