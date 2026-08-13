<?php
/**
 * 主题模板 - 404 错误页
 * 变量由 Route.php 注入
 */
Theme::partial('header');
?>

<div class="container">
  <div class="error-page">
    <div class="error-code"><?= (int)($code ?? 404) ?></div>
    <i class="ti ti-mood-empty"></i>
    <h1><?= Theme::e($message ?? '页面不存在') ?></h1>
    <p>您访问的页面不存在或已被移除</p>
    <a href="<?= Theme::url('home') ?>" class="submit-btn"><i class="ti ti-arrow-left"></i> 返回首页</a>
  </div>
</div>

<?php Theme::partial('footer'); ?>
