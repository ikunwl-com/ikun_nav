<?php
/**
 * Go 跳转中间页
 * 功能：记录点击次数 + 展示跳转过渡页 + 自动跳转目标站
 * 用法：/go.php?url=https://example.com&id=123
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/Database.php'; // 确保 Database 类已加载

// 获取参数
$siteId = (int)($_GET['id'] ?? 0);

// 参数校验
if ($siteId <= 0) {
    Logger::log('go_jump', "[跳转拒绝] 参数缺失：id={$siteId}，来源IP=" . Security::getClientIP());
    header('Location: /');
    exit;
}

// 验证站点存在
$siteModel = new SiteModel();
$site = $siteModel->getSite($siteId);
if (!$site || $site['status'] !== 'published') {
    Logger::log('go_jump', "[跳转拒绝] 站点不存在或未发布：id={$siteId}");
    header('Location: /');
    exit;
}

// 安全：始终使用数据库中的站点URL，忽略用户传入的url参数（防开放重定向）
$rawUrl = $site['url'] ?? '';
if (empty($rawUrl)) {
    Logger::log('go_jump', "[跳转拒绝] 站点URL为空：id={$siteId}");
    header('Location: /');
    exit;
}

// 注意：点击计数由前端 /api/click 完成，go.php 只负责展示，不重复+1

// 处理目标 URL
if (!preg_match('/^https?:\/\//i', $rawUrl)) {
    $rawUrl = 'https://' . $rawUrl;
}

// XSS 安全过滤（只允许 http/https 协议）
$parsed = parse_url($rawUrl);
if (!in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true)) {
    header('Location: /');
    exit;
}

// 防 SSRF：禁止内网地址跳转
$host = $parsed['host'] ?? '';
if (Security::isInternalHost($host)) {
    header('Location: /');
    exit;
}

$targetUrl = $rawUrl;

// 获取展示信息
$siteName = htmlspecialchars($site['name'] ?? '目标网站');
$siteDomain = htmlspecialchars(parseDomain($site['url'] ?? ''));
$totalClicks = (int)($site['clicks'] ?? 0); // 数据库中的当前值（点击由前端 /api/click 计数）
$firstChar = htmlspecialchars(mb_substr($site['name'] ?? '?', 0, 1), ENT_QUOTES, 'UTF-8');

header('Referrer-Policy: no-referrer');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $siteName ?> - 正在跳转</title>
    <link rel="stylesheet" href="/templates/default/css/common.css">
    <style>
        .go-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            padding: 20px;
        }
        .go-card {
            background: #fff;
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .go-icon {
            width: 72px;
            height: 72px;
            background: #6b5ce7;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: #fff;
            font-size: 32px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(107,92,231,0.3);
        }
        .go-title {
            font-size: 22px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 6px;
        }
        .go-domain {
            color: #999;
            font-size: 14px;
            margin: 0 0 32px;
            word-break: break-all;
        }
        .go-clicks {
            color: #888;
            font-size: 14px;
            margin: 0 0 32px;
        }
        .go-clicks strong {
            color: #6b5ce7;
            font-weight: 600;
        }
        .go-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .go-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .go-btn-primary {
            background: #6b5ce7;
            color: #fff;
        }
        .go-btn-primary:hover {
            background: #5a4bd0;
        }
        .go-btn-secondary {
            background: #f0f0f0;
            color: #555;
        }
        .go-btn-secondary:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="go-page">
        <div class="go-card">
            <div class="go-icon"><?= $firstChar ?></div>
            <h1 class="go-title"><?= $siteName ?></h1>
            <p class="go-domain"><?= $siteDomain ?></p>

            <p class="go-clicks">
                已有 <strong><?= number_format($totalClicks) ?></strong> 人访问过该站点
            </p>

            <div class="go-actions">
                <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>" class="go-btn go-btn-primary" target="_blank" rel="noopener nofollow">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    前往网站
                </a>
                <button type="button" class="go-btn go-btn-secondary" onclick="window.close()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    关闭本页
                </button>
            </div>
        </div>
    </div>
</body>
</html>
