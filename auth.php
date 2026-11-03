<?php
/**
 * Bảo mật API: CSRF, rate limit, kiểm tra origin/referer.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

class LocketAuth
{
    private const RATE_LIMIT_WINDOW = 60;
    private const RATE_LIMIT_MAX = 120;
    private const CSRF_KEY = 'locket_csrf';
    private const RATE_KEY = 'locket_rate';

    public static function getApiSecret(): string
    {
        if (defined('API_SECRET') && API_SECRET !== '') {
            return API_SECRET;
        }
        return '!LOCKETgold';
    }

    public static function issueCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_KEY] = $token;
        $_SESSION[self::CSRF_KEY . '_time'] = time();
        return $token;
    }

    public static function getCsrfToken(): ?string
    {
        return $_SESSION[self::CSRF_KEY] ?? null;
    }

    public static function validateCsrf(?string $token): bool
    {
        $stored = $_SESSION[self::CSRF_KEY] ?? '';
        $issuedAt = $_SESSION[self::CSRF_KEY . '_time'] ?? 0;

        if ($stored === '' || $token === null || $token === '') {
            return false;
        }
        if (time() - $issuedAt > 7200) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public static function checkRateLimit(): bool
    {
        $ip = self::clientIp();
        $bucket = $_SESSION[self::RATE_KEY][$ip] ?? ['count' => 0, 'start' => time()];

        if (time() - $bucket['start'] > self::RATE_LIMIT_WINDOW) {
            $bucket = ['count' => 0, 'start' => time()];
        }

        $bucket['count']++;
        $_SESSION[self::RATE_KEY][$ip] = $bucket;

        return $bucket['count'] <= self::RATE_LIMIT_MAX;
    }

    public static function clientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function isAllowedOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($origin !== '') {
            $parsed = parse_url($origin);
            if (($parsed['host'] ?? '') === $host) {
                return true;
            }
        }

        if ($referer !== '') {
            $parsed = parse_url($referer);
            if (($parsed['host'] ?? '') === $host) {
                return true;
            }
        }

        return empty($origin) && empty($referer);
    }

    public static function requireApiAccess(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if (!self::isAllowedOrigin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nguồn truy cập không hợp lệ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!self::checkRateLimit()) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ajax = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($ajax) !== 'xmlhttprequest') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!self::validateCsrf($csrf)) {
            $action = $_POST['action'] ?? '';
            // Cho phép các tác vụ chỉ đọc dữ liệu công khai (queue_sync, limit_status) tự động cấp lại CSRF token mới mà không chặn request
            if (in_array($action, ['queue_sync', 'limit_status'], true)) {
                $_SESSION['new_csrf_token_issued'] = self::issueCsrfToken();
                return;
            }

            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Phiên làm việc hết hạn. Tải lại trang.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public static function sanitizePublicUser(array $data): array
    {
        $allowed = [
            'uid', 'id', 'user_id', 'username', 'user_name',
            'first_name', 'firstName', 'given_name',
            'last_name', 'lastName', 'family_name',
            'full_name', 'fullName', 'name',
            'profile_picture_url', 'avatar', 'profile_pic',
            'profilePicture', 'photo_url', 'picture',
            'badge', 'is_gold', 'friend_status', 'is_friend',
            'created_at', 'bio', 'phone', 'email',
        ];

        $out = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            } elseif (is_array($value) && count($value) < 20) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function sanitizeGoldResponse(array $data): array
    {
        unset($data['raw_data'], $data['entitlement_data'], $data['activation_data']);
        if (isset($data['gold_status'])) {
            unset($data['gold_status']['raw_data'], $data['gold_status']['entitlement_data']);
        }
        return $data;
    }
}