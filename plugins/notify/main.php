<?php
/**
 * 邮箱通知插件 - 设置面板
 *
 * 后台钩子：
 *   admin_settings_nav  - 在基础设置页注入"邮箱通知"Tab 导航
 *   admin_settings_tabs - 在基础设置页注入"邮箱通知"Tab 内容面板
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

// ========== 后台设置页钩子：注入 Tab 导航 ==========
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = ($activeTab === 'notify') ? 'active' : '';
    echo '<a href="#tab-notify" class="settings-tab ' . $cls . '" onclick="switchTab(\'notify\', this)">邮箱通知</a>';
});

// ========== 后台设置页钩子：注入 Tab 内容面板 ==========
Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    try {
        // 直接读取插件配置，不依赖 include.php 中的自定义函数
        $cfg = function ($key, $default = '') {
            return (string) Plugin::config('notify', $key, $default);
        };

        $smtpHost   = $cfg('smtp_host', '');
        $smtpPort   = $cfg('smtp_port', '465');
        $smtpUser   = $cfg('smtp_user', '');
        $smtpPass   = $cfg('smtp_pass', '');
        $smtpSecure = $cfg('smtp_secure', 'ssl');
        $fromEmail  = $cfg('from_email', '');
        $fromName   = $cfg('from_name', '懒人导航');
        $recipient  = $cfg('recipient', '');
        $onSubmit   = $cfg('on_submit', '1') === '1';
        $onFeedback = $cfg('on_feedback', '1') === '1';
        $onApprove  = $cfg('on_approve', '1') === '1';
        $onReject   = $cfg('on_reject', '1') === '1';

        $cls = ($activeTab === 'notify') ? 'active' : '';
        ?>
<!-- 邮箱通知 Tab -->
<div id="tab-notify" class="tab-panel <?= $cls ?>">
  <div class="card">
    <div class="card-header"><span class="card-title">SMTP 邮箱配置</span></div>
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '') ?>">
      <input type="hidden" name="section" value="notify">
      <input type="hidden" name="tab" value="notify">

      <div class="form-row">
        <div class="form-group flex-1">
          <label>SMTP 服务器</label>
          <input type="text" class="form-input" name="notify_smtp_host" value="<?= Security::eAttr($smtpHost) ?>" placeholder="smtp.qq.com" maxlength="200">
          <div class="form-help">QQ邮箱: smtp.qq.com / 163邮箱: smtp.163.com / Gmail: smtp.gmail.com</div>
        </div>
        <div class="form-group" style="width:120px;">
          <label>端口</label>
          <input type="number" class="form-input" name="notify_smtp_port" value="<?= Security::eAttr($smtpPort) ?>" placeholder="465" min="1" max="65535">
        </div>
        <div class="form-group" style="width:120px;">
          <label>加密方式</label>
          <select class="form-input" name="notify_smtp_secure">
            <option value="ssl" <?= $smtpSecure === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="tls" <?= $smtpSecure === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>>无加密</option>
          </select>
          <div class="form-help">SSL 用 465，TLS 用 587</div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group flex-1">
          <label>SMTP 用户名</label>
          <input type="text" class="form-input" name="notify_smtp_user" value="<?= Security::eAttr($smtpUser) ?>" placeholder="发件邮箱地址" maxlength="200">
        </div>
        <div class="form-group flex-1">
          <label>SMTP 密码 / 授权码</label>
          <input type="password" class="form-input" name="notify_smtp_pass" value="<?= Security::eAttr($smtpPass) ?>" placeholder="邮箱密码或授权码" maxlength="200">
          <div class="form-help">QQ/163等需使用授权码而非登录密码</div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group flex-1">
          <label>发件人邮箱</label>
          <input type="email" class="form-input" name="notify_from_email" value="<?= Security::eAttr($fromEmail) ?>" placeholder="noreply@example.com" maxlength="200">
          <div class="form-help">通常与 SMTP 用户名一致</div>
        </div>
        <div class="form-group flex-1">
          <label>发件人名称</label>
          <input type="text" class="form-input" name="notify_from_name" value="<?= Security::eAttr($fromName) ?>" placeholder="懒人导航" maxlength="100">
        </div>
      </div>

      <div class="form-group">
        <label>通知收件人</label>
        <input type="text" class="form-input" name="notify_recipient" value="<?= Security::eAttr($recipient) ?>" placeholder="admin@example.com" maxlength="500">
        <div class="form-help">多个收件人用英文逗号分隔，如 admin@xx.com,admin2@xx.com</div>
      </div>

      <div class="form-group">
        <label>通知触发条件</label>
        <div class="form-help" style="margin-bottom:12px;">勾选需要发送邮件通知的事件：</div>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <label class="flex-center-gap-10">
            <input type="checkbox" name="notify_on_submit" value="1" <?= $onSubmit ? 'checked' : '' ?> class="wh-18">
            <span>站点提交通知 <span class="text-xs-dim-2">（用户通过前台提交站点时）</span></span>
          </label>
          <label class="flex-center-gap-10">
            <input type="checkbox" name="notify_on_feedback" value="1" <?= $onFeedback ? 'checked' : '' ?> class="wh-18">
            <span>反馈站点通知 <span class="text-xs-dim-2">（用户提交站点反馈/报错时）</span></span>
          </label>
          <label class="flex-center-gap-10">
            <input type="checkbox" name="notify_on_approve" value="1" <?= $onApprove ? 'checked' : '' ?> class="wh-18">
            <span>审核通过通知 <span class="text-xs-dim-2">（管理员审核通过站点时）</span></span>
          </label>
          <label class="flex-center-gap-10">
            <input type="checkbox" name="notify_on_reject" value="1" <?= $onReject ? 'checked' : '' ?> class="wh-18">
            <span>审核拒绝通知 <span class="text-xs-dim-2">（管理员拒绝站点时）</span></span>
          </label>
        </div>
      </div>

      <div class="text-right">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存邮箱配置</button>
        <a href="/admin/plugin.php?p=notify" class="btn" style="margin-left:8px;"><i class="ti ti-mail"></i> 查看发送日志</a>
      </div>
    </form>
  </div>
</div>
        <?php
    } catch (\Exception $e) {
        echo '<div style="padding:20px;color:#e74c3c;background:#fee;border-radius:8px;margin:20px;">';
        echo '<strong>邮箱通知插件加载出错：</strong><br>';
        echo htmlspecialchars($e->getMessage()) . '<br>';
        echo '<small>' . htmlspecialchars($e->getFile()) . ':' . (int)$e->getLine() . '</small>';
        echo '</div>';
    }
});
