<?php

// 复制到项目 config/lizi.php 后即可覆盖下列默认值
return [
    // 请求轨迹中间件：仅在开发环境开启，生产环境务必保持 false
    'trace' => [
        'enable' => false,
    ],

    // 内置开发服务器（php think lizi:serve）默认参数
    'dev_server' => [
        'host'   => '127.0.0.1',
        'port'   => 9877,
        // 留空则使用包内自带的 server/router.php
        'router' => '',
    ],
];
