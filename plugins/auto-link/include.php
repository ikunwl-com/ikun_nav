<?php
/**
 * 友链来访自动收录插件 - 主文件
 * 检测 Referer 来源，验证对方是否挂了本站友链，通过后自动收录
 *
 * 此插件从现有 footer.php 中的自动收录代码和 AutoLinkModel 拆分而来。
 * 后台设置中的 autolink_* 配置项仍然复用，保持向后兼容。
 *
 * 配置项（复用原有设置，不新增 plugin_ 前缀）：
 *   autolink_enable          - 是否开启
 *   autolink_need_review     - 收录后是否需要审核
 *   autolink_default_category - 默认收录分类
 *   autolink_banned_words    - 违禁词黑名单
 *
 * 前台钩子：
 *   after_footer - 异步检测 Referer 并触发收录
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// ========== 前台钩子：异步检测 Referer ==========
Plugin::registerHook('after_footer', function () {
    // 复用原有 autolink_enable 设置
    if (setting('autolink_enable', '0') !== '1') {
        return;
    }
    ?>
<!-- 友链自动收录插件：异步检测 Referer，不影响页面加载 -->
<script>
(function(){
    var referer = '<?php
        $autoLinkReferer = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refDomain = Security::extractDomain($_SERVER['HTTP_REFERER']);
            $selfHost  = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $sePattern = '/(baidu\.com|google\.com|bing\.com|sogou\.com|so\.com|yahoo\.com|yandex\.com|duckduckgo\.com|baiducontent\.com)$/i';
            if (strcasecmp($refDomain, $selfHost) !== 0 && !preg_match($sePattern, $refDomain)) {
                $autoLinkReferer = $_SERVER['HTTP_REFERER'];
            }
        }
        echo rawurlencode($autoLinkReferer);
    ?>';
    if (!referer) return;
    setTimeout(function(){
        try {
            var img = new Image();
            img.src = '/api/?endpoint=auto-link&ref=' + referer + '&_t=' + Date.now();
        } catch(e) {}
    }, 2000);
})();
</script>
<?php
});
