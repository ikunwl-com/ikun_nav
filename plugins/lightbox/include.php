<?php
/**
 * 灯箱插件
 * 站点详情页图片点击放大，自动给图片加 data-lightbox 属性
 * 内置轻量灯箱实现，无需外部依赖
 *
 * 配置项：
 *   plugin_lightbox_selector - 图片选择器（默认 .site-details img, .article-content img）
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

Plugin::registerHook('after_footer', function () {
    $selector = Plugin::config('lightbox', 'selector', '.site-details img, .article-content img, .article-detail img');
    ?>
<!-- 灯箱插件 -->
<style>
.lightbox-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);z-index:99999;justify-content:center;align-items:center;cursor:zoom-out;}
.lightbox-overlay.active{display:flex;}
.lightbox-overlay img{max-width:90%;max-height:90%;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,.5);}
.lightbox-close{position:fixed;top:16px;right:20px;color:#fff;font-size:36px;cursor:pointer;line-height:1;z-index:100000;opacity:.7;transition:opacity .2s;}
.lightbox-close:hover{opacity:1;}
.lightbox-loading{color:#fff;font-size:14px;}
</style>
<div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImage" src="" alt="预览">
</div>
<script>
(function(){
    var selector = <?= json_encode($selector) ?>;
    var imgs = document.querySelectorAll(selector);
    imgs.forEach(function(img){
        // 跳过已有 data-lightbox 的
        if (img.hasAttribute('data-lightbox')) return;
        img.setAttribute('data-lightbox', 'plugin');
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            openLightbox(this.src);
        });
    });

    function openLightbox(src){
        var overlay = document.getElementById('lightboxOverlay');
        var img = document.getElementById('lightboxImage');
        if (!overlay || !img) return;
        img.src = src;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    window.closeLightbox = function(){
        var overlay = document.getElementById('lightboxOverlay');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    };
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeLightbox();
    });
})();
</script>
<?php
});
