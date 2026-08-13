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
    <li class="nav-divider"></li>
    <li class="nav-item"><a href="#ch6" class="nav-link">第六章 参考文档</a></li>
    <li class="nav-item"><a href="#ch6-1" class="nav-link sub">6.1 API 接口</a></li>
    <li class="nav-item"><a href="#ch6-2" class="nav-link sub">6.2 日志系统</a></li>
    <li class="nav-item"><a href="#ch6-3" class="nav-link sub">6.3 伪静态配置</a></li>
    <li class="nav-item"><a href="#ch6-4" class="nav-link sub">6.4 安全规范</a></li>
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
          <tr><td>主题开发者</td><td>第二章、第三章</td><td>自定义页面布局和视觉风格</td></tr>
          <tr><td>插件开发者</td><td>第二章、第四章</td><td>扩展功能、注册钩子、管理数据库</td></tr>
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
          <li>确保以下目录可写：<code>data/logs/</code>、<code>data/cache/</code>、<code>templates/</code>、<code>plugins/</code></li>
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

        <h3>1.2.3 伪静态配置（推荐）</h3>
        <p>安装完成后，进入后台「基础设置 - 伪静态」，选择伪静态模式并复制自动生成的服务器规则到 <code>.htaccess</code>（Apache）或 Nginx 配置文件中。</p>
        <table>
          <thead><tr><th>模式</th><th>示例 URL</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td>dynamic</td><td><code>/index.php?route=category&amp;slug=tech</code></td><td>动态模式，无需服务器配置</td></tr>
            <tr><td>rewrite</td><td><code>/category/tech/</code></td><td>伪静态模式，需配置服务器规则</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch1-3" class="doc-section">
        <h2>1.3 后台使用</h2>
        <p>安装完成后访问 <code>http://你的域名/admin/</code> 登录后台。</p>
        <table>
          <thead><tr><th>菜单</th><th>功能说明</th></tr></thead>
          <tbody>
            <tr><td>仪表盘</td><td>站点概况、最近操作、统计数据概览</td></tr>
            <tr><td>站点管理</td><td>站点增删改查、审核、推荐设置、批量操作</td></tr>
            <tr><td>分类管理</td><td>分类增删改、排序、SEO 字段设置</td></tr>
            <tr><td>待审核</td><td>审核前台提交和自动收录的站点</td></tr>
            <tr><td>推荐管理</td><td>设置全局推荐和分类推荐</td></tr>
            <tr><td>基础设置</td><td>站点名称、SEO、主题、伪静态、日志开关、各插件配置 Tab</td></tr>
            <tr><td>插件管理</td><td>启用/停用/卸载插件，查看插件数据库信息</td></tr>
            <tr><td>统计报表</td><td>站点浏览、点击、评分等数据统计</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">基础设置中的 Tab</div>
          <p>基础设置页面采用 Tab 面板设计。核心设置（站点信息、SEO、主题等）固定显示，插件注入的设置 Tab（如广告管理、伪静态格式等）仅在对应插件启用后出现。</p>
        </div>
      </section>

      <section id="ch1-4" class="doc-section">
        <h2>1.4 插件管理</h2>
        <p>后台「插件管理」页面展示所有已扫描到的插件。每个插件有三种状态操作：</p>
        <table>
          <thead><tr><th>操作</th><th>效果</th></tr></thead>
          <tbody>
            <tr><td><strong>启用</strong></td><td>自动执行 <code>ensureSchema()</code>：创建插件声明的表、向已有表添加字段、写入默认配置。然后加载插件代码并注册钩子</td></tr>
            <tr><td><strong>停用</strong></td><td>仅修改启用状态为关闭，<strong>保留</strong>所有数据库表和配置数据。再次启用时无需重新安装</td></tr>
            <tr><td><strong>卸载</strong></td><td>停用 + 删除插件自建表 + 删除插件添加的字段 + 清除插件配置。共享表智能判断：仅当所有声明该表的插件都卸载时才删表</td></tr>
          </tbody>
        </table>
        <p>插件列表中还会显示每个插件的数据库信息，格式如 <code>📋 articles · settings(2配置)</code>，表示该插件创建了 articles 表并写入了 2 条配置。</p>
        <div class="tip">
          <div class="tip-title">提示</div>
          <p>所有内置插件默认关闭。安装完成后，根据需要到插件管理页面逐个启用。插件之间的依赖关系极低，可以按任意顺序启用。</p>
        </div>
      </section>

      <section id="ch1-5" class="doc-section">
        <h2>1.5 主题切换</h2>
        <ol>
          <li>进入后台「基础设置」</li>
          <li>在「主题设置」区域选择已安装的主题</li>
          <li>保存后前台立即生效</li>
        </ol>
        <p>主题文件放在 <code>templates/{主题名}/</code> 目录下。系统会自动扫描所有含 <code>theme.json</code> 的目录并显示在后台主题列表中。</p>
      </section>

    </section>

    <!-- ===== 第二章 系统概述 ===== -->
    <section id="ch2" class="doc-section">
      <h1>第二章 系统概述 <button class="share-anchor" data-anchor="ch2" title="复制章节链接">🔗</button></h1>

      <section id="ch2-1" class="doc-section">
        <h2>2.1 目录结构</h2>
        <div class="file-tree">
          <span class="dir">.</span><br>
          &nbsp;&nbsp;<span class="file">index.php</span> <span class="comment">前台统一入口</span><br>
          &nbsp;&nbsp;<span class="file">go.php</span> <span class="comment">跳转中间页（记录点击统计后跳转）</span><br>
          &nbsp;&nbsp;<span class="file">config.php</span> <span class="comment">数据库配置（安装时自动生成）</span><br>
          &nbsp;&nbsp;<span class="dir">core/</span> <span class="comment">核心类库</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">bootstrap.php</span> <span class="comment">应用引导（Session、自动加载、调试模式）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Database.php</span> <span class="comment">PDO 单例 + 预处理封装</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Security.php</span> <span class="comment">安全模块（XSS/CSRF/频率限制/HTML清洗）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Route.php</span> <span class="comment">路由分发器</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Rewrite.php</span> <span class="comment">伪静态系统（URL 解析与生成）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Theme.php</span> <span class="comment">主题系统（扫描/加载/渲染/资源引用）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Plugin.php</span> <span class="comment">插件系统核心（扫描/启用/schema/钩子）</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">Logger.php</span> <span class="comment">日志工具类</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">helpers.php</span> <span class="comment">前台辅助函数库</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">SiteModel.php</span> <span class="comment">站点模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">CategoryModel.php</span> <span class="comment">分类模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">SettingsModel.php</span> <span class="comment">设置模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">FeatureModel.php</span> <span class="comment">推荐模型</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="file">WormholeModel.php</span> <span class="comment">虫洞模型</span><br>
          &nbsp;&nbsp;<span class="dir">templates/</span> <span class="comment">主题目录</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">default/</span> <span class="comment">默认主题</span><br>
          &nbsp;&nbsp;<span class="dir">plugins/</span> <span class="comment">插件目录</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">ad/</span> <span class="comment">广告管理</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">article/</span> <span class="comment">文章发布</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">auto-link/</span> <span class="comment">友链自动收录</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">wormhole/</span> <span class="comment">虫洞联盟</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">rewrite/</span> <span class="comment">伪静态设置</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;<span class="dir">...</span> <span class="comment">其他插件</span><br>
          &nbsp;&nbsp;<span class="dir">admin/</span> <span class="comment">后台管理</span><br>
          &nbsp;&nbsp;<span class="dir">api/</span> <span class="comment">API 接口入口</span><br>
          &nbsp;&nbsp;<span class="dir">install/</span> <span class="comment">安装程序</span><br>
          &nbsp;&nbsp;<span class="dir">data/</span> <span class="comment">数据目录（日志/缓存/文档）</span>
        </div>
      </section>

      <section id="ch2-2" class="doc-section">
        <h2>2.2 核心类一览</h2>
        <table>
          <thead><tr><th>类</th><th>文件</th><th>职责</th></tr></thead>
          <tbody>
            <tr><td><code>Database</code></td><td><code>core/Database.php</code></td><td>PDO 单例，提供 <code>query()</code>、<code>queryOne()</code>、<code>execute()</code>、<code>insert()</code>、<code>table()</code>、事务</td></tr>
            <tr><td><code>Security</code></td><td><code>core/Security.php</code></td><td>输入过滤、输出转义、CSRF、频率限制、HTML 清洗</td></tr>
            <tr><td><code>Route</code></td><td><code>core/Route.php</code></td><td>路由分发：解析 URL 参数，分发到对应模板</td></tr>
            <tr><td><code>Rewrite</code></td><td><code>core/Rewrite.php</code></td><td>伪静态：URL 解析、生成、服务器规则自动生成</td></tr>
            <tr><td><code>Theme</code></td><td><code>core/Theme.php</code></td><td>主题扫描、加载、渲染、资源引用、布局片段</td></tr>
            <tr><td><code>Plugin</code></td><td><code>core/Plugin.php</code></td><td>插件扫描、启用/停用/卸载、schema 安装、钩子系统</td></tr>
            <tr><td><code>Logger</code></td><td><code>core/Logger.php</code></td><td>按日期分目录、按频道分文件的日志写入</td></tr>
            <tr><td><code>SettingsModel</code></td><td><code>core/SettingsModel.php</code></td><td>键值对配置读写（settings 表）</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch2-3" class="doc-section">
        <h2>2.3 数据库表概览</h2>
        <p>初始安装创建以下核心表（不含插件表）：</p>
        <table>
          <thead><tr><th>表名</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>sites</code></td><td>站点主表：名称、URL、分类、权重、状态等</td></tr>
            <tr><td><code>categories</code></td><td>分类表：名称、slug、图标、排序、SEO 字段</td></tr>
            <tr><td><code>settings</code></td><td>配置表：键值对存储全站配置</td></tr>
            <tr><td><code>site_features</code></td><td>推荐关联表：分类推荐站点排序</td></tr>
            <tr><td><code>admins</code></td><td>管理员账号（bcrypt 密码哈希）</td></tr>
            <tr><td><code>site_ratings</code></td><td>用户评分（IP 防刷）</td></tr>
            <tr><td><code>site_feedback</code></td><td>站点反馈（URL变更/打不开/内容错误）</td></tr>
            <tr><td><code>deleted_ids</code></td><td>ID 回收队列（删除站点后复用 ID）</td></tr>
            <tr><td><code>site_daily_stats</code></td><td>站点每日统计</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">插件管理的表</div>
          <p>插件声明的表（如 <code>articles</code>、<code>blacklist</code>）和插件向已有表添加的字段（如 sites 表的 <code>wormhole_status</code> 等）<strong>不在初始安装时创建</strong>，而是在插件启用时由 <code>Plugin::ensureSchema()</code> 自动安装。卸载插件时会自动清理。</p>
        </div>
      </section>

      <section id="ch2-4" class="doc-section">
        <h2>2.4 辅助函数速查（core/helpers.php）</h2>
        <table>
          <thead><tr><th>函数名</th><th>参数</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>setting()</code></td><td>$key, $default=null</td><td>读取配置值（别名 <code>getConfig()</code>）</td></tr>
            <tr><td><code>redirect()</code></td><td>$url</td><td>HTTP 重定向</td></tr>
            <tr><td><code>isInstalled()</code></td><td>无</td><td>检查系统是否已安装</td></tr>
            <tr><td><code>isDebug()</code></td><td>无</td><td>判断调试模式</td></tr>
            <tr><td><code>parseDomain()</code></td><td>$url</td><td>从 URL 提取域名</td></tr>
            <tr><td><code>normalizeSiteUrl()</code></td><td>$url</td><td>补全 URL 协议头</td></tr>
            <tr><td><code>formatNumber()</code></td><td>$num</td><td>格式化数字（1k, 1M）</td></tr>
            <tr><td><code>formatDate()</code></td><td>$date, $format</td><td>格式化日期</td></tr>
            <tr><td><code>parseTags()</code></td><td>$json</td><td>解析 JSON 标签为数组</td></tr>
            <tr><td><code>renderPagination()</code></td><td>$current, $total, $urlTemplate</td><td>生成分页 HTML</td></tr>
            <tr><td><code>getMaxBr()</code></td><td>$site</td><td>获取站点最高权重</td></tr>
          </tbody>
        </table>
      </section>

    </section>

    <!-- ===== 第三章 主题开发 ===== -->
    <section id="ch3" class="doc-section">
      <h1>第三章 主题开发 <button class="share-anchor" data-anchor="ch3" title="复制章节链接">🔗</button></h1>

      <section id="ch3-1" class="doc-section">
        <h2>3.1 主题目录结构</h2>
        <p>主题放在 <code>templates/{主题名}/</code> 目录下：</p>
        <div class="file-tree">
          <span class="dir">templates/mytheme/</span><br>
          &nbsp;&nbsp;<span class="file">theme.json</span> <span class="comment">主题信息文件（必需）</span><br>
          &nbsp;&nbsp;<span class="file">index.php</span> <span class="comment">首页模板（必需）</span><br>
          &nbsp;&nbsp;<span class="file">category.php</span> <span class="comment">分类页模板</span><br>
          &nbsp;&nbsp;<span class="file">site.php</span> <span class="comment">站点详情页模板</span><br>
          &nbsp;&nbsp;<span class="file">search.php</span> <span class="comment">搜索页模板</span><br>
          &nbsp;&nbsp;<span class="file">submit.php</span> <span class="comment">提交站点页模板</span><br>
          &nbsp;&nbsp;<span class="file">header.php</span> <span class="comment">公共头部（可选，通过 partial 加载）</span><br>
          &nbsp;&nbsp;<span class="file">footer.php</span> <span class="comment">公共底部（可选，通过 partial 加载）</span><br>
          &nbsp;&nbsp;<span class="file">404.php</span> <span class="comment">404 错误页</span><br>
          &nbsp;&nbsp;<span class="dir">css/</span> <span class="comment">样式文件</span><br>
          &nbsp;&nbsp;<span class="dir">js/</span> <span class="comment">脚本文件</span><br>
          &nbsp;&nbsp;<span class="file">screenshot.png</span> <span class="comment">主题截图（可选，后台展示用）</span>
        </div>
      </section>

      <section id="ch3-2" class="doc-section">
        <h2>3.2 theme.json</h2>
        <p>每个主题必须包含 <code>theme.json</code>：</p>
        <pre><code>{
  "name": "mytheme",
  "title": "我的主题",
  "version": "1.0",
  "author": "你的名字",
  "description": "主题简介",
  "support": ["index", "category", "site", "search", "submit"]
}</code></pre>
        <table>
          <thead><tr><th>字段</th><th>必填</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>name</code></td><td>是</td><td>目录名（必须与文件夹一致）</td></tr>
            <tr><td><code>title</code></td><td>是</td><td>显示名称</td></tr>
            <tr><td><code>version</code></td><td>是</td><td>版本号</td></tr>
            <tr><td><code>author</code></td><td>否</td><td>作者</td></tr>
            <tr><td><code>description</code></td><td>否</td><td>简介</td></tr>
            <tr><td><code>support</code></td><td>否</td><td>支持的页面类型列表</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch3-3" class="doc-section">
        <h2>3.3 模板文件与注入变量</h2>
        <p><code>Route::dispatch()</code> 解析请求后，通过 <code>Theme::render()</code> 将变量注入模板作用域（<code>extract()</code>），模板中直接使用变量。</p>
        <table>
          <thead><tr><th>模板文件</th><th>路由</th><th>注入变量</th></tr></thead>
          <tbody>
            <tr><td><code>index.php</code></td><td><code>/</code></td><td>$categories, $activeCats, $featuredSites, $currentSites, $settings, $seoTitle, $seoDesc, $ranking, $showWeight, $siteStats</td></tr>
            <tr><td><code>category.php</code></td><td><code>category/{slug}/</code></td><td>$category, $sites, $slug, $sort, $total, $totalPages, $showWeight</td></tr>
            <tr><td><code>site.php</code></td><td><code>site/{id}/</code></td><td>$site, $category, $related, $domain, $tags, $ratingStats</td></tr>
            <tr><td><code>search.php</code></td><td><code>search/</code></td><td>$keyword, $results, $total, $totalPages, $perPage</td></tr>
            <tr><td><code>submit.php</code></td><td><code>submit/</code></td><td>$enable, $needReview, $categories</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch3-4" class="doc-section">
        <h2>3.4 Theme 类方法</h2>
        <table>
          <thead><tr><th>方法</th><th>参数</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>Theme::current()</code></td><td>无</td><td>获取当前主题名</td></tr>
            <tr><td><code>Theme::set()</code></td><td>$name</td><td>切换主题</td></tr>
            <tr><td><code>Theme::scan()</code></td><td>无</td><td>扫描所有可用主题</td></tr>
            <tr><td><code>Theme::render()</code></td><td>$template, $vars=[]</td><td>渲染模板（注入变量 + require）</td></tr>
            <tr><td><code>Theme::partial()</code></td><td>$name, $vars=[]</td><td>加载布局片段（如 header/footer），自动回退 default</td></tr>
            <tr><td><code>Theme::e()</code></td><td>$value</td><td>HTML 实体转义（等价 <code>Security::e()</code>）</td></tr>
            <tr><td><code>Theme::eAttr()</code></td><td>$value</td><td>属性值转义</td></tr>
            <tr><td><code>Theme::url()</code></td><td>$type, $params=[]</td><td>生成 URL（自动适配伪静态模式）</td></tr>
            <tr><td><code>Theme::asset()</code></td><td>$file</td><td>获取主题资源路径（如 css/style.css）</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch3-5" class="doc-section">
        <h2>3.5 钩子列表</h2>
        <p>主题模板中通过 <code>Plugin::hook('钩子名')</code> 输出插件注入的内容。以下是系统内置的所有前台钩子：</p>
        <table>
          <thead><tr><th>钩子名</th><th>位置</th><th>参数</th><th>用途</th></tr></thead>
          <tbody>
            <tr><td><code>before_header</code></td><td>header.php 顶部</td><td>无</td><td>在 HTML head 之前输出内容</td></tr>
            <tr><td><code>after_header</code></td><td>header.php 底部</td><td>无</td><td>在 body 开头输出内容</td></tr>
            <tr><td><code>search_bar_after</code></td><td>首页搜索栏之后</td><td>无</td><td>搜索栏下方注入内容</td></tr>
            <tr><td><code>sidebar_top</code></td><td>侧边栏顶部</td><td>无</td><td>侧边栏上方广告/内容</td></tr>
            <tr><td><code>sidebar_bottom</code></td><td>侧边栏底部</td><td>无</td><td>侧边栏下方内容</td></tr>
            <tr><td><code>site_list_before</code></td><td>站点列表前</td><td>无</td><td>列表上方广告/内容</td></tr>
            <tr><td><code>site_list_after</code></td><td>站点列表后</td><td>无</td><td>列表下方广告/内容</td></tr>
            <tr><td><code>before_content</code></td><td>站点详情内容前</td><td>[$site]</td><td>详情内容上方</td></tr>
            <tr><td><code>after_content</code></td><td>站点详情内容后</td><td>[$site]</td><td>详情内容下方</td></tr>
            <tr><td><code>before_footer</code></td><td>footer.php 顶部</td><td>无</td><td>页脚之前</td></tr>
            <tr><td><code>after_footer</code></td><td>footer.php 底部</td><td>无</td><td>页脚之后（常用于 JS 注入）</td></tr>
          </tbody>
        </table>
        <p>后台钩子：</p>
        <table>
          <thead><tr><th>钩子名</th><th>位置</th><th>参数</th><th>用途</th></tr></thead>
          <tbody>
            <tr><td><code>admin_sidebar</code></td><td>后台侧边栏</td><td>无</td><td>注入后台菜单项</td></tr>
            <tr><td><code>admin_settings_nav</code></td><td>基础设置页 Tab 导航</td><td>[$activeTab]</td><td>注入设置 Tab 标签</td></tr>
            <tr><td><code>admin_settings_tabs</code></td><td>基础设置页 Tab 面板</td><td>[$activeTab]</td><td>注入设置 Tab 内容</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">使用方式</div>
          <p>在模板中调用 <code>&lt;?php Plugin::hook('sidebar_top'); ?&gt;</code> 即可。钩子名是约定好的，主题开发者只需在对应位置放置钩子调用，插件会自动注入内容。</p>
        </div>
      </section>

      <section id="ch3-6" class="doc-section">
        <h2>3.6 URL 生成与资源引用</h2>
        <pre><code>// URL 生成（自动适配伪静态）
