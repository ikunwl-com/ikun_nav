# 日志系统使用指南

> 懒人导航内置结构化文件日志系统：按天分目录、按频道分文件，可在后台直接开关，用于运维排查、安全审计和数据分析。

## 目录

1. [日志目录结构](#日志目录结构)
2. [日志频道清单](#日志频道清单)
3. [开关配置（后台界面）](#开关配置后台界面)
4. [开关优先级与直接 SQL 修改](#开关优先级与直接-sql-修改)
5. [如何在代码中写日志](#如何在代码中写日志)
6. [日志文件格式](#日志文件格式)
7. [查看日志的方法](#查看日志的方法)
8. [日志清理策略](#日志清理策略)
9. [常见问题](#常见问题)

---

## 日志目录结构

```
data/logs/
  20260801/              # 日期目录（YYYYMMDD）
    autolink.log         # 友链自动收录日志
    wormhole_join.log    # 虫洞联盟加入日志
    wormhole_check.log   # 虫洞联盟检测日志
    go_jump.log          # 跳转访问日志
    admin_site.log       # 后台站点操作审计
    ...
  20260802/
    autolink.log
    ...
```

日志按天分割，每天一个目录，每个频道独立文件。

## 日志频道清单

> 所有频道默认开启；后台可逐频道关闭（见下）。除内置频道外，代码中可写任意自定义频道，未配置开关的频道默认开启。

### 虫洞联盟频道

| 频道名 | 用途 |
|--------|------|
| `wormhole_join` | 虫洞上报/加入联盟 |
| `wormhole_check` | 虫洞每日检测 |
| `wormhole_model` | 虫洞模型操作 |
| `wormhole_display` | 联盟成员列表展示 |

### 友链自动收录频道

| 频道名 | 用途 |
|--------|------|
| `autolink` | 自动收录全流程（抓取/回链/TDK/收录/拦截原因） |

### 安全风控频道

| 频道名 | 用途 |
|--------|------|
| `security_ratelimit` | 频率限制拦截 |
| `security_csrf` | CSRF 校验失败 |
| `security_referer` | Referer 校验失败 |

### 跳转与 API 频道

| 频道名 | 用途 |
|--------|------|
| `go_jump` | 跳转请求（go.php） |
| `api_5118` | 5118 权重 API 调用 |
| `api_tdk` | TDK 抓取 API |
| `open_api` | 开放 API（open/*）发布/编辑/删除等调用审计 |

### 后台管理审计频道

| 频道名 | 用途 |
|--------|------|
| `admin_auth` | 后台登录/登出/改密 |
| `admin_site` | 站点增删改审 |
| `admin_category` | 分类增删改排序 |
| `admin_feature` | 推荐位设置 |
| `admin_blacklist` | 黑名单管理 |
| `admin_setting` | 系统设置/插件启停修改 |
| `admin_wormhole` | 虫洞管理操作 |
| `admin_api_key` | API Key 管理 |

### 系统与数据库频道

| 频道名 | 用途 |
|--------|------|
| `database_error` | SQL 执行失败（含 SQL 与参数） |
| `plugin_error` | 插件运行错误 |
| `plugin_info` | 插件启用建表/加字段/写配置等 |
| `plugin_uninstall` | 插件卸载记录 |
| `search_fallback` | 搜索回退（FULLTEXT 不可用时 LIKE） |

## 开关配置（后台界面）

日志开关配置项存储在数据库 `settings` 表中，后台已提供完整界面：

**后台 → 基础设置 → 基础信息 → 日志设置**

- **日志总开关**（`log_global`）：取消勾选即关闭所有日志写入；关闭时不改动各频道开关状态，重新开启后按原设置生效
- **频道独立开关**（`log_{channel}`）：总开关勾选后自动展开各频道列表（按功能分组），可逐项单独开启/关闭，默认全部开启
- 保存后即时生效，无需重启服务

## 开关优先级与直接 SQL 修改

```
log_global = 0     → 关闭所有日志（无视频道开关）
log_global = 1     → 按各频道开关控制
log_{channel} = 1  → 开启该频道
log_{channel} = 0  → 关闭该频道
```

如需绕过后台直接修改（例如脚本批量调整）：

```sql
UPDATE settings SET value = '1' WHERE key = 'log_global';   -- 开启所有日志
UPDATE settings SET value = '0' WHERE key = 'log_global';   -- 关闭所有日志（最高优先级）

UPDATE settings SET value = '0' WHERE key = 'log_autolink'; -- 单独关闭自动收录日志
UPDATE settings SET value = '1' WHERE key = 'log_wormhole_join';
```

## 如何在代码中写日志

### 单条日志

```php
Logger::log('autolink', "自动收录成功：{$domain}（ID={$result['id']}）IP={$clientIp}");
```

### 批量日志

```php
$logs = [
    "开始检测：{$domain}",
    "TDK 抓取成功：{$title}",
    "回链验证通过",
    "收录成功：ID={$siteId}",
];
Logger::logs('autolink', $logs);
```

### 判断日志是否开启

```php
if (Logger::isEnabled('autolink')) {
    // 只有开启时才执行昂贵的日志准备工作
    $detail = $this->buildDetailedLog();
    Logger::log('autolink', $detail);
}
```

### 获取日志文件路径

```php
$todayLog = Logger::getLogFile('autolink');                  // 今天的日志
$yesterdayLog = Logger::getLogFile('autolink', '20260804');  // 指定日期
```

### 自定义频道

任意字符串均可作为频道名，文件即 `data/logs/YYYYMMDD/{频道名}.log`：

```php
Logger::log('my_plugin', '执行了某操作，结果=' . $result);
```

## 日志文件格式

```
[HH:MM:SS] 日志内容
```

示例：

```
[09:23:15] [added] 自动收录成功：bbs.ikunwl.com（ID=123）IP=192.168.1.1
[09:24:01] [no_backlink] 未检测到回链：example.com（首页不含 site.ikunwl.com） IP=192.168.1.1
[09:25:33] 自动收录异常：cURL 错误 28：Connection timed out
```

## 查看日志的方法

### 方法 1：服务器直接查看

```bash
# 查看今天的自动收录日志
cat data/logs/$(date +%Y%m%d)/autolink.log

# 实时追踪
tail -f data/logs/$(date +%Y%m%d)/autolink.log

# 查看昨天的日志
cat data/logs/$(date -d yesterday +%Y%m%d)/autolink.log
```

### 方法 2：后台提示

后台仪表盘的「登录日志」卡片会提示登录审计日志位置（`data/logs/YYYYMMDD/admin_auth.log`），并链接到在线文档。

### 方法 3：PHP 脚本查看（调试用）

```php
<?php
// view-log.php（开发调试用，生产环境建议加访问控制）
$channel = $_GET['channel'] ?? 'autolink';
$date = $_GET['date'] ?? date('Ymd');
$path = __DIR__ . '/data/logs/' . $date . '/' . $channel . '.log';

if (file_exists($path)) {
    echo "<pre>";
    echo htmlspecialchars(file_get_contents($path));
    echo "</pre>";
} else {
    echo "暂无日志";
}
```

## 日志清理策略

日志文件会持续增长，建议设置定时清理。

### 保留 30 天日志

```bash
# 添加 cron 任务（每天凌晨执行）
0 3 * * * find /www/wwwroot/site.ikunwl.com/data/logs -type d -mtime +30 -exec rm -rf {} + 2>/dev/null
```

### 保留 7 天日志（更激进）

```bash
0 3 * * * find /www/wwwroot/site.ikunwl.com/data/logs -type d -mtime +7 -exec rm -rf {} + 2>/dev/null
```

### 日志大小监控

```bash
# 查看日志目录总大小
du -sh /www/wwwroot/site.ikunwl.com/data/logs

# 查看各频道日志大小
du -sh /www/wwwroot/site.ikunwl.com/data/logs/*/*
```

---

## 常见问题

### Q: 自动收录没有日志怎么办？

1. 检查后台「基础设置 - 基础信息 - 日志设置」中总开关与 `autolink` 频道是否开启
2. 检查 `data/logs/` 目录是否有写权限
3. 从外站点击友链进入导航站，等待 2-3 秒后再查看日志
4. 确认 auto-link 插件已启动，且主题 `footer.php` 调用了 `Plugin::hook('after_footer')`（检测 JS 由插件注入）

### Q: 日志太多占磁盘怎么办？

设置 cron 自动清理（见上方「日志清理策略」），或到后台关闭非必要频道的日志开关。

### Q: 如何单独关闭某个频道的日志？

后台「基础设置 - 基础信息 - 日志设置」展开频道列表，取消勾选对应频道后保存即可，即时生效，无需重启服务。
