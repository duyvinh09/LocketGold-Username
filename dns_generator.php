<?php
// dns_generator.php
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

// THÊM DÒNG NÀY ĐỂ INCLUDE CONFIG
require_once 'config.php';

class DNSGenerator {
    private $apiKey;
    private $baseUrl = "https://api.nextdns.io";
    
    public function __construct($apiKey = null) {
        // Ưu tiên dùng API key từ config trước
        if (defined('NEXTDNS_API_KEY') && !empty(NEXTDNS_API_KEY)) {
            $this->apiKey = NEXTDNS_API_KEY;
        } else {
            // Fallback sang parameter hoặc env
            $this->apiKey = $apiKey ?? getenv('NEXTDNS_API_KEY') ?: '';
        }
        
        // Log để debug
        $this->log("DNSGenerator initialized with API Key: " . substr($this->apiKey, 0, 10) . "...");
    }
    
    /**
     * Tạo profile DNS mới hoặc lấy profile đã tồn tại trong ngày
     */
    public function createProfile($customName = null) {
        try {
            if (empty($this->apiKey)) {
                $this->log("❌ Missing NextDNS API Key");
                return [
                    'success' => false,
                    'message' => 'Missing NextDNS API Key. Vui lòng cấu hình NEXTDNS_API_KEY trong config.php'
                ];
            }
            
            $todayStr = date('Y-m-d');
            $profileName = $customName ?? "LocketVIP-{$todayStr}";
            
            $this->log("🔍 Checking for existing profile: {$profileName}...");
            
            // Kiểm tra profile đã tồn tại chưa
            $existingProfile = $this->findExistingProfile($profileName);
            
            if ($existingProfile) {
                $pid = $existingProfile['id'];
                $this->log("✅ Found existing daily profile: {$pid} (REUSING)");
                
                // Verify và cập nhật rules
                $this->log("🔧 Verifying High-Speed VIP Node...");
                $this->ensureBlockRules($pid);
                
                $this->log("✅ DNS VIP Node Active (Cached).");
                
                return [
                    'success' => true,
                    'profile_id' => $pid,
                    'profile_name' => $profileName,
                    'ios_link' => "https://apple.nextdns.io/?profile={$pid}",
                    'android_dns' => "{$pid}.dns.nextdns.io",
                    'is_new' => false
                ];
            }
            
            // Tạo profile mới
            $this->log("🆕 Creating new daily profile: {$profileName}");
            $this->log("⚡ Initializing High-Speed VIP DNS Node...");
            usleep(500000); // 0.5 seconds
            
            $pid = $this->createNewProfile($profileName);
            
            if (!$pid) {
                return [
                    'success' => false,
                    'message' => 'Failed to create DNS profile. Kiểm tra API Key có đúng không?'
                ];
            }
            
            $this->log("✅ Profile created: {$pid}");
            
            // Áp dụng rules chặn
            $this->log("🛡️ Applying Anti-Revoke Rules (RevenueCat/Apple)...");
            usleep(400000); // 0.4 seconds
            
            $this->applyBlockRules($pid);
            
            $this->log("✅ DNS VIP Node Active.");
            
            return [
                'success' => true,
                'profile_id' => $pid,
                'profile_name' => $profileName,
                'ios_link' => "https://apple.nextdns.io/?profile={$pid}",
                'android_dns' => "{$pid}.dns.nextdns.io",
                'is_new' => true
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Tìm profile đã tồn tại theo tên
     */
    private function findExistingProfile($profileName) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/profiles");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $this->log("❌ CURL Error listing profiles: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $profiles = $data['data'] ?? [];
            
            foreach ($profiles as $profile) {
                if ($profile['name'] === $profileName) {
                    return $profile;
                }
            }
        } else {
            $this->log("⚠️ List profiles failed with HTTP {$httpCode}: " . substr($response, 0, 200));
        }
        
        return null;
    }
    
    /**
     * Tạo profile mới
     */
    private function createNewProfile($profileName) {
        $payload = json_encode(['name' => $profileName]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/profiles");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $this->log("❌ CURL Error creating profile: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['data']['id'] ?? null;
        } else {
            $this->log("⚠️ Create profile failed with HTTP {$httpCode}: " . substr($response, 0, 200));
            return null;
        }
    }
    
    /**
     * Đảm bảo các rules chặn đã được áp dụng
     */
    private function ensureBlockRules($profileId) {
        $this->applyBlockRules($profileId);
    }
    
    /**
     * Áp dụng rules chặn cho profile
     */
    private function applyBlockRules($profileId) {
        $domainsToBlock = [
            'revenuecat.com',
            'api.revenuecat.com',
            'www.revenuecat.com',
            // 'apple.com',
            // 'itunes.apple.com',
            // 'buy.itunes.apple.com'
        ];
        
        $denylistUrl = $this->baseUrl . "/profiles/{$profileId}/denylist";
        
        foreach ($domainsToBlock as $domain) {
            $payload = json_encode([
                'id' => $domain,
                'active' => true
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $denylistUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Api-Key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_exec($ch);
            curl_close($ch);
            
            usleep(100000); // 0.1 second delay between requests
        }
        
        // Verify rules đã được áp dụng
        $this->verifyBlockRules($profileId);
    }
    
    /**
     * Verify rules đã được áp dụng thành công
     */
    private function verifyBlockRules($profileId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/profiles/{$profileId}/denylist");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $rules = $data['data'] ?? [];
            $blocked = [];
            
            foreach ($rules as $rule) {
                if ($rule['active']) {
                    $blocked[] = $rule['id'];
                }
            }
            
            if (!empty($blocked)) {
                $this->log("✅ Firewall Rules Applied: " . implode(', ', $blocked));
            } else {
                $this->log("⚠️ No rules found in verify.");
            }
        } else {
            $this->log("⚠️ Validation Failed: HTTP {$httpCode}");
        }
    }
    
    /**
     * Xóa profile DNS
     */
    public function deleteProfile($profileId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/profiles/{$profileId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Api-Key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode === 204,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Log message
     */
    private function log($message) {
        $logFile = __DIR__ . '/dns_generator.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
        error_log($message);
    }
}

// Xử lý request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Debug: Kiểm tra config đã được load chưa
error_log("NEXTDNS_API_KEY from config: " . (defined('NEXTDNS_API_KEY') ? substr(NEXTDNS_API_KEY, 0, 10) . '...' : 'NOT DEFINED'));

if (isset($_POST['action'])) {
    $dns = new DNSGenerator();
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create':
            $customName = $_POST['name'] ?? null;
            $result = $dns->createProfile($customName);
            ob_clean();
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'delete':
            $profileId = $_POST['profile_id'] ?? '';
            if (empty($profileId)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Vui lòng cung cấp profile_id'
                ]);
                exit;
            }
            $result = $dns->deleteProfile($profileId);
            ob_clean();
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Action không hợp lệ. Các action hỗ trợ: create, delete'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
    }
    exit();
} else {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng cung cấp action'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}
?>