<?php
/**
 * 虫洞传送跳转页面
 * GET /wormhole/teleport.php?ref=来源URL
 * 
 * 此页面处理虫洞传送请求：
 * 1. 接收来源 URL (ref 参数)
 * 2. 上报来源站点的域名和 TDK 信息
 * 3. 随机跳转到联盟成员站点
 * 4. 等待 2 秒后执行跳转（确保上报完成）
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/BlacklistModel.php';

// 获取来源 URL（需要解码）
$ref = isset($_GET['ref']) ? urldecode($_GET['ref']) : '';

// 获取随机成员站点
$wormhole = new WormholeModel();
$member = $wormhole->getRandomMember();
$targetUrl = $member['url'] ?? '';
$memberName = $member['name'] ?? '';

// 确保跳转 URL 有协议头且不为空
$isValidTarget = false;
if (!empty($targetUrl)) {
    if (!preg_match('/^https?:\/\//i', $targetUrl)) {
        $targetUrl = 'https://' . ltrim($targetUrl, '/');
    }
    $targetHost = parse_url($targetUrl, PHP_URL_HOST) ?: '';
    $isValidTarget = !empty($targetHost) && $targetHost !== 'about.blank';
}

// 执行上报：通过 API 端点上报来源站
$domain = '';
if (!empty($ref)) {
    $domain = parse_url($ref, PHP_URL_HOST);
    if ($domain) {
        // 全局 IP 屏蔽：开启后拒绝所有纯 IP 地址的自动收录
        if (setting('block_all_ip', '0') === '1' && filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            $domain = ''; // 清空，跳过后续自动收录逻辑
        }
        // 黑名单拦截：IP 或域名命中则跳过自动加入逻辑
        if ($domain) {
            $blacklist = new BlacklistModel();
            if ($blacklist->isBlocked(Security::getClientIP(), $domain)) {
                $domain = ''; // 清空，跳过后续自动收录逻辑
            }
        }
    }
    if ($domain) {
        // 调用 api_wormhole_join 逻辑：查找/创建站点，加入联盟
        // 由于 teleport.php 和 api/index.php 在同一项目，直接复用逻辑
        // 查找站点
        $tbl = Database::table('sites');
        // 安全：转义 LIKE 通配符，防止 SQL LIKE 注入
        $escapedDomain = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $domain);
        $site = Database::queryOne(
            "SELECT id, wormhole_status FROM {$tbl} WHERE url LIKE ? ESCAPE '\\\\' AND status IN ('published', 'pending') LIMIT 1",
            ['%' . $escapedDomain . '%']
        );
        
        if (!$site) {
            // 站点不存在，创建新站点并加入联盟
            $siteModel = new SiteModel();
            $catModel = new CategoryModel();
            $matchedCatId = $catModel->matchCategoryByKeywords($domain);
            if (!$matchedCatId) {
                $fallbackCat = Database::queryOne("SELECT id FROM " . Database::table('categories') . " WHERE is_show = 1 ORDER BY sort_order LIMIT 1");
                $matchedCatId = $fallbackCat ? (int)$fallbackCat['id'] : 1;
            }
            
            $wormholeNeedReview = setting('wormhole_need_review', '0') === '1';
            $siteId = $siteModel->create([
                'name' => $domain,
                'url' => 'https://' . $domain,
                'category_id' => $matchedCatId,
                'status' => $wormholeNeedReview ? 'pending' : 'published',
                'submit_ip' => Security::getClientIP(),
            ]);
            
            if ($siteId > 0 && !$wormholeNeedReview) {
                $wormhole = new WormholeModel();
                $wormhole->joinAuto((int)$siteId, $domain);
            }
        } else {
            // 站点已存在，更新检测状态
            if ($site['wormhole_status'] === 'auto') {
                $wormhole = new WormholeModel();
                $wormhole->markCheckPass((int)$site['id']);
            }
        }
    }
}

// 如果有有效目标 URL，显示跳转页面
if ($isValidTarget) {
    $domain = parse_url($targetUrl, PHP_URL_HOST);
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>虫洞传送中...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .wormhole {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            animation: pulse 2s ease-in-out infinite;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }
        h1 { font-size: 28px; margin-bottom: 15px; }
        p { font-size: 16px; opacity: 0.9; margin-bottom: 10px; }
        .target { 
            background: rgba(255,255,255,0.15); 
            padding: 15px 30px; 
            border-radius: 10px; 
            margin-top: 20px;
            max-width: 90%;
            word-break: break-all;
        }
        .target span { font-weight: bold; }
        .progress {
            width: 200px;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            margin-top: 30px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: #fff;
            animation: progress 2s linear forwards;
        }
        @keyframes progress {
            from { width: 0%; }
            to { width: 100%; }
        }
        .manual-link {
            margin-top: 20px;
            opacity: 0.8;
        }
        .manual-link a {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="wormhole">🌌</div>
    <h1>虫洞传送中...</h1>
    <p>正在跳转到联盟成员站点</p>
    <div class="target">
        目标站点：<span><?php echo htmlspecialchars($memberName ?: $domain); ?></span>
    </div>
    <div class="progress">
        <div class="progress-bar"></div>
    </div>
    <div class="manual-link">
        如果浏览器没有自动跳转，请 <a href="<?php echo htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">点击这里</a>
    </div>
    
    <script>
        // 2 秒后自动跳转
        setTimeout(function() {
            window.location.href = <?php echo json_encode($targetUrl); ?>;
        }, 2000);
        
        // 同时在新窗口打开（实现虫洞传送效果）
        window.open(<?php echo json_encode($targetUrl); ?>, '_blank');
    </script>
</body>
</html>
    <?php
} else {
    // 没有可用成员，显示提示
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>虫洞传送</title>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { font-size: 24px; margin-bottom: 10px; }
        p { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="icon">🌌</div>
    <h1>虫洞联盟暂无成员</h1>
    <p>请先加入虫洞联盟后再试</p>
</body>
</html>
    <?php
}
