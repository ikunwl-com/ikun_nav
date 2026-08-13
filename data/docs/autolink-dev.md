# 友链自动收录开发文档

> 本文档面向开发者，说明友链自动收录功能的内部机制、API 接口和二次开发方法。

## 目录

1. [功能概述](#功能概述)
2. [工作原理](#工作原理)
3. [数据流图](#数据流图)
4. [核心代码文件](#核心代码文件)
5. [API 接口](#api-接口)
6. [配置项说明](#配置项说明)
7. [安全机制](#安全机制)
8. [二次开发指南](#二次开发指南)
9. [调试与排查](#调试与排查)

---

## 功能概述

友链自动收录是懒人导航的核心功能之一：

- 当用户从挂了导航站友链的外站点击跳转到导航站时，系统自动检测来路
- 抓取对方首页，验证是否包含指向导航站的回链
- 抓取对方站点的 TDK（标题/描述/关键词）
- 检查 TDK 是否包含违禁词
- 通过后自动将对方站点收录到导航站（状态可为"待审核"或"直接发布"）

## 工作原理

### 为什么需要特殊处理 Referer？

标准 HTTP 请求中，Referer 表示"用户从哪个页面跳转过来"。但友链自动收录遇到两个难点：

1. **pixel tracking 模式**：为了避免 CORS 限制和跨域问题，自动收录使用 `<img src="/api/?endpoint=auto-link">` 的方式发送请求。但浏览器在请求图片时，Referer 会被设置为当前页面（即导航站自身），导致无法知道用户是从哪个外站来的。

2. **go.php 跳转问题**：如果外站使用 go.php 中间页跳转（如 `site.ikunwl.com/go.php?url=xxx`），go.php 设置了 `Referrer-Policy: no-referrer`，会导致下一页（目标站）收不到 Referer。

**解决方案**：在 PHP 渲染前台首页阶段就捕获 `$_SERVER['HTTP_REFERER']`（此时 Referer 还是用户从外站跳转来的原始值），然后通过 URL 参数 `ref=` 传给 API。

### 完整工作流程

```
1. 用户从 bbs.ikunwl.com 点击友链跳转到 site.ikunwl.com
          ↓
2. 浏览器请求 site.ikunwl.com/，携带 Referer: bbs.ikunwl.com
          ↓
3. PHP 渲染首页，footer.php 读取 $_SERVER['HTTP_REFERER'] = 'bbs.ikunwl.com'
          ↓
4. footer.php 过滤：排除本站、搜索引擎
          ↓
5. JS 执行：img.src = '/api/?endpoint=auto-link&ref=bbs.ikunwl.com'
          ↓
6. api_auto_link() 接收 ref 参数
          ↓
7. AutoLinkModel::process('bbs.ikunwl.com') 开始处理
          ↓
8. 抓取 bbs.ikunwl.com 首页 HTML
          ↓
9. 检查首页是否包含 site.ikunwl.com 的链接（回链验证）
          ↓
10. 抓取 TDK（标题/描述/关键词）
          ↓
11. 检查 TDK 是否包含违禁词
          ↓
12. 检查黑名单
          ↓
13. 检查频率限制（同一域名 6 小时最多 3 次）
          ↓
14. 检查域名是否已存在（避免重复收录）
          ↓
15. 插入数据库，状态根据设置决定（待审核 / 直接发布）
          ↓
16. 写入日志
```

## 数据流图

```
┌─────────────────┐     点击友链      ┌──────────────────┐
│  bbs.ikunwl.com │ ───────────────→ │  site.ikunwl.com │
│  (外站/友链源)   │   Referer 携带   │   (导航站首页)    │
└─────────────────┘                  └──────────────────┘
                                              │
                                              │ PHP 渲染
                                              │ $_SERVER['HTTP_REFERER']
                                              ↓
                                    ┌──────────────────┐
                                    │  footer.php      │
                                    │  捕获 Referer    │
                                    │  生成 JS 代码    │
                                    └──────────────────┘
                                              │
                                              │ 2秒后
                                              │ img.src 请求
                                              ↓
                                    ┌──────────────────┐
                                    │  /api/index.php  │
                                    │  api_auto_link() │
                                    └──────────────────┘
                                              │
                                              │ $refererOverride
                                              ↓
                                    ┌──────────────────┐
                                    │  AutoLinkModel   │
                                    │  ::process()     │
                                    └──────────────────┘
                                              │
                    ┌─────────────────────────┼─────────────────────────┐
                    │                         │                         │
                    ↓                         ↓                         ↓
           ┌──────────────┐          ┌──────────────┐          ┌──────────────┐
           │ 抓取首页 HTML │          │ 回链验证      │          │ TDK 抓取     │
           │ cURL 5秒超时 │          │ 检查是否包含 │          │ 解析 title  │
           └──────────────┘          │ site.ikunwl.com         │ meta desc   │
                                    └──────────────┘          │ meta keywords│
                                                              └──────────────┘
                                                                    │
                                                                    ↓
                                                           ┌──────────────┐
                                                           │ 违禁词检查    │
                                                           │ 黑名单检查    │
                                                           │ 频率限制检查  │
                                                           │ 重复域名检查  │
                                                           └──────────────┘
                                                                    │
                                                                    ↓
                                                           ┌──────────────┐
                                                           │  SiteModel   │
                                                           │  ::create()  │
                                                           │  插入数据库   │
                                                           └──────────────┘
                                                                    │
                                                                    ↓
                                                           ┌──────────────┐
                                                           │   Logger     │
                                                           │  写入日志     │
                                                           └──────────────┘
```

## 核心代码文件

| 文件 | 职责 |
|------|------|
| `templates/default/footer.php` | 前台 JS 代码，捕获 Referer 并发送 pixel 请求 |
| `api/index.php` | API 入口，接收请求，调用 AutoLinkModel |
| `core/AutoLinkModel.php` | 核心逻辑：验证、抓取、收录全流程 |
| `core/Logger.php` | 日志写入 |
| `core/Security.php` | 安全工具：域名提取、黑名单检测、频率限制 |

## API 接口

### GET /api/?endpoint=auto-link

**功能**：触发友链自动收录检测

**参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `endpoint` | string | 是 | 固定值 `auto-link` |
| `ref` | string | 否 | 原始 Referer URL（URL 编码） |
| `_t` | int | 否 | 时间戳（用于防止缓存） |

**返回**：

返回 1x1 透明 GIF 图片（像素追踪模式），HTTP 响应体为二进制图片数据。

**响应头**：

```
Content-Type: image/gif
Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache
```

**注意**：此 API 返回的是图片而非 JSON，目的是：

1. 避免 CORS 跨域限制
2. 浏览器对 `<img>` 请求的兼容性最好
3. 无感触发，不影响页面加载

**调用示例**：

```javascript
// 前台 JS 代码
var img = new Image();
img.src = '/api/?endpoint=auto-link&ref=' + encodeURIComponent('https://bbs.ikunwl.com') + '&_t=' + Date.now();
```

## 配置项说明

友链自动收录的配置存储在数据库 `settings` 表中：

| 配置键 | 默认值 | 说明 |
|--------|--------|------|
| `autolink_enable` | `0` | 是否开启友链自动收录（`1`=开启，`0`=关闭） |
| `autolink_review` | `0` | 收录后是否需要审核（`1`=待审核状态，`0`=直接发布） |
| `autolink_cat_id` | `1` | 自动收录的默认分类 ID |
| `autolink_ban_words` | `""` | 违禁词黑名单，每行一个词 |
| `block_all_ip` | `0` | 全局 IP 屏蔽（`1`=拒绝所有纯 IP 地址的自动收录） |

### 配置读取方式

```php
// 在任意 PHP 文件中
$enabled = setting('autolink_enable', '0');        // '1' 或 '0'
$needReview = setting('autolink_review', '0');     // '1' 或 '0'
$catId = (int)setting('autolink_cat_id', '1');     // 分类 ID
$banWords = setting('autolink_ban_words', '');     // 违禁词文本
```

## 安全机制

### 1. Referer 预过滤（前端 JS）

```javascript
// footer.php 中已内置
var referer = '<?php
    $autoLinkReferer = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refDomain = Security::extractDomain($_SERVER['HTTP_REFERER']);
        $selfHost  = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $sePattern = '/(baidu\.com|google\.com|...)$/i';
        if (strcasecmp($refDomain, $selfHost) !== 0 && !preg_match($sePattern, $refDomain)) {
            $autoLinkReferer = $_SERVER['HTTP_REFERER'];
        }
    }
    echo rawurlencode($autoLinkReferer);
?>';
if (!referer) return;  // 无外部来路时不发请求
```

### 2. Referer 验证（后端 PHP）

```php
// AutoLinkModel::process()
$referer = $refererOverride;
if (empty($referer)) {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
}
if (empty($referer)) {
    return ['success' => false, 'action' => 'no_referer'];
}
```

### 3. 搜索引擎排除

```php
private const SEARCH_ENGINE_DOMAINS = [
    'baidu.com', 'google.com', 'bing.com', 'sogou.com', 'so.com',
    'yahoo.com', 'yandex.com', 'duckduckgo.com', 'baiducontent.com',
    // ...
];
```

### 4. 内网地址防护

```php
if (Security::isInternalHost($refererHost)) {
    return ['success' => false, 'action' => 'internal'];
}
```

### 5. 全局 IP 屏蔽

```php
if (setting('block_all_ip', '0') === '1' && filter_var($domain, FILTER_VALIDATE_IP) !== false) {
    return ['success' => false, 'action' => 'ip_blocked'];
}
```

### 6. 黑名单检查

```php
$blacklistModel = new BlacklistModel();
if ($blacklistModel->isBlacklisted($domain)) {
    return ['success' => false, 'action' => 'blacklisted'];
}
```

### 7. 频率限制

```php
private const RATE_LIMIT_MAX = 3;        // 6 小时内最多 3 次
private const RATE_LIMIT_WINDOW = 21600; // 6 小时 = 21600 秒
```

### 8. 回链验证

```php
// 抓取对方首页 HTML，检查是否包含导航站域名
$homeHtml = $this->fetchUrl('https://' . $domain);
if (stripos($homeHtml, $selfDomain) === false) {
    return ['success' => false, 'action' => 'no_backlink'];
}
```

### 9. 违禁词检查

```php
$banWords = explode("\n", setting('autolink_ban_words', ''));
foreach ($banWords as $word) {
    $word = trim($word);
    if ($word && stripos($title . $description . $keywords, $word) !== false) {
        return ['success' => false, 'action' => 'banned_word'];
    }
}
```

### 10. 重复域名检查

```php
$existing = $siteModel->findByDomain($domain);
if ($existing) {
    return ['success' => false, 'action' => 'duplicated'];
}
```

## 二次开发指南

### 自定义回链验证逻辑

如果你想修改"回链验证"的规则（比如不验证、或者验证子页面也可以），可以继承 `AutoLinkModel`：

```php
<?php
// core/CustomAutoLinkModel.php
require_once __DIR__ . '/AutoLinkModel.php';

class CustomAutoLinkModel extends AutoLinkModel
{
    /**
     * 自定义回链验证：允许全站任意页面包含回链（不仅是首页）
     */
    protected function verifyBacklink(string $domain, string $selfDomain): bool
    {
        // 检查首页
        $homeHtml = $this->fetchUrl('https://' . $domain);
        if (stripos($homeHtml, $selfDomain) !== false) {
            return true;
        }
        
        // 检查 /links.html 页面
        $linksHtml = $this->fetchUrl('https://' . $domain . '/links.html');
        if (stripos($linksHtml, $selfDomain) !== false) {
            return true;
        }
        
        return false;
    }
}
```

### 自定义收录后操作

```php
<?php
// core/CustomAutoLinkModel.php
class CustomAutoLinkModel extends AutoLinkModel
{
    /**
     * 收录成功后发送通知
     */
    protected function afterAdd(array $site): void
    {
        parent::afterAdd($site);
        
        // 发送邮件通知管理员
        $this->sendNotificationEmail($site);
        
        // 推送到企业微信
        $this->pushToWechat($site);
    }
    
    private function sendNotificationEmail(array $site): void
    {
        // 实现邮件通知逻辑
    }
    
    private function pushToWechat(array $site): void
    {
        // 实现企业微信推送逻辑
    }
}
```

### 修改前台触发时机

如果你想把触发时机从"首页加载"改为"用户点击分类时"，可以修改模板代码：

```php
<?php
// 在 category.php 或 index.php 中
// 改为点击某个按钮时触发
?>
<script>
document.getElementById('someButton').addEventListener('click', function() {
    var referer = '<?php echo rawurlencode($_SERVER['HTTP_REFERER'] ?? ''); ?>';
    if (referer) {
        var img = new Image();
        img.src = '/api/?endpoint=auto-link&ref=' + referer + '&_t=' + Date.now();
    }
});
</script>
```

## 调试与排查

### 开启调试日志

```sql
-- 确保自动收录日志开启
UPDATE settings SET value = '1' WHERE key = 'log_autolink';
```

### 查看实时日志

```bash
# SSH 登录服务器
tail -f /www/wwwroot/site.ikunwl.com/data/logs/$(date +%Y%m%d)/autolink.log
```

### 浏览器 Network 面板排查

1. 打开浏览器 DevTools（F12）
2. 切换到 Network 标签
3. 勾选 Preserve log（保留日志）
4. 从测试站（bbs.ikunwl.com）点击友链跳转到导航站
5. 在导航站首页等待 2 秒
6. 搜索 `auto-link`，查看请求详情：
   - **Status**: 应为 200
   - **Response**: 应为 1x1 GIF（看起来是空白）
   - **Request URL**: 应包含 `ref=bbs.ikunwl.com`

### 常见问题排查

| 现象 | 可能原因 | 排查方法 |
|------|----------|----------|
| 无 `auto-link` 请求 | 功能未开启 / JS 未执行 | 检查 `autolink_enable` 设置，检查 footer.php 代码 |
| 请求 404 | 伪静态规则未配置 | 检查 `.htaccess` 或 Nginx 规则 |
| 无日志 | 日志开关关闭 / 被跳过 | 检查 `log_autolink`，检查是否被 `skipLog` 过滤 |
| `[no_referer]` | 浏览器未发送 Referer | 检查是否通过 go.php 跳转，或 HTTPS→HTTP 跳转 |
| `[no_backlink]` | 对方未挂回链 | 手动访问对方首页，搜索导航站域名 |
| `[fetch_failed]` | 对方网站无法访问 | curl 测试对方网站连通性 |
| `[banned_word]` | 对方 TDK 含违禁词 | 检查 `autolink_ban_words` 配置 |
| `[rate_limited]` | 频率限制 | 同一域名 6 小时内最多 3 次，等待后再试 |

### 手动测试 API

```bash
# 直接调用 API（带 Referer 参数）
curl -v "https://site.ikunwl.com/api/?endpoint=auto-link&ref=https://bbs.ikunwl.com"

# 查看响应头（应为 image/gif）
# 查看日志文件是否有新记录
```

### 模拟收录流程

如果你想在不依赖真实 Referer 的情况下测试自动收录逻辑，可以写一个测试脚本：

```php
<?php
// test-autolink.php（放在网站根目录，测试后删除）
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/AutoLinkModel.php';

// 模拟从某个外站来的请求
$_SERVER['HTTP_REFERER'] = 'https://test-site.com';

$autoLink = new AutoLinkModel();
$result = $autoLink->process();

echo "结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
```
