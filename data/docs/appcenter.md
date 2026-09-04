# 懒人导航「应用中心」开发者接入指南

> 本文面向**插件 / 主题开发者**：教你把自研扩展通过「应用中心」分发给所有懒人导航站长——
> 站长在后台一键安装、升级，无需上传解压、无需联系你远程部署。
>
> 阅读前提：先了解懒人导航的插件系统与主题系统，详见
> [插件开发指南](plugin-dev.md) 与 [主题开发指南](theme-dev.md)。
> 本文只讲「如何把开发好的扩展发布进应用中心」以及应用中心的全部约定。

---

## 1. 整体流程（先建立概念）

```
你（开发者）                    你的发布服务器                使用你扩展的站长
┌──────────────┐   上传文件夹   ┌───────────────────┐   拉目录   ┌──────────────┐
│ 写好插件/主题 │ ────────────► │ apps/plugins/xxx/ │ ◄───────── │ 后台-应用中心  │
│ 本地测试通过  │               │ apps/themes/xxx/  │ ──下载──► │ 一键安装/升级  │
└──────────────┘               │ list.php 自动扫描 │           └──────────────┘
                               │ download.php 打包 │
                               └───────────────────┘
```

- **你不用**维护任何清单、不用手动打 ZIP：发布服务器会自动扫描 `apps/` 目录下的文件夹，
  读取元数据生成目录；站长点击安装时服务器**实时打包**成 ZIP 下发；
- 你唯一要交付的产物，就是一个**结构规范的插件 / 主题文件夹**。

---

## 2. 插件规范（发布前必须满足）

### 2.1 文件夹结构

```
myplugin/
├── plugin.json     元数据（必需）
├── include.php     主文件：函数定义 + 钩子注册（推荐与内置插件一致用此文件名）
├── main.php        后台设置面板（可选）
├── admin.php       独立后台管理页（可选，存在即自动出现「管理」按钮）
├── schema.php      数据库声明：建表/加字段/默认配置（可选，固定文件名）
└── css/ js/ ...    资源（可选）
```

每个 PHP 文件开头都要有防直接访问的安全检查：

```php
<?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}
```

### 2.2 plugin.json 字段

```json
{
    "name": "myplugin",
    "title": "我的插件",
    "version": "1.0.0",
    "author": "作者名",
    "description": "一句话功能描述（站长在商店列表看到的就是它）",
    "main_file": "include.php",
    "hooks": ["before_footer"],
    "builtin": false,
    "min_version": "1.0.0",
    "max_version": "",
    "changelog": "v1.0.0：首个版本"
}
```

| 字段 | 必填 | 说明 |
|---|---|---|
| `name` | ✅ | 插件标识，**必须等于文件夹名**，仅允许小写 `a-z0-9-` |
| `title` / `description` / `author` | ✅/– | 商店列表与后台展示信息 |
| `version` | ✅ | 版本号，见第 5 节「版本与升级规则」 |
| `main_file` | – | 主文件名，默认 `{name}.php`，请统一显式写 `"include.php"` |
| `hooks` | – | 声明使用的钩子（后台展示用，实际注册靠代码） |
| `builtin` | – | 系统默认 `true`；**第三方插件请写 `false`**，否则后台会显示「内置」标签 |
| `min_version` / `max_version` | – | **应用中心扩展字段**：声明兼容的懒人导航版本区间，空=不限制 |
| `changelog` | – | **应用中心扩展字段**：更新日志，可选 |

> `enabled`（是否启用）不写在这里，由站长后台决定，系统存到 settings 表。

### 2.3 include.php 最简示例（可运行的骨架）

```php
<?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ====== 钩子注册（仅插件启用时加载本文件，可直接注册） ======
Plugin::registerHook('before_footer', 'myplugin_footer_note', 30);

function myplugin_footer_note(): void
{
    $info = Plugin::getInfo('myplugin');
    $ver  = is_array($info) ? (string)($info['version'] ?? '1.0.0') : '1.0.0';
    echo '<div style="text-align:center;font-size:12px;color:#9ca3af;padding:8px 0;">'
       . '我的插件 v' . Security::e($ver)
       . ' 运行中</div>';
}
```

