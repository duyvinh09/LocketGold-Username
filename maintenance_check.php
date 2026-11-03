<?php
define('MAINTENANCE_FILE', __DIR__ . '/storage/maintenance.lock');
define('ADMIN_SECRET_KEY', '09012005');

function isMaintenanceMode() {
    return file_exists(MAINTENANCE_FILE);
}

function isAdminBypassMaintenance() {
    $token = $_COOKIE['locket_admin_token'] ?? '';
    return $token && hash_equals(hash('sha256', ADMIN_SECRET_KEY), $token);
}

function requireNotMaintenance() {
    if (isMaintenanceMode() && !isAdminBypassMaintenance()) {
        include __DIR__ . '/baotri.php';
        exit;
    }
}