<?php

declare(strict_types=1);

/**
 * php -S 路由脚本：静态资源交给内置服务器，其余请求转发到框架入口。
 *
 * docroot 以 php -S -t 传入的 DOCUMENT_ROOT 为准：本脚本被当作 Composer 包
 * 安装时，__DIR__ 指向 vendor，而不是宿主项目的 public 目录。
 */
$docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');

$publicPath = $docRoot !== ''
    ? realpath($docRoot)
    : realpath(__DIR__ . '/../public');

if ($publicPath === false) {
    http_response_code(500);
    echo 'public 目录不存在';

    return true;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// 路径穿越防护：内置服务器只做基础校验，自己再拦一道
if (str_contains($uri, '..')) {
    http_response_code(400);
    echo 'Bad Request';

    return true;
}

// return false = 交还给内置服务器处理，MIME 与缓存头都不用手写
if ($uri !== '/' && is_file($publicPath . $uri)) {
    return false;
}

// 纠正入口相关变量，避免 ThinkPHP 推导 baseUrl 时算歪
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require $publicPath . '/index.php';
