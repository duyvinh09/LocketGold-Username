<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/config.php';

define('MAINTENANCE_FILE', __DIR__ . '/storage/maintenance.lock');
define('LOCKET_INTERNAL', true);
define('ADMIN_PANEL_SESSION_KEY', 'admin_panel_auth');
define('ADMIN_PANEL_SESSION_TTL', 86400 * 7); // giữ phiên thao tác 7 ngày

require_once __DIR__ . '/ctv_core.php';

$adminPassword = ADMIN_SECRET_KEY;

function ensureStorageDir() {
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function isMaintenanceMode() {
    return file_exists(MAINTENANCE_FILE);
}

function enableMaintenance() {
    ensureStorageDir();
    file_put_contents(MAINTENANCE_FILE, 'maintenance enabled at ' . date('Y-m-d H:i:s'));
}

function disableMaintenance() {
    if (file_exists(MAINTENANCE_FILE)) {
        unlink(MAINTENANCE_FILE);
    }
}

function setAdminCookie() {
    setcookie('locket_admin_token', hash('sha256', ADMIN_SECRET_KEY), [
        'expires'  => time() + 86400 * 365,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['locket_admin_token'] = hash('sha256', ADMIN_SECRET_KEY);
}

function clearAdminCookie() {
    setcookie('locket_admin_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['locket_admin_token']);
}

function isAdminActive() {
    $token = $_COOKIE['locket_admin_token'] ?? '';
    return hash_equals(hash('sha256', ADMIN_SECRET_KEY), $token);
}

function isPanelUnlocked() {
    $auth = $_SESSION[ADMIN_PANEL_SESSION_KEY] ?? null;
    if (!is_array($auth)) {
        return false;
    }
    $at = (int)($auth['at'] ?? 0);
    if ($at <= 0 || (time() - $at) > ADMIN_PANEL_SESSION_TTL) {
        unset($_SESSION[ADMIN_PANEL_SESSION_KEY]);
        return false;
    }
    return !empty($auth['ok']);
}

function unlockPanelSession() {
    $_SESSION[ADMIN_PANEL_SESSION_KEY] = [
        'ok' => true,
        'at' => time(),
    ];
}

function lockPanelSession() {
    unset($_SESSION[ADMIN_PANEL_SESSION_KEY]);
}

function jsonOut($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function adminStatePayload($message = '', $messageType = 'success') {
    $accounts = listCtvAccounts();
    return [
        'success' => true,
        'message' => $message,
        'message_type' => $messageType,
        'unlocked' => isPanelUnlocked(),
        'is_admin_active' => isAdminActive(),
        'is_maintenance' => isMaintenanceMode(),
        'ctv_count' => count($accounts),
        'ctv_accounts' => $accounts,
        'default_ctv_expire' => date('Y-m-d', strtotime('+30 days')),
        'default_ctv_limit' => (int)CTV_DEFAULT_DAILY_LIMIT,
    ];
}

// API AJAX — không reload trang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['ajax'] ?? '') === '1' || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'unlock') {
        $password = (string)($_POST['password'] ?? '');
        if (!hash_equals($adminPassword, $password)) {
            jsonOut([
                'success' => false,
                'message' => 'Sai mật khẩu admin.',
                'message_type' => 'error',
                'unlocked' => false,
            ], 401);
        }
        unlockPanelSession();
        jsonOut(adminStatePayload('Đã mở khóa thao tác cho phiên này. Bạn có thể dùng toàn bộ trang mà không cần nhập lại.', 'success'));
    }

    if ($action === 'lock_panel') {
        lockPanelSession();
        jsonOut(adminStatePayload('Đã khóa lại panel thao tác.', 'warning'));
    }

    if (!isPanelUnlocked()) {
        jsonOut([
            'success' => false,
            'message' => 'Phiên thao tác đã hết hoặc chưa mở khóa. Vui lòng nhập mật khẩu.',
            'message_type' => 'error',
            'unlocked' => false,
        ], 403);
    }

    // Gia hạn phiên khi đang thao tác
    unlockPanelSession();

    switch ($action) {
        case 'admin_on':
            setAdminCookie();
            jsonOut(adminStatePayload('Đã bật quyền admin không giới hạn lượt trên trình duyệt này.', 'success'));

        case 'admin_off':
            clearAdminCookie();
            jsonOut(adminStatePayload('Đã tắt quyền admin trên trình duyệt này.', 'warning'));

        case 'maintenance_on':
            enableMaintenance();
            jsonOut(adminStatePayload('Đã bật chế độ bảo trì. User thường sẽ thấy trang bảo trì.', 'warning'));

        case 'maintenance_off':
            disableMaintenance();
            jsonOut(adminStatePayload('Đã tắt chế độ bảo trì. Trang chính hoạt động lại bình thường.', 'success'));

        case 'ctv_activate':
            $result = activateOrUpdateCtv(
                $_POST['ctv_key'] ?? '',
                $_POST['ctv_expires_at'] ?? '',
                $_POST['ctv_daily_limit'] ?? CTV_DEFAULT_DAILY_LIMIT,
                $_POST['ctv_note'] ?? ''
            );
            jsonOut(adminStatePayload(
                $result['message'] ?? 'Không xử lý được CTV.',
                !empty($result['ok']) ? 'success' : 'error'
            ), !empty($result['ok']) ? 200 : 400);

        case 'ctv_update':
            $result = updateCtvFields($_POST['ctv_key'] ?? '', [
                'expires_at' => $_POST['ctv_expires_at'] ?? '',
                'daily_limit' => $_POST['ctv_daily_limit'] ?? CTV_DEFAULT_DAILY_LIMIT,
                'note' => $_POST['ctv_note'] ?? '',
                'enabled' => true,
            ]);
            jsonOut(adminStatePayload(
                $result['message'] ?? 'Không cập nhật được CTV.',
                !empty($result['ok']) ? 'success' : 'error'
            ), !empty($result['ok']) ? 200 : 400);

        case 'ctv_delete':
            $result = deleteCtv($_POST['ctv_key'] ?? '');
            jsonOut(adminStatePayload(
                $result['message'] ?? 'Không xoá được CTV.',
                !empty($result['ok']) ? 'warning' : 'error'
            ), !empty($result['ok']) ? 200 : 400);

        case 'refresh_state':
            jsonOut(adminStatePayload('', 'success'));

        default:
            jsonOut(['success' => false, 'message' => 'Hành động không hợp lệ.', 'message_type' => 'error'], 400);
    }
}

if (isset($_GET['logout'])) {
    clearAdminCookie();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$isAdminActive = isAdminActive();
$isMaintenanceMode = isMaintenanceMode();
$isUnlocked = isPanelUnlocked();
$ctvAccounts = listCtvAccounts();
$defaultCtvExpire = date('Y-m-d', strtotime('+30 days'));
$defaultCtvLimit = (int)CTV_DEFAULT_DAILY_LIMIT;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Locket Gold · Admin & CTV</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./assets/logoLocket.png"/>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --yellow: #F5C518;
            --yellow-soft: #FFFCF7;
            --yellow-mid: #FFF8EB;
            --yellow-deep: #E8A317;
            --amber: #B45309;
            --card: rgba(255, 255, 255, 0.86);
            --border: rgba(232, 163, 23, 0.24);
            --border-strong: rgba(232, 163, 23, 0.38);
            --text: #1C1917;
            --text-soft: #78716C;
            --green: #16A34A;
            --red: #DC2626;
            --blue: #2563EB;
            --shadow: 0 20px 60px rgba(28, 25, 23, 0.08), 0 4px 16px rgba(232, 163, 23, 0.10);
        }

        @font-face {
            font-family: 'ProximaSoft';
            src: url('./assets/proxima_soft_bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'ProximaSoft', 'Outfit', system-ui, sans-serif;
            color: var(--text);
            background: #F7F4EF;
            overflow-x: hidden;
        }

        .bg-scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
            background: linear-gradient(165deg, #FFFCFA 0%, #FAF7F2 38%, #F3EDE4 100%);
        }

        .bg-scene::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 90% 55% at 8% -5%, rgba(255, 200, 120, 0.30) 0%, transparent 58%),
                radial-gradient(ellipse 70% 50% at 105% 15%, rgba(255, 228, 181, 0.36) 0%, transparent 52%),
                radial-gradient(ellipse 55% 45% at 50% 110%, rgba(253, 230, 138, 0.22) 0%, transparent 55%);
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(50px);
            animation: drift 18s ease-in-out infinite;
        }

        .orb-1 {
            width: min(430px, 90vw);
            height: min(430px, 90vw);
            top: -14%;
            right: -18%;
            background: rgba(255, 183, 77, 0.24);
        }

        .orb-2 {
            width: min(350px, 80vw);
            height: min(350px, 80vw);
            bottom: -12%;
            left: -22%;
            background: rgba(255, 214, 153, 0.22);
            animation-delay: -6s;
        }

        .orb-3 {
            width: 220px;
            height: 220px;
            top: 42%;
            left: 55%;
            background: rgba(255, 248, 235, 0.9);
            filter: blur(14px);
            opacity: 0.75;
            animation-delay: -11s;
        }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-14px, 9px) scale(1.04); }
            66% { transform: translate(12px, -8px) scale(0.98); }
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 18px;
        }

        .admin-card {
            width: 100%;
            max-width: 720px;
            border-radius: 30px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            overflow: hidden;
            animation: cardPop 0.45s ease-out;
        }

        @keyframes cardPop {
            from { opacity: 0; transform: translateY(18px) scale(0.96); }
            to { opacity: 1; transform: none; }
        }

        .admin-hero {
            position: relative;
            padding: 28px 22px 22px;
            text-align: center;
            overflow: hidden;
            background:
                radial-gradient(circle at 100% 0%, rgba(245, 197, 24, 0.22), transparent 36%),
                linear-gradient(135deg, #FFFFFF 0%, #FFFBEB 100%);
            border-bottom: 1px solid rgba(232, 163, 23, 0.16);
        }

        .admin-hero::before {
            content: '';
            position: absolute;
            width: 190px;
            height: 190px;
            top: -100px;
            right: -80px;
            border-radius: 50%;
            background: rgba(245, 197, 24, 0.14);
        }

        .logo-wrap {
            position: relative;
            z-index: 1;
            width: 88px;
            height: 88px;
            margin: 0 auto 16px;
            display: grid;
            place-items: center;
        }

        .logo-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(245, 197, 24, 0.24);
            border-top-color: var(--yellow-deep);
            animation: spin 1.2s linear infinite;
        }

        .logo-icon {
            width: 66px;
            height: 66px;
            border-radius: 22px;
            background: linear-gradient(145deg, #FFE566 0%, var(--yellow) 52%, var(--yellow-deep) 100%);
            display: grid;
            place-items: center;
            color: #7C2D12;
            font-size: 28px;
            box-shadow: 0 12px 34px rgba(232, 163, 23, 0.34);
            animation: heartBeat 1.4s ease-in-out infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes heartBeat {
            0%, 100% { transform: scale(1); }
            45% { transform: scale(1.08); }
        }

        .badge {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.82);
            border: 1px solid rgba(232, 163, 23, 0.22);
            color: var(--amber);
            font-size: 12px;
            font-weight: 900;
        }

        .brand {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-bottom: 10px;
        }

        .brand .locket,
        .brand .gold {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.4rem, 10vw, 3.3rem);
            font-weight: 800;
            line-height: 1;
        }

        .brand .locket {
            letter-spacing: -0.04em;
            color: var(--text);
        }

        .brand .gold {
            letter-spacing: 0.02em;
            background: linear-gradient(120deg, #D97706 0%, #F59E0B 40%, #FBBF24 70%, #E8A317 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 10px rgba(245, 158, 11, 0.22));
        }

        .subtitle {
            position: relative;
            z-index: 1;
            max-width: 360px;
            margin: 0 auto;
            color: var(--text-soft);
            font-size: 13.5px;
            line-height: 1.55;
        }

        .admin-body { padding: 20px; }

        .status-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 20px;
            margin-bottom: 14px;
            border: 1px solid;
        }

        .status-box.active {
            background: #ECFDF5;
            border-color: #BBF7D0;
            color: #166534;
        }

        .status-box.inactive {
            background: #FEF2F2;
            border-color: #FECACA;
            color: #991B1B;
        }

        .status-icon {
            width: 42px;
            height: 42px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            background: rgba(255,255,255,0.78);
            font-size: 17px;
        }

        .status-title {
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .status-desc {
            font-size: 12.5px;
            line-height: 1.4;
            opacity: 0.88;
        }

        .form-box {
            padding: 14px;
            border-radius: 20px;
            background: rgba(255,255,255,0.68);
            border: 1px solid rgba(232, 163, 23, 0.18);
        }

        .form-box.unlocked-ok {
            border-color: #BBF7D0;
            background: #F0FDF4;
        }

        .input-label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 9px;
            color: #92400E;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .input-wrap { position: relative; }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--amber);
            font-size: 14px;
        }

        input[type="password"],
        input[type="text"],
        input[type="date"],
        input[type="number"] {
            width: 100%;
            padding: 14px 44px 14px 40px;
            border-radius: 16px;
            border: 1.5px solid rgba(232, 163, 23, 0.28);
            background: rgba(255,255,255,0.86);
            color: var(--text);
            font-family: inherit;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .ctv-field input,
        .ctv-item-meta input,
        .ctv-item-note input {
            padding: 12px 14px;
        }

        input:focus {
            border-color: var(--yellow-deep);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(250, 204, 21, 0.28);
        }

        .toggle-pass {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            padding: 0;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #A8A29E;
            cursor: pointer;
            font-size: 14px;
            display: grid;
            place-items: center;
            box-shadow: none;
        }

        .toggle-pass:hover {
            background: #FFFBEB;
            color: var(--amber);
            transform: translateY(-50%);
        }

        .unlock-btn,
        .lock-btn {
            width: 100%;
            margin-top: 12px;
            padding: 14px;
            border: none;
            border-radius: 16px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .unlock-btn {
            color: #1F2937;
            background: linear-gradient(135deg, #FFE259 0%, var(--yellow) 50%, var(--yellow-deep) 100%);
            box-shadow: 0 8px 26px rgba(245, 158, 11, 0.30);
        }

        .lock-btn {
            color: #92400E;
            background: #FFFBEB;
            border: 1.5px solid rgba(232, 163, 23, 0.28);
        }

        .unlock-note {
            margin-top: 10px;
            font-size: 12.5px;
            line-height: 1.45;
            color: var(--text-soft);
            text-align: center;
        }

        .unlock-note.ok { color: #166534; font-weight: 800; }
        .unlock-note.error { color: #991B1B; font-weight: 800; }

        .control-panel {
            margin-top: 14px;
            padding: 14px;
            border-radius: 20px;
            background: rgba(255,255,255,0.68);
            border: 1px solid rgba(232, 163, 23, 0.18);
            transition: opacity 0.2s;
        }

        .control-panel.locked,
        .ctv-section.locked {
            opacity: 0.55;
            pointer-events: none;
        }

        .control-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .control-btn {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px;
            border-radius: 15px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 900;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .control-btn:disabled {
            cursor: not-allowed;
            filter: grayscale(0.25);
            opacity: 0.55;
        }

        .admin-on { background: #ECFDF5; color: #166534; border-color: #BBF7D0; }
        .admin-off { background: #FEF2F2; color: #991B1B; border-color: #FECACA; }
        .maintenance-on { background: #FFFBEB; color: #92400E; border-color: rgba(232, 163, 23, 0.28); }
        .maintenance-off { background: #DCFCE7; color: #166534; border-color: #BBF7D0; }

        .home-btn {
            margin-top: 10px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 14px;
            border-radius: 16px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            color: #92400E;
            background: #FFFFFF;
            border: 1.5px solid rgba(232, 163, 23, 0.28);
        }

        .message {
            display: none;
            align-items: flex-start;
            gap: 9px;
            margin-top: 14px;
            padding: 12px;
            border-radius: 16px;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 800;
        }

        .message.show { display: flex; }
        .message.success { background: #ECFDF5; border: 1px solid #BBF7D0; color: #166534; }
        .message.error { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
        .message.warning { background: #FFFBEB; border: 1px solid rgba(232, 163, 23, 0.24); color: #92400E; }

        .tips {
            margin-top: 14px;
            padding: 13px;
            border-radius: 18px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            text-align: left;
        }

        .tips-title {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #334155;
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 7px;
        }

        .tips ul { margin: 0; padding-left: 18px; }
        .tips li { color: #64748B; font-size: 12px; line-height: 1.55; margin: 4px 0; }

        .footer {
            margin-top: 16px;
            text-align: center;
            color: var(--text-soft);
            font-size: 12px;
        }

        .maintenance-chip {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            font-size: 12.5px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .maintenance-chip.on {
            background: #FFFBEB;
            color: #92400E;
            border: 1px solid rgba(232,163,23,0.28);
        }

        .maintenance-chip.off {
            background: #ECFDF5;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        .ctv-section {
            margin-top: 16px;
            padding: 16px;
            border-radius: 22px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(232, 163, 23, 0.18);
            transition: opacity 0.2s;
        }

        .ctv-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .ctv-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 900;
            color: #92400E;
        }

        .ctv-count {
            font-size: 12px;
            font-weight: 800;
            color: #78716C;
            background: #FFF7ED;
            border: 1px solid rgba(232,163,23,0.22);
            padding: 5px 10px;
            border-radius: 999px;
        }

        .ctv-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .ctv-form-grid .full { grid-column: 1 / -1; }

        .ctv-field label {
            display: block;
            font-size: 11px;
            font-weight: 900;
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }

        .ctv-activate-btn {
            width: 100%;
            margin-top: 10px;
            padding: 13px;
            border: none;
            border-radius: 15px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 900;
            color: #1F2937;
            background: linear-gradient(135deg, #FFE259 0%, var(--yellow) 50%, var(--yellow-deep) 100%);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .ctv-activate-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .ctv-list {
            margin-top: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ctv-item {
            padding: 12px;
            border-radius: 16px;
            border: 1px solid #E7E5E4;
            background: #FFFCFA;
        }

        .ctv-item.active { border-color: #BBF7D0; background: #F0FDF4; }
        .ctv-item.expired,
        .ctv-item.disabled { border-color: #FECACA; background: #FEF2F2; }

        .ctv-item-top {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .ctv-item-key {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11.5px;
            font-weight: 700;
            word-break: break-all;
            color: #1C1917;
        }

        .ctv-pill {
            flex-shrink: 0;
            font-size: 11px;
            font-weight: 900;
            padding: 4px 8px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid currentColor;
        }

        .ctv-item-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .ctv-item-meta label {
            display: block;
            font-size: 10px;
            font-weight: 900;
            color: #78716C;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .ctv-item-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .ctv-item-actions button {
            padding: 10px;
            border-radius: 12px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .ctv-item-actions button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .ctv-save { background: #ECFDF5; color: #166534; border-color: #BBF7D0 !important; }
        .ctv-del { background: #FEF2F2; color: #991B1B; border-color: #FECACA !important; }

        .ctv-empty {
            text-align: center;
            color: #78716C;
            font-size: 13px;
            padding: 16px 8px;
        }

        .busy {
            opacity: 0.7;
            pointer-events: none;
        }

        @media (max-width: 560px) {
            .control-grid,
            .ctv-form-grid,
            .ctv-item-meta,
            .ctv-item-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="bg-scene" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <main class="page">
        <section class="admin-card">
            <div class="admin-hero">
                <div class="logo-wrap">
                    <div class="logo-ring"></div>
                    <div class="logo-icon">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                </div>

                <div class="badge">
                    <i class="fa-solid fa-user-shield"></i>
                    Admin Limit Unlock
                </div>

                <div class="brand">
                    <span class="locket">Locket</span>
                    <span class="gold">Gold</span>
                </div>

                <p class="subtitle">
                    Nhập mật khẩu một lần để thao tác toàn trang. Không cần reload hay nhập lại sau mỗi hành động.
                </p>
            </div>

            <div class="admin-body">
                <div class="status-box <?= $isAdminActive ? 'active' : 'inactive' ?>" id="adminStatusBox">
                    <div class="status-icon">
                        <i class="fa-solid <?= $isAdminActive ? 'fa-circle-check' : 'fa-circle-xmark' ?>" id="adminStatusIcon"></i>
                    </div>
                    <div>
                        <div class="status-title" id="adminStatusTitle">
                            <?= $isAdminActive ? 'Admin đang bật' : 'Admin chưa bật' ?>
                        </div>
                        <div class="status-desc" id="adminStatusDesc">
                            <?= $isAdminActive ? 'Trình duyệt này đang được bỏ qua giới hạn lượt.' : 'Bật quyền admin để không giới hạn lượt kích Gold.' ?>
                        </div>
                    </div>
                </div>

                <div class="maintenance-chip <?= $isMaintenanceMode ? 'on' : 'off' ?>" id="maintenanceChip">
                    <i class="fa-solid <?= $isMaintenanceMode ? 'fa-screwdriver-wrench' : 'fa-circle-check' ?>" id="maintenanceChipIcon"></i>
                    <span id="maintenanceChipText">
                        <?= $isMaintenanceMode ? 'Đang bảo trì' : 'Không bảo trì' ?>
                    </span>
                </div>

                <div class="form-box <?= $isUnlocked ? 'unlocked-ok' : '' ?>" id="authBox">
                    <div class="input-label">
                        <i class="fa-solid fa-key"></i>
                        <?= $isUnlocked ? 'Phiên thao tác đang mở' : 'Nhập mật khẩu để mở khóa thao tác' ?>
                    </div>

                    <div id="passwordArea" style="<?= $isUnlocked ? 'display:none' : '' ?>">
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="adminPassword" placeholder="Nhập mật khẩu admin" autocomplete="current-password">
                            <button type="button" class="toggle-pass" onclick="togglePassword()" aria-label="Hiện/ẩn mật khẩu">
                                <i class="fa-solid fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>

                        <button type="button" class="unlock-btn" id="unlockBtn" onclick="unlockAdminActions()">
                            <i class="fa-solid fa-unlock-keyhole"></i>
                            Mở khóa thao tác
                        </button>
                    </div>

                    <button type="button" class="lock-btn" id="lockBtn" onclick="lockPanel()" style="<?= $isUnlocked ? '' : 'display:none' ?>">
                        <i class="fa-solid fa-lock"></i>
                        Khoá lại panel
                    </button>

                    <div class="unlock-note <?= $isUnlocked ? 'ok' : '' ?>" id="unlockNote">
                        <?= $isUnlocked
                            ? 'Đã mở khóa. Bạn có thể thao tác toàn trang mà không cần nhập lại mật khẩu.'
                            : 'Các nút bên dưới sẽ bị khóa cho đến khi bạn nhập đúng mật khẩu.' ?>
                    </div>
                </div>

                <div class="control-panel <?= $isUnlocked ? '' : 'locked' ?>" id="controlPanel">
                    <div class="control-grid">
                        <button type="button" class="control-btn admin-on" data-action="admin_on" <?= $isUnlocked ? '' : 'disabled' ?>>
                            <i class="fa-solid fa-user-shield"></i>
                            Bật quyền admin
                        </button>
                        <button type="button" class="control-btn admin-off" data-action="admin_off" <?= $isUnlocked ? '' : 'disabled' ?>>
                            <i class="fa-solid fa-power-off"></i>
                            Tắt quyền admin
                        </button>
                        <button type="button" class="control-btn maintenance-on" data-action="maintenance_on" <?= $isUnlocked ? '' : 'disabled' ?>>
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                            Bật bảo trì
                        </button>
                        <button type="button" class="control-btn maintenance-off" data-action="maintenance_off" <?= $isUnlocked ? '' : 'disabled' ?>>
                            <i class="fa-solid fa-circle-check"></i>
                            Tắt bảo trì
                        </button>
                    </div>

                    <a class="home-btn" href="index.php">
                        <i class="fa-solid fa-house"></i>
                        Về trang chính
                    </a>
                </div>

                <div class="ctv-section <?= $isUnlocked ? '' : 'locked' ?>" id="ctvSection">
                    <div class="ctv-head">
                        <div class="ctv-title">
                            <i class="fa-solid fa-handshake"></i>
                            Quản lý CTV
                        </div>
                        <div class="ctv-count" id="ctvCount"><?= count($ctvAccounts) ?> tài khoản</div>
                    </div>

                    <form id="ctvActivateForm" onsubmit="return submitCtvActivate(event)">
                        <div class="ctv-form-grid">
                            <div class="ctv-field full">
                                <label>Key CTV (copy từ ctv.php)</label>
                                <input type="text" name="ctv_key" id="ctvKeyInput" placeholder="CTV-XXXXXXXX-XXXXXXXX-..." required>
                            </div>
                            <div class="ctv-field">
                                <label>Hết hạn</label>
                                <input type="date" name="ctv_expires_at" id="ctvExpireInput" value="<?= htmlspecialchars($defaultCtvExpire) ?>" required>
                            </div>
                            <div class="ctv-field">
                                <label>Lượt / ngày</label>
                                <input type="number" name="ctv_daily_limit" id="ctvLimitInput" min="1" max="999" value="<?= $defaultCtvLimit ?>" required>
                            </div>
                            <div class="ctv-field full">
                                <label>Ghi chú (tuỳ chọn)</label>
                                <input type="text" name="ctv_note" id="ctvNoteInput" placeholder="Tên CTV / Zalo / ghi chú...">
                            </div>
                        </div>

                        <button type="submit" class="ctv-activate-btn" id="ctvActivateBtn" <?= $isUnlocked ? '' : 'disabled' ?>>
                            <i class="fa-solid fa-bolt"></i>
                            Kích hoạt / cập nhật CTV
                        </button>
                    </form>

                    <div class="ctv-list" id="ctvList"></div>
                </div>

                <div class="message" id="flashMessage"></div>

                <div class="tips">
                    <div class="tips-title">
                        <i class="fa-solid fa-shield-halved"></i>
                        Lưu ý bảo mật
                    </div>
                    <ul>
                        <li>Nhập mật khẩu 1 lần — phiên thao tác được giữ trên server, không reload trang.</li>
                        <li>CTV lấy key tại <code>ctv.php</code>, gửi key cho bạn để kích hoạt.</li>
                        <li>Không chia sẻ link trang admin. Nên đổi mật khẩu định kỳ.</li>
                    </ul>
                </div>

                <div class="footer">
                    © 2026 Locket Gold · duyvinh09
                </div>
            </div>
        </section>
    </main>

    <script>
        const INITIAL_STATE = <?= json_encode([
            'unlocked' => $isUnlocked,
            'is_admin_active' => $isAdminActive,
            'is_maintenance' => $isMaintenanceMode,
            'ctv_accounts' => $ctvAccounts,
            'ctv_count' => count($ctvAccounts),
            'default_ctv_expire' => $defaultCtvExpire,
            'default_ctv_limit' => $defaultCtvLimit,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        let unlocked = Boolean(INITIAL_STATE.unlocked);
        let busy = false;

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function togglePassword() {
            const input = document.getElementById('adminPassword');
            const icon = document.getElementById('toggleIcon');
            if (!input || !icon) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        }

        function showMessage(text, type) {
            const el = document.getElementById('flashMessage');
            if (!el) return;
            if (!text) {
                el.className = 'message';
                el.innerHTML = '';
                return;
            }
            const icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info');
            el.className = 'message show ' + (type || 'success');
            el.innerHTML = '<i class="fa-solid ' + icon + '"></i><span>' + escapeHtml(text) + '</span>';
        }

        function setUnlockedUI(isOn, noteText) {
            unlocked = Boolean(isOn);
            const panel = document.getElementById('controlPanel');
            const ctv = document.getElementById('ctvSection');
            const authBox = document.getElementById('authBox');
            const passwordArea = document.getElementById('passwordArea');
            const lockBtn = document.getElementById('lockBtn');
            const note = document.getElementById('unlockNote');
            const label = authBox?.querySelector('.input-label');

            panel?.classList.toggle('locked', !unlocked);
            ctv?.classList.toggle('locked', !unlocked);
            authBox?.classList.toggle('unlocked-ok', unlocked);

            if (passwordArea) passwordArea.style.display = unlocked ? 'none' : '';
            if (lockBtn) lockBtn.style.display = unlocked ? '' : 'none';

            document.querySelectorAll('.control-btn, .ctv-activate-btn').forEach(function (btn) {
                btn.disabled = !unlocked;
            });

            if (label) {
                label.innerHTML = unlocked
                    ? '<i class="fa-solid fa-key"></i> Phiên thao tác đang mở'
                    : '<i class="fa-solid fa-key"></i> Nhập mật khẩu để mở khóa thao tác';
            }

            if (note) {
                note.className = 'unlock-note ' + (unlocked ? 'ok' : '');
                note.innerHTML = noteText || (unlocked
                    ? 'Đã mở khóa. Bạn có thể thao tác toàn trang mà không cần nhập lại mật khẩu.'
                    : 'Các nút bên dưới sẽ bị khóa cho đến khi bạn nhập đúng mật khẩu.');
            }
        }

        function renderAdminStatus(state) {
            const box = document.getElementById('adminStatusBox');
            const icon = document.getElementById('adminStatusIcon');
            const title = document.getElementById('adminStatusTitle');
            const desc = document.getElementById('adminStatusDesc');
            const active = Boolean(state.is_admin_active);

            box.className = 'status-box ' + (active ? 'active' : 'inactive');
            icon.className = 'fa-solid ' + (active ? 'fa-circle-check' : 'fa-circle-xmark');
            title.textContent = active ? 'Admin đang bật' : 'Admin chưa bật';
            desc.textContent = active
                ? 'Trình duyệt này đang được bỏ qua giới hạn lượt.'
                : 'Bật quyền admin để không giới hạn lượt kích Gold.';
        }

        function renderMaintenance(state) {
            const chip = document.getElementById('maintenanceChip');
            const icon = document.getElementById('maintenanceChipIcon');
            const text = document.getElementById('maintenanceChipText');
            const on = Boolean(state.is_maintenance);

            chip.className = 'maintenance-chip ' + (on ? 'on' : 'off');
            icon.className = 'fa-solid ' + (on ? 'fa-screwdriver-wrench' : 'fa-circle-check');
            text.textContent = on ? 'Đang bảo trì' : 'Không bảo trì';
        }

        function renderCtvList(accounts) {
            const list = document.getElementById('ctvList');
            const countEl = document.getElementById('ctvCount');
            const items = Array.isArray(accounts) ? accounts : [];

            if (countEl) countEl.textContent = items.length + ' tài khoản';

            if (!items.length) {
                list.innerHTML = '<div class="ctv-empty">Chưa có CTV nào. CTV vào <strong>ctv.php</strong> lấy key rồi gửi cho bạn.</div>';
                return;
            }

            list.innerHTML = items.map(function (ctv) {
                let itemClass = 'disabled';
                let pill = 'Tắt';
                if (ctv.is_active) {
                    itemClass = 'active';
                    pill = 'Active';
                } else if (ctv.is_expired) {
                    itemClass = 'expired';
                    pill = 'Hết hạn';
                }

                return `
                    <div class="ctv-item ${itemClass}" data-key="${escapeHtml(ctv.key)}">
                        <div class="ctv-item-top">
                            <div class="ctv-item-key">${escapeHtml(ctv.display_key || ctv.key)}</div>
                            <div class="ctv-pill">${pill}</div>
                        </div>
                        <div class="ctv-item-meta">
                            <div>
                                <label>Hết hạn</label>
                                <input type="date" class="js-ctv-expire" value="${escapeHtml(ctv.expires_at || '')}">
                            </div>
                            <div>
                                <label>Lượt / ngày</label>
                                <input type="number" class="js-ctv-limit" min="1" max="999" value="${Number(ctv.daily_limit || 10)}">
                            </div>
                        </div>
                        <div class="ctv-item-note">
                            <input type="text" class="js-ctv-note" value="${escapeHtml(ctv.note || '')}" placeholder="Ghi chú">
                        </div>
                        <div class="ctv-item-actions">
                            <button type="button" class="ctv-save" ${unlocked ? '' : 'disabled'} onclick="updateCtv(this)">
                                <i class="fa-solid fa-floppy-disk"></i> Lưu
                            </button>
                            <button type="button" class="ctv-del" ${unlocked ? '' : 'disabled'} onclick="deleteCtv(this)">
                                <i class="fa-solid fa-trash"></i> Xoá
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function applyState(state, opts) {
            opts = opts || {};
            if (typeof state.unlocked !== 'undefined') {
                setUnlockedUI(state.unlocked, opts.note);
            }
            renderAdminStatus(state);
            renderMaintenance(state);
            renderCtvList(state.ctv_accounts || []);

            if (state.default_ctv_expire) {
                const expireInput = document.getElementById('ctvExpireInput');
                if (expireInput && !expireInput.value) expireInput.value = state.default_ctv_expire;
            }

            if (state.message && opts.showMessage !== false) {
                showMessage(state.message, state.message_type || 'success');
            }
        }

        async function api(action, extra) {
            if (busy) return null;
            busy = true;
            document.body.classList.add('busy');

            try {
                const form = new FormData();
                form.append('ajax', '1');
                form.append('action', action);
                Object.entries(extra || {}).forEach(function ([k, v]) {
                    form.append(k, v == null ? '' : v);
                });

                const res = await fetch(location.pathname + location.search, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (data && typeof data.unlocked !== 'undefined' && !data.unlocked && action !== 'unlock' && action !== 'lock_panel') {
                    setUnlockedUI(false, 'Phiên đã hết. Nhập lại mật khẩu để tiếp tục.');
                }
                return data;
            } catch (err) {
                showMessage('Không kết nối được server. Thử lại nhé.', 'error');
                return null;
            } finally {
                busy = false;
                document.body.classList.remove('busy');
            }
        }

        async function unlockAdminActions() {
            const input = document.getElementById('adminPassword');
            const note = document.getElementById('unlockNote');
            if (!input) return;

            if (!input.value.trim()) {
                note.className = 'unlock-note error';
                note.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Vui lòng nhập mật khẩu.';
                return;
            }

            const data = await api('unlock', { password: input.value });
            if (!data) return;

            if (!data.success) {
                note.className = 'unlock-note error';
                note.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + escapeHtml(data.message || 'Sai mật khẩu admin.');
                showMessage(data.message || 'Sai mật khẩu admin.', 'error');
                return;
            }

            input.value = '';
            applyState(data, { note: data.message });
        }

        async function lockPanel() {
            const data = await api('lock_panel');
            if (!data) return;
            applyState(data, { note: data.message || 'Đã khóa lại panel thao tác.' });
        }

        async function runAction(action) {
            if (!unlocked) {
                showMessage('Hãy mở khóa bằng mật khẩu trước.', 'error');
                return;
            }
            const data = await api(action);
            if (!data) return;
            applyState(data);
        }

        async function submitCtvActivate(e) {
            e.preventDefault();
            if (!unlocked) {
                showMessage('Hãy mở khóa bằng mật khẩu trước.', 'error');
                return false;
            }

            const data = await api('ctv_activate', {
                ctv_key: document.getElementById('ctvKeyInput').value,
                ctv_expires_at: document.getElementById('ctvExpireInput').value,
                ctv_daily_limit: document.getElementById('ctvLimitInput').value,
                ctv_note: document.getElementById('ctvNoteInput').value
            });

            if (!data) return false;

            applyState(data);
            if (data.success && data.message_type !== 'error') {
                document.getElementById('ctvKeyInput').value = '';
                document.getElementById('ctvNoteInput').value = '';
                if (data.default_ctv_expire) {
                    document.getElementById('ctvExpireInput').value = data.default_ctv_expire;
                }
                if (data.default_ctv_limit) {
                    document.getElementById('ctvLimitInput').value = data.default_ctv_limit;
                }
            }
            return false;
        }

        async function updateCtv(btn) {
            const item = btn.closest('.ctv-item');
            if (!item) return;

            const data = await api('ctv_update', {
                ctv_key: item.dataset.key,
                ctv_expires_at: item.querySelector('.js-ctv-expire').value,
                ctv_daily_limit: item.querySelector('.js-ctv-limit').value,
                ctv_note: item.querySelector('.js-ctv-note').value
            });
            if (!data) return;
            applyState(data);
        }

        async function deleteCtv(btn) {
            if (!confirm('Xoá quyền CTV này?')) return;
            const item = btn.closest('.ctv-item');
            if (!item) return;

            const data = await api('ctv_delete', { ctv_key: item.dataset.key });
            if (!data) return;
            applyState(data);
        }

        document.querySelectorAll('.control-btn[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                runAction(btn.dataset.action);
            });
        });

        document.getElementById('adminPassword')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                unlockAdminActions();
            }
        });

        applyState(INITIAL_STATE, { showMessage: false });
    </script>
</body>
</html>
