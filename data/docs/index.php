<?php
/**
 * 懒人导航 - 使用与开发文档
 * 单页技术文档，覆盖用户使用、主题开发、插件开发三大方向
 */
$docBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>懒人导航 - 使用与开发文档</title>
  <link rel="stylesheet" href="<?= $docBase ?>css/doc.css">
</head>
<body>

<div class="doc-overlay"></div>
<button class="mobile-toggle">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
</button>

<aside class="doc-sidebar">
  <div class="sidebar-header">
    <div class="sidebar-title">
      <span class="logo-icon">懒</span>
      <span>懒人导航</span>
    </div>
    <div class="sidebar-subtitle">使用与开发文档 v2.0</div>
  </div>
  <ul class="nav-menu">
    <li class="nav-item"><a href="#ch1" class="nav-link active">第一章 快速入门</a></li>
    <li class="nav-item"><a href="#ch1-1" class="nav-link sub">1.1 系统要求</a></li>
    <li class="nav-item"><a href="#ch1-2" class="nav-link sub">1.2 安装部署</a></li>
    <li class="nav-item"><a href="#ch1-3" class="nav-link sub">1.3 后台使用</a></li>
    <li class="nav-item"><a href="#ch1-4" class="nav-link sub">1.4 插件管理</a></li>
    <li class="nav-item"><a href="#ch1-5" class="nav-link sub">1.5 主题切换</a></li>
    <li class="nav-divider"></li>
    <li class="nav-item"><a href="#ch2" class="nav-link">第二章 系统概述</a></li>
    <li class="nav-item"><a href="#ch2-1" class="nav-link sub">2.1 目录结构</a></li>
    <li class="nav-item"><a href="#ch2-2" class="nav-link sub">2.2 核心类一览</a></li>
    <li class="nav-item"><a href="#ch2-3" class="nav-link sub">2.3 数据库表概览</a></li>
    <li class="nav-item"><a href="#ch2-4" class="nav-link sub">2.4 辅助函数速查</a></li>
    <li class="nav-divider"></li>
    <li class="nav-item"><a href="#ch3" class="nav-link">第三章 主题开发</a></li>
    <li class="nav-item"><a href="#ch3-1" class="nav-link sub">3.1 主题目录结构</a></li>
    <li class="nav-item"><a href="#ch3-2" class="nav-link sub">3.2 theme.json</a></li>
    <li class="nav-item"><a href="#ch3-3" class="nav-link sub">3.3 模板文件与变量</a></li>
    <li class="nav-item"><a href="#ch3-4" class="nav-link sub">3.4 Theme 类方法</a></li>
    <li class="nav-item"><a href="#ch3-5" class="nav-link sub">3.5 钩子列表</a></li>
    <li class="nav-item"><a href="#ch3-6" class="nav-link sub">3.6 URL 生成与资源引用</a></li>
    <li class="nav-item"><a href="#ch3-7" class="nav-link sub">3.7 实战案例：创建主题</a></li>
    <li class="nav-divider"></li>
    <li class="nav-item"><a href="#ch4" class="nav-link">第四章 插件开发</a></li>
    <li class="nav-item"><a href="#ch4-1" class="nav-link sub">4.1 插件目录结构</a></li>
    <li class="nav-item"><a href="#ch4-2" class="nav-link sub">4.2 plugin.json</a></li>
    <li class="nav-item"><a href="#ch4-3" class="nav-link sub">4.3 schema.php 机制</a></li>
    <li class="nav-item"><a href="#ch4-4" class="nav-link sub">4.4 include.php 与钩子</a></li>
    <li class="nav-item"><a href="#ch4-5" class="nav-link sub">4.5 main.php 设置面板</a></li>
    <li class="nav-item"><a href="#ch4-6" class="nav-link sub">4.6 Plugin 类 API</a></li>
    <li class="nav-item"><a href="#ch4-7" class="nav-link sub">4.7 共享表与卸载</a></li>
    <li class="nav-item"><a href="#ch4-8" class="nav-link sub">4.8 实战案例：每日一言</a></li>
    <li class="nav-divider"></li>
    <li class="nav-item"><a href="#ch5" class="nav-link">第五章 内置插件</a></li>
    <li class="nav-item"><a href="#ch5-1" class="nav-link sub">5.1 插件一览</a></li>
    <li class="nav-item"><a href="#ch5-2" class="nav-link sub">5.2 广告管理</a></li>
    <li class="nav-item"><a href="#ch5-3" class="nav-link sub">5.3 文章发布</a></li>
    <li class="nav-item"><a href="#ch5-4" class="nav-link sub">5.4 虫洞联盟</a></li>
    <li class="nav-item"><a href="#ch5-5" class="nav-link sub">5.5 友链自动收录</a></li>
    <li class="nav-item"><a href="#ch5-6" class="nav-link sub">5.6 伪静态设置</a></li>
    <li class="nav-item"><a href="#ch5-7" class="nav-link sub">5.7 提交网站收录</a></li>
    <li class="nav-item"><a href="#ch5-8" class="nav-link sub">5.8 站点地图</a></li>
    <li class="nav-item"><a href="#ch5-9" class="nav-link sub">5.9 图片灯箱</a></li>
    <li class="nav-item"><a href="#ch5-10" class="nav-link sub">5.10 图片ALT</a></li>
    <li class="nav-item"><a href="#ch5-11" class="nav-link sub">5.11 邮箱通知</a></li>
    <li class="nav-item"><a href="#ch5-12" class="nav-link sub">5.12 友情链接</a></li>
    <li class="nav-item"><a href="#ch5-13" class="nav-link sub">5.13 蜘蛛来访</a></li>
    <li class="nav-item"><a href="#ch5-14" class="nav-link sub">5.14 数据库备份</a></li>
    <li class="nav-divider"></li>
    <li class="nav-item"><a href="#ch6" class="nav-link">第六章 参考文档</a></li>
    <li class="nav-item"><a href="#ch6-1" class="nav-link sub">6.1 API 接口</a></li>
    <li class="nav-item"><a href="#ch6-2" class="nav-link sub">6.2 日志系统</a></li>
    <li class="nav-item"><a href="#ch6-3" class="nav-link sub">6.3 伪静态配置</a></li>
    <li class="nav-item"><a href="#ch6-4" class="nav-link sub">6.4 安全规范</a></li>
    <li class="nav-item"><a href="#ch6-5" class="nav-link sub">6.5 应用中心</a></li>
  </ul>
