# 懒人导航主题开发指南

> 本指南面向希望为懒人导航开发自定义主题的前端开发者。先阅读完整章节内容可参考在线文档 `data/docs/index.php` 第三章。

## 目录

1. [主题目录结构](#主题目录结构)
2. [theme.json](#themejson)
3. [模板文件与注入变量](#模板文件与注入变量)
4. [模板可用调用（方法/函数）](#模板可用调用方法函数)
5. [URL 生成与资源引用](#url-生成与资源引用)
6. [钩子集成（插件兼容关键）](#钩子集成插件兼容关键)
7. [最佳实践](#最佳实践)
8. [主题测试清单](#主题测试清单)
9. [最小可工作示例](#最小可工作示例)

---

## 主题目录结构

```
templates/
  default/              # 默认主题（复制它作为起点最稳妥）
    theme.json          # 主题元数据
    index.php           # 首页
    category.php        # 分类页
    site.php            # 站点详情页
    search.php          # 搜索结果页
    submit.php          # 提交站点页
    wormhole.php        # 虫洞联盟页
    article_list.php    # 文章列表页（article 插件）
    article_detail.php  # 文章详情页（article 插件）
    error.php           # 错误页（收到 $code / $message）
    404.php             # 404 兼容页（参考模板）
    header.php          # 公共头部片段（partial）
    footer.php          # 公共底部片段（partial，含钩子）
    css/                # 样式
    js/                 # 脚本
    screenshot.png      # 主题截图（后台展示，可选）
  mytheme/              # 你的主题
```

- 判定目录是否为可用主题：存在 `index.php`（`Theme::exists()`）
- **模板缺失自动回退**：`Theme::render()` / `Theme::partial()` 在当前主题找不到文件时回退到 `templates/default/` 同名文件，因此可以只覆盖想自定义的页面

## theme.json

每个主题可包含 `theme.json`，供后台「主题管理」展示（缺失时用默认值）：

```json
{
  "name": "mytheme",
  "title": "我的主题",
  "version": "1.0",
  "author": "你的名字",
  "description": "主题简介",
  "preview": ""
}
```

| 字段 | 必填 | 说明 |
|------|------|------|
| `name` | 否 | 主题标识（默认取目录名） |
| `title` | 否 | 显示名称（默认取目录名） |
| `version` | 否 | 版本号（默认 1.0） |
| `author` | 否 | 作者 |
| `description` | 否 | 简介 |
| `preview` | 否 | 预览图 URL；目录中的 `screenshot.png` 会被自动识别为截图 |

## 模板文件与注入变量

`core/Route.php` 收集变量后交给 `Theme::render('模板名', $vars)`，经 `extract()` 注入模板作用域，模板直接使用变量（默认主题习惯用 `?? ` 兜底）。

| 模板文件 | 路由（rewrite 模式示例） | 主要注入变量 |
|----------|--------------------------|--------------|
| `index.php` | `/` | `$categories` `$activeCats` `$featuredSites` `$currentCat` `$currentSites` `$ranking` `$siteStats` `$showWeight` `$perCategory` `$settings` `$seoTitle` `$seoDesc` `$seoKeywords` |
| `category.php` | `/category/{slug}/` | `$category` `$sites` `$slug` `$page` `$sort` `$total` `$totalPages` `$perPage` `$showWeight` `$categories` `$settings` `$seo*` |
| `site.php` | `/site/{id}/` | `$site` `$category` `$related` `$categories` `$settings` `$showWeight` `$ratingStats` `$trendData` `$seo*` |
| `search.php` | `/search/?q=` | `$keyword` `$sites` `$page` `$total` `$totalPages` `$perPage` `$categories` `$settings` `$seo*` |
| `submit.php` | `/submit/` | `$categories` `$siteStats` `$settings` `$enable` `$needReview` `$seo*` |
| `wormhole.php` | `/wormhole/` | `$categories` `$siteStats` `$wormholeStats` `$members` `$settings` `$seo*` |
| `article_list.php` | `/articles/` | `$articles` `$page` `$total` `$totalPages` `$perPage` `$categories` `$settings` `$seo*` |
| `article_detail.php` | `/article/{id}/` | `$article` `$categories` `$settings` `$seo*` |
| `error.php` | 任意错误 | `$code` `$message` `$settings` |

`$settings` 为全站配置（`site_name`、`site_slogan`、`site_footer`、SEO 等）。

## 模板可用调用（方法/函数）

### Theme 类（全部静态方法，`core/Theme.php`）

| 方法 | 说明 |
|------|------|
| `Theme::current()` | 当前主题名（`current_theme`，无效回退 default） |
| `Theme::set($name)` | 切换主题并写入配置 |
| `Theme::scan()` | 扫描 templates/ 下所有主题 |
| `Theme::getInfo($name)` | 主题信息（含 files/screenshot） |
| `Theme::exists($name)` | 主题是否存在 |
| `Theme::render($tpl, $vars)` | 渲染模板（Route 调用，自动回退 default） |
| `Theme::path($tpl)` | 当前主题模板绝对路径 |
| `Theme::partial($name, $vars=[])` | 加载片段 header/footer（继承页面变量、显式参数优先、自动回退） |
| `Theme::e($value)` | HTML 转义（= `Security::e()`） |
| `Theme::eAttr($value)` | 属性值转义（= `Security::eAttr()`） |
| `Theme::url($type, $params=[])` | 生成 URL（自动适配动态/伪静态模式） |
| `Theme::asset($file)` | 主题资源 URL，如 `/templates/default/css/style.css` |

### 常用全局函数（`core/helpers.php`）

`setting($key, $default)`、`renderSiteCards($sites, $showWeight)`、`renderSiteIcon($name, $size)`、`renderPagination($current, $total, $urlTemplate)`、`formatNumber($num)`、`parseTags($tags)`、`getDisplayDomain($url)`、`getMaxBr($site)`、`getWeightBadgeClass($br)`、`getCategoryUrl($slug)`、`normalizeSiteUrl($url)` 等（完整清单见在线文档 2.4 节）。

### 安全类

`Security::e()` / `Security::eAttr()` 与 Theme 同名方法等价；表单/接口场景使用 `Security::csrfField()` 与 `Security::verifyCSRFToken()`。

## URL 生成与资源引用

```php
// URL 生成（自动适配 dynamic / rewrite / index 模式，禁止硬编码）
Theme::url('home');                                        // 首页
Theme::url('category', ['slug' => $cat['slug']]);          // 分类页
Theme::url('site', ['id' => $site['id'], 'slug' => $site['category_slug'] ?? '']); // 详情页
Theme::url('search', ['q' => 'AI']);                       // 搜索页
Theme::url('submit');                                      // 提交页
Theme::url('wormhole');                                    // 虫洞页
Theme::url('article_list');                                // 文章列表
Theme::url('article', ['id' => 1]);                        // 文章详情
Theme::url('category_page', ['slug' => $slug, 'page' => 2, 'sort' => 'br']); // 分类分页

// 分类分页 + 分页组件
$pgTemplate = Theme::url('category_page', ['slug' => $slug, 'page' => '%d', 'sort' => $sort]);
echo renderPagination($page, $totalPages, $pgTemplate);

// 资源引用（基于当前主题）
<link rel="stylesheet" href="<?= Theme::asset('css/style.css') ?>">
<script src="<?= Theme::asset('js/script.js') ?>"></script>
```

rewrite 模式下默认格式：`category/{%slug%}/`、`site/{%id%}/`、`article/{%id%}/`、`category/{%slug%}/page-{%page%}/`（后台 rewrite 插件可自定义）。

## 钩子集成（插件兼容关键）

主题负责在正确位置调用 `Plugin::hook()`，插件注册的回调会在此输出内容。**以下钩子调用点必须保留，否则对应插件功能失效：**

| 钩子 | 文件/位置 | 参数 | 依赖它的插件 |
|------|-----------|------|--------------|
| `before_header` | header.php：`<!DOCTYPE html>` 之前 | 无 | spider（来访检测）等 |
| `after_header` | header.php：`<body>` 之后 | 无 | — |
| `search_bar_after` | index.php：搜索栏之后 | 无 | submit（提交站点按钮） |
| `site_list_before` / `site_list_after` | index.php：站点卡片网格前后 | 无 | ad |
| `sidebar_top` / `sidebar_bottom` | index.php：侧边栏分类列表前后 | 无 | ad、article、wormhole 等（多插件共享，调用一次即可） |
| `before_content` / `after_content` | site.php：详情内容前后 | `[$site]` | ad |
| `before_footer` | footer.php：`<footer>` 前 | 无 | article（自定义 CSS）、friendlink |
| `after_footer` | footer.php：`</body>` 前 | 无 | auto-link（自动收录 JS）、lightbox、auto-alt、friendlink |

调用方式：

```php
<?php Plugin::hook('sidebar_top'); ?>
<?php Plugin::hook('before_content', [$site ?? []]); ?>
```

> **特别注意**：友链自动收录（auto-link）的检测 JS、灯箱、图片 ALT 均由插件自己通过 `after_footer` 钩子注入，主题**不需要**再写任何硬编码代码，但必须保留 `Plugin::hook('after_footer')` 调用点（放在 `</body>` 之前）。业务事件钩子（site_submitted 等）由核心触发，主题不感知。

## 最佳实践

1. **优先复制 default 主题**再改样式，可保证页面齐全且不遗漏钩子与 JS 交互（搜索、分类切换、评分、反馈弹窗等）
2. 所有用户/数据库输出用 `Theme::e()` / `Theme::eAttr()` 转义，防 XSS
3. URL 一律 `Theme::url()`，资源一律 `Theme::asset()`，不要硬编码主题名与链接
4. header/footer 用 `Theme::partial('header')` / `Theme::partial('footer')` 引入（片段内可访问页面变量；`site.php` 直接 `include __DIR__.'/header.php'` 也可）
5. 未注入的变量用 `?? ` 兜底默认值（参照 default 主题 header.php）
6. SEO 变量 `$seoTitle` / `$seoDesc` / `$seoKeywords` 由 Route 注入，header 中记得输出 title/description/keywords

## 主题测试清单

开发完主题后，请逐项验证：

- [ ] 首页/分类页/详情页/搜索页/提交页正常渲染，无 PHP 报错
- [ ] dynamic、rewrite、index 三种模式下 URL 都正确（后台 rewrite 插件切换验证）
- [ ] 移动端布局正常
- [ ] 启用 ad 插件后各广告位出现在对应钩子位置
- [ ] 启用 auto-link 插件后从外站点击友链进入，2 秒后 Network 面板出现 `auto-link` 请求
- [ ] 详情页图片可灯箱放大（lightbox 插件）、评分与反馈可用
- [ ] `theme.json` 与 screenshot.png 齐全，后台「主题管理」可切换

## 最小可工作示例

```php
<?php
// templates/mytheme/theme.json
{
  "name": "mytheme",
  "title": "极简主题",
  "version": "1.0",
  "author": "你",
  "description": "极简主题",
  "preview": ""
}
```

```php
<?php
// templates/mytheme/header.php
if (!isset($seoTitle))    $seoTitle    = $settings['site_name'] ?? '导航站';
if (!isset($seoDesc))     $seoDesc     = '';
if (!isset($seoKeywords)) $seoKeywords = '';
?>
<?php Plugin::hook('before_header'); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= Theme::e($seoTitle) ?></title>
<?php if (!empty($seoDesc)): ?>
<meta name="description" content="<?= Theme::eAttr($seoDesc) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= Theme::asset('css/style.css') ?>">
</head>
<body>
<?php Plugin::hook('after_header'); ?>
```

```php
<?php
// templates/mytheme/index.php
Theme::partial('header');
?>
<div class="container">
  <?php Plugin::hook('site_list_before'); ?>
  <?php foreach ($currentSites as $site): ?>
    <a href="<?= Theme::url('site', ['id' => (int)$site['id'], 'slug' => $site['category_slug'] ?? '']) ?>">
      <?= Theme::e($site['name']) ?> - <?= Theme::e($site['description'] ?? '') ?>
    </a>
  <?php endforeach; ?>
  <?php Plugin::hook('site_list_after'); ?>
</div>
<?php Theme::partial('footer'); ?>
```

```php
<?php
// templates/mytheme/footer.php
Plugin::hook('before_footer');
?>
<footer><?= Theme::e($settings['site_name'] ?? '') ?></footer>
<?php Plugin::hook('after_footer'); ?>
</body>
</html>
```