&lt;?= Theme::url('home') ?&gt;                              // 首页
&lt;?= Theme::url('category', ['slug' => $cat['slug']]) ?&gt;  // 分类页
&lt;?= Theme::url('site', ['id' => $site['id']]) ?&gt;          // 站点详情
&lt;?= Theme::url('search') ?&gt;                   // 搜索页
&lt;?= Theme::url('submit') ?&gt;                   // 提交页

// 静态资源引用
&lt;link rel="stylesheet" href="&lt;?= Theme::asset('css/style.css') ?&gt;"&gt;
&lt;script src="&lt;?= Theme::asset('js/script.js') ?&gt;"&gt;&lt;/script&gt;
&lt;img src="&lt;?= Theme::asset('images/logo.png') ?&gt;"&gt;</code></pre>
        <p><code>Theme::asset()</code> 返回的路径基于当前主题目录，例如 <code>/templates/mytheme/css/style.css</code>。</p>
      </section>

      <section id="ch3-7" class="doc-section">
        <h2>3.7 实战案例：创建一个主题</h2>
        <p>下面以默认主题 <code>default</code> 的实际代码为例，展示从零创建一个主题的完整流程。</p>

        <h3>步骤 1：创建目录和 theme.json</h3>
        <pre><code>templates/mytheme/theme.json:
{
  "name": "mytheme",
  "title": "极简主题",
  "version": "1.0",
  "author": "懒人导航",
  "description": "极简风格，专注内容",
  "support": ["index", "category", "site", "search", "submit"]
}</code></pre>

        <h3>步骤 2：编写 header.php（公共头部）</h3>
        <p>头部负责 DOCTYPE、meta 标签、CSS 引用和插件钩子。以下是默认主题的实际写法：</p>
        <pre><code>&lt;?php
