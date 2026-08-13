<?php
/**
 * 图片自动ALT插件
 * 自动给没有 alt 属性的 <img> 标签补填站点名称或描述
 *
 * 配置项：
 *   plugin_auto-alt_default_alt - 默认 alt 文本（留空则用页面 title）
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

Plugin::registerHook('after_footer', function () {
    $defaultAlt = Plugin::config('auto-alt', 'default_alt', '');
    ?>
<!-- 图片自动ALT插件 -->
<script>
(function(){
    var defaultAlt = <?= json_encode($defaultAlt) ?>;
    var pageTitle = document.title || '图片';
    var imgs = document.querySelectorAll('img:not([alt]), img[alt=""]');
    var imgs = document.querySelectorAll('img:not([alt]), img[alt=""]');
    imgs.forEach(function(img){
        // 1. 优先使用配置的默认 alt
        var alt = defaultAlt;

        // 2. 若未配置，尝试从父级 a 标签的 title 提取
        var parentA = img.closest('a');
        if (!alt && parentA && parentA.title) {
            alt = parentA.title;
        }

        // 3. 仍无可用 alt，从 URL 提取文件名（最后手段）
        if (!alt) {
            var src = img.getAttribute('src') || '';
            if (src) {
                var fileName = src.split('/').pop().split('?')[0].split('.')[0];
                if (fileName && fileName.length > 2 && fileName.length < 50) {
                    // 过滤无意义的 API 端点名
                    var meaningless = ['random','api','image','img','placeholder','pic','photo','temp','upload'];
                    if (meaningless.indexOf(fileName.toLowerCase()) === -1) {
                        alt = fileName.replace(/[-_]/g, ' ');
                    }
                }
            }
        }

        // 4. 兜底：页面标题
        if (!alt) {
            alt = pageTitle;
        }

        img.setAttribute('alt', alt);
    });
})();
</script>
<?php
});
