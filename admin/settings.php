<?php
/**
 * 后台基础设置
 */
require_once __DIR__ . '/bootstrap.php';
$currentPage = 'settings';

$settingsModel = new SettingsModel();
$authModel = new AuthModel();

$msg = '';
$msgType = 'success';

// ========== 日志频道分组（基础信息 - 日志设置） ==========
// 每个 key 对应 settings 表中的 log_{channel} 配置，读取方式见 core/Logger.php
$logChannelGroups = [
    '虫洞联盟' => [
        'wormhole_join'    => '虫洞上报 / 加入联盟',
        'wormhole_check'   => '虫洞每日检测',
        'wormhole_model'   => '虫洞模型操作',
        'wormhole_display' => '联盟成员列表展示',
    ],
    '友链自动收录' => [
        'autolink' => '自动收录全流程',
    ],
    '安全风控' => [
        'security_ratelimit' => '频率限制拦截',
        'security_csrf'      => 'CSRF 校验失败',
        'security_referer'   => 'Referer 校验失败',
    ],
    '跳转与 API' => [
        'go_jump'  => '跳转请求（go.php）',
        'api_5118' => '5118 权重 API 调用',
        'api_tdk'  => 'TDK 抓取 API',
        'open_api' => '开放 API（open/*）调用审计',
    ],
    '后台管理审计' => [
        'admin_auth'      => '后台登录 / 登出 / 改密',
        'admin_site'      => '站点增删改审',
        'admin_category'  => '分类增删改排序',
        'admin_feature'   => '推荐位设置',
        'admin_blacklist' => '黑名单管理',
        'admin_setting'   => '系统设置修改',
        'admin_wormhole'  => '虫洞管理操作',
        'admin_api_key'   => 'API Key 管理',
    ],
    '系统与数据库' => [
        'database_error'   => 'SQL 执行失败',
        'plugin_error'     => '插件运行错误',
        'plugin_info'      => '插件安装 / 启用信息',
        'plugin_uninstall' => '插件卸载记录',
        'search_fallback'  => '搜索回退（FULLTEXT 不可用）',
    ],
];
$logChannels = [];
foreach ($logChannelGroups as $channels) {
    foreach ($channels as $ch => $label) {
        $logChannels[] = $ch;
    }
}

