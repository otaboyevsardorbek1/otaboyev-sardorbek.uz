<?php
// Database configuration for the content API.
// Compatible with PHP 7.1.
//
// Resolution order for every setting:
//   1. environment variable (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_SOCKET)
//   2. config.local.php (gitignored)
//   3. defaults below
//
// config.local.php example:
//   <?php
//   return [
//       'host' => 'localhost',
//       'port' => 3306,
//       'name' => 'cpaneluser_portfolio',
//       'user' => 'cpaneluser_portfolio',
//       'pass' => 'secret',
//       'socket' => '',
//   ];

function content_env($key)
{
    $value = getenv($key);
    return $value === false ? null : $value;
}

function content_local_settings()
{
    static $local = null;
    if ($local !== null) {
        return $local;
    }

    $local = array();
    $localFile = dirname(__DIR__) . '/config.local.php';
    if (is_file($localFile)) {
        $loaded = require $localFile;
        if (is_array($loaded)) {
            $local = $loaded;
        }
    }

    return $local;
}

function content_db_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = content_local_settings();

    $envHost = content_env('DB_HOST');
    $envPort = content_env('DB_PORT');
    $envName = content_env('DB_NAME');
    $envUser = content_env('DB_USER');
    $envPass = content_env('DB_PASS');
    $envSocket = content_env('DB_SOCKET');

    $config = array(
        'host' => $envHost !== null ? $envHost : (isset($local['host']) ? $local['host'] : 'localhost'),
        'port' => (int) ($envPort !== null ? $envPort : (isset($local['port']) ? $local['port'] : 3306)),
        'name' => $envName !== null ? $envName : (isset($local['name']) ? $local['name'] : ''),
        'user' => $envUser !== null ? $envUser : (isset($local['user']) ? $local['user'] : ''),
        'pass' => $envPass !== null ? $envPass : (isset($local['pass']) ? $local['pass'] : ''),
        'socket' => $envSocket !== null ? $envSocket : (isset($local['socket']) ? $local['socket'] : ''),
    );

    return $config;
}

function content_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = content_db_config();
    $name = trim((string) $config['name']);
    $user = trim((string) $config['user']);

    if ($name === '' || $user === '') {
        throw new RuntimeException('Content database is not configured');
    }

    $socket = trim((string) $config['socket']);
    if ($socket !== '') {
        $dsn = 'mysql:unix_socket=' . $socket . ';dbname=' . $name . ';charset=utf8mb4';
    } else {
        $dsn = 'mysql:host=' . $config['host'] . ';port=' . (int) $config['port'] . ';dbname=' . $name . ';charset=utf8mb4';
    }

    $pdo = new PDO(
        $dsn,
        $user,
        (string) $config['pass'],
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        )
    );
    // Keep PHP gmdate(), UTC_TIMESTAMP() and DATETIME defaults in one timezone.
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}

