<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

/**
 * CONFIG
 */
define('MAX_DAILY_SUCCESS', 2); // Số lượt giới hạn cho user nè
define('LIMIT_DIR', __DIR__ . '/storage/limits');
define('LIMIT_CLEANUP_FILE', __DIR__ . '/storage/limits_cleanup.json');

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/ctv_core.php';
/**
 * Hàm phụ trợ tách tên thiết bị từ chuỗi User Agent
 */
function getDeviceFromUserAgent($ua) {
    if (empty($ua)) return 'Thiết bị ẩn danh';

    if (preg_match('/iPhone/i', $ua)) return 'iPhone';
    if (preg_match('/iPad/i', $ua)) return 'iPad';
    if (preg_match('/Redmi/i', $ua)) return 'Xiaomi Redmi';
    if (preg_match('/Realme/i', $ua)) return 'Realme';
    if (preg_match('/Pixel/i', $ua)) return 'Google Pixel';
    if (preg_match('/Samsung|Galaxy/i', $ua)) return 'Samsung';

    if (preg_match('/Android/i', $ua)) {
        if (preg_match('/Android\s+[^;]+;\s+([^;\)]+)/i', $ua, $matches)) {
            return trim($matches[1]);
        }
        return 'Thiết bị Android';
    }

    if (preg_match('/Macintosh|Mac OS X/i', $ua)) return 'Máy tính Mac/Macbook';
    if (preg_match('/Windows NT/i', $ua)) return 'Máy tính Windows PC';
    if (preg_match('/Linux/i', $ua)) return 'Máy tính Linux';

    return 'Thiết bị lạ (Chưa xác định)';
}

function ensureLimitDir() {
    if (!is_dir(LIMIT_DIR)) {
        @mkdir(LIMIT_DIR, 0755, true);
    }
}

/**
 * Lấy IP thật nhất có thể.
 */
function getClientIpForLimit() {
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $userIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $userIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $userIp = $_SERVER['HTTP_CLIENT_IP'];
    }

    return $userIp;
}

/**
 * Tạo/Lấy visitor_id định danh.
 * Kết hợp Cookie + Fallback theo Fingerprint (IP + UserAgent).
 * Nếu xoá cookie/cache, hệ thống vẫn giữ nguyên visitor_id theo thiết bị thay vì reset ngẫu nhiên.
 */
function getVisitorIdForLimit() {
    if (!empty($_COOKIE['locket_visitor_id']) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE['locket_visitor_id'])) {
        return $_COOKIE['locket_visitor_id'];
    }

    $ip = getClientIpForLimit();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UnknownDevice';

    // Tạo Fingerprint ID dựa trên IP + User-Agent (32 ký tự hex)
    $visitorId = md5("locket_device_salt_v2_" . $ip . "_" . $ua);

    setcookie('locket_visitor_id', $visitorId, [
        'expires'  => time() + 86400 * 365,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    $_COOKIE['locket_visitor_id'] = $visitorId;

    return $visitorId;
}

/**
 * Admin không bị limit.
 * Không dùng IP nữa, mà dùng cookie admin.
 */
function isLimitAdmin() {
    $adminCookie = $_COOKIE['locket_admin_token'] ?? '';
    if (!$adminCookie) return false;

    return hash_equals(hash('sha256', ADMIN_SECRET_KEY), $adminCookie);
}

/**
 * CTV đang active (theo visitor_id + hạn trong JSON).
 */
function getActiveCtvAccount() {
    static $cached = false;
    static $account = null;

    if ($cached) {
        return $account;
    }

    $cached = true;
    $account = getActiveCtvForVisitor(getVisitorIdForLimit());
    return $account;
}

function isLimitCtv() {
    return getActiveCtvAccount() !== null;
}

/**
 * Quota ngày theo role: admin = ∞, ctv = admin setup, user = MAX_DAILY_SUCCESS.
 */
function getDailyLimitMax() {
    if (isLimitAdmin()) {
        return 999999;
    }

    $ctv = getActiveCtvAccount();
    if ($ctv) {
        return max(1, (int)($ctv['daily_limit'] ?? CTV_DEFAULT_DAILY_LIMIT));
    }

    return MAX_DAILY_SUCCESS;
}

function getLimitFilePath() {
    ensureLimitDir();
    return LIMIT_DIR . '/' . getVisitorIdForLimit() . '.json';
}
/**
 * Tạo cấu trúc JSON dễ truy vấn hơn.
 */
function buildDefaultLimitData() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Không rõ User Agent';
    $max = getDailyLimitMax();

    return [
        'visitor_id' => getVisitorIdForLimit(),
        'is_admin' => isLimitAdmin(),
        'is_ctv' => isLimitCtv(),
        'created_at' => date('c'),
        'updated_at' => date('c'),

        'client' => [
            'ip' => getClientIpForLimit(),
            'device' => getDeviceFromUserAgent($ua),
            'user_agent' => $ua
        ],

        'daily' => [
            date('Y-m-d') => [
                'success_count' => 0,
                'remaining' => $max,
                'first_seen_at' => date('c'),
                'last_seen_at' => date('c'),
                'last_success_at' => null
            ]
        ]
    ];
}
function readLimitData($fp, $logFile) {
    clearstatcache(true, $logFile);
    $fileSize = file_exists($logFile) ? filesize($logFile) : 0;

    if ($fileSize <= 0) {
        return buildDefaultLimitData();
    }

    rewind($fp);
    $content = fread($fp, $fileSize);
    $data = json_decode($content, true);

    if (!is_array($data)) {
        return buildDefaultLimitData();
    }

    return normalizeLimitData($data);
}

