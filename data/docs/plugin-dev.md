# 懒人导航插件开发指南

> 本指南面向希望为懒人导航开发自定义插件的开发者。

## 目录

1. [插件系统概述](#插件系统概述)
2. [插件目录结构](#插件目录结构)
3. [plugin.json 元数据](#pluginjson-元数据)
4. [插件文件规范](#插件文件规范)
5. [钩子系统](#钩子系统)
6. [插件配置管理](#插件配置管理)
7. [可用钩子位置](#可用钩子位置)
8. [内置插件一览](#内置插件一览)
9. [开发示例](#开发示例)
10. [最佳实践](#最佳实践)
11. [调试技巧](#调试技巧)

---

## 插件系统概述

懒人导航插件系统是一个轻量级的内置插件框架：

- **仅内置插件**：不支持第三方安装，所有插件放在 `plugins/` 目录下
- **无安全沙箱**：插件与主程序共享同一运行环境，可直接调用所有核心类
- **无依赖管理**：插件自行处理外部依赖（如需引入 Composer 包请自行加载）
- **配置复用**：插件配置存储在 `settings` 数据库表中，与系统设置共用存储层
- **PHP 7.4+**：插件代码需兼容 PHP 7.4+

### 核心类

插件系统由 `core/Plugin.php` 管理，提供以下能力：

| 方法 | 说明 |
|------|------|
| `Plugin::init()` | 初始化，扫描并**仅加载已启用**的插件（由 bootstrap 自动调用） |
| `Plugin::scan()` | 扫描 `plugins/` 目录，返回所有可用插件列表 |
| `Plugin::getInfo($name)` | 获取单个插件的元数据 |
| `Plugin::isEnabled($name)` | 检查插件是否已启用 |
| `Plugin::setEnabled($name, $enabled)` | 启用/停用插件（保留数据） |
| `Plugin::uninstall($name)` | 卸载插件（停用+删表+清配置，返回操作结果数组） |
| `Plugin::registerHook($hook, $callback, $priority)` | 注册钩子回调 |
| `Plugin::hook($hook, $args)` | 执行动作钩子（输出模式） |
| `Plugin::filter($hook, $value, $args)` | 执行过滤钩子（返回模式） |
| `Plugin::config($plugin, $key, $default)` | 获取插件配置值 |
| `Plugin::setConfig($plugin, $key, $value)` | 设置插件配置值 |
| `Plugin::asset($plugin, $file)` | 获取插件资源 URL |

### 加载机制

`Plugin::init()` 只加载**已启用**的插件，未启用插件完全不加载、不注册任何钩子：

1. 扫描 `plugins/` 目录下所有子目录
2. 对每个插件检查 `plugin_{name}_enabled` 配置是否为 `1`
3. 仅对已启用插件加载 `include.php`（函数+前台钩子）和 `main.php`（设置面板）
4. 未启用插件的文件不会被 include，不会注册任何钩子

---

## 插件目录结构

```
plugins/
  {plugin_name}/
    plugin.json           # 元数据（必需）
    include.php           # 主文件：函数定义 + 前台钩子注册（必需）
    main.php              # 设置面板：后台设置 Tab 注入（可选，无设置项则不放）
```

**命名规范**：
- 插件目录名使用小写字母 + 连字符（如 `auto-link`）
- 主文件统一命名为 `include.php`
- 设置面板统一命名为 `main.php`
- 函数名建议以 `plugin_{name}_` 为前缀，避免冲突

---

## plugin.json 元数据

每个插件根目录下必须有 `plugin.json`：

```json
{
    "name": "my-plugin",
    "title": "我的插件",
    "version": "1.0",
    "author": "作者名",
    "description": "插件功能描述",
    "main_file": "include.php",
    "config_file": "main.php",
    "hooks": ["sidebar_top", "after_footer"],
    "tables": ["my_plugin_data"],
    "builtin": true
}
```

| 字段 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `name` | string | 是 | 插件标识名（与目录名一致） |
| `title` | string | 是 | 显示名称 |
| `version` | string | 否 | 版本号，默认 `1.0` |
| `author` | string | 否 | 作者 |
| `description` | string | 否 | 功能描述 |
| `main_file` | string | 否 | 主文件名，默认 `include.php` |
| `config_file` | string | 否 | 设置面板文件名，无设置项时留空 |
| `config_tab` | string | 否 | 设置 Tab 的 ID，默认等于 `name`。当 Tab ID 与插件名不同时需指定（如 `auto-link` 插件的 Tab ID 为 `autolink`） |
| `hooks` | array | 否 | 声明使用的钩子列表（仅用于文档展示） |
| `tables` | array | 否 | 插件自建数据表名列表（不含表前缀），卸载时自动删除 |
| `builtin` | bool | 否 | 是否为内置插件，默认 `true` |

### 停用 vs 卸载

插件在后台管理页有两个关闭方式，均为**行级独立操作**（每个插件右侧有独立按钮）：

| 操作 | 按钮 | 效果 | 数据 |
|------|------|------|------|
| **启动** | 绿色「启动」按钮 | 插件开始加载，钩子生效 | — |
| **停用** | 黄色「停用」按钮 | 插件不再加载，钩子不执行 | **保留**数据表和配置 |
| **卸载** | 红色「卸载」按钮（二次确认） | 停用 + 删除 `tables` 声明的数据表 + 清除 `plugin_{name}_*` 所有配置 | **永久删除，不可恢复** |

卸载时插件文件不会被删除，可以随时重新启动。`tables` 字段中声明的表名不含数据库表前缀，例如声明 `"tables": ["articles"]`，系统会自动拼接为 `{prefix}articles`。

---

## 插件文件规范

每个插件必须包含 `include.php`，可选包含 `main.php`：

### include.php（必需）

放置**函数定义**和**前台钩子注册**。此文件仅在插件启用时加载。

```php
<?php
if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

// 注册前台钩子
Plugin::registerHook('sidebar_top', 'plugin_myplugin_sidebar', 10);

function plugin_myplugin_sidebar(): void {
    $text = Plugin::config('my-plugin', 'text', '默认文字');
    echo '<div class="notice">' . Security::e($text) . '</div>';
}
```

### main.php（可选）

放置**后台设置面板**。仅在插件启用且 `plugin.json` 中声明了 `config_file` 时加载。

如果插件有设置项，在 `main.php` 中注册 `admin_settings_nav` 和 `admin_settings_tabs` 钩子注入设置 Tab：

```php
<?php
if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

// 注入 Tab 导航
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    echo '<a href="#tab-myplugin" class="settings-tab ' . $cls . '" onclick="switchTab(\'myplugin\', this)">我的插件</a>';
});

// 注入 Tab 内容面板
Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    ?>
<div id="tab-myplugin" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">我的插件设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="myplugin">
      <input type="hidden" name="tab" value="myplugin">
      <!-- 表单字段 -->
      <button type="submit" class="btn btn-primary">保存</button>
    </form>
  </div>
</div>
<?php
});
```

> **重要**：后台钩子（`admin_sidebar`、`admin_settings_nav`、`admin_settings_tabs`）必须放在 `include.php` 或 `main.php` 中。由于这两个文件仅在插件启用时加载，未启用插件不会注册后台钩子，侧边栏和设置 Tab 不会显示。

### 无设置项的插件

如果插件不需要设置面板，只创建 `include.php`，不要创建 `main.php`，`plugin.json` 中不设置 `config_file` 字段。后台插件管理页面不会显示「设置」按钮。

---

## 钩子系统

插件通过注册钩子回调来注入功能。钩子分两种模式：

### 动作钩子（Action）

输出模式，在模板特定位置执行：

```php
// 注册
Plugin::registerHook('after_header', 'my_plugin_header_code', 10);

// 回调函数
function my_plugin_header_code() {
    echo '<div class="banner">广告内容</div>';
}
```

模板中触发：
```php
Plugin::hook('after_header');
```

### 过滤钩子（Filter）

返回模式，对值进行链式过滤：

```php
// 注册
Plugin::registerHook('filter_title', 'my_plugin_modify_title', 10);

// 回调函数
function my_plugin_modify_title($title) {
    return $title . ' - 附加后缀';
}
```

模板中触发：
```php
$title = Plugin::filter('filter_title', $title);
```

### 优先级

第三个参数为优先级（数字越小越先执行），默认 `10`：

```php
Plugin::registerHook('after_header', 'first_callback', 5);   // 先执行
Plugin::registerHook('after_header', 'second_callback', 10); // 后执行
```

---

## 插件配置管理

插件配置存储在 `settings` 表，key 格式为 `plugin_{插件名}_{配置键}`。

### 读取配置

```php
// 方式一：通过 Plugin 类
$value = Plugin::config('my-plugin', 'setting_key', 'default_value');

// 方式二：直接通过 setting() 全局函数
$value = setting('plugin_my-plugin_setting_key', 'default_value');
```

### 写入配置

```php
// 方式一：通过 Plugin 类
Plugin::setConfig('my-plugin', 'setting_key', 'value');

// 方式二：通过 SettingsModel
$settings = new SettingsModel();
$settings->set('plugin_my-plugin_setting_key', 'value');
```

### 启用状态

插件的启用状态存储为 `plugin_{name}_enabled`，值为 `1`（启用）或 `0`（停用）：

```php
// 检查是否启用
if (Plugin::isEnabled('my-plugin')) {
    // ...
}
```

---

## 可用钩子位置

以下是模板中已预埋的钩子位置，插件可注册使用：

### 全局页面钩子

| 钩子名 | 触发位置 | 文件 |
|--------|----------|------|
| `before_header` | `<!DOCTYPE html>` 之前 | `header.php` |
| `after_header` | `<body>` 标签之后 | `header.php` |
| `before_footer` | `<footer>` 标签之前 | `footer.php` |
| `after_footer` | `</body>` 之前 | `footer.php` |

### 首页钩子

| 钩子名 | 触发位置 | 文件 |
|--------|----------|------|
| `sidebar_top` | 侧边栏分类列表之前 | `index.php` |
| `sidebar_bottom` | 侧边栏分类列表之后 | `index.php` |
| `site_list_before` | 站点卡片网格之前 | `index.php` |
| `site_list_after` | 站点卡片网格之后 | `index.php` |
| `search_bar_after` | 搜索栏热词标签之后 | `index.php` |

### 站点详情页钩子

| 钩子名 | 触发位置 | 参数 | 文件 |
|--------|----------|------|------|
| `before_content` | 站点详情内容之前 | `$site` (站点数组) | `site.php` |
| `after_content` | 站点详情内容之后 | `$site` (站点数组) | `site.php` |

### 后台管理钩子

后台管理页面也预埋了钩子位置，插件可向后台注入导航项和设置面板：

| 钩子名 | 触发位置 | 参数 | 文件 |
|--------|----------|------|------|
| `admin_sidebar` | 后台侧边栏导航末尾（"插件管理"之后） | 无（通过 `$GLOBALS['currentPage']` 判断高亮） | `admin/bootstrap.php` |
| `admin_settings_nav` | 基础设置页 Tab 导航末尾 | `$activeTab` (当前激活的 Tab ID) | `admin/settings.php` |
| `admin_settings_tabs` | 基础设置页 Tab 面板区域 | `$activeTab` (当前激活的 Tab ID) | `admin/settings.php` |

**后台导航结构说明**：

侧边栏固定显示：仪表盘、站点管理、分类管理、推荐管理、提交审核、数据统计、基础设置、主题管理、插件管理。插件可通过 `admin_sidebar` 钩子追加自定义导航项（如文章管理、虫洞联盟）。**仅启用插件**的侧边栏项会显示。

基础设置页固定 Tab：基础信息、修改密码。伪静态设置、网站地图、广告管理、提交收录、虫洞联盟、友链收录等均通过插件注入。插件可通过 `admin_settings_nav` + `admin_settings_tabs` 钩子对注入自定义 Tab。**仅启用插件**的 Tab 会显示。

**注入侧边栏导航项示例**：

```php
// 在 include.php 或 main.php 中注册
Plugin::registerHook('admin_sidebar', function () {
    $cls = ($GLOBALS['currentPage'] ?? '') === 'myplugin' ? 'active' : '';
    echo '<a href="/admin/my-plugin.php" class="nav-item ' . $cls . '">'
       . '<i class="ti ti-tool"></i><span>我的插件</span></a>';
});
```

**注入基础设置 Tab 示例**：

```php
// 注入 Tab 导航链接
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    echo '<a href="#tab-myplugin" class="settings-tab ' . $cls . '"'
       . ' onclick="switchTab(\'myplugin\', this)">我的插件设置</a>';
});

// 注入 Tab 内容面板
Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    ?>
<div id="tab-myplugin" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">我的插件设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="myplugin">
      <input type="hidden" name="tab" value="myplugin">
      <!-- 表单字段 -->
      <button type="submit" class="btn btn-primary">保存</button>
    </form>
  </div>
</div>
<?php
});
```

> 注意：Tab 内容面板的表单 `action` 指向 `/admin/settings.php`，POST 处理需在 `admin/settings.php` 的 `switch ($section)` 中添加对应的 `case` 分支。

### 使用示例

在模板中触发钩子时，可传递参数：

```php
// 无参数
Plugin::hook('after_header');

// 带参数
Plugin::hook('before_content', [$site]);
```

在回调函数中接收参数：

```php
Plugin::registerHook('before_content', function($site) {
    echo '<div>站点ID: ' . (int)$site['id'] . '</div>';
});
```

---

## 内置插件一览

懒人导航内置 9 个默认插件：

| 插件 | 目录 | 说明 | 后台入口 | 默认状态 |
|------|------|------|----------|----------|
| 网站地图 | `sitemap/` | 生成 XML Sitemap 和 robots.txt | 基础设置 → 网站地图 Tab | 启用 |
| 广告管理 | `ad/` | 后台可配置广告位 HTML，支持多位置投放 | 基础设置 → 广告管理 Tab | 启用 |
| 虫洞联盟 | `wormhole/` | 联盟站点互访统计、虫洞入口显示 | 侧边栏 → 虫洞联盟 | 启用 |
| 伪静态设置 | `rewrite/` | URL 伪静态模式配置 | 基础设置 → 伪静态设置 Tab | 启用 |
| 提交网站收录 | `submit/` | 前台提交入口按钮、审核流程控制 | 基础设置 → 提交收录 Tab | 启用 |
| 文章发布 | `article/` | 前台文章列表/详情页，后台文章管理 | 侧边栏 → 文章管理 | 停用 |
| 灯箱 | `lightbox/` | 站点详情页图片点击放大 | — | 停用 |
| 图片自动ALT | `auto-alt/` | 自动给无 alt 的 img 补填描述 | — | 停用 |
| 友链自动收录 | `auto-link/` | 检测 Referer 来源自动添加友链 | 基础设置 → 友链收录 Tab | 停用 |

> 插件启用后，其后台入口（侧边栏导航项或基础设置 Tab）才会显示。停用后入口自动消失。插件管理列表中已启用插件排在前面，已停用插件排在后面。

---

## 开发示例

### 示例 1：最小插件（无设置项）

创建 `plugins/hello/plugin.json`：

```json
{
    "name": "hello",
    "title": "Hello World",
    "version": "1.0",
    "description": "在页脚输出 Hello World",
    "main_file": "include.php",
    "hooks": ["after_footer"],
    "builtin": true
}
```

创建 `plugins/hello/include.php`：

```php
<?php
if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

Plugin::registerHook('after_footer', function () {
    echo '<!-- Hello World Plugin -->';
});
```

### 示例 2：带配置的插件

`plugins/notice/plugin.json`：

```json
{
    "name": "notice",
    "title": "公告横幅",
    "version": "1.0",
    "description": "侧边栏显示公告",
    "main_file": "include.php",
    "config_file": "main.php",
    "hooks": ["sidebar_top"],
    "builtin": true
}
```

`plugins/notice/include.php`：

```php
<?php
if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

Plugin::registerHook('sidebar_top', 'plugin_notice_sidebar', 10);

function plugin_notice_sidebar() {
    $text = Plugin::config('notice', 'text', '欢迎光临！');
    if (empty($text)) {
        return;
    }
    echo '<div class="notice-banner" style="padding:8px 12px;background:#f0f7ff;border-radius:6px;margin-bottom:8px;">';
    echo Security::e($text);
    echo '</div>';
}
```

`plugins/notice/main.php`：

```php
<?php
if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'notice' ? 'active' : '';
    echo '<a href="#tab-notice" class="settings-tab ' . $cls . '" onclick="switchTab(\'notice\', this)">公告横幅</a>';
});

Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $text = Plugin::config('notice', 'text', '');
    $cls = $activeTab === 'notice' ? 'active' : '';
    ?>
<div id="tab-notice" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">公告横幅设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="notice">
      <input type="hidden" name="tab" value="notice">
      <div class="form-group">
        <label>公告内容</label>
        <input type="text" class="form-input" name="plugin_notice_text" value="<?= Security::eAttr($text) ?>">
      </div>
      <button type="submit" class="btn btn-primary">保存</button>
    </form>
  </div>
</div>
<?php
});
```

### 示例 3：调用核心类

```php
<?php
if (!defined('APP_VERSION') && !class_exists('Database')) {
    die('Direct access denied');
}

Plugin::registerHook('site_list_before', 'plugin_stats_banner', 10);

function plugin_stats_banner() {
    $siteModel = new SiteModel();
    $stats = $siteModel->getStats();
    
    echo '<div class="stats-banner">';
    echo '已收录 ' . (int)$stats['published'] . ' 个站点';
    echo '</div>';
}
```

### 示例 4：带后台管理页面的插件

插件可以提供独立的后台管理页面，放在 `admin/` 目录下。通过 `admin_sidebar` 钩子注入侧边栏导航项，无需修改 `admin/bootstrap.php`：

```
plugins/
  my-plugin/
    plugin.json
    include.php          # 插件主文件（注册钩子）
admin/
  my-plugin.php         # 后台管理页面
```

在插件 `include.php` 中注册侧边栏钩子：

```php
Plugin::registerHook('admin_sidebar', function () {
    $cls = ($GLOBALS['currentPage'] ?? '') === 'myplugin' ? 'active' : '';
    echo '<a href="/admin/my-plugin.php" class="nav-item ' . $cls . '">'
       . '<i class="ti ti-tool"></i><span>我的插件</span></a>';
});
```

后台页面模板参考 `admin/article.php`，需引入 `admin/bootstrap.php` 并使用 `adminHeader()` / `adminFooter()`。

> **插件管理页面的「设置/管理」按钮**：
> - 有独立管理页面（`admin/{name}.php`）的插件显示「管理」按钮，链接到该页面
> - 没有独立页面但有 `config_file` 的插件显示「设置」按钮，链接到 `settings.php?tab={config_tab}`
> - 两者都没有的插件不显示按钮
> - 独立管理页面映射在 `admin/plugins.php` 中的 `$adminPages` 数组中维护

---

## 最佳实践

### 1. 安全防护

- **输出转义**：所有用户输入和数据库数据输出前使用 `Security::e()` 或 `Theme::e()` 转义
- **HTML 过滤**：允许 HTML 的内容使用 `Security::cleanHtml()` 过滤
- **CSRF 防护**：表单提交必须包含 CSRF Token
- **SQL 注入**：使用 `Database::query()` / `Database::execute()` 的预处理语句

```php
// 正确
echo Security::e($userInput);
$html = Security::cleanHtml($_POST['content']);

// 错误（XSS 风险）
echo $userInput;
```

### 2. 函数命名

使用 `plugin_{插件名}_` 前缀避免冲突：

```php
// 推荐
function plugin_myplugin_do_something() { }

// 不推荐（可能冲突）
function do_something() { }
```

### 3. 文件分工

- `include.php`：函数定义 + 前台钩子注册 + 后台侧边栏导航注册
- `main.php`：后台设置 Tab 注册（`admin_settings_nav` + `admin_settings_tabs`）
- 无设置项的插件只创建 `include.php`，不创建 `main.php`

### 4. 性能考虑

- 避免在钩子回调中执行耗时数据库查询
- 需要复杂计算时使用文件缓存
- JS 注入尽量使用异步加载

### 5. 日志记录

使用 `Logger` 类记录关键操作：

```php
Logger::log('my_plugin', '执行了某操作，结果=' . $result);
```

日志文件位于 `data/logs/` 目录下。

---

## 调试技巧

### 查看插件加载状态

在后台「插件管理」页面（侧边栏 → 插件管理）可查看所有插件的启用状态和注册的钩子。已启用插件排在列表前面。

### 手动检查插件是否加载

```php
if (function_exists('Logger::log')) {
    Logger::log('my_plugin', '插件已加载');
}
```

### 查看日志

日志文件位于 `data/logs/` 目录，按日期命名：

```
data/logs/
  2026-01-15.php
  2026-01-16.php
```

### 开启调试模式

在后台「基础设置 → 基础信息」中开启「调试模式」，前台会显示详细错误信息。

### 检查钩子是否注册

```php
if (Plugin::hasHook('after_header')) {
    echo 'after_header 钩子已有注册回调';
}
```

---

## 附录：核心类速查

| 类名 | 说明 | 常用方法 |
|------|------|----------|
| `Plugin` | 插件系统核心 | `registerHook()`, `hook()`, `filter()`, `config()` |
| `Database` | 数据库操作 | `query()`, `queryOne()`, `execute()`, `insert()`, `table()` |
| `Security` | 安全工具 | `e()`, `eAttr()`, `cleanString()`, `cleanHtml()`, `verifyCSRFToken()` |
| `SettingsModel` | 设置管理 | `get()`, `set()`, `setMany()`, `loadAll()` |
| `SiteModel` | 站点模型 | `getSite()`, `getStats()`, `searchPaged()` |
| `CategoryModel` | 分类模型 | `getSidebarCategories()`, `getBySlug()` |
| `Theme` | 主题系统 | `render()`, `partial()`, `e()`, `eAttr()`, `url()` |
| `Rewrite` | 伪静态系统 | `url()`, `getConfig()` |
| `SitemapModel` | 网站地图 | `generate()`, `getStatus()`, `getSitemapUrl()` |
| `Logger` | 日志记录 | `log($category, $message)` |

### 全局函数

| 函数 | 说明 |
|------|------|
| `setting($key, $default)` | 读取设置值 |
| `formatDate($datetime)` | 格式化日期 |
| `renderPagination($page, $total, $pattern)` | 渲染分页 |
| `parseTags($json)` | 解析标签 JSON |
| `renderSiteIcon($name, $size)` | 渲染站点图标 |
| `getDisplayDomain($url)` | 提取显示用域名 |