// 确保 SEO 变量有默认值
$fallbackSiteName = $settings['site_name'] ?? '懒人导航';
if (!isset($seoTitle)) $seoTitle = $fallbackSiteName;
if (!isset($seoDesc)) $seoDesc = '';
if (!isset($seoKeywords)) $seoKeywords = '';
?&gt;
&lt;?php Plugin::hook('before_header'); ?&gt;
&lt;!DOCTYPE html&gt;
&lt;html lang="zh-CN"&gt;
&lt;head&gt;
&lt;meta charset="UTF-8"&gt;
&lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
&lt;title&gt;&lt;?= Theme::e($seoTitle) ?&gt;&lt;/title&gt;
&lt;?php if (!empty($seoDesc)): ?&gt;
&lt;meta name="description" content="&lt;?= Theme::eAttr($seoDesc) ?&gt;"&gt;
&lt;?php endif; ?&gt;
&lt;link rel="stylesheet" href="&lt;?= Theme::asset('css/common.css') ?&gt;"&gt;
&lt;/head&gt;
&lt;body&gt;
&lt;?php Plugin::hook('after_header'); ?&gt;</code></pre>
        <div class="tip">
          <div class="tip-title">关键点</div>
          <ul>
            <li><code>Plugin::hook('before_header')</code> 和 <code>Plugin::hook('after_header')</code> 是必须的，插件依赖这些钩子注入内容</li>
            <li>所有输出使用 <code>Theme::e()</code> 或 <code>Theme::eAttr()</code> 转义，防止 XSS</li>
            <li>CSS 通过 <code>Theme::asset()</code> 引用，路径自动适配当前主题</li>
          </ul>
        </div>

        <h3>步骤 3：编写 index.php（首页）</h3>
        <pre><code>&lt;?php
