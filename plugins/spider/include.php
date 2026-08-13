<?php
/**
 * 蜘蛛来访统计插件 - 主文件
 *
 * 功能：
 *   1. 在每次请求时检测 User-Agent 识别搜索引擎蜘蛛
 *   2. 将蜘蛛来访记录写入 spider_visits 表
 *   3. 数据自动保留 30 天，超期自动清理
 *   4. 后台侧边栏注入「蜘蛛来访」管理入口
 *
 * 配置项：
 *   plugin_spider_retention_days - 数据保留天数（默认30）
 *   plugin_spider_engines        - 启用的引擎列表（逗号分隔）
 *
 * 数据库表：{prefix}spider_visits
 *
 * 钩子：
 *   before_header  - 前台请求入口，检测并记录蜘蛛来访
 *   admin_sidebar  - 后台侧边栏注入管理导航
 */

if (!defined('APP_VERSION') || !class_exists('Database')) {
    die('Forbidden');
}

/**
 * 蜘蛛来访统计模型
 */
class SpiderModel
{
    /**
     * 支持的搜索引擎列表
     * key = 内部标识，value = [name(显示名), patterns(UA匹配正则数组), color(图表颜色), icon(Tabler Icon类名)]
     */
    public static array $engines = [
        'baidu'     => ['name' => '百度',   'patterns' => ['Baiduspider'],                 'color' => '#2932E1', 'icon' => 'ti-brand-baidu'],
        'google'    => ['name' => 'Google', 'patterns' => ['Googlebot'],                    'color' => '#4285F4', 'icon' => 'ti-brand-google'],
        'bing'      => ['name' => 'Bing',   'patterns' => ['bingbot'],                      'color' => '#008373', 'icon' => 'ti-brand-bing'],
        'sogou'     => ['name' => '搜狗',   'patterns' => ['Sogou web spider', 'sogou'],   'color' => '#FF6F00', 'icon' => 'ti-search'],
        '360'       => ['name' => '360',    'patterns' => ['360Spider'],                    'color' => '#19BE4F', 'icon' => 'ti-circle-dot'],
        'bytespider'=> ['name' => '字节',   'patterns' => ['Bytespider'],                   'color' => '#325AB4', 'icon' => 'ti-brand-tiktok'],
        'yandex'    => ['name' => 'Yandex', 'patterns' => ['YandexBot'],                    'color' => '#FF3333', 'icon' => 'ti-brand-yandex'],
    ];

    /**
     * 根据 User-Agent 识别蜘蛛类型
     * @return string|null 匹配到返回引擎标识，不匹配返回 null
     */
    public static function detectSpider(string $ua): ?string
    {
        if (empty($ua)) {
            return null;
        }
        foreach (self::$engines as $key => $info) {
            foreach ($info['patterns'] as $pattern) {
                if (stripos($ua, $pattern) !== false) {
                    return $key;
                }
            }
        }
        return null;
    }

    /**
     * 获取已启用的引擎列表
     * @return array [key, ...] 启用的引擎标识
     */
    public static function getEnabledEngines(): array
    {
        $configured = Plugin::config('spider', 'engines', 'baidu,google,bing,sogou,360,bytespider,yandex');
        $list = array_filter(array_map('trim', explode(',', $configured)));
        // 只返回在 $engines 中存在的
        return array_values(array_filter($list, function ($k) {
            return isset(self::$engines[$k]);
        }));
    }

    /**
     * 检查某引擎是否已启用
     */
    public static function isEngineEnabled(string $engine): bool
    {
        return in_array($engine, self::getEnabledEngines(), true);
    }

    /**
     * 记录一次蜘蛛来访
     */
    public function recordVisit(string $spiderType, string $url, string $ip, string $ua): void
    {
        $tbl = Database::table('spider_visits');
        Database::execute(
            "INSERT INTO {$tbl} (spider_type, url, ip, user_agent, visited_at) VALUES (?, ?, ?, ?, NOW())",
            [$spiderType, $url, $ip, $ua]
        );
    }

