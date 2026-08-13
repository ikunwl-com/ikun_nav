<?php
/**
 * 虫洞联盟 - 每日自动检测脚本
 *
 * 功能：
 * 1. 获取所有 auto 类型的虫洞成员（24 小时未检测）
 * 2. 抓取对方网页内容，检查是否包含联盟代码（JS嵌入/PHP引用/友链传送）
 * 3. 不包含则标记失败，连续失败 3 次自动移出联盟
 * 4. 手动勾选（manual）的成员不检测
 *
 * 触发方式：
 *   - 后台手动触发：通过 admin/wormhole.php 的「全量检测」按钮
 *   - Cron 定时：php /www/wwwroot/site.ikunwl.com/core/cron_wormhole_check.php
 *   - 访问触发：每次有人访问前台时检查上次检测时间，超过 24 小时则异步触发
 */

// ===== 日志配置 =====
function whLog(string $msg): void {
    Logger::log('wormhole_check', $msg);
}

// ===== CLI 检测 =====
$isCli = php_sapi_name() === 'cli';

if ($isCli) {
    // CLI 模式下强制开启错误报告，方便排查问题
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

if (!$isCli) {
    // 非 CLI 模式下只加载框架，不执行检测
    require_once __DIR__ . '/bootstrap.php';
}

// ===== 检测执行 =====
if ($isCli) {
    try {
        require_once __DIR__ . '/bootstrap.php';

        whLog('=== 虫洞联盟自动检测开始 ===');

        $result = runWormholeCheck();

        whLog("检测完成: 检测{$result['checked']}个, 通过{$result['passed']}个, 失败{$result['failed']}个, 移出{$result['removed']}个");

        if (!empty($result['details'])) {
            foreach ($result['details'] as $d) {
                $status = $d['result'] === 'pass' ? '通过' : "失败({$d['result']}, 累计{$d['fails']}次)";
                whLog("  [{$status}] {$d['name']} - {$d['url']}");
            }
        }

        whLog('=== 检测结束 ===');

        // 同时输出到 stdout，方便宝塔日志查看
        echo "=== 虫洞联盟自动检测 ===\n";
        echo "时间: " . date('Y-m-d H:i:s') . "\n\n";
        echo "检测完成:\n";
        echo "  检测数量: {$result['checked']}\n";
        echo "  通过: {$result['passed']}\n";
        echo "  失败: {$result['failed']}\n";
        echo "  移出: {$result['removed']}\n\n";
        if (!empty($result['details'])) {
            echo "详情:\n";
            foreach ($result['details'] as $d) {
                $status = $d['result'] === 'pass' ? '✓ 通过' : "✗ 失败({$d['result']}, 累计{$d['fails']}次)";
                echo "  [{$status}] {$d['name']} - {$d['url']}\n";
            }
        }
        echo "\n完成。\n";

    } catch (Throwable $e) {
        $err = "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        whLog($err);
        whLog($e->getTraceAsString());
        echo $err . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
    exit(0);
}

/**
 * 执行虫洞联盟检测
 * @return array{checked: int, passed: int, failed: int, removed: int, details: array}
 */
function runWormholeCheck(): array
{
    $wormhole = new WormholeModel();
    $siteUrl = getSiteUrl();

    // 要检测的标识特征（对方网页中应包含以下任一）
    $markers = [
        $siteUrl . '/api/?endpoint=wormhole',     // HTML 展示 / PHP 引用
        $siteUrl . '/api/?endpoint=wormhole.js',  // JS 嵌入
        $siteUrl . '/api/?endpoint=wormhole-teleport', // 友链传送
        'wormhole',                               // 通用关键词
    ];

    $members = $wormhole->getAutoMembersForCheck();

    $checked = 0;
    $passed = 0;
    $failed = 0;
    $removed = 0;
    $details = [];

    foreach ($members as $member) {
        $checked++;
        $url = $member['url'];
        $failCount = (int)$member['wormhole_check_fail'];

        // 抓取对方网页
        $html = fetchPageContent($url);

        if ($html === null) {
            // 抓取失败
            $wormhole->markCheckFail((int)$member['id']);
            $failCount++;
            $failed++;
            $details[] = [
                'name'   => $member['name'],
                'url'    => $url,
                'result' => 'fetch_failed',
                'fails'  => $failCount,
            ];
            continue;
        }

        // 检查是否包含联盟标识
        $found = false;
        foreach ($markers as $marker) {
            if (stripos($html, $marker) !== false) {
                $found = true;
                break;
            }
        }

        if ($found) {
            $wormhole->markCheckPass((int)$member['id']);
            $passed++;
            $details[] = [
                'name'   => $member['name'],
                'url'    => $url,
                'result' => 'pass',
                'fails'  => 0,
            ];
        } else {
            $wormhole->markCheckFail((int)$member['id']);
            $failCount++;
            $failed++;
            $details[] = [
                'name'   => $member['name'],
                'url'    => $url,
                'result' => 'not_found',
                'fails'  => $failCount,
            ];
        }
    }

    // 先清理 URL 明显非法的成员（无需等 3 次失败）
    $cleanedIllegal = 0;
    foreach ($members as $member) {
        $url = $member['url'] ?? '';
        $domain = Security::extractDomain($url);
        if (empty($domain) || Security::isInternalHost($domain)) {
            $wormhole->leave((int)$member['id']);
            $cleanedIllegal++;
            continue;
        }
        $badSchemes = ['about', 'javascript', 'data', 'blob', 'file'];
        foreach ($badSchemes as $scheme) {
            if (stripos($domain, $scheme . ':') === 0) {
                $wormhole->leave((int)$member['id']);
                $cleanedIllegal++;
                break;
            }
        }
    }

    // 移出连续失败 3 次的成员
    $removed = $wormhole->removeFailedMembers();
    $removed += $cleanedIllegal;

    return [
        'checked' => $checked,
        'passed'  => $passed,
        'failed'  => $failed,
        'removed' => $removed,
        'details' => $details,
    ];
}

/**
 * 抓取网页内容
 * @return string|null HTML 内容，失败返回 null
 */
function fetchPageContent(string $url): ?string
{
    // URL 安全检查
    $domain = Security::extractDomain($url);
    if (empty($domain) || Security::isInternalHost($domain)) {
        return null;
    }

    // 拒绝伪协议域名（about:blank、javascript: 等）
    $badSchemes = ['about', 'javascript', 'data', 'blob', 'file'];
    foreach ($badSchemes as $scheme) {
        if (stripos($domain, $scheme . ':') === 0) {
            return null;
        }
    }

    if (!function_exists('curl_init')) {
        // 无 cURL 时用 file_get_contents
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 10,
                'user_agent'    => 'LazyNavWormholeBot/1.0',
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
            ],
        ]);
        $html = @file_get_contents($url, false, $ctx);
        return $html === false ? null : $html;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: LazyNavWormholeBot/1.0',
            'Accept: text/html,application/xhtml+xml',
        ],
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode >= 400) {
        return null;
    }

    return $html ?: null;
}