// ========== POST 保存设置 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/settings.php?msg=csrf&tab=' . urlencode($_POST['tab'] ?? 'general'));
    }

    $adminId = $_SESSION['admin_id'] ?? '未知';
    $ip = Security::getClientIP();
    $section = $_POST['section'] ?? '';

    switch ($section) {
        case 'general':
            $data = [
                'site_name' => Security::cleanString($_POST['site_name'] ?? '', 100),
                'site_url' => Security::cleanString($_POST['site_url'] ?? '', 200),
                'site_slogan' => Security::cleanString($_POST['site_slogan'] ?? '', 200),
                'seo_title' => Security::cleanString($_POST['seo_title'] ?? '', 100),
                'seo_description' => Security::cleanString($_POST['seo_description'] ?? '', 300),
                'seo_keywords' => Security::cleanString($_POST['seo_keywords'] ?? '', 300),
                'site_footer' => Security::cleanHtml($_POST['site_footer'] ?? '', 2000),
                'api_key_5118' => Security::cleanString($_POST['api_key_5118'] ?? '', 200),
                'default_per_page' => max(6, min(60, Security::int($_POST['default_per_page'] ?? 12))),
                'debug_mode' => isset($_POST['debug_mode']) ? '1' : '0',
                'session_timeout' => max(300, Security::int($_POST['session_timeout'] ?? 3600)),
                'enable_captcha' => isset($_POST['enable_captcha']) ? '1' : '0',
            ];

            // 日志设置：总开关 + 频道独立开关
            // 总开关关闭时不改动各频道开关值（保留原配置，重新开启后仍生效）
            $data['log_global'] = isset($_POST['log_global']) ? '1' : '0';
            if (isset($_POST['log_global'])) {
                foreach ($logChannels as $ch) {
                    $data['log_' . $ch] = isset($_POST['log_' . $ch]) ? '1' : '0';
                }
            }
            $settingsModel->setMany($data);
            Logger::log('admin_setting', "修改基础设置 admin_id={$adminId} IP={$ip}");
            $msg = '基础设置已保存';
            break;


        case 'password':
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $admin = $authModel->getById($adminId);

            if (!$admin || !password_verify($oldPassword, $admin['password_hash'])) {
                $msg = '原密码不正确';
                $msgType = 'error';
                Logger::log('admin_auth', "修改密码失败 admin_id={$adminId} IP={$ip} 原因=原密码不正确");
            } elseif ($newPassword !== $confirmPassword) {
                $msg = '两次输入的新密码不一致';
                $msgType = 'error';
                Logger::log('admin_auth', "修改密码失败 admin_id={$adminId} IP={$ip} 原因=两次输入不一致");
            } elseif (!Security::validatePasswordStrength($newPassword)) {
                $msg = '密码强度不足：至少8位，须包含字母和数字';
                $msgType = 'error';
                Logger::log('admin_auth', "修改密码失败 admin_id={$adminId} IP={$ip} 原因=密码强度不足");
            } else {
                $authModel->updatePassword($adminId, $newPassword);
                $msg = '密码修改成功';
                Logger::log('admin_auth', "修改密码成功 admin_id={$adminId} IP={$ip}");
            }
            break;

        case 'rewrite':
            $rewriteData = [
                'mode' => Security::enum($_POST['rewrite_mode'] ?? 'dynamic', ['dynamic', 'rewrite', 'index'], 'dynamic'),
            ];
            foreach (['home', 'category', 'category_page', 'site', 'search', 'submit', 'wormhole', 'article_list', 'article'] as $key) {
                $rewriteData[$key] = Security::cleanString($_POST['url_format_' . $key] ?? '', 200);
            }
            Rewrite::saveConfig($rewriteData);
            SettingsModel::clearCache();
            Logger::log('admin_setting', "修改伪静态 admin_id={$adminId} IP={$ip} mode={$rewriteData['mode']}");
            $msg = '伪静态设置已保存';
            break;

        case 'sitemap':
            $action = $_POST['sitemap_action'] ?? '';
            $sitemap = new SitemapModel();
            if ($action === 'generate') {
                $result = $sitemap->generate();
                if ($result['success']) {
                    $msg = "Sitemap 已重新生成，共 {$result['url_count']} 个 URL";
                    Logger::log('admin_setting', "手动生成Sitemap admin_id={$adminId} IP={$ip} urls={$result['url_count']}");
                } else {
                    $msg = 'Sitemap 生成失败：' . ($result['error'] ?? '未知错误');
                    $msgType = 'error';
                    Logger::log('admin_setting', "生成Sitemap失败 admin_id={$adminId} IP={$ip} error=" . ($result['error'] ?? ''));
                }
            }
            break;

        case 'autolink':
            $data = [
                'autolink_enable'         => isset($_POST['autolink_enable']) ? '1' : '0',
                'autolink_need_review'    => isset($_POST['autolink_need_review']) ? '1' : '0',
                'autolink_default_category' => (int)($_POST['autolink_default_category'] ?? 0),
                'autolink_banned_words'   => Security::cleanString($_POST['autolink_banned_words'] ?? '', 5000),
            ];
            $settingsModel->setMany($data);
            Logger::log('admin_setting', "修改友链收录设置 admin_id={$adminId} IP={$ip}");
            $msg = '友链收录设置已保存';
            break;

        case 'ad':
            $adPositions = ['site_list_before', 'site_list_after', 'sidebar_top', 'sidebar_bottom', 'before_content', 'after_content'];
            $adData = [];
            foreach ($adPositions as $pos) {
                $adData['plugin_ad_' . $pos] = Security::cleanHtml($_POST['plugin_ad_' . $pos] ?? '', 10000);
            }
            $settingsModel->setMany($adData);
            Logger::log('admin_setting', "修改广告位设置 admin_id={$adminId} IP={$ip}");
            $msg = '广告设置已保存';
            break;

        case 'submit':
            $data = [
                'plugin_submit_enable'           => isset($_POST['plugin_submit_enable'])           ? '1' : '0',
                'plugin_submit_need_review'      => isset($_POST['plugin_submit_need_review'])      ? '1' : '0',
                'plugin_submit_show_weight'      => isset($_POST['plugin_submit_show_weight'])      ? '1' : '0',
                'plugin_submit_default_category' => max(0, Security::int($_POST['plugin_submit_default_category'] ?? 0)),
                'plugin_submit_require_category' => isset($_POST['plugin_submit_require_category']) ? '1' : '0',
                'plugin_submit_rate_limit'       => max(0, Security::int($_POST['plugin_submit_rate_limit'] ?? 5)),
                'plugin_submit_tdk_rate_limit'   => max(0, Security::int($_POST['plugin_submit_tdk_rate_limit'] ?? 10)),
                'plugin_submit_rules'            => Security::cleanHtml($_POST['plugin_submit_rules'] ?? '', 20000),
            ];
            $settingsModel->setMany($data);
            // 同步到插件配置表
            Plugin::setConfig('submit', 'default_category', (string)$data['plugin_submit_default_category']);
            Plugin::setConfig('submit', 'require_category', $data['plugin_submit_require_category']);
            // 保存收录分类列表（逗号分隔的ID）
            $categoryIds = [];
            if (!empty($_POST['plugin_submit_category_ids']) && is_array($_POST['plugin_submit_category_ids'])) {
                foreach ($_POST['plugin_submit_category_ids'] as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) $categoryIds[] = $cid;
                }
            }
            Plugin::setConfig('submit', 'category_ids', implode(',', $categoryIds));
        Logger::log('admin_setting', "修改提交收录设置 admin_id={$adminId} IP={$ip}");
            $msg = '提交收录设置已保存';
            break;

        case 'notify':
            $notifyData = [
                'plugin_notify_smtp_host'   => Security::cleanString($_POST['notify_smtp_host'] ?? '', 200),
                'plugin_notify_smtp_port'   => (string)max(1, min(65535, Security::int($_POST['notify_smtp_port'] ?? 465))),
                'plugin_notify_smtp_user'   => Security::cleanString($_POST['notify_smtp_user'] ?? '', 200),
                'plugin_notify_smtp_pass'   => Security::cleanString($_POST['notify_smtp_pass'] ?? '', 200),
                'plugin_notify_smtp_secure' => Security::enum($_POST['notify_smtp_secure'] ?? 'ssl', ['ssl', 'tls', 'none'], 'ssl'),
                'plugin_notify_from_email'  => Security::cleanString($_POST['notify_from_email'] ?? '', 200),
                'plugin_notify_from_name'   => Security::cleanString($_POST['notify_from_name'] ?? '', 100),
                'plugin_notify_recipient'   => Security::cleanString($_POST['notify_recipient'] ?? '', 500),
                'plugin_notify_on_submit'   => isset($_POST['notify_on_submit']) ? '1' : '0',
                'plugin_notify_on_feedback' => isset($_POST['notify_on_feedback']) ? '1' : '0',
                'plugin_notify_on_approve'  => isset($_POST['notify_on_approve']) ? '1' : '0',
                'plugin_notify_on_reject'   => isset($_POST['notify_on_reject']) ? '1' : '0',
            ];
            $settingsModel->setMany($notifyData);
            Logger::log('admin_setting', "修改邮箱通知设置 admin_id={$adminId} IP={$ip}");
            $msg = '邮箱通知配置已保存';
            break;
    }

    // ========== PRG：保存后跳转 GET，避免刷新重复提交 ==========
    $tab = urlencode($_POST['tab'] ?? 'general');
    if ($msgType === 'success') {
        redirect('/admin/settings.php?tab=' . $tab . '&ok=' . urlencode($msg));
    } else {
        redirect('/admin/settings.php?tab=' . $tab . '&err=' . urlencode($msg));
    }
}