Theme::partial('header');
?&gt;

&lt;div class="container"&gt;
  &lt;!-- 搜索栏 --&gt;
  &lt;div class="search-bar"&gt;
    &lt;input type="search" id="searchInput" placeholder="搜索站点..."&gt;
  &lt;/div&gt;
  &lt;?php Plugin::hook('search_bar_after'); ?&gt;

  &lt;!-- 侧边栏 --&gt;
  &lt;aside class="sidebar"&gt;
    &lt;?php Plugin::hook('sidebar_top'); ?&gt;
    &lt;?php foreach ($categories as $cat): ?&gt;
      &lt;a href="&lt;?= Theme::url('category', ['slug' =&gt; $cat['slug']]) ?&gt;"&gt;
        &lt;?= Theme::e($cat['name']) ?&gt;
      &lt;/a&gt;
    &lt;?php endforeach; ?&gt;
    &lt;?php Plugin::hook('sidebar_bottom'); ?&gt;
  &lt;/aside&gt;

  &lt;!-- 站点列表 --&gt;
  &lt;main class="site-list"&gt;
    &lt;?php Plugin::hook('site_list_before'); ?&gt;
    &lt;?php foreach ($currentSites as $site): ?&gt;
      &lt;a href="&lt;?= Theme::url('site', ['id' =&gt; $site['id']]) ?&gt;" class="card"&gt;
        &lt;span class="card-title"&gt;&lt;?= Theme::e($site['name']) ?&gt;&lt;/span&gt;
        &lt;span class="card-desc"&gt;&lt;?= Theme::e($site['description']) ?&gt;&lt;/span&gt;
      &lt;/a&gt;
    &lt;?php endforeach; ?&gt;
    &lt;?php Plugin::hook('site_list_after'); ?&gt;
  &lt;/main&gt;
&lt;/div&gt;

&lt;?php Theme::partial('footer'); ?&gt;</code></pre>
        <p>注意模板中放置了 5 个钩子调用：<code>search_bar_after</code>、<code>sidebar_top</code>、<code>sidebar_bottom</code>、<code>site_list_before</code>、<code>site_list_after</code>。这些钩子让广告插件、文章插件等能在对应位置注入内容。</p>

        <h3>步骤 4：编写 footer.php（公共底部）</h3>
        <pre><code>&lt;?php Plugin::hook('before_footer'); ?&gt;
&lt;footer class="site-footer"&gt;
  &lt;nav class="footer-nav"&gt;
    &lt;a href="&lt;?= Theme::url('home') ?&gt;"&gt;首页&lt;/a&gt;
    &lt;a href="&lt;?= Theme::url('submit') ?&gt;"&gt;提交站点&lt;/a&gt;
  &lt;/nav&gt;
  &lt;p&gt;&lt;?= Theme::e($settings['site_name'] ?? '') ?&gt;&lt;/p&gt;
&lt;/footer&gt;
&lt;?php Plugin::hook('after_footer'); ?&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

        <h3>步骤 5：编写其他页面</h3>
        <p>按同样模式编写 <code>category.php</code>、<code>site.php</code>、<code>search.php</code>、<code>submit.php</code>。每个页面通过 <code>Theme::partial('header')</code> 和 <code>Theme::partial('footer')</code> 引入公共布局，中间放置页面内容。变量由 <code>Route::dispatch()</code> 自动注入（见 3.3 节）。</p>

        <h3>步骤 6：添加 CSS</h3>
        <p>在 <code>templates/mytheme/css/common.css</code> 中编写样式。可以通过 <code>Theme::asset('css/common.css')</code> 引用。</p>

        <h3>步骤 7：后台切换</h3>
        <p>进入后台「基础设置 - 主题设置」，选择 <code>极简主题</code> 并保存，前台立即生效。</p>
      </section>

    </section>

    <!-- ===== 第四章 插件开发 ===== -->
    <section id="ch4" class="doc-section">
      <h1>第四章 插件开发 <button class="share-anchor" data-anchor="ch4" title="复制章节链接">🔗</button></h1>

      <section id="ch4-1" class="doc-section">
        <h2>4.1 插件目录结构</h2>
        <p>插件放在 <code>plugins/{插件名}/</code> 目录下。一个完整的插件包含以下文件：</p>
        <div class="file-tree">
          <span class="dir">plugins/myplugin/</span><br>
          &nbsp;&nbsp;<span class="file">plugin.json</span> <span class="comment">元数据声明（必需）</span><br>
          &nbsp;&nbsp;<span class="file">include.php</span> <span class="comment">主文件：函数定义 + 前台钩子注册（必需）</span><br>
          &nbsp;&nbsp;<span class="file">main.php</span> <span class="comment">后台设置面板：注册设置 Tab 钩子（可选）</span><br>
          &nbsp;&nbsp;<span class="file">schema.php</span> <span class="comment">数据库声明：表、字段、配置（可选）</span><br>
          &nbsp;&nbsp;<span class="file">admin.php</span> <span class="comment">后台管理页面（可选，通过 /admin/plugin.php?p=myplugin 访问）</span><br>
          &nbsp;&nbsp;<span class="dir">css/</span> <span class="comment">插件样式（可选）</span><br>
          &nbsp;&nbsp;<span class="dir">js/</span> <span class="comment">插件脚本（可选）</span>
        </div>
        <div class="tip">
          <div class="tip-title">加载机制</div>
          <ul>
            <li><strong>include.php</strong>：仅在插件启用时由 <code>Plugin::init()</code> 加载，定义类/函数并注册前台钩子</li>
            <li><strong>main.php</strong>：仅在插件启用时加载，注册后台设置 Tab 钩子（<code>admin_settings_nav</code> + <code>admin_settings_tabs</code>）</li>
            <li><strong>schema.php</strong>：在插件启用和卸载时由 <code>Plugin::loadSchema()</code> 加载，返回数组声明</li>
            <li><strong>admin.php</strong>：通过 <code>/admin/plugin.php?p=插件名</code> 访问时加载，需插件已启用</li>
            <li><strong>未启用的插件完全不加载</strong>，不注册任何钩子，不执行任何代码</li>
          </ul>
        </div>
      </section>

      <section id="ch4-2" class="doc-section">
        <h2>4.2 plugin.json</h2>
        <pre><code>{
    "name": "myplugin",
    "title": "我的插件",
    "version": "1.0",
    "author": "你的名字",
    "description": "插件功能描述",
    "main_file": "include.php",
    "config_file": "main.php",
    "schema_file": "schema.php",
    "hooks": ["sidebar_top", "after_footer"],
    "tables": ["mytable"],
    "builtin": false
}</code></pre>
        <table>
          <thead><tr><th>字段</th><th>必填</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>name</code></td><td>是</td><td>插件目录名（必须与文件夹一致）</td></tr>
            <tr><td><code>title</code></td><td>是</td><td>显示名称</td></tr>
            <tr><td><code>version</code></td><td>是</td><td>版本号</td></tr>
            <tr><td><code>author</code></td><td>否</td><td>作者</td></tr>
            <tr><td><code>description</code></td><td>否</td><td>功能描述</td></tr>
            <tr><td><code>main_file</code></td><td>否</td><td>主文件名（默认 <code>{name}.php</code>，通常设为 <code>include.php</code>）</td></tr>
            <tr><td><code>config_file</code></td><td>否</td><td>设置面板文件名（如 <code>main.php</code>）</td></tr>
            <tr><td><code>schema_file</code></td><td>否</td><td>数据库声明文件名（固定 <code>schema.php</code>）</td></tr>
            <tr><td><code>hooks</code></td><td>否</td><td>声明使用的钩子列表（用于文档展示，不影响实际注册）</td></tr>
            <tr><td><code>tables</code></td><td>否</td><td>声明创建的表名列表（用于卸载时的共享表判断）</td></tr>
            <tr><td><code>builtin</code></td><td>否</td><td>是否为内置插件（默认 true）</td></tr>
          </tbody>
        </table>
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
        <p>插件配置项使用 <code>plugin_{插件名}_{配置键}</code> 的命名格式存储在 settings 表中。例如插件 <code>myplugin</code> 的 <code>count</code> 配置存储为 <code>plugin_myplugin_count</code>。</p>
        <p>但有些历史插件（如 wormhole、auto-link）使用不带 <code>plugin_</code> 前缀的简短键名（如 <code>wormhole_enable</code>）。这两种方式都能正常工作，新插件建议使用 <code>plugin_</code> 前缀格式。</p>
      </section>

      <section id="ch4-4" class="doc-section">
        <h2>4.4 include.php 与钩子注册</h2>
        <p><code>include.php</code> 是插件主文件，负责定义函数/类和注册钩子：</p>
        <pre><code>&lt;?php
