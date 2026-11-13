# youvision/think-store-weather

ThinkPHP 8 天气查询组件。提供两样东西：

- **城市天气页面** —— 省市联动 + 实时天气 + 七天预报，Layui 2.13.9 本地化，默认河南郑州
- **天气 JSON 接口** —— 代理远程天气接口，原样返回上游 JSON，默认缓存 8 小时，支持令牌强制刷新

## 安装

```bash
composer require youvision/think-store-weather
```

若工程里已经在用 ThinkPHP 8，服务会被自动发现（`extra.think.services`），路由也会一并注册。如果没生效，手动注册一次：

```bash
php think service:discover
```

静态资源（Layui、样式、脚本）由 `/weather/assets/<path>` 路由直接读取包内 `theme/assets` 目录输出，不需要发布到 `public`，也不需要额外的目录写权限。资源前缀可通过 `config/weather.php` 的 `assets` 修改。

页面使用 `topthink/think-view` 模板引擎渲染，安装本包时会自动引入，无需额外配置。

## 天气页面与接口

| 方法 | 路由 | 说明 |
|---|---|---|
| GET | `/weather` | 天气页面，默认展示河南省 · 郑州（`YVItN`） |
| GET | `/weather/api/provinces` | 省市联动数据，树形数组，城市 `code` 即 `stationid` |
| GET | `/weather/api/weather?stationid=YVItN` | 原样返回上游天气 JSON，默认读缓存 |
| GET | `/weather/api/refresh?stationid=YVItN&token=xxx` | 强制回源并覆盖缓存 |
| GET | `/weather/assets/<path>` | 包内静态资源，含 Layui、样式与脚本 |

页面使用包内 `src/data/province.php` 的省/市树，城市节点中的 `code` 直接作为天气接口的 `stationid`。

天气接口行为：

- `stationid` 在 `src/data/stationid.php` 白名单内：原样转发配置的上游接口响应体，不做字段裁剪
- `stationid` 为空或不在白名单：自动回退到默认城市郑州（`YVItN`），响应头返回 `X-Weather-Fallback: 1`
- 上游不可用：返回 HTTP 502 与 `{"code":-1,"msg":"...","data":null}`

响应头会附带 `X-Weather-Station`、`X-Weather-Fallback` 与 `X-Weather-Cache`，用于确认实际站点与缓存状态。

### 缓存与强制刷新

- 天气响应默认缓存 **8 小时**（`cache_ttl`，单位秒），缓存键为 `weather:station:<stationid>`
- 命中缓存：`X-Weather-Cache: hit`，不会再请求上游
- 未命中：`X-Weather-Cache: miss`，请求上游并写入缓存
- 强制刷新：`X-Weather-Cache: refresh`，忽略缓存、回源并覆盖缓存
- 刷新令牌无效或未配置：HTTP 403，`X-Weather-Cache: bypass`

在 `config/weather.php` 配置令牌后即可使用：

```bash
curl 'https://example.com/weather/api/refresh?stationid=YVItN&token=your-token'
```

`token` 在 `/weather/api/weather` 上同样生效，带正确令牌时也会强制刷新。令牌留空表示禁用强制刷新。

> 令牌通过查询参数传递，会出现在访问日志中；生产环境建议使用足够长的随机值，并在网关侧限制访问频率。

### 配置

```php
// config/weather.php
return [
    // 远程天气接口
    'api' => [
        'endpoint' => 'https://example.com/rest/weather?stationid=%s',
        'timeout'  => 8,
        // 响应缓存时间（秒），默认 8 小时
        'cache_ttl' => 28800,
        // 强制刷新令牌，留空表示禁用刷新接口
        'refresh_token' => '',
    ],

    // 静态资源路由前缀
    'assets' => '/weather/assets',
];
```

路由由服务提供者在 `boot()` 阶段加载，宿主项目不需要复制路由文件。若宿主已有 `/weather` 路径，可自行调整 `src/route/route.php`。

页面使用 `topthink/think-view` 的 `Think` 驱动渲染；宿主未配置 `view.type` 时服务提供者会自动设为 `Think`，已有视图配置不受影响。

静态资源路由只允许读取包内 `theme/assets` 目录，路径会经过 `realpath` 校验并拒绝含 `..` 的请求，避免目录穿越。

## 目录结构

```
think-store-weather/
├── composer.json
├── config/weather.php          # 默认配置
├── theme/                      # 页面模板与前端资源
│   ├── view/dashboard.html     # 天气页面模板（不对外暴露）
│   └── assets/                 # 由 /weather/assets/<path> 路由直接输出
│       ├── layui/              # Layui 2.13.9 本地文件
│       ├── app.js              # 城市联动与天气渲染
│       └── site.css            # 页面样式
├── src/
│   ├── Service.php             # 服务提供者（配置合并、路由）
│   ├── route/route.php         # /weather 路由定义
│   ├── Controller/WeatherController.php
│   ├── Controller/AssetController.php
│   ├── data/province.php       # 省/市站点树
│   ├── data/stationid.php      # 站点白名单
│   └── Support/                # Province / Station / WeatherClient / WeatherCache / RefreshToken
├── tests/
├── phpstan.neon
└── .github/workflows/ci.yml
```

## 开发

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan level 5
composer check       # 两者一起跑
```

CI 矩阵覆盖 PHP 8.0–8.4，并额外跑一次 `--prefer-lowest`，确保 `composer.json` 里的依赖区间没有写窄或写漏。

> 本地若使用自签证书或企业代理导致 PHP curl 报 `self-signed certificate in certificate chain`，需要给 PHP 配置可信 CA（`curl.cainfo` / `openssl.cafile`），而不是关闭 SSL 校验。

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