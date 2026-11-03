<?php
if (!defined('LOCKET_INTERNAL')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/config.php';

class LocketAPI {
    protected $idToken;
    protected $refreshToken;
    protected $currentAccount;
    protected $lastRequestTime = 0;
    protected $minRequestInterval = 1000000; // 1 giây (microseconds)

    public function __construct() {
        // Không auto login ở constructor để linh hoạt hơn
    }

    /**
     * Đường dẫn file lưu cache token
     */
    protected function getTokenCacheFilePath() {
        $dir = __DIR__ . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/tokens_cache.json';
    }

    /**
     * Đọc token cache từ file với khoá đọc (LOCK_SH)
     */
    protected function readTokenCache() {
        $file = $this->getTokenCacheFilePath();
        if (!file_exists($file)) {
            return [];
        }
        $fp = @fopen($file, 'r');
        if (!$fp) {
            return [];
        }
        if (@flock($fp, LOCK_SH)) {
            $content = @stream_get_contents($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        @fclose($fp);
        return [];
    }

    /**
     * Ghi token cache vào file với khoá ghi (LOCK_EX)
     */
    protected function saveTokenCache($cache) {
        $file = $this->getTokenCacheFilePath();
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return false;
        }
        if (@flock($fp, LOCK_EX)) {
            @ftruncate($fp, 0);
            @rewind($fp);
            @fwrite($fp, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @fflush($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return true;
        }
        @fclose($fp);
        return false;
    }

    /**
     * Xoá token bị lỗi khỏi cache
     */
    protected function invalidateToken($email) {
        if (empty($email)) return;
        $key = md5($email);
        $cache = $this->readTokenCache();
        if (isset($cache[$key])) {
            unset($cache[$key]);
            $this->saveTokenCache($cache);
        }
    }

    /**
     * Đọc danh sách accounts từ file
     */
    protected function getAllAccounts() {
        if (!file_exists(LOCKET_ACCOUNTS_FILE)) {
            return [];
        }

        $lines = file(LOCKET_ACCOUNTS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $accounts = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $email = trim($parts[0]);
            $password = trim($parts[1]);

            if ($email !== '' && $password !== '') {
                $accounts[] = [
                    'email' => $email,
                    'password' => $password
                ];
            }
        }

        return $accounts;
    }

    /**
     * Đăng nhập trực tiếp tới Firebase Auth
     */
    protected function loginDirectly($account) {
        if (empty($account['email']) || empty($account['password'])) {
            return null;
        }

        $loginUrl = "https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword";

        $postData = [
            'email' => $account['email'],
            'password' => $account['password'],
            'returnSecureToken' => true,
            'clientType' => 'CLIENT_TYPE_IOS'
        ];

        $url = $loginUrl . '?key=' . urlencode(FIREBASE_API_KEY);

        $headers = array_merge(DEFAULT_HEADERS, [
            'X-Firebase-AppCheck: ' . FIREBASE_APPCHECK,
            'X-Firebase-GMPID: ' . FIREBASE_GMPID,
            'sentry-trace: ' . SENTRY_TRACE,
            'X-Ios-Bundle-Identifier: com.locket.Locket'
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (!empty($data['idToken'])) {
                return [
                    'idToken' => $data['idToken'],
                    'refreshToken' => $data['refreshToken'] ?? null,
                    'expires_at' => time() + 3300 // Firebase token hết hạn sau 3600s, cache 3300s (55 min)
                ];
            }
        }

        return null;
    }

    /**
     * Tải hoặc làm mới Token với cơ chế Token Pool & Cache
     */
    protected function refreshToken($excludeEmails = []) {
        $accounts = $this->getAllAccounts();
        if (empty($accounts)) {
            return false;
        }

        $cache = $this->readTokenCache();
        $now = time();

        // 1. Tìm các account có token hợp lệ trong cache và chưa bị loại trừ
        $validAccounts = [];
        foreach ($accounts as $acc) {
            if (in_array($acc['email'], $excludeEmails, true)) {
                continue;
            }
            $key = md5($acc['email']);
            if (isset($cache[$key]) && isset($cache[$key]['idToken']) && isset($cache[$key]['expires_at'])) {
                if (($cache[$key]['expires_at'] - $now) > 300) { // còn hạn trên 5 phút
                    $validAccounts[] = [
                        'account' => $acc,
                        'token' => $cache[$key]['idToken'],
                        'refreshToken' => $cache[$key]['refreshToken'] ?? null
                    ];
                }
            }
        }

        // Nếu có token sẵn trong cache, xoay vòng ngẫu nhiên chọn 1 token hợp lệ
        if (!empty($validAccounts)) {
            $selected = $validAccounts[array_rand($validAccounts)];
            $this->idToken = $selected['token'];
            $this->refreshToken = $selected['refreshToken'];
            $this->currentAccount = $selected['account'];
            return true;
        }

        // 2. Nếu chưa có token hợp lệ trong cache, xáo trộn danh sách account để login
        shuffle($accounts);
        foreach ($accounts as $acc) {
            if (in_array($acc['email'], $excludeEmails, true)) {
                continue;
            }

            $loginData = $this->loginDirectly($acc);
            if ($loginData) {
                $key = md5($acc['email']);
                $cache[$key] = [
                    'email' => $acc['email'],
                    'idToken' => $loginData['idToken'],
                    'refreshToken' => $loginData['refreshToken'],
                    'expires_at' => $loginData['expires_at'],
                    'updated_at' => date('c')
                ];
                $this->saveTokenCache($cache);

                $this->idToken = $loginData['idToken'];
                $this->refreshToken = $loginData['refreshToken'];
                $this->currentAccount = $acc;
                return true;
            }
        }

        return false;
    }

    /**
     * Đảm bảo khoảng cách thời gian giữa các request (trong 1 process)
     */
    protected function enforceRateLimit() {
        $now = microtime(true) * 1000000; // microseconds
        $timeSinceLastRequest = $now - $this->lastRequestTime;
        
        if ($timeSinceLastRequest < $this->minRequestInterval) {
            usleep($this->minRequestInterval - $timeSinceLastRequest);
        }
        
        $this->lastRequestTime = microtime(true) * 1000000;
    }

    /**
     * Thực hiện request đến Locket API
     */
    protected function makeRequest($username) {
        $this->enforceRateLimit();

        if (empty($this->idToken)) {
            if (!$this->refreshToken()) {
                return [
                    'success' => false, 
                    'message' => 'Không thể đăng nhập',
                    'httpCode' => 401
                ];
            }
        }

        $url = 'https://api.locketcamera.com/getUserByUsername';

        $headers = array_merge(DEFAULT_HEADERS, [
            'Authorization: Bearer ' . $this->idToken,
            'X-Firebase-AppCheck: ' . FIREBASE_APPCHECK,
            'X-Firebase-GMPID: ' . FIREBASE_GMPID,
            'sentry-trace: ' . SENTRY_TRACE,
            'X-Ios-Bundle-Identifier: com.locket.Locket'
        ]);

        $postData = ['data' => ['username' => $username]];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['data']) && !empty($data['data'])) {
                return ['success' => true, 'data' => $data['data']];
            }
            if (isset($data['result']) && !empty($data['result'])) {
                return ['success' => true, 'data' => $data['result']];
            }
        }

        return [
            'success' => false, 
            'message' => 'Request failed (HTTP ' . $httpCode . ')',
            'httpCode' => $httpCode
        ];
    }

    /**
     * Public method: Lấy user theo username với cơ chế retry thông minh
     */
    public function getUserByUsername($username, $maxRetries = 3) {
        if (empty($username)) {
            return ['success' => false, 'message' => 'Vui lòng cung cấp username'];
        }

        $triedEmails = [];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            if (empty($this->idToken) || $attempt > 1) {
                if (!$this->refreshToken($triedEmails)) {
                    return ['success' => false, 'message' => 'Không thể đăng nhập/lấy token'];
                }
            }

            if (!empty($this->currentAccount['email'])) {
                $triedEmails[] = $this->currentAccount['email'];
            }

            $result = $this->makeRequest($username);

            // Nếu thành công, return ngay
            if ($result['success']) {
                return $result;
            }

            // Nếu bị ngắt kết nối/token lỗi 401/403 -> invalidate token hiện tại để attempt sau lấy token mới
            if (in_array($result['httpCode'] ?? 0, [401, 403])) {
                if (!empty($this->currentAccount['email'])) {
                    $this->invalidateToken($this->currentAccount['email']);
                }
                $this->idToken = null;
            }

            // Nếu không phải lỗi token/rate-limit hoặc đã thử hết số lần
            if (!in_array($result['httpCode'] ?? 0, [401, 403, 429]) || $attempt == $maxRetries) {
                return $result;
            }

            // Tạm hoãn nhẹ trước khi thử lại
            usleep(500000); // 0.5 giây
        }

        return ['success' => false, 'message' => 'Hệ thống bận, vui lòng thử lại sau'];
    }

    /**
     * Public method: Kiểm tra xem input có phải là link copy / invite link hay không
     */
    public function isInviteLink($input) {
        $input = trim($input);
        return (bool)preg_match('~([a-zA-Z0-9-]+\.)?locket\.(camera|cam|me|com)/(links|invites)/[a-zA-Z0-9_\-]+~i', $input);
    }

    /**
     * Public method: Trích xuất thông tin UID và ảnh từ link copy / invite link (locket.camera/links/...)
     */
    /**
     * Public method: Trích xuất thông tin UID và ảnh từ link copy / invite link (locket.camera/links/...)
     */
    public function getUserByInviteLink($input) {
        $input = trim($input);
        if (empty($input)) {
            return ['success' => false, 'message' => 'Vui lòng nhập link invite / copy link'];
        }

        if (!preg_match('~^https?://~i', $input)) {
            $url = 'https://' . $input;
        } else {
            $url = $input;
        }

        $effectiveUrl = $url;
        $body = '';

        // 1. Thực hiện cURL để lấy link chuyển tiếp (/invites/...) hoặc HTML body
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        curl_close($ch);

        // 2. Trích xuất raw ID từ effectiveUrl, url hoặc body HTML
        $rawId = '';
        $inviteUrl = '';

        if (preg_match('/\/invites\/([a-zA-Z0-9_\-]+)/i', $effectiveUrl, $m)) {
            $rawId = $m[1];
            $inviteUrl = $effectiveUrl;
        } elseif (preg_match('/\/invites\/([a-zA-Z0-9_\-]+)/i', $url, $m)) {
            $rawId = $m[1];
            $inviteUrl = $url;
        }

        if (empty($rawId) && !empty($body)) {
            if (preg_match('/data-deep-link-url=["\']([^"\']*\/invites\/([a-zA-Z0-9_\-]+)[^"\']*)["\']/i', $body, $m)) {
                $inviteUrl = html_entity_decode($m[1]);
                $rawId = $m[2];
            } elseif (preg_match('/deep_link_id=(https%3A%2F%2Flocket\.camera%2Finvites%2F([a-zA-Z0-9_\-]+)[^&"\'\s]*)/i', $body, $m)) {
                $inviteUrl = urldecode($m[1]);
                $rawId = $m[2];
            } elseif (preg_match('/\/invites\/([a-zA-Z0-9_\-]+)/i', $body, $m)) {
                $rawId = $m[1];
                $inviteUrl = 'https://locket.camera/invites/' . $rawId;
            }
        }

        if (empty($rawId)) {
            return ['success' => false, 'message' => 'Không thể theo dõi đường dẫn link copy Locket này'];
        }

        // Lấy 28 ký tự đầu tiên làm Firebase UID của người dùng Locket
        $uid = substr($rawId, 0, 28);
        if (strlen($uid) < 28) {
            return ['success' => false, 'message' => 'UID trích xuất từ link không hợp lệ (ngắn hơn 28 ký tự)'];
        }

        // 3. Nếu chưa crawl trực tiếp trang invite html, thực hiện crawl trang $inviteUrl để lấy profile_pic.webp chính thức
        $inviteHtml = $body;
        if (strpos($effectiveUrl, '/invites/') === false && !empty($inviteUrl)) {
            $ch2 = curl_init($inviteUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ]);
            $inviteHtml = curl_exec($ch2);
            curl_close($ch2);
        }

        // 4. Tìm ảnh đại diện chính thức (profile_pic.webp) từ HTML trang invite chứa token hợp lệ
        $profilePic = '';
        if (!empty($inviteHtml)) {
            if (preg_match('/(https?:\/\/[^"\'>\s]*profile_pic[^"\'>\s]*)/i', $inviteHtml, $ogMatches)) {
                $profilePic = html_entity_decode($ogMatches[1]);
                if (preg_match('/^https?%3A%2F%2F/i', $profilePic)) {
                    $profilePic = rawurldecode($profilePic);
                }
                $profilePic = str_replace('%25', '%', $profilePic);
            }
        }

        // 5. Fallback: Bóc tách social_img từ URL nếu trong HTML chưa thấy profile_pic
        if (empty($profilePic)) {
            $targetQueryUrl = !empty($inviteUrl) ? $inviteUrl : $effectiveUrl;
            $queryStr = parse_url($targetQueryUrl, PHP_URL_QUERY);
            if ($queryStr) {
                parse_str($queryStr, $params);
                if (!empty($params['social_img'])) {
                    $profilePic = $params['social_img'];
                }
            }

            if (!empty($profilePic)) {
                if (preg_match('/^https?%3A%2F%2F/i', $profilePic)) {
                    $profilePic = rawurldecode($profilePic);
                }
                $profilePic = str_replace('%25', '%', $profilePic);
            }
        }

        // 6. Fallback thứ 2: Kiểm tra tag meta og:image nếu vẫn chưa có
        if (empty($profilePic) && !empty($inviteHtml)) {
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $inviteHtml, $ogMatches)) {
                $profilePic = html_entity_decode($ogMatches[1]);
                if (preg_match('/^https?%3A%2F%2F/i', $profilePic)) {
                    $profilePic = rawurldecode($profilePic);
                }
                $profilePic = str_replace('%25', '%', $profilePic);
            }
        }

        return [
            'success' => true,
            'data' => [
                'uid' => $uid,
                'username' => 'link_invite',
                'first_name' => 'Tài khoản Locket',
                'last_name' => '(qua Link Copy)',
                'profile_picture_url' => $profilePic ?: '',
                'is_invite_link' => true
            ]
        ];
    }

    /**
     * Public method: Trích xuất username từ input
     */
    public function extractUsername($input) {
        $input = trim($input);
        
        // URL: https://locket.cam/username
        if (preg_match('/https?:\/\/([^\/]+\.)?locket\.(cam|me|com)\/([a-zA-Z0-9_\.]+)/i', $input, $matches)) {
            return $matches[3];
        }
        
        // @username
        if (preg_match('/^@([a-zA-Z0-9_\.]+)$/', $input, $matches)) {
            return $matches[1];
        }
        
        // locket.cam/username
        if (preg_match('/^locket\.(cam|me|com)\/([a-zA-Z0-9_\.]+)$/i', $input, $matches)) {
            return $matches[2];
        }
        
        // Mặc định coi input là username
        return $input;
    }

    /**
     * Public method: Kiểm tra trạng thái token hiện tại
     */
    public function getStatus() {
        $cache = $this->readTokenCache();
        $validTokens = 0;
        $now = time();
        foreach ($cache as $item) {
            if (isset($item['expires_at']) && ($item['expires_at'] - $now > 300)) {
                $validTokens++;
            }
        }

        return [
            'hasToken' => !empty($this->idToken),
            'currentAccount' => $this->currentAccount['email'] ?? 'none',
            'accountsCount' => count($this->getAllAccounts()),
            'cachedValidTokens' => $validTokens
        ];
    }
}