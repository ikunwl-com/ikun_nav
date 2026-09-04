<?php
/**
 * 后台公共引导文件 - 所有后台页面必须引入
 */
require_once __DIR__ . '/../core/bootstrap.php';

// 未安装跳转
if (!isInstalled()) {
    redirect('/install/');
}

// 开启会话
Security::initSession();

// 检查登录状态
if (!isset($_SESSION['admin_id'])) {
    // 记住当前页面，登录后跳回
    $current = $_SERVER['REQUEST_URI'] ?? '/admin/';
    if (strpos($current, 'login.php') === false && strpos($current, 'logout.php') === false) {
        $_SESSION['redirect_after_login'] = $current;
    }
    redirect('/admin/login.php');
}

// 会话过期检查（使用后台设置的 session_timeout）
$sessionTimeout = (int)setting('session_timeout', 3600);
if (Security::isSessionExpired($sessionTimeout)) {
    session_destroy();
    redirect('/admin/login.php?msg=timeout');
}

// 安全：每次后台请求刷新CSRF Token（滑动窗口）
if (empty($_SESSION['csrf_token'])) {
    Security::generateCSRFToken();
}

// 当前管理员信息
$adminId = (int)$_SESSION['admin_id'];
$adminUsername = $_SESSION['admin_username'] ?? '';

// 页面标识（用于侧边栏高亮）
global $currentPage;
$currentPage = $currentPage ?? '';

// 版本检测改为异步 AJAX（页面加载后由 JS 请求 /admin/update_api.php）
// 这里只从 Session 读取上一次检测结果，避免阻塞页面加载
$hasNewVersion = !empty($_SESSION['has_new_version']);

/**
 * 后台HTML头部
 */
function adminHeader($title = '', $extraCss = '') {
    global $adminUsername, $hasNewVersion;
    $siteName = setting('site_name') ?: '懒人导航';
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    // 安全：对 $extraCss 做基本过滤（只允许 CSS 内容，防止标签注入）
    $extraCss = $extraCss !== '' ? '<style>' . $extraCss . '</style>' : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= Security::e($title) ?> - <?= Security::e($siteName) ?> 管理后台</title>
<link rel="stylesheet" href="/assets/css/tabler-icons.css">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<?= $extraCss ?>
</head>
<body>
<div class="admin-layout">
  <!-- 侧边栏 -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <i class="ti ti-compass"></i>
      <span><?= Security::e($siteName) ?></span>
    </div>
    <nav class="sidebar-nav">
      <a href="/admin/" class="nav-item <?= $GLOBALS['currentPage'] === 'dashboard' ? 'active' : '' ?>"><i class="ti ti-dashboard"></i><span>仪表盘</span></a>
      <a href="/admin/sites.php" class="nav-item <?= $GLOBALS['currentPage'] === 'sites' ? 'active' : '' ?>"><i class="ti ti-world"></i><span>站点管理</span></a>
      <a href="/admin/categories.php" class="nav-item <?= $GLOBALS['currentPage'] === 'categories' ? 'active' : '' ?>"><i class="ti ti-category"></i><span>分类管理</span></a>
      <a href="/admin/featured.php" class="nav-item <?= $GLOBALS['currentPage'] === 'featured' ? 'active' : '' ?>"><i class="ti ti-star"></i><span>推荐管理</span></a>
      <a href="/admin/review.php" class="nav-item <?= $GLOBALS['currentPage'] === 'review' ? 'active' : '' ?>"><i class="ti ti-clipboard-check"></i><span>提交审核</span></a>
      <a href="/admin/stats.php" class="nav-item <?= $GLOBALS['currentPage'] === 'stats' ? 'active' : '' ?>"><i class="ti ti-chart-line"></i><span>数据统计</span></a>
      <a href="/admin/settings.php" class="nav-item <?= $GLOBALS['currentPage'] === 'settings' ? 'active' : '' ?>"><i class="ti ti-settings"></i><span>基础设置</span></a>
      <a href="/admin/themes.php" class="nav-item <?= $GLOBALS['currentPage'] === 'themes' ? 'active' : '' ?>"><i class="ti ti-palette"></i><span>主题管理</span></a>
      <a href="/admin/plugins.php" class="nav-item <?= $GLOBALS['currentPage'] === 'plugins' ? 'active' : '' ?>"><i class="ti ti-puzzle"></i><span>插件管理</span></a>
      <a href="/admin/api_keys.php" class="nav-item <?= $GLOBALS['currentPage'] === 'api_keys' ? 'active' : '' ?>"><i class="ti ti-key"></i><span>API 密钥</span></a>
      <?php Plugin::hook('admin_sidebar'); ?>
    </nav>
    <div class="sidebar-footer">
      <a href="/admin/update.php" class="nav-item <?= $GLOBALS['currentPage'] === 'update' ? 'active' : '' ?>">
        <i class="ti ti-refresh"></i>
        <span>程序更新<?php if ($hasNewVersion): ?><span class="nav-dot"></span><?php endif; ?></span>
      </a>
      <a href="/" target="_blank" class="nav-item"><i class="ti ti-external-link"></i><span>访问前台</span></a>
      <form method="POST" action="/admin/logout.php" class="d-flex cursor-pointer" onsubmit="return confirm('确定退出登录？')">
        <input type="hidden" name="csrf_token" value="<?= Security::eAttr($csrfToken) ?>">
        <button type="submit" class="nav-item nav-logout">
          <i class="ti ti-logout"></i><span>退出登录</span>
        </button>
      </form>
    </div>
  </aside>

  <!-- 主内容 -->
  <main class="main-content">
    <header class="topbar">
      <button class="menu-toggle" onclick="document.body.classList.toggle('sidebar-collapsed')"><i class="ti ti-menu-2"></i></button>
      <h1 class="page-title"><?= Security::e($title) ?></h1>
      <div class="topbar-right">
        <span class="admin-info"><i class="ti ti-user"></i> <?= Security::e($adminUsername) ?></span>
      </div>
    </header>
    <div class="content-body">
      <input type="hidden" id="csrfToken" value="<?= Security::eAttr($csrfToken) ?>">
<?php
}

