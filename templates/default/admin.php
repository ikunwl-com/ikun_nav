<?php
/**
 * 默认主题（default）- 主题设置页
 * 访问：/admin/theme.php?name=default（仅"当前使用的主题"可进入，分发器已校验）
 * 本文件由分发器加载，adminHeader()/adminFooter() 已由分发器处理，文件内直接输出内容
 *
 * 本页是"主题自带后台设置页"的参考范例，第三方主题可照此结构提供自己的 admin.php：
 *   - 入口：后台 → 主题管理 → 当前主题卡片 → 「设置」按钮
 *     （templates/{主题名}/admin.php 存在时按钮自动出现，见 admin/themes.php）
 *   - 配置存取：Theme::config($key) / Theme::setConfig([...])
 *     存储于 settings 表，key 前缀 theme_{主题名}_（此处为 theme_default_）
 *   - 前台消费：custom_css          → templates/default/header.php 的 <head> 内输出
 *               custom_footer_code  → templates/default/footer.php 的 </body> 前输出
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Direct access denied');
}

$themeName = Theme::current();
$themeTitle = Theme::getInfo($themeName)['title'] ?? $themeName;

// ========== POST 保存 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('/admin/theme.php?name=' . urlencode($themeName) . '&err=' . urlencode('CSRF验证失败'));
    }

    Theme::setConfig([
        'custom_css'         => Security::cleanString($_POST['custom_css'] ?? '', 50000),
        'custom_footer_code' => Security::cleanString($_POST['custom_footer_code'] ?? '', 50000),
    ]);

    if (class_exists('Logger')) {
        Logger::log('admin_setting', "修改主题设置 theme={$themeName} admin_id=" . ($_SESSION['admin_id'] ?? '?') . ' IP=' . Security::getClientIP());
    }
    redirect('/admin/theme.php?name=' . urlencode($themeName) . '&ok=' . urlencode('主题设置已保存'));
}

// ========== GET 消息（PRG 跳转后） ==========
$msg = '';
$msgType = 'success';
if (isset($_GET['ok'])) {
    $msg = Security::cleanString($_GET['ok']);
    $msgType = 'success';
} elseif (isset($_GET['err'])) {
    $msg = Security::cleanString($_GET['err']);
    $msgType = 'error';
}

// 当前已保存的值
$customCss = (string)Theme::config('custom_css', '');
$customFooterCode = (string)Theme::config('custom_footer_code', '');
?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="ti ti-settings"></i> 主题设置：<?= Security::e($themeTitle) ?></span></div>

    <?php if ($msg) { adminAlert($msg, $msgType); } ?>

    <div class="alert" style="background:#f0f7ff;border:1px solid #b3d9ff;padding:12px;border-radius:6px;margin:0 16px 16px;">
      <h4 style="margin:0 0 6px;font-size:14px"><i class="ti ti-info-circle"></i> 关于主题设置</h4>
      <p style="margin:0;font-size:13px;color:#495057;line-height:1.8">
        此处配置仅对「当前使用的主题」生效，保存后立即作用于前台。<br>
        本页为内置范例：自定义 CSS 输出到页面 &lt;head&gt;，页脚代码输出到 &lt;/body&gt; 前（允许统计类 script）。<br>
        第三方主题可提供自己的 admin.php 与专属配置项（参考 <code>templates/default/admin.php</code> 结构）。
      </p>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?>">

      <div class="form-group" style="padding:0 16px;">
        <label>自定义 CSS</label>
        <textarea name="custom_css" class="form-input" rows="10" style="font-family:Consolas,Menlo,monospace;font-size:12px;" placeholder="/* 例：把导航主色调成红色 */&#10;.site-header { background: #e11d48; }"><?= Security::e($customCss) ?></textarea>
        <div class="form-help">写入页面 &lt;head&gt; 的 &lt;style&gt; 中，可覆盖本主题默认样式。留空则输出空样式。</div>
      </div>

      <div class="form-group" style="padding:0 16px;">
        <label>页脚统计代码 / 自定义 HTML</label>
        <textarea name="custom_footer_code" class="form-input" rows="6" style="font-family:Consolas,Menlo,monospace;font-size:12px;" placeholder="&lt;!-- 例：百度统计 / 51LA / 51统计 --&gt;&#10;&lt;script&gt;&#10;var _hmt = _hmt || [];&#10;&lt;/script&gt;"><?= Security::e($customFooterCode) ?></textarea>
        <div class="form-help">输出到 &lt;/body&gt; 之前，允许 script 标签；自动过滤 on* 事件属性与 javascript:/data: 危险协议。</div>
      </div>

      <div class="text-right" style="padding:0 16px 16px;">
        <a href="/admin/themes.php" class="btn btn-secondary" style="margin-right:8px;"><i class="ti ti-arrow-left"></i> 返回主题管理</a>
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> 保存设置</button>
      </div>
    </form>
</div>