常用前台钩子位置（默认主题里触发）：`before_header` / `after_header` / `search_bar_after` /
`site_list_before` / `sidebar_top` / `sidebar_bottom` / `site_list_after` /
`before_content` / `after_content` / `before_footer` / `after_footer`；
后台入口钩子：`admin_sidebar`。完整列表与带参钩子见 plugin-dev.md「可用钩子位置」。

### 2.4 需要数据库时的 schema.php

启用插件时系统自动执行，**必须幂等**（已存在就跳过，重复启用不出错、不覆盖站长数据）：

```php
<?php
return [
    // 1. 自建表（{prefix} 会自动替换为站点表前缀）
    'tables' => [
        'myplugin_records' => "CREATE TABLE IF NOT EXISTS `{prefix}myplugin_records` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ],
    // 2. 默认配置项（仅不存在时写入；key 建议写全 plugin_{插件名}_{键}）
    'config' => [
        'plugin_myplugin_enable' => '1',
    ],
];
```

> ⚠️ **升级只替换文件、不动数据库表**——应用中心升级时不会执行任何卸载/建表动作。
> 如果新版本需要加字段/表，请在插件自己逻辑里幂等处理（例如启用时调用
> `Plugin::ensureSchema('myplugin')`，或初始化时执行 `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` 等价写法）。

### 2.5 想给站长一个后台管理页？

放一个 `admin.php`，系统自动在「插件管理」里显示「管理」按钮（跳转
`/admin/plugin.php?p=myplugin`）。页内 POST 必须校验 CSRF：

```php
<?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/plugin.php?p=myplugin&err=' . urlencode('CSRF验证失败'));
    }
    // ……处理逻辑……
    redirect('/admin/plugin.php?p=myplugin&ok=' . urlencode('保存成功'));
}
// ……页面 HTML（外层已输出后台布局，直接写内容即可）……
```

### 2.6 插件开发红线

- **禁止修改核心文件**（`core/`、`admin/`、`index.php` 等）——升级主程序会冲突，站长也无法一键回滚你的改动；
- 自定义函数/类/全局变量加插件名前缀（`myplugin_xxx`），避免与其它插件冲突；
- 输出内容一律转义：`Security::e()` / `Security::eAttr()`；
- 插件名全局唯一，发布前先到应用中心确认没有同名 id。

---

## 3. 主题规范（发布前必须满足）

### 3.1 文件夹结构

```
templates/mytheme/
├── theme.json          元数据
├── index.php           首页（必需！没有它无法被启用为当前主题）
├── category.php / site.php / search.php / submit.php ...  按需覆盖
├── header.php / footer.php ... 片段（Theme::partial() 引用）
└── screenshot.png      后台缩略图（可选）
```

> 好消息：**模板缺失自动回退**——`Theme::render()` 在当前主题找不到模板时会用
> `templates/default/` 的同名文件顶上。所以新手可以从复制 `templates/default/` 起步，
> 或只做"换肤"覆盖想改的页面。

### 3.2 theme.json 字段

```json
{
    "name": "mytheme",
    "title": "我的主题",
    "version": "1.0.0",
    "author": "作者名",
    "description": "主题简介",
    "preview": "",
    "support": ["index", "category", "site", "search", "submit"],
    "min_version": "1.0.0",
    "changelog": "v1.0.0：首发"
}
```

- `name` 必须等于文件夹名（小写 `a-z0-9-`）；
- `version`、`min_version`/`max_version`、`changelog` 语义与插件完全一致（见第 5 节）；
- 其它主题开发细节（模板变量、URL 生成、钩子集成、最佳实践）见 theme-dev.md。

---

## 4. 发布到应用中心（核心操作）

发布服务器目录约定：

```
appcenter-server/
├── list.php              自动扫描目录（只读）
├── download.php          站长点安装时实时打包 ZIP
└── apps/
    ├── plugins/          ← 把插件文件夹整个放这里
    │   └── myplugin/
    │       ├── plugin.json
    │       └── include.php
    └── themes/           ← 把主题文件夹整个放这里
        └── mytheme/
            ├── theme.json
            └── index.php
```

发布动作就三步：

1. **上传**：把整个扩展文件夹（含元数据文件）传到 `apps/plugins/` 或 `apps/themes/`；
2. **验证**：浏览器打开 `https://你的服务器/appcenter/list.php`，JSON 里能看到你的应用即成功
   （被忽略的目录会出现在 `warnings` 数组，附原因）；
3. **升级**：改元数据里的 `version` 后重新上传覆盖即可，站长端自动出现「可升级」。

