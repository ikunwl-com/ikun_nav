<?php
// header partial 被多页面复用，确保关键变量有默认值
$fallbackSiteName = $settings['site_name'] ?? '懒人导航';
if (!isset($seoTitle)) $seoTitle = $fallbackSiteName;
if (!isset($seoDesc)) $seoDesc = '';
if (!isset($seoKeywords)) $seoKeywords = '';
?>
<?php Plugin::hook('before_header'); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= Security::eAttr(Security::generateCSRFToken()) ?>">
<title><?= Theme::e($seoTitle) ?></title>
<?php if (!empty($seoDesc)): ?>
<meta name="description" content="<?= Theme::eAttr($seoDesc) ?>">
<?php endif; ?>
<?php if (!empty($seoKeywords)): ?>
<meta name="keywords" content="<?= Theme::eAttr($seoKeywords) ?>">
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/tabler-icons.css">
<link rel="stylesheet" href="<?= Theme::asset('css/common.css') ?>">
<script>
// 防FOUC：CSS加载完成后淡入显示
(function(){
  function show(){ document.body.classList.add('css-ready'); }
  if(document.readyState==='complete'){ show(); return; }
  window.addEventListener('load',show);
  document.addEventListener('DOMContentLoaded',function(){ setTimeout(show,50); });
  setTimeout(show,600);
})();
</script>
<?php
// 主题自定义 CSS（后台：主题管理 → 当前主题 → 设置 → 自定义CSS）
$themeCustomCss = Theme::config('custom_css', '');
if ($themeCustomCss !== '') {
    // 转义 </style 防止提前闭合样式块，其余内容原样输出
    echo '<style>' . str_ireplace('</style', '<\\/style', $themeCustomCss) . '</style>' . "\n";
}
?>
</head>
<body>
<?php Plugin::hook('after_header'); ?>