// 安全检查：阻止直接访问
if (!defined('APP_VERSION') &amp;&amp; !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 输出插件内容
 */
function myplugin_render(): void
{
    $count = (int)Plugin::config('myplugin', 'count', '5');
    // ... 业务逻辑
    echo '&lt;div class="myplugin"&gt;' . $content . '&lt;/div&gt;';
}

// 注册前台钩子
Plugin::registerHook('sidebar_top', function () {
    myplugin_render();
});

// 注册后台侧边栏菜单（如有 admin.php）
Plugin::registerHook('admin_sidebar', function () {
    $cls = ($GLOBALS['currentPage'] ?? '') === 'myplugin' ? 'active' : '';
    echo '&lt;a href="/admin/plugin.php?p=myplugin" class="nav-item ' . $cls . '"&gt;'
       . '&lt;i class="ti ti-star"&gt;&lt;/i&gt;&lt;span&gt;我的插件&lt;/span&gt;&lt;/a&gt;';
});</code></pre>
        <div class="tip">
          <div class="tip-title">关键点</div>
          <ul>
            <li>文件开头必须有安全检查，阻止直接访问</li>
            <li>通过 <code>Plugin::registerHook(钩子名, 回调函数)</code> 注册钩子</li>
            <li>通过 <code>Plugin::config('插件名', '配置键', 默认值)</code> 读取插件配置</li>
            <li>钩子回调函数中的 <code>echo</code> 内容会直接输出到页面</li>
            <li>可注册多个钩子，同一钩子可被多个插件注册</li>
          </ul>
        </div>
      </section>

      <section id="ch4-5" class="doc-section">
        <h2>4.5 main.php 设置面板</h2>
        <p><code>main.php</code> 通过注册两个后台钩子，在基础设置页面注入自定义 Tab：</p>
        <pre><code>&lt;?php
if (!defined('APP_VERSION') &amp;&amp; !class_exists('Database')) {
    die('Forbidden');
}

// 钩子1：注入 Tab 导航标签
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
        &lt;input type="number" name="plugin_myplugin_count"
               value="&lt;?= Security::e(Plugin::config('myplugin', 'count', '5')) ?&gt;"&gt;
      &lt;/div&gt;

      &lt;button type="submit" class="btn btn-primary"&gt;保存&lt;/button&gt;
    &lt;/form&gt;
  &lt;/div&gt;
&lt;/div&gt;
&lt;?php
});</code></pre>
        <div class="tip">
          <div class="tip-title">注意</div>
          <ul>
            <li>表单 POST 到 <code>/admin/settings.php</code>，需要带 <code>csrf_token</code> 和 <code>section</code> 字段</li>
            <li>配置项的 <code>name</code> 属性必须与 schema.php 中声明的配置键一致</li>
            <li>Tab 的 <code>id</code> 必须为 <code>tab-{插件名}</code>，与导航标签的 <code>href</code> 对应</li>
            <li><code>switchTab()</code> 是后台已有的 JS 函数，用于 Tab 切换</li>
          </ul>
        </div>
      </section>

      <section id="ch4-6" class="doc-section">
        <h2>4.6 Plugin 类 API</h2>
        <table>
          <thead><tr><th>方法</th><th>参数</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>Plugin::isEnabled()</code></td><td>$name</td><td>检查插件是否已启用</td></tr>
            <tr><td><code>Plugin::setEnabled()</code></td><td>$name, $enabled</td><td>启用/停用插件（启用时自动 ensureSchema）</td></tr>
            <tr><td><code>Plugin::ensureSchema()</code></td><td>$name</td><td>执行建表、加字段、写配置</td></tr>
            <tr><td><code>Plugin::loadSchema()</code></td><td>$name</td><td>加载 schema.php 返回数组</td></tr>
            <tr><td><code>Plugin::uninstall()</code></td><td>$name</td><td>卸载：停用 + 删表 + 删字段 + 清配置</td></tr>
            <tr><td><code>Plugin::registerHook()</code></td><td>$hook, $callback, $priority=10</td><td>注册钩子回调</td></tr>
            <tr><td><code>Plugin::hook()</code></td><td>$hook, $args=[]</td><td>执行动作钩子（输出模式）</td></tr>
            <tr><td><code>Plugin::filter()</code></td><td>$hook, $value, $args=[]</td><td>执行过滤钩子（返回模式）</td></tr>
            <tr><td><code>Plugin::config()</code></td><td>$plugin, $key, $default=null</td><td>读取插件配置</td></tr>
            <tr><td><code>Plugin::setConfig()</code></td><td>$plugin, $key, $value</td><td>写入插件配置</td></tr>
            <tr><td><code>Plugin::asset()</code></td><td>$plugin, $file</td><td>获取插件资源 URL</td></tr>
            <tr><td><code>Plugin::getDir()</code></td><td>$name</td><td>获取插件目录路径</td></tr>
            <tr><td><code>Plugin::scan()</code></td><td>无</td><td>扫描所有可用插件</td></tr>
            <tr><td><code>Plugin::getInfo()</code></td><td>$name</td><td>获取插件元数据</td></tr>
          </tbody>
        </table>
      </section>

      <section id="ch4-7" class="doc-section">
        <h2>4.7 共享表与卸载</h2>
        <p>当多个插件声明同一张表时（如 <code>blacklist</code> 表被 <code>wormhole</code> 和 <code>auto-link</code> 共享），系统会智能处理：</p>
        <ul>
          <li><strong>启用时</strong>：<code>ensureSchema()</code> 使用 <code>CREATE TABLE IF NOT EXISTS</code>，重复执行安全</li>
          <li><strong>卸载时</strong>：<code>Plugin::uninstall()</code> 检查是否还有其他插件声明该表。如果有，则跳过删表（仅清除当前插件的配置和字段）</li>
          <li><strong>全部卸载时</strong>：当最后一个声明该表的插件被卸载时，才真正执行 <code>DROP TABLE</code></li>
        </ul>
        <p>卸载操作还会：</p>
        <ol>
          <li>删除插件向已有表添加的字段（<code>ALTER TABLE DROP COLUMN</code>）</li>
          <li>清除插件的所有配置项（通过 <code>LIKE 'plugin_{name}_%'</code> 通配匹配 + schema.php 声明的 key）</li>
          <li>记录卸载日志（删了哪些表、哪些字段、清了多少条配置）</li>
        </ol>
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

