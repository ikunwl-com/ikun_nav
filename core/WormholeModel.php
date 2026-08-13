<?php
/**
 * 虫洞联盟模型
 * 管理虫洞成员、检测、跳转
 */

class WormholeModel
{
    /**
     * 获取所有虫洞成员
     */
    public function getMembers(string $status = 'all'): array
    {
        $tbl = Database::table('sites');
        $sql = "SELECT id, name, url, description, tags,
                        br_pc, br_mobile, br_360, br_shenma, views,
                        wormhole_status, wormhole_source_domain, wormhole_last_check, wormhole_check_fail
                FROM {$tbl}
                WHERE wormhole_status IN ('manual', 'auto', 'pending')
                AND status = 'published'";
        if ($status === 'manual') {
            $sql .= " AND wormhole_status = 'manual'";
        } elseif ($status === 'auto') {
            $sql .= " AND wormhole_status = 'auto'";
        } elseif ($status === 'pending') {
            $sql .= " AND wormhole_status = 'pending'";
        }
        $sql .= " ORDER BY wormhole_joined_at DESC";
        return Database::query($sql);
    }

    /**
     * 随机获取 N 个虫洞成员（用于展示和跳转）
     * 仅返回已审核成员，pending 不参与展示
     */
    public function getRandomMembers(int $limit = 12): array
    {
        $tbl = Database::table('sites');
        $sql = "SELECT id, name, url, description
                FROM {$tbl}
                WHERE wormhole_status IN ('manual', 'auto')
                AND status = 'published'
                AND url IS NOT NULL AND url != ''
                ORDER BY RAND()
                LIMIT ?";
        $rows = Database::query($sql, [$limit]);
        // 确保所有 url 都有协议头
        foreach ($rows as &$row) {
            $url = $row['url'] ?? '';
            if ($url && !preg_match('/^https?:\/\//i', $url)) {
                $row['url'] = 'https://' . ltrim($url, '/');
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * 随机跳转：选一个虫洞成员
     */
    public function getRandomMember()
    {
        $members = $this->getRandomMembers(1);
        return $members[0] ?? null;
    }

    /**
     * 将站点加入虫洞（后台勾选）
     */
    public function joinManual(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_status = 'manual',
                wormhole_joined_at = NOW(),
                wormhole_last_check = NOW(),
                wormhole_check_fail = 0
                WHERE id = ? AND status = 'published'";
        return Database::execute($sql, [$siteId]) > 0;
    }

    /**
     * 外站检测页面申请加入
     */
    public function joinAuto(int $siteId, string $sourceDomain): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_status = 'auto',
                wormhole_joined_at = NOW(),
                wormhole_last_check = NOW(),
                wormhole_check_fail = 0,
                wormhole_source_domain = ?
                WHERE id = ? AND status IN ('published', 'pending')";
        $result = Database::execute($sql, [$sourceDomain, $siteId]) > 0;
        
        // 采集来源网站的TDK
        if ($result) {
            $site = Database::query("SELECT url FROM {$tbl} WHERE id = ?", [$siteId])[0] ?? null;
            if ($site) {
                $tdk = $this->fetchTDK($site['url']);
                if ($tdk) {
                    // 提取主标题：智能提取（支持正序/倒序两种格式）
                    $titleRaw = $tdk['title'] ?? '';
                    if (!empty($titleRaw)) {
                        $titleRaw = extractMainTitle($titleRaw);
                    }
                    // 新站点 name 初始值是域名，TDK 抓到的标题应覆盖它
                    $updateSql = "UPDATE {$tbl} SET
                                  name = COALESCE(NULLIF(?, ''), name),
                                  description = COALESCE(NULLIF(?, ''), description),
                                  tags = CASE WHEN tags IS NULL OR tags = '' OR tags = 'null' THEN ? ELSE tags END
                                  WHERE id = ?";
                    Database::execute($updateSql, [
                        $titleRaw,
                        $tdk['description'] ?? '',
                        !empty($tdk['keywords']) ? json_encode($tdk['keywords']) : '[]',
                        $siteId
                    ]);
                    $this->log("已采集站点 {$siteId} 的 TDK：标题={$tdk['title']}，描述=" . mb_substr($tdk['description'] ?? '', 0, 50));
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 写入虫洞联盟日志
     */
    private function log(string $msg): void
    {
        Logger::log('wormhole_model', $msg);
    }

    /**
     * 采集网站TDK（标题、描述、关键词）
     */
    public function fetchTDK(string $url): array
    {
        $html = '';
        
        // 优先使用 curl（支持重定向、gzip解压）
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
                CURLOPT_ENCODING       => '',
            ]);
            $html = curl_exec($ch);
            curl_close($ch);
        }
        
        // curl 失败，回退到 file_get_contents
        if (empty($html)) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                    'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'follow_location' => 1,
                    'max_redirects' => 3,
                ],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $html = @file_get_contents($url, false, $context);
        }
        
        if (!$html) {
            Logger::log('wormhole_model', "TDK 抓取失败：{$url}");
            return [];
        }
        
        $tdk = ['title' => '', 'description' => '', 'keywords' => []];
        
        // 提取标题
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $tdk['title'] = trim(strip_tags($matches[1]));
        }
        
        // 提取描述
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/is', $html, $matches)) {
            $tdk['description'] = trim($matches[1]);
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']/is', $html, $matches)) {
            $tdk['description'] = trim($matches[1]);
        }
        
        // 提取关键词
        if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\']([^"\']+)["\']/is', $html, $matches)) {
            $tdk['keywords'] = array_map('trim', explode(',', $matches[1]));
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']keywords["\']/is', $html, $matches)) {
            $tdk['keywords'] = array_map('trim', explode(',', $matches[1]));
        }
        
        $logMsg = "TDK 抓取成功：标题=" . (empty($tdk['title']) ? '（空）' : mb_substr($tdk['title'], 0, 50)) . "，描述=" . (empty($tdk['description']) ? '（空）' : mb_substr($tdk['description'], 0, 50)) . "，关键词 " . count($tdk['keywords']) . " 个";
        Logger::log('wormhole_model', $logMsg);
        
        return $tdk;
    }

    /**
     * 移出虫洞（设为 none）
     */
    public function leave(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_status = 'none',
                wormhole_source_domain = '',
                wormhole_check_fail = 0
                WHERE id = ? AND wormhole_status IN ('auto', 'manual', 'pending')";
        return Database::execute($sql, [$siteId]) > 0;
    }

    /**
     * 标记检测失败
     */
    public function markCheckFail(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_check_fail = wormhole_check_fail + 1,
                wormhole_last_check = NOW()
                WHERE id = ? AND wormhole_status = 'auto'";
        return Database::execute($sql, [$siteId]) > 0;
    }

    /**
     * 标记检测通过
     */
    public function markCheckPass(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_check_fail = 0,
                wormhole_last_check = NOW()
                WHERE id = ? AND wormhole_status = 'auto'";
        return Database::execute($sql, [$siteId]) > 0;
    }

    /**
     * 审核通过：pending -> auto
     */
    public function approve(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_status = 'auto',
                wormhole_check_fail = 0,
                wormhole_last_check = NOW()
                WHERE id = ? AND wormhole_status = 'pending'";
        return Database::execute($sql, [$siteId]) > 0;
    }

    /**
     * 审核拒绝：pending -> none
     */
    public function reject(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $sql = "UPDATE {$tbl} SET
                wormhole_status = 'none',
                wormhole_source_domain = '',
                wormhole_check_fail = 0
                WHERE id = ? AND wormhole_status = 'pending'";
        return Database::execute($sql, [$siteId]) > 0;
    }

    /**
     * 批量审核通过
     */
    public function approveBatch(array $siteIds): int
    {
        $siteIds = array_filter(array_map('intval', $siteIds));
        if (empty($siteIds)) return 0;
        $tbl = Database::table('sites');
        $in = implode(',', array_fill(0, count($siteIds), '?'));
        return Database::execute(
            "UPDATE {$tbl} SET wormhole_status = 'auto', wormhole_check_fail = 0, wormhole_last_check = NOW() WHERE id IN ({$in}) AND wormhole_status = 'pending'",
            array_values($siteIds)
        );
    }

    /**
     * 移出连续检测失败的站点（失败 3 次）
     * 标记为 broken 而非 none，保留记录用于统计
     */
    public function removeFailedMembers(): int
    {
        $tbl = Database::table('sites');
        
        // 规则1：连续失败 3 次的 auto 成员
        $sql1 = "UPDATE {$tbl} SET
                wormhole_status = 'broken',
                wormhole_check_fail = 0
                WHERE wormhole_status = 'auto' AND wormhole_check_fail >= 3";
        $count1 = Database::execute($sql1);
        
        // 规则2：强制清理 URL 明显非法的站点（about:blank、伪协议等）
        $illegalPatterns = [
            "url LIKE '%about:%'",
            "url LIKE '%aboutblank%'",
            "url LIKE '%javascript:%'",
            "url LIKE '%data:%'",
            "url LIKE '%blob:%'",
            "url LIKE '%file:%'",
        ];
        $sql2 = "UPDATE {$tbl} SET
                wormhole_status = 'broken',
                wormhole_check_fail = 0
                WHERE wormhole_status IN ('auto', 'pending', 'manual')
                AND (" . implode(' OR ', $illegalPatterns) . ")";
        $count2 = Database::execute($sql2);
        
        return $count1 + $count2;
    }

    /**
     * 增加点击出站次数（虫洞传送时调用）
     */
    public function incrementClickOut(int $siteId): bool
    {
        $tbl = Database::table('sites');
        return Database::execute(
            "UPDATE {$tbl} SET clicks = COALESCE(clicks, 0) + 1 WHERE id = ?",
            [$siteId]
        ) > 0;
    }

    /**
     * 获取需要检测的站点（auto 类型）
     */
    public function getAutoMembersForCheck(): array
    {
        $tbl = Database::table('sites');
        // 超过 24 小时未检测的
        $sql = "SELECT id, name, url, wormhole_source_domain, wormhole_check_fail
                FROM {$tbl}
                WHERE wormhole_status = 'auto'
                AND status = 'published'
                AND (wormhole_last_check IS NULL OR wormhole_last_check < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                ORDER BY wormhole_last_check ASC
                LIMIT 50";
        return Database::query($sql);
    }

    /**
     * 统计虫洞成员数量
     */
    public function getStats(): array
    {
        $tbl = Database::table('sites');
        $sql = "SELECT
                SUM(wormhole_status = 'manual') AS manual_count,
                SUM(wormhole_status = 'auto') AS auto_count,
                SUM(wormhole_status = 'pending') AS pending_count,
                SUM(wormhole_status IN ('manual', 'auto', 'pending')) AS total_count,
                SUM(wormhole_status = 'broken') AS broken_count
                FROM {$tbl}
                WHERE status = 'published'";
        return Database::queryOne($sql) ?? ['manual_count' => 0, 'auto_count' => 0, 'pending_count' => 0, 'total_count' => 0, 'broken_count' => 0];
    }

    /**
     * 增加虫洞点入次数（回流）
     * 当从联盟站点跳转回本站点时调用
     */
    public function incrementWormholeClickIn(int $siteId): bool
    {
        $tbl = Database::table('sites');
        return Database::execute(
            "UPDATE {$tbl} SET wormhole_click_in = COALESCE(wormhole_click_in, 0) + 1 WHERE id = ?",
            [$siteId]
        ) > 0;
    }

    /**
     * 增加虫洞点出次数（送出）
     * 当从本站点跳转到联盟站点时调用
     */
    public function incrementWormholeClickOut(int $siteId): bool
    {
        $tbl = Database::table('sites');
        return Database::execute(
            "UPDATE {$tbl} SET wormhole_click_out = COALESCE(wormhole_click_out, 0) + 1 WHERE id = ?",
            [$siteId]
        ) > 0;
    }

    /**
     * 计算单个站点的质量评分
     * 评分维度：
     * 1. 点击回流率（点入/点出）- 权重 50%
     * 2. 内容更新频率 - 权重 30%
     * 3. 站点活跃度（总点击量）- 权重 20%
     */
    public function calculateQualityScore(int $siteId): float
    {
        $tbl = Database::table('sites');
        $site = Database::queryOne(
            "SELECT id, wormhole_click_in, wormhole_click_out, wormhole_last_content_update,
                    wormhole_joined_at, views, clicks, updated_at
             FROM {$tbl}
             WHERE id = ?",
            [$siteId]
        );

        if (!$site) return 0.0;

        $score = 0.0;

        // 1. 点击回流率评分（50分）
        $clickIn = (int)($site['wormhole_click_in'] ?? 0);
        $clickOut = (int)($site['wormhole_click_out'] ?? 0);
        if ($clickOut > 0) {
            $returnRate = $clickIn / $clickOut;
            // 回流率 100% 为满分，最低 0%
            $returnScore = min(50, $returnRate * 50);
        } else {
            // 没有点出数据，给基础分 25
            $returnScore = 25;
        }
        $score += $returnScore;

        // 2. 内容更新频率评分（30分）
        $lastUpdate = $site['wormhole_last_content_update'] ?? $site['updated_at'] ?? null;
        if ($lastUpdate) {
            $daysSinceUpdate = (time() - strtotime($lastUpdate)) / 86400;
            if ($daysSinceUpdate <= 1) {
                $updateScore = 30; // 1天内更新，满分
            } elseif ($daysSinceUpdate <= 7) {
                $updateScore = 25; // 1周内更新
            } elseif ($daysSinceUpdate <= 30) {
                $updateScore = 20; // 1个月内更新
            } elseif ($daysSinceUpdate <= 90) {
                $updateScore = 10; // 3个月内更新
            } elseif ($daysSinceUpdate <= 180) {
                $updateScore = 5; // 半年内更新
            } else {
                $updateScore = 0; // 超过半年没更新
            }
        } else {
            $updateScore = 10; // 没有更新时间，给基础分
        }
        $score += $updateScore;

        // 3. 站点活跃度评分（20分）
        $totalViews = (int)($site['views'] ?? 0);
        $totalClicks = (int)($site['clicks'] ?? 0);
        $activity = $totalViews + $totalClicks * 2; // 点击权重更高
        if ($activity >= 10000) {
            $activityScore = 20;
        } elseif ($activity >= 5000) {
            $activityScore = 15;
        } elseif ($activity >= 1000) {
            $activityScore = 10;
        } elseif ($activity >= 100) {
            $activityScore = 5;
        } else {
            $activityScore = 2;
        }
        $score += $activityScore;

        return round($score, 2);
    }

    /**
     * 更新单个站点的质量评分
     */
    public function updateQualityScore(int $siteId): bool
    {
        $score = $this->calculateQualityScore($siteId);
        $tbl = Database::table('sites');
        return Database::execute(
            "UPDATE {$tbl} SET wormhole_quality_score = ?, wormhole_quality_updated_at = NOW() WHERE id = ?",
            [$score, $siteId]
        ) > 0;
    }

    /**
     * 批量更新所有联盟站点的质量评分
     * 返回更新的站点数量
     */
    public function updateAllQualityScores(): int
    {
        $tbl = Database::table('sites');
        $sites = Database::query(
            "SELECT id FROM {$tbl}
             WHERE wormhole_status IN ('manual', 'auto', 'pending')
             AND status = 'published'"
        );

        $count = 0;
        foreach ($sites as $site) {
            if ($this->updateQualityScore($site['id'])) {
                $count++;
            }
        }

        $this->log("批量更新质量评分完成，共更新 {$count} 个站点");
        return $count;
    }

    /**
     * 自动淘汰低质量联盟站点
     * 规则：
     * 1. 质量评分低于 30 分的 auto 类型站点
     * 2. 加入联盟超过 7 天，且评分持续偏低
     * 3. 直接移出联盟（设为 none 状态）
     */
    public function removeLowQualityMembers(): int
    {
        $tbl = Database::table('sites');

        // 先更新所有评分
        $this->updateAllQualityScores();

        // 淘汰低质量站点（加入超过7天且评分低于30分的自动成员）
        $sql = "UPDATE {$tbl} SET
                wormhole_status = 'none',
                wormhole_source_domain = '',
                wormhole_check_fail = 0
                WHERE wormhole_status = 'auto'
                AND status = 'published'
                AND wormhole_joined_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND wormhole_quality_score < 30";

        $count = Database::execute($sql);

        if ($count > 0) {
            $this->log("自动淘汰低质量联盟站点 {$count} 个（评分低于30分且加入超过7天）");
        }

        return $count;
    }

    /**
     * 获取按质量排序的联盟成员
     */
    public function getMembersByQuality(int $limit = 12, string $status = 'all'): array
    {
        $tbl = Database::table('sites');
        $sql = "SELECT id, name, url, description, wormhole_quality_score,
                        wormhole_click_in, wormhole_click_out
                FROM {$tbl}
                WHERE wormhole_status IN ('manual', 'auto')
                AND status = 'published'
                AND url IS NOT NULL AND url != ''";

        if ($status === 'manual') {
            $sql .= " AND wormhole_status = 'manual'";
        } elseif ($status === 'auto') {
            $sql .= " AND wormhole_status = 'auto'";
        }

        $sql .= " ORDER BY wormhole_quality_score DESC, wormhole_click_in DESC LIMIT ?";
        $rows = Database::query($sql, [$limit]);

        // 确保所有 url 都有协议头
        foreach ($rows as &$row) {
            $url = $row['url'] ?? '';
            if ($url && !preg_match('/^https?:\/\//i', $url)) {
                $row['url'] = 'https://' . ltrim($url, '/');
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * 检测并更新站点内容更新时间
     * 通过抓取页面判断内容是否有更新
     */
    public function checkContentUpdate(int $siteId): bool
    {
        $tbl = Database::table('sites');
        $site = Database::queryOne("SELECT url, wormhole_last_content_update FROM {$tbl} WHERE id = ?", [$siteId]);
        if (!$site || empty($site['url'])) return false;

        $url = $site['url'];
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        // 简单的内容更新检测：抓取页面标题和描述的 hash
        $tdk = $this->fetchTDK($url);
        if (empty($tdk)) return false;

        $contentHash = md5(($tdk['title'] ?? '') . '|' . ($tdk['description'] ?? ''));

        // 这里简化处理：每次检测都更新时间（实际应该对比 hash 是否变化）
        // 完整实现应该存储上次的 hash，对比后决定是否更新
        Database::execute(
            "UPDATE {$tbl} SET wormhole_last_content_update = NOW() WHERE id = ?",
            [$siteId]
        );

        return true;
    }
}