/**
 * Hỗ trợ đọc cả JSON cũ.
 * Nếu file cũ chỉ có count/date/ip thì tự chuyển sang format mới.
 */
function normalizeLimitData($data) {
    $today = date('Y-m-d');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Không rõ User Agent';
    $max = getDailyLimitMax();

    if (!isset($data['visitor_id'])) {
        $oldDate = $data['date'] ?? $today;
        $oldCount = (int)($data['count'] ?? 0);

        $data = [
            'visitor_id' => getVisitorIdForLimit(),
            'is_admin' => $oldCount < 0,
            'is_ctv' => isLimitCtv(),
            'created_at' => date('c'),
            'updated_at' => date('c'),

            'client' => [
                'ip' => $data['ip'] ?? getClientIpForLimit(),
                'device' => $data['device'] ?? getDeviceFromUserAgent($ua),
                'user_agent' => $data['user_agent'] ?? $ua
            ],

            'daily' => [
                $oldDate => [
                    'success_count' => max(0, $oldCount),
                    'remaining' => max(0, $max - max(0, $oldCount)),
                    'first_seen_at' => date('c'),
                    'last_seen_at' => date('c'),
                    'last_success_at' => null
                ]
            ]
        ];
    }

    if (!isset($data['daily'][$today])) {
        $data['daily'][$today] = [
            'success_count' => 0,
            'remaining' => $max,
            'first_seen_at' => date('c'),
            'last_seen_at' => date('c'),
            'last_success_at' => null
        ];
    }

    $wasCtv = !empty($data['is_ctv']);
    $isCtvNow = isLimitCtv();

    // Nếu trước đó là user thường, bây giờ vừa nâng cấp lên CTV trong ngày -> Reset lượt sử dụng trong ngày về 0 để CTV hưởng trọn quota mới
    if (!$wasCtv && $isCtvNow) {
        $data['daily'][$today]['success_count'] = 0;
    }

    $data['is_admin'] = isLimitAdmin();
    $data['is_ctv'] = $isCtvNow;
    $data['updated_at'] = date('c');
    $data['client'] = [
        'ip' => getClientIpForLimit(),
        'device' => getDeviceFromUserAgent($ua),
        'user_agent' => $ua
    ];

    // Đồng bộ remaining theo quota hiện tại (user/ctv có thể đổi).
    $count = (int)($data['daily'][$today]['success_count'] ?? 0);
    $data['daily'][$today]['remaining'] = max(0, $max - $count);

    return $data;
}
function writeLimitData($fp, $data) {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
}

function getTodaySuccessCountFromData($data) {
    $today = date('Y-m-d');
    return (int)($data['daily'][$today]['success_count'] ?? 0);
}

function updateTodayMeta(&$data, $successCount = null) {
    $today = date('Y-m-d');
    $max = getDailyLimitMax();

    if (!isset($data['daily'][$today])) {
        $data['daily'][$today] = [
            'success_count' => 0,
            'remaining' => $max,
            'first_seen_at' => date('c'),
            'last_seen_at' => date('c'),
            'last_success_at' => null
        ];
    }

    if ($successCount !== null) {
        $data['daily'][$today]['success_count'] = max(0, (int)$successCount);
        $data['daily'][$today]['last_success_at'] = date('c');
    }

    $count = (int)$data['daily'][$today]['success_count'];
    $data['daily'][$today]['remaining'] = max(0, $max - $count);
    $data['daily'][$today]['last_seen_at'] = date('c');
    $data['updated_at'] = date('c');
}

