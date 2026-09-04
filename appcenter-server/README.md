# 应用中心 - 服务端（作者侧）

> 懒人导航「应用中心」插件的配套服务端。**零清单维护**：把插件/主题文件夹丢进
> `apps/` 对应目录，客户端「刷新目录」即可自动看到并一键安装/升级。
> 客户端插件位于 `plugins/appcenter/`，目录协议见 [客户端 README](../plugins/appcenter/README.md)。

## 一、目录结构

```
appcenter-server/
├── list.php              目录接口（自动扫描 apps/，返回 JSON 目录）
├── download.php          按需打包下载（点击安装时实时生成 ZIP）
├── apps/                 ← 扩展源文件都放这里，无需任何配置
│   ├── plugins/          插件文件夹放这里，如 apps/plugins/demo-plugin/
│   │   └── demo-plugin/    （内含 plugin.json、include.php）
│   └── themes/           主题文件夹放这里，如 apps/themes/my-theme/
│       └── my-theme/       （内含 theme.json、index.php）
```

> 不需要 `apps.json`、不需要手动打包 ZIP——`list.php` 读每个文件夹里的
> `plugin.json` / `theme.json` 生成目录，`download.php` 在下载时实时打包。

## 二、快速开始（三步）

### 1. 部署

将本目录上传到服务器任意可执行 PHP 的目录，例如：

```
https://apps.example.com/appcenter/
├── list.php
├── download.php
└── apps/
```

浏览器访问 `https://apps.example.com/appcenter/list.php`，应返回 JSON 目录
（自带 `apps/plugins/demo-plugin/` 示例插件，开箱即见）。

### 2. 发布插件 / 主题（就是这么简单）

```
1. 把你的插件文件夹（含 plugin.json）上传到  apps/plugins/你的插件名/
   把主题文件夹（含 theme.json、index.php）上传到  apps/themes/你的主题名/
2. 完成 —— 无需任何其它操作
```

- 文件夹名 = 应用 id（仅允许 `a-z0-9-`），且 `plugin.json`/`theme.json` 里的 `name` 必须一致；
- 版本号写在 `plugin.json`/`theme.json` 的 `version` 字段——**改版本号重新上传即自动变成"可升级"**；
- 兼容区间写在元数据的 `min_version` / `max_version`（可选，如 `"min_version": "1.0.0"`）；
- 建议在 `apps/` 同级目录用版本子目录或备份工具管理历史版本，避免直接覆盖丢失旧版。

### 3. 客户端接入

站长侧：后台 → 插件管理 → 启动「应用中心」→ 服务器地址填
`https://apps.example.com/appcenter`（基地址，不含 list.php）→ 保存 → 刷新目录。

> 端到端验证：示例插件已在 `apps/plugins/demo-plugin/`，客户端刷新后应看到「示例插件」，
> 点安装 → 前台页脚出现提示 → 把插件 `version` 改成 1.1.0 重新上传 → 客户端显示「可升级」。

## 三、元数据字段（写在 plugin.json / theme.json 里）

| 字段 | 必填 | 说明 |
|---|---|---|
| `name` | 是 | 须等于文件夹名（id） |
| `version` | 是 | 版本号，如 `1.2.0`；与客户端「本地版本」比对判断是否可升级 |
| `title / description / author` | 否 | 目录展示信息 |
| `homepage` | 否 | 项目主页（展示用） |
| `min_version / max_version` | 否 | 懒人导航版本兼容区间，空不限制 |
| `changelog` | 否 | 更新日志（展示用） |

## 四、行为与安全说明

1. `list.php` 只读 `apps/`，自动忽略：无元数据文件的目录、`name` 不一致的目录、
   目录名非法的目录、隐藏目录（如 `.git`）；被忽略项会出现在响应的 `warnings` 数组里方便排查；
2. `download.php` 按需打包：`type` 白名单 + `id` 白名单 + realpath 目录包含校验（防路径穿越）；
   打包时自动跳过 `.DS_Store`、`Thumbs.db`、`desktop.ini`、`__MACOSX` 等噪音；
3. 下载地址由 `list.php` 按当前访问域名自动生成，因此**永远与目录服务器同域**，
   客户端无需配置下载白名单即可安装（若你自行架设把 download_url 指向 CDN 的服务器，才需要白名单）；
4. 打包为实时生成、不落缓存，适合目录较小（几十 MB 以内）的常规分发；超大扩展建议自行托管静态 ZIP
   并自行实现 list.php 返回静态地址（协议不变）；
5. **可选授权分发**：客户端请求 `list.php` 会自动携带 `X-Site-Url` 请求头，
   可在此校验站点域名白名单，非白名单返回空目录即可；
6. 请全程使用 HTTPS；本目录无需数据库，备份 = 复制整个目录。

## 五、常用命令（可选）

目录里不需要任何命令。若在服务器上想快速验证目录接口：

```bash
curl https://apps.example.com/appcenter/list.php
```

返回 JSON 中的 `count` 即当前可安装的应用数，`warnings` 数组会提示被忽略的文件夹及原因。