</aside>
<main class="doc-main">
  <div class="doc-content">

    <!-- ===== 前言 ===== -->
    <section id="intro" class="doc-section">
      <h1>懒人导航 - 使用与开发文档</h1>
      <p>懒人导航基于原生 PHP + MySQL 构建，不依赖任何第三方框架。本文档面向三类读者：</p>
      <table>
        <thead><tr><th>角色</th><th>关注章节</th><th>目标</th></tr></thead>
        <tbody>
          <tr><td>普通用户 / 站长</td><td>第一章、第五章</td><td>安装部署、日常运营、按需启用插件</td></tr>
          <tr><td>主题开发者</td><td>第二章、第三章、6.5</td><td>自定义页面布局和视觉风格；向应用中心发布主题</td></tr>
          <tr><td>插件开发者</td><td>第二章、第四章、6.5</td><td>扩展功能、注册钩子、管理数据库；向应用中心发布扩展</td></tr>
        </tbody>
      </table>
      <div class="tip">
        <div class="tip-title">核心设计理念</div>
        <p>懒人导航采用<strong>按需加载</strong>架构：初始安装只创建核心表（sites、categories、settings 等 9 张），所有插件默认关闭。插件启用时才自动创建其所需的表、字段和配置——做到真正的插件单独安装、按需启用。</p>
      </div>
    </section>

    <!-- ===== 第一章 快速入门 ===== -->
    <section id="ch1" class="doc-section">
      <h1>第一章 快速入门 <button class="share-anchor" data-anchor="ch1" title="复制章节链接">🔗</button></h1>

      <section id="ch1-1" class="doc-section">
        <h2>1.1 系统要求</h2>
        <table>
          <thead><tr><th>组件</th><th>最低版本</th><th>推荐版本</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td>PHP</td><td>7.4</td><td>8.0+</td><td>PDO、GD、cURL、mbstring、JSON、OpenSSL、Session</td></tr>
            <tr><td>MySQL</td><td>5.7</td><td>8.0</td><td>utf8mb4 字符集</td></tr>
            <tr><td>Web 服务器</td><td>Nginx / Apache</td><td>Nginx</td><td>需支持伪静态</td></tr>
          </tbody>
        </table>
        <p>PHP 扩展要求：PDO_MYSQL（必需）、GD（必需）、cURL（必需）、mbstring（必需）、JSON（必需）、OpenSSL（必需）、Session（必需）、fileinfo（推荐）。</p>
        <p>可通过 <code>php -m</code> 命令查看已安装的扩展列表。</p>
      </section>

      <section id="ch1-2" class="doc-section">
        <h2>1.2 安装部署</h2>
        <h3>1.2.1 上传文件</h3>
        <ol>
          <li>将程序压缩包解压，上传到 Web 服务器根目录或子目录</li>
          <li>确保以下目录可写：<code>data/logs/</code>、<code>data/backups/</code>、<code>config.php</code>（在线更新、备份与日志功能需要）</li>
          <li>将 <code>config.php</code> 设置为可写（安装程序会自动写入数据库配置）</li>
        </ol>

        <h3>1.2.2 运行安装向导</h3>
        <ol>
          <li>浏览器访问 <code>http://你的域名/install/</code></li>
          <li>按提示填写数据库主机、库名、用户名、密码、表前缀</li>
          <li>设置管理员账号和密码</li>
          <li>点击安装，系统自动创建核心表、写入默认配置、生成 <code>config.php</code></li>
          <li>安装完成后删除 <code>install/</code> 目录或重命名</li>
        </ol>
        <div class="tip">
          <div class="tip-title">说明</div>
          <p>初始安装只创建 9 张核心表和基础配置。所有插件默认关闭，需要到后台「插件管理」中按需启用。插件启用时会自动创建插件所需的表、字段和配置。</p>
        </div>

        <h3>1.2.3 伪静态配置（可选，推荐）</h3>
        <p>系统支持三种 URL 模式，默认 <code>dynamic</code>（动态模式，无需任何服务器配置即可运行）。需要伪静态时：</p>
        <ol>
          <li>到后台「插件管理」启动 <code>rewrite</code> 插件（其设置 Tab 才会出现在基础设置中）</li>
          <li>进入后台「基础设置 - 伪静态」，选择 <code>rewrite</code> 或 <code>index</code> 模式，可自定义各页面 URL 格式</li>
          <li>复制自动生成的服务器规则到 <code>.htaccess</code>（Apache）或 Nginx 配置文件中</li>
        </ol>
        <table>
          <thead><tr><th>模式</th><th>示例 URL</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td>dynamic</td><td><code>/index.php?route=category&amp;slug=tech</code></td><td>动态模式，无需服务器配置</td></tr>
            <tr><td>rewrite</td><td><code>/category/tech/</code></td><td>伪静态模式，需配置服务器规则</td></tr>
            <tr><td>index</td><td><code>/index.php/category/tech/</code></td><td>URL 中带 index.php 的兼容模式</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch1-3" class="doc-section">
        <h2>1.3 后台使用</h2>
        <p>安装完成后访问 <code>http://你的域名/admin/</code> 登录后台。</p>
        <table>
          <thead><tr><th>菜单</th><th>功能说明</th></tr></thead>
          <tbody>
            <tr><td>仪表盘</td><td>站点概况、登录日志提示、统计数据概览</td></tr>
            <tr><td>站点管理</td><td>站点增删改查、审核发布、批量操作</td></tr>
            <tr><td>分类管理</td><td>分类增删改、排序、SEO 字段设置</td></tr>
            <tr><td>推荐管理</td><td>设置全局推荐和分类推荐位</td></tr>
            <tr><td>提交审核</td><td>审核前台提交和自动收录的站点，通过/拒绝操作会触发邮箱通知钩子</td></tr>
            <tr><td>数据统计</td><td>站点浏览、点击、评分等数据统计</td></tr>
            <tr><td>基础设置</td><td>基础信息（站点信息、SEO、<strong>日志设置</strong>等）、修改密码，以及各已启用插件注入的设置 Tab</td></tr>
            <tr><td>主题管理</td><td>查看可用主题、一键切换当前主题</td></tr>
            <tr><td>插件管理</td><td>启动/停用/卸载插件，查看插件钩子与数据库影响</td></tr>
            <tr><td>API 密钥</td><td>开放 API（open/*）的 API Key 管理与调用频率限制</td></tr>
            <tr><td>程序更新</td><td>检查并在线更新程序（侧边栏底部）</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">基础设置中的面板</div>
          <p>基础设置页采用 Tab 面板设计。「基础信息」固定显示站点信息、SEO、<strong>日志设置</strong>（含全局总开关与各频道独立开关）；「修改密码」固定显示；其余 Tab（广告管理、友链收录、伪静态设置等）由对应插件在启用后通过钩子注入。</p>
          <p>主题切换不在基础设置中，而是在「主题管理」页面完成（对应设置项 <code>current_theme</code>）。</p>
        </div>
      </section>

      <section id="ch1-4" class="doc-section">
        <h2>1.4 插件管理</h2>
        <p>后台「插件管理」页面（<code>/admin/plugins.php</code>）展示所有已扫描到的插件（内置 13 个），列表显示插件名称、描述、版本、状态、声明钩子与数据库影响。每个插件有行级操作按钮：</p>
        <table>
          <thead><tr><th>操作</th><th>效果</th></tr></thead>
          <tbody>
            <tr><td><strong>启动</strong></td><td>自动执行 <code>Plugin::ensureSchema()</code>：创建 schema.php 声明的表、向已有表添加字段、写入默认配置；之后该插件的 <code>include.php</code> / <code>main.php</code> 才会被加载并注册钩子</td></tr>
            <tr><td><strong>停用</strong></td><td>仅修改启用状态（<code>plugin_{name}_enabled=0</code>）为关闭，<strong>保留</strong>所有数据库表和配置数据。再次启动时无需重新安装</td></tr>
            <tr><td><strong>卸载</strong></td><td>停用 + 删除插件自建表 + 删除插件向已有表添加的字段 + 清除插件配置（<code>plugin_{name}_*</code>）。共享表智能判断：仅当所有声明该表的插件都卸载时才删表。插件文件不会被删除，可随时重新启动</td></tr>
          </tbody>
        </table>
        <p>插件若启用且其目录下存在 <code>admin.php</code>，管理页面会显示「管理」按钮（跳转 <code>/admin/plugin.php?p=插件名</code>）；若只有 <code>config_file</code>（如 <code>main.php</code>）则显示「设置」按钮（跳转 <code>settings.php?tab={config_tab 或插件名}</code>）。</p>
        <p>列表中的数据库信息格式如 <code>articles · settings(2配置)</code>，表示该插件创建了 articles 表并声明写入 2 条配置；向已有表添加字段时显示为 <code>sites(5字段)</code>。</p>
        <div class="tip">
          <div class="tip-title">提示</div>
          <p>所有内置插件默认关闭（安装时即写入 <code>plugin_{name}_enabled=0</code>）。安装完成后，根据需要到插件管理页面逐个启动。插件之间的依赖关系极低，可以按任意顺序启动。</p>
        </div>
      </section>

      <section id="ch1-5" class="doc-section">
        <h2>1.5 主题切换</h2>
        <ol>
          <li>进入后台「主题管理」</li>
          <li>在可用主题列表中点击「启用」选择要使用的主题</li>
          <li>保存后前台立即生效</li>
        </ol>
        <p>主题文件放在 <code>templates/{主题名}/</code> 目录下。系统通过 <code>Theme::scan()</code> 扫描所有含 <code>index.php</code> 的子目录并读取其 <code>theme.json</code> 展示在后台主题列表中。切换结果写入 <code>current_theme</code> 配置。</p>
      </section>

    </section>

    <!-- ===== 第二章 系统概述 ===== -->
    <section id="ch2" class="doc-section">
      <h1>第二章 系统概述 <button class="share-anchor" data-anchor="ch2" title="复制章节链接">🔗</button></h1>

      <section id="ch2-1" class="doc-section">
        <h2>2.1 目录结构</h2>
        <div class="file-tree">
          <span class="dir">.</span><br>
          &nbsp;&nbsp;<span class="file">index.php</span> <span class="comment">前台统一入口（路由分发由 core/Route.php 完成）</span><br>
          &nbsp;&nbsp;<span class="file">go.php</span> <span class="comment">跳转中间页（记录点击统计后跳转）</span><br>
          &nbsp;&nbsp;<span class="file">config.php</span> <span class="comment">数据库配置（安装时自动生成，可写权限要求）</span><br>
          &nbsp;&nbsp;<span class="file">.htaccess</span> <span class="comment">伪静态规则（后台可自动生成，需 rewrite 插件）</span><br>
          &nbsp;&nbsp;<span class="dir">core/</span> <span class="comment">核心类库</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">bootstrap.php</span> <span class="comment">应用引导（Session、自动加载、插件初始化、调试模式）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Database.php</span> <span class="comment">PDO 单例 + 预处理封装（query/queryOne/execute/insert/scalar/table/事务）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Security.php</span> <span class="comment">安全模块（XSS/CSRF/频率限制/HTML清洗/Referer校验）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Route.php</span> <span class="comment">路由分发器（home/category/site/search/submit/wormhole/article*/sitemap/robots/api）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Rewrite.php</span> <span class="comment">伪静态系统（URL 解析/生成/服务器规则生成）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Theme.php</span> <span class="comment">主题系统（扫描/加载/渲染/片段/资源引用）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Plugin.php</span> <span class="comment">插件系统核心（扫描/启停/schema/钩子/卸载）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Logger.php</span> <span class="comment">日志工具类（按天/按频道写文件，支持开关）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">helpers.php</span> <span class="comment">前台辅助函数库（setting/renderSiteCards/renderPagination 等）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">SiteModel.php</span> <span class="comment">站点模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">CategoryModel.php</span> <span class="comment">分类模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">SettingsModel.php</span> <span class="comment">设置模型（键值对读写）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">FeatureModel.php</span> <span class="comment">推荐模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">WormholeModel.php</span> <span class="comment">虫洞联盟模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">AutoLinkModel.php</span> <span class="comment">友链自动收录模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">BlacklistModel.php</span> <span class="comment">黑名单模型（wormhole / auto-link 共享）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">ApiKeyModel.php</span> <span class="comment">开放 API Key 模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">SitemapModel.php</span> <span class="comment">Sitemap 生成模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Updater.php</span> <span class="comment">在线更新逻辑</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">cron_wormhole_check.php</span> <span class="comment">虫洞联盟每日检测脚本（可配 crontab）</span><br>
          &nbsp;&nbsp;<span class="dir">templates/</span> <span class="comment">主题目录</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">default/</span> <span class="comment">默认主题（index/category/site/search/submit/wormhole/article_list/article_detail/error/404 等）</span><br>
          &nbsp;&nbsp;<span class="dir">plugins/</span> <span class="comment">插件目录（appcenter/ad/article/auto-alt/auto-link/dbtool/friendlink/lightbox/notify/rewrite/sitemap/spider/submit/wormhole）</span><br>
          &nbsp;&nbsp;<span class="dir">admin/</span> <span class="comment">后台管理（settings.php/plugins.php/themes.php/api_keys.php/plugin.php 等）</span><br>
          &nbsp;&nbsp;<span class="dir">api/</span> <span class="comment">API 接口入口（index.php 分发，含 open/* 开放接口）</span><br>
          &nbsp;&nbsp;<span class="dir">assets/</span> <span class="comment">全局静态资源（css/tabler 图标字体等）</span><br>
          &nbsp;&nbsp;<span class="dir">install/</span> <span class="comment">安装程序</span><br>
          &nbsp;&nbsp;<span class="dir">data/</span> <span class="comment">数据目录（logs 日志 / backups 数据库备份 / docs 本文档）</span>
        </div>
      </section>

      <section id="ch2-2" class="doc-section">
        <h2>2.2 核心类一览</h2>
        <table>
          <thead><tr><th>类</th><th>文件</th><th>职责与常用方法</th></tr></thead>
          <tbody>
            <tr><td><code>Database</code></td><td><code>core/Database.php</code></td><td>PDO 单例：<code>query()</code>、<code>queryOne()</code>、<code>execute()</code>、<code>insert()</code>、<code>scalar()</code>、<code>table()</code>、事务（beginTransaction/commit/rollback）</td></tr>
            <tr><td><code>Security</code></td><td><code>core/Security.php</code></td><td>输入清洗、输出转义、CSRF、频率限制、HTML 清洗、Referer 校验（详见 6.4）</td></tr>
            <tr><td><code>Route</code></td><td><code>core/Route.php</code></td><td>路由分发：解析 URL 参数，收集模板变量后调用 <code>Theme::render()</code></td></tr>
            <tr><td><code>Rewrite</code></td><td><code>core/Rewrite.php</code></td><td>伪静态：<code>url()</code>、<code>getConfig()</code>、<code>parseRequest()</code>、<code>generateHtaccess()</code>、<code>generateNginx()</code></td></tr>
            <tr><td><code>Theme</code></td><td><code>core/Theme.php</code></td><td>主题扫描、加载、渲染、片段、资源引用（详见 3.4）</td></tr>
            <tr><td><code>Plugin</code></td><td><code>core/Plugin.php</code></td><td>插件扫描、启停/卸载、schema 安装、钩子系统（详见 4.6）</td></tr>
            <tr><td><code>Logger</code></td><td><code>core/Logger.php</code></td><td>日志写入：<code>log()</code>、<code>logs()</code>、<code>isEnabled()</code>、<code>getLogFile()</code>（详见 6.2）</td></tr>
            <tr><td><code>SettingsModel</code></td><td><code>core/SettingsModel.php</code></td><td>settings 表键值对读写：<code>loadAll()</code>、<code>get()</code>、<code>set()</code>、<code>setMany()</code>、<code>delete()</code>、<code>clearCache()</code></td></tr>
            <tr><td><code>SiteModel</code></td><td><code>core/SiteModel.php</code></td><td>站点数据：查询/统计/搜索/评分/反馈/点击浏览统计等</td></tr>
            <tr><td><code>CategoryModel</code></td><td><code>core/CategoryModel.php</code></td><td>分类数据：<code>getAll()</code>、<code>getSidebarCategories()</code>、<code>getBySlug()</code> 等</td></tr>
            <tr><td><code>FeatureModel</code></td><td><code>core/FeatureModel.php</code></td><td>推荐位（site_features）管理</td></tr>
            <tr><td><code>WormholeModel</code></td><td><code>core/WormholeModel.php</code></td><td>虫洞联盟成员与统计</td></tr>
            <tr><td><code>AutoLinkModel</code></td><td><code>core/AutoLinkModel.php</code></td><td>友链自动收录全流程（auto-link 插件）</td></tr>
            <tr><td><code>BlacklistModel</code></td><td><code>core/BlacklistModel.php</code></td><td>黑名单管理（wormhole / auto-link 插件共享）</td></tr>
            <tr><td><code>ApiKeyModel</code></td><td><code>core/ApiKeyModel.php</code></td><td>开放 API Key 校验与限流</td></tr>
            <tr><td><code>SitemapModel</code></td><td><code>core/SitemapModel.php</code></td><td>Sitemap / robots.txt 生成（sitemap 插件）</td></tr>
          </tbody>
        </table>
        <p>所有核心类由 <code>core/bootstrap.php</code> 注册的 PSR-0 风格自动加载器按需加载（<code>core/{类名}.php</code>），插件代码可直接使用，无需手动 include。</p>
      </section>

      <section id="ch2-3" class="doc-section">
        <h2>2.3 数据库表概览</h2>
        <p>安装程序（<code>install/do_install.php</code>）初始创建以下 9 张核心表（不含插件表）：</p>
        <table>
          <thead><tr><th>表名</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>sites</code></td><td>站点主表：名称、URL、分类、br_pc/br_mobile/br_360/br_shenma 权重、状态、标签、提交者邮箱（submit_email，notify 插件添加）等</td></tr>
            <tr><td><code>categories</code></td><td>分类表：名称、slug、图标、排序、seo_title/seo_desc 等 SEO 字段</td></tr>
            <tr><td><code>settings</code></td><td>配置表：setting_key / setting_value 键值对，存储全站配置与插件配置</td></tr>
            <tr><td><code>site_features</code></td><td>推荐位关联表：全局推荐与分类推荐</td></tr>
            <tr><td><code>admins</code></td><td>管理员账号（password_hash 使用 password_hash()）</td></tr>
            <tr><td><code>site_ratings</code></td><td>用户评分（IP 防刷）</td></tr>
            <tr><td><code>site_feedback</code></td><td>站点反馈（网址变更/打不开/内容错误）</td></tr>
            <tr><td><code>deleted_ids</code></td><td>ID 回收队列（删除站点后复用 ID）</td></tr>
            <tr><td><code>site_daily_stats</code></td><td>站点每日浏览/点击统计（趋势图数据源）</td></tr>
          </tbody>
        </table>
        <p>插件启用的表（由 <code>Plugin::ensureSchema()</code> 自动创建，卸载时智能清理）：</p>
        <table>
          <thead><tr><th>表名</th><th>创建插件</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>articles</code></td><td>article</td><td>文章（标题、slug、内容、分类、标签、状态、浏览）</td></tr>
            <tr><td><code>blacklist</code></td><td>wormhole / auto-link</td><td>黑名单共享表（两个插件都声明，最后一个卸载时才删表）</td></tr>
            <tr><td><code>notify_logs</code></td><td>notify</td><td>邮件发送记录（notify 同时向 sites 表添加 submit_email 字段）</td></tr>
            <tr><td><code>friendlinks</code></td><td>friendlink</td><td>友情链接</td></tr>
            <tr><td><code>spider_visits</code></td><td>spider</td><td>搜索引擎蜘蛛来访记录</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">插件管理的表</div>
          <p>插件声明的表（<code>schema.php</code> 的 tables）以及向已有表添加的字段（columns，如 wormhole 向 sites 添加联盟字段）<strong>不在初始安装时创建</strong>，而是在插件启动时由 <code>Plugin::ensureSchema()</code> 自动安装（幂等：已存在则跳过），卸载时自动清理。</p>
        </div>
      </section>

      <section id="ch2-4" class="doc-section">
        <h2>2.4 辅助函数速查（core/helpers.php）</h2>
        <p>以下函数在引导阶段自动加载（<code>core/bootstrap.php</code> 引入 <code>helpers.php</code>），主题模板与插件代码中可直接调用：</p>
        <table>
          <thead><tr><th>函数名</th><th>参数</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>setting()</code></td><td>$key, $default=null</td><td>读取配置（别名 <code>getConfig()</code>）</td></tr>
            <tr><td><code>getConfig()</code></td><td>$key, $default=null</td><td>读取配置（同 setting）</td></tr>
            <tr><td><code>isInstalled()</code></td><td>无</td><td>检查系统是否已安装</td></tr>
            <tr><td><code>isDebug()</code></td><td>无</td><td>判断调试模式（APP_DEBUG 或 debug_mode 配置）</td></tr>
            <tr><td><code>redirect()</code></td><td>$url</td><td>HTTP 重定向并终止</td></tr>
            <tr><td><code>getSiteUrl()</code></td><td>$path=''</td><td>获取站点完整 URL（支持子路径拼接）</td></tr>
            <tr><td><code>getCurrentSiteUrl()</code></td><td>无</td><td>从请求推导当前站点 URL</td></tr>
            <tr><td><code>getDisplayDomain()</code></td><td>$url</td><td>从 URL 提取域名（显示用）</td></tr>
            <tr><td><code>parseDomain()</code></td><td>$url</td><td>同 getDisplayDomain</td></tr>
            <tr><td><code>getCategoryUrl()</code></td><td>$slug</td><td>生成分类页 URL（等价 Rewrite::url('category')）</td></tr>
            <tr><td><code>normalizeSiteUrl()</code></td><td>$url</td><td>URL 无协议时补全 https://</td></tr>
            <tr><td><code>getWeightBadgeClass()</code></td><td>$br</td><td>权重数值 → CSS 类（weight-0 ~ weight-9）</td></tr>
            <tr><td><code>getBrColor()</code></td><td>$br</td><td>权重数值 → 颜色</td></tr>
            <tr><td><code>renderSiteIcon()</code></td><td>$name, $size=36</td><td>渲染首字符站点图标 HTML</td></tr>
            <tr><td><code>getSiteColor()</code></td><td>$name</td><td>按名称稳定取色</td></tr>
            <tr><td><code>renderPagination()</code></td><td>$current, $total, $urlTemplate</td><td>生成分页 HTML（%d / {%page%} 占位符均可）</td></tr>
            <tr><td><code>renderSiteCards()</code></td><td>$sites, $showWeight=1</td><td>批量渲染站点卡片列表 HTML</td></tr>
            <tr><td><code>formatNumber()</code></td><td>$num</td><td>格式化数字（1000→1k，1000000→1M）</td></tr>
            <tr><td><code>formatDate()</code></td><td>$date, $format='Y-m-d'</td><td>格式化日期</td></tr>
            <tr><td><code>parseTags()</code></td><td>$tags</td><td>解析 tags（JSON 字符串/数组/空）为数组</td></tr>
            <tr><td><code>tagsToKeywords()</code></td><td>$tags</td><td>标签数组 → SEO 关键词字符串</td></tr>
            <tr><td><code>table()</code></td><td>$name</td><td>获取带前缀的表名（等价 Database::table）</td></tr>
            <tr><td><code>getMaxBr()</code></td><td>$site</td><td>获取站点 PC/移动/360/神马最高权重</td></tr>
            <tr><td><code>extractMainTitle()</code></td><td>$title</td><td>从网站标题智能提取主标题（≤6 字）</td></tr>
          </tbody>
        </table>
      </section>

    </section>

    <!-- ===== 第三章 主题开发 ===== -->
    <section id="ch3" class="doc-section">
      <h1>第三章 主题开发 <button class="share-anchor" data-anchor="ch3" title="复制章节链接">🔗</button></h1>

      <section id="ch3-1" class="doc-section">
        <h2>3.1 主题目录结构</h2>
        <p>主题放在 <code>templates/{主题名}/</code> 目录下。判定一个目录是否为可用主题：目录存在且包含 <code>index.php</code>（<code>Theme::exists()</code>），<code>theme.json</code> 提供展示信息。</p>
        <div class="file-tree">
          <span class="dir">templates/mytheme/</span><br>
          &nbsp;&nbsp;<span class="file">theme.json</span> <span class="comment">主题信息（名称/标题/版本/作者/简介/预览图）</span><br>
          &nbsp;&nbsp;<span class="file">index.php</span> <span class="comment">首页模板（必需，判定主题存在与否的依据）</span><br>
          &nbsp;&nbsp;<span class="file">category.php</span> <span class="comment">分类页模板（可选，缺失时回退 default 主题）</span><br>
          &nbsp;&nbsp;<span class="file">site.php</span> <span class="comment">站点详情页模板</span><br>
          &nbsp;&nbsp;<span class="file">search.php</span> <span class="comment">搜索页模板</span><br>
          &nbsp;&nbsp;<span class="file">submit.php</span> <span class="comment">提交站点页模板</span><br>
          &nbsp;&nbsp;<span class="file">wormhole.php</span> <span class="comment">虫洞联盟页模板</span><br>
          &nbsp;&nbsp;<span class="file">article_list.php</span> <span class="comment">文章列表页模板（article 插件启用后生效）</span><br>
          &nbsp;&nbsp;<span class="file">article_detail.php</span> <span class="comment">文章详情页模板</span><br>
          &nbsp;&nbsp;<span class="file">error.php</span> <span class="comment">错误页模板（收到 $code / $message）</span><br>
          &nbsp;&nbsp;<span class="file">header.php</span> <span class="comment">公共头部片段（可选；Theme::partial('header') 加载）</span><br>
          &nbsp;&nbsp;<span class="file">footer.php</span> <span class="comment">公共底部片段（可选；含 before_footer / after_footer 钩子）</span><br>
          &nbsp;&nbsp;<span class="file">404.php</span> <span class="comment">404 兼容页（可选，参考模板）</span><br>
          &nbsp;&nbsp;<span class="dir">css/</span> <span class="comment">样式文件（Theme::asset('css/xxx.css') 引用）</span><br>
          &nbsp;&nbsp;<span class="dir">js/</span> <span class="comment">脚本文件（Theme::asset('js/xxx.js') 引用）</span><br>
          &nbsp;&nbsp;<span class="file">screenshot.png</span> <span class="comment">主题截图（可选，后台主题管理展示）</span>
        </div>
        <p>缺失的模板文件会自动回退到 <code>templates/default/</code> 对应文件（<code>Theme::render()</code> / <code>Theme::partial()</code> 均支持回退），因此可以只覆盖想自定义的页面。直接复制 <code>templates/default/</code> 目录作为新主题起点是最快的做法。</p>
      </section>

      <section id="ch3-2" class="doc-section">
        <h2>3.2 theme.json</h2>
        <p>每个主题可包含 <code>theme.json</code>，由 <code>Theme::getInfo()</code> 读取并合并到默认值（名称/标题/版本/作者/简介），供后台主题管理展示；缺失时使用目录名等默认值。</p>
        <pre><code>{
  "name": "default",
  "title": "我的主题",
  "version": "1.0",
  "author": "你的名字",
  "description": "主题简介",
  "preview": ""
}</code></pre>
        <table>
          <thead><tr><th>字段</th><th>必填</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>name</code></td><td>否</td><td>主题标识（默认取目录名，后台展示用）</td></tr>
            <tr><td><code>title</code></td><td>否</td><td>显示名称（默认取目录名）</td></tr>
            <tr><td><code>version</code></td><td>否</td><td>版本号（默认 1.0）</td></tr>
            <tr><td><code>author</code></td><td>否</td><td>作者</td></tr>
            <tr><td><code>description</code></td><td>否</td><td>简介</td></tr>
            <tr><td><code>preview</code></td><td>否</td><td>预览图 URL 或留空（<code>screenshot.png</code> 会被自动识别为截图）</td></tr>
          </tbody>
        </table>
        <p>除 <code>theme.json</code> 外，系统还会自动检测 <code>index.php / category.php / site.php / search.php / submit.php</code> 是否存在并记录到 <code>files</code> 字段，供后台展示文件齐全度。</p>
      </section>

      <section id="ch3-3" class="doc-section">
        <h2>3.3 模板文件与注入变量</h2>
        <p><code>Route::dispatch()</code> 解析请求后，将 <code>core/Route.php</code> 中各页面方法收集的变量交给 <code>Theme::render('模板名', $vars)</code>，通过 <code>extract()</code> 注入模板作用域，模板中直接使用变量（未注入的变量需用 <code>??</code> 兜底，默认主题即如此）。</p>
        <table>
          <thead><tr><th>模板文件</th><th>路由（rewrite 模式示例）</th><th>注入变量（$data = compact(...)）</th></tr></thead>
          <tbody>
            <tr><td><code>index.php</code></td><td><code>/</code>（home）</td><td>$categories, $activeCats, $featuredSites, $seoTitle, $seoDesc, $seoKeywords, $siteStats, $showWeight, $perCategory, $currentCat, $currentSites, $settings, $ranking</td></tr>
            <tr><td><code>category.php</code></td><td><code>category/{slug}/</code>（分页 <code>page-{n}</code>）</td><td>$category, $sites, $slug, $page, $sort, $total, $totalPages, $perPage, $seoTitle, $seoDesc, $seoKeywords, $showWeight, $categories, $settings</td></tr>
            <tr><td><code>site.php</code></td><td><code>site/{id}/</code></td><td>$site, $category, $related, $categories, $settings, $showWeight, $seoTitle, $seoDesc, $seoKeywords, $ratingStats, $trendData</td></tr>
            <tr><td><code>search.php</code></td><td><code>search/?q=关键词</code></td><td>$keyword, $sites, $page, $total, $totalPages, $perPage, $categories, $settings, $seoTitle, $seoDesc, $seoKeywords</td></tr>
            <tr><td><code>submit.php</code></td><td><code>submit/</code></td><td>$categories, $siteStats, $settings, $enable, $needReview, $seoTitle, $seoDesc, $seoKeywords</td></tr>
            <tr><td><code>wormhole.php</code></td><td><code>wormhole/</code></td><td>$categories, $siteStats, $wormholeStats, $members, $settings, $seoTitle, $seoDesc, $seoKeywords（成员/统计数据依赖 wormhole 插件启用）</td></tr>
            <tr><td><code>article_list.php</code></td><td><code>articles/</code>（article 插件启用后）</td><td>$articles, $page, $total, $totalPages, $perPage, $categories, $settings, $seoTitle, $seoDesc, $seoKeywords</td></tr>
            <tr><td><code>article_detail.php</code></td><td><code>article/{id}/</code>（article 插件启用后）</td><td>$article, $categories, $settings, $seoTitle, $seoDesc, $seoKeywords</td></tr>
            <tr><td><code>error.php</code></td><td>任意错误（404 等）</td><td>$code, $message, $settings</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">模板中可用的公共调用</div>
          <ul>
            <li>转义：<code>Theme::e()</code> / <code>Theme::eAttr()</code>（等同 <code>Security::e()</code> / <code>Security::eAttr()</code>）</li>
            <li>URL 生成：<code>Theme::url()</code>（自动适配三种模式，见 3.6）</li>
            <li>资源引用：<code>Theme::asset()</code></li>
            <li>布局片段：<code>Theme::partial('header')</code> / <code>Theme::partial('footer')</code>（片段内继承页面注入的全部变量，也可显式传参 <code>Theme::partial('header', ['title'=&gt;'xx'])</code>）</li>
            <li>钩子输出：<code>Plugin::hook('钩子名', [参数])</code>（见 3.5）</li>
            <li>全局设置：<code>$settings['site_name']</code> 等（也可用 <code>setting('site_name')</code>）</li>
            <li>辅助函数：<code>setting()</code>、<code>renderSiteCards()</code>、<code>renderSiteIcon()</code>、<code>renderPagination()</code>、<code>formatNumber()</code>、<code>parseTags()</code>、<code>getDisplayDomain()</code>、<code>getMaxBr()</code>、<code>getWeightBadgeClass()</code>、<code>getCategoryUrl()</code> 等（完整清单见 2.4）</li>
          </ul>
        </div>
      </section>

      <section id="ch3-4" class="doc-section">
        <h2>3.4 Theme 类方法</h2>
        <p>全部为静态方法，主题模板与插件中可直接调用。完整清单（<code>core/Theme.php</code>）：</p>
        <table>
          <thead><tr><th>方法</th><th>参数</th><th>返回 / 说明</th></tr></thead>
          <tbody>
            <tr><td><code>Theme::current()</code></td><td>无</td><td><code>string</code> 当前主题名（读取 <code>current_theme</code>，不存在则回退 default）</td></tr>
            <tr><td><code>Theme::set()</code></td><td>$name</td><td><code>bool</code> 切换主题并写入 <code>current_theme</code>（主题不存在返回 false）</td></tr>
            <tr><td><code>Theme::scan()</code></td><td>无</td><td><code>array</code> 扫描 templates/ 下所有可用主题 [name =&gt; info]（按名称排序）</td></tr>
            <tr><td><code>Theme::getInfo()</code></td><td>$name</td><td><code>array</code> 主题信息：name/title/version/author/description/preview/screenshot/files 等</td></tr>
            <tr><td><code>Theme::exists()</code></td><td>$name</td><td><code>bool</code> 目录存在且含 index.php</td></tr>
            <tr><td><code>Theme::render()</code></td><td>$template, $vars=[]</td><td>渲染模板（当前主题缺失自动回退 default，仍缺失输出 500 提示）；由 Route 调用，开发者一般不直接调用</td></tr>
            <tr><td><code>Theme::path()</code></td><td>$template</td><td><code>string</code> 当前主题下模板文件的绝对路径</td></tr>
            <tr><td><code>Theme::partial()</code></td><td>$name, $vars=[]</td><td>加载布局片段（header/footer 等），自动继承页面变量、显式参数优先；当前主题缺失回退 default，再缺失静默跳过</td></tr>
            <tr><td><code>Theme::e()</code></td><td>$value</td><td><code>string</code> HTML 实体转义（等价 <code>Security::e()</code>）</td></tr>
            <tr><td><code>Theme::eAttr()</code></td><td>$value</td><td><code>string</code> HTML 属性值转义（等价 <code>Security::eAttr()</code>）</td></tr>
            <tr><td><code>Theme::url()</code></td><td>$type, $params=[]</td><td><code>string</code> 生成 URL（内部委托 <code>Rewrite::url()</code>，自动适配动态/伪静态模式）</td></tr>
            <tr><td><code>Theme::asset()</code></td><td>$file</td><td><code>string</code> 主题资源 URL（<code>/templates/{当前主题}/{file}</code>）</td></tr>
          </tbody>
        </table>
        <p>模板中未注入变量时建议兜底默认值，例如默认主题 header.php 中：<code>$seoTitle = $seoTitle ?? $settings['site_name'] ?? '懒人导航';</code>。</p>
      </section>

      <section id="ch3-5" class="doc-section">
        <h2>3.5 钩子列表</h2>
        <p>钩子分三类：<strong>前台模板钩子</strong>（主题模板中放置 <code>Plugin::hook()</code> 调用点，插件在此输出内容）、<strong>业务事件钩子</strong>（核心/插件在事件发生时触发，主题通常不感知）、<strong>后台钩子</strong>（后台页面提供，供插件注入菜单与设置 Tab）。</p>
        <h3>前台模板钩子（主题开发必放）</h3>
        <table>
          <thead><tr><th>钩子名</th><th>所在文件 / 位置</th><th>参数</th><th>用途示例</th></tr></thead>
          <tbody>
            <tr><td><code>before_header</code></td><td>header.php：&lt;!DOCTYPE html&gt; 之前</td><td>无</td><td>head 之前注入内容/统计代码</td></tr>
            <tr><td><code>after_header</code></td><td>header.php：&lt;body&gt; 之后</td><td>无</td><td>body 开头注入横幅</td></tr>
            <tr><td><code>search_bar_after</code></td><td>index.php：搜索栏之后</td><td>无</td><td>「提交站点」按钮等</td></tr>
            <tr><td><code>site_list_before</code></td><td>index.php：站点卡片网格之前</td><td>无</td><td>列表上方广告/内容</td></tr>
            <tr><td><code>sidebar_top</code></td><td>index.php：侧边栏分类列表前</td><td>无</td><td>侧边栏顶部广告</td></tr>
            <tr><td><code>sidebar_bottom</code></td><td>index.php：侧边栏分类列表后</td><td>无</td><td>文章入口、虫洞联盟入口、广告等（多插件共享）</td></tr>
            <tr><td><code>site_list_after</code></td><td>index.php：站点卡片网格之后</td><td>无</td><td>列表下方广告/内容</td></tr>
            <tr><td><code>before_content</code></td><td>site.php：详情内容前</td><td>[$site]（当前站点数组）</td><td>详情上方广告/内容</td></tr>
            <tr><td><code>after_content</code></td><td>site.php：详情内容后</td><td>[$site]（当前站点数组）</td><td>详情下方广告/内容</td></tr>
            <tr><td><code>before_footer</code></td><td>footer.php：&lt;footer&gt; 之前</td><td>无</td><td>页脚前内容</td></tr>
            <tr><td><code>after_footer</code></td><td>footer.php：&lt;/body&gt; 之前</td><td>无</td><td>JS 注入（灯箱、自动收录等）</td></tr>
          </tbody>
        </table>
        <p>调用方式（带参数钩子可直接传当前数据，便于插件使用）：</p>
        <pre><code>&lt;?php Plugin::hook('sidebar_top'); ?&gt;
&lt;?php Plugin::hook('before_content', [$site ?? []]); ?&gt;</code></pre>
        <div class="tip">
          <div class="tip-title">集成清单</div>
          <p>要让内置插件（广告 ad、文章 article、友链自动收录 auto-link、虫洞联盟 wormhole、灯箱 lightbox、图片ALT auto-alt、提交收录 submit 等）在自定义主题中全部生效，需要在新主题的 <code>header.php</code>、<code>index.php</code>、<code>site.php</code>、<code>footer.php</code> 中放置上述全部 11 处钩子调用。最稳妥的方式是直接复制默认主题再改样式。</p>
        </div>
        <h3>业务事件钩子（插件间事件通知）</h3>
        <table>
          <thead><tr><th>钩子名</th><th>触发点</th><th>参数</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>site_submitted</code></td><td>前台提交站点 API（api/index.php）</td><td>[['id','name','url','category_id','status','ip','email']]</td><td>站点提交成功时触发</td></tr>
            <tr><td><code>site_approved</code></td><td>后台审核通过（admin/review.php、admin/sites.php）</td><td>[['id','submit_email']]</td><td>站点审核通过时触发</td></tr>
            <tr><td><code>site_rejected</code></td><td>后台审核拒绝（admin/review.php）</td><td>[['id','submit_email']]</td><td>站点审核拒绝时触发</td></tr>
            <tr><td><code>feedback_submitted</code></td><td>前台反馈提交 API（api/index.php）</td><td>[['site_id','type','content','email','ip']]</td><td>用户提交问题反馈时触发</td></tr>
            <tr><td><code>article_editor_before</code> / <code>article_editor_after</code></td><td>article 插件后台编辑表单（plugins/article/admin.php）</td><td>无</td><td>供其他插件向文章编辑器扩展字段</td></tr>
          </tbody>
        </table>
        <h3>后台钩子（供插件注入）</h3>
        <table>
          <thead><tr><th>钩子名</th><th>位置</th><th>参数</th><th>用途</th></tr></thead>
          <tbody>
            <tr><td><code>admin_sidebar</code></td><td>后台侧边栏导航（admin/bootstrap.php）</td><td>无</td><td>注入后台菜单项（用 <code>$GLOBALS['currentPage']</code> 高亮）</td></tr>
            <tr><td><code>admin_settings_nav</code></td><td>基础设置页 Tab 导航（admin/settings.php）</td><td>[$activeTab]</td><td>注入设置 Tab 标签</td></tr>
            <tr><td><code>admin_settings_tabs</code></td><td>基础设置页 Tab 面板</td><td>[$activeTab]</td><td>注入设置 Tab 内容面板</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch3-6" class="doc-section">
        <h2>3.6 URL 生成与资源引用</h2>
        <p>所有前台 URL 必须通过 <code>Theme::url()</code> / <code>Rewrite::url()</code> 生成，系统会自动适配 dynamic / rewrite / index 三种模式，主题内禁止硬编码链接。</p>
        <pre><code>// URL 生成（自动适配当前伪静态模式）
&lt;?= Theme::url('home') ?&gt;                                        // 首页
&lt;?= Theme::url('category', ['slug' =&gt; $cat['slug']]) ?&gt;          // 分类页
&lt;?= Theme::url('site', ['id' =&gt; $site['id'], 'slug' =&gt; $site['category_slug'] ?? '']) ?&gt;  // 站点详情
&lt;?= Theme::url('search', ['q' =&gt; 'AI']) ?&gt;                       // 搜索页
&lt;?= Theme::url('submit') ?&gt;                                      // 提交页
&lt;?= Theme::url('wormhole') ?&gt;                                    // 虫洞联盟页
&lt;?= Theme::url('article_list') ?&gt;                                // 文章列表页
&lt;?= Theme::url('article', ['id' =&gt; 1]) ?&gt;                        // 文章详情页
&lt;?= Theme::url('category_page', ['slug' =&gt; $slug, 'page' =&gt; 2, 'sort' =&gt; 'br']) ?&gt; // 分类分页

// 静态资源引用（基于当前主题目录）
&lt;link rel="stylesheet" href="&lt;?= Theme::asset('css/style.css') ?&gt;"&gt;
&lt;script src="&lt;?= Theme::asset('js/script.js') ?&gt;"&gt;&lt;/script&gt;
&lt;img src="&lt;?= Theme::asset('images/logo.png') ?&gt;"&gt;</code></pre>
        <p>URL 类型与默认格式（rewrite 模式，可在后台 rewrite 插件中自定义 <code>url_format_*</code>）：</p>
        <table>
          <thead><tr><th>type</th><th>默认格式（rewrite 模式）</th><th>占位符 / 说明</th></tr></thead>
          <tbody>
            <tr><td><code>home</code></td><td><code>/</code></td><td>首页</td></tr>
            <tr><td><code>category</code></td><td><code>/category/{%slug%}/</code></td><td>{%slug%} 分类识别名；page&gt;1 时自动切换为分页格式</td></tr>
            <tr><td><code>category_page</code></td><td><code>/category/{%slug%}/page-{%page%}/</code></td><td>{%slug%}、{%page%} 页码</td></tr>
            <tr><td><code>site</code></td><td><code>/site/{%id%}/</code></td><td>{%id%} 站点 ID</td></tr>
            <tr><td><code>search</code></td><td><code>/search/</code></td><td>关键词 q 以查询串传递</td></tr>
            <tr><td><code>submit</code></td><td><code>/submit/</code></td><td>提交收录页</td></tr>
            <tr><td><code>wormhole</code></td><td><code>/wormhole/</code></td><td>虫洞联盟页</td></tr>
            <tr><td><code>article_list</code></td><td><code>/articles/</code></td><td>文章列表页</td></tr>
            <tr><td><code>article</code></td><td><code>/article/{%id%}/</code></td><td>{%id%} 文章 ID</td></tr>
          </tbody>
        </table>
        <p>dynamic 模式生成的等价 URL 形如 <code>/index.php?route=category&amp;slug=tech</code>。分类分页模板中通常这样配合分页函数：</p>
        <pre><code>&lt;?php
$pgTemplate = Theme::url('category_page', ['slug' =&gt; $slug, 'page' =&gt; '%d', 'sort' =&gt; $sort]);
echo renderPagination($page, $totalPages, $pgTemplate);
?&gt;</code></pre>
        <p><code>Rewrite::url()</code> 与 <code>Theme::url()</code> 等价，插件或 PHP 逻辑中可直接使用 <code>Rewrite::url()</code>；<code>Theme::asset()</code> 返回 <code>/templates/{当前主题}/{file}</code> 形式的路径。</p>
      </section>

      <section id="ch3-7" class="doc-section">
        <h2>3.7 实战案例：创建一个主题</h2>
        <p>下面以默认主题 <code>default</code> 的实现模式为参照，从零创建一个可用的「极简主题」。</p>

        <h3>步骤 1：创建目录和 theme.json</h3>
        <pre><code>templates/mytheme/theme.json:
{
  "name": "mytheme",
  "title": "极简主题",
  "version": "1.0",
  "author": "懒人导航",
  "description": "极简风格，专注内容",
  "preview": ""
}</code></pre>

        <h3>步骤 2：编写 header.php（公共头部片段）</h3>
        <p>头部负责 SEO 变量兜底、meta 标签、CSS 引用和插件钩子：</p>
        <pre><code>&lt;?php
// 片段被多页面复用，先兜底 SEO 变量（$settings 由 Route 注入）
if (!isset($seoTitle))    $seoTitle    = $settings['site_name'] ?? '懒人导航';
if (!isset($seoDesc))     $seoDesc     = '';
if (!isset($seoKeywords)) $seoKeywords = '';
?&gt;
&lt;?php Plugin::hook('before_header'); ?&gt;
&lt;!DOCTYPE html&gt;
&lt;html lang="zh-CN"&gt;
&lt;head&gt;
&lt;meta charset="UTF-8"&gt;
&lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
&lt;meta name="csrf-token" content="&lt;?= Security::eAttr(Security::generateCSRFToken()) ?&gt;"&gt;
&lt;title&gt;&lt;?= Theme::e($seoTitle) ?&gt;&lt;/title&gt;
&lt;?php if (!empty($seoDesc)): ?&gt;
&lt;meta name="description" content="&lt;?= Theme::eAttr($seoDesc) ?&gt;"&gt;
&lt;?php endif; ?&gt;
&lt;link rel="stylesheet" href="&lt;?= Theme::asset('css/common.css') ?&gt;"&gt;
&lt;/head&gt;
&lt;body&gt;
&lt;?php Plugin::hook('after_header'); ?&gt;</code></pre>

        <h3>步骤 3：编写 index.php（首页）</h3>
        <p>首页用 <code>Theme::partial('header')</code> / <code>Theme::partial('footer')</code> 引入公共片段，中间按默认主题的方式放置钩子调用与列表渲染（<code>$currentSites</code>、<code>$categories</code>、<code>$ranking</code>、<code>$siteStats</code>、<code>$showWeight</code> 等变量见 3.3）：</p>
        <pre><code>&lt;?php Theme::partial('header'); ?&gt;

&lt;div class="container"&gt;
  &lt;!-- 搜索栏（JS 搜索依赖 #searchInput，可参考默认主题 footer.php 的实现） --&gt;
  &lt;div class="search-bar"&gt;
    &lt;input type="search" id="searchInput" placeholder="搜索站点..."&gt;
    &lt;?php Plugin::hook('search_bar_after'); ?&gt;
  &lt;/div&gt;

  &lt;aside class="sidebar"&gt;
    &lt;?php Plugin::hook('sidebar_top'); ?&gt;
    &lt;?php foreach ($categories as $cat): ?&gt;
      &lt;a href="&lt;?= Theme::url('category', ['slug' =&gt; $cat['slug']]) ?&gt;"&gt;
        &lt;?= Theme::e($cat['name']) ?&gt; (&lt;?= (int)$cat['site_count'] ?&gt;)
      &lt;/a&gt;
    &lt;?php endforeach; ?&gt;
    &lt;?php Plugin::hook('sidebar_bottom'); ?&gt;
  &lt;/aside&gt;

  &lt;main&gt;
    &lt;?php Plugin::hook('site_list_before'); ?&gt;
    &lt;?= renderSiteCards($currentSites ?? [], $showWeight) ?&gt;
    &lt;?php Plugin::hook('site_list_after'); ?&gt;
  &lt;/main&gt;
&lt;/div&gt;

&lt;?php Theme::partial('footer'); ?&gt;</code></pre>
        <p>首页共放置了 5 个钩子：<code>search_bar_after</code>、<code>sidebar_top</code>、<code>sidebar_bottom</code>、<code>site_list_before</code>、<code>site_list_after</code>。默认主题还提供排行榜切换、分类就地切换等 JS 交互（见 <code>templates/default/js/site.js</code> 与 footer.php 内联脚本），追求功能完整可直接复用。</p>

        <h3>步骤 4：编写 footer.php（公共底部片段）</h3>
        <pre><code>&lt;?php Plugin::hook('before_footer'); ?&gt;
&lt;footer&gt;
  &lt;a href="&lt;?= Theme::url('home') ?&gt;"&gt;首页&lt;/a&gt; |
  &lt;a href="&lt;?= Theme::url('submit') ?&gt;"&gt;提交站点&lt;/a&gt;
  &lt;p&gt;&lt;?= Theme::e($settings['site_name'] ?? '') ?&gt;&lt;/p&gt;
&lt;/footer&gt;
&lt;?php Plugin::hook('after_footer'); ?&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>
        <div class="tip">
          <div class="tip-title">钩子位置要求</div>
          <ul>
            <li><code>before_footer</code> / <code>after_footer</code> 必须放在 <code>&lt;/body&gt;</code> 之前</li>
            <li>友链自动收录、灯箱、图片ALT 等插件都通过 <code>after_footer</code> 注入 JS；缺失则这些插件功能失效</li>
            <li>广告插件（ad）依赖 <code>site_list_before/site_list_after/sidebar_top/sidebar_bottom/before_content/after_content</code> 钩子</li>
          </ul>
        </div>

        <h3>步骤 5：编写其他页面</h3>
        <p>按同样模式编写 <code>category.php</code>、<code>site.php</code>、<code>search.php</code>、<code>submit.php</code> 等页面。变量由 <code>Route::dispatch()</code> 自动注入（见 3.3）。站点详情页记得在内容前后放置带站点参数的钩子：</p>
        <pre><code>&lt;?php Plugin::hook('before_content', [$site ?? []]); ?&gt;
&lt;!-- 详情内容 --&gt;
&lt;?php Plugin::hook('after_content', [$site ?? []]); ?&gt;</code></pre>

        <h3>步骤 6：添加 CSS 并在后台切换</h3>
        <p>在 <code>templates/mytheme/css/common.css</code> 中编写样式并通过 <code>Theme::asset('css/common.css')</code> 引用。进入后台「主题管理」，主题列表中会出现「极简主题」，点击启用后前台立即生效。</p>
        <div class="tip success">
          <div class="tip-title">推荐做法</div>
          <p>复制 <code>templates/default/</code> 整个目录改名为新主题再修改，可保证页面齐全（error.php、wormhole.php、article 相关页面等）且不会遗漏钩子与 JS 交互。</p>
        </div>
      </section>

    </section>

    <!-- ===== 第四章 插件开发 ===== -->
    <section id="ch4" class="doc-section">
      <h1>第四章 插件开发 <button class="share-anchor" data-anchor="ch4" title="复制章节链接">🔗</button></h1>

      <section id="ch4-1" class="doc-section">
        <h2>4.1 插件目录结构</h2>
        <p>插件放在 <code>plugins/{插件名}/</code> 目录下，目录名即插件名（小写字母/数字/连字符）。一个完整插件可包含以下文件：</p>
        <div class="file-tree">
          <span class="dir">plugins/myplugin/</span><br>
          &nbsp;&nbsp;<span class="file">plugin.json</span> <span class="comment">元数据声明（必需）</span><br>
          &nbsp;&nbsp;<span class="file">include.php</span> <span class="comment">主文件：类/函数定义 + 钩子注册（可选，由 plugin.json 的 main_file 指定）</span><br>
          &nbsp;&nbsp;<span class="file">main.php</span> <span class="comment">后台设置面板：注册设置 Tab 钩子（可选，由 config_file 指定）</span><br>
          &nbsp;&nbsp;<span class="file">schema.php</span> <span class="comment">数据库声明：表、字段、默认配置（可选，固定文件名）</span><br>
          &nbsp;&nbsp;<span class="file">api.php</span> <span class="comment">开放 API 接口声明（可选）：启用后自动注册 /api/open/* 接口并出现在后台「API 密钥」文档（见 6.1 与 data/docs/api-guide.md）</span><br>
          &nbsp;&nbsp;<span class="file">admin.php</span> <span class="comment">独立后台管理页面（可选；插件目录存在该文件时后台自动显示「管理」按钮）</span><br>
          &nbsp;&nbsp;<span class="file">settings.php</span> <span class="comment">插件内部自用页面/片段（可选，按需命名，如 article/spider 插件）</span><br>
          &nbsp;&nbsp;<span class="dir">css/</span> <span class="comment">插件样式（可选，Plugin::asset() 引用）</span><br>
          &nbsp;&nbsp;<span class="dir">js/</span> <span class="comment">插件脚本（可选）</span>
        </div>
        <div class="tip">
          <div class="tip-title">加载机制</div>
          <ul>
            <li><strong>include.php</strong>：仅在插件启用时由 <code>Plugin::init()</code>（core/bootstrap.php 调用）加载，用于定义类/函数并注册前台与后台钩子</li>
            <li><strong>main.php</strong>：仅在插件启用时随 include.php 一起加载，用于注册设置 Tab 钩子（<code>admin_settings_nav</code> + <code>admin_settings_tabs</code>）</li>
            <li><strong>schema.php</strong>：仅在插件启动（ensureSchema）与卸载（uninstall）时由 <code>Plugin::loadSchema()</code> 加载，返回声明数组</li>
            <li><strong>api.php</strong>：由 <code>core/OpenApi.php</code> 按需加载。插件启用后，其中声明的接口自动注册到 <code>/api/open/*</code>（需 API Key），并自动出现在后台「API 密钥」使用说明；停用后接口失效（返回 403 / 40301）。声明格式与内置参考见 <code>data/docs/plugin-dev.md</code> 与 <code>plugins/article/api.php</code></li>
            <li><strong>admin.php</strong>：通过 <code>/admin/plugin.php?p=插件名</code> 访问。分发器会先校验插件已启用并输出后台公共头尾；由于引导阶段已执行 <code>Plugin::init()</code>，<strong>include.php 中定义的类/函数可直接使用</strong>，无需手动 include</li>
            <li><strong>未启用的插件完全不加载</strong>，不注册任何钩子、不执行任何代码；其 admin.php 也无法访问</li>
          </ul>
        </div>
        <p>所有 PHP 文件开头建议加安全检查，阻止被直接 URL 访问（各内置插件统一写法）：</p>
        <pre><code>&lt;?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}</code></pre>
      </section>

      <section id="ch4-2" class="doc-section">
        <h2>4.2 plugin.json</h2>
        <p>每个插件根目录必须有 <code>plugin.json</code>，由 <code>Plugin::getInfo()</code> 读取并与默认值合并：</p>
        <pre><code>{
    "name": "myplugin",
    "title": "我的插件",
    "version": "1.0",
    "author": "你的名字",
    "description": "插件功能描述",
    "main_file": "include.php",
    "config_file": "main.php",
    "config_tab": "myplugin",
    "schema_file": "schema.php",
    "hooks": ["sidebar_top", "after_footer"],
    "tables": ["mytable"],
    "builtin": true
}</code></pre>
        <table>
          <thead><tr><th>字段</th><th>必填</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>name</code></td><td>是</td><td>插件目录名（必须与文件夹一致），用于启用状态键 <code>plugin_{name}_enabled</code> 与配置前缀</td></tr>
            <tr><td><code>title</code></td><td>是</td><td>显示名称（默认取目录名）</td></tr>
            <tr><td><code>version</code></td><td>否</td><td>版本号（默认 1.0）</td></tr>
            <tr><td><code>author</code></td><td>否</td><td>作者</td></tr>
            <tr><td><code>description</code></td><td>否</td><td>功能描述（后台插件列表展示）</td></tr>
            <tr><td><code>main_file</code></td><td>否</td><td>主文件名（默认 <code>{name}.php</code>，内置插件统一设为 <code>include.php</code>）</td></tr>
            <tr><td><code>config_file</code></td><td>否</td><td>后台设置面板文件名（如 <code>main.php</code>）；无设置项则不填</td></tr>
            <tr><td><code>config_tab</code></td><td>否</td><td>设置 Tab 的 ID（默认等于插件名）；当 Tab ID 与插件名不同时需指定，后台「设置」按钮据此跳转（admin/plugins.php）</td></tr>
            <tr><td><code>schema_file</code></td><td>否</td><td>信息性字段——系统实际固定读取 <code>schema.php</code>，无需配置</td></tr>
            <tr><td><code>hooks</code></td><td>否</td><td>声明使用的钩子列表（后台插件列表展示，不影响实际注册——注册靠 include.php/main.php 中的 <code>Plugin::registerHook()</code>）</td></tr>
            <tr><td><code>tables</code></td><td>否</td><td>声明创建的表名列表（卸载共享表判断的补充来源）</td></tr>
            <tr><td><code>builtin</code></td><td>否</td><td>是否为内置插件（默认 true，后台列表显示「内置」标签）</td></tr>
          </tbody>
        </table>
        <p>注意：插件启用状态不写在 plugin.json 中，而是由系统管理在 settings 表（<code>plugin_{name}_enabled</code>，安装时写入 0）。</p>
      </section>

      <section id="ch4-3" class="doc-section">
        <h2>4.3 schema.php 机制</h2>
        <p><code>schema.php</code> 是插件数据库声明文件，返回一个包含三个部分的数组：</p>
        <pre><code>&lt;?php
return [
    // 1. 独立表：插件启用时自动 CREATE TABLE IF NOT EXISTS
    'tables' =&gt; [
        'mytable' =&gt; "CREATE TABLE IF NOT EXISTS `{prefix}mytable` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_title (title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ],

    // 2. 向已有表添加字段：插件启用时自动 ALTER TABLE ADD COLUMN（跳过已存在）
    'columns' =&gt; [
        'sites' =&gt; [
            'my_field' =&gt; "VARCHAR(100) DEFAULT '' COMMENT '自定义字段'",
        ],
    ],

    // 3. 默认配置项：插件启用时写入 settings 表（仅当配置项不存在时写入）
    'config' =&gt; [
        'plugin_myplugin_count'  =&gt; '5',
        'plugin_myplugin_enable' =&gt; '1',
    ],
];</code></pre>

        <h3>ensureSchema() 执行流程</h3>
        <p>当插件被启用时，<code>Plugin::setEnabled($name, true)</code> 会自动调用 <code>Plugin::ensureSchema($name)</code>：</p>
        <ol>
          <li><strong>创建独立表</strong>：遍历 <code>tables</code>，通过 <code>information_schema</code> 检查表是否存在，不存在则执行 SQL（<code>{prefix}</code> 占位符替换为实际表前缀）</li>
          <li><strong>添加字段</strong>：遍历 <code>columns</code>，通过 <code>information_schema.columns</code> 检查字段是否存在，不存在则 <code>ALTER TABLE ADD COLUMN</code></li>
          <li><strong>写入配置</strong>：遍历 <code>config</code>，检查 settings 表中是否已存在该 key，不存在则写入默认值</li>
        </ol>
        <div class="tip">
          <div class="tip-title">幂等安全</div>
          <p>所有操作都是幂等的：表已存在则跳过，字段已存在则跳过，配置已存在则跳过。因此重复启用插件不会出错，也不会覆盖用户已修改的配置。</p>
        </div>

        <h3>配置项命名规范</h3>
        <p>插件配置项建议使用 <code>plugin_{插件名}_{配置键}</code> 的命名格式存储在 settings 表中（schema.php 的 <code>config</code> 中直接写完整 key，如 <code>'plugin_myplugin_count'</code>），并配合 <code>Plugin::config('myplugin', 'count', 默认值)</code> 读取——该方法内部即拼接 <code>plugin_{plugin}_{key}</code> 前缀。</p>
        <p>少数早期插件（如 auto-link 的 <code>autolink_enable</code>、<code>autolink_need_review</code>、<code>autolink_default_category</code>、<code>autolink_banned_words</code>）仍沿用不带前缀的键名，由后台 <code>admin/settings.php</code> 对应 case 直接保存。两种方式都可用，新插件请统一使用 <code>plugin_</code> 前缀。</p>
      </section>

      <section id="ch4-4" class="doc-section">
        <h2>4.4 include.php 与钩子注册</h2>
        <p><code>include.php</code> 是插件主文件（<code>main_file</code>），负责定义类/函数并注册钩子：</p>
        <pre><code>&lt;?php
// 安全检查：阻止直接访问（所有插件文件统一写法）
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 输出插件内容（可用 Plugin::config 读取配置）
 */
