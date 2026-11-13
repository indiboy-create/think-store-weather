# youvision/think-store-weather

面向 ThinkPHP 8 的天气模块，可作为 `youvision/think-store-*` 发布包接入 think-store 商店：

- **城市天气页面** —— 省市联动 + 实时天气 + 温度趋势 + 七天预报，响应式布局，Layui 2.13.9 本地化，默认河南郑州
- **天气 JSON 接口** —— 代理远程天气接口，原样返回上游 JSON，默认缓存 8 小时，支持令牌强制刷新
- **城市电台** —— 首屏城市选择卡内置播放器，播放包内 `zhengzhou.mp3`，支持进度拖动

## 环境要求

- PHP >= 8.0
- ThinkPHP 8 主项目
- `topthink/think-view`（随本包自动安装）

## 安装

```bash
composer require youvision/think-store-weather
```

ThinkPHP 8 项目通常在 `composer.json` 的 `post-autoload-dump` 中执行 `service:discover` 与 `vendor:publish`，安装后会自动完成服务注册与配置发布。若主项目未配置该脚本，请手动执行：

```bash
php think service:discover
php think vendor:publish
```

配置声明在 `composer.json` 的 `extra.think.config.weather`，发布后可在主项目 `config/weather.php` 覆盖。

## 路由

| 方法 | 地址 | 说明 |
|---|---|---|
| GET | `/store/think-store-weather/index` | 天气页面，默认展示河南省 · 郑州（`YVItN`） |
| GET | `/store/think-store-weather/api/provinces` | 省市联动数据，树形数组，城市 `code` 即 `stationid` |
| GET | `/store/think-store-weather/api/weather?stationid=YVItN` | 原样返回上游天气 JSON，默认读缓存 |
| GET | `/store/think-store-weather/api/refresh?stationid=YVItN&token=xxx` | 强制回源并覆盖缓存 |
| GET | `/store/assets/<path>` | 包内静态资源，含 Layui、样式与脚本 |
| GET | `/store/think-store-weather/music` | 页面背景音乐，支持 HTTP Range 与进度拖动 |

路由由 `Youvision\ThinkStoreWeather\Service` 在 `boot()` 阶段加载，宿主项目不需要复制路由文件。路由前缀由 `config/weather.php` 的 `route.prefix` 控制，默认 `store`；包内命名空间由 `route.segment` 控制，默认 `think-store-weather`。设为空字符串时地址变为 `/index`、`/api/provinces` 等。

## 商店首页

`composer.json` 的 `extra.store.home` 声明了页面入口：

```json
{
  "extra": {
    "store": {
      "home": "/store/think-store-weather/index"
    }
  }
}
```

think-store 商店会读取该字段作为发布包的「访问」地址。

## 页面与静态资源

- 模板位于包内 `theme/view/dashboard.html`，由 think-view 的 `Think` 驱动渲染，不需要复制到主项目。
- 静态资源由 `AssetController` 读取 `theme/assets` 后输出，路径经过 `realpath` 校验并拒绝 `..`，避免目录穿越。
- 资源响应带 `Cache-Control: public, max-age=86400`，字体、CSS、JS、图片等类型会返回对应的 `Content-Type`。
- 模板内的接口地址、资源地址与音乐地址会跟随 `route.prefix`、`route.segment` 与 `assets` 自动变化。

```
/store/think-store-weather/index
/store/assets/layui/css/layui.css
```

## 查询接口

GET `/store/think-store-weather/api/weather?stationid=YVItN`

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
curl 'https://example.com/store/think-store-weather/api/refresh?stationid=YVItN&token=your-token'
```

`token` 在 `/api/weather` 上同样生效，带正确令牌时也会强制刷新。令牌留空表示禁用强制刷新。

> 令牌通过查询参数传递，会出现在访问日志中；生产环境建议使用足够长的随机值，并在网关侧限制访问频率。

## 配置

配置文件位于 `config/weather.php`，发布后可在主项目中覆盖：

| 配置项 | 默认值 | 说明 |
|---|---|---|
| `route.enabled` | `true` | 是否自动注册页面、查询与资源路由；关闭后由主项目自行注册 |
| `route.prefix` | `store` | 路由前缀，留空表示不添加前缀 |
| `route.segment` | `think-store-weather` | 包内命名空间，留空表示页面直接挂在路由前缀下 |
| `route.middleware` | `[]` | 应用到整组路由的中间件，可填写类名或别名 |
| `api.endpoint` | 远程接口 | 上游地址，`%s` 接收站点编码 |
| `api.referer` | 上游站点 | 请求时携带的 Referer，留空则不发送 |
| `api.timeout` | `8` | 请求超时（秒） |
| `api.cache_ttl` | `28800` | 响应缓存时间（秒） |
| `api.refresh_token` | `''` | 强制刷新令牌，留空表示禁用 |
| `assets` | `assets` | 静态资源路径，相对路径会拼在路由前缀后 |

`Service` 会自动合并包内默认配置与主项目配置，项目侧配置优先。修改后清除 `runtime` 缓存即可生效。

```php
// config/weather.php
return [
    'api' => [
        'endpoint'      => 'https://example.com/rest/weather?stationid=%s',
        'referer'       => 'https://example.com/',
        'timeout'       => 8,
        'cache_ttl'     => 28800,
        'refresh_token' => '',
    ],
    'route' => [
        'enabled'    => true,
        'prefix'     => 'store',
        'segment'    => 'think-store-weather',
        'middleware' => [],
    ],
    'assets' => 'assets',
];
```

生产环境请务必通过 `route.middleware` 配置登录鉴权中间件。

## PHP 调用

服务注册后，可通过容器获取 `Weather`：

```php
use Youvision\ThinkStoreWeather\Weather;

