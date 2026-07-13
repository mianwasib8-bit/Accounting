<?php
/**
 * Application Configuration
 * Professional Accounting Software
 */

declare(strict_types=1);

// Environment
define('APP_NAME', 'Accounting System');
define('APP_TAGLINE', 'Professional Voucher & Ledger Suite');
define('APP_VERSION', '2.1.0');
define('APP_ENV', 'local'); // local | production

// Database (Laragon defaults)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'accounting_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_NAME', 'ACCOUNTING_SYS_SESS');
define('SESSION_LIFETIME', 7200);

// Financial Year default (July–June, common in PK)
// Can be overridden from Settings / FinancialYears table
define('FY_START_MONTH', 7); // 1=Jan … 7=July
define('FY_START_DAY', 1);

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// Detect base URL for Laragon subdirectory installs
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$appRoot = realpath(ROOT_PATH);
$baseUrl = '';
if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
    $baseUrl = rtrim(str_replace('\\', '/', substr($appRoot, strlen($docRoot))), '/');
}
define('BASE_URL', $baseUrl);

/** Build absolute app URL */
function url(string $path = '/'): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return BASE_URL . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
