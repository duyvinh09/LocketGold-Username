<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json; charset=utf-8');

$storageDir = __DIR__ . '/storage/queue_system';
if (!is_dir($storageDir)) { @mkdir($storageDir, 0755, true); }

define('ONLINE_FILE', $storageDir . '/online.json');
define('QUEUE_FILE', $storageDir . '/queue.json');

// Lấy IP của Client
$userIp = $_SERVER['REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $userIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
}
$ipHash = md5($userIp);

// Nhận hành động từ Front-end (get_status, join, finish)
$action = $_GET['action'] ?? 'get_status';

// 1. XỬ LÝ ĐẾM SỐ NGƯỜI ONLINE (Đơn giản bằng thời gian tương tác)
function updateAndGetOnlineCount($ipHash) {
    $now = time();
    $file = ONLINE_FILE;
    $onlineUsers = [];
    
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content) {
            $onlineUsers = json_decode($content, true) ?: [];
        }
    }

    $lastSeen = $onlineUsers[$ipHash] ?? 0;
    $needsUpdate = ($now - $lastSeen) > 5;
    
    $onlineUsers[$ipHash] = $now;
    
    $activeCount = 0;
    foreach ($onlineUsers as $ip => $time) {
        if ($now - $time > 45) {
            unset($onlineUsers[$ip]);
            $needsUpdate = true;
        } else {
            $activeCount++;
        }
    }

    if ($needsUpdate) {
        @file_put_contents($file, json_encode($onlineUsers), LOCK_EX);
    }

    return max(1, $activeCount);
}

$totalOnline = updateAndGetOnlineCount($ipHash);

// 2. XỬ LÝ HÀNG ĐỢI KÍCH HOẠT GOLD
$queueResult = ['status' => 'not_in_queue', 'position' => -1, 'queue_count' => 0];

$fp = fopen(QUEUE_FILE, 'c+');
if ($fp && flock($fp, LOCK_EX)) { // Khóa file chống xung đột
    clearstatcache(true, QUEUE_FILE);
    $fileSize = filesize(QUEUE_FILE);
    $queue = [];
    if ($fileSize > 0) {
        $queue = json_decode(fread($fp, $fileSize), true) ?: [];
    }
    
    $now = time();
    
    // Sửa lỗi treo hàng đợi: Nếu ông đầu tiên giữ chỗ quá 25 giây mà không báo xong -> Đuổi ra cho người sau vào
    if (!empty($queue) && $now > $queue[0]['expires_at']) {
        array_shift($queue);
        if (!empty($queue)) { $queue[0]['expires_at'] = $now + 25; } // Cấp 25 giây cho người kế tiếp
    }
    
    // Tìm vị trí của user hiện tại trong hàng đợi
    $userIndex = -1;
    foreach ($queue as $index => $item) {
        if ($item['ip'] === $ipHash) { $userIndex = $index; break; }
    }
    
    // Hành động: Tham gia hàng đợi kích hoạt
    if ($action === 'join') {
        if ($userIndex === -1) {
            // Nếu là người đầu tiên thì cho chạy luôn (sống trong 25s), nếu là người sau thì đợi xếp hàng
            $expiresAt = empty($queue) ? ($now + 25) : ($now + 9999);
            $queue[] = ['ip' => $ipHash, 'expires_at' => $expiresAt];
            $userIndex = count($queue) - 1;
        }
    }
    // Hành động: Kích hoạt xong, tự out ra khỏi hàng đợi
    elseif ($action === 'finish') {
        if ($userIndex === 0) {
            array_shift($queue); // Xóa ông đầu tiên
            if (!empty($queue)) { $queue[0]['expires_at'] = $now + 25; } // Kích hoạt thời gian cho ông thứ 2
            $userIndex = -1;
        }
    }
    
    // Trả kết quả trạng thái hàng đợi
    if ($userIndex === 0) {
        $queueResult = ['status' => 'your_turn', 'position' => 0, 'queue_count' => count($queue)];
    } elseif ($userIndex > 0) {
        $queueResult = ['status' => 'waiting', 'position' => $userIndex, 'queue_count' => count($queue)];
    } else {
        $queueResult = ['status' => 'not_in_queue', 'position' => -1, 'queue_count' => count($queue)];
    }
    
    // Ghi đè cập nhật lại file
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($queue));
    fflush($fp);
    flock($fp, LOCK_UN);
}
if ($fp) fclose($fp);

// Xuất data tổng hợp trả về cho Front-end hiển thị lên giao diện
$queueDataResponse = [
    'online_count' => $totalOnline,
    'queue' => $queueResult
];
if (isset($_SESSION['new_csrf_token_issued'])) {
    $queueDataResponse['new_csrf_token'] = $_SESSION['new_csrf_token_issued'];
    unset($_SESSION['new_csrf_token_issued']);
}
echo json_encode($queueDataResponse);