function myplugin_render(): void
{
    $count = (int)Plugin::config('myplugin', 'count', '5');
    // ... 业务逻辑（可用 Database::query 等核心类）
    echo '&lt;div class="myplugin"&gt;内容&lt;/div&gt;';
}

// 注册前台动作钩子（第 3 个参数为优先级，数字越小越先执行，默认 10）
Plugin::registerHook('sidebar_top', function () {
    myplugin_render();
});

// 注册过滤钩子（返回模式：返回值作为下一个回调的入参）
Plugin::registerHook('filter_title', function ($title) {
    return $title . ' - 我的插件';
});

// 注册后台侧边栏菜单（进入独立管理页 /admin/plugin.php?p=myplugin）
Plugin::registerHook('admin_sidebar', function () {
    $cls = ($GLOBALS['currentPage'] ?? '') === 'myplugin' ? 'active' : '';
    echo '&lt;a href="/admin/plugin.php?p=myplugin" class="nav-item ' . $cls . '"&gt;'
       . '&lt;i class="ti ti-star"&gt;&lt;/i&gt;&lt;span&gt;我的插件&lt;/span&gt;&lt;/a&gt;';
});</code></pre>
        <div class="tip">
          <div class="tip-title">钩子机制关键点</div>
          <ul>
            <li>注册：<code>Plugin::registerHook($hook, $callback, $priority = 10)</code>；执行动作钩子 <code>Plugin::hook($hook, $args = [])</code>；执行过滤钩子 <code>Plugin::filter($hook, $value, $args = [])</code></li>
            <li>模板/页面触发钩子时传入的参数数组会原样展开传给每个回调，例如 <code>Plugin::hook('before_content', [$site])</code> 中回调收到 <code>$site</code>；回调可忽略多余参数</li>
            <li>同一钩子可被多个插件注册，按优先级排序执行；单个回调抛异常只记录 <code>plugin_error</code> 日志，不影响其他回调</li>
            <li><code>Plugin::hasHook($hook)</code> 可判断钩子是否已有注册回调；<code>Plugin::addFilter()</code> 是 <code>registerHook</code> 的语义别名</li>
            <li>echo 的内容会直接输出到页面；事件类钩子（site_submitted 等）回调也可不输出，仅做逻辑处理（参考 notify 插件）</li>
          </ul>
        </div>
        <p>可用钩子全集与参数见 3.5 节。</p>
      </section>

      <section id="ch4-5" class="doc-section">
        <h2>4.5 main.php 设置面板</h2>
        <p><code>main.php</code>（<code>config_file</code>）通过注册两个后台钩子，在基础设置页面注入自定义 Tab：</p>
        <pre><code>&lt;?php
