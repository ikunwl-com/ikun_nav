<?php
/**
 * 主题模板 - 错误页
 */
if (!isset($seoTitle)) $seoTitle = ($settings['site_name'] ?? '') . ' - 页面不存在';
if (!isset($seoDesc)) $seoDesc = '';
if (!isset($seoKeywords)) $seoKeywords = '';
Theme::partial('header');
?>

<div class="container">
  <div class="empty-state" style="padding:80px 20px;text-align:center">
    <i class="ti ti-error-404" style="font-size:72px;color:#ccc;margin-bottom:16px;display:block"></i>
    <h2 style="font-size:24px;margin-bottom:8px"><?= (int)$code ?></h2>
    <p style="font-size:16px;color:#888"><?= Theme::e($message) ?></p>
    <a href="<?= Theme::url('home') ?>" class="btn btn-primary" style="margin-top:20px;display:inline-block">
      <i class="ti ti-home"></i> 返回首页
    </a>
  </div>
</div>

<?php Theme::partial('footer'); ?>