    /**
     * 清理过期数据
     * @return int 删除的行数
     */
    public function purgeOldRecords(): int
    {
        $days = (int)Plugin::config('spider', 'retention_days', '30');
        if ($days <= 0) {
            $days = 30;
        }
        $tbl = Database::table('spider_visits');
        return Database::execute(
            "DELETE FROM {$tbl} WHERE visited_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }

    /**
     * 清空所有记录
     * @return int 删除的行数
     */
    public function clearAll(): int
    {
        $tbl = Database::table('spider_visits');
        $count = (int)Database::scalar("SELECT COUNT(*) FROM {$tbl}");
        if ($count > 0) {
            Database::execute("TRUNCATE TABLE {$tbl}");
        }
        return $count;
    }

    /**
     * 删除指定引擎的记录
     */
    public function clearByEngine(string $engine): int
    {
        $tbl = Database::table('spider_visits');
        $count = (int)Database::scalar("SELECT COUNT(*) FROM {$tbl} WHERE spider_type = ?", [$engine]);
        if ($count > 0) {
            Database::execute("DELETE FROM {$tbl} WHERE spider_type = ?", [$engine]);
        }
        return $count;
    }

    /**
     * 获取今日各引擎统计
     * @return array [spider_type => count]
     */
    public function getTodayStats(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT spider_type, COUNT(*) as cnt FROM {$tbl}
             WHERE visited_at >= CURDATE()
             GROUP BY spider_type ORDER BY cnt DESC"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['spider_type']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * 获取昨日各引擎统计
     */
    public function getYesterdayStats(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT spider_type, COUNT(*) as cnt FROM {$tbl}
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
               AND visited_at < CURDATE()
             GROUP BY spider_type ORDER BY cnt DESC"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['spider_type']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * 获取近7天各引擎统计
     */
    public function getRecent7Stats(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT spider_type, COUNT(*) as cnt FROM {$tbl}
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY spider_type ORDER BY cnt DESC"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['spider_type']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * 获取近30天各引擎统计
     */
    public function getRecent30Stats(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT spider_type, COUNT(*) as cnt FROM {$tbl}
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY spider_type ORDER BY cnt DESC"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['spider_type']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * 获取近30天每日趋势数据
     * @return array [date => ['total'=>N, 'baidu'=>N, 'google'=>N, ...]]
     */
    public function getTrend30Days(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT DATE(visited_at) as visit_date, spider_type, COUNT(*) as cnt
             FROM {$tbl}
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
             GROUP BY DATE(visited_at), spider_type
             ORDER BY visit_date ASC"
        );

        // 初始化30天日期框架
        $result = [];
        $enabledEngines = self::getEnabledEngines();
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $result[$date] = ['total' => 0];
            foreach ($enabledEngines as $engine) {
                $result[$date][$engine] = 0;
            }
        }

        // 填充数据
        foreach ($rows as $row) {
            $date = $row['visit_date'];
            if (isset($result[$date])) {
                $result[$date]['total'] += (int)$row['cnt'];
                if (isset($result[$date][$row['spider_type']])) {
                    $result[$date][$row['spider_type']] += (int)$row['cnt'];
                }
            }
        }

        return $result;
    }

    /**
     * 获取来访记录列表（分页）
     */
    public function getVisitList(int $page = 1, int $perPage = 20, string $filterEngine = ''): array
    {
        $tbl = Database::table('spider_visits');
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT id, spider_type, url, ip, user_agent, visited_at FROM {$tbl}";
        $params = [];
        if (!empty($filterEngine)) {
            $sql .= " WHERE spider_type = ?";
            $params[] = $filterEngine;
        }
        $sql .= " ORDER BY visited_at DESC LIMIT {$perPage} OFFSET {$offset}";
        return Database::query($sql, $params);
    }

    /**
     * 获取记录总数
     */
    public function count(string $filterEngine = ''): int
    {
        $tbl = Database::table('spider_visits');
        $sql = "SELECT COUNT(*) as cnt FROM {$tbl}";
        $params = [];
        if (!empty($filterEngine)) {
            $sql .= " WHERE spider_type = ?";
            $params[] = $filterEngine;
        }
        $row = Database::queryOne($sql, $params);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * 获取各引擎的最近来访时间
     * @return array [spider_type => last_visit_time]
     */
    public function getLastVisitTimes(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT spider_type, MAX(visited_at) as last_visit
             FROM {$tbl}
             GROUP BY spider_type"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['spider_type']] = $row['last_visit'];
        }
        return $result;
    }

    /**
     * 获取各引擎的独立IP数（近30天）
     */
    public function getUniqueIpCount(): array
    {
        $tbl = Database::table('spider_visits');
        $rows = Database::query(
            "SELECT spider_type, COUNT(DISTINCT ip) as cnt
             FROM {$tbl}
             WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND ip != ''
             GROUP BY spider_type ORDER BY cnt DESC"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['spider_type']] = (int)$row['cnt'];
        }
        return $result;
    }
}

// ========== 钩子注册：仅在插件启用时注册 ==========
if (Plugin::isEnabled('spider')) {

    // 前台请求入口：检测并记录蜘蛛来访
    Plugin::registerHook('before_header', function () {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $spiderType = SpiderModel::detectSpider($ua);

        if ($spiderType === null) {
            return;
        }

        // 只统计已启用的引擎
        if (!SpiderModel::isEngineEnabled($spiderType)) {
            return;
        }

        // 获取请求URL（去掉查询参数，只保留路径）
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        // 截断过长的URL
        if (strlen($url) > 450) {
            $url = substr($url, 0, 450);
        }

        // 获取IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (empty($ip)) {
            // 尝试获取真实IP（代理场景）
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            $ip = trim(explode(',', $ip)[0]);
        }
        $ip = substr($ip, 0, 45);

        // 截断UA
        $ua = substr($ua, 0, 500);

        // 异常静默写入，不影响前台正常输出
        try {
            $model = new SpiderModel();
            $model->recordVisit($spiderType, $url, $ip, $ua);

            // 概率性清理过期数据（约每100次请求清理一次，避免每次请求都执行DELETE）
            if (mt_rand(1, 100) === 1) {
                $model->purgeOldRecords();
            }
        } catch (Throwable $e) {
            // 静默失败，不影响前台渲染
            if (class_exists('Logger')) {
                Logger::log('plugin_error', '蜘蛛统计写入失败: ' . $e->getMessage());
            }
        }
    });

    // 后台侧边栏钩子：注入蜘蛛来访管理入口
    Plugin::registerHook('admin_sidebar', function () {
        $cls = ($GLOBALS['currentPage'] ?? '') === 'spider' ? 'active' : '';
        echo '<a href="/admin/plugin.php?p=spider" class="nav-item ' . $cls . '">'
           . '<i class="ti ti-bug"></i><span>蜘蛛来访</span></a>';
    });
}
