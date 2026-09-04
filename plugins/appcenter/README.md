# 应用中心插件（appcenter）使用与开发说明

> 在线应用商店：站长在后台即可浏览你发布的插件 / 主题目录，一键下载、安装与升级。
> 属于**分发通道的客户端**，配套的服务端参考实现见仓库根目录 `appcenter-server/`
> （自动扫描文件夹，无需维护清单）。

## 一、安装与启用

1. 将本目录（`plugins/appcenter/`）放入懒人导航的 `plugins/` 目录；
2. 后台 → 插件管理 → 找到「应用中心」→ 点击**启动**；
3. 左侧边栏出现「应用中心」入口（或 插件管理 → 应用中心 的「管理」按钮）。

> 首次使用无需配置即可用：默认指向**官方服务器**（`https://site.ikunwl.com/appcenter-server`），
> 可在「服务器设置」切换**第三方服务器**，或填写**自定义地址**（自定义非空时优先使用；清空即回到预设）。
> 保存后点击「刷新目录」拉取对应服务器的应用列表。

## 二、功能

- **目录浏览**：插件 / 主题列表，展示标题、作者、简介、最新版本、本地版本、包体积；
- **状态识别**：未安装 / 可升级 / 已是最新 / 不兼容（自动对比懒人导航版本与 `min_version`/`max_version`）；
- **一键安装**：下载 → 安全解压 → 写入 `plugins/` 或 `templates/` → 新插件自动启用；
- **一键升级**：升级前自动备份旧版本目录到 `data/appcenter/backups/`（保留最近 3 份），失败自动回滚；
  - 升级**只替换文件、不动数据库表**——如需表结构变更，请在插件内自行处理（如 schema 幂等建表）；
- **自我保护**：应用中心自身（`appcenter`）禁止通过商店安装 / 覆盖；
- **审计日志**：拉取、安装、升级、设置变更全部写入 `appcenter` 日志频道。

## 三、目录协议（客户端视角）

服务器 `list.php` 返回 JSON：

```json
{
  "success": true,
  "message": "",
  "items": [
    {
      "type": "plugin",
      "id": "ad",
      "title": "广告管理",
      "version": "1.2.0",
      "description": "……",
      "author": "懒人导航",
      "homepage": "https://example.com",
      "min_version": "1.0.0",
      "max_version": "",
      "changelog": "v1.2.0：修复……",
      "download_url": "https://apps.example.com/appcenter/download.php?type=plugin&id=ad",
      "size": 20480,
      "sha256": ""
    }
  ]
}
```

字段说明：

| 字段 | 必填 | 说明 |
|---|---|---|
| `type` | 是 | `plugin` 或 `theme` |
| `id` | 是 | 唯一标识，仅允许 `a-z0-9-`，须与包内目录名一致 |
| `version` | 是 | 版本号（建议语义化如 `1.2.0`） |
| `download_url` | 是 | 安装包下载地址，默认须与目录服务器**同域**（可在后台添加白名单域名） |
| `title/description/author/changelog` | 否 | 展示信息 |
| `min_version/max_version` | 否 | 懒人导航版本兼容区间，空表示不限制 |
| `size` | 否 | 包体积（字节），供展示 |
| `sha256` | 否 | 可选，提供后客户端下载完强制校验（自动扫描版服务端为按需打包，故留空） |

### 安装包（ZIP）结构约定

压缩包**根目录**必须直接包含 `plugins/{id}/` 或 `templates/{id}/`（与 `type` 对应）：

```
ad-1.2.0.zip
└── plugins/
    └── ad/
        ├── plugin.json        ← type=plugin 必须（name 必须等于 id）
        ├── include.php
        └── ...
```

```
my-theme-1.0.0.zip
└── templates/
    └── my-theme/
        ├── theme.json         ← type=theme 必须（建议含 name=my-theme）
        ├── index.php          ← 必须（否则无法启用为主题）
        └── ...
```

> 配套服务端（`appcenter-server/`）会自动按此结构打包，你只需要把扩展文件夹放进
> `apps/plugins/` 或 `apps/themes/`，无需手工维护 ZIP。

### 安全边界（客户端已内置）

- 所有接口需后台登录 + CSRF（滑动窗口 Token）双重校验；
- 下载地址仅允许 http/https、服务器同域或后台白名单、禁止内网/本机地址（防 SSRF）；
- 压缩包解压**逐条校验路径**：拒绝 `..` 穿越、绝对路径、盘符、NUL 字节；忽略 `__MACOSX`、`.DS_Store` 等打包噪音；目录外文件一律不写入；
- 解压数量（≤5000 个）与解压体积（≤300MB）双上限，超大单条预检不入内存；
- 元数据 `name` 与目录 `id` 不一致即拒绝；
- 升级前自动备份、失败自动回滚；卸载/删除仍走系统自带的「插件管理 / 主题管理」，应用中心不越权删库；
- **运行时数据目录自动防护**：`data/appcenter/` 首次使用时自动生成禁访 `.htaccess`（Apache），
  防止备份的插件代码 / 临时解压文件 / 下载包被 Web 直接访问执行；**Nginx 用户必须**在站点配置手动加：
  `location ~ ^/data/appcenter/ { deny all; }`；
- 目录 JSON 响应有 6MB 体积上限；安装包下载限 400MB，且**重定向结束后仍校验最终域名**是否允许（防跳转劫持 / SSRF）。

## 四、本地目录结构

```
plugins/appcenter/
├── plugin.json     元数据
├── include.php     主文件（后台侧边栏导航钩子）
├── lib.php         核心库（拉取/下载/安全解压/安装升级回滚/版本比较）
├── api.php         AJAX 接口（list / refresh / install / save_config）
├── admin.php       后台商店页面（admin/plugin.php?p=appcenter 载入）
└── README.md       本文件
```

运行期数据（目录缓存、下载包、升级备份、临时解压目录）写入 `data/appcenter/`：

```
data/appcenter/
├── catalog.json     最近一次目录缓存
├── packages/        下载的安装包（安装成功后即删除）
├── backups/         升级前备份（保留最近 3 份，可手动删除）
└── tmp/             解压临时目录（用完即删）
```

> 停用 / 卸载插件不会自动清除以上数据；彻底清理时手动删除整个 `data/appcenter/` 目录即可。

## 五、发布插件到应用中心（作者侧）

1. 在发布服务器部署 `appcenter-server/`（见该目录 README）；
2. **把插件文件夹上传到 `apps/plugins/{插件名}/`，主题文件夹上传到 `apps/themes/{主题名}/`**——
   目录自动识别，无需任何清单或打包操作；
3. 升级 = 改 `plugin.json`/`theme.json` 里的 `version` 后重新上传文件；
   客户端刷新后即可看到「可升级」；
4. 兼容区间写在元数据的 `min_version`/`max_version` 字段（可选）。

> 若分发渠道希望做到「仅授权站点可用」，可在服务端 `list.php` 增加域名校验
> （客户端每次请求会带 `X-Site-Url` 请求头），仅对白名单站点返回可下载条目即可。
