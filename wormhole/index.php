<?php
/**
 * 虫洞联盟 - 独立入口页
 * 不依赖模板系统，可直接绑定到子目录或独立域名
 */
require_once __DIR__ . '/../core/bootstrap.php';

$wormhole = new WormholeModel();
$members = $wormhole->getMembers('all');
$stats = $wormhole->getStats();

$settingsModel = new SettingsModel();
$siteUrl = getSiteUrl();
$siteName = $settingsModel->get('site_name', '懒人导航');

$apiUrl = $siteUrl . '/api/';
$jsEmbed = '<script src="' . $siteUrl . '/api/index.php?endpoint=wormhole.js" async></script>';

// 生成 A-tag 代码（直接跳转到传送页面，传递来源URL）
$linkCode = '<a href="' . $siteUrl . '/wormhole/teleport.php?ref=贵站网址" target="_blank">🌌 神秘虫洞传送</a>';

$seoTitle = '神秘虫洞传送 万站同盟 流量互传 | ' . $siteName;
$seoDesc = '虫洞联盟是一个站点互推机制，加入后你的站点会随机出现在所有联盟成员的网站上，实现流量互传。';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= Security::eAttr($seoDesc) ?>">
<title><?= Security::e($seoTitle) ?></title>
<link rel="stylesheet" href="<?= Security::safeUrl($siteUrl) ?>/assets/css/tabler-icons.css">
<link rel="stylesheet" href="<?= Security::safeUrl($siteUrl) ?>/wormhole/css/wormhole.css">
</head>
<body>

<!-- Header -->
<div class="header">
  <div class="container">
    <h1>🌌 虫洞联盟</h1>
    <p>万站同盟 · 流量互传 · 随机传送</p>
  </div>
</div>

<div class="container">
  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="number"><?= (int)$stats['total_count'] ?></div>
      <div class="label">联盟成员</div>
    </div>
    <div class="stat-card">
      <div class="number" style="color:#059669"><?= (int)$stats['manual_count'] ?></div>
      <div class="label">站长推荐</div>
    </div>
    <div class="stat-card">
      <div class="number" style="color:#d97706"><?= (int)$stats['auto_count'] ?></div>
      <div class="label">自动加入</div>
    </div>
  </div>

  <!-- Teleport -->
  <div class="teleport">
    <h2>神秘虫洞传送</h2>
    <p>点击下方按钮，随机传送到一个联盟成员站点</p>
    <a href="javascript:void(0)" class="teleport-btn" onclick="doTeleport(this)">
      🚀 立即传送
    </a>
  </div>

  <!-- How to Join -->
  <div class="card">
    <div class="card-title"><i class="ti ti-bulb"></i>如何加入虫洞联盟</div>
    <p style="font-size:14px;color:#666;margin-bottom:20px;line-height:1.6">
      虫洞联盟是一个站点互推机制，加入后你的站点会随机出现在所有联盟成员的网站上，实现流量互传。
      以下任意一种方式首次触发时，系统都会通过 Referer 识别你的域名并自动加入联盟（需你的站点已在本导航收录）。
    </p>

    <div class="method">
      <div class="method-header">
        <span class="method-num">1</span>
        <span class="method-title">JS 嵌入（推荐）</span>
      </div>
      <p class="method-desc">将以下代码放到你的网站任意位置（如页脚），加载后会在页面底部展示 12 个随机联盟站点，点击即可访问。首次加载时自动上报你的域名并加入联盟（需你的站点已在本导航收录）</p>
      <div class="code-block"><?= Security::e($jsEmbed) ?></div>
    </div>

    <div class="method">
      <div class="method-header">
        <span class="method-num">2</span>
        <span class="method-title">虫洞传送友链</span>
      </div>
      <p class="method-desc">添加友链按钮，用户首次点击时自动加入联盟，并随机传送到任一联盟成员站点</p>
      <div class="code-block"><?= Security::e($linkCode) ?></div>
    </div>

    <div class="rules" style="margin-top:16px">
      <i class="ti ti-info-circle"></i>
      <strong>规则说明：</strong>
      JS 嵌入方式首次加载时，脚本会自动上报来源域名并加入联盟；传送友链方式需用户首次点击，系统通过浏览器 Referer 识别来源域名并加入联盟（需你的站点已在本导航收录）。
      加入后系统每天自动检测你是否仍在网站挂载联盟代码，连续 3 次未检测到将自动移出联盟。
      手动勾选的成员不受检测限制。
    </div>
  </div>

  <!-- Members -->
  <div class="card">
    <div class="card-title"><i class="ti ti-users"></i>联盟成员</div>
    <?php if (empty($members)): ?>
    <div class="empty">
      <i class="ti ti-world-question" style="font-size:48px;color:#ccc;margin-bottom:12px;display:block"></i>
      暂无联盟成员，快来加入吧！
    </div>
    <?php else: ?>
    <div class="member-grid">
      <?php foreach ($members as $m): ?>
      <a href="<?= Security::safeUrl($m['url']) ?>" target="_blank" rel="nofollow" class="member-card">
        <div class="member-name"><?= Security::e($m['name']) ?></div>
        <div class="member-domain"><?= Security::e(getDisplayDomain($m['url'])) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Footer -->
<div class="footer">
  <div class="container">
    <p>© <?= date('Y') ?> <?= Security::e($siteName) ?> · 虫洞联盟</p>
    <p style="margin-top:6px"><a href="<?= Security::safeUrl($siteUrl) ?>">返回导航首页</a></p>
  </div>
</div>

<script>
var __wormholeRef = window.location.href;
function doTeleport(btn) {
  btn.textContent = '🔄 传送中...';
  btn.style.pointerEvents = 'none';
  var apiUrl = '<?= Security::eAttr($siteUrl . '/api/index.php?endpoint=wormhole-teleport') ?>';
  // 传递当前页面URL作为ref（用于来源上报）
  apiUrl += (apiUrl.indexOf('?') > -1 ? '&' : '?') + 'ref=' + encodeURIComponent(__wormholeRef);
  fetch(apiUrl)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success && data.url) {
        // 安全：仅允许 http/https 协议跳转，防止 javascript: 伪协议注入
        if (/^https?:\/\//i.test(data.url)) {
          window.location.href = data.url;
        } else {
          alert('传送目标地址无效');
        }
      } else {
        alert('传送失败：暂无可用站点');
        btn.textContent = '🚀 立即传送';
        btn.style.pointerEvents = 'auto';
      }
    })
    .catch(function() {
      alert('传送失败，请重试');
      btn.textContent = '🚀 立即传送';
      btn.style.pointerEvents = 'auto';
    });
}
</script>

</body>
</html>