if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// 钩子1：注入 Tab 导航标签（tab ID 默认等于插件名）
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    echo '&lt;a href="#tab-myplugin" class="settings-tab ' . $cls . '"'
       . ' onclick="switchTab(\'myplugin\', this)"&gt;我的插件&lt;/a&gt;';
});

// 钩子2：注入 Tab 内容面板
Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $cls = $activeTab === 'myplugin' ? 'active' : '';
    ?&gt;
&lt;div id="tab-myplugin" class="tab-panel &lt;?= $cls ?&gt;"&gt;
  &lt;div class="card"&gt;
    &lt;div class="card-header"&gt;&lt;span class="card-title"&gt;我的插件设置&lt;/span&gt;&lt;/div&gt;
    &lt;form method="POST" action="/admin/settings.php"&gt;
      &lt;input type="hidden" name="csrf_token" value="&lt;?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?&gt;"&gt;
      &lt;input type="hidden" name="section" value="myplugin"&gt;
      &lt;input type="hidden" name="tab" value="myplugin"&gt;

      &lt;div class="form-group"&gt;
        &lt;label&gt;显示数量&lt;/label&gt;
        &lt;input type="number" class="form-input" name="plugin_myplugin_count"
               value="&lt;?= Security::eAttr(Plugin::config('myplugin', 'count', '5')) ?&gt;"&gt;
      &lt;/div&gt;

      &lt;div class="text-right"&gt;
        &lt;button type="submit" class="btn btn-primary"&gt;保存&lt;/button&gt;
      &lt;/div&gt;
    &lt;/form&gt;
  &lt;/div&gt;
