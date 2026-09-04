# 懒人导航插件开发指南

> 本指南面向希望为懒人导航开发自定义插件的开发者。配套的完整版内容见在线文档 `data/docs/index.php` 第四章与 3.5 节钩子清单。

## 目录

1. [插件系统概述](#插件系统概述)
2. [插件目录结构与文件规范](#插件目录结构与文件规范)
3. [plugin.json 元数据](#pluginjson-元数据)
4. [启动 / 停用 / 卸载](#启动--停用--卸载)
5. [schema.php 数据库声明](#schemaphp-数据库声明)
6. [钩子系统](#钩子系统)
7. [可用钩子位置](#可用钩子位置)
8. [后台设置 Tab（main.php）](#后台设置-tabmainphp)
9. [独立后台管理页（admin.php）](#独立后台管理页adminphp)
10. [Plugin 类 API 速查](#plugin-类-api-速查)
11. [内置插件一览](#内置插件一览)
12. [开发示例](#开发示例)
13. [最佳实践与调试](#最佳实践与调试)

---

## 插件系统概述

- 插件放在 `plugins/{插件名}/` 目录（小写字母/数字/连字符），目录名即插件名
- 插件与主程序共享运行环境，可直接调用全部核心类（Database/Security/Theme/Rewrite/SettingsModel/各 Model 等）
- 插件配置与系统设置共用 `settings` 表，键名规范 `plugin_{插件名}_{配置键}`
- 代码需兼容 PHP 7.4+
- **加载机制**（`core/Plugin.php` + `core/bootstrap.php`）：
  1. `Plugin::scan()` 扫描 plugins/ 下含 `plugin.json` 的目录
  2. `Plugin::isEnabled($name)` 检查 `plugin_{name}_enabled` 是否为 `1`
  3. `Plugin::init()` 仅加载**已启用**插件的 `include.php`（`main_file`）与 `main.php`（`config_file`）
  4. 未启用插件完全不加载、不注册任何钩子；所有 PHP 文件被直接 URL 访问时也因安全检查而拒绝

## 插件目录结构与文件规范

```
plugins/myplugin/
  plugin.json     # 元数据（必需）
  include.php     # 主文件：类/函数定义 + 钩子注册（main_file，可选但推荐）
  main.php        # 后台设置面板：注册设置 Tab 钩子（config_file，可选）
  schema.php      # 数据库声明：表/字段/默认配置（固定文件名，可选）
  admin.php       # 独立后台管理页（可选；存在该文件时后台自动显示「管理」按钮）
  api.php         # 开放 API 接口声明（可选；启用后自动注册 open/* 接口并出现在后台 API 文档，见下文）
  css/ js/        # 资源（可选，Plugin::asset() 引用）
```

所有 PHP 文件开头统一安全检查，阻止直接访问：

```php
<?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}
```

- `include.php` / `main.php` 仅插件启用时由 `Plugin::init()` 加载
- `schema.php` 仅启动（ensureSchema）与卸载（uninstall）时加载
- `api.php` 由 `core/OpenApi.php` 按需加载：插件启用后，其中声明的接口自动注册到 `/api/open/*`（需 API Key），并自动出现在后台「API 密钥」使用说明与开放 API 对接文档中；插件停用后接口自动失效
- `admin.php` 由 `/admin/plugin.php?p=插件名` 分发加载；分发器校验插件已启用并输出后台布局，**引导阶段已加载 include.php**，其中定义的类/函数可直接使用，无需手动 include

## plugin.json 元数据

```json
{
    "name": "myplugin",
    "title": "我的插件",
    "version": "1.0",
    "author": "作者名",
    "description": "插件功能描述",
    "main_file": "include.php",
    "config_file": "main.php",
    "config_tab": "myplugin",
    "schema_file": "schema.php",
    "hooks": ["sidebar_bottom", "after_footer"],
    "tables": ["my_table"],
    "builtin": true
}
```

| 字段 | 必填 | 说明 |
|------|------|------|
| `name` | 是 | 插件目录名（与文件夹一致） |
| `title` | 是 | 显示名称 |
| `version` | 否 | 版本号，默认 `1.0` |
| `author` | 否 | 作者 |
| `description` | 否 | 功能描述（后台插件列表展示） |
| `main_file` | 否 | 主文件名，默认 `{name}.php`，内置插件统一 `include.php` |
| `config_file` | 否 | 后台设置面板文件名，无设置项则不填 |
| `config_tab` | 否 | 设置 Tab 的 ID，默认等于 `name`；与插件名不同时指定（后台「设置」按钮据此跳转） |
| `schema_file` | 否 | 信息性字段，系统固定读取 `schema.php` |
| `hooks` | 否 | 声明使用的钩子（后台展示用；实际注册靠代码中 `Plugin::registerHook()`） |
| `tables` | 否 | 声明自建表名列表（卸载共享表判断的补充来源） |
| `builtin` | 否 | 是否内置插件（默认 true，后台显示「内置」标签） |

> 启用状态不写在 plugin.json，由系统写入 settings 表 `plugin_{name}_enabled`。

## 启动 / 停用 / 卸载

后台「插件管理」每行提供三个按钮（行级独立操作）：

| 操作 | 效果 | 数据 |
|------|------|------|
| **启动** | `Plugin::setEnabled($name, true)`：写启用状态 + 自动 `ensureSchema()` 安装表/字段/默认配置；之后 include.php/main.php 才会被加载 | 自动安装 |
| **停用** | `setEnabled($name, false)`：仅改状态 | **保留**数据表与配置 |
| **卸载** | `Plugin::uninstall($name)`：停用 + 删自建表（共享表智能跳过）+ 删添加的字段 + 清 `plugin_{name}_*` 配置 | **永久删除，不可恢复** |

插件文件不会被删除，卸载后可随时重新启动（会重新 ensureSchema）。`tables` 声明/`schema.php` 中的表名不含前缀，系统自动拼接 `{prefix}`。

## schema.php 数据库声明

`schema.php` 返回数组，三个部分，全部**幂等**（已存在则跳过，重复启动不出错、不覆盖用户配置）：

```php
<?php
return [
    // 1. 独立表：启动时自动 CREATE TABLE IF NOT EXISTS（{prefix} 在执行时替换为表前缀）
    'tables' => [
        'my_table' => "CREATE TABLE IF NOT EXISTS `{prefix}my_table` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_title (title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ],

    // 2. 向已有表添加字段（跳过已存在）
    'columns' => [
        'sites' => [
            'my_field' => "VARCHAR(100) DEFAULT '' COMMENT '自定义字段'",
        ],
    ],

    // 3. 默认配置项（仅当 key 不存在时写入）
    'config' => [
        'plugin_myplugin_count'  => '5',
        'plugin_myplugin_enable' => '1',
    ],
];
```

- 配置 key 建议完整书写 `plugin_{插件名}_{配置键}`，与 `Plugin::config('myplugin','count','5')` 的拼接规则一致
- 少量早期插件（如 auto-link 的 `autolink_enable` 等）沿用无前缀键名，由 `admin/settings.php` 对应 case 保存，兼容可用
- **共享表**：多插件声明同一表（如 blacklist 被 wormhole/auto-link 共享）时，卸载会检查是否还有插件声明该表，最后一个卸载才真正 DROP

## 钩子系统

### 动作钩子（输出模式）

```php
// 注册（第 3 参数为优先级，越小越先执行，默认 10）
Plugin::registerHook('sidebar_bottom', 'my_plugin_sidebar', 10);

function my_plugin_sidebar() {
    echo '<div class="my-block">' . Security::e(Plugin::config('myplugin', 'text', '')) . '</div>';
}
```

### 过滤钩子（返回模式）

```php
Plugin::registerHook('filter_title', function ($title) {
    return $title . ' - 后缀';
});

// 模板/代码中触发：值链式经过所有回调
$title = Plugin::filter('filter_title', $title);
```

### 触发与辅助

```php
Plugin::hook('sidebar_top');                    // 无参数动作钩子
Plugin::hook('before_content', [$site]);        // 带参数（回调依次收到 $site）
Plugin::hasHook('after_footer');                // 是否有注册回调
Plugin::addFilter('filter_title', $cb);         // registerHook 的语义别名
```

模板中放置钩子调用点后，任何已启用插件的注册回调都会按优先级执行（同一钩子可被多个插件注册）；单个回调异常只写 `plugin_error` 日志，不影响其他回调。

## 可用钩子位置

### 前台模板钩子（主题中调用 `Plugin::hook()`）

`before_header`、`after_header`（header.php）；`search_bar_after`、`site_list_before`、`sidebar_top`、`sidebar_bottom`、`site_list_after`（index.php）；`before_content`（带 `[$site]`）、`after_content`（带 `[$site]`）（site.php）；`before_footer`、`after_footer`（footer.php）。

### 业务事件钩子（核心触发，插件监听；notify 插件即监听这些）

| 钩子 | 触发点 | 参数 |
|------|--------|------|
| `site_submitted` | 前台提交站点成功（api/index.php） | `['id','name','url','category_id','status','ip','email']` |
| `site_approved` | 后台审核通过（admin/review.php、admin/sites.php） | `['id','submit_email']` |
| `site_rejected` | 后台审核拒绝（admin/review.php） | `['id','submit_email']` |
| `feedback_submitted` | 前台反馈提交（api/index.php） | `['site_id','type','content','email','ip']` |
| `article_editor_before` / `article_editor_after` | article 插件后台编辑表单 | 无 |

### 后台钩子（系统页面触发，插件注入）

| 钩子 | 位置 | 参数 |
|------|------|------|
| `admin_sidebar` | 后台侧边栏导航（admin/bootstrap.php） | 无（用 `$GLOBALS['currentPage']` 判断高亮） |
| `admin_settings_nav` / `admin_settings_tabs` | 基础设置页 Tab（admin/settings.php） | `$activeTab` |

## 后台设置 Tab（main.php）

在 `main.php` 中注册两个钩子注入设置 Tab：

```php
<?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// 注入 Tab 导航
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    echo '<a href="#tab-myplugin" class="settings-tab ' . $cls . '"'
       . ' onclick="switchTab(\'myplugin\', this)">我的插件</a>';
});

// 注入 Tab 内容
Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    $value = Plugin::config('myplugin', 'count', '5');
    ?>
<div id="tab-myplugin" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">我的插件设置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="myplugin">
      <input type="hidden" name="tab" value="myplugin">
      <div class="form-group">
        <label>显示数量</label>
        <input type="number" class="form-input" name="plugin_myplugin_count" value="<?= Security::eAttr($value) ?>">
      </div>
      <div class="text-right"><button type="submit" class="btn btn-primary">保存</button></div>
    </form>
  </div>
</div>
<?php
});
```

**重要约定**：

- 表单 POST 到 `/admin/settings.php`，隐藏字段 `csrf_token` / `section`（插件名）/ `tab` 缺一不可
- 保存处理需在 `admin/settings.php` 的 `switch ($section)` 中新增对应 `case 'myplugin'` 分支（用 `$settingsModel->setMany()` 或 `Plugin::setConfig()` 保存），**没有对应 case 时不会保存**
- Tab `id` 必须为 `tab-{tabId}`；`switchTab()` 为后台内置 JS；样式沿用后台 `card/form-group/form-input/text-right/btn` 等类

## 独立后台管理页（admin.php）

- 插件目录放 `admin.php` 后，插件管理列表会在启用状态下显示「管理」按钮（跳转 `/admin/plugin.php?p=插件名`）
- 分发器会：校验插件已启用 → `adminHeader($title)` 输出后台布局 → `require_once` 插件 admin.php → `adminFooter()`
- admin.php 内可自行处理 POST（必须校验 `Security::verifyCSRFToken()`），直接输出卡片/表单/表格内容
- 参考实现：`plugins/article/admin.php`（文章管理）、`plugins/wormhole/admin.php`（成员/检测/黑名单）、`plugins/notify/admin.php`（测试发送/日志）、`plugins/dbtool/admin.php`（备份恢复）

## Plugin 类 API 速查

| 方法 | 参数 | 返回 / 说明 |
|------|------|-------------|
| `Plugin::init()` | 无 | 加载全部已启用插件的 include/main（bootstrap 自动调用） |
| `Plugin::scan()` | 无 | 全部插件 [name => info] |
| `Plugin::getInfo($name)` | $name | 插件元数据（?array） |
| `Plugin::isEnabled($name)` | $name | bool |
| `Plugin::setEnabled($name, $enabled)` | $name, bool | 启停（启动自动 ensureSchema） |
| `Plugin::ensureSchema($name)` | $name | 建表/加字段/写默认配置（幂等） |
| `Plugin::loadSchema($name)` | $name | ['tables','columns','config'] |
| `Plugin::ensureTables($name)` | $name | 旧接口（弃用，转发 ensureSchema） |
| `Plugin::uninstall($name)` | $name | ['success','dropped_tables','dropped_columns','cleared_keys'] |
| `Plugin::getEnabledPlugins()` | 无 | 已启用插件列表 |
| `Plugin::registerHook($hook, $cb, $p=10)` | — | 注册钩子 |
| `Plugin::addFilter($hook, $cb, $p=10)` | — | registerHook 别名 |
| `Plugin::hook($hook, $args=[])` | — | 执行动作钩子 |
| `Plugin::filter($hook, $value, $args=[])` | — | 执行过滤钩子 |
| `Plugin::hasHook($hook)` | $hook | bool |
| `Plugin::config($plugin, $key, $default=null)` | — | 读配置（拼 `plugin_{plugin}_{key}`） |
| `Plugin::setConfig($plugin, $key, $value)` | — | 写配置 |
| `Plugin::getDir($name)` | $name | 插件目录绝对路径 |
| `Plugin::asset($plugin, $file)` | — | 插件资源 URL |
| `Plugin::clearCache()` | 无 | 清扫描缓存（后台启停后调用） |

数据库与安全常用：`Database::table('表名')`（带前缀）、`Database::query/queryOne/execute/insert/scalar`、`Security::e/eAttr/cleanString/cleanHtml/int/enum`、`setting()`、`Rewrite::url()`、`Theme::e()` 等。

## 内置插件一览

内置 13 个插件，全部默认关闭：广告管理（ad）、文章发布（article）、虫洞联盟（wormhole）、友链自动收录（auto-link）、伪静态设置（rewrite）、提交网站收录（submit）、网站地图（sitemap）、图片灯箱（lightbox）、图片ALT（auto-alt）、邮箱通知（notify）、友情链接（friendlink）、蜘蛛来访（spider）、数据库备份（dbtool）。各插件功能/数据库影响/钩子见在线文档第五章。

## 开发示例

### 示例 1：最小插件（无设置项）

`plugins/hello/plugin.json`：

```json
{
    "name": "hello",
    "title": "Hello World",
    "version": "1.0",
    "description": "在页脚输出内容",
    "main_file": "include.php",
    "hooks": ["after_footer"],
    "builtin": true
}
```

`plugins/hello/include.php`：

```php
<?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

Plugin::registerHook('after_footer', function () {
    echo '<!-- hello plugin -->';
});
```

### 示例 2：带设置 Tab 的插件（notice）

`plugins/notice/plugin.json` 增加 `"config_file": "main.php"`；`include.php` 注册 `sidebar_top` 输出公告；`main.php` 按上文「后台设置 Tab」模板注入 Tab（注意在 `admin/settings.php` 加 `case 'notice'` 保存 `plugin_notice_text`）。

### 示例 3：带数据表与管理页的插件

参考第 5 节 schema.php（声明表与配置）、第 9 节 admin.php（管理页）。数据读写统一用：

```php
$tbl = Database::table('my_table');
$rows = Database::query("SELECT * FROM {$tbl} WHERE status = 1 ORDER BY id DESC");
Database::execute("UPDATE {$tbl} SET status = ? WHERE id = ?", [1, $id]);
```

## 开放 API 接口声明（api.php，可选）

如果希望 App / 小程序等第三方通过开放 API 读写插件数据（如文章、友链），可在插件目录放一个 `api.php` 声明接口：

```php
<?php
// plugins/myplugin/api.php
if (!defined('APP_VERSION') || !class_exists('Database') || !class_exists('Plugin')) {
    die('Forbidden');
}

// 处理器：命名建议 open_api_{插件}_{动作}；请求体用 api_json_input()，输出用 Security::jsonOutput()
function open_api_myplugin_list(): void
{
    $rows = Database::query("SELECT * FROM " . Database::table('my_table') . " WHERE status = 1");
    Security::jsonOutput(['success' => true, 'code' => 0, 'message' => 'ok', 'data' => ['list' => $rows]]);
}

return [
    [
        'endpoint' => 'open/myplugin/list',      // 访问路径 /api/open/myplugin/list
        'method'   => 'GET',
        'handler'  => 'open_api_myplugin_list',  // 处理器函数名
        'group'    => '我的插件',                 // 后台文档分组名
        'title'    => '数据列表',
        'desc'     => '一句话说明。',
        'params'   => 'page / limit 等参数说明',
        'example'  => 'GET /api/open/myplugin/list?page=1',
    ],
];
```

约定与说明：

1. 所有 `open/*` 接口**默认需要 API Key**（后台「API 密钥」创建），`X-API-Key` 请求头 / `api_key` 参数 / POST JSON 字段三选一
2. 插件**启用后接口自动注册**（无需改 api/index.php）；**停用后访问返回 403（40301）**
3. 处理器运行于 api/index.php 环境：可用 `api_json_input()` 读取 JSON 请求体、`Security::jsonOutput()` 输出、`api_open_log()` 写审计日志、`Logger`、`Database`、核心各 Model 与已加载的插件 Model
4. `include.php` 在引导阶段已加载，处理器可放心使用其中定义的类与函数
5. 每个插件可声明多个接口，`handler` 名建议加插件前缀避免冲突
6. 内置插件参考实现：`plugins/article/api.php`（含文章发布/编辑/删除）、`plugins/friendlink/api.php`、`plugins/wormhole/api.php`、`plugins/spider/api.php`

## 最佳实践与调试

1. **安全**：输出转义 `Security::e()/eAttr()`；富文本 `Security::cleanHtml()`；表单带 CSRF；SQL 一律预处理
2. **命名**：函数/类加前缀避免冲突（`plugin_myplugin_` 风格）
3. **文件分工**：include.php = 业务+钩子注册；main.php = 设置 Tab；无设置项只建 include.php
4. **日志**：关键操作写 `Logger::log('my_channel', ...)`（后台可开关，见 log-guide.md）
5. **性能**：钩子回调避免重查询/阻塞操作
6. 调试：后台「基础设置-基础信息」开启调试模式可看到错误详情；`data/logs/YYYYMMDD/plugin_error.log` 记录插件运行错误
7. 检查插件是否生效：后台「插件管理」看状态与注册钩子；`Plugin::isEnabled('hello')` 可在代码中判断