// ========== GET 参数处理（PRG 跳转后的消息显示）==========
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
    $msgType = 'success';
} elseif (isset($_GET['err'])) {
    $msg = Security::cleanString($_GET['err']);
    $msgType = 'error';
} elseif (isset($_GET['msg'])) {
    $getMsg = $_GET['msg'];
    if ($getMsg === 'csrf') {
        $msg = 'CSRF验证失败，请重试';
        $msgType = 'error';
    }
}

// 获取伪静态配置（供 POST 处理使用，Tab 面板已迁移至 rewrite 插件）

$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'general');

adminHeader('基础设置');
if ($msg) { adminAlert($msg, $msgType);
}

?>

<!-- Tab 导航 -->
<div class="settings-tabs">
  <a href="#tab-general" class="settings-tab <?= $activeTab === 'general' ? 'active' : '' ?>" onclick="switchTab('general', this)">基础信息</a>
  <a href="#tab-password" class="settings-tab <?= $activeTab === 'password' ? 'active' : '' ?>" onclick="switchTab('password', this)">修改密码</a>
  <?php Plugin::hook('admin_settings_nav', [$activeTab]); ?>
</div>

<!-- 基础信息 Tab -->
<div id="tab-general" class="tab-panel <?= $activeTab === 'general' ? 'active' : '' ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">基础信息</span></div>
    <form method="POST">
      <input type="hidden" name="tab" value="general">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="general">

      <div class="form-row">
        <div class="form-group">
          <label>站点名称</label>
          <input type="text" class="form-input" name="site_name" value="<?= Security::eAttr(setting('site_name')) ?>" placeholder="虫洞联盟" maxlength="100">
        </div>
        <div class="form-group">
          <label>站点URL <span class="text-xs-dim-2">（必填，影响虫洞联盟代码生成）</span></label>
          <?php $currentUrl = getCurrentSiteUrl(); $siteUrl = setting('site_url'); ?>
          <input type="text" class="form-input" name="site_url" value="<?= Security::eAttr($siteUrl ?: $currentUrl) ?>" placeholder="<?= Security::eAttr($currentUrl) ?>" maxlength="200">
          <div class="form-help">请填写完整URL，如 https://nav.example.com，虫洞联盟代码将使用此地址</div>
        </div>
      </div>

      <div class="form-group">
        <label>站点口号</label>
        <input type="text" class="form-input" name="site_slogan" value="<?= Security::eAttr(setting('site_slogan')) ?>" placeholder="精选优质站点，一个页面搞定日常上网需求" maxlength="200">
      </div>

      <div class="form-group">
        <label>首页标题（SEO Title）</label>
        <input type="text" class="form-input" name="seo_title" value="<?= Security::eAttr(setting('seo_title')) ?>" placeholder="懒人导航 - 精选优质站点" maxlength="100">
        <div class="form-help">显示在浏览器标签和搜索引擎结果中，建议60字以内</div>
      </div>

      <div class="form-group">
        <label>首页描述（SEO Description）</label>
        <textarea class="form-textarea" name="seo_description" maxlength="300"><?= Security::e(setting('seo_description')) ?></textarea>
        <div class="form-help">显示在搜索引擎结果摘要中，建议150字以内</div>
      </div>

      <div class="form-group">
        <label>首页关键词（SEO Keywords，逗号分隔）</label>
        <input type="text" class="form-input" name="seo_keywords" value="<?= Security::eAttr(setting('seo_keywords')) ?>" placeholder="导航站,懒人导航,网站导航" maxlength="300">
        <div class="form-help">多个关键词用英文逗号分隔</div>
      </div>

      <div class="form-row">
        <div class="form-group flex-1" >
          <label>站点底部内容 <span class="text-xs-dim-2">（支持 HTML，可放备案号、版权、统计代码等）</span></label>
          <textarea class="form-textarea font-mono-sm" name="site_footer" rows="5"  placeholder="如：&lt;div&gt;&lt;a href=&quot;/beian/&quot;&gt;京ICP备XXXXXXXX号&lt;/a&gt; | &amp;copy; 2026 懒人导航&lt;/div&gt;"><?= Security::e(setting('site_footer')) ?></textarea>
        </div>
        <div class="form-group flex-1" >
          <label>5118 API Key <span class="text-xs-dim-2">（权重查询）</span></label>
          <input type="text" class="form-input" name="api_key_5118" value="<?= Security::eAttr(setting('api_key_5118')) ?>" placeholder="留空则权重显示为0">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group w-8f77" >
          <label>每页显示数量</label>
          <input type="number" class="form-input" name="default_per_page" value="<?= (int)setting('default_per_page', 12) ?>" min="6" max="60">
        </div>
        <div class="form-group w-8f77" >
          <label>后台登录超时（秒）</label>
          <input type="number" class="form-input" name="session_timeout" value="<?= (int)setting('session_timeout', 3600) ?>" min="300">
          <div class="form-help">默认 3600 秒（1小时），超过此时间无操作将自动退出</div>
        </div>
      </div>

      <div class="form-group">
        <label class="flex-center-gap-10">
          <input type="checkbox" name="enable_captcha" value="1" <?= setting('enable_captcha') === '1' ? 'checked' : '' ?> class="wh-18">
          <span>开启登录验证码</span>
        </label>
      </div>

      <div class="form-group">
        <label class="flex-center-gap-10">
          <input type="checkbox" name="debug_mode" value="1" <?= setting('debug_mode') === '1' ? 'checked' : '' ?> class="wh-18">
          <span><strong>调试模式</strong>（开启后前台显示详细错误信息，生产环境请关闭）</span>
        </label>
      </div>

      <!-- 日志设置（全局总开关 + 频道独立开关） -->
      <div style="margin-top:26px;padding-top:18px;border-top:1px solid #e9ecef;">
        <div class="section-title" style="margin-bottom:12px;"><i class="ti ti-file-text"></i> 日志设置</div>
        <div class="form-group">
          <label class="flex-center-gap-10">
            <input type="checkbox" name="log_global" value="1" id="logGlobal"
                   <?= setting('log_global', '1') === '1' ? 'checked' : '' ?>
                   class="wh-18" onchange="toggleLogChannels(this)">
            <span><strong>日志总开关</strong>（关闭后所有日志停止记录；开启后自动展开下方频道开关，可逐项单独开启/关闭）</span>
          </label>
          <div class="form-help">对应配置 <code>log_global</code>。日志按天分目录写入 <code>data/logs/YYYYMMDD/{channel}.log</code>，频道开关对应 <code>log_{channel}</code> 配置，默认全部开启。</div>
        </div>

        <div id="log-channels" <?= setting('log_global', '1') === '1' ? '' : 'style="display:none;"' ?>>
          <?php foreach ($logChannelGroups as $groupTitle => $channels): ?>
          <div style="margin:14px 0 8px;font-size:13px;font-weight:600;color:#495057;"><?= Security::e($groupTitle) ?></div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px;">
            <?php foreach ($channels as $chKey => $chLabel): ?>
            <label class="log-channel-item" style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;cursor:pointer;">
              <input type="checkbox" name="log_<?= $chKey ?>" value="1"
                     <?= setting('log_' . $chKey, '1') === '1' ? 'checked' : '' ?>
                     style="width:16px;height:16px;cursor:pointer;flex-shrink:0;">
              <span style="font-family:ui-monospace,Consolas,monospace;font-size:13px;white-space:nowrap;"><?= Security::e($chKey) ?></span>
              <span style="color:#868e96;font-size:12px;line-height:1.4;"><?= Security::e($chLabel) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <div class="form-help" style="margin-top:12px;">提示：关闭总开关时不会清空各频道开关状态，再次开启后按原频道设置生效；如需查看日志内容可直接打开对应 <code>data/logs/</code> 目录文件。</div>
        </div>
      </div>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存基础设置</button>
      </div>
    </form>
  </div>
