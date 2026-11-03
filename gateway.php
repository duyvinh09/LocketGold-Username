<?php
/**
 * Cổng API duy nhất — không gọi trực tiếp api.php / locketGold.php từ client.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$serverKey = $_POST['server_key'] ?? '';
$trustedProxy = $serverKey !== '' && hash_equals(LocketAuth::getApiSecret(), $serverKey);

if (!$trustedProxy) {
    LocketAuth::requireApiAccess();
}

define('LOCKET_INTERNAL', true);

require_once __DIR__ . '/api_core.php';
require_once __DIR__ . '/gold_core.php';
require_once __DIR__ . '/rate_limit.php';

$action = $_POST['action'] ?? '';

if ($action === 'activate') {
    checkIpRateLimit();
}

/**
 * Tự động dọn dẹp các file cache & limit rác đã hết hạn để giải phóng dung lượng hosting
 */
function autoCleanupStorage() {
    $cleanupLog = __DIR__ . '/storage/last_cleanup.txt';
    $now = time();

    // Chỉ thực hiện dọn dẹp tối đa 1 lần mỗi 30 phút để không ảnh hưởng tốc độ
    if (file_exists($cleanupLog) && ($now - (int)@file_get_contents($cleanupLog)) < 1800) {
        return;
    }

    @file_put_contents($cleanupLog, $now);

    // 1. Dọn dẹp storage/user_cache (Xóa file rác quá 2 giờ)
    $userCacheDir = __DIR__ . '/storage/user_cache';
    if (is_dir($userCacheDir)) {
        foreach (glob($userCacheDir . '/*.json') as $file) {
            if (($now - filemtime($file)) > 7200) {
                @unlink($file);
            }
        }
    }

    // 2. Dọn dẹp storage/search_limits (Xóa file giới hạn quá 10 phút)
    $searchLimitDir = __DIR__ . '/storage/search_limits';
    if (is_dir($searchLimitDir)) {
        foreach (glob($searchLimitDir . '/*.json') as $file) {
            if (($now - filemtime($file)) > 600) {
                @unlink($file);
            }
        }
    }

    // 3. Dọn dẹp storage/limits (Xóa file giới hạn cũ quá 24 giờ)
    $limitDir = __DIR__ . '/storage/limits';
    if (is_dir($limitDir)) {
        foreach (glob($limitDir . '/*.json') as $file) {
            if (($now - filemtime($file)) > 86400) {
                @unlink($file);
            }
        }
    }
}

switch ($action) {
    case 'queue_sync':
        $_GET['action'] = 'get_status';
        require_once __DIR__ . '/queue_api.php';
        exit;

    case 'queue_join':
        $_GET['action'] = 'join';
        require_once __DIR__ . '/queue_api.php';
        exit;

    case 'queue_finish':
        $_GET['action'] = 'finish';
        require_once __DIR__ . '/queue_api.php';
        exit;

    case 'limit_status':
        $limitRes = [
            'success' => true,
            'limit' => getIpStatus()
        ];
        if (isset($_SESSION['new_csrf_token_issued'])) {
            $limitRes['new_csrf_token'] = $_SESSION['new_csrf_token_issued'];
            unset($_SESSION['new_csrf_token_issued']);
        }
        echo json_encode($limitRes, JSON_UNESCAPED_UNICODE);
        exit;

    case 'search':
        autoCleanupStorage();

        $input = trim($_POST['username'] ?? '');
        if ($input === '') {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập username'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Tần suất tìm kiếm đơn giản theo visitor ID (Max 30 request/phút ngoại trừ admin)
        if (!isLimitAdmin()) {
            $searchLimitDir = __DIR__ . '/storage/search_limits';
            if (!is_dir($searchLimitDir)) @mkdir($searchLimitDir, 0755, true);
            $visitorFile = $searchLimitDir . '/' . getVisitorIdForLimit() . '.json';
            $now = time();
            $limitData = file_exists($visitorFile) ? json_decode(@file_get_contents($visitorFile), true) : ['count' => 0, 'start' => $now];
            if (!is_array($limitData) || ($now - ($limitData['start'] ?? 0)) > 60) {
                $limitData = ['count' => 1, 'start' => $now];
            } else {
                $limitData['count']++;
            }
            @file_put_contents($visitorFile, json_encode($limitData), LOCK_EX);
            if ($limitData['count'] > 30) {
                echo json_encode(['success' => false, 'message' => 'Bạn đã tìm kiếm quá nhiều lần. Vui lòng chờ 1 phút.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $api = new LocketAPI();

        // 0. Kiểm tra nếu input là link copy / invite link (locket.camera/links/... hoặc /invites/...)
        if ($api->isInviteLink($input)) {
            $result = $api->getUserByInviteLink($input);
            if ($result['success'] && isset($result['data']) && is_array($result['data'])) {
                $result['data'] = LocketAuth::sanitizePublicUser($result['data']);
                $result['resolved_username'] = 'link_invite';
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
        }

        $username = strtolower($api->extractUsername($input));

        // 1. Kiểm tra Server Cache (TTL: 15 phút = 900 giây)
        $userCacheDir = __DIR__ . '/storage/user_cache';
        if (!is_dir($userCacheDir)) @mkdir($userCacheDir, 0755, true);
        $userCacheFile = $userCacheDir . '/' . md5($username) . '.json';

        if (file_exists($userCacheFile) && (time() - filemtime($userCacheFile)) < 900) {
            $cachedContent = @file_get_contents($userCacheFile);
            $cachedData = json_decode($cachedContent, true);
            if (is_array($cachedData) && !empty($cachedData['success'])) {
                $cachedData['from_cache'] = true;
                echo json_encode($cachedData, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // 2. Lấy dữ liệu từ Locket API nếu chưa có cache
        $result = $api->getUserByUsername($username);
        if ($result['success'] && isset($result['data']) && is_array($result['data'])) {
            $result['data'] = LocketAuth::sanitizePublicUser($result['data']);
            $result['resolved_username'] = $username;
            // Lưu kết quả tìm kiếm thành công vào cache với LOCK_EX
            @file_put_contents($userCacheFile, json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);
        } else {
            $result['resolved_username'] = $username;
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'check':
        $uid = trim($_POST['uid'] ?? '');
        if ($uid === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu UID'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $gold = new LocketGold();
        $result = LocketAuth::sanitizeGoldResponse($gold->checkGoldStatus($uid));
        
        $ipStatus = getIpStatus();
        $result['is_new_ip'] = $ipStatus['is_new_ip'];
        $result['is_first_of_day'] = $ipStatus['is_first_of_day'];
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'activate':
        $uid = trim($_POST['uid'] ?? '');
        if ($uid === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu UID'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $gold = new LocketGold();
        $isSandbox = isset($_POST['sandbox']) ? filter_var($_POST['sandbox'], FILTER_VALIDATE_BOOLEAN) : true;
        $result = LocketAuth::sanitizeGoldResponse($gold->activateGold($uid, null, null, $isSandbox));
        if ($result['success'] ?? false) {
            $result['activated_at'] = date('c');
            $result['expires_date'] = $result['expires_date'] ?? null;
            $result['has_gold'] = $result['has_gold'] ?? true;

            // Chỉ tính 1 lượt khi API kích hoạt trả về thành công.
            recordSuccessfulGoldActivation();
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ'], JSON_UNESCAPED_UNICODE);
}