<?php
// Cheranalloor Faces — Global Configuration (Sample Template)
// Copy this file to config.php and update with your local/production credentials.

date_default_timezone_set('Asia/Kolkata');

// 1. Determine the base URI (handles root domains and subfolders like WampServer)
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$appDir = str_replace('\\', '/', __DIR__);
$basePath = '/';

if (!empty($docRoot)) {
    $docRootLower = strtolower($docRoot);
    $appDirLower = strtolower($appDir);
    if (strpos($appDirLower, $docRootLower) === 0) {
        $basePath = substr($appDir, strlen($docRoot));
    }
}
$basePath = '/' . ltrim($basePath, '/');
$basePath = rtrim($basePath, '/') . '/';
define('BASE_URI', $basePath);

// 2. Database Credentials (Auto-detect environment: Localhost vs Production)
$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST']));

$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, ':') !== false) {
    $host = explode(':', $host)[0];
}

$isPrivateIp = false;
if (filter_var($host, FILTER_VALIDATE_IP)) {
    $isPrivateIp = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

$isLocalhost = $isCli || 
               in_array($host, ['localhost', '127.0.0.1', '[::1]']) || 
               $isPrivateIp;

if ($isLocalhost) {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'cheranalloor_faces');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DEVELOPMENT_MODE_DEFAULT', true);
} else {
    // Production MySQL Settings
    define('DB_HOST', 'your_production_db_host');
    define('DB_PORT', '3306');
    define('DB_NAME', 'your_production_db_name');
    define('DB_USER', 'your_production_db_user');
    define('DB_PASS', 'your_production_db_password');
    define('DEVELOPMENT_MODE_DEFAULT', false);
}

// 3. Session Configurations (Session Hijacking & CSRF mitigations)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
if ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) || 
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', 1);
}
?>
