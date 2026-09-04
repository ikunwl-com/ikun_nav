# 懒人导航

> 一个简洁、现代、功能完善的网址导航站系统，支持友链自动收录、虫洞联盟、TDK 权重采集、Sitemap 生成等特性。

## 快速开始

1. 上传代码到服务器
2. 访问 `install/` 目录完成安装向导
3. 登录后台配置站点信息
4. 前台开始收录和展示站点

## 核心特性

- **友链自动收录**：外站挂友链后，用户点击进入导航站时自动检测并收录对方站点
- **虫洞联盟**：站点间互相引流，加入联盟后自动展示联盟成员
- **TDK 权重采集**：自动抓取站点标题/描述/关键词，对接 5118 查询搜索引擎权重
- **Sitemap 生成**：自动生成标准 XML Sitemap 和 robots.txt
- **开放 API**：API Key 鉴权，覆盖站点/分类的查询与发布、编辑、删除接口，内置插件数据接口启用后自动注册，方便 App / 小程序 / 合作方直接对接（详见 [开放 API 对接文档](data/docs/api-guide.md)）
- **伪静态支持**：支持 Apache/Nginx 伪静态，可自定义 URL 格式
- **主题系统**：支持多主题切换，易于开发和扩展
- **完整日志**：覆盖自动收录、虫洞联盟、后台审计等全链路日志

## 技术栈

- **后端**：PHP 7.4+ / MySQL 5.7+ / PDO 预处理
- **前端**：原生 HTML/CSS/JS（无框架依赖）
- **安全**：XSS 全站转义 / CSRF Token / 频率限制 / 黑名单 / 防盗链
- **部署**：支持 Apache / Nginx / PHP 内置服务器

## 项目结构

```
懒人导航/
  admin/              # 后台管理
    bootstrap.php     # 后台初始化
    sites.php         # 站点管理
    categories.php    # 分类管理
    settings.php      # 基础设置（含友链自动收录配置）
    wormhole.php      # 虫洞联盟管理
    ...
  api/                # RESTful API 入口
    index.php         # API 路由分发（open/* 开放接口与插件接口由 core/OpenApi.php 注册）
  core/               # 核心类库
    AutoLinkModel.php # 友链自动收录核心逻辑
    Theme.php         # 主题系统
    Logger.php        # 日志系统
    Security.php      # 安全工具
    Route.php         # 路由分发
    Rewrite.php       # 伪静态系统
    ...
  templates/          # 主题目录
    default/          # 默认主题
      index.php       # 首页
      footer.php      # 底部（含自动收录 JS，主题开发必保留）
      ...
  install/            # 安装向导
    do_install.php    # 安装脚本
  data/               # 数据目录
    logs/             # 日志文件（按天分目录）
    backups/          # 数据库备份（dbtool 插件）
    docs/             # 文档（index.php 为在线版文档，*.md 为配套文档）
  docs/               # 开发文档（存放于 data/docs/）
    index.php         # 在线版使用与开发文档（访问 /data/docs/ 查看）
    theme-dev.md      # 主题开发指南
    plugin-dev.md     # 插件开发指南
    log-guide.md      # 日志系统使用指南
```

## 文档索引

| 文档 | 面向读者 | 内容 |
|------|----------|------|
| [在线版文档](data/docs/index.php) | 全体 | 使用与开发全量文档（安装、系统结构、主题开发、插件开发、内置插件、参考） |
| [主题开发指南](data/docs/theme-dev.md) | 前端/主题开发者 | 主题目录结构、模板变量、Theme 方法、钩子集成、最佳实践 |
| [插件开发指南](data/docs/plugin-dev.md) | 插件应用开发者 | plugin.json、schema.php、钩子系统、Plugin API、内置插件案例 |
| [日志系统指南](data/docs/log-guide.md) | 运维/开发者 | 日志频道清单、后台开关配置、查看与清理方法 |
| [开放 API 对接文档](data/docs/api-guide.md) | App/小程序/合作方开发 | API Key 鉴权、站点/分类查询与发布/编辑/删除接口、内置插件接口、请求响应示例与代码示例 |

## 友链自动收录

懒人导航的核心功能。当用户从挂了导航站友链的外站点击跳转到导航站时：

1. 前台首页 PHP 渲染阶段捕获 `$_SERVER['HTTP_REFERER']`
2. 过滤本站和搜索引擎来路
3. 通过 URL 参数 `ref=` 传给 `/api/?endpoint=auto-link`
4. 后端抓取对方首页，验证回链、TDK、违禁词
5. 通过后自动收录到导航站

## 配置参考

### 数据库 `settings` 表常用配置

| 键 | 默认值 | 说明 |
|---|---|---|
| `site_name` | `懒人导航` | 站点名称 |
| `site_slogan` | `发现好网站` | 站点口号 |
| `current_theme` | `default` | 当前主题 |
| `rewrite_mode` | `dynamic` | URL 模式：`dynamic`/`rewrite`/`index` |
| `autolink_enable` | `0` | 友链自动收录开关（auto-link 插件） |
| `autolink_need_review` | `1` | 收录后是否需审核 |
| `autolink_default_category` | `0` | 自动收录默认分类 ID |
| `autolink_banned_words` | `""` | 违禁词黑名单 |
| `log_global` | `1` | 日志总开关（基础设置 → 基础信息 → 日志设置） |
| `log_autolink` | `1` | 自动收录日志开关 |

## 伪静态配置

### Apache

已包含 `.htaccess` 文件：

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

### Nginx

伪静态模式请以后台「基础设置 - 伪静态」生成的规则为准，参考配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 日志查看

```bash
# 查看今天的自动收录日志
cat data/logs/$(date +%Y%m%d)/autolink.log

# 实时追踪
tail -f data/logs/$(date +%Y%m%d)/autolink.log
```

详见 [日志系统指南](data/docs/log-guide.md)。

## 开发调试

### 开启调试模式

编辑 `config.php`：

```php
define('APP_DEBUG', true);
```

开启后，API 错误会返回详细的 JSON 信息（包含文件路径和堆栈）。

### 本地开发服务器

```bash
cd /path/to/项目
php -S localhost:8080 index.php
```

## 安全建议

1. **生产环境关闭调试模式**：`APP_DEBUG` 设为 `false`
2. **修改后台默认路径**：将 `admin/` 目录重命名为随机名称
3. **限制目录权限**：
   ```bash
   chmod 755 data/
   chmod 644 data/logs/ -R
   ```
4. **定期清理日志**：设置 cron 任务清理 30 天前的日志
5. **开启 HTTPS**：确保导航站使用 HTTPS，Referer 传递更可靠

## 开源协议

MIT License

## 技术支持

- QQ: 207385345
- E-mail: lkba@aliyun.com
- [演示网站：https://site.ikunwl.com](https://site.ikunwl.com)