/** @var Weather $weather */
$weather = app('weather');
// 也可使用类名：app(Weather::class)

// 省市联动树
$tree = $weather->provinces();

// 查询天气，返回 body / stationid / fallback / cache
$result = $weather->query('YVItN');

// 强制刷新
$result = $weather->query('YVItN', true, 'your-token');
```

`Weather` 构造函数支持脱离容器使用：

```php
$weather = new Weather(
    'https://example.com/rest/weather?stationid=%s',
    8,
    28800,
    'your-token'
);
```

## 目录结构

```
think-store-weather/
├─ composer.json
├─ config/
│  └─ weather.php                 # 天气配置
├─ route/
│  └─ weather.php                 # 页面、查询与资源路由
├─ src/
│  ├─ Service.php                 # ThinkPHP 服务注册
│  ├─ Weather.php                 # 核心服务：省市数据、缓存与回源
│  ├─ Controller/
│  │  ├─ WeatherController.php    # 页面渲染、查询接口与音乐输出
│  │  └─ AssetController.php      # 包内静态资源输出
│  ├─ Support/                    # Province / Station / WeatherClient / WeatherCache / RefreshToken / AudioRange
│  └─ data/                       # 省市站点树、站点白名单与本地音频
├─ theme/
│  ├─ view/dashboard.html         # 天气页面模板
│  └─ assets/                     # Layui、页面样式与脚本
├─ tests/
├─ phpstan.neon
└─ phpunit.xml
```

## 验证

```bash
composer test        # PHPUnit
composer verify      # 结构与配置校验脚本
composer analyse     # PHPStan level 5
composer check       # 三者一起跑
```

`tests/verify.php` 会检查 `composer.json` 的依赖、服务注册、配置发布与商店首页声明，默认配置、路由定义、控制器与资源解析，并实际调用 `Weather` 服务验证白名单回退、强制刷新令牌与资源目录穿越防护。

> 若接口返回「天气服务暂时不可用：SSL 证书校验失败，请检查 PHP 的 curl.cainfo 配置」，说明 PHP curl 未加载 CA，而不是上游不可用。请在 `php.ini` 中配置：
>
> ```ini
> curl.cainfo = "C:\path\to\ca-bundle.crt"
> openssl.cafile = "C:\path\to\ca-bundle.crt"
> ```
>
> Windows 可直接使用 Git for Windows 自带的证书文件（如 `C:\Program Files\Git\ucrt64\etc\ssl\certs\ca-bundle.crt`，部分版本为 `mingw64`）。不要通过关闭 SSL 校验来绕过。

## 发布

```bash
git tag v0.1.0
git push origin v0.1.0
```

在 Packagist 提交仓库地址并配置 GitHub webhook 后，tag 推上去即自动发布。版本策略：

- 破坏性改动（改类名、改配置键、改方法签名）只进下一个大版本
- 分支 `1.x` 与 tag `v1.x.y` 对应
- 发布前先跑 `composer validate --strict`

## 许可证

MIT，详见 `LICENSE`。

## 相关项目

- [youvision/think-store](https://github.com/indiboy-create/think-store)：ThinkPHP 8 商店管理扩展。
- [ThinkPHP-PanGu](https://github.com/indiboy-create/ThinkPHP-PanGu)：面向 ThinkPHP 的开源工程索引。

## ThinkPHP-PanGu - 盘古ThinkPHP开源索引!
面向 ThinkPHP 的开源工程索引：组件、框架、服务与发布工具，按能力组织，持续整理。
- 仓库地址：**<https://github.com/indiboy-create/ThinkPHP-PanGu/>**
- 在线站点：**<https://indiboy-create.github.io/ThinkPHP-PanGu/>**