if (!defined('APP_VERSION') &amp;&amp; !class_exists('Database')) {
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

if (!defined('APP_VERSION') &amp;&amp; !class_exists('Database')) {
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

        <h3>4.8.7 启用插件</h3>
        <ol>
          <li>将 <code>daily-quote</code> 目录放入 <code>plugins/</code></li>
          <li>进入后台「插件管理」，找到「每日一言」</li>
          <li>点击「启用」——系统自动创建 <code>quotes</code> 表、写入 2 条默认配置、加载插件代码</li>
          <li>前台首页侧边栏底部出现每日一言</li>
          <li>后台侧边栏出现「每日一言」管理入口，可添加/删除名言</li>
          <li>后台「基础设置」出现「每日一言」Tab，可修改显示数量</li>
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
        <p>系统内置 9 个插件，全部默认关闭。安装后根据需要在后台「插件管理」中启用。</p>
        <table>
          <thead><tr><th>插件</th><th>功能</th><th>数据库影响</th></tr></thead>
          <tbody>
            <tr><td>广告管理 (ad)</td><td>后台配置广告位 HTML，前台多个位置展示</td><td>6 条配置，无独立表</td></tr>
            <tr><td>文章发布 (article)</td><td>后台文章管理，前台文章列表和详情</td><td>articles 表 + 2 条配置</td></tr>
            <tr><td>虫洞联盟 (wormhole)</td><td>站点互推、随机传送、定时检测</td><td>blacklist 表（共享）+ sites 表 5 字段 + 5 条配置</td></tr>
            <tr><td>友链自动收录 (auto-link)</td><td>检测来路、验证回链、自动收录</td><td>blacklist 表（共享）+ 4 条配置</td></tr>
            <tr><td>伪静态设置 (rewrite)</td><td>URL 格式配置，自动生成服务器规则</td><td>10 条配置</td></tr>
            <tr><td>提交网站收录 (submit)</td><td>前台提交入口、表单、审核流程</td><td>6 条配置</td></tr>
            <tr><td>站点地图 (sitemap)</td><td>自动生成 sitemap.xml</td><td>1 条配置</td></tr>
            <tr><td>图片灯箱 (lightbox)</td><td>详情页图片点击放大</td><td>无数据库影响</td></tr>
            <tr><td>图片ALT (auto-alt)</td><td>自动给图片添加 alt 属性</td><td>无数据库影响</td></tr>
          </tbody>
        </table>
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
        <p>启用后在后台「基础设置 - 广告管理」Tab 填写 HTML 代码。广告 HTML 经过 <code>Security::cleanHtml()</code> 过滤后输出。</p>

        <h3>主题模板中的挂接代码</h3>
        <p>广告插件需要主题在对应位置放置钩子调用。以下是默认主题 <code>templates/default/</code> 中的实际代码示例：</p>

        <h4>1. before_header / after_header（公共头部）</h4>
        <p>文件：<code>templates/default/header.php</code></p>
        <pre><code>&lt;?php Plugin::hook('before_header'); ?&gt;
&lt;!DOCTYPE html&gt;
&lt;html lang="zh-CN"&gt;
&lt;head&gt;
    ...
&lt;/head&gt;
&lt;body&gt;
&lt;?php Plugin::hook('after_header'); ?&gt;</code></pre>

        <h4>2. search_bar_after（搜索栏后）</h4>
        <p>文件：<code>templates/default/index.php</code></p>
        <pre><code>&lt;div class="search-bar"&gt;
    &lt;div class="search-wrap"&gt;
        &lt;i class="ti ti-search"&gt;&lt;/i&gt;
        &lt;input type="search" placeholder="搜索..." id="searchInput"&gt;
    &lt;/div&gt;
    &lt;div class="hot-tags"&gt;
        ...
        &lt;?php Plugin::hook('search_bar_after'); ?&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

        <h4>3. sidebar_top / sidebar_bottom（侧边栏）</h4>
        <p>文件：<code>templates/default/index.php</code></p>
        <pre><code>&lt;aside class="sidebar"&gt;
    &lt;div class="sidebar-header"&gt;全部分类&lt;/div&gt;
    &lt;?php Plugin::hook('sidebar_top'); ?&gt;
    &lt;?php foreach ($categories as $cat): ?&gt;
        &lt;a href="..."&gt;...&lt;/a&gt;
    &lt;?php endforeach; ?&gt;
    &lt;?php Plugin::hook('sidebar_bottom'); ?&gt;
&lt;/aside&gt;</code></pre>

        <h4>4. site_list_before / site_list_after（站点列表）</h4>
        <p>文件：<code>templates/default/index.php</code></p>
        <pre><code>&lt;div class="card-grid" id="site-grid"&gt;
    &lt;?php Plugin::hook('site_list_before'); ?&gt;
    &lt;?= renderSiteCards($currentSites ?? [], $showWeight) ?&gt;
    &lt;?php Plugin::hook('site_list_after'); ?&gt;
&lt;/div&gt;</code></pre>

        <h4>5. before_content / after_content（详情内容）</h4>
        <p>文件：<code>templates/default/site.php</code></p>
        <pre><code>&lt;div class="site-details"&gt;
    &lt;?php Plugin::hook('before_content', [$site ?? []]); ?&gt;
    &lt;div class="detail-row"&gt;
        &lt;div class="detail-label"&gt;描述：&lt;/div&gt;
        &lt;div class="detail-value"&gt;...&lt;/div&gt;
    &lt;/div&gt;
    ...
&lt;/div&gt;

&lt;?php Plugin::hook('after_content', [$site ?? []]); ?&gt;</code></pre>

        <h4>6. before_footer / after_footer（页脚）</h4>
        <p>文件：<code>templates/default/footer.php</code></p>
        <pre><code>&lt;?php Plugin::hook('before_footer'); ?&gt;
&lt;footer class="site-footer"&gt;
    ...
&lt;/footer&gt;
&lt;?php Plugin::hook('after_footer'); ?&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

        <div class="tip">
          <div class="tip-title">自定义主题注意事项</div>
          <p>开发自定义主题时，需要在上述 8 个位置放置对应的 <code>Plugin::hook()</code> 调用，否则广告插件启用后也不会显示广告。钩子参数说明：</p>
          <ul>
            <li><code>before_content</code> 和 <code>after_content</code> 需要传入站点数组 <code>[$site ?? []]</code>，方便插件访问当前站点数据</li>
            <li>其他钩子不需要参数，直接调用 <code>Plugin::hook('钩子名')</code> 即可</li>
          </ul>
        </div>
      </section>

      <section id="ch5-3" class="doc-section">
        <h2>5.3 文章发布 (article)</h2>
        <p>启用后前台侧边栏出现「文章专栏」入口，后台侧边栏出现「文章管理」菜单。支持 Markdown/HTML 内容、分类、标签、草稿/发布状态。</p>
        <p>数据库：创建 <code>articles</code> 表（id、title、slug、content、excerpt、author、category、tags、status、views、created_at、updated_at）+ 2 条配置（每页显示数、是否允许投稿）。</p>

        <h3>钩子与模板挂接</h3>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>sidebar_bottom</code></td><td>前台</td><td>在侧边栏底部注入「文章专栏」入口链接</td></tr>
            <tr><td><code>admin_sidebar</code></td><td>后台</td><td>在后台侧边栏注入「文章管理」导航链接</td></tr>
          </tbody>
        </table>

        <h4>sidebar_bottom 挂接代码</h4>
        <p>文件：<code>templates/default/index.php</code></p>
        <pre><code>&lt;aside class="sidebar"&gt;
    &lt;div class="sidebar-header"&gt;全部分类&lt;/div&gt;
    &lt;?php Plugin::hook('sidebar_top'); ?&gt;
    ...
    &lt;?php Plugin::hook('sidebar_bottom'); ?&gt;
&lt;/aside&gt;</code></pre>
        <div class="tip">
          <div class="tip-title">注意事项</div>
          <p>sidebar_bottom 被多个插件共享使用（广告、虫洞联盟、每日一言等都在此处注册）。主题只需调用一次 <code>Plugin::hook('sidebar_bottom')</code>，所有注册的插件内容都会按优先级顺序输出。</p>
        </div>
      </section>

      <section id="ch5-4" class="doc-section">
        <h2>5.4 虫洞联盟 (wormhole)</h2>
        <p>站点互推机制：联盟成员在页面嵌入 JS，互相展示链接实现流量互传。</p>
        <table>
          <thead><tr><th>状态</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>none</code></td><td>未加入联盟</td></tr>
            <tr><td><code>manual</code></td><td>后台手动加入（不检测）</td></tr>
            <tr><td><code>auto</code></td><td>JS 上报自动加入（每日检测）</td></tr>
            <tr><td><code>pending</code></td><td>待审核</td></tr>
            <tr><td><code>broken</code></td><td>连续检测失败 3 次，已移出</td></tr>
          </tbody>
        </table>

        <h3>钩子与模板挂接</h3>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>sidebar_bottom</code></td><td>前台</td><td>在侧边栏底部注入虫洞联盟入口链接（"🌀 虫洞联盟"）</td></tr>
            <tr><td><code>admin_sidebar</code></td><td>后台</td><td>在后台侧边栏注入「虫洞联盟」管理入口</td></tr>
          </tbody>
        </table>

        <h4>sidebar_bottom 挂接代码</h4>
        <p>文件：<code>templates/default/index.php</code></p>
        <pre><code>&lt;aside class="sidebar"&gt;
    ...
    &lt;?php Plugin::hook('sidebar_bottom'); ?&gt;
&lt;/aside&gt;</code></pre>

        <h4>虫洞联盟入口（主题中硬编码链接）</h4>
        <p>默认主题在 footer.php 中还硬编码了一个虫洞联盟入口链接：</p>
        <pre><code>&lt;!-- templates/default/footer.php --&gt;
&lt;a href="&lt;?= Theme::eAttr(Rewrite::url('wormhole')) ?&gt;" target="_blank"&gt;🌀 虫洞联盟&lt;/a&gt;</code></pre>

        <h3>外站嵌入代码</h3>
        <p>联盟成员需要在页面中嵌入以下 JS：</p>
        <pre><code>&lt;script&gt;
(function(){
    var d=document,s=d.createElement('script');
    s.src='https://你的主站/api/?endpoint=wormhole.js';
    s.async=1;
    d.body.appendChild(s);
})();
&lt;/script&gt;</code></pre>
        <p>定时检测：每天凌晨 3 点执行 <code>core/cron_wormhole_check.php</code>，抓取 auto 成员检查是否包含联盟代码。</p>
        <pre><code>0 3 * * * php /path/to/core/cron_wormhole_check.php</code></pre>
      </section>

      <section id="ch5-5" class="doc-section">
        <h2>5.5 友链自动收录 (auto-link)</h2>
        <p>当用户从挂了导航站友链的外站点击进入时，系统自动检测来路、验证回链、抓取 TDK、检查违禁词，通过后自动收录。</p>
        <p><strong>工作流程</strong>：PHP 渲染首页时捕获 Referer → 过滤本站和搜索引擎 → JS 延迟 2 秒发送 <code>/api/?endpoint=auto-link&ref=xxx</code> → 后端抓取对方首页验证回链 → 抓取 TDK → 检查违禁词 → 插入数据库。</p>
        <p>配置项：<code>autolink_enable</code>（开关）、<code>autolink_review</code>（是否需要审核）、<code>autolink_cat_id</code>（默认分类）、<code>autolink_ban_words</code>（违禁词）、<code>block_all_ip</code>（全局IP屏蔽）。</p>
        <p>安全机制：前端 Referer 预过滤、搜索引擎排除、内网地址防护、黑名单检查、频率限制（6小时3次）、回链验证、违禁词检查、重复域名检查。</p>

        <h3>钩子与模板挂接</h3>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>after_footer</code></td><td>前台</td><td>在页面底部注入自动收录检测 JS（延迟2秒异步执行）</td></tr>
            <tr><td><code>admin_settings_nav</code></td><td>后台</td><td>在基础设置页注入「友链收录」Tab 导航</td></tr>
            <tr><td><code>admin_settings_tabs</code></td><td>后台</td><td>在基础设置页注入「友链收录」Tab 内容面板</td></tr>
          </tbody>
        </table>

        <h4>after_footer 挂接代码</h4>
        <p>文件：<code>templates/default/footer.php</code></p>
        <pre><code>&lt;?php Plugin::hook('before_footer'); ?&gt;
&lt;footer class="site-footer"&gt;...&lt;/footer&gt;
&lt;?php echo $chartJs; ?&gt;
&lt;?php echo $siteJs; ?&gt;
&lt;?php Plugin::hook('after_footer'); ?&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

        <h4>自动收录 JS 注入效果</h4>
        <p>插件通过 <code>after_footer</code> 钩子注入一段延迟执行的 JS：</p>
        <pre><code>&lt;script&gt;
(function(){
    var referer = '捕获的HTTP_REFERER值';
    if (!referer) return;
    setTimeout(function(){
        var img = new Image();
        img.src = '/api/?endpoint=auto-link&ref=' + referer + '&_t=' + Date.now();
    }, 2000);
})();
&lt;/script&gt;</code></pre>

        <div class="tip">
          <div class="tip-title">主题集成要点</div>
          <p>主题 footer.php 中必须调用 <code>Plugin::hook('after_footer')</code>，否则自动收录插件启用后不会注入检测 JS，导致功能无法工作。钩子需要放在 <code>&lt;/body&gt;</code> 标签之前。</p>
        </div>
      </section>

      <section id="ch5-6" class="doc-section">
        <h2>5.6 伪静态设置 (rewrite)</h2>
        <p>URL 伪静态模式配置，支持动态和伪静态两种模式。可自定义各页面 URL 格式，自动生成 Apache .htaccess 和 Nginx 配置规则。</p>
        <p>启用后在后台「基础设置 - 伪静态」Tab 配置。支持子目录部署和自定义 URL 格式。</p>
        <p><code>Rewrite.php</code> 内置 URL 格式默认值（<code>$defaults</code> 数组），不依赖 settings 表，插件启用时才将配置写入数据库。</p>
      </section>

      <section id="ch5-7" class="doc-section">
        <h2>5.7 提交网站收录 (submit)</h2>
        <p>前台提交网站入口，支持表单提交、验证码、TDK 自动获取、审核流程控制。</p>
        <p>配置项：提交开关、是否需要审核、默认分类、违禁词、频率限制、提交说明文本。</p>

        <h3>钩子与模板挂接</h3>
        <table>
          <thead><tr><th>钩子</th><th>位置</th><th>说明</th></tr></thead>
          <tbody>
            <tr><td><code>search_bar_after</code></td><td>前台</td><td>在搜索栏右侧注入「提交站点」按钮</td></tr>
          </tbody>
        </table>

        <h4>search_bar_after 挂接代码</h4>
        <p>文件：<code>templates/default/index.php</code></p>
        <pre><code>&lt;div class="hot-tags"&gt;
    &lt;span class="hot-tag" onclick="searchTag('AI')"&gt;AI&lt;/span&gt;
    ...
    &lt;?php Plugin::hook('search_bar_after'); ?&gt;
&lt;/div&gt;</code></pre>

        <h4>插件注入效果</h4>
        <p>插件注册 <code>search_bar_after</code> 钩子，输出「提交站点」按钮 HTML：</p>
        <pre><code>&lt;a href="/submit/" class="submit-btn"&gt;
    &lt;i class="ti ti-plus"&gt;&lt;/i&gt; 提交站点
&lt;/a&gt;</code></pre>
      </section>

      <section id="ch5-8" class="doc-section">
        <h2>5.8 站点地图 (sitemap)</h2>
        <p>自动生成 <code>sitemap.xml</code>，帮助搜索引擎收录站点。配置项：是否包含已下线站点。</p>
        <p>此插件无前台钩子，在后台「基础设置」中通过 <code>admin_settings_nav</code> 和 <code>admin_settings_tabs</code> 注入设置 Tab。访问 <code>/sitemap.xml</code> 即可获取动态生成的站点地图。</p>
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

        <h4>after_footer 挂接代码</h4>
        <p>文件：<code>templates/default/footer.php</code></p>
        <pre><code>&lt;?php Plugin::hook('before_footer'); ?&gt;
&lt;footer class="site-footer"&gt;...&lt;/footer&gt;
&lt;?php Plugin::hook('after_footer'); ?&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

        <h4>插件注入效果</h4>
        <p>灯箱插件通过 <code>after_footer</code> 钩子注入一段 CSS + JS，自动将 <code>.site-details img</code> 和 <code>.article-content img</code> 的点击事件绑定为灯箱展开：</p>
        <pre><code>&lt;style&gt;
.lightbox-overlay { display:none; position:fixed; ... }
.lightbox-overlay.active { display:flex; }
&lt;/style&gt;
&lt;div class="lightbox-overlay" id="lightboxOverlay"&gt;...&lt;/div&gt;
&lt;script&gt;
(function(){
    var selector = '.site-details img, .article-content img';
    var imgs = document.querySelectorAll(selector);
    imgs.forEach(function(img){
        img.setAttribute('data-lightbox', 'plugin');
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function(e){
            openLightbox(this.src);
        });
    });
})();
&lt;/script&gt;</code></pre>
        <p>支持键盘 ESC 键关闭灯箱，无需额外配置。</p>
      </section>

      <section id="ch5-10" class="doc-section">
        <h2>5.10 图片ALT (auto-alt)</h2>
        <p>纯前端钩子插件，无数据库影响。自动给页面中的图片添加 alt 属性（基于站点名称或上下文），有利于 SEO。</p>
      </section>

    </section>

    <!-- ===== 第六章 参考文档 ===== -->
    <section id="ch6" class="doc-section">
      <h1>第六章 参考文档 <button class="share-anchor" data-anchor="ch6" title="复制章节链接">🔗</button></h1>

      <section id="ch6-1" class="doc-section">
        <h2>6.1 API 接口</h2>
        <p>所有 API 通过 <code>/api/?endpoint={端点名}</code> 访问。</p>
        <table>
          <thead><tr><th>端点</th><th>方法</th><th>说明</th><th>CSRF</th></tr></thead>
          <tbody>
            <tr><td><code>sites</code></td><td>GET</td><td>获取分类站点列表</td><td>否</td></tr>
            <tr><td><code>featured</code></td><td>GET</td><td>获取推荐站点</td><td>否</td></tr>
            <tr><td><code>site</code></td><td>GET</td><td>获取站点详情</td><td>否</td></tr>
            <tr><td><code>search</code></td><td>GET</td><td>搜索站点</td><td>否</td></tr>
            <tr><td><code>submit</code></td><td>POST</td><td>提交站点</td><td>是</td></tr>
            <tr><td><code>click</code></td><td>POST</td><td>记录点击</td><td>否</td></tr>
            <tr><td><code>fetch-tdk</code></td><td>POST</td><td>获取 TDK + 权重</td><td>是</td></tr>
            <tr><td><code>update-meta</code></td><td>POST</td><td>更新站点 TDK + 权重</td><td>是</td></tr>
            <tr><td><code>rate</code></td><td>POST</td><td>提交评分</td><td>否</td></tr>
            <tr><td><code>feedback</code></td><td>POST</td><td>提交反馈</td><td>否</td></tr>
            <tr><td><code>wormhole</code></td><td>GET</td><td>联盟成员列表（需启用虫洞插件）</td><td>否</td></tr>
            <tr><td><code>wormhole.js</code></td><td>GET</td><td>联盟 JS 脚本</td><td>否</td></tr>
            <tr><td><code>wormhole-teleport</code></td><td>GET</td><td>虫洞传送</td><td>否</td></tr>
            <tr><td><code>wormhole-join</code></td><td>GET</td><td>加入上报</td><td>否</td></tr>
            <tr><td><code>auto-link</code></td><td>GET</td><td>友链自动收录（需启用插件）</td><td>否</td></tr>
          </tbody>
        </table>
        <div class="tip">
          <div class="tip-title">插件守护</div>
          <p>wormhole、auto-link、submit 相关的 API 端点在对应插件未启用时会返回 403 或透明 GIF，不会报错。</p>
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

// 获取日志文件路径
$file = Logger::getLogFile('wormhole_join');
// 返回：data/logs/20260808/wormhole_join.log</code></pre>
        <p>日志目录：<code>data/logs/YYYYMMDD/{channel}.log</code></p>
        <p>全局开关：<code>log_global</code>（设为 0 关闭所有日志）。各频道独立开关：<code>log_{channel}</code>。</p>
        <p>常用频道：<code>admin_auth</code>（登录审计）、<code>admin_site</code>（站点操作）、<code>database_error</code>（SQL错误）、<code>security_ratelimit</code>（频率限制）、<code>wormhole_check</code>（虫洞检测）、<code>autolink</code>（自动收录）、<code>plugin_error</code>（插件错误）、<code>plugin_info</code>（插件信息）。</p>
      </section>

      <section id="ch6-3" class="doc-section">
        <h2>6.3 伪静态配置</h2>
        <p>伪静态系统支持两种模式：</p>
        <table>
          <thead><tr><th>模式</th><th>首页</th><th>分类页</th><th>详情页</th></tr></thead>
          <tbody>
            <tr><td>dynamic</td><td><code>/</code></td><td><code>/index.php?route=category&amp;slug=tech</code></td><td><code>/index.php?route=site&amp;id=1</code></td></tr>
            <tr><td>rewrite</td><td><code>/</code></td><td><code>/category/tech/</code></td><td><code>/site/1/</code></td></tr>
          </tbody>
        </table>
        <p>启用 rewrite 插件后，后台自动生成 Apache .htaccess 和 Nginx 配置规则，可一键复制。</p>
      </section>

      <section id="ch6-4" class="doc-section">
        <h2>6.4 安全规范</h2>
        <ul>
          <li><strong>输出转义</strong>：所有输出到 HTML 的内容必须使用 <code>Theme::e()</code> 或 <code>Security::e()</code>；属性值使用 <code>Theme::eAttr()</code></li>
          <li><strong>URL 生成</strong>：必须使用 <code>Theme::url()</code> 生成 URL，不能硬编码</li>
          <li><strong>CSRF 防护</strong>：所有 POST 表单必须包含 <code>Security::csrfField()</code>，后端使用 <code>Security::verifyCSRFToken()</code> 校验</li>
          <li><strong>输入过滤</strong>：使用 <code>Security::cleanString()</code> 清洗字符串、<code>Security::int()</code> 清洗整数、<code>Security::cleanHtml()</code> 清洗 HTML</li>
          <li><strong>频率限制</strong>：使用 <code>Security::rateLimit($key, $maxCount, $windowSeconds)</code> 防止刷接口</li>
          <li><strong>SQL 注入</strong>：所有数据库查询使用 PDO 预处理（<code>Database::query()</code>、<code>Database::execute()</code> 等），禁止拼接 SQL</li>
          <li><strong>文件安全</strong>：<code>data/logs/</code> 目录不可通过 Web 直接访问（已默认受 .htaccess / Nginx 规则保护）</li>
        </ul>
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
