<?php

declare(strict_types=1);

namespace Youvision\Lizi\Command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use Youvision\Lizi\Support\Port;

/**
 * 启动 PHP 内置开发服务器。
 *
 * 与 php think run 的区别是：默认参数来自 config/lizi.php，
 * 端口自动顺延，并且永远使用包内自带的路由脚本。
 */
class Serve extends Command
{
    protected function configure(): void
    {
        $this->setName('lizi:serve')
            ->addOption('host', 'H', Option::VALUE_REQUIRED, '监听地址', null)
            ->addOption('port', 'p', Option::VALUE_REQUIRED, '监听端口', null)
            ->addOption('router', null, Option::VALUE_REQUIRED, '自定义路由脚本', null)
            ->setDescription('启动内置开发服务器（含伪静态与静态资源处理）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $config = (array) $this->app->config->get('lizi.dev_server', []);

        $host = (string) ($input->getOption('host') ?: ($config['host'] ?? '127.0.0.1'));
        $port = (int) ($input->getOption('port') ?: ($config['port'] ?? 9877));

        try {
            $port = Port::find($host, $port);
        } catch (\Throwable $e) {
            $output->error($e->getMessage());

            return 1;
        }

        $rootPath = $this->app->getRootPath();
        $public   = $rootPath . 'public';

        if (!is_dir($public)) {
            $output->error('入口目录不存在：' . $public);

            return 1;
        }

        $router = $this->resolveRouter((string) $input->getOption('router'), $config, $rootPath);

        if ($router === null) {
            $output->error('路由脚本不存在，请创建 ' . $rootPath . 'server/router.php');

            return 1;
        }

        // 用 PHP_BINARY 而不是 "php"：PATH 里可能指向另一版本的解释器
        $command = sprintf(
            '%s -S %s:%d -t %s %s',
            escapeshellarg(PHP_BINARY),
            $host,
            $port,
            escapeshellarg($public),
            escapeshellarg($router)
        );

        $output->info(sprintf('开发服务器已启动：http://%s:%d', $host, $port));
        $output->comment($command);
        $output->writeln('按 Ctrl+C 停止');

        passthru($command, $code);

        return (int) $code;
    }

    private function resolveRouter(string $option, array $config, string $rootPath): ?string
    {
        $candidates = [];

        if ($option !== '') {
            $candidates[] = $option;
        }

        if (!empty($config['router'])) {
            $candidates[] = (string) $config['router'];
        }

        $candidates[] = $rootPath . 'server/router.php';
        $candidates[] = __DIR__ . '/../../server/router.php';

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);

            if ($real !== false && is_file($real)) {
                return $real;
            }
        }

        return null;
    }
}
