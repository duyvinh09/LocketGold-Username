<?php
/**
 * CTV (cộng tác viên) — lưu JSON, không dùng database.
 * Key = visitor_id cookie; admin kích hoạt hạn + quota ngày.
 */
if (!defined('LOCKET_INTERNAL') && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'ctv_core.php') {
    http_response_code(403);
    exit('Forbidden');
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

define('CTV_DIR', __DIR__ . '/storage/ctv');
define('CTV_ACCOUNTS_FILE', CTV_DIR . '/accounts.json');
define('CTV_DEFAULT_DAILY_LIMIT', 10);

function ensureCtvDir() {
    if (!is_dir(CTV_DIR)) {
        @mkdir(CTV_DIR, 0755, true);
    }
}

function ctvDefaultStore() {
    return ['accounts' => [], 'updated_at' => date('c')];
}

function readCtvStore() {
    ensureCtvDir();

    if (!file_exists(CTV_ACCOUNTS_FILE)) {
        return ctvDefaultStore();
    }

    $raw = @file_get_contents(CTV_ACCOUNTS_FILE);
    $data = json_decode($raw ?: '', true);

    if (!is_array($data) || !isset($data['accounts']) || !is_array($data['accounts'])) {
        return ctvDefaultStore();
    }

    return $data;
}

function writeCtvStore($data) {
    ensureCtvDir();
    $data['updated_at'] = date('c');

    if (empty($data['accounts'])) {
        $data['accounts'] = new stdClass();
    }

    $tmp = CTV_ACCOUNTS_FILE . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $fp = fopen($tmp, 'c+');
    if (!$fp) {
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return @rename($tmp, CTV_ACCOUNTS_FILE);
}

function withCtvStoreLock(callable $fn) {
    ensureCtvDir();
    $fp = fopen(CTV_ACCOUNTS_FILE, 'c+');
    if (!$fp) {
        return ['ok' => false, 'message' => 'Không mở được file CTV.'];
    }

    $result = null;
    if (flock($fp, LOCK_EX)) {
        clearstatcache(true, CTV_ACCOUNTS_FILE);
        $size = filesize(CTV_ACCOUNTS_FILE);
        $data = ctvDefaultStore();

        if ($size > 0) {
            rewind($fp);
            $decoded = json_decode(fread($fp, $size), true);
            if (is_array($decoded) && isset($decoded['accounts']) && is_array($decoded['accounts'])) {
                $data = $decoded;
            }
        }

        $result = $fn($data);

        if (!empty($result['save'])) {
            $data['updated_at'] = date('c');
            $payload = $data;
            if (empty($payload['accounts'])) {
                $payload['accounts'] = new stdClass();
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return $result ?? ['ok' => false, 'message' => 'Không khoá được file CTV.'];
}

/**
 * Chuẩn hoá key CTV: chỉ giữ a-f0-9 (visitor_id 32 hex).
 */
function normalizeCtvKey($key) {
    $key = strtolower(trim((string)$key));
    $key = preg_replace('/^ctv[-_]?/', '', $key);
    $key = preg_replace('/[^a-f0-9]/', '', $key);
    return $key;
}

function formatCtvDisplayKey($visitorId) {
    $id = strtoupper(normalizeCtvKey($visitorId));
    if (strlen($id) !== 32) {
        return $id;
    }
    return 'CTV-' . substr($id, 0, 8) . '-' . substr($id, 8, 8) . '-' . substr($id, 16, 8) . '-' . substr($id, 24, 8);
}

function isCtvExpired($account) {
    $expires = $account['expires_at'] ?? '';
    if ($expires === '' || $expires === null) {
        return true;
    }
    return strtotime($expires . ' 23:59:59') < time();
}

function isCtvAccountActive($account) {
    if (!is_array($account)) {
        return false;
    }
    if (empty($account['enabled'])) {
        return false;
    }
    return !isCtvExpired($account);
}

function getCtvAccountByKey($key) {
    $key = normalizeCtvKey($key);
    if ($key === '' || strlen($key) !== 32) {
        return null;
    }

    $store = readCtvStore();
    $account = $store['accounts'][$key] ?? null;
    if (!is_array($account)) {
        return null;
    }

    $account['key'] = $key;
    $account['display_key'] = formatCtvDisplayKey($key);
    $account['is_active'] = isCtvAccountActive($account);
    $account['is_expired'] = isCtvExpired($account);

    return $account;
}

function getActiveCtvForVisitor($visitorId = null) {
    if ($visitorId === null) {
        if (!function_exists('getVisitorIdForLimit')) {
            return null;
        }
        $visitorId = getVisitorIdForLimit();
    }

    $account = getCtvAccountByKey($visitorId);
    if (!$account || !$account['is_active']) {
        return null;
    }

    return $account;
}

function listCtvAccounts() {
    $store = readCtvStore();
    $list = [];

    foreach ($store['accounts'] as $key => $account) {
        if (!is_array($account)) {
            continue;
        }
        $item = $account;
        $item['key'] = $key;
        $item['display_key'] = formatCtvDisplayKey($key);
        $item['is_active'] = isCtvAccountActive($account);
        $item['is_expired'] = isCtvExpired($account);
        $list[] = $item;
    }

    usort($list, function ($a, $b) {
        return strcmp($b['activated_at'] ?? $b['updated_at'] ?? '', $a['activated_at'] ?? $a['updated_at'] ?? '');
    });

    return $list;
}

/**
 * Admin kích hoạt / cập nhật CTV.
 */
function activateOrUpdateCtv($key, $expiresAt, $dailyLimit, $note = '') {
    $key = normalizeCtvKey($key);
    if (strlen($key) !== 32) {
        return ['ok' => false, 'message' => 'Key CTV không hợp lệ. Key phải là mã 32 ký tự từ trang ctv.php.'];
    }

    $expiresAt = trim((string)$expiresAt);
    if ($expiresAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
        return ['ok' => false, 'message' => 'Ngày hết hạn không hợp lệ (YYYY-MM-DD).'];
    }

    $dailyLimit = (int)$dailyLimit;
    if ($dailyLimit < 1 || $dailyLimit > 999) {
        return ['ok' => false, 'message' => 'Số lượt/ngày phải từ 1 đến 999.'];
    }

    $note = trim(mb_substr((string)$note, 0, 120));

    return withCtvStoreLock(function (&$data) use ($key, $expiresAt, $dailyLimit, $note) {
        $now = date('c');
        $existing = $data['accounts'][$key] ?? null;
        $isNew = !is_array($existing);

        $data['accounts'][$key] = [
            'enabled' => true,
            'daily_limit' => $dailyLimit,
            'expires_at' => $expiresAt,
            'note' => $note,
            'created_at' => $isNew ? $now : ($existing['created_at'] ?? $now),
            'activated_at' => $now,
            'updated_at' => $now,
        ];

        // Nếu là kích hoạt CTV mới, xóa file limit cũ để CTV được hưởng đủ lượt dùng mới
        if ($isNew && defined('LIMIT_DIR')) {
            $limitFile = LIMIT_DIR . '/' . $key . '.json';
            if (file_exists($limitFile)) {
                @unlink($limitFile);
            }
        }

        $saved = $data['accounts'][$key];
        $saved['key'] = $key;
        $saved['display_key'] = formatCtvDisplayKey($key);
        $saved['is_active'] = isCtvAccountActive($saved);
        $saved['is_expired'] = isCtvExpired($saved);

        return [
            'ok' => true,
            'save' => true,
            'message' => $isNew
                ? 'Đã kích hoạt CTV thành công.'
                : 'Đã cập nhật thông tin CTV.',
            'account' => $saved,
        ];
    });
}

function updateCtvFields($key, $fields) {
    $key = normalizeCtvKey($key);
    if (strlen($key) !== 32) {
        return ['ok' => false, 'message' => 'Key CTV không hợp lệ.'];
    }

    return withCtvStoreLock(function (&$data) use ($key, $fields) {
        if (!isset($data['accounts'][$key]) || !is_array($data['accounts'][$key])) {
            return ['ok' => false, 'message' => 'Không tìm thấy CTV này.'];
        }

        $account = $data['accounts'][$key];

        if (array_key_exists('expires_at', $fields)) {
            $expiresAt = trim((string)$fields['expires_at']);
            if ($expiresAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
                return ['ok' => false, 'message' => 'Ngày hết hạn không hợp lệ.'];
            }
            $account['expires_at'] = $expiresAt;
        }

        if (array_key_exists('daily_limit', $fields)) {
            $dailyLimit = (int)$fields['daily_limit'];
            if ($dailyLimit < 1 || $dailyLimit > 999) {
                return ['ok' => false, 'message' => 'Số lượt/ngày phải từ 1 đến 999.'];
            }
            $account['daily_limit'] = $dailyLimit;
        }

        if (array_key_exists('note', $fields)) {
            $account['note'] = trim(mb_substr((string)$fields['note'], 0, 120));
        }

        if (array_key_exists('enabled', $fields)) {
            $account['enabled'] = (bool)$fields['enabled'];
        }

        $account['updated_at'] = date('c');
        $data['accounts'][$key] = $account;

        return [
            'ok' => true,
            'save' => true,
            'message' => 'Đã cập nhật CTV.',
        ];
    });
}

function deleteCtv($key) {
    $key = normalizeCtvKey($key);
    if (strlen($key) !== 32) {
        return ['ok' => false, 'message' => 'Key CTV không hợp lệ.'];
    }

    return withCtvStoreLock(function (&$data) use ($key) {
        if (!isset($data['accounts'][$key])) {
            return ['ok' => false, 'message' => 'Không tìm thấy CTV này.'];
        }

        unset($data['accounts'][$key]);

        return [
            'ok' => true,
            'save' => true,
            'message' => 'Đã xoá quyền CTV.',
        ];
    });
}

function getCtvPublicStatus($visitorId) {
    $key = normalizeCtvKey($visitorId);
    $displayKey = formatCtvDisplayKey($key);
    $account = getCtvAccountByKey($key);

    if (!$account) {
        return [
            'has_key' => strlen($key) === 32,
            'key' => $key,
            'display_key' => $displayKey,
            'status' => 'pending',
            'status_label' => 'Chưa kích hoạt',
            'daily_limit' => null,
            'expires_at' => null,
            'note' => null,
            'is_active' => false,
        ];
    }

    if (!$account['enabled']) {
        $status = 'disabled';
        $label = 'Đã bị tắt quyền';
    } elseif ($account['is_expired']) {
        $status = 'expired';
        $label = 'Đã hết hạn';
    } else {
        $status = 'active';
        $label = 'Đang hoạt động';
    }

    return [
        'has_key' => true,
        'key' => $key,
        'display_key' => $displayKey,
        'status' => $status,
        'status_label' => $label,
        'daily_limit' => (int)($account['daily_limit'] ?? CTV_DEFAULT_DAILY_LIMIT),
        'expires_at' => $account['expires_at'] ?? null,
        'note' => $account['note'] ?? '',
        'is_active' => $account['is_active'],
        'activated_at' => $account['activated_at'] ?? null,
    ];
}