&lt;/div&gt;
&lt;?php
});</code></pre>
        <div class="tip">
          <div class="tip-title">表单保存约定（重要）</div>
          <ul>
            <li>Tab 面板表单 POST 到 <code>/admin/settings.php</code>，必须带 <code>csrf_token</code>、<code>section</code>（插件名）、<code>tab</code> 三个隐藏字段</li>
            <li><code>admin/settings.php</code> 的 <code>switch ($section)</code> 需要存在对应的 <code>case 'myplugin'</code> 分支来读取并保存字段（内置插件如 rewrite/sitemap/ad/autolink/submit/notify 均在其中有对应 case）；<strong>没有对应 case 时表单内容不会被保存</strong></li>
            <li>保存时用 <code>$settingsModel-&gt;setMany([...])</code> 或 <code>Plugin::setConfig('myplugin', 'count', $value)</code>（等价写入 <code>plugin_myplugin_count</code>）</li>
            <li>Tab 内容面板 <code>id</code> 必须为 <code>tab-{tabId}</code>，与导航标签 <code>href="#tab-{tabId}"</code> 对应；tabId 默认插件名，不同时在 plugin.json 用 <code>config_tab</code> 声明</li>
            <li><code>switchTab()</code> 是后台内置 JS 函数；面板类名沿用 <code>card / card-header / card-title / form-group / form-input / text-right / btn btn-primary</code> 等后台样式</li>
          </ul>
        </div>
      </section>

      <section id="ch4-6" class="doc-section">
        <h2>4.6 Plugin 类 API</h2>
        <p>以下为 <code>core/Plugin.php</code> 的全部公开静态方法（插件代码与模板中均可调用）：</p>
        <table>
          <thead><tr><th>方法</th><th>参数</th><th>返回 / 说明</th></tr></thead>
          <tbody>
            <tr><td><code>Plugin::init()</code></td><td>无</td><td>初始化：扫描并加载全部<strong>已启用</strong>插件的 include.php / main.php（bootstrap 自动调用，勿手动重复调用）</td></tr>
            <tr><td><code>Plugin::scan()</code></td><td>无</td><td><code>array</code> 扫描 plugins/ 下所有含 plugin.json 的插件 [name =&gt; info]（含 enabled/dir 等）</td></tr>
            <tr><td><code>Plugin::getInfo()</code></td><td>$name</td><td><code>?array</code> 插件元数据（无 plugin.json 返回 null）</td></tr>
            <tr><td><code>Plugin::isEnabled()</code></td><td>$name</td><td><code>bool</code> 检查 <code>plugin_{name}_enabled</code> 是否为 1</td></tr>
            <tr><td><code>Plugin::setEnabled()</code></td><td>$name, $enabled</td><td>启停插件（启动时自动 <code>ensureSchema()</code>）</td></tr>
            <tr><td><code>Plugin::ensureSchema()</code></td><td>$name</td><td>安装数据库结构：建表 / 加字段 / 写默认配置（幂等）</td></tr>
            <tr><td><code>Plugin::loadSchema()</code></td><td>$name</td><td><code>array</code> 加载 schema.php，返回 ['tables','columns','config']</td></tr>
            <tr><td><code>Plugin::ensureTables()</code></td><td>$name</td><td>旧接口别名（内部转发 ensureSchema，已弃用）</td></tr>
            <tr><td><code>Plugin::uninstall()</code></td><td>$name</td><td><code>array</code> 卸载：停用 + 删自建表（共享表跳过）+ 删字段 + 清配置，返回 ['success','dropped_tables','dropped_columns','cleared_keys']</td></tr>
            <tr><td><code>Plugin::getEnabledPlugins()</code></td><td>无</td><td><code>array</code> 所有已启用插件 [name =&gt; info]</td></tr>
            <tr><td><code>Plugin::registerHook()</code></td><td>$hook, $callback, $priority=10</td><td>注册钩子回调（动作/过滤通用）</td></tr>
            <tr><td><code>Plugin::addFilter()</code></td><td>$hook, $callback, $priority=10</td><td>registerHook 的语义别名</td></tr>
            <tr><td><code>Plugin::hook()</code></td><td>$hook, $args=[]</td><td>执行动作钩子：按优先级依次调用回调并输出</td></tr>
            <tr><td><code>Plugin::filter()</code></td><td>$hook, $value, $args=[]</td><td><code>mixed</code> 执行过滤钩子：值链式经过所有回调后返回</td></tr>
            <tr><td><code>Plugin::hasHook()</code></td><td>$hook</td><td><code>bool</code> 钩子是否已有注册回调</td></tr>
            <tr><td><code>Plugin::config()</code></td><td>$plugin, $key, $default=null</td><td>读取插件配置（拼接 <code>plugin_{plugin}_{key}</code>）</td></tr>
            <tr><td><code>Plugin::setConfig()</code></td><td>$plugin, $key, $value</td><td>写入插件配置</td></tr>
            <tr><td><code>Plugin::getDir()</code></td><td>$name</td><td><code>string</code> 插件目录绝对路径</td></tr>
            <tr><td><code>Plugin::asset()</code></td><td>$plugin, $file</td><td><code>string</code> 插件资源 URL（/plugins/{plugin}/{file}）</td></tr>
            <tr><td><code>Plugin::clearCache()</code></td><td>无</td><td>清除扫描缓存（后台启停/卸载后调用）</td></tr>
          </tbody>
        </table>
        <p>数据库与安全的通用调用（插件内最常用）：<code>Database::table('表名')</code>（带前缀表名）、<code>Database::query()</code> / <code>queryOne()</code> / <code>execute()</code> / <code>insert()</code> / <code>scalar()</code>、<code>Security::e()</code> / <code>eAttr()</code> / <code>cleanString()</code> / <code>cleanHtml()</code> / <code>int()</code>、<code>setting()</code> / <code>Rewrite::url()</code>。</p>
      </section>

      <section id="ch4-7" class="doc-section">
        <h2>4.7 共享表与卸载</h2>
        <p>当多个插件声明同一张表时（如 <code>blacklist</code> 表被 <code>wormhole</code> 和 <code>auto-link</code> 共同声明），系统会智能处理：</p>
        <ul>
          <li><strong>启动时</strong>：<code>ensureSchema()</code> 使用 <code>CREATE TABLE IF NOT EXISTS</code>，重复执行安全</li>
          <li><strong>卸载时</strong>：<code>Plugin::uninstall()</code> 通过 <code>getPluginsDeclaringTable()</code> 检查是否还有其他插件（无论启用与否）声明该表（schema.php tables + plugin.json tables 均计入）。只要有，就跳过删表</li>
          <li><strong>全部卸载时</strong>：当最后一个声明该表的插件被卸载时，才真正执行 <code>DROP TABLE</code></li>
        </ul>
        <p><code>Plugin::uninstall($name)</code> 的完整流程与返回：</p>
        <ol>
          <li>停用插件（<code>plugin_{name}_enabled = 0</code>）</li>
          <li>删除自建表（智能处理共享表，见上）</li>
          <li>删除 schema.php <code>columns</code> 声明添加到已有表的字段（<code>ALTER TABLE DROP COLUMN</code>）</li>
          <li>清除配置：删除 <code>plugin_{name}_%</code> 前缀通配命中项 + schema.php <code>config</code> 声明的 key</li>
          <li>清除扫描缓存，写 <code>plugin_uninstall</code> 频道日志</li>
          <li>返回 <code>['success' =&gt; bool, 'dropped_tables' =&gt; [], 'dropped_columns' =&gt; [], 'cleared_keys' =&gt; int]</code>（后台插件管理页据此展示结果）</li>
        </ol>
        <p>插件文件本身不会被删除，卸载后可随时重新启动（重新执行 ensureSchema 安装结构与默认配置）。</p>
      </section>

      <section id="ch4-8" class="doc-section">
        <h2>4.8 实战案例：每日一言插件</h2>
        <p>下面创建一个完整的「每日一言」插件，展示从 plugin.json 到 schema.php、include.php、main.php 的完整开发流程。</p>
        <p>功能：前台侧边栏显示每日一条名言，后台可管理名言列表和显示设置。</p>

        <h3>4.8.1 创建目录</h3>
        <pre><code>plugins/daily-quote/
    plugin.json
    schema.php
    include.php
    main.php
    admin.php</code></pre>

        <h3>4.8.2 plugin.json</h3>
        <pre><code>{
    "name": "daily-quote",
    "title": "每日一言",
    "version": "1.0",
    "author": "懒人导航",
    "description": "前台侧边栏显示每日名言，后台可管理名言列表。",
    "main_file": "include.php",
    "config_file": "main.php",
    "schema_file": "schema.php",
    "hooks": ["sidebar_bottom", "admin_sidebar"],
    "tables": ["quotes"],
    "builtin": false
}</code></pre>

        <h3>4.8.3 schema.php</h3>
        <p>声明一张独立的 <code>quotes</code> 表和 2 个默认配置项：</p>
        <pre><code>&lt;?php
/**
 * 每日一言插件 - 数据库声明
 * 启用插件时自动创建 quotes 表并写入默认配置
 */

return [
    // 独立表
    'tables' =&gt; [
        'quotes' =&gt; "CREATE TABLE IF NOT EXISTS `{prefix}quotes` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content VARCHAR(500) NOT NULL COMMENT '名言内容',
            author VARCHAR(100) DEFAULT '' COMMENT '作者',
            status TINYINT DEFAULT 1 COMMENT '状态(1=启用,0=禁用)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日言表';",
    ],

    // 向已有表添加字段（无）
    'columns' =&gt; [],

    // 默认配置项
    'config' =&gt; [
        'plugin_daily-quote_count'    =&gt; '1',
        'plugin_daily-quote_template' =&gt; 'card',
    ],
];</code></pre>
        <div class="tip">
          <div class="tip-title">说明</div>
          <ul>
            <li><code>{prefix}</code> 占位符在执行时被替换为实际表前缀（如 <code>nav_</code>）</li>
            <li>配置项 key 使用 <code>plugin_{插件名}_{配置键}</code> 格式</li>
            <li>表和字段声明中包含 <code>COMMENT</code> 方便数据库管理</li>
          </ul>
        </div>

        <h3>4.8.4 include.php</h3>
        <p>定义 Model 类、注册前台钩子和后台侧边栏入口：</p>
        <pre><code>&lt;?php
/**
 * 每日一言插件 - 主文件
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 每日一言模型
 */
class DailyQuoteModel
{
    /**
     * 获取随机名言
     */
    public function getRandom(int $limit = 1): array
    {
        $tbl = Database::table('quotes');
        return Database::query(
            "SELECT * FROM {$tbl} WHERE status = 1 ORDER BY RAND() LIMIT ?",
            [$limit]
        );
    }

    /**
     * 获取全部名言
     */
    public function getAll(): array
    {
        $tbl = Database::table('quotes');
        return Database::query("SELECT * FROM {$tbl} ORDER BY id DESC");
    }

    /**
     * 创建名言
     */
    public function create(string $content, string $author = ''): int
    {
        $tbl = Database::table('quotes');
        return Database::insert(
            "INSERT INTO {$tbl} (content, author) VALUES (?, ?)",
            [Security::cleanString($content, 500), Security::cleanString($author, 100)]
        );
    }

    /**
     * 删除名言
     */
    public function delete(int $id): bool
    {
        $tbl = Database::table('quotes');
        return Database::execute("DELETE FROM {$tbl} WHERE id = ?", [$id]) &gt; 0;
    }