function admin_setting($envKeys, $section, $key, $default)
{
    foreach ((array) $envKeys as $envKey) {
        $value = content_env($envKey);
        if ($value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }

    $local = content_local_settings();
    if (isset($local[$section]) && is_array($local[$section]) && isset($local[$section][$key]) && trim((string) $local[$section][$key]) !== '') {
        return trim((string) $local[$section][$key]);
    }

    return $default;
}

function admin_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $siteUrl = admin_setting(array('SITE_URL', 'APP_URL'), 'admin', 'site_url', 'https://otaboyev-sardorbek.uz');
    $botUsername = ltrim(admin_setting(array('TELEGRAM_BOT_USERNAME'), 'admin', 'telegram_bot_username', ''), '@');
    $telegramId = admin_setting(array('ADMIN_TELEGRAM_ID'), 'admin', 'telegram_id', '');
    $adminEmail = admin_setting(array('ADMIN_EMAIL'), 'admin', 'email', '');
    $appSecret = admin_setting(array('ADMIN_APP_SECRET', 'APP_SECRET'), 'admin', 'app_secret', '');

    $config = array(
        'site_url' => rtrim($siteUrl, '/'),
        'telegram_bot_token' => admin_setting(array('TELEGRAM_BOT_TOKEN'), 'admin', 'telegram_bot_token', ''),
        'telegram_bot_username' => $botUsername,
        'telegram_id' => $telegramId,
        'email' => $adminEmail,
        'app_secret' => $appSecret,
        'session_ttl' => (int) admin_setting(array('ADMIN_SESSION_TTL'), 'admin', 'session_ttl', 43200),
        'challenge_ttl' => (int) admin_setting(array('ADMIN_CHALLENGE_TTL'), 'admin', 'challenge_ttl', 600),
        'otp_max_attempts' => (int) admin_setting(array('ADMIN_OTP_MAX_ATTEMPTS'), 'admin', 'otp_max_attempts', 5),
        'auth_window' => (int) admin_setting(array('ADMIN_TELEGRAM_AUTH_WINDOW'), 'admin', 'telegram_auth_window', 300),
        'app_env' => strtolower(admin_setting(array('APP_ENV'), 'admin', 'app_env', 'production')),
        'trust_proxy' => admin_setting(array('ADMIN_TRUST_PROXY'), 'admin', 'trust_proxy', '0') === '1',
        'mail' => array(
            'driver' => strtolower(admin_setting(array('ADMIN_MAIL_DRIVER', 'MAIL_DRIVER'), 'mail', 'driver', 'mail')),
            'from' => admin_setting(array('ADMIN_MAIL_FROM', 'MAIL_FROM'), 'mail', 'from', 'no-reply@otaboyev-sardorbek.uz'),
            'from_name' => admin_setting(array('ADMIN_MAIL_FROM_NAME', 'MAIL_FROM_NAME'), 'mail', 'from_name', 'Sardorbek Otaboyev'),
            'log_file' => admin_setting(array('ADMIN_MAIL_LOG', 'MAIL_LOG'), 'mail', 'log_file', ''),
            'resend_api_key' => admin_setting(array('RESEND_API_KEY'), 'mail', 'resend_api_key', ''),
            'brevo_api_key' => admin_setting(array('BREVO_API_KEY'), 'mail', 'brevo_api_key', ''),
            'smtp_host' => admin_setting(array('SMTP_HOST'), 'mail', 'smtp_host', ''),
            'smtp_port' => (int) admin_setting(array('SMTP_PORT'), 'mail', 'smtp_port', 587),
            'smtp_secure' => strtolower(admin_setting(array('SMTP_SECURE'), 'mail', 'smtp_secure', 'tls')),
            'smtp_user' => admin_setting(array('SMTP_USER'), 'mail', 'smtp_user', ''),
            'smtp_pass' => admin_setting(array('SMTP_PASS'), 'mail', 'smtp_pass', ''),
        ),
    );

    $config['auth_url'] = $config['site_url'] . '/api.php/admin/auth/telegram';
    $siteUrlValid = (bool) preg_match('#^https?://#i', $config['site_url']);
    if ($config['app_env'] === 'production' && stripos($config['site_url'], 'https://') !== 0) {
        $siteUrlValid = false;
    }
    $mailUsable = !($config['app_env'] === 'production' && $config['mail']['driver'] === 'log');
    if ($mailUsable) {
        if ($config['mail']['driver'] === 'log') {
            $mailUsable = $config['mail']['log_file'] !== '';
        } elseif ($config['mail']['driver'] === 'smtp') {
            $mailUsable = $config['mail']['smtp_host'] !== '' && filter_var($config['mail']['from'], FILTER_VALIDATE_EMAIL) !== false;
        } elseif ($config['mail']['driver'] === 'resend') {
            $mailUsable = $config['mail']['resend_api_key'] !== '' && filter_var($config['mail']['from'], FILTER_VALIDATE_EMAIL) !== false;
        } elseif ($config['mail']['driver'] === 'brevo') {
            $mailUsable = $config['mail']['brevo_api_key'] !== '' && filter_var($config['mail']['from'], FILTER_VALIDATE_EMAIL) !== false;
        } elseif ($config['mail']['driver'] === 'mail') {
            $mailUsable = function_exists('mail') && filter_var($config['mail']['from'], FILTER_VALIDATE_EMAIL) !== false;
        } else {
            $mailUsable = false;
        }
    }
    $config['enabled'] = $siteUrlValid
        && $mailUsable
        && preg_match('/^[0-9]{5,20}:[A-Za-z0-9_-]{30,}$/', $config['telegram_bot_token'])
        && preg_match('/^[A-Za-z0-9_]{3,64}$/', $config['telegram_bot_username'])
        && preg_match('/^[0-9]{5,20}$/', $config['telegram_id'])
        && filter_var($config['email'], FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^[a-f0-9]{64}$/', $config['app_secret']);

    if ($config['session_ttl'] < 900 || $config['session_ttl'] > 604800) {
        $config['session_ttl'] = 43200;
    }
    if ($config['challenge_ttl'] < 300 || $config['challenge_ttl'] > 1800) {
        $config['challenge_ttl'] = 600;
    }
    if ($config['otp_max_attempts'] < 3 || $config['otp_max_attempts'] > 10) {
        $config['otp_max_attempts'] = 5;
    }

    return $config;
}
