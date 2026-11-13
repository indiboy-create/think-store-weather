<?php

declare(strict_types=1);

namespace Youvision\Lizi\Middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 请求轨迹中间件：往响应头里写入耗时与峰值内存。
 * 仅作开发期观测，生产环境不要开启。
 */
class Trace
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        if ($response instanceof Response) {
            $response->header([
                'X-Lizi-Cost' => sprintf('%.2fms', (microtime(true) - $start) * 1000),
                'X-Lizi-Mem'  => sprintf('%.2fMB', memory_get_peak_usage(true) / 1048576),
            ]);
        }

        return $response;
    }
}