</div>

<!-- 伪静态设置和网站地图 Tab 已迁移至 rewrite/sitemap 插件，由 admin_settings_tabs 钩子注入 -->
<?php Plugin::hook('admin_settings_tabs', [$activeTab]); ?>

<!-- 修改密码 Tab -->
<div id="tab-password" class="tab-panel <?= $activeTab === 'password' ? 'active' : '' ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">修改密码</span></div>
    <form method="POST">
      <input type="hidden" name="tab" value="password">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="section" value="password">

      <div class="form-group">
        <label>原密码</label>
        <input type="password" class="form-input" name="old_password" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>新密码</label>
          <input type="password" class="form-input" name="new_password" required>
          <div class="form-help">至少8位，须包含字母和数字</div>
        </div>
        <div class="form-group">
          <label>确认新密码</label>
          <input type="password" class="form-input" name="confirm_password" required>
        </div>
      </div>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-key"></i> 修改密码</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(tabId, el) {
  // 隐藏所有 panel
  document.querySelectorAll('.tab-panel').forEach(function(p) {
    p.classList.remove('active');
  });
  // 显示目标 panel
  document.getElementById('tab-' + tabId).classList.add('active');
  // 更新 tab 样式
  document.querySelectorAll('.settings-tab').forEach(function(t) {
    t.classList.remove('active');
  });
  if (el) el.classList.add('active');
  // 同步更新 URL hash
  if (history.replaceState) {
    history.replaceState(null, null, '#tab-' + tabId);
  }
  // 切换 Tab 时立即隐藏提示弹窗
  var alertEl = document.getElementById('auto-alert');
  if (alertEl) {
    alertEl.style.opacity = '0';
    setTimeout(function() { alertEl.style.display = 'none'; }, 500);
  }
}

