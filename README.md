# youvision/lizi

ThinkPHP 8 开发期工具箱。提供三样东西：

- **内置开发服务器** `php think lizi:serve` —— 不依赖 Nginx/Apache，伪静态与静态资源都能正常跑
- **请求轨迹中间件** —— 往响应头写入耗时与峰值内存，前端联调时一眼看出慢在哪
- **端口探测** —— 端口被占时自动顺延，不再出现 "Address already in use"

包名 `lizi` 取"粒子"之意：给每个请求附上一层可观测的轻量粒子。

## 安装

```bash
composer require youvision/lizi --dev
```

生产环境不需要它，所以加 `--dev`。若工程里已经在用 ThinkPHP 8，服务会被自动发现（`extra.think.services`）。

如果没生效，手动注册一次：

```bash
php think service:discover
```

或者编辑 `vendor/services.php` 后清缓存：

```bash
php think clear
```

## 内置开发服务器

```bash
php think lizi:serve
php think lizi:serve -H 0.0.0.0 -p 9877
php think lizi:serve --router server/mock.php
```

| 选项 | 说明 | 默认 |
|---|---|---|
| `-H, --host` | 监听地址 | `127.0.0.1` |
| `-p, --port` | 监听端口 | `9877`，被占用时自动顺延 |
| `--router` | 自定义路由脚本 | 包内 `server/router.php` |

想用手机或局域网内其他机器访问，必须改成 `-H 0.0.0.0`，否则只监听本机回环地址。

它会自动处理三件容易踩坑的事：

1. 用 `PHP_BINARY` 启动，而不是 `php`，避免 PATH 指向另一版本
2. docroot 固定为 `public`，静态资源由内置服务器直出
3. 覆盖 `SCRIPT_NAME` / `SCRIPT_FILENAME`，ThinkPHP 推导 baseUrl 不会算歪

路由脚本自带路径穿越防护（拒绝含 `..` 的 URI）。

## 请求轨迹中间件

复制配置到项目：

```bash
php think vendor:publish --tag=lizi
```

或者手动在 `config/lizi.php` 写入：

```php
return [
    'trace' => [
        'enable' => true,
    ],
];
```

开启后响应头会多出两个字段：

```
X-Lizi-Cost: 12.34ms
X-Lizi-Mem: 6.00MB
```

`config/lizi.php` 里的 `dev_server` 段同样可以覆盖命令的默认参数：

```php
return [
    'dev_server' => [
        'host'   => '127.0.0.1',
        'port'   => 9877,
        'router' => '',   // 留空用包内自带脚本
    ],
];
```

> 中间件与开发命令只在 `app_debug = true` 时生效/注册，生产环境不会暴露。

## 目录结构

```
youvision-lizi/
├── composer.json
├── config/lizi.php          # 默认配置
├── server/router.php        # php -S 路由脚本
├── src/
│   ├── Service.php          # 服务提供者
│   ├── Command/Serve.php    # lizi:serve
│   ├── Middleware/Trace.php # 请求轨迹
│   └── Support/Port.php     # 端口工具（纯逻辑，可单测）
├── tests/
├── phpstan.neon
└── .github/workflows/ci.yml
```

## 本地联调（不发布也能用）

在宿主项目的 `composer.json` 里加 path 仓库：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../youvision-lizi",
            "options": { "symlink": true }
        }
    ],
    "require-dev": {
        "youvision/lizi": "*"
    }
}
```

```bash
composer update youvision/lizi
```

符号链接后改包源码即时生效，不用反复 `dump-autoload`；但新增类文件仍要 `composer dump-autoload -o`。

## 开发

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan level 5
composer check       # 两者一起跑
```

CI 矩阵覆盖 PHP 8.0–8.4，并额外跑一次 `--prefer-lowest`，确保 `composer.json` 里的依赖区间没有写窄或写漏。

## 发布

```bash
git tag v0.1.0
git push origin v0.1.0
```

在 Packagist 提交仓库地址并配置 GitHub webhook 后，tag 推上去即自动发布。版本策略：

- 破坏性改动（改类名、改配置键、改方法签名）只进下一个大版本
- 分支 `1.x` 与 tag `v1.x.y` 对应
- 发布前先跑 `composer validate --strict`

## License

MIT
