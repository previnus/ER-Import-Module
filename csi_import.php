<?php

/**
 * csi_import.php — Server-side cron trigger for the CSI RemoteCatalog module.
 *
 * Place this file outside the web root and call it from the server's crontab:
 *   /usr/bin/php /path/to/csi_import.php >> /dev/null 2>&1
 *
 * Tokens are derived from PrestaShop's own configuration files at runtime,
 * so no secrets need to be stored in this script.
 */

// ---------------------------------------------------------------------------
// Configuration — edit these two lines to match your server layout
// ---------------------------------------------------------------------------
define('PS_ROOT',    '/home/esw/apps/esw');          // PrestaShop installation root
define('ADMIN_DIR',  'eswpanel');                     // Admin folder name
// ---------------------------------------------------------------------------

define('LOG_FILE',   PS_ROOT . '/csi_import.log');
define('LOCK_FILE',  PS_ROOT . '/var/cache/csi_import.lock');
define('LOCK_TTL',   7200);   // Seconds before a stale lock is ignored (2 h)
define('CURL_TIMEOUT', 1800); // Seconds before curl gives up (30 min)
// LOCK_TTL must be > CURL_TIMEOUT so the lock outlives the HTTP request.

// ---------------------------------------------------------------------------
// Lock — app-specific path prevents conflicts on shared hosting
// ---------------------------------------------------------------------------
$lockDir = dirname(LOCK_FILE);
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0750, true);
}

if (file_exists(LOCK_FILE) && (time() - filemtime(LOCK_FILE)) < LOCK_TTL) {
    logEntry("Skipped — lock held since " . date('Y-m-d H:i:s', filemtime(LOCK_FILE)));
    exit(0);
}

// Write lock; remove on script exit regardless of how it exits
file_put_contents(LOCK_FILE, (string)time());
register_shutdown_function(function () {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
});

// ---------------------------------------------------------------------------
// Load PrestaShop configuration — no hardcoded secrets
// ---------------------------------------------------------------------------
try {
    $cronToken  = readCronToken();
    $adminToken = computeAdminToken();
} catch (RuntimeException $e) {
    logEntry('FATAL: ' . $e->getMessage());
    exit(1);
}

if (!$cronToken) {
    logEntry('FATAL: CSI_REMOTECATALOG_CRON_TOKEN not found in database. Is the module installed?');
    exit(1);
}
if (!$adminToken) {
    logEntry('FATAL: Could not compute PrestaShop admin token. Check PS_ROOT and admin employee.');
    exit(1);
}

// ---------------------------------------------------------------------------
// Build URL — nothing hardcoded except paths already set above
// ---------------------------------------------------------------------------
$url = sprintf(
    'https://%s/%s/?controller=AdminTriggerRemoteCatalogCron&cron=%s&token=%s',
    $_SERVER['HTTP_HOST'] ?? parse_ini_file(PS_ROOT . '/app/config/parameters.php')['parameters']['ps_domain'] ?? gethostname(),
    ADMIN_DIR,
    rawurlencode($cronToken),
    rawurlencode($adminToken)
);

// ---------------------------------------------------------------------------
// Execute
// ---------------------------------------------------------------------------
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_TIMEOUT         => CURL_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT  => 30,
    CURLOPT_SSL_VERIFYPEER  => true,
    CURLOPT_SSL_VERIFYHOST  => 2,
]);

$response   = curl_exec($ch);
$curlError  = curl_error($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ---------------------------------------------------------------------------
// Result handling
// ---------------------------------------------------------------------------
if ($curlError) {
    logEntry("ERROR [curl]: {$curlError}");
    exit(1);
}

if ($httpStatus < 200 || $httpStatus >= 300) {
    logEntry("ERROR [HTTP {$httpStatus}]: unexpected status code");
    exit(1);
}

// Truncate and strip control characters before logging remote output
$safeResponse = mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$response), 0, 2000);
logEntry("OK [HTTP {$httpStatus}]: {$safeResponse}");
exit(0);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function logEntry(string $message): void
{
    file_put_contents(
        LOG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
        FILE_APPEND
    );
}

/**
 * Reads the module's cron token from the PrestaShop configuration table.
 * Token is generated on module install; never hardcoded.
 */
function readCronToken(): ?string
{
    $pdo = getPrestaShopPdo();
    $prefix = getDbPrefix();

    $stmt = $pdo->prepare(
        "SELECT `value` FROM `{$prefix}configuration` WHERE `name` = 'CSI_REMOTECATALOG_CRON_TOKEN' LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : null;
}

/**
 * Computes the PrestaShop admin token for employee #1 and the cron controller.
 * Formula: md5(employee_id . _COOKIE_KEY_ . controller_class_name)
 * This matches Tools::getAdminTokenLite() in the PS core.
 */
function computeAdminToken(): ?string
{
    $cookieKey = readCookieKey();
    if (!$cookieKey) {
        return null;
    }
    return md5('1' . $cookieKey . 'AdminTriggerRemoteCatalogCron');
}

/**
 * Extracts _COOKIE_KEY_ from config/settings.inc.php without requiring a
 * full PrestaShop bootstrap (avoids missing-constant fatals).
 */
function readCookieKey(): ?string
{
    $settingsFile = PS_ROOT . '/config/settings.inc.php';
    if (!file_exists($settingsFile)) {
        throw new RuntimeException("PS settings file not found: {$settingsFile}");
    }

    $content = file_get_contents($settingsFile);
    if (preg_match("/define\s*\(\s*'_COOKIE_KEY_'\s*,\s*'([^']+)'\s*\)/", $content, $m)) {
        return $m[1];
    }
    // Newer PS 1.7.x stores it in parameters.php as 'cookie_key'
    $params = readPsParameters();
    return $params['cookie_key'] ?? null;
}

/**
 * Returns a PDO connection to the PrestaShop database.
 */
function getPrestaShopPdo(): PDO
{
    $p = readPsParameters();
    $host = $p['database_host'] ?? 'localhost';
    $port = $p['database_port'] ? ';port=' . $p['database_port'] : '';
    $dsn  = "mysql:host={$host}{$port};dbname={$p['database_name']};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $p['database_user'], $p['database_password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (\PDOException $e) {
        throw new RuntimeException('DB connection failed: ' . $e->getMessage());
    }
}

function getDbPrefix(): string
{
    $p = readPsParameters();
    return $p['database_prefix'] ?? 'ps_';
}

/**
 * Parses app/config/parameters.php and returns the parameters array.
 * This file is the canonical source of DB credentials in PS 1.7+.
 */
function readPsParameters(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $file = PS_ROOT . '/app/config/parameters.php';
    if (!file_exists($file)) {
        throw new RuntimeException("PrestaShop parameters file not found: {$file}");
    }

    $data = require $file;
    $cache = $data['parameters'] ?? [];
    return $cache;
}
