# 日志系统使用指南

> 懒人导航内置完整的结构化日志系统，用于运维排查、安全审计和数据分析。

## 目录

1. [日志目录结构](#日志目录结构)
2. [日志频道清单](#日志频道清单)
3. [日志开关配置](#日志开关配置)
4. [如何在代码中写日志](#如何在代码中写日志)
5. [日志文件格式](#日志文件格式)
6. [查看日志的方法](#查看日志的方法)
7. [日志清理策略](#日志清理策略)

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

### 友链自动收录频道

| 频道名 | 用途 | 默认开关 |
|--------|------|----------|
| `autolink` | 友链自动收录全流程 | 开启 |

**日志内容示例：**

```
[09:23:15] [fetch_failed] 抓取失败：http://bbs.ikunwl.com/（超时5秒） IP=192.168.1.1
[09:23:16] [no_backlink] 未检测到回链：bbs.ikunwl.com（首页不含 site.ikunwl.com） IP=192.168.1.1
[09:23:17] [banned_word] 检测到违禁词"博彩"：bbs.ikunwl.com IP=192.168.1.1
[09:23:18] [rate_limited] 频率限制：bbs.ikunwl.com 6小时内已处理3次 IP=192.168.1.1
[09:23:19] [blacklisted] 黑名单域名：bbs.ikunwl.com IP=192.168.1.1
[09:23:20] [added] 自动收录成功：bbs.ikunwl.com（ID=123）IP=192.168.1.1
[09:23:21] [duplicated] 重复域名：bbs.ikunwl.com 已在站点列表中 IP=192.168.1.1
[09:23:22] [invalid_tdk] TDK 抓取失败：bbs.ikunwl.com（标题为空）IP=192.168.1.1
[09:23:23] [no_referer] 无来路（用户在地址栏直接访问） IP=192.168.1.1
[09:23:24] [self] 本站来路（导航站内页跳转） IP=192.168.1.1
[09:23:25] [search_engine] 搜索引擎来路 IP=192.168.1.1
```

**action 状态码对照表：**

| action | 含义 | 是否记录日志 |
|--------|------|-------------|
| `no_referer` | 无 HTTP Referer | 否（静默跳过） |
| `self` | 本站来路 | 否（静默跳过） |
| `search_engine` | 搜索引擎来路 | 否（静默跳过） |
| `disabled` | 功能未开启 | 否（静默跳过） |
| `fetch_failed` | 抓取对方首页失败 | 是 |
| `no_backlink` | 对方未挂回链 | 是 |
| `banned_word` | 含违禁词 | 是 |
| `rate_limited` | 频率限制 | 是 |
| `blacklisted` | 黑名单域名 | 是 |
| `added` | 收录成功 | 是 |
| `duplicated` | 域名已存在 | 是 |
| `invalid_tdk` | TDK 无效 | 是 |
| `internal` | 内网地址 | 是 |
| `ip_blocked` | IP 被全局屏蔽 | 是 |

### 虫洞联盟频道

| 频道名 | 用途 | 默认开关 |
|--------|------|----------|
| `wormhole_join` | 虫洞联盟加入上报 | 开启 |
| `wormhole_check` | 虫洞联盟每日检测 | 开启 |
| `wormhole_model` | 虫洞模型操作 | 开启 |
| `wormhole_tdk` | TDK 自动采集 | 开启 |

### 安全风控频道

| 频道名 | 用途 | 默认开关 |
|--------|------|----------|
| `security_ratelimit` | 频率限制拦截 | 开启 |
| `security_csrf` | CSRF 校验失败 | 开启 |
| `security_referer` | Referer 校验失败 | 开启 |

### API 与跳转频道

| 频道名 | 用途 | 默认开关 |
|--------|------|----------|
| `go_jump` | 跳转请求（go.php） | 开启 |
| `api_5118` | 5118 权重 API 调用 | 开启 |
| `api_tdk` | TDK 抓取 API | 开启 |
| `api_error` | API 错误与异常 | 开启 |

### 后台管理审计频道

| 频道名 | 用途 | 默认开关 |
|--------|------|----------|
| `admin_auth` | 后台登录/登出/改密 | 开启 |
| `admin_site` | 站点增删改审 | 开启 |
| `admin_category` | 分类增删改排序 | 开启 |
| `admin_feature` | 推荐位设置 | 开启 |
| `admin_blacklist` | 黑名单管理 | 开启 |
| `admin_setting` | 系统设置修改 | 开启 |
| `admin_wormhole` | 虫洞管理操作 | 开启 |

### 数据库频道

| 频道名 | 用途 | 默认开关 |
|--------|------|----------|
| `database_error` | SQL 执行失败 | 开启 |

## 日志开关配置

日志开关存储在数据库 `settings` 表中，后台暂时没有 UI 界面，需要直接修改数据库或在代码中设置。

### 全局总开关

```sql
UPDATE settings SET value = '1' WHERE key = 'log_global';   -- 开启所有日志
UPDATE settings SET value = '0' WHERE key = 'log_global';   -- 关闭所有日志（最高优先级）
```

### 频道独立开关

```sql
-- 友链自动收录日志
UPDATE settings SET value = '1' WHERE key = 'log_autolink';

-- 虫洞联盟日志
UPDATE settings SET value = '1' WHERE key = 'log_wormhole_join';
UPDATE settings SET value = '1' WHERE key = 'log_wormhole_check';

-- 跳转日志
UPDATE settings SET value = '1' WHERE key = 'log_go_jump';

-- 后台审计日志
UPDATE settings SET value = '1' WHERE key = 'log_admin_site';
UPDATE settings SET value = '1' WHERE key = 'log_admin_auth';

-- 数据库错误日志
UPDATE settings SET value = '1' WHERE key = 'log_database_error';
```

### 开关优先级

```
log_global = 0     → 关闭所有日志（无视频道开关）
log_global = 1     → 按各频道开关控制
log_{channel} = 1  → 开启该频道
log_{channel} = 0  → 关闭该频道
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
    // 只有在日志开启时才执行昂贵的日志准备工作
    $detail = $this->buildDetailedLog();
    Logger::log('autolink', $detail);
}
```

### 获取日志文件路径

```php
$todayLog = Logger::getLogFile('autolink');          // 今天的日志
$yesterdayLog = Logger::getLogFile('autolink', '20260804');  // 指定日期
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

### 方法 2：通过 Web 端（如果开启了 admin 日志查看功能）

后台管理 → 基础设置 → 友链自动收录 → 查看收录日志

### 方法 3：PHP 脚本查看

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

日志文件会持续增长，建议设置定时清理：

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

1. 检查 `log_global` 是否被设为 `0`
2. 检查 `log_autolink` 是否被设为 `0`
3. 检查 `data/logs/` 目录是否有写权限
4. 从外站点击友链进入导航站，等待 2-3 秒后再查看日志
5. 确保主题的 footer.php 已包含自动收录 JS 代码（参考 `theme-dev.md`）

### Q: 日志太多占磁盘怎么办？

设置 cron 自动清理（见上方"日志清理策略"），或关闭非必要频道的日志开关。

### Q: 如何单独关闭某个频道的日志？

```sql
UPDATE settings SET value = '0' WHERE key = 'log_autolink';
```

不需要重启服务，即时生效。