服务端会自动**忽略**这些情况并给出 warnings（不会报错中断）：
- 文件夹名不是小写 `a-z0-9-`（含中文、大写、空格）；
- 缺少 `plugin.json` / `theme.json`，或 JSON 解析失败；
- 元数据 `name` 与文件夹名不一致；
- 隐藏目录（如 `.git`、`.DS_Store`）。

---

## 5. 版本与升级规则（务必理解）

| 客户端状态 | 判定 | 站长看到 |
|---|---|---|
| 未安装 | 本地无该 id | 「安装」按钮 |
| 可升级 | 本地已装，且 **目录 version > 本地 version** | 「升级」按钮 |
| 已是最新 | 目录 version ≤ 本地 version | 灰色状态，无按钮 |
| 不兼容 | 站点版本 < `min_version` 或 > `max_version` | 禁用 + 原因说明 |

规则：

1. **版本号只升不降**；建议语义化版本 `1.2.0`；
2. 与本地版本比较用系统版本比较器（`1.0` 与 `1.0.0` 视为相同），所以升级请**递增**；
3. `min_version`/`max_version` 声明对懒人导航主程序版本的兼容区间，不满足的站长
   **无法安装**（客户端会拦截），可避免低版本站点装出问题；
4. 不确定兼容范围时，`min_version` 写你测试过的最低版本，`max_version` 留空。

---

## 6. 发布前自测清单（照着过一遍再传）

- [ ] 插件能直接放进**未启用状态**的懒人导航 `plugins/` 目录并正常「启动 / 停用 / 卸载」；
- [ ] 需要数据库时 schema 幂等：反复启用/停用不报错、不重复建表；
- [ ] 主题含 `index.php` 且后台「主题管理」能切换、前台正常；
- [ ] 前端输出全部转义，无 PHP 报错（可在自己站点开 `APP_DEBUG` 验证）；
- [ ] 上传到 `apps/` 后 `list.php` 返回你的应用、`warnings` 为空；
- [ ] 用另一台（或本地另一份）懒人导航当"站长"，走一遍：安装 → 前台功能正常 → 停用 →
      改版本升级 → 卸载；
- [ ] 服务器 PHP 已装 **zip 扩展**（实时打包必需）且目录可写。

---

## 7. 常见问题（FAQ）

| 现象 | 原因与处理 |
|---|---|
| `list.php` 里看不到我的应用 | 看返回 JSON 的 `warnings` 数组；按第 4 节逐条核对目录名、元数据文件、`name` 一致性 |
| 站长安装时下载 404 | `apps/` 路径或文件夹名被改；服务器 PHP 缺 zip 扩展（download.php 会返回 500 提示） |
| 提示"不兼容" | 站长站点版本不在你声明的 `min_version`~`max_version` 区间 |
| 已经上传新版却不显示"可升级" | `version` 没改，或新版本号 ≤ 本地已装版本 |
| 主题安装成功但前台没变化 | 需在后台「主题管理」**启用**为当前主题 |
| 安装后插件报错/无效果 | 后台日志找 `appcenter` 频道与插件自身日志；常见为 include.php 语法错误或函数名冲突 |
| 我想只更新一处小文件 | 直接重新上传整个文件夹即可（服务器按文件夹整体打包） |

---

## 8. 你的责任（发布者须知）

应用中心是一个**自动执行代码**的通道——站长点「安装」后，你的代码会在他们的服务器上运行。
请务必：

1. 只做你承诺的功能，不含后门、挖矿、收集站长隐私等恶意逻辑；
2. 不写死站长域名/IP，不采集他人站点数据；
3. 升级前想清楚数据兼容：老版本数据如何平滑过渡（schema 幂等）；
4. 明确填写 `description` 与 `changelog`，让站长知道装的是什么、改了什么；
5. 保留联系方式（作者名/主页），出问题站长能找得到你。

---

## 参考文档

- [插件开发指南](plugin-dev.md) — 插件系统完整机制
- [主题开发指南](theme-dev.md) — 主题系统完整机制
- [开放 API 对接文档](api-guide.md) — 需要对外提供数据接口时阅读
- `appcenter-server/README.md` — 发布服务器部署与运维（作者侧）
- `plugins/appcenter/README.md` — 客户端协议与安全边界（有兴趣深究时阅读）