    /**
     * 切换状态
     */
    public function toggleStatus(int $id): bool
    {
        $tbl = Database::table('quotes');
        return Database::execute(
            "UPDATE {$tbl} SET status = 1 - status WHERE id = ?",
            [$id]
        ) &gt; 0;
    }
}

// ========== 钩子注册 ==========

// 前台侧边栏：显示每日一言
Plugin::registerHook('sidebar_bottom', function () {
    $count = (int)Plugin::config('daily-quote', 'count', '1');
    $model = new DailyQuoteModel();
    $quotes = $model-&gt;getRandom($count);

    if (empty($quotes)) {
        return;
    }
    ?&gt;
    &lt;div class="daily-quote-widget" style="margin-top:16px;padding:12px;border-radius:8px;background:#f0f4ff;"&gt;
        &lt;div style="font-size:13px;color:#999;margin-bottom:8px;"&gt;
            &lt;i class="ti ti-quote"&gt;&lt;/i&gt; 每日一言
        &lt;/div&gt;
        &lt;?php foreach ($quotes as $q): ?&gt;
        &lt;div class="quote-item" style="margin-bottom:8px;"&gt;
            &lt;p style="font-size:14px;color:#333;line-height:1.6;"&gt;
                &lt;?= Theme::e($q['content']) ?&gt;
            &lt;/p&gt;
            &lt;?php if (!empty($q['author'])): ?&gt;
            &lt;p style="font-size:12px;color:#999;text-align:right;"&gt;
                —— &lt;?= Theme::e($q['author']) ?&gt;
            &lt;/p&gt;
            &lt;?php endif; ?&gt;
        &lt;/div&gt;
        &lt;?php endforeach; ?&gt;
    &lt;/div&gt;
    &lt;?php
});

// 后台侧边栏：注入管理入口
Plugin::registerHook('admin_sidebar', function () {
    $cls = ($GLOBALS['currentPage'] ?? '') === 'daily-quote' ? 'active' : '';
    echo '&lt;a href="/admin/plugin.php?p=daily-quote" class="nav-item ' . $cls . '"&gt;'
       . '&lt;i class="ti ti-quote"&gt;&lt;/i&gt;&lt;span&gt;每日一言&lt;/span&gt;&lt;/a&gt;';
});</code></pre>

        <h3>4.8.5 main.php（后台设置 Tab）</h3>
        <pre><code>&lt;?php
/**
 * 每日一言插件 - 设置面板
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

// 注入 Tab 导航
Plugin::registerHook('admin_settings_nav', function ($activeTab) {
    $cls = $activeTab === 'daily-quote' ? 'active' : '';
    echo '&lt;a href="#tab-daily-quote" class="settings-tab ' . $cls . '"'
       . ' onclick="switchTab(\'daily-quote\', this)"&gt;每日一言&lt;/a&gt;';
});

// 注入 Tab 内容
Plugin::registerHook('admin_settings_tabs', function ($activeTab) {
    $cls = $activeTab === 'daily-quote' ? 'active' : '';
    ?&gt;
&lt;div id="tab-daily-quote" class="tab-panel &lt;?= $cls ?&gt;"&gt;
  &lt;div class="card"&gt;
    &lt;div class="card-header"&gt;&lt;span class="card-title"&gt;每日一言设置&lt;/span&gt;&lt;/div&gt;
    &lt;form method="POST" action="/admin/settings.php"&gt;
      &lt;input type="hidden" name="csrf_token" value="&lt;?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?&gt;"&gt;
      &lt;input type="hidden" name="section" value="daily-quote"&gt;
      &lt;input type="hidden" name="tab" value="daily-quote"&gt;

      &lt;div class="form-group"&gt;
        &lt;label&gt;显示数量&lt;/label&gt;
        &lt;input type="number" name="plugin_daily-quote_count" min="1" max="10"
               value="&lt;?= Security::e(Plugin::config('daily-quote', 'count', '1')) ?&gt;"&gt;
        &lt;p class="form-help"&gt;侧边栏每次显示几条名言&lt;/p&gt;
      &lt;/div&gt;

      &lt;div class="text-right"&gt;
        &lt;button type="submit" class="btn btn-primary"&gt;保存设置&lt;/button&gt;
      &lt;/div&gt;
    &lt;/form&gt;
  &lt;/div&gt;
&lt;/div&gt;
&lt;?php
});</code></pre>

        <div class="tip">
          <div class="tip-title">别忘了保存分支</div>
          <p>Tab 表单 POST 到 <code>/admin/settings.php</code> 后，需要在 <code>admin/settings.php</code> 的 <code>switch ($section)</code> 中添加 <code>case 'daily-quote'</code> 分支读取 <code>plugin_daily-quote_count</code> 并保存（参考 4.5 节的保存约定）。</p>
        </div>

        <h3>4.8.6 admin.php（名言管理页面）</h3>
        <pre><code>&lt;?php
/**
 * 每日一言插件 - 后台管理页面
 * 通过 /admin/plugin.php?p=daily-quote 访问
 */

$model = new DailyQuoteModel();

// 处理 POST 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF 校验失败');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $model-&gt;create($_POST['content'] ?? '', $_POST['author'] ?? '');
        echo '&lt;div class="alert alert-success"&gt;添加成功&lt;/div&gt;';
    } elseif ($action === 'delete') {
        $model-&gt;delete((int)($_POST['id'] ?? 0));
        echo '&lt;div class="alert alert-success"&gt;删除成功&lt;/div&gt;';
    } elseif ($action === 'toggle') {
        $model-&gt;toggleStatus((int)($_POST['id'] ?? 0));
    }
}

$quotes = $model-&gt;getAll();
?&gt;

&lt;div class="card"&gt;
  &lt;div class="card-header"&gt;&lt;span class="card-title"&gt;添加名言&lt;/span&gt;&lt;/div&gt;
  &lt;form method="POST"&gt;
    &lt;input type="hidden" name="csrf_token" value="&lt;?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?&gt;"&gt;
    &lt;input type="hidden" name="action" value="create"&gt;
    &lt;div class="form-group"&gt;
      &lt;label&gt;内容&lt;/label&gt;
      &lt;textarea name="content" rows="3" required&gt;&lt;/textarea&gt;
    &lt;/div&gt;
    &lt;div class="form-group"&gt;
      &lt;label&gt;作者（可选）&lt;/label&gt;
      &lt;input type="text" name="author"&gt;
    &lt;/div&gt;
    &lt;button type="submit" class="btn btn-primary"&gt;添加&lt;/button&gt;
  &lt;/form&gt;
&lt;/div&gt;

&lt;div class="card" style="margin-top:16px;"&gt;
  &lt;div class="card-header"&gt;&lt;span class="card-title"&gt;名言列表&lt;/span&gt;&lt;/div&gt;
  &lt;table&gt;
    &lt;thead&gt;&lt;tr&gt;&lt;th&gt;ID&lt;/th&gt;&lt;th&gt;内容&lt;/th&gt;&lt;th&gt;作者&lt;/th&gt;&lt;th&gt;状态&lt;/th&gt;&lt;th&gt;操作&lt;/th&gt;&lt;/tr&gt;&lt;/thead&gt;
    &lt;tbody&gt;
    &lt;?php foreach ($quotes as $q): ?&gt;
      &lt;tr&gt;
        &lt;td&gt;&lt;?= (int)$q['id'] ?&gt;&lt;/td&gt;
        &lt;td&gt;&lt;?= Security::e($q['content']) ?&gt;&lt;/td&gt;
        &lt;td&gt;&lt;?= Security::e($q['author']) ?&gt;&lt;/td&gt;
        &lt;td&gt;&lt;?= $q['status'] ? '启用' : '禁用' ?&gt;&lt;/td&gt;
        &lt;td&gt;
          &lt;form method="POST" style="display:inline"&gt;
            &lt;input type="hidden" name="csrf_token" value="&lt;?= Security::eAttr($_SESSION['csrf_token'] ?? '') ?&gt;"&gt;
            &lt;input type="hidden" name="action" value="delete"&gt;
            &lt;input type="hidden" name="id" value="&lt;?= (int)$q['id'] ?&gt;"&gt;
            &lt;button type="submit" onclick="return confirm('确认删除?')"&gt;删除&lt;/button&gt;
          &lt;/form&gt;
        &lt;/td&gt;
      &lt;/tr&gt;
    &lt;?php endforeach; ?&gt;
    &lt;/tbody&gt;
  &lt;/table&gt;
&lt;/div&gt;</code></pre>

        <h3>4.8.7 启动插件</h3>
        <ol>
          <li>将 <code>daily-quote</code> 目录放入 <code>plugins/</code></li>
          <li>进入后台「插件管理」，找到「每日一言」</li>
          <li>点击「启动」——系统自动创建 <code>quotes</code> 表、写入 2 条默认配置、加载插件代码</li>
          <li>前台首页侧边栏底部出现每日一言</li>
          <li>后台侧边栏出现「每日一言」管理入口，可添加/删除名言</li>
          <li>后台「基础设置」出现「每日一言」Tab，可修改显示数量（保存分支需在 settings.php 增加 case，见 4.5）</li>
        </ol>
        <div class="tip success">
          <div class="tip-title">完整流程回顾</div>
          <p>这个案例展示了插件开发的完整流程：<code>plugin.json</code> 声明元数据 → <code>schema.php</code> 声明数据库 → <code>include.php</code> 定义业务逻辑和钩子 → <code>main.php</code> 后台设置面板 → <code>admin.php</code> 后台管理页面。启用后自动安装，卸载后自动清理。</p>
        </div>
      </section>

    </section>

    <!-- ===== 第五章 内置插件 ===== -->
    <section id="ch5" class="doc-section">
      <h1>第五章 内置插件 <button class="share-anchor" data-anchor="ch5" title="复制章节链接">🔗</button></h1>

      <section id="ch5-1" class="doc-section">
        <h2>5.1 插件一览</h2>
        <p>系统内置 13 个插件，全部默认关闭（安装时写入 <code>plugin_{name}_enabled=0</code>）。安装后根据需要在后台「插件管理」中启动，启用时才执行建表/加字段/写默认配置。</p>
        <table>
          <thead><tr><th>插件</th><th>功能</th><th>数据库影响</th><th>主要钩子</th></tr></thead>
          <tbody>
            <tr><td>广告管理 (ad)</td><td>后台配置 6 个广告位 HTML，前台对应位置展示</td><td>6 条配置（plugin_ad_*），无独立表</td><td>site_list_before/after、sidebar_top/bottom、before/after_content</td></tr>
            <tr><td>文章发布 (article)</td><td>后台文章管理，前台文章列表/详情</td><td>articles 表 + 2 条配置</td><td>sidebar_bottom、before_footer、admin_sidebar</td></tr>
            <tr><td>虫洞联盟 (wormhole)</td><td>站点互推、随机传送、定时检测、黑名单</td><td>blacklist 表（与 auto-link 共享）+ sites 表 10 字段 + 5 条配置</td><td>sidebar_bottom、admin_sidebar</td></tr>
            <tr><td>友链自动收录 (auto-link)</td><td>检测来路、验证回链、抓取 TDK、自动收录</td><td>blacklist 表（共享）+ 4 条配置（autolink_*）</td><td>after_footer、admin_settings_nav/tabs</td></tr>
            <tr><td>伪静态设置 (rewrite)</td><td>URL 模式与格式配置，自动生成服务器规则</td><td>10 条配置（rewrite_mode + 9 个 url_format_*）</td><td>admin_settings_nav/tabs</td></tr>
            <tr><td>提交网站收录 (submit)</td><td>前台提交入口/表单、审核流程、频率限制</td><td>9 条配置（plugin_submit_*）</td><td>search_bar_after、admin_settings_nav/tabs</td></tr>
            <tr><td>网站地图 (sitemap)</td><td>生成 sitemap.xml / robots.txt，支持缓存与分片</td><td>少量配置</td><td>admin_settings_nav/tabs</td></tr>
            <tr><td>图片灯箱 (lightbox)</td><td>详情/文章页图片点击放大</td><td>无数据库影响</td><td>after_footer</td></tr>
            <tr><td>图片ALT (auto-alt)</td><td>自动给无 alt 的 img 补填描述</td><td>无数据库影响</td><td>after_footer</td></tr>
            <tr><td>邮箱通知 (notify)</td><td>SMTP 邮件：提交/审核/反馈事件自动通知</td><td>notify_logs 表 + sites.submit_email 字段 + 配置</td><td>site_submitted / site_approved / site_rejected / feedback_submitted、admin_settings_nav/tabs</td></tr>
            <tr><td>友情链接 (friendlink)</td><td>友链管理，前台底部展示</td><td>friendlinks 表 + 配置</td><td>before_footer、after_footer、admin_sidebar</td></tr>
            <tr><td>蜘蛛来访 (spider)</td><td>搜索引擎蜘蛛来访统计（30 天保留）</td><td>spider_visits 表 + 配置</td><td>before_header、admin_sidebar</td></tr>
            <tr><td>数据库备份 (dbtool)</td><td>一键备份/恢复/下载/导入数据库 SQL</td><td>无自建表</td><td>无（后台经插件管理「管理」按钮进入）</td></tr>
          </tbody>
        </table>
        <p>钩子列 = 插件实际注册的钩子；主题需要为前台钩子保留调用点（见 3.5），后台钩子（admin_*）由系统页面自动触发。</p>
      </section>

      <section id="ch5-2" class="doc-section">
        <h2>5.2 广告管理 (ad)</h2>
        <p>后台可配置 6 个广告位的 HTML 代码，前台在对应位置展示。</p>
        <table>
          <thead><tr><th>广告位</th><th>钩子位置</th></tr></thead>
          <tbody>
            <tr><td>首页列表前</td><td><code>site_list_before</code></td></tr>
            <tr><td>首页列表后</td><td><code>site_list_after</code></td></tr>
            <tr><td>侧边栏顶部</td><td><code>sidebar_top</code></td></tr>
            <tr><td>侧边栏底部</td><td><code>sidebar_bottom</code></td></tr>
            <tr><td>详情内容前</td><td><code>before_content</code></td></tr>
            <tr><td>详情内容后</td><td><code>after_content</code></td></tr>
          </tbody>
        </table>
        <p>启用后在后台「基础设置 - 广告管理」Tab（ad/main.php 注入）为每个广告位填写 HTML。内容存储为 <code>plugin_ad_{位置}</code> 配置，输出前经 <code>Security::cleanHtml()</code> 清洗。ad/include.php 在前台注册了上表全部 6 个钩子，内容直接 echo 到对应位置。</p>
        <div class="tip">
          <div class="tip-title">自定义主题须知</div>
          <p>广告内容能否显示取决于主题是否在对应位置调用 <code>Plugin::hook()</code>（含 <code>before_content</code>/<code>after_content</code> 传 <code>[$site]</code>）。钩子调用点清单与示例见 3.5、3.7 节，直接复制默认主题即可保证兼容。</p>
        </div>
      </section>

      <section id="ch5-3" class="doc-section">
        <h2>5.3 文章发布 (article)</h2>
        <p>启用后前台出现文章列表/详情页（路由 <code>article_list</code> / <code>article</code>，模板 <code>article_list.php</code> / <code>article_detail.php</code>），侧边栏注入「文章专栏」入口；后台侧边栏出现「文章管理」入口（<code>/admin/plugin.php?p=article</code>，plugins/article/admin.php）。支持分类、标签、发布/草稿/待审状态与浏览量统计；编辑器支持扩展钩子（<code>article_editor_before</code> / <code>article_editor_after</code>）供其他插件加字段。</p>
        <p>数据库：<code>articles</code> 表（title、slug、content(HTML)、excerpt、author、category、tags、status、views、created_at、updated_at）。默认配置：<code>plugin_article_per_page</code>（列表每页条数）、<code>plugin_article_enable_submit</code>（是否开放投稿）。</p>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>sidebar_bottom</code></td><td>前台首页侧边栏</td><td>注入「文章专栏」入口（含文章数统计）</td></tr>
            <tr><td><code>before_footer</code></td><td>前台公共底部</td><td>注入插件自定义 CSS（<code>plugin_article_custom_css</code>）</td></tr>
            <tr><td><code>admin_sidebar</code></td><td>后台侧边栏</td><td>注入「文章管理」导航入口</td></tr>
          </tbody>
        </table>
        <p>文章相关页面未启用该插件时访问返回 404；article_list / article_detail 模板仅在该插件启用后有意义。</p>
      </section>

      <section id="ch5-4" class="doc-section">
        <h2>5.4 虫洞联盟 (wormhole)</h2>
        <p>站点互推机制：联盟成员在页面嵌入 JS（<code>/api/?endpoint=wormhole.js</code>），互相展示成员站点实现流量互传；内置随机传送（teleport）、每日检测与黑名单管理。</p>
        <p>数据库影响：创建 <code>blacklist</code> 表（与 auto-link 共享）；向 sites 表添加联盟字段（wormhole_status、wormhole_joined_at、wormhole_last_check、wormhole_check_fail、wormhole_source_domain、wormhole_quality_score、wormhole_click_in/out、wormhole_last_content_update、wormhole_quality_updated_at 等）；默认配置：<code>wormhole_enable</code>、<code>wormhole_need_review</code>、<code>wormhole_fallback_category</code>、<code>plugin_wormhole_rate_limit</code>、<code>block_all_ip</code>。</p>
        <table>
          <thead><tr><th>状态</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>none</code></td><td>未加入联盟</td></tr>
            <tr><td><code>manual</code></td><td>后台手动加入（不检测）</td></tr>
            <tr><td><code>auto</code></td><td>JS 上报自动加入（每日检测）</td></tr>
            <tr><td><code>pending</code></td><td>待审核</td></tr>
            <tr><td><code>broken</code></td><td>连续检测失败达阈值，已移出</td></tr>
          </tbody>
        </table>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>sidebar_bottom</code></td><td>前台首页侧边栏</td><td>注入「🌀 虫洞联盟」入口（含成员数，点击前往 wormhole 页）</td></tr>
            <tr><td><code>admin_sidebar</code></td><td>后台侧边栏</td><td>注入「虫洞联盟」管理入口（<code>/admin/plugin.php?p=wormhole</code>，成员管理/联盟设置/检测/黑名单）</td></tr>
          </tbody>
        </table>
        <h3>外站嵌入代码</h3>
        <p>联盟成员需要在页面中嵌入以下 JS（后台虫洞联盟管理页可复制）：</p>
        <pre><code>&lt;script&gt;
