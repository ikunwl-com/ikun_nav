# 懒人导航主题开发指南

> 本指南面向希望为懒人导航开发自定义主题的前端开发者。

## 目录

1. [主题目录结构](#主题目录结构)
2. [主题配置文件](#主题配置文件)
3. [模板文件清单](#模板文件清单)
4. [必做：友链自动收录集成](#必做友链自动收录集成)
5. [辅助开发工具](#辅助开发工具)
6. [最佳实践](#最佳实践)
7. [主题测试清单](#主题测试清单)

---

## 主题目录结构

```
templates/
  default/              # 默认主题（必看参考）
    index.php           # 首页
    category.php        # 分类页
    site.php            # 站点详情页
    search.php          # 搜索结果页
    submit.php          # 提交站点页
    header.php          # 页面头部片段（可选）
    footer.php          # 页面底部片段（可选，但强烈建议保留自动收录代码）
    theme.json          # 主题元数据
    css/                # 样式文件
    js/                 # 脚本文件
    screenshot.png      # 主题预览图（可选）
```

## 主题配置文件

每个主题根目录下必须有 `theme.json`，示例如下：

```json
{
  "title": "默认主题",
  "version": "1.0",
  "author": "懒人导航官方",
  "description": "简洁现代的导航站默认主题",
  "preview": ""
}
```

## 模板文件清单

| 文件名 | 必填 | 说明 |
|--------|------|------|
| `index.php` | 是 | 首页，展示分类和推荐站点 |
| `category.php` | 是 | 分类详情页，按分类展示站点列表 |
| `site.php` | 是 | 单个站点详情页 |
| `search.php` | 是 | 搜索结果页 |
| `submit.php` | 是 | 提交站点表单页 |
| `header.php` | 可选 | 公共头部片段 |
| `footer.php` | 可选 | 公共底部片段 |

### 渲染机制

系统通过 `Theme::render('模板名', $vars)` 渲染模板。模板中可用变量取决于具体页面传递的数据，常见变量包括：

```php
// index.php 常用变量
$sites          // 推荐站点列表
$categories     // 分类列表
$siteStats      // 统计信息
$settings       // 系统设置
$featured       // 推荐位站点

// 所有模板通用
$settings['site_name']       // 站点名称
$settings['site_slogan']     // 站点口号
$settings['site_footer']     // 自定义底部内容（HTML）
```

### 模板安全函数

```php
// 输出转义（防 XSS）
<?= Theme::e($value) ?>

// 属性转义（用于 HTML 属性）
<?= Theme::eAttr($value) ?>

// 生成 URL（自动适配伪静态/动态模式）
<?= Theme::url('home') ?>           // 首页
<?= Theme::url('category', ['slug' => 'tech']) ?>  // 分类页
<?= Theme::url('site', ['id' => 1, 'slug' => 'tech']) ?> // 站点详情
<?= Theme::url('search') ?>         // 搜索页
<?= Theme::url('submit') ?>        // 提交页

// 加载模板片段
<?php Theme::partial('header', ['title' => '首页']) ?>

// 获取资源路径
<?= Theme::asset('css/style.css') ?>  // /templates/default/css/style.css
```

---

## 必做：友链自动收录集成

**这是最重要的一步！** 如果不在主题中集成自动收录代码，后台开启的"友链自动收录"功能将完全无法工作。

### 为什么必须这样做？

友链自动收录的工作原理是：当用户从挂了导航站友链的外站点击跳转到导航站时，导航站需要检测到"这个用户是从哪个网站来的"。由于浏览器访问 API 时不会保留原始来路（Referer 会被覆盖或为空），所以**必须在 PHP 渲染首页阶段就捕获 Referer**，然后通过 URL 参数传给后端。

### 集成代码

将以下代码放入主题的 **footer.php 底部（`</body></html>` 之前）**：

```php
<?php if (setting('autolink_enable', '0') === '1'): ?>
<!-- 友链自动收录：异步检测 Referer，不影响页面加载 -->
<script>
(function(){
    var referer = '<?php
        $autoLinkReferer = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refDomain = Security::extractDomain($_SERVER['HTTP_REFERER']);
            $selfHost  = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $sePattern = '/(baidu\.com|google\.com|bing\.com|sogou\.com|so\.com|yahoo\.com|yandex\.com|duckduckgo\.com|baiducontent\.com)$/i';
            if (strcasecmp($refDomain, $selfHost) !== 0 && !preg_match($sePattern, $refDomain)) {
                $autoLinkReferer = $_SERVER['HTTP_REFERER'];
            }
        }
        echo rawurlencode($autoLinkReferer);
    ?>';
    // 没有外部来路时不发送请求，减少无效调用
    if (!referer) return;
    // 延迟 2 秒执行，确保页面已完全加载
    setTimeout(function(){
        try {
            var img = new Image();
            img.src = '/api/?endpoint=auto-link&ref=' + referer + '&_t=' + Date.now();
        } catch(e) {}
    }, 2000);
})();
</script>
<?php endif; ?>
```

### 代码说明

| 部分 | 作用 |
|------|------|
| `setting('autolink_enable', '0')` | 检测后台是否开启了友链自动收录功能 |
| `$_SERVER['HTTP_REFERER']` | PHP 获取用户从哪个页面跳转到当前页（这是唯一可靠的时机） |
| `Security::extractDomain()` | 从完整 URL 提取域名部分 |
| `strcasecmp($refDomain, $selfHost)` | 排除本站自己的来路（用户从导航站内页跳转到首页） |
| `preg_match($sePattern, ...)` | 排除搜索引擎来路（避免误收录搜索结果页） |
| `rawurlencode()` | 对 URL 进行编码，避免特殊字符破坏请求 |
| `new Image()` | 通过图片请求发送 pixel tracking，无 CORS 限制 |
| `setTimeout(..., 2000)` | 延迟 2 秒执行，避免影响页面首屏加载 |
| `endpoint=auto-link&ref=xxx` | 调用自动收录 API 并传入原始 Referer |

### 注意事项

1. **位置要求**：必须放在所有模板页面的底部（首页最重要，因为友链跳转通常指向首页）
2. **条件判断**：必须包裹在 `if (setting('autolink_enable', '0') === '1')` 中，避免用户关闭功能后仍发送无效请求
3. **不要修改参数名**：`ref` 参数名是 API 约定的，不能改
4. **不要加 `encodeURIComponent`**：PHP 已经用 `rawurlencode` 编码过了，JS 不需要再编码

---

## 辅助开发工具

### 获取当前重写模式

```php
$urlCfg = Rewrite::getConfig();
// $urlCfg['mode'] => 'dynamic' | 'rewrite' | 'index'
```

### 伪静态 URL 生成

```php
// 不依赖 Theme 类，直接生成 URL
Rewrite::url('home');              // /
Rewrite::url('category', ['slug' => 'tech']);  // /category/tech/
Rewrite::url('site', ['id' => 1, 'slug' => 'tech']); // /site/1-tech/
```

### 安全辅助

```php
// 频率限制检查
Security::checkRateLimit('rate_1h', 10, 3600);  // 1小时内最多10次

// CSRF Token 生成和校验
$token = Security::generateCSRFToken();
Security::verifyCSRFToken($_POST['csrf_token']);

// 防 XSS 清理
$safe = Security::cleanString($raw, 200);
$tags = Security::cleanTags('php, mysql, nginx');

// URL 校验
[$valid, $cleanUrl, $domain] = Security::validateUrl($url);
```

---

## 最佳实践

1. **优先复用 default 主题的结构**，特别是 footer.php 中的自动收录代码
2. **所有用户输出使用 `Theme::e()` 转义**，防止 XSS
3. **使用 `Theme::url()` 生成 URL**，确保伪静态和动态模式都能正确工作
4. **资源路径使用 `Theme::asset()`**，避免硬编码主题名
5. **保持 footer.php 中的自动收录代码完整**，这是功能核心

---

## 主题测试清单

开发完主题后，请逐项验证：

- [ ] 首页正常渲染，分类和站点列表正确
- [ ] 分类页 URL 和分页正常
- [ ] 站点详情页正常
- [ ] 搜索功能正常
- [ ] 提交站点表单正常
- [ ] **友链自动收录：从外站点击友链进入导航站，2 秒后 Network 面板能看到 `auto-link` 请求**
- [ ] 伪静态模式 / 动态模式 / index.php 模式 都能正确生成 URL
- [ ] 移动端样式正常
- [ ] `theme.json` 信息完整

---

## 示例：最小可工作主题

```php
<?php
// templates/minimal/index.php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= Theme::e($settings['site_name'] ?? '导航站') ?></title>
</head>
<body>
    <h1><?= Theme::e($settings['site_name'] ?? '导航站') ?></h1>

    <?php foreach ($categories as $cat): ?>
    <section>
        <h2><?= Theme::e($cat['name']) ?></h2>
        <?php foreach ($cat['sites'] as $site): ?>
        <a href="<?= Theme::url('site', ['id' => $site['id'], 'slug' => $cat['slug']]) ?>">
            <?= Theme::e($site['name']) ?>
        </a>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <!-- 必须包含自动收录代码 -->
    <?php Theme::partial('footer') ?>
</body>
</html>
```

```php
<?php
// templates/minimal/footer.php
// 必须包含这段自动收录代码
?>
<?php if (setting('autolink_enable', '0') === '1'): ?>
<script>
(function(){
    var referer = '<?php
        $autoLinkReferer = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refDomain = Security::extractDomain($_SERVER['HTTP_REFERER']);
            $selfHost  = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $sePattern = '/(baidu\.com|google\.com|bing\.com|sogou\.com|so\.com|yahoo\.com|yandex\.com|duckduckgo\.com|baiducontent\.com)$/i';
            if (strcasecmp($refDomain, $selfHost) !== 0 && !preg_match($sePattern, $refDomain)) {
                $autoLinkReferer = $_SERVER['HTTP_REFERER'];
            }
        }
        echo rawurlencode($autoLinkReferer);
    ?>';
    if (!referer) return;
    setTimeout(function(){
        try {
            var img = new Image();
            img.src = '/api/?endpoint=auto-link&ref=' + referer + '&_t=' + Date.now();
        } catch(e) {}
    }, 2000);
})();
</script>
<?php endif; ?>
</body>
</html>
```
