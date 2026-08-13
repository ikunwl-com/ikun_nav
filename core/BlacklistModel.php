<?php
/**
 * 黑名单模型
 * 管理 IP 和域名黑名单，用于拦截自动收录和自动加入联盟
 */

class BlacklistModel
{
    /**
     * 获取所有黑名单记录
     */
    public function getAll(string $type = '', string $search = '', int $page = 1, int $perPage = 20): array
    {
        $tbl = Database::table('blacklist');
        $where = ['1=1'];
        $params = [];

        if ($type !== '' && in_array($type, ['ip', 'domain'])) {
            $where[] = "type = ?";
            $params[] = $type;
        }

        if ($search !== '') {
            $where[] = "(value LIKE ? OR remark LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        // 总数
        $total = (int)Database::scalar(
            "SELECT COUNT(*) FROM {$tbl} WHERE {$whereSql}",
            $params
        );

        // 分页数据
        $offset = ($page - 1) * $perPage;
        $items = Database::query(
            "SELECT * FROM {$tbl} WHERE {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * 检查 IP 是否在黑名单中
     */
    public function isIpBlocked(string $ip): bool
    {
        if (empty($ip)) return false;
        $tbl = Database::table('blacklist');
        $exists = Database::queryOne(
            "SELECT id FROM {$tbl} WHERE type = 'ip' AND value = ? AND status = 1 LIMIT 1",
            [$ip]
        );
        return $exists !== null;
    }

    /**
     * 检查域名是否在黑名单中（支持子域名匹配）
     */
    public function isDomainBlocked(string $domain): bool
    {
        if (empty($domain)) return false;

        $tbl = Database::table('blacklist');

        // 精确匹配
        $exact = Database::queryOne(
            "SELECT id FROM {$tbl} WHERE type = 'domain' AND value = ? AND status = 1 LIMIT 1",
            [$domain]
        );
        if ($exact) return true;

        // 子域名匹配：如屏蔽 ikunwl.com，则 site.ikunwl.com 也会被拦截
        $parts = explode('.', $domain);
        // 从最精确的子域名开始尝试，逐步缩短
        for ($i = 1; $i < count($parts); $i++) {
            $parentDomain = implode('.', array_slice($parts, $i));
            if (empty($parentDomain)) continue;

            $found = Database::queryOne(
                "SELECT id FROM {$tbl} WHERE type = 'domain' AND value = ? AND status = 1 LIMIT 1",
                [$parentDomain]
            );
            if ($found) return true;
        }

        return false;
    }

    /**
     * 检查 URL 是否在黑名单中（提取域名后检查）
     */
    public function isUrlBlocked(string $url): bool
    {
        $domain = Security::extractDomain($url);
        return $this->isDomainBlocked($domain);
    }

    /**
     * 根据来源信息综合检查是否被屏蔽
     * 同时检查 IP 和域名
     */
    public function isBlocked(string $ip = '', string $domain = ''): bool
    {
        if (!empty($ip) && $this->isIpBlocked($ip)) {
            return true;
        }
        if (!empty($domain) && $this->isDomainBlocked($domain)) {
            return true;
        }
        return false;
    }

    /**
     * 添加黑名单记录
     */
    public function add(string $type, string $value, string $remark = '', int $adminId = 0): int
    {
        $value = trim($value);
        if (empty($value)) return 0;

        // 域名统一小写
        if ($type === 'domain') {
            $value = strtolower($value);
            // 去除协议头和 www 前缀
            $value = preg_replace('/^https?:\/\//i', '', $value);
            $value = preg_replace('/^www\./i', '', $value);
            // 去除路径和端口
            $value = explode('/', $value)[0];
            $value = explode(':', $value)[0];
        }

        $tbl = Database::table('blacklist');

        // 检查是否已存在
        $exists = Database::queryOne(
            "SELECT id FROM {$tbl} WHERE type = ? AND value = ? LIMIT 1",
            [$type, $value]
        );
        if ($exists) {
            return -1; // 已存在
        }

        return Database::insert(
            "INSERT INTO {$tbl} (type, value, remark, created_by, created_at, status) VALUES (?, ?, ?, ?, NOW(), 1)",
            [$type, $value, $remark, $adminId]
        );
    }

    /**
     * 自动检测值类型：IP 或域名
     */
    public static function detectType(string $value): string
    {
        $value = trim($value);
        // 移除协议头和路径，只取 host 部分
        if (preg_match('/^(https?:\/\/)?([^\/\s:]+)/', $value, $m)) {
            $value = $m[2];
        }
        // 移除端口
        $value = preg_replace('/:\d+$/', '', $value);
        // 判断是否为 IP 地址（支持 IPv4）
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return 'ip';
        }
        return 'domain';
    }

    /**
     * 批量添加黑名单记录（自动识别类型）
     * 支持逗号、空格、换行分隔
     */
    public function addBatchAuto(string $input, string $remark = '', int $adminId = 0): array
    {
        $lines = preg_split('/[,\s\n\r]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        $added = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($lines as $value) {
            $value = trim($value);
            if (empty($value)) continue;

            $type = self::detectType($value);
            $result = $this->add($type, $value, $remark, $adminId);
            if ($result > 0) {
                $added++;
            } elseif ($result === -1) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        return [
            'added' => $added,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * 批量添加黑名单记录（指定类型）
     * 支持逗号、空格、换行分隔
     */
    public function addBatch(string $type, string $input, string $remark = '', int $adminId = 0): array
    {
        $lines = preg_split('/[,\s\n\r]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        $added = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($lines as $value) {
            $value = trim($value);
            if (empty($value)) continue;

            $result = $this->add($type, $value, $remark, $adminId);
            if ($result > 0) {
                $added++;
            } elseif ($result === -1) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        return [
            'added' => $added,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * 删除黑名单记录
     */
    public function delete(int $id): bool
    {
        $tbl = Database::table('blacklist');
        return Database::execute(
            "DELETE FROM {$tbl} WHERE id = ?",
            [$id]
        ) > 0;
    }

    /**
     * 批量删除
     */
    public function deleteBatch(array $ids): int
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) return 0;

        $tbl = Database::table('blacklist');
        $in = implode(',', array_fill(0, count($ids), '?'));
        return Database::execute(
            "DELETE FROM {$tbl} WHERE id IN ({$in})",
            array_values($ids)
        );
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $tbl = Database::table('blacklist');
        $ipCount = (int)Database::scalar("SELECT COUNT(*) FROM {$tbl} WHERE type = 'ip' AND status = 1");
        $domainCount = (int)Database::scalar("SELECT COUNT(*) FROM {$tbl} WHERE type = 'domain' AND status = 1");

        return [
            'ip_count' => $ipCount,
            'domain_count' => $domainCount,
            'total_count' => $ipCount + $domainCount,
        ];
    }
}