(function(){
    var d=document,s=d.createElement('script');
    s.src='https://你的主站/api/?endpoint=wormhole.js';
    s.async=1;
    d.body.appendChild(s);
})();
&lt;/script&gt;</code></pre>
        <p>定时检测：可配 crontab 每天执行 <code>core/cron_wormhole_check.php</code>（抓取 auto 成员页面检查是否仍含联盟代码，失败累计达阈值标记 broken）：</p>
        <pre><code>0 3 * * * php /path/to/core/cron_wormhole_check.php</code></pre>
        <p>相关 API 端点：<code>wormhole</code>（成员列表）、<code>wormhole.js</code>（嵌入脚本）、<code>wormhole-teleport</code>（随机传送）、<code>wormhole-join</code>（加入上报，返回 GIF）；插件未启用时这些端点返回 403（join 返回透明 GIF）。</p>
      </section>

      <section id="ch5-5" class="doc-section">
        <h2>5.5 友链自动收录 (auto-link)</h2>
        <p>当用户从挂了本站友链的外站点击进入时，系统自动检测来路、验证回链、抓取 TDK、检查违禁词与黑名单，通过后自动收录。</p>
        <p><strong>工作流程</strong>：PHP 渲染页面时捕获 Referer → 过滤本站与搜索引擎 → 插件在 <code>after_footer</code> 钩子注入 JS，延迟 2 秒发送 <code>/api/?endpoint=auto-link&ref=xxx</code> → 后端 <code>AutoLinkModel::process()</code> 抓取对方首页验证回链、抓取 TDK、检查违禁词/黑名单/频率限制/重复域名 → 插入数据库。</p>
        <p>配置项（基础设置「友链收录」Tab，auto-link/main.php 注入；键名沿用 <code>autolink_*</code> 兼容旧版）：<code>autolink_enable</code>（开关）、<code>autolink_need_review</code>（是否需审核）、<code>autolink_default_category</code>（默认分类）、<code>autolink_banned_words</code>（违禁词）。</p>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>after_footer</code></td><td>前台公共底部</td><td>在 <code>&lt;/body&gt;</code> 前注入检测 JS（仅 <code>autolink_enable=1</code> 时输出）</td></tr>
            <tr><td><code>admin_settings_nav</code> / <code>admin_settings_tabs</code></td><td>后台基础设置</td><td>注入「友链收录」设置 Tab</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">主题集成要点</div>
          <p>检测 JS 由<strong>插件自身</strong>通过 <code>after_footer</code> 钩子注入，主题<strong>无需</strong>再硬编码任何自动收录代码——但主题 footer.php 必须调用 <code>Plugin::hook('after_footer')</code>（放在 <code>&lt;/body&gt;</code> 之前），否则该插件启用后功能不生效。</p>
        </div>
        <p>安全机制：Referer 预过滤、搜索引擎排除、内网地址防护（<code>Security::isInternalHost()</code>）、黑名单检查（<code>blacklist</code> 表，与 wormhole 共享）、频率限制、回链验证、违禁词检查、重复域名检查。插件未启用时访问 <code>auto-link</code> 端点返回 1x1 透明 GIF。</p>
      </section>

      <section id="ch5-6" class="doc-section">
        <h2>5.6 伪静态设置 (rewrite)</h2>
        <p>URL 模式与格式配置。启动后在后台「基础设置 - 伪静态」Tab（rewrite/main.php 注入）配置：</p>
        <ul>
          <li><strong>模式</strong>：<code>dynamic</code>（默认，无需服务器规则）/ <code>rewrite</code>（伪静态，需服务器规则）/ <code>index</code>（URL 含 index.php 的兼容模式）</li>
          <li><strong>URL 格式</strong>：9 个页面（home/category/category_page/site/search/submit/wormhole/article_list/article）可自定义模板，占位符 <code>{%slug%}</code>、<code>{%id%}</code>、<code>{%page%}</code>（见 3.6）</li>
          <li><strong>规则生成</strong>：一键生成并复制 Apache <code>.htaccess</code> / Nginx 规则（<code>Rewrite::generateHtaccess()</code> / <code>generateNginx()</code>，含敏感目录与模板文件防护），支持写入项目根 .htaccess</li>
        </ul>
        <p><code>Rewrite.php</code> 内置默认格式（<code>$defaults</code>），配置存于 settings（<code>rewrite_mode</code> + <code>url_format_*</code>）；未启用插件时前台仍可用 dynamic 模式正常工作。</p>
        <p>钩子：<code>admin_settings_nav</code> / <code>admin_settings_tabs</code>（注入设置 Tab）。</p>
      </section>

      <section id="ch5-7" class="doc-section">
        <h2>5.7 提交网站收录 (submit)</h2>
        <p>前台提交入口与收录审核。配置在后台「基础设置 - 提交收录」Tab（submit/main.php 注入）：允许提交、需审核、前台是否显示权重、默认分类、是否强制选分类、收录分类白名单（category_ids）、提交频率限制、TDK 抓取频率限制、提交说明文本；存储为 <code>plugin_submit_*</code> 配置（如 <code>plugin_submit_enable_submit</code>、<code>plugin_submit_need_review</code>、<code>plugin_submit_show_weight</code> 等）。</p>
        <p>前台提交页由路由 <code>submit</code> 渲染（模板 <code>submit.php</code>，注入 <code>$enable</code> / <code>$needReview</code>）；表单提交到 <code>/api/?endpoint=submit</code>（需 CSRF，submit 插件未启用返回 403），支持 TDK 自动抓取与邮箱采集（<code>sites.submit_email</code>，供 notify 插件使用）。提交后进入后台「提交审核」处理。</p>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>search_bar_after</code></td><td>前台首页搜索栏后</td><td>注入「提交站点」入口按钮</td></tr>
            <tr><td><code>admin_settings_nav</code> / <code>admin_settings_tabs</code></td><td>后台基础设置</td><td>注入「提交收录」设置 Tab</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch5-8" class="doc-section">
        <h2>5.8 站点地图 (sitemap)</h2>
        <p>自动生成 <code>sitemap.xml</code> 与 <code>robots.txt</code>，收录首页、分类页、站点详情页与文章页；支持缓存、分片（<code>sitemap-{n}.xml</code>）与手动重新生成。站点地图由路由层直接输出（<code>Route::sitemap()</code> / <code>Route::robots()</code>，无需该插件也能访问，但生成逻辑与后台按钮由插件提供）。</p>
        <p>后台「基础设置 - 网站地图」Tab（sitemap/main.php 注入）可查看状态、缓存并手动生成；此插件无前台模板钩子。</p>
      </section>

      <section id="ch5-9" class="doc-section">
        <h2>5.9 图片灯箱 (lightbox)</h2>
        <p>纯前端钩子插件，无数据库影响。详情页图片点击放大，自动给图片加 <code>data-lightbox</code> 属性。内置轻量灯箱实现，无需外部依赖。</p>

        <h3>钩子与模板挂接</h3>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>after_footer</code></td><td>前台</td><td>在页面底部注入灯箱 CSS + JS</td></tr>
          </tbody>
        </table>

        <h4>插件注入效果</h4>
        <p>插件通过 <code>after_footer</code> 钩子注入轻量 CSS + JS（无需外部依赖），自动为 <code>.site-details img</code> 与 <code>.article-content img</code> 绑定点击放大，支持键盘 ESC 关闭；不改变原图 DOM 与链接行为。</p>
      </section>

      <section id="ch5-10" class="doc-section">
        <h2>5.10 图片ALT (auto-alt)</h2>
        <p>纯前端钩子插件，无数据库影响。通过 <code>after_footer</code> 钩子注入 JS，自动给页面中缺少 <code>alt</code> 属性的 <code>&lt;img&gt;</code> 补填站点名称或描述，提升 SEO 与无障碍访问。</p>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>after_footer</code></td><td>前台公共底部</td><td>注入补 alt 脚本</td></tr>
          </tbody>
        </table>
        <p>与灯箱（lightbox）插件一样依赖主题在 <code>footer.php</code> 中调用 <code>Plugin::hook('after_footer')</code>。</p>
      </section>

      <section id="ch5-11" class="doc-section">
        <h2>5.11 邮箱通知 (notify)</h2>
        <p>通过原生 PHP socket 实现 SMTP 邮件发送，在站点提交、审核通过/拒绝、用户反馈时自动通知管理员和提交者。</p>

        <h3>触发场景</h3>
        <table>
          <thead><tr><th>场景</th><th>触发钩子</th><th>通知对象</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td>前台提交站点</td><td><code>site_submitted</code></td><td>管理员（always）</td><td>始终通知站长，不受邮箱配置影响</td></tr>
            <tr><td>审核通过</td><td><code>site_approved</code></td><td>管理员 + 提交者（如有邮箱）</td><td>"通过"按钮或"编辑并发布"都会触发</td></tr>
            <tr><td>审核拒绝</td><td><code>site_rejected</code></td><td>管理员 + 提交者（如有邮箱）</td><td>拒绝操作触发，提交者无邮箱则跳过</td></tr>
            <tr><td>用户反馈</td><td><code>feedback_submitted</code></td><td>管理员（always）</td><td>收到反馈后通知站长</td></tr>
          </tbody>
        </table>

        <h3>数据库影响</h3>
        <table>
          <thead><tr><th>影响</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>notify_logs</code> 表</td><td>记录每次邮件发送的状态、收件人、主题和失败原因</td></tr>
            <tr><td><code>sites.submit_email</code></td><td>向 sites 表添加字段，存储前台提交者填写的联系邮箱</td></tr>
            <tr><td>13 条配置项</td><td>总开关（plugin_notify_enabled）、SMTP 服务器/端口/用户名/密码/加密、发件人、收件人、4 个通知开关</td></tr>
          </tbody>
        </table>

        <h3>SMTP 配置</h3>
        <p>启用后进入后台「基础设置 - 邮箱通知」Tab 配置：</p>
        <table>
          <thead><tr><th>配置项</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td>SMTP 服务器</td><td>如 smtp.qq.com、smtp.163.com</td></tr>
            <tr><td>端口</td><td>常用 465（SSL）或 587（TLS）</td></tr>
            <tr><td>用户名/密码</td><td>邮箱账号和授权码（非登录密码）</td></tr>
            <tr><td>加密方式</td><td>ssl / tls / none</td></tr>
            <tr><td>发件人邮箱/名称</td><td>邮件中显示的发件人</td></tr>
            <tr><td>收件人邮箱</td><td>管理员通知邮箱，多个用英文逗号分隔</td></tr>
            <tr><td>通知开关</td><td>分别控制提交/反馈/通过/拒绝四种通知</td></tr>
          </tbody>
        </table>

        <h3>测试发送</h3>
        <p>插件管理页面（<code>/admin/plugin.php?p=notify</code>）提供：</p>
        <ul>
          <li><strong>SMTP 测试发送</strong>：输入任意邮箱地址，立即发送测试邮件验证配置是否正确</li>
          <li><strong>发送日志</strong>：分页查看所有通知记录，支持按类型/状态筛选</li>
          <li><strong>清空日志</strong>：一键清空历史发送记录</li>
        </ul>

        <h3>通知行为细节</h3>
        <ul>
          <li><strong>无邮箱的提交者</strong>：通过/拒绝通知只发送给管理员，不尝试通知提交者（不会报错或空发）</li>
          <li><strong>审核通过</strong>：在「提交审核」页直接点「通过」、点「编辑并发布」、或在「站点管理」编辑 pending 站点为 published，都会触发 site_approved 钩子</li>
          <li><strong>HTML 邮件模板</strong>：内置响应式邮件模板，含站点名称、URL、审核结果和操作按钮</li>
          <li><strong>失败重试</strong>：SMTP 连接失败时记录错误日志但不中断页面流程</li>
        </ul>

        <h3>提交者邮箱采集</h3>
        <p>前台提交表单（<code>/templates/default/submit.php</code>）已增加邮箱输入框。API 端点 <code>/api/?endpoint=submit</code> 接收并写入 <code>sites.submit_email</code> 字段。没有邮箱的提交不影响正常收录。</p>
      </section>

      <section id="ch5-12" class="doc-section">
        <h2>5.12 友情链接 (friendlink)</h2>
        <p>友情链接管理：后台可快速添加友链（名称 + 链接 + 自定义 CSS 类 + 图标），前台底部区块展示（支持设置区块标题、打开方式、最大显示数量）。</p>
        <p>数据库：<code>friendlinks</code> 表（name、url、css_class、icon、sort_order、status）。配置：<code>plugin_friendlink_title</code>、<code>plugin_friendlink_target</code>、<code>plugin_friendlink_max_display</code>。</p>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>before_footer</code></td><td>前台公共底部</td><td>渲染友链区块 HTML</td></tr>
            <tr><td><code>after_footer</code></td><td>前台公共底部</td><td>注入友链管理弹窗所需 CSS + JS</td></tr>
            <tr><td><code>admin_sidebar</code></td><td>后台侧边栏</td><td>注入「友情链接」管理入口（<code>/admin/plugin.php?p=friendlink</code>）</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch5-13" class="doc-section">
        <h2>5.13 蜘蛛来访 (spider)</h2>
        <p>统计各大搜索引擎蜘蛛来访记录：按 User-Agent 识别百度/Google/Bing/搜狗/360/字节/Yandex 等引擎，支持按引擎开关、今日/昨日/近 7 日/近 30 日趋势与汇总图表，数据自动保留 30 天（可配置）。</p>
        <p>数据库：<code>spider_visits</code> 表。配置：<code>plugin_spider_engines</code>（启用的引擎）、<code>plugin_spider_retention_days</code>（保留天数，默认 30）。</p>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>before_header</code></td><td>前台页面 head 之前</td><td>检测 UA 并记录蜘蛛来访</td></tr>
            <tr><td><code>admin_sidebar</code></td><td>后台侧边栏</td><td>注入「蜘蛛来访」管理入口（<code>/admin/plugin.php?p=spider</code>，含趋势图表）</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch5-14" class="doc-section">
        <h2>5.14 数据库备份 (dbtool)</h2>
        <p>数据库备份与恢复工具：一键备份（纯 PHP PDO 导出当前表前缀下全部表结构与数据为 SQL）、下载备份到本地、删除备份、从备份文件恢复、上传 SQL 文件导入。备份存储于 <code>data/backups/</code>，文件名严格校验并防路径穿越。</p>
        <p><strong>无任何钩子</strong>：后台入口由插件管理页自动提供——该插件目录存在 <code>admin.php</code>，启用后在「插件管理」点击「管理」进入（<code>/admin/plugin.php?p=dbtool</code>），不会出现在侧边栏导航中。</p>
      </section>

    </section>

    <!-- ===== 第六章 参考文档 ===== -->
    <section id="ch6" class="doc-section">
      <h1>第六章 参考文档 <button class="share-anchor" data-anchor="ch6" title="复制章节链接">🔗</button></h1>

      <section id="ch6-1" class="doc-section">
        <h2>6.1 API 接口</h2>
        <p>所有 API 通过 <code>/api/?endpoint={端点名}</code>（或伪静态 <code>/api/{端点名}</code>）访问。POST 接口默认需要 CSRF 校验（Token 放在 <code>X-CSRF-Token</code> 头或 <code>csrf_token</code> 字段，页面模板会输出 <code>csrf-token</code> meta），<code>click/rate/feedback</code> 三个公开端点豁免。</p>
        <table>
          <thead><tr><th>端点</th><th>方法</th><th>说明</th><th>鉴权</th></tr></thead>
          <tbody>
            <tr><td><code>sites</code></td><td>GET</td><td>分类站点列表（category=slug、page、sort=br/newest/views/clicks）</td><td>无</td></tr>
            <tr><td><code>featured</code></td><td>GET</td><td>推荐站点</td><td>无</td></tr>
            <tr><td><code>site</code></td><td>GET</td><td>站点详情</td><td>无</td></tr>
            <tr><td><code>search</code></td><td>GET</td><td>搜索站点</td><td>无</td></tr>
            <tr><td><code>submit</code></td><td>POST</td><td>提交站点（CSRF；submit 插件未启用返回 403）</td><td>CSRF</td></tr>
            <tr><td><code>click</code></td><td>POST</td><td>记录点击</td><td>无</td></tr>
            <tr><td><code>fetch-tdk</code></td><td>POST</td><td>获取 TDK + 权重（CSRF；支持 internal HMAC 签名）</td><td>CSRF</td></tr>
            <tr><td><code>update-meta</code></td><td>POST</td><td>更新站点 TDK + 权重</td><td>CSRF</td></tr>
            <tr><td><code>rate</code></td><td>POST</td><td>提交评分（id、rating=1~5，IP 防刷）</td><td>无</td></tr>
            <tr><td><code>feedback</code></td><td>POST</td><td>提交问题反馈</td><td>无</td></tr>
            <tr><td><code>wormhole</code></td><td>GET</td><td>联盟成员列表（需启用 wormhole 插件）</td><td>无</td></tr>
            <tr><td><code>wormhole.js</code></td><td>GET</td><td>联盟嵌入 JS 脚本</td><td>无</td></tr>
            <tr><td><code>wormhole-teleport</code></td><td>GET</td><td>虫洞随机传送</td><td>无</td></tr>
            <tr><td><code>wormhole-join</code></td><td>GET</td><td>加入上报（返回透明 GIF）</td><td>无</td></tr>
            <tr><td><code>auto-link</code></td><td>GET</td><td>友链自动收录触发（ref 参数，返回透明 GIF）</td><td>无</td></tr>
          </tbody>
        </table>
        <h3>开放 API（API Key 鉴权，open/*）</h3>
        <p>需在后台「API 密钥」创建 Key，请求头携带 <code>X-API-Key</code>（或参数 <code>api_key</code>，POST 也支持 JSON 请求体中的 <code>api_key</code> 字段），响应头返回 <code>X-RateLimit-*</code> 限流信息。API Key 视为受信凭证：除查询外，还提供站点<strong>发布 / 编辑 / 删除</strong>与<strong>分类新增 / 编辑 / 删除</strong>，App、小程序、前台自定义提交页可直接调用（免 CSRF）。</p>
        <p>📄 完整《开放 API 对接文档》（含各接口请求/响应示例与代码示例）：<code>data/docs/api-guide.md</code>；后台「API 密钥」页面的使用说明为实时清单（已启用插件接口自动出现）。</p>
        <h4>站点查询</h4>
        <table>
          <thead><tr><th>端点</th><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>open/sites</code></td><td>GET</td><td>开放站点列表（category/page/limit/sort=views|clicks|br|newest|name）</td></tr>
            <tr><td><code>open/site</code></td><td>GET</td><td>开放站点详情（id）</td></tr>
            <tr><td><code>open/site/check</code></td><td>GET</td><td>网址收录/审核状态查询（url，App 查收录进度、提交前查重用）</td></tr>
            <tr><td><code>open/site/related</code></td><td>GET</td><td>相关站点（id、limit）</td></tr>
            <tr><td><code>open/featured</code></td><td>GET</td><td>推荐位站点（limit）</td></tr>
            <tr><td><code>open/rank</code></td><td>GET</td><td>开放排行榜（type=views|clicks|br_pc|br_mobile|newest）</td></tr>
            <tr><td><code>open/search</code></td><td>GET</td><td>开放搜索（q）</td></tr>
            <tr><td><code>open/stats</code></td><td>GET</td><td>开放统计</td></tr>
          </tbody>
        </table>
        <h4>站点发布 / 编辑 / 删除（写接口）</h4>
        <table>
          <thead><tr><th>端点</th><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>open/submit</code></td><td>POST</td><td>发布/提交站点（name/url/category_id 必填；默认 published，可传 status=pending）</td></tr>
            <tr><td><code>open/site/update</code></td><td>POST</td><td>编辑站点（id + 部分更新：名称/网址/分类/描述/标签/权重/状态/推荐/排序）</td></tr>
            <tr><td><code>open/site/delete</code></td><td>POST</td><td>删除站点（id）</td></tr>
          </tbody>
        </table>
        <h4>分类管理</h4>
        <table>
          <thead><tr><th>端点</th><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>open/categories</code></td><td>GET</td><td>开放分类列表（含站点数与 SEO 信息）</td></tr>
            <tr><td><code>open/category/create</code></td><td>POST</td><td>新增分类（name/slug 必填，slug 唯一）</td></tr>
            <tr><td><code>open/category/update</code></td><td>POST</td><td>编辑分类（id + 部分更新）</td></tr>
            <tr><td><code>open/category/delete</code></td><td>POST</td><td>删除分类（分类下仍有站点时返回 40901）</td></tr>
          </tbody>
        </table>
        <h4>系统查询</h4>
        <table>
          <thead><tr><th>端点</th><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>open/plugins</code></td><td>GET</td><td>插件列表与启用状态（判断哪些插件接口可用）</td></tr>
          </tbody>
        </table>
        <h4>内置插件接口（插件启用后自动注册）</h4>
        <p>内置插件在各自目录提供 <code>api.php</code> 接口声明：插件<strong>启用后</strong>其 <code>open/插件/*</code> 接口自动注册并在后台「API 密钥」使用说明中自动展示查询案例与说明；停用后接口失效（返回 403 / 40301）。</p>
        <table>
          <thead><tr><th>插件</th><th>端点（节选）</th><th>方法</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td>文章</td><td><code>open/article/list</code> / <code>open/article/detail</code></td><td>GET</td><td>文章列表/详情</td></tr>
            <tr><td>文章</td><td><code>open/article/publish</code> / <code>open/article/update</code> / <code>open/article/delete</code></td><td>POST</td><td>文章发布/编辑/删除</td></tr>
            <tr><td>虫洞联盟</td><td><code>open/wormhole/members</code> / <code>open/wormhole/stats</code> / <code>open/wormhole/random</code></td><td>GET</td><td>联盟成员/统计/随机成员</td></tr>
            <tr><td>友情链接</td><td><code>open/friendlinks</code></td><td>GET</td><td>友链列表</td></tr>
            <tr><td>友情链接</td><td><code>open/friendlink/create</code> / <code>open/friendlink/update</code> / <code>open/friendlink/delete</code></td><td>POST</td><td>友链新增/编辑/删除</td></tr>
            <tr><td>蜘蛛来访</td><td><code>open/spider/stats</code> / <code>open/spider/trend</code> / <code>open/spider/visits</code></td><td>GET</td><td>蜘蛛来访汇总/趋势/明细</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">开放 API 约定</div>
          <p>成功响应 <code>{success:true, code:0, message:"ok", data:{...}}</code>；失败 <code>{success:false, code:错误码, message:"原因"}</code>。错误码：40101 缺 Key、40102 Key 无效、42901 超限、40001 参数错误、40301 插件未启用、40401 资源不存在、40901 冲突。列表接口分页字段 <code>data.total/page/limit/total_pages</code>。写接口默认操作任意站点（受信凭证），请勿将 Key 交给不可信方；接口被调用时会写入 <code>open_api</code> 日志频道（后台「日志设置」可开关）。</p>
        </div>
      </section>

      <section id="ch6-2" class="doc-section">
        <h2>6.2 日志系统</h2>
        <p>通过 <code>Logger::log($channel, $message)</code> 写入日志，按日期分目录、按频道分文件存储。</p>
        <pre><code>// 写单条日志
Logger::log('admin_site', "[编辑] 站点ID={$siteId}，结果=成功");

// 批量写日志
Logger::logs('wormhole_check', [
    "检测通过：site_id=1",
    "检测失败：site_id=3，原因=404",
]);

// 判断某频道是否开启（可在耗时日志前使用）
if (Logger::isEnabled('autolink')) {
    Logger::log('autolink', $detail);
}

// 获取日志文件路径
$file = Logger::getLogFile('wormhole_join');
// 返回：data/logs/20260808/wormhole_join.log</code></pre>
        <p>日志目录：<code>data/logs/YYYYMMDD/{channel}.log</code>，单行格式 <code>[HH:MM:SS] 内容</code>。</p>

        <h3>开关配置（后台操作）</h3>
        <p>后台「基础设置 - 基础信息 - 日志设置」提供完整开关界面：</p>
        <ul>
          <li><strong>日志总开关</strong> <code>log_global</code>：关闭后所有日志停止写入；关闭时不会改动各频道开关值，重新开启后按原频道设置生效</li>
          <li><strong>频道独立开关</strong> <code>log_{channel}</code>：总开关开启后自动展开，可按频道单独开启/关闭（默认全部开启）</li>
        </ul>
        <p>全部频道（分组与后台界面一致）：</p>
        <table>
          <thead><tr><th>分组</th><th>频道</th></tr></thead>
          <tbody>
            <tr><td>虫洞联盟</td><td><code>wormhole_join</code>、<code>wormhole_check</code>、<code>wormhole_model</code>、<code>wormhole_display</code></td></tr>
            <tr><td>友链自动收录</td><td><code>autolink</code></td></tr>
            <tr><td>安全风控</td><td><code>security_ratelimit</code>、<code>security_csrf</code>、<code>security_referer</code></td></tr>
            <tr><td>跳转与 API</td><td><code>go_jump</code>、<code>api_5118</code>、<code>api_tdk</code>、<code>open_api</code></td></tr>
            <tr><td>后台管理审计</td><td><code>admin_auth</code>、<code>admin_site</code>、<code>admin_category</code>、<code>admin_feature</code>、<code>admin_blacklist</code>、<code>admin_setting</code>、<code>admin_wormhole</code>、<code>admin_api_key</code></td></tr>
            <tr><td>系统与数据库</td><td><code>database_error</code>、<code>plugin_error</code>、<code>plugin_info</code>、<code>plugin_uninstall</code>、<code>search_fallback</code></td></tr>
          </tbody>
        </table>
        <p>除内置频道外任意频道均可写入（如 <code>Logger::log('my_channel', ...)</code>），未配置开关的频道默认开启。</p>
      </section>

      <section id="ch6-3" class="doc-section">
        <h2>6.3 伪静态配置</h2>
        <p>伪静态系统支持三种模式（配置入口：rewrite 插件启动后的后台「基础设置 - 伪静态」Tab）：</p>
        <table>
          <thead><tr><th>模式</th><th>首页</th><th>分类页</th><th>详情页</th></tr></thead>
          <tbody>
            <tr><td>dynamic</td><td><code>/</code></td><td><code>/index.php?route=category&amp;slug=tech</code></td><td><code>/index.php?route=site&amp;id=1</code></td></tr>
            <tr><td>rewrite</td><td><code>/</code></td><td><code>/category/tech/</code></td><td><code>/site/1/</code></td></tr>
            <tr><td>index</td><td><code>/index.php</code></td><td><code>/index.php/category/tech/</code></td><td><code>/index.php/site/1/</code></td></tr>
          </tbody>
        </table>
        <p>分类页分页格式 <code>category/{slug}/page-{n}/</code>；9 个页面的 URL 格式均可自定义（占位符 <code>{%slug%}</code>、<code>{%id%}</code>、<code>{%page%}</code>）。后台可按当前配置自动生成 Apache <code>.htaccess</code> 与 Nginx 规则（含 <code>core/</code>、<code>config/</code>、<code>install/</code>、<code>.git/</code>、模板 PHP 文件的访问防护），并可一键写入项目根 .htaccess。</p>
      </section>

      <section id="ch6-4" class="doc-section">
        <h2>6.4 安全规范</h2>
        <ul>
          <li><strong>输出转义</strong>：所有输出到 HTML 的内容必须使用 <code>Theme::e()</code> / <code>Security::e()</code>；HTML 属性值使用 <code>Theme::eAttr()</code> / <code>Security::eAttr()</code></li>
          <li><strong>URL 生成</strong>：必须使用 <code>Theme::url()</code> / <code>Rewrite::url()</code> 生成 URL，不能硬编码</li>
          <li><strong>CSRF 防护</strong>：所有 POST 表单必须包含 <code>Security::csrfField()</code>（或 <code>$_SESSION['csrf_token']</code>），后端使用 <code>Security::verifyCSRFToken()</code> 校验</li>
          <li><strong>输入过滤</strong>：<code>Security::cleanString()</code> 清洗字符串、<code>Security::int()</code> 清洗整数、<code>Security::enum()</code> 枚举白名单、<code>Security::validateSlug()</code> 校验 slug、<code>Security::validateUrl()</code> 校验 URL、<code>Security::cleanHtml()</code> 清洗富文本、<code>Security::cleanTags()</code> 清洗标签</li>
          <li><strong>频率限制</strong>：<code>Security::rateLimit($key, $maxCount, $windowSeconds)</code> 防刷接口</li>
          <li><strong>Referer 校验</strong>：<code>Security::checkReferer()</code>（后台接口按需启用）</li>
          <li><strong>SQL 注入</strong>：一律使用 PDO 预处理（<code>Database::query()</code> / <code>queryOne()</code> / <code>execute()</code> / <code>insert()</code> / <code>scalar()</code>），禁止拼接 SQL</li>
          <li><strong>文件安全</strong>：<code>data/logs/</code>、<code>data/backups/</code> 等目录不可通过 Web 直接访问（后台生成的 .htaccess / Nginx 规则已默认防护 <code>core/</code>、<code>templates/*.php</code> 等）</li>
        </ul>
      </section>

      <section id="ch6-5" class="doc-section">
        <h2>6.5 应用中心（扩展分发）</h2>
        <p>「应用中心」是懒人导航的在线扩展分发通道：站长在后台（插件管理 → 应用中心）浏览目录，
          对插件 / 主题<strong>一键安装、升级</strong>。开发者只需把符合规范的扩展文件夹上传到发布服务器
          的 <code>apps/plugins/</code> 或 <code>apps/themes/</code> 目录即自动生效——无需维护清单、无需手动打包 ZIP。</p>
        <ul>
          <li><strong>站长侧</strong>：内置插件 <code>plugins/appcenter/</code>（启动后侧边栏出现「应用中心」入口；目录协议与安全边界见 <code>plugins/appcenter/README.md</code>）</li>
          <li><strong>发布侧</strong>：服务端 <code>appcenter-server/</code>（<code>list.php</code> 自动扫描 + <code>download.php</code> 按需打包；部署运维见 <code>appcenter-server/README.md</code>）</li>
          <li><strong>开发者</strong>：插件 / 主题发布规范、元数据字段、版本与升级规则、自测清单、FAQ —— 📄 完整《应用中心开发者接入指南》：<code>data/docs/appcenter.md</code></li>
        </ul>
        <div class="tip">
          <div class="tip-title">一句话流程</div>
          <p>写好插件 / 主题并在本地验证 → 文件夹（含 <code>plugin.json</code> / <code>theme.json</code>）上传到
            <code>apps/plugins/</code> 或 <code>apps/themes/</code> → 浏览器访问 <code>list.php</code> 确认目录出现 →
            站长后台即可一键安装；改 <code>version</code> 重新上传即自动升级。</p>
        </div>
      </section>

    </section>

    <!-- 文档结束 -->
    <div class="tip success">
      <div class="tip-title">文档结束</div>
      <p>如需了解更多细节，请查阅 <code>core/</code> 目录下的源代码，或查看 <code>plugins/</code> 目录中内置插件的实际实现。</p>
    </div>

  </div>
</main>

<button class="back-to-top">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
</button>

<script src="<?= $docBase ?>js/doc.js"></script>
</body>
</html>
