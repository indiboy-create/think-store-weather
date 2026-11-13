<?php

// 复制到项目 config/weather.php 后即可覆盖下列默认值
return [
    // 中国天气网接口
    'nmc' => [
        'endpoint' => 'https://www.nmc.cn/rest/weather?stationid=%s',
        'timeout'  => 8,
        // 响应缓存时间（秒），默认 8 小时
        'cache_ttl' => 28800,
        // 强制刷新令牌，留空表示禁用刷新接口
        'refresh_token' => '',
    ],

    // 静态资源路由前缀（由包内路由直接输出，无需发布到 public）
    'assets' => '/weather/assets',
];