function denyRateLimit() {
    $max = getDailyLimitMax();
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => "Bạn đã hết {$max} lượt kích hoạt Gold thành công của ngày hôm nay rồi nha! Hãy quay lại vào ngày mai nhó ✨"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
/**
 * Tự cleanup file giới hạn rác (đã quá 24 giờ) để giải phóng dung lượng hosting.
 * Các file chưa đủ 24h trong ngày vẫn được giữ lại để chống lách luật khi xoá cache.
 */
function cleanupLimitsEvery3Days() {
    ensureLimitDir();

    $now = time();
    $needCleanup = false;

    if (!file_exists(LIMIT_CLEANUP_FILE)) {
        $needCleanup = true;
    } else {
        $cleanupData = json_decode(@file_get_contents(LIMIT_CLEANUP_FILE), true);
        $lastCleanup = (int)($cleanupData['last_cleanup_ts'] ?? 0);

        if (($now - $lastCleanup) >= 21600) { // Kiểm tra mỗi 6 giờ
            $needCleanup = true;
        }
    }

    if (!$needCleanup) return;

    $files = glob(LIMIT_DIR . '/*.json') ?: [];
    foreach ($files as $file) {
        if (is_file($file)) {
            // Chỉ xóa các file limit không có tương tác trong 24 giờ qua (file cũ của ngày trước)
            if (($now - filemtime($file)) >= 86400) {
                @unlink($file);
            }
        }
    }

    file_put_contents(LIMIT_CLEANUP_FILE, json_encode([
        'last_cleanup_at' => date('c'),
        'last_cleanup_ts' => $now,
        'message' => 'Auto cleanup limits older than 24 hours'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Chỉ kiểm tra hạn mức, KHÔNG tăng lượt.
 * Gọi trước khi kích hoạt để chặn người đã hết lượt.
 */
function checkIpRateLimit() {
    cleanupLimitsEvery3Days();

    if (isLimitAdmin()) {
        return;
    }

    $max = getDailyLimitMax();
    $logFile = getLimitFilePath();
    $fp = fopen($logFile, 'c+');
    if (!$fp) return;

    if (flock($fp, LOCK_EX)) {
        $data = readLimitData($fp, $logFile);
        updateTodayMeta($data);

        $count = getTodaySuccessCountFromData($data);

        if ($count >= $max) {
            writeLimitData($fp, $data);
            flock($fp, LOCK_UN);
            fclose($fp);
            denyRateLimit();
        }

        writeLimitData($fp, $data);

        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

/**
 * Chỉ cộng lượt khi kích hoạt Gold thật sự thành công.
 */
function recordSuccessfulGoldActivation() {
    cleanupLimitsEvery3Days();

    if (isLimitAdmin()) {
        return;
    }

    $max = getDailyLimitMax();
    $logFile = getLimitFilePath();
    $fp = fopen($logFile, 'c+');
    if (!$fp) return;

    if (flock($fp, LOCK_EX)) {
        $data = readLimitData($fp, $logFile);
        $count = getTodaySuccessCountFromData($data);

        if ($count >= $max) {
            writeLimitData($fp, $data);
            flock($fp, LOCK_UN);
            fclose($fp);
            denyRateLimit();
        }

        updateTodayMeta($data, $count + 1);
        writeLimitData($fp, $data);

        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

function getIpStatus() {
    cleanupLimitsEvery3Days();

    if (isLimitAdmin()) {
        return [
            'is_new_ip' => false,
            'is_first_of_day' => false,
            'is_admin' => true,
            'is_ctv' => false,
            'role' => 'admin',
            'daily_limit' => 999,
            'today_success_count' => 0,
            'today_remaining' => 999
        ];
    }

    $max = getDailyLimitMax();
    $ctv = getActiveCtvAccount();
    $isCtv = $ctv !== null;
    $logFile = getLimitFilePath();

    if (!file_exists($logFile)) {
        return [
            'is_new_ip' => true,
            'is_first_of_day' => true,
            'is_admin' => false,
            'is_ctv' => $isCtv,
            'role' => $isCtv ? 'ctv' : 'user',
            'daily_limit' => $max,
            'ctv_expires_at' => $isCtv ? ($ctv['expires_at'] ?? null) : null,
            'today_success_count' => 0,
            'today_remaining' => $max
        ];
    }

    $data = json_decode(file_get_contents($logFile), true);
    $data = normalizeLimitData(is_array($data) ? $data : []);

    $today = date('Y-m-d');
    $count = (int)($data['daily'][$today]['success_count'] ?? 0);

    return [
        'is_new_ip' => false,
        'is_first_of_day' => $count === 0,
        'is_admin' => false,
        'is_ctv' => $isCtv,
        'role' => $isCtv ? 'ctv' : 'user',
        'daily_limit' => $max,
        'ctv_expires_at' => $isCtv ? ($ctv['expires_at'] ?? null) : null,
        'today_success_count' => $count,
        'today_remaining' => max(0, $max - $count)
    ];
}