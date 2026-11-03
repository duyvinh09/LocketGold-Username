<?php
if (!defined('LOCKET_INTERNAL')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/config.php';

class LocketGold {
    private $revenueCatApiKey;

    public function __construct() {
        $this->revenueCatApiKey = REVENUECAT_API_KEY;
    }

    public function checkGoldStatus($uid) {
        try {
            if (empty($uid)) {
                return ['success' => false, 'message' => 'Vui lòng cung cấp UID'];
            }

            $url = "https://api.revenuecat.com/v1/subscribers/" . urlencode($uid);

            $headers = [
                'Accept: */*',
                'Accept-Language: en',
                'Connection: keep-alive',
                'Content-Type: application/json',
                'User-Agent: Locket/1 CFNetwork/3860.600.12 Darwin/25.5.0',
                'Authorization: Bearer ' . $this->revenueCatApiKey,
                'X-Platform: iOS',
                'X-Version: 5.43.0'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['success' => false, 'message' => 'Lỗi kết nối: ' . $curlError];
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $entitlements = $data['subscriber']['entitlements']['Gold'] ?? null;

                if ($entitlements) {
                    $expiresDate = $entitlements['expires_date'] ?? null;
                    $isActive = $entitlements['active'] ?? false;
                    
                    $isActiveByDate = false;
                    if ($expiresDate) {
                        $isActiveByDate = strtotime($expiresDate) > time();
                    }
                    
                    $active = $isActive || $isActiveByDate;
                    $isExpired = !$active && $expiresDate !== null; 

                    return [
                        'success' => true,
                        'has_gold' => $active,
                        'is_expired' => $isExpired,
                        'active' => $active,
                        'expires_date' => $expiresDate,
                        'purchase_date' => $entitlements['purchase_date'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'has_gold' => false,
                    'active' => false,
                    'message' => 'User chưa có Gold entitlement'
                ];
            }

            if ($httpCode === 404) {
                return [
                    'success' => true,
                    'has_gold' => false,
                    'active' => false,
                    'message' => 'User chưa có subscription nào'
                ];
            }

            return [
                'success' => false,
                'message' => 'Không thể kiểm tra trạng thái Gold (HTTP ' . $httpCode . ')',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function activateGold($uid, $fetchToken = null, $appTransaction = null, $isSandbox = true) {
        try {
            $url = "https://api.revenuecat.com/v1/receipts";

            $fetchToken = $fetchToken ?? DEFAULT_FETCH_TOKEN;
            $appTransaction = $appTransaction ?? DEFAULT_APP_TRANSACTION;

            $payload = [
                "product_id" => GOLD_PRODUCT_ID,
                "fetch_token" => $fetchToken,
                "app_transaction" => $appTransaction,
                "app_user_id" => $uid,
                "is_restore" => true,
                "store_country" => GOLD_COUNTRY,
                "currency" => GOLD_CURRENCY,
                "price" => GOLD_PRODUCT_PRICE,
                "normal_duration" => GOLD_DURATION,
                "subscription_group_id" => GOLD_SUBSCRIPTION_GROUP_ID,
                "observer_mode" => false,
                "initiation_source" => "restore",
                "offers" => [],
                "attributes" => [
                    "\$attConsentStatus" => [
                        "updated_at_ms" => round(microtime(true) * 1000),
                        "value" => "denied"
                    ]
                ]
            ];

            $headers = [
                'Accept: */*',
                'Accept-Language: en',
                'Connection: keep-alive',
                'Content-Type: application/json',
                'User-Agent: Locket/1 CFNetwork/3860.600.12 Darwin/25.5.0',
                'X-Platform: iOS',
                'X-Version: 5.43.0',
                'X-Ios-Bundle-Identifier: com.locket.Locket',
                'X-Is-Sandbox: ' . ($isSandbox ? 'true' : 'false'),
                'Authorization: Bearer ' . $this->revenueCatApiKey
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                $entitlements = $data['subscriber']['entitlements']['Gold'] 
                             ?? $data['subscriber']['entitlements']['gold'] 
                             ?? null;

                if (!$entitlements && !empty($data['subscriber']['entitlements']) && is_array($data['subscriber']['entitlements'])) {
                    $entitlements = reset($data['subscriber']['entitlements']);
                }

                $expiresDate = $entitlements['expires_date'] ?? null;
                $purchaseDate = $entitlements['purchase_date'] ?? null;
                $hasGold = !empty($entitlements);

                // Fallback nếu không parse được entitlement từ receipt
                if (!$hasGold || !$expiresDate) {
                    $checkResult = $this->checkGoldStatus($uid);
                    if (!empty($checkResult['has_gold'])) {
                        $hasGold = true;
                        $expiresDate = $checkResult['expires_date'] ?? $expiresDate;
                        $purchaseDate = $checkResult['purchase_date'] ?? $purchaseDate;
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Kích hoạt Gold thành công!',
                    'expires_date' => $expiresDate,
                    'purchase_date' => $purchaseDate,
                    'has_gold' => $hasGold,
                ];
            }

            return [
                'success' => false,
                'message' => 'Kích hoạt Gold thất bại, vui lòng thử lại',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
}