function copyCode(id, btn) {
  var code = document.getElementById(id);
  if (!code) return;
  var text = code.innerText || code.textContent;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function() {
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="ti ti-check"></i> 已复制';
      setTimeout(function() { btn.innerHTML = orig; }, 2000);
    });
  } else {
    // Fallback
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-check"></i> 已复制';
    setTimeout(function() { btn.innerHTML = orig; }, 2000);
  }
}

// 日志总开关：开启时展开频道独立开关列表，关闭时收起
function toggleLogChannels(el) {
  var box = document.getElementById('log-channels');
  if (!box) return;
  box.style.display = el.checked ? '' : 'none';
}

function toggleRewriteFields(el) {
  var fields = document.getElementById('rewrite-url-fields');
  var rules = document.getElementById('rewrite-server-rules');
  if (!fields) return;
  if (el.value === 'dynamic') {
    fields.style.display = 'none';
    if (rules) rules.style.display = 'none';
  } else {
    fields.style.display = '';
    if (rules) rules.style.display = '';
  }
}

// 页面加载时初始化伪静态设置项的显示状态
(function() {
  var mode = document.querySelector('input[name="rewrite_mode"]:checked');
  if (mode) toggleRewriteFields(mode);

  // 初始化日志频道开关的显示状态
  var logGlobal = document.getElementById('logGlobal');
  if (logGlobal) toggleLogChannels(logGlobal);

  // 自动隐藏提示弹窗（3秒后淡出，切换Tab时立即隐藏）
  function hideAlert() {
    var el = document.getElementById('auto-alert');
    if (!el) return;
    el.style.opacity = '0';
    setTimeout(function() { el.style.display = 'none'; }, 500);
  }
  setTimeout(hideAlert, 3000);
})();
</script>

<?php adminFooter(); ?>