/**
 * 后台HTML底部
 */
function adminFooter($extraJs = '') {
    $csrfToken = $_SESSION['csrf_token'] ?? '';
?>
    </div>
  </main>
</div>
<?= $extraJs ?>
<script>
// 异步版本检测（页面加载后执行，不阻塞渲染）
(function() {
    var lastCheck = parseInt(sessionStorage.getItem('nav_update_last_check') || '0', 10);
    var now = Date.now();
    // 每小时最多检测一次（前端节流，避免频繁请求）
    if (now - lastCheck < 3600000) return;

    var formData = new FormData();
    formData.append('action', 'check');
    formData.append('csrf_token', <?= json_encode($csrfToken) ?>);

    fetch('/admin/update_api.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }).then(function(r) { return r.json(); })
      .then(function(data) {
          sessionStorage.setItem('nav_update_last_check', String(now));
          if (data && data.has_update) {
              // 显示侧边栏红点
              var dot = document.querySelector('.sidebar-footer .nav-dot');
              if (!dot) {
                  var updateLink = document.querySelector('.sidebar-footer a[href="/admin/update.php"] span');
                  if (updateLink) {
                      var newDot = document.createElement('span');
                      newDot.className = 'nav-dot';
                      updateLink.appendChild(newDot);
                  }
              }
          }
      }).catch(function() { /* 静默失败，不影响用户体验 */ });
})();
</script>
<script>
// 移动端适配：表格转卡片（自动写入列名）+ 侧边栏遮罩/点击收起
(function() {
  // 1. 给有表头的表格每个单元格写入 data-label，配合 CSS 在手机端堆叠成卡片
  document.querySelectorAll('.data-table, table.table').forEach(function(table) {
    var thead = table.querySelector('thead');
    if (!thead) return; // 无表头的键值表（如系统信息、Sitemap 状态）本身很窄，保持原样

    var headers = [];
    thead.querySelectorAll('th').forEach(function(th) {
      headers.push((th.textContent || '').trim().replace(/\s+/g, ' '));
    });

    table.querySelectorAll('tbody tr').forEach(function(tr) {
      var tds = tr.querySelectorAll('td');
      // 空状态整行（单个 td 且 colspan 跨多列）标记后原样居中显示
      if (tds.length === 1 && parseInt(tds[0].getAttribute('colspan') || '1', 10) > 1) {
        tds[0].setAttribute('data-empty', '1');
        return;
      }
      var idx = 0;
      var titleFound = false;
      tds.forEach(function(td) {
        var span = parseInt(td.getAttribute('colspan') || '1', 10);
        var header = headers[idx] || '';
        idx += span;

        // 复选框列：卡片右上角浮动，不显示标签
        var check = td.querySelector('input[type="checkbox"]');
        if (check && (td.textContent || '').trim() === '') {
          td.classList.add('td-check');
          return;
        }
        // 操作列：卡片底部按钮行，不显示标签
        var isLast = td === tds[tds.length - 1];
        if (td.classList.contains('actions') || (isLast && td.querySelector('button, form, a.btn'))) {
          td.classList.add('td-actions');
          return;
        }
        // 标题列：第一个有实质文本（且非纯数字）的单元格，整行展示
        var text = (td.textContent || '').trim();
        if (!titleFound && text.length >= 2 && !/^\d+$/.test(text)) {
          td.classList.add('td-title');
          titleFound = true;
        } else {
          td.classList.add('td-field');
          // 长内容（如 URL、长描述）占整行
          if (text.length > 24) td.classList.add('td-wide');
        }
        // 内容包一层，便于布局
        var value = document.createElement('div');
        value.className = 'td-value';
        while (td.firstChild) value.appendChild(td.firstChild);
        td.appendChild(value);
        td.setAttribute('data-label', header);
      });
    });
  });

  // 2. 侧边栏遮罩层
  var backdrop = document.createElement('div');
  backdrop.className = 'sidebar-backdrop';
  document.body.appendChild(backdrop);

  function closeSidebar() {
    document.body.classList.remove('sidebar-collapsed');
  }
  backdrop.addEventListener('click', closeSidebar);

  // 点击侧边栏中的链接后自动收起（退出登录按钮除外）
  document.querySelectorAll('.sidebar a.nav-item').forEach(function(link) {
    link.addEventListener('click', closeSidebar);
  });

  // ESC 键关闭侧边栏
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
  });
})();
</script>
</body>
</html>
<?php
}

/**
 * 后台提示消息
 */
function adminAlert($msg, $type = 'success') {
    $icon = $type === 'success' ? 'ti-circle-check' : ($type === 'error' ? 'ti-alert-circle' : 'ti-info-circle');
    echo '<div class="alert alert-' . $type . '" id="auto-alert" style="transition: opacity 0.5s;"><i class="ti ' . $icon . '"></i> ' . Security::e($msg) . '</div>';
}
