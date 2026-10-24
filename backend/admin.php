<?php
// Admin authentication and content API. Included from api.php.
// Compatible with PHP 7.1.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

if (!isset($requestPath)) {
    http_response_code(404);
    exit;
}

define('ADMIN_SESSION_COOKIE', 'admin_session');
define('ADMIN_CHALLENGE_COOKIE', 'admin_challenge');
define('ADMIN_RATE_WINDOW_MINUTES', 15);
define('ADMIN_RATE_MAX_CHALLENGES', 3);
define('ADMIN_RATE_MAX_STARTS', 5);

function admin_headers()
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
}

function admin_respond($data, $code)
{
    admin_headers();
    http_response_code((int) $code);
    echo json_encode(
        array('status' => 'ok', 'data' => $data),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function admin_fail($message, $errorCode, $httpCode)
{
    admin_headers();
    http_response_code((int) $httpCode);
    echo json_encode(
        array('status' => 'error', 'message' => $message, 'code' => $errorCode),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function admin_redirect($path)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('X-Robots-Tag: noindex, nofollow');
    header('Location: ' . $path, true, 302);
    exit;
}

function admin_require_method($allowed)
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    if (!in_array($method, (array) $allowed, true)) {
        admin_fail('Method not allowed', 'method_not_allowed', 405);
    }
    return $method;
}

function admin_json_input()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return array();
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        admin_fail('Invalid JSON body', 'invalid_json', 400);
    }
    return $data;
}

function admin_is_https()
{
    $config = admin_config();
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($config['trust_proxy']) && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function admin_set_cookie($name, $value, $ttl, $sameSite)
{
    $secure = admin_is_https() ? '; Secure' : '';
    $maxAge = max(0, (int) $ttl);
    $expires = $maxAge === 0 ? 'Thu, 01 Jan 1970 00:00:00 GMT' : gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT';
    $header = 'Set-Cookie: ' . $name . '=' . rawurlencode((string) $value)
        . '; Max-Age=' . $maxAge
        . '; Expires=' . $expires
        . '; Path=/; HttpOnly; SameSite=' . $sameSite . $secure;
    header($header, false);
}

function admin_clear_cookie($name, $sameSite)
{
    admin_set_cookie($name, '', 0, $sameSite);
}

function admin_cookie($name)
{
    return isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) ? (string) $_COOKIE[$name] : '';
}

function admin_token($bytes)
{
    return bin2hex(random_bytes((int) $bytes));
}

function admin_secret_hash($purpose, $value)
{
    $config = admin_config();
    return hash_hmac('sha256', $purpose . ':' . (string) $value, $config['app_secret']);
}

function admin_session_hash($token)
{
    return admin_secret_hash('session', $token);
}

function admin_challenge_hash($token)
{
    return admin_secret_hash('challenge', $token);
}

function admin_code_hash($challengeToken, $code)
{
    return admin_secret_hash('otp', $challengeToken . ':' . $code);
}

function admin_csrf_token($sessionToken)
{
    return admin_secret_hash('csrf', $sessionToken);
}

function admin_client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

function admin_ip_hash()
{
    return admin_secret_hash('ip', admin_client_ip());
}

function admin_user_agent()
{
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return substr($agent, 0, 255);
}

function admin_mask_email($email)
{
    $parts = explode('@', (string) $email, 2);
    if (count($parts) !== 2) {
        return '***';
    }
    $name = $parts[0];
    $visible = substr($name, 0, min(2, strlen($name)));
    return $visible . str_repeat('*', max(2, strlen($name) - strlen($visible))) . '@' . $parts[1];
}

function admin_origin_parts($url)
{
    $parts = parse_url((string) $url);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    return array(
        'scheme' => $scheme,
        'host' => strtolower((string) $parts['host']),
        'port' => $port,
    );
}

function admin_check_origin()
{
    $config = admin_config();
    $expected = admin_origin_parts($config['site_url']);
    if ($expected === null) {
        admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) && is_string($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';

    // Sandboxed/opaque origins ("null") must never pass the comparison below.
    if (strtolower(trim($origin)) === 'null') {
        admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
    }

    $candidate = $origin !== '' ? $origin : $referer;
    if ($candidate === '') {
        // Browsers always send Origin (or Referer) for state-changing
        // requests. Header-less clients must opt in explicitly, so a forged
        // request that strips both headers is still rejected.
        $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? (string) $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
        if (strcasecmp($requestedWith, 'XMLHttpRequest') !== 0) {
            admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
        }
        return;
    }

    $actual = admin_origin_parts($candidate);
    if ($actual === null
        || $actual['host'] !== $expected['host']
        || $actual['scheme'] !== $expected['scheme']
        || $actual['port'] !== $expected['port']) {
        admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
    }
}

function admin_lookup_session($pdo, $touch)
{
    $token = admin_cookie(ADMIN_SESSION_COOKIE);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $config = admin_config();
    $idleCutoff = gmdate('Y-m-d H:i:s', time() - (int) $config['idle_ttl']);

    $statement = $pdo->prepare(
        'SELECT s.id AS session_id, s.admin_id, s.expires_at, s.last_seen_at,
                a.telegram_id, a.email, a.display_name, a.telegram_username,
                a.role, a.last_login_at
         FROM admin_sessions s
         INNER JOIN admin_users a ON a.id = s.admin_id
         WHERE s.token_hash = :token_hash
           AND s.expires_at > UTC_TIMESTAMP()
           AND s.last_seen_at > :idle_cutoff
           AND a.is_active = 1
         LIMIT 1'
    );
    $statement->execute(array(
        ':token_hash' => admin_session_hash($token),
        ':idle_cutoff' => $idleCutoff,
    ));
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }

    if ($touch) {
        $update = $pdo->prepare('UPDATE admin_sessions SET last_seen_at = UTC_TIMESTAMP() WHERE id = :id');
        $update->execute(array(':id' => $row['session_id']));
    }

    return array('row' => $row, 'token' => $token);
}

function admin_require_session($pdo)
{
    $session = admin_lookup_session($pdo, true);
    if (!$session) {
        admin_clear_cookie(ADMIN_SESSION_COOKIE, 'Strict');
        admin_fail('Authentication required', 'auth_required', 401);
    }
    return $session;
}

function admin_require_csrf($session)
{
    admin_check_origin();
    $provided = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    $expected = admin_csrf_token($session['token']);
    if ($provided === '' || !hash_equals($expected, $provided)) {
        admin_fail('Invalid CSRF token', 'csrf_failed', 403);
    }
}

function admin_require_super($session)
{
    $role = isset($session['row']['role']) ? (string) $session['row']['role'] : '';
    if ($role !== 'super') {
        admin_fail('Super admin permission required', 'forbidden', 403);
    }
}

function admin_has_other_active_super($pdo, $exceptId)
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM admin_users
         WHERE is_active = 1 AND role = 'super' AND id <> :id"
    );
    $statement->execute(array(':id' => (int) $exceptId));
    return (int) $statement->fetchColumn() > 0;
}

function admin_audit($pdo, $adminId, $action, $entity, $entityId, $details)
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity, entity_id, ip_hash, details)
             VALUES (:admin_id, :action, :entity, :entity_id, :ip_hash, :details)'
        );
        $statement->execute(array(
            ':admin_id' => $adminId,
            ':action' => (string) $action,
            ':entity' => $entity === null ? null : (string) $entity,
            ':entity_id' => $entityId === null ? null : (string) $entityId,
            ':ip_hash' => admin_ip_hash(),
            ':details' => $details === null ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ));
    } catch (Exception $error) {
        error_log('admin audit error: ' . $error->getMessage());
    }
}

function admin_log_auth_event($pdo, $type, $telegramId)
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO admin_auth_events (event_type, telegram_id, ip_hash)
             VALUES (:event_type, :telegram_id, :ip_hash)'
        );
        $statement->execute(array(
            ':event_type' => (string) $type,
            ':telegram_id' => $telegramId === null ? null : (int) $telegramId,
            ':ip_hash' => admin_ip_hash(),
        ));
    } catch (Exception $error) {
        error_log('admin auth event error: ' . $error->getMessage());
    }
}

function admin_rate_count($pdo, $table, $where, $params, $minutes)
{
    $allowed = array('admin_auth_events', 'admin_login_challenges');
    if (!in_array($table, $allowed, true)) {
        return 0;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where
        . ' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (int) $minutes . ' MINUTE)'
    );
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

function admin_cleanup($pdo)
{
    if (random_int(1, 10) !== 1) {
        return;
    }
    try {
        $pdo->exec('DELETE FROM admin_login_challenges WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
        $pdo->exec('DELETE FROM admin_sessions WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
        $pdo->exec('DELETE FROM admin_auth_events WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
    } catch (Exception $error) {
        error_log('admin cleanup error: ' . $error->getMessage());
    }
}

function admin_telegram_validate($query)
{
    $config = admin_config();
    if (!$config['enabled']) {
        return array(false, 'config');
    }
    if (!is_array($query) || !isset($query['hash'], $query['id'], $query['auth_date'])) {
        return array(false, 'telegram');
    }

    $hash = strtolower((string) $query['hash']);
    if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
        return array(false, 'telegram');
    }

    $data = array();
    foreach ($query as $key => $value) {
        if (!is_scalar($value) || $key === 'hash') {
            continue;
        }
        $data[(string) $key] = (string) $value;
    }
    ksort($data, SORT_STRING);

    $pairs = array();
    foreach ($data as $key => $value) {
        $pairs[] = $key . '=' . $value;
    }
    $checkString = implode("\n", $pairs);
    $secretKey = hash('sha256', $config['telegram_bot_token'], true);
    $expected = hash_hmac('sha256', $checkString, $secretKey);
    if (!hash_equals($expected, $hash)) {
        return array(false, 'telegram');
    }

    $authDate = (int) $query['auth_date'];
    $now = time();
    if ($authDate < $now - (int) $config['auth_window'] || $authDate > $now + 30) {
        return array(false, 'expired');
    }

    $telegramId = preg_replace('/[^0-9]/', '', (string) $query['id']);
    if ($telegramId === '' || strlen($telegramId) > 20) {
        return array(false, 'telegram');
    }

    return array(true, array(
        'id' => $telegramId,
        'first_name' => isset($query['first_name']) ? substr((string) $query['first_name'], 0, 120) : '',
        'last_name' => isset($query['last_name']) ? substr((string) $query['last_name'], 0, 120) : '',
        'username' => isset($query['username']) ? substr((string) $query['username'], 0, 64) : '',
    ));
}

function admin_find_or_bootstrap_user($pdo, array $telegramUser)
{
    $config = admin_config();
    $statement = $pdo->prepare(
        'SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at
         FROM admin_users WHERE telegram_id = :telegram_id LIMIT 1'
    );
    $statement->execute(array(':telegram_id' => $telegramUser['id']));
    $row = $statement->fetch();

    if ($row) {
        if ((int) $row['is_active'] !== 1) {
            return null;
        }
        $displayName = trim($telegramUser['first_name'] . ' ' . $telegramUser['last_name']);
        if ($displayName !== '' && $displayName !== $row['display_name']) {
            $update = $pdo->prepare('UPDATE admin_users SET display_name = :name, telegram_username = :username WHERE id = :id');
            $update->execute(array(
                ':name' => $displayName,
                ':username' => $telegramUser['username'] !== '' ? $telegramUser['username'] : null,
                ':id' => $row['id'],
            ));
            $row['display_name'] = $displayName;
            $row['telegram_username'] = $telegramUser['username'];
        }
        return admin_promote_bootstrap_super($pdo, $row, $config);
    }

    if ((string) $telegramUser['id'] !== (string) $config['telegram_id']) {
        return null;
    }

    $displayName = trim($telegramUser['first_name'] . ' ' . $telegramUser['last_name']);
    $insert = $pdo->prepare(
        'INSERT INTO admin_users (telegram_id, email, display_name, telegram_username, is_active, role)
         VALUES (:telegram_id, :email, :display_name, :username, 1, :role)'
    );
    try {
        $insert->execute(array(
            ':telegram_id' => $telegramUser['id'],
            ':email' => $config['email'],
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':username' => $telegramUser['username'] !== '' ? $telegramUser['username'] : null,
            ':role' => 'super',
        ));
    } catch (PDOException $error) {
        // Another callback may have created the same allowlisted admin.
        if ($error->getCode() !== '23000') {
            throw $error;
        }
    }

    $statement->execute(array(':telegram_id' => $telegramUser['id']));
    $row = $statement->fetch();

    return $row ? admin_promote_bootstrap_super($pdo, $row, $config) : $row;
}

// Safety net for databases migrated from before the role column existed: when
// no active super admin remains, the configured bootstrap ID is promoted on
// its next successful login so the panel cannot lock itself out.
function admin_promote_bootstrap_super($pdo, $row, $config)
{
    if (!is_array($row) || !isset($row['role'])) {
        return $row;
    }
    if ((string) $row['role'] === 'super'
        || (string) $row['telegram_id'] !== (string) $config['telegram_id']) {
        return $row;
    }

    try {
        $superCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM admin_users WHERE is_active = 1 AND role = 'super'"
        )->fetchColumn();
        if ($superCount === 0) {
            $promote = $pdo->prepare("UPDATE admin_users SET role = 'super' WHERE id = :id");
            $promote->execute(array(':id' => (int) $row['id']));
            $row['role'] = 'super';
        }
    } catch (Exception $error) {
        error_log('admin bootstrap promote failed: ' . $error->getMessage());
    }

    return $row;
}

function admin_create_challenge($pdo, array $admin, $ipHash)
{
    $config = admin_config();
    $challengeCount = admin_rate_count(
        $pdo,
        'admin_login_challenges',
        'admin_id = :admin_id',
        array(':admin_id' => $admin['id']),
        ADMIN_RATE_WINDOW_MINUTES
    );
    if ($challengeCount >= ADMIN_RATE_MAX_CHALLENGES) {
        admin_log_auth_event($pdo, 'challenge_rate_limited', $admin['telegram_id']);
        return array(false, 'rate_limited', 429, 'Too many login codes requested. Try again later.');
    }

    $token = admin_token(32);
    $code = (string) random_int(100000, 999999);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + (int) $config['challenge_ttl']);

    $statement = $pdo->prepare(
        'INSERT INTO admin_login_challenges
         (admin_id, challenge_hash, code_hash, email, attempts, expires_at, ip_hash)
         VALUES (:admin_id, :challenge_hash, :code_hash, :email, 0, :expires_at, :ip_hash)'
    );
    $statement->execute(array(
        ':admin_id' => $admin['id'],
        ':challenge_hash' => admin_challenge_hash($token),
        ':code_hash' => admin_code_hash($token, $code),
        ':email' => $admin['email'],
        ':expires_at' => $expiresAt,
        ':ip_hash' => $ipHash,
    ));
    $challengeId = (int) $pdo->lastInsertId();

    $mailResult = admin_send_login_code($admin['email'], $code, (int) floor((int) $config['challenge_ttl'] / 60));
    if (!$mailResult[0]) {
        $delete = $pdo->prepare('DELETE FROM admin_login_challenges WHERE challenge_hash = :hash');
        $delete->execute(array(':hash' => admin_challenge_hash($token)));
        admin_log_auth_event($pdo, 'email_failed', $admin['telegram_id']);
        error_log('admin mail error: ' . $mailResult[1]);
        return array(false, 'email_failed', 503, 'Login code could not be sent. Try again later.');
    }

    $consume = $pdo->prepare('UPDATE admin_login_challenges SET consumed_at = UTC_TIMESTAMP()
        WHERE admin_id = :admin_id AND consumed_at IS NULL AND id <> :id');
    $consume->execute(array(':admin_id' => $admin['id'], ':id' => $challengeId));

    admin_set_cookie(ADMIN_CHALLENGE_COOKIE, $token, (int) $config['challenge_ttl'], 'Lax');
    admin_log_auth_event($pdo, 'challenge_created', $admin['telegram_id']);

    return array(true, array(
        'expires_in' => (int) $config['challenge_ttl'],
        'email' => admin_mask_email($admin['email']),
    ), 200, '');
}

function admin_lookup_challenge($pdo)
{
    $token = admin_cookie(ADMIN_CHALLENGE_COOKIE);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT c.id, c.admin_id, c.attempts, c.expires_at,
                a.telegram_id, a.email, a.display_name, a.telegram_username, a.is_active
         FROM admin_login_challenges c
         INNER JOIN admin_users a ON a.id = c.admin_id
         WHERE c.challenge_hash = :challenge_hash
           AND c.consumed_at IS NULL
           AND c.expires_at > UTC_TIMESTAMP()
           AND a.is_active = 1
         LIMIT 1'
    );
    $statement->execute(array(':challenge_hash' => admin_challenge_hash($token)));
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }
    return array('row' => $row, 'token' => $token);
}

function admin_create_session($pdo, array $admin)
{
    $config = admin_config();
    $token = admin_token(32);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + (int) $config['session_ttl']);
    $statement = $pdo->prepare(
        'INSERT INTO admin_sessions (admin_id, token_hash, expires_at, ip_hash, user_agent)
         VALUES (:admin_id, :token_hash, :expires_at, :ip_hash, :user_agent)'
    );
    $statement->execute(array(
        ':admin_id' => $admin['id'],
        ':token_hash' => admin_session_hash($token),
        ':expires_at' => $expiresAt,
        ':ip_hash' => admin_ip_hash(),
        ':user_agent' => admin_user_agent(),
    ));
    $update = $pdo->prepare('UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
    $update->execute(array(':id' => $admin['id']));
    return $token;
}

function admin_handle_telegram_callback($pdo)
{
    admin_require_method('GET');
    $config = admin_config();
    if (!$config['enabled']) {
        admin_redirect('/admin/?error=config');
    }

    $validation = admin_telegram_validate($_GET);
    if (!$validation[0]) {
        admin_log_auth_event($pdo, 'telegram_rejected_' . $validation[1], null);
        admin_redirect('/admin/?error=' . rawurlencode($validation[1]));
    }

    $telegramUser = $validation[1];
    $startCount = admin_rate_count(
        $pdo,
        'admin_auth_events',
        'event_type = :event_type AND telegram_id = :telegram_id',
        array(':event_type' => 'challenge_created', ':telegram_id' => (int) $telegramUser['id']),
        ADMIN_RATE_WINDOW_MINUTES
    );
    if ($startCount >= ADMIN_RATE_MAX_STARTS) {
        admin_log_auth_event($pdo, 'telegram_rate_limited', $telegramUser['id']);
        admin_redirect('/admin/?error=rate');
    }

    $admin = admin_find_or_bootstrap_user($pdo, $telegramUser);
    if (!$admin) {
        admin_log_auth_event($pdo, 'telegram_unauthorized', $telegramUser['id']);
        admin_redirect('/admin/?error=unauthorized');
    }

    $challengeCount = admin_rate_count(
        $pdo,
        'admin_login_challenges',
        'admin_id = :admin_id',
        array(':admin_id' => $admin['id']),
        ADMIN_RATE_WINDOW_MINUTES
    );
    if ($challengeCount >= ADMIN_RATE_MAX_CHALLENGES) {
        admin_log_auth_event($pdo, 'telegram_rate_limited', $telegramUser['id']);
        admin_redirect('/admin/?error=rate');
    }

    $created = admin_create_challenge($pdo, $admin, admin_ip_hash());
    if (!$created[0]) {
        $errorCode = $created[1] === 'rate_limited' ? 'rate' : 'email';
        admin_redirect('/admin/?error=' . $errorCode);
    }
    admin_cleanup($pdo);
    admin_redirect('/admin/verify/');
}

function admin_handle_config()
{
    admin_require_method('GET');
    $config = admin_config();
    admin_respond(array(
        'enabled' => (bool) $config['enabled'],
        'bot_username' => $config['telegram_bot_username'],
        'auth_url' => $config['auth_url'],
        'site_url' => $config['site_url'],
    ), 200);
}

function admin_handle_status($pdo)
{
    admin_require_method('GET');
    $challenge = admin_lookup_challenge($pdo);
    if (!$challenge) {
        admin_clear_cookie(ADMIN_CHALLENGE_COOKIE, 'Lax');
        admin_fail('Login challenge required', 'challenge_required', 401);
    }
    admin_respond(array(
        'email' => admin_mask_email($challenge['row']['email']),
        'expires_in' => max(0, strtotime($challenge['row']['expires_at'] . ' UTC') - time()),
        'attempts_remaining' => max(0, (int) admin_config()['otp_max_attempts'] - (int) $challenge['row']['attempts']),
    ), 200);
}

function admin_handle_verify($pdo)
{
    admin_require_method('POST');
    admin_check_origin();
    $input = admin_json_input();
    $code = isset($input['code']) && is_scalar($input['code']) ? trim((string) $input['code']) : '';
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        admin_fail('Enter the 6-digit code', 'invalid_code', 422);
    }

    $challenge = admin_lookup_challenge($pdo);
    if (!$challenge) {
        admin_clear_cookie(ADMIN_CHALLENGE_COOKIE, 'Lax');
        admin_fail('Login challenge expired. Start again.', 'challenge_expired', 401);
    }

    $config = admin_config();
    $row = $challenge['row'];
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare(
            'SELECT id, admin_id, attempts, code_hash, expires_at
             FROM admin_login_challenges
             WHERE id = :id AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()
             FOR UPDATE'
        );
        $lock->execute(array(':id' => $row['id']));
        $locked = $lock->fetch();
        if (!$locked) {
            $pdo->rollBack();
            admin_clear_cookie(ADMIN_CHALLENGE_COOKIE, 'Lax');
            admin_fail('Login challenge expired. Start again.', 'challenge_expired', 401);
        }

        if ((int) $locked['attempts'] >= (int) $config['otp_max_attempts']) {
            $consume = $pdo->prepare('UPDATE admin_login_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = :id');
            $consume->execute(array(':id' => $locked['id']));
            $pdo->commit();
            admin_clear_cookie(ADMIN_CHALLENGE_COOKIE, 'Lax');
            admin_fail('Too many attempts. Start again.', 'too_many_attempts', 429);
        }

        $expected = admin_code_hash($challenge['token'], $code);
        if (!hash_equals($locked['code_hash'], $expected)) {
            $attempt = $pdo->prepare('UPDATE admin_login_challenges SET attempts = attempts + 1 WHERE id = :id');
            $attempt->execute(array(':id' => $locked['id']));
            $pdo->commit();
            admin_log_auth_event($pdo, 'otp_failed', $row['telegram_id']);
            admin_fail('Invalid code', 'invalid_code', 401);
        }

        $consume = $pdo->prepare('UPDATE admin_login_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = :id AND consumed_at IS NULL');
        $consume->execute(array(':id' => $locked['id']));
        if ($consume->rowCount() !== 1) {
            $pdo->rollBack();
            admin_fail('Login challenge expired. Start again.', 'challenge_expired', 401);
        }

        $adminRow = array(
            'id' => $row['admin_id'],
            'telegram_id' => $row['telegram_id'],
            'email' => $row['email'],
            'display_name' => $row['display_name'],
            'telegram_username' => $row['telegram_username'],
        );
        $sessionToken = admin_create_session($pdo, $adminRow);
        $pdo->commit();
        admin_set_cookie(ADMIN_SESSION_COOKIE, $sessionToken, (int) $config['session_ttl'], 'Strict');
        admin_clear_cookie(ADMIN_CHALLENGE_COOKIE, 'Lax');
        admin_log_auth_event($pdo, 'login_success', $row['telegram_id']);
        admin_audit($pdo, $adminRow['id'], 'login', 'admin', $adminRow['id'], null);
        admin_respond(array('redirect' => '/admin/dashboard/'), 200);
    } catch (Exception $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('admin verify error: ' . $error->getMessage());
        admin_fail('Login failed. Try again.', 'login_failed', 500);
    }
}

function admin_handle_resend($pdo)
{
    admin_require_method('POST');
    admin_check_origin();
    $challenge = admin_lookup_challenge($pdo);
    if (!$challenge) {
        admin_clear_cookie(ADMIN_CHALLENGE_COOKIE, 'Lax');
        admin_fail('Login challenge expired. Start again.', 'challenge_expired', 401);
    }

    $admin = array(
        'id' => $challenge['row']['admin_id'],
        'telegram_id' => $challenge['row']['telegram_id'],
        'email' => $challenge['row']['email'],
    );
    $created = admin_create_challenge($pdo, $admin, admin_ip_hash());
    if (!$created[0]) {
        admin_fail($created[3], $created[1], $created[2]);
    }
    admin_audit($pdo, $admin['id'], 'login_code_resent', 'admin', $admin['id'], null);
    admin_respond(array(
        'email' => $created[1]['email'],
        'expires_in' => $created[1]['expires_in'],
    ), 200);
}

function admin_handle_logout($pdo)
{
    admin_require_method('POST');
    $session = admin_require_session($pdo);
    admin_require_csrf($session);
    $statement = $pdo->prepare('DELETE FROM admin_sessions WHERE id = :id');
    $statement->execute(array(':id' => $session['row']['session_id']));
    admin_clear_cookie(ADMIN_SESSION_COOKIE, 'Strict');
    admin_audit($pdo, $session['row']['admin_id'], 'logout', 'admin', $session['row']['admin_id'], null);
    admin_respond(array('logged_out' => true), 200);
}

function admin_handle_logout_all($pdo)
{
    admin_require_method('POST');
    $session = admin_require_session($pdo);
    admin_require_csrf($session);
    $statement = $pdo->prepare('DELETE FROM admin_sessions WHERE admin_id = :admin_id');
    $statement->execute(array(':admin_id' => $session['row']['admin_id']));
    admin_clear_cookie(ADMIN_SESSION_COOKIE, 'Strict');
    admin_audit($pdo, $session['row']['admin_id'], 'logout_all', 'admin', $session['row']['admin_id'], null);
    admin_respond(array('logged_out' => true), 200);
}

function admin_handle_upload($pdo)
{
    admin_require_method('POST');
    $session = admin_require_session($pdo);
    admin_require_csrf($session);

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        admin_fail('No file uploaded', 'upload_missing', 422);
    }
    $file = $_FILES['file'];
    if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
        admin_fail('Upload failed', 'upload_failed', 422);
    }
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        admin_fail('Image must be up to 2 MB', 'upload_too_large', 422);
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        admin_fail('Upload failed', 'upload_failed', 422);
    }

    $info = @getimagesize($file['tmp_name']);
    $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    $mime = (is_array($info) && !empty($info['mime'])) ? strtolower((string) $info['mime']) : '';
    if (!isset($allowed[$mime])) {
        admin_fail('Only JPEG, PNG or WebP images are allowed', 'upload_invalid_type', 422);
    }
    $width = isset($info[0]) ? (int) $info[0] : 0;
    $height = isset($info[1]) ? (int) $info[1] : 0;
    if ($width < 16 || $height < 16 || $width > 8000 || $height > 8000) {
        admin_fail('Image dimensions are out of range', 'upload_invalid_type', 422);
    }

    // Double-check the payload with finfo when the extension is available.
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = @finfo_file($finfo, $file['tmp_name']);
            @finfo_close($finfo);
            if (is_string($detected) && strtolower($detected) !== $mime) {
                admin_fail('Image type mismatch', 'upload_invalid_type', 422);
            }
        }
    }

    $dir = dirname(__DIR__) . '/files/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        admin_fail('Upload directory is not writable', 'upload_dir_failed', 500);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        admin_fail('Upload failed', 'upload_failed', 500);
    }
    @chmod($target, 0644);

    admin_audit($pdo, $session['row']['admin_id'], 'upload_created', 'file', null, array('name' => $name, 'bytes' => $size));
    admin_respond(array('url' => '/files/uploads/' . $name, 'bytes' => $size), 201);
}

function admin_handle_session($pdo)
{
    admin_require_method('GET');
    $optional = isset($_GET['optional']) && (string) $_GET['optional'] === '1';
    $session = admin_lookup_session($pdo, true);
    if (!$session) {
        if ($optional) {
            admin_respond(array('authenticated' => false), 200);
        }
        admin_clear_cookie(ADMIN_SESSION_COOKIE, 'Strict');
        admin_fail('Authentication required', 'auth_required', 401);
    }

    admin_respond(array(
        'authenticated' => true,
        'admin' => array(
            'id' => (int) $session['row']['admin_id'],
            'telegram_id' => $session['row']['telegram_id'],
            'email' => $session['row']['email'],
            'display_name' => $session['row']['display_name'],
            'telegram_username' => $session['row']['telegram_username'],
            'role' => $session['row']['role'],
            'last_login_at' => $session['row']['last_login_at'],
        ),
        'csrf_token' => admin_csrf_token($session['token']),
        'expires_at' => $session['row']['expires_at'],
    ), 200);
}

function admin_valid_url($value, $allowEmpty)
{
    $value = trim((string) $value);
    if ($value === '') {
        return $allowEmpty ? true : false;
    }
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

// VARCHAR limits in MySQL are characters, not bytes. Count UTF-8 characters so
// an Uzbek/Russian field is not rejected for exceeding a byte limit it never
// hits in the database. Falls back to preg_match_all when mbstring is absent.
function admin_strlen($value)
{
    $value = (string) $value;
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : (int) $count;
}

function admin_text($value, $max, $allowEmpty)
{
    $value = is_scalar($value) ? trim((string) $value) : '';
    if (!$allowEmpty && $value === '') {
        return null;
    }
    if (admin_strlen($value) > $max) {
        return null;
    }
    return $value;
}

function admin_string_list($input, $maxItems, $maxLength)
{
    if (is_string($input)) {
        $input = preg_split('/\r\n|\r|\n/', $input);
    }
    if (!is_array($input)) {
        $input = array();
    }

    $list = array();
    foreach ($input as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        $item = trim((string) $item);
        if ($item === '') {
            continue;
        }
        if (admin_strlen($item) > $maxLength) {
            return array(null, 'item_too_long');
        }
        $list[] = $item;
    }

    $list = array_values(array_unique($list));
    if (count($list) > $maxItems) {
        return array(null, 'too_many');
    }
    return array($list, '');
}

function admin_profile_data($pdo)
{
    $statement = $pdo->query(
        'SELECT id, name, email, github, telegram, telegram_handle, avatar_url, updated_at
         FROM profiles WHERE id = 1 LIMIT 1'
    );
    $profile = $statement->fetch();
    if (!$profile) {
        admin_fail('Profile not found', 'profile_not_found', 404);
    }

    $translations = array();
    $statement = $pdo->query('SELECT lang, role, bio, quick_text, location FROM profile_translations WHERE profile_id = 1');
    foreach ($statement->fetchAll() as $row) {
        $lang = $row['lang'];
        unset($row['lang']);
        $translations[$lang] = $row;
    }

    $profile['id'] = (int) $profile['id'];
    $profile['translations'] = $translations;
    return $profile;
}

function admin_handle_profile($pdo)
{
    $method = admin_require_method(array('GET', 'PUT'));
    $session = admin_require_session($pdo);

    if ($method === 'GET') {
        admin_respond(admin_profile_data($pdo), 200);
    }

    admin_require_csrf($session);
    $input = admin_json_input();

    $name = admin_text(isset($input['name']) ? $input['name'] : '', 120, false);
    $email = admin_text(isset($input['email']) ? $input['email'] : '', 190, false);
    $github = admin_text(isset($input['github']) ? $input['github'] : '', 190, true);
    $telegram = admin_text(isset($input['telegram']) ? $input['telegram'] : '', 190, true);
    $telegramHandle = admin_text(isset($input['telegram_handle']) ? $input['telegram_handle'] : '', 100, true);
    $avatarUrl = admin_text(isset($input['avatar_url']) ? $input['avatar_url'] : '', 255, true);

    $errors = array();
    if ($name === null) $errors['name'] = 'Name is required (max 120 characters).';
    if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) $errors['email'] = 'A valid email is required.';
    if ($github === null || !admin_valid_url($github, true)) $errors['github'] = 'GitHub must be an http(s) URL.';
    if ($telegram === null || !admin_valid_url($telegram, true)) $errors['telegram'] = 'Telegram must be an http(s) URL.';
    if ($telegramHandle === null) $errors['telegram_handle'] = 'Telegram handle is too long.';
    if ($avatarUrl === null || !admin_valid_url($avatarUrl, true)) $errors['avatar_url'] = 'Avatar must be an http(s) URL.';

    $translations = isset($input['translations']) && is_array($input['translations']) ? $input['translations'] : array();
    foreach (array('uz', 'en', 'ru') as $lang) {
        if (!isset($translations[$lang]) || !is_array($translations[$lang])) {
            $errors['translations.' . $lang] = 'Translation is required.';
            continue;
        }
        $row = $translations[$lang];
        if (admin_text(isset($row['role']) ? $row['role'] : '', 160, false) === null) $errors['translations.' . $lang . '.role'] = 'Role is required.';
        if (admin_text(isset($row['bio']) ? $row['bio'] : '', 5000, false) === null) $errors['translations.' . $lang . '.bio'] = 'Bio is required (max 5000).';
        if (admin_text(isset($row['quick_text']) ? $row['quick_text'] : '', 255, false) === null) $errors['translations.' . $lang . '.quick_text'] = 'Quick text is required (max 255).';
        if (admin_text(isset($row['location']) ? $row['location'] : '', 120, false) === null) $errors['translations.' . $lang . '.location'] = 'Location is required.';
    }

    if ($errors) {
        admin_headers();
        http_response_code(422);
        echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $errors), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'UPDATE profiles SET name = :name, email = :email, github = :github, telegram = :telegram,
                    telegram_handle = :telegram_handle, avatar_url = :avatar_url WHERE id = 1'
        );
        $statement->execute(array(
            ':name' => $name,
            ':email' => $email,
            ':github' => $github,
            ':telegram' => $telegram,
            ':telegram_handle' => $telegramHandle,
            ':avatar_url' => $avatarUrl === '' ? null : $avatarUrl,
        ));

        $upsert = $pdo->prepare(
            'INSERT INTO profile_translations (profile_id, lang, role, bio, quick_text, location)
             VALUES (1, :lang, :role, :bio, :quick_text, :location)
             ON DUPLICATE KEY UPDATE role = VALUES(role), bio = VALUES(bio), quick_text = VALUES(quick_text), location = VALUES(location)'
        );
        foreach (array('uz', 'en', 'ru') as $lang) {
            $row = $translations[$lang];
            $upsert->execute(array(
                ':lang' => $lang,
                ':role' => trim((string) $row['role']),
                ':bio' => trim((string) $row['bio']),
                ':quick_text' => trim((string) $row['quick_text']),
                ':location' => trim((string) $row['location']),
            ));
        }
        $pdo->commit();
        admin_audit($pdo, $session['row']['admin_id'], 'profile_updated', 'profile', 1, null);
        admin_respond(admin_profile_data($pdo), 200);
    } catch (Exception $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('admin profile update error: ' . $error->getMessage());
        admin_fail('Profile could not be saved', 'save_failed', 500);
    }
}

function admin_project_data($pdo, $id)
{
    $statement = $pdo->prepare(
        'SELECT id, slug, category, repo_url, demo_url, image_url, sort_order, is_active, created_at, updated_at
         FROM projects WHERE id = :id LIMIT 1'
    );
    $statement->execute(array(':id' => (int) $id));
    $project = $statement->fetch();
    if (!$project) {
        return null;
    }

    $translations = array();
    $translation = $pdo->prepare('SELECT lang, tag, title, summary, description, tech, results, stack_notes FROM project_translations WHERE project_id = :id');
    $translation->execute(array(':id' => (int) $id));
    foreach ($translation->fetchAll() as $row) {
        $lang = $row['lang'];
        foreach (array('tech', 'results', 'stack_notes') as $field) {
            $decoded = json_decode((string) $row[$field], true);
            $row[$field] = is_array($decoded) ? $decoded : array();
        }
        unset($row['lang']);
        $translations[$lang] = $row;
    }

    $project['id'] = (int) $project['id'];
    $project['sort_order'] = (int) $project['sort_order'];
    $project['is_active'] = (int) $project['is_active'] === 1;
    $project['translations'] = $translations;
    return $project;
}

function admin_project_list($pdo)
{
    $statement = $pdo->query('SELECT id FROM projects ORDER BY sort_order ASC, id ASC');
    $items = array();
    foreach ($statement->fetchAll() as $row) {
        $project = admin_project_data($pdo, $row['id']);
        if ($project) $items[] = $project;
    }
    return $items;
}

function admin_validate_project($input)
{
    $errors = array();
    $slug = admin_text(isset($input['slug']) ? $input['slug'] : '', 80, false);
    if ($slug === null || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $errors['slug'] = 'Slug must be lowercase letters, numbers and hyphens.';
    } elseif ($slug === 'view') {
        $errors['slug'] = 'Slug is reserved for the detail fallback route.';
    }
    $category = admin_text(isset($input['category']) ? $input['category'] : '', 30, false);
    if ($category === null || !preg_match('/^[a-z0-9-]+$/', $category)) {
        $errors['category'] = 'Category must use lowercase letters, numbers and hyphens.';
    }
    $repoUrl = admin_text(isset($input['repo_url']) ? $input['repo_url'] : '', 255, true);
    if ($repoUrl === null || !admin_valid_url($repoUrl, true)) $errors['repo_url'] = 'Repository URL is invalid.';
    $demoUrl = admin_text(isset($input['demo_url']) ? $input['demo_url'] : '', 255, true);
    if ($demoUrl === null || !admin_valid_url($demoUrl, true)) $errors['demo_url'] = 'Demo URL is invalid.';
    $imageUrl = admin_text(isset($input['image_url']) ? $input['image_url'] : '', 255, true);
    if ($imageUrl === null || !admin_valid_url($imageUrl, true)) $errors['image_url'] = 'Image URL is invalid.';
    $sortOrder = isset($input['sort_order']) ? (int) $input['sort_order'] : 0;
    if ($sortOrder < -32768 || $sortOrder > 32767) $errors['sort_order'] = 'Sort order is out of range.';
    $isActive = !empty($input['is_active']) && (string) $input['is_active'] !== '0';

    $translations = isset($input['translations']) && is_array($input['translations']) ? $input['translations'] : array();
    $cleanTranslations = array();
    foreach (array('uz', 'en', 'ru') as $lang) {
        if (!isset($translations[$lang]) || !is_array($translations[$lang])) {
            $errors['translations.' . $lang] = 'Translation is required.';
            continue;
        }
        $row = $translations[$lang];
        $tag = admin_text(isset($row['tag']) ? $row['tag'] : '', 40, false);
        $title = admin_text(isset($row['title']) ? $row['title'] : '', 160, false);
        $summary = admin_text(isset($row['summary']) ? $row['summary'] : '', 2000, false);
        $description = admin_text(isset($row['description']) ? $row['description'] : '', 20000, true);
        if ($tag === null) $errors['translations.' . $lang . '.tag'] = 'Tag is required.';
        if ($title === null) $errors['translations.' . $lang . '.title'] = 'Title is required.';
        if ($summary === null) $errors['translations.' . $lang . '.summary'] = 'Summary is required.';
        if ($description === null) $errors['translations.' . $lang . '.description'] = 'Description is too long.';

        $techInput = isset($row['tech']) ? $row['tech'] : array();
        if (is_string($techInput)) {
            $techInput = explode(',', $techInput);
        }
        $techResult = admin_string_list($techInput, 12, 40);
        $tech = $techResult[0] === null ? array() : $techResult[0];
        if ($techResult[1] === 'item_too_long') {
            $errors['translations.' . $lang . '.tech'] = 'Each technology must be max 40 characters.';
        } elseif ($techResult[1] === 'too_many') {
            $errors['translations.' . $lang . '.tech'] = 'Maximum 12 technologies.';
        }
        if (!$tech) $errors['translations.' . $lang . '.tech'] = 'At least one technology is required.';

        $resultsResult = admin_string_list(isset($row['results']) ? $row['results'] : array(), 20, 300);
        $stackResult = admin_string_list(isset($row['stack_notes']) ? $row['stack_notes'] : array(), 20, 300);
        if ($resultsResult[1] === 'item_too_long') {
            $errors['translations.' . $lang . '.results'] = 'Each result must be max 300 characters.';
        } elseif ($resultsResult[1] === 'too_many') {
            $errors['translations.' . $lang . '.results'] = 'Maximum 20 results.';
        }
        if ($stackResult[1] === 'item_too_long') {
            $errors['translations.' . $lang . '.stack_notes'] = 'Each stack note must be max 300 characters.';
        } elseif ($stackResult[1] === 'too_many') {
            $errors['translations.' . $lang . '.stack_notes'] = 'Maximum 20 stack notes.';
        }

        $cleanTranslations[$lang] = array(
            'tag' => $tag === null ? '' : $tag,
            'title' => $title === null ? '' : $title,
            'summary' => $summary === null ? '' : $summary,
            'description' => $description === null ? '' : $description,
            'tech' => $tech,
            'results' => $resultsResult[0] === null ? array() : $resultsResult[0],
            'stack_notes' => $stackResult[0] === null ? array() : $stackResult[0],
        );
    }

    if ($errors) {
        return array(null, $errors);
    }

    return array(array(
        'slug' => $slug,
        'category' => $category,
        'repo_url' => $repoUrl === '' ? null : $repoUrl,
        'demo_url' => $demoUrl === '' ? null : $demoUrl,
        'image_url' => $imageUrl === '' ? null : $imageUrl,
        'sort_order' => $sortOrder,
        'is_active' => $isActive ? 1 : 0,
        'translations' => $cleanTranslations,
    ), array());
}

function admin_handle_projects($pdo)
{
    $method = admin_require_method(array('GET', 'POST'));
    $session = admin_require_session($pdo);
    if ($method === 'GET') {
        admin_respond(admin_project_list($pdo), 200);
    }

    admin_require_csrf($session);
    $input = admin_json_input();
    $validated = admin_validate_project($input);
    if ($validated[1]) {
        admin_headers();
        http_response_code(422);
        echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $validated[1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    $project = $validated[0];

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO projects (slug, category, repo_url, demo_url, image_url, sort_order, is_active)
             VALUES (:slug, :category, :repo_url, :demo_url, :image_url, :sort_order, :is_active)'
        );
        $statement->execute(array(
            ':slug' => $project['slug'],
            ':category' => $project['category'],
            ':repo_url' => $project['repo_url'],
            ':demo_url' => $project['demo_url'],
            ':image_url' => $project['image_url'],
            ':sort_order' => $project['sort_order'],
            ':is_active' => $project['is_active'],
        ));
        $id = (int) $pdo->lastInsertId();
        $translation = $pdo->prepare(
            'INSERT INTO project_translations (project_id, lang, tag, title, summary, description, tech, results, stack_notes)
             VALUES (:project_id, :lang, :tag, :title, :summary, :description, :tech, :results, :stack_notes)'
        );
        foreach ($project['translations'] as $lang => $row) {
            $translation->execute(array(
                ':project_id' => $id,
                ':lang' => $lang,
                ':tag' => $row['tag'],
                ':title' => $row['title'],
                ':summary' => $row['summary'],
                ':description' => $row['description'] === '' ? null : $row['description'],
                ':tech' => json_encode($row['tech'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':results' => json_encode($row['results'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':stack_notes' => json_encode($row['stack_notes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
        }
        $pdo->commit();
        admin_audit($pdo, $session['row']['admin_id'], 'project_created', 'project', $id, array('slug' => $project['slug']));
        admin_try_write_sitemap($pdo);
        admin_respond(admin_project_data($pdo, $id), 201);
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error->getCode() === '23000') {
            admin_fail('Slug already exists', 'slug_exists', 409);
        }
        error_log('admin project create error: ' . $error->getMessage());
        admin_fail('Project could not be created', 'save_failed', 500);
    } catch (Exception $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('admin project create error: ' . $error->getMessage());
        admin_fail('Project could not be created', 'save_failed', 500);
    }
}

function admin_handle_project_item($pdo, $id)
{
    $method = admin_require_method(array('GET', 'PUT', 'DELETE'));
    $session = admin_require_session($pdo);
    $project = admin_project_data($pdo, $id);
    if (!$project) {
        admin_fail('Project not found', 'project_not_found', 404);
    }

    if ($method === 'GET') {
        admin_respond($project, 200);
    }

    admin_require_csrf($session);

    if ($method === 'DELETE') {
        $statement = $pdo->prepare('DELETE FROM projects WHERE id = :id');
        $statement->execute(array(':id' => (int) $id));
        admin_audit($pdo, $session['row']['admin_id'], 'project_deleted', 'project', $id, array('slug' => $project['slug']));
        admin_try_write_sitemap($pdo);
        admin_respond(array('deleted' => true, 'id' => (int) $id), 200);
    }

    $input = admin_json_input();
    $validated = admin_validate_project($input);
    if ($validated[1]) {
        admin_headers();
        http_response_code(422);
        echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $validated[1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    $clean = $validated[0];

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'UPDATE projects SET slug = :slug, category = :category, repo_url = :repo_url,
                    demo_url = :demo_url, image_url = :image_url, sort_order = :sort_order, is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute(array(
            ':slug' => $clean['slug'],
            ':category' => $clean['category'],
            ':repo_url' => $clean['repo_url'],
            ':demo_url' => $clean['demo_url'],
            ':image_url' => $clean['image_url'],
            ':sort_order' => $clean['sort_order'],
            ':is_active' => $clean['is_active'],
            ':id' => (int) $id,
        ));

        $translation = $pdo->prepare(
            'INSERT INTO project_translations (project_id, lang, tag, title, summary, description, tech, results, stack_notes)
             VALUES (:project_id, :lang, :tag, :title, :summary, :description, :tech, :results, :stack_notes)
             ON DUPLICATE KEY UPDATE tag = VALUES(tag), title = VALUES(title), summary = VALUES(summary),
                                     description = VALUES(description), tech = VALUES(tech),
                                     results = VALUES(results), stack_notes = VALUES(stack_notes)'
        );
        foreach ($clean['translations'] as $lang => $row) {
            $translation->execute(array(
                ':project_id' => (int) $id,
                ':lang' => $lang,
                ':tag' => $row['tag'],
                ':title' => $row['title'],
                ':summary' => $row['summary'],
                ':description' => $row['description'] === '' ? null : $row['description'],
                ':tech' => json_encode($row['tech'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':results' => json_encode($row['results'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':stack_notes' => json_encode($row['stack_notes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
        }
        $pdo->commit();
        admin_audit($pdo, $session['row']['admin_id'], 'project_updated', 'project', $id, array('slug' => $clean['slug']));
        admin_try_write_sitemap($pdo);
        admin_respond(admin_project_data($pdo, $id), 200);
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error->getCode() === '23000') {
            admin_fail('Slug already exists', 'slug_exists', 409);
        }
        error_log('admin project update error: ' . $error->getMessage());
        admin_fail('Project could not be saved', 'save_failed', 500);
    } catch (Exception $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('admin project update error: ' . $error->getMessage());
        admin_fail('Project could not be saved', 'save_failed', 500);
    }
}

function admin_message_data($pdo, $id)
{
    $statement = $pdo->prepare(
        'SELECT id, form_id, locale, name, email, subject, message, payload, is_read, created_at
         FROM contact_messages WHERE id = :id LIMIT 1'
    );
    $statement->execute(array(':id' => (int) $id));
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['is_read'] = (int) $row['is_read'] === 1;
    $decoded = json_decode((string) $row['payload'], true);
    $row['payload'] = is_array($decoded) ? $decoded : array();
    return $row;
}

function admin_handle_messages($pdo)
{
    admin_require_method('GET');
    admin_require_session($pdo);

    $unread = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
    if (isset($_GET['count']) && (string) $_GET['count'] === '1') {
        admin_respond(array('unread' => $unread, 'total' => $total), 200);
    }

    $where = '';
    if (isset($_GET['unread']) && (string) $_GET['unread'] === '1') {
        $where = ' WHERE is_read = 0';
    }
    $statement = $pdo->query(
        'SELECT id, form_id, locale, name, email, subject, is_read, created_at,
                LEFT(message, 280) AS preview
         FROM contact_messages' . $where . '
         ORDER BY created_at DESC, id DESC
         LIMIT 200'
    );
    $items = array();
    foreach ($statement->fetchAll() as $row) {
        $row['id'] = (int) $row['id'];
        $row['is_read'] = (int) $row['is_read'] === 1;
        $items[] = $row;
    }

    admin_respond(array('items' => $items, 'unread' => $unread, 'total' => $total), 200);
}

function admin_handle_message_item($pdo, $id)
{
    $method = admin_require_method(array('GET', 'PUT', 'DELETE'));
    $session = admin_require_session($pdo);
    $message = admin_message_data($pdo, $id);
    if (!$message) {
        admin_fail('Message not found', 'message_not_found', 404);
    }

    if ($method === 'GET') {
        admin_respond($message, 200);
    }

    admin_require_csrf($session);

    if ($method === 'DELETE') {
        $statement = $pdo->prepare('DELETE FROM contact_messages WHERE id = :id');
        $statement->execute(array(':id' => (int) $id));
        admin_audit($pdo, $session['row']['admin_id'], 'message_deleted', 'contact_message', $id, null);
        admin_respond(array('deleted' => true, 'id' => (int) $id), 200);
    }

    $input = admin_json_input();
    if (!array_key_exists('is_read', $input)) {
        admin_fail('is_read is required', 'validation_failed', 422);
    }
    $isRead = !empty($input['is_read']) && (string) $input['is_read'] !== '0' ? 1 : 0;
    $statement = $pdo->prepare('UPDATE contact_messages SET is_read = :is_read WHERE id = :id');
    $statement->execute(array(':is_read' => $isRead, ':id' => (int) $id));
    admin_audit($pdo, $session['row']['admin_id'], $isRead ? 'message_read' : 'message_unread', 'contact_message', $id, null);
    admin_respond(admin_message_data($pdo, $id), 200);
}

function admin_sitemap_urls($pdo)
{
    $config = admin_config();
    $siteUrl = rtrim($config['site_url'], '/');
    $today = gmdate('Y-m-d');
    $urls = array();

    foreach (array('uz', 'en', 'ru') as $locale) {
        $urls[] = array(
            'loc' => $siteUrl . '/' . $locale . '/',
            'lastmod' => $today,
            'changefreq' => 'monthly',
            'priority' => '1.0',
        );
        $urls[] = array(
            'loc' => $siteUrl . '/' . $locale . '/projects/',
            'lastmod' => $today,
            'changefreq' => 'monthly',
            'priority' => '0.8',
        );
    }

    $statement = $pdo->query('SELECT slug, updated_at FROM projects WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    foreach ($statement->fetchAll() as $project) {
        $slug = (string) $project['slug'];
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            continue;
        }
        $lastmod = substr((string) $project['updated_at'], 0, 10);
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $lastmod)) {
            $lastmod = $today;
        }
        foreach (array('uz', 'en', 'ru') as $locale) {
            $urls[] = array(
                'loc' => $siteUrl . '/' . $locale . '/projects/' . $slug . '/',
                'lastmod' => $lastmod,
                'changefreq' => 'monthly',
                'priority' => '0.7',
            );
        }
    }

    return $urls;
}

function admin_write_sitemap($pdo)
{
    $urls = admin_sitemap_urls($pdo);
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
        $xml .= '    <changefreq>' . htmlspecialchars($url['changefreq'], ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
        $xml .= '    <priority>' . htmlspecialchars($url['priority'], ENT_QUOTES, 'UTF-8') . "</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";

    $path = dirname(__DIR__) . '/sitemap.xml';
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $bytes = file_put_contents($tmp, $xml, LOCK_EX);
    if ($bytes === false) {
        @unlink($tmp);
        throw new RuntimeException('Sitemap is not writable');
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Sitemap could not be replaced');
    }
    @chmod($path, 0644);
    return count($urls);
}

function admin_try_write_sitemap($pdo)
{
    try {
        admin_write_sitemap($pdo);
        return true;
    } catch (Exception $error) {
        error_log('admin sitemap error: ' . $error->getMessage());
        return false;
    }
}

function admin_handle_sitemap($pdo)
{
    admin_require_method('POST');
    $session = admin_require_session($pdo);
    admin_require_csrf($session);
    try {
        $count = admin_write_sitemap($pdo);
    } catch (Exception $error) {
        error_log('admin sitemap error: ' . $error->getMessage());
        admin_fail('Sitemap could not be written. Check file permissions.', 'sitemap_write_failed', 500);
    }
    admin_audit($pdo, $session['row']['admin_id'], 'sitemap_generated', 'sitemap', null, array('urls' => $count));
    admin_respond(array('updated' => true, 'urls' => $count), 200);
}

function admin_admin_data($row)
{
    $row['id'] = (int) $row['id'];
    $row['telegram_id'] = (string) $row['telegram_id'];
    $row['is_active'] = (int) $row['is_active'] === 1;
    return $row;
}

function admin_validate_admin($input)
{
    $errors = array();
    $telegramId = admin_text(isset($input['telegram_id']) ? $input['telegram_id'] : '', 20, false);
    if ($telegramId === null || !preg_match('/^[0-9]{5,19}$/', $telegramId)) {
        $errors['telegram_id'] = 'Telegram ID must contain 5-19 digits.';
    }
    $email = admin_text(isset($input['email']) ? $input['email'] : '', 190, false);
    if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'A valid email is required.';
    }
    $displayName = admin_text(isset($input['display_name']) ? $input['display_name'] : '', 120, true);
    if ($displayName === null) {
        $errors['display_name'] = 'Display name is too long.';
    }
    $isActive = !empty($input['is_active']) && (string) $input['is_active'] !== '0';
    $role = admin_text(isset($input['role']) ? $input['role'] : 'admin', 20, false);
    if ($role === null || !in_array($role, array('super', 'admin'), true)) {
        $errors['role'] = 'Role super yoki admin bo‘lishi kerak.';
    }

    if ($errors) {
        return array(null, $errors);
    }
    return array(array(
        'telegram_id' => $telegramId,
        'email' => $email,
        'display_name' => $displayName === '' ? null : $displayName,
        'is_active' => $isActive ? 1 : 0,
        'role' => $role,
    ), array());
}

function admin_active_count($pdo, $exceptId)
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE is_active = 1 AND id <> :id');
    $statement->execute(array(':id' => (int) $exceptId));
    return (int) $statement->fetchColumn();
}

function admin_handle_admins($pdo)
{
    $method = admin_require_method(array('GET', 'POST'));
    $session = admin_require_session($pdo);
    admin_require_super($session);
    if ($method === 'GET') {
        $statement = $pdo->query(
            'SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at
             FROM admin_users ORDER BY id ASC'
        );
        $items = array();
        foreach ($statement->fetchAll() as $row) {
            $items[] = admin_admin_data($row);
        }
        admin_respond($items, 200);
    }

    admin_require_csrf($session);
    $validated = admin_validate_admin(admin_json_input());
    if ($validated[1]) {
        admin_headers();
        http_response_code(422);
        echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $validated[1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    $admin = $validated[0];

    try {
        $statement = $pdo->prepare(
            'INSERT INTO admin_users (telegram_id, email, display_name, is_active, role)
             VALUES (:telegram_id, :email, :display_name, :is_active, :role)'
        );
        $statement->execute(array(
            ':telegram_id' => $admin['telegram_id'],
            ':email' => $admin['email'],
            ':display_name' => $admin['display_name'],
            ':is_active' => $admin['is_active'],
            ':role' => $admin['role'],
        ));
        $id = (int) $pdo->lastInsertId();
        admin_audit($pdo, $session['row']['admin_id'], 'admin_created', 'admin_user', $id, array(
            'telegram_id' => $admin['telegram_id'],
            'role' => $admin['role'],
        ));
        $row = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at FROM admin_users WHERE id = :id');
        $row->execute(array(':id' => $id));
        admin_respond(admin_admin_data($row->fetch()), 201);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            admin_fail('This Telegram ID is already an admin', 'admin_exists', 409);
        }
        error_log('admin create error: ' . $error->getMessage());
        admin_fail('Admin could not be created', 'save_failed', 500);
    } catch (Exception $error) {
        error_log('admin create error: ' . $error->getMessage());
        admin_fail('Admin could not be created', 'save_failed', 500);
    }
}

function admin_handle_admin_item($pdo, $id)
{
    $method = admin_require_method(array('GET', 'PUT', 'DELETE'));
    $session = admin_require_session($pdo);
    admin_require_super($session);
    $statement = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at FROM admin_users WHERE id = :id LIMIT 1');
    $statement->execute(array(':id' => (int) $id));
    $row = $statement->fetch();
    if (!$row) {
        admin_fail('Admin not found', 'admin_not_found', 404);
    }
    $current = admin_admin_data($row);

    if ($method === 'GET') {
        admin_respond($current, 200);
    }

    admin_require_csrf($session);
    if ($method === 'DELETE') {
        if ((int) $id === (int) $session['row']['admin_id']) {
            admin_fail('You cannot delete your own account', 'cannot_delete_self', 422);
        }
        if ($current['is_active'] && admin_active_count($pdo, $id) === 0) {
            admin_fail('At least one active admin is required', 'last_admin', 422);
        }
        if ($current['role'] === 'super' && !admin_has_other_active_super($pdo, $id)) {
            admin_fail('At least one active super admin is required', 'last_super_admin', 422);
        }
        $delete = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
        $delete->execute(array(':id' => (int) $id));
        admin_audit($pdo, $session['row']['admin_id'], 'admin_deleted', 'admin_user', $id, array(
            'telegram_id' => $current['telegram_id'],
            'role' => $current['role'],
        ));
        admin_respond(array('deleted' => true, 'id' => (int) $id), 200);
    }

    $validated = admin_validate_admin(admin_json_input());
    if ($validated[1]) {
        admin_headers();
        http_response_code(422);
        echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $validated[1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    $admin = $validated[0];
    if ((int) $id === (int) $session['row']['admin_id'] && !$admin['is_active']) {
        admin_fail('You cannot deactivate your own account', 'cannot_deactivate_self', 422);
    }
    if (!$admin['is_active'] && $current['is_active'] && admin_active_count($pdo, $id) === 0) {
        admin_fail('At least one active admin is required', 'last_admin', 422);
    }
    if ($current['role'] === 'super'
        && (!$admin['is_active'] || $admin['role'] !== 'super')
        && !admin_has_other_active_super($pdo, $id)) {
        admin_fail('At least one active super admin is required', 'last_super_admin', 422);
    }

    try {
        $update = $pdo->prepare(
            'UPDATE admin_users SET telegram_id = :telegram_id, email = :email, display_name = :display_name, is_active = :is_active, role = :role WHERE id = :id'
        );
        $update->execute(array(
            ':telegram_id' => $admin['telegram_id'],
            ':email' => $admin['email'],
            ':display_name' => $admin['display_name'],
            ':is_active' => $admin['is_active'],
            ':role' => $admin['role'],
            ':id' => (int) $id,
        ));
        admin_audit($pdo, $session['row']['admin_id'], 'admin_updated', 'admin_user', $id, array(
            'telegram_id' => $admin['telegram_id'],
            'is_active' => $admin['is_active'],
            'role' => $admin['role'],
        ));
        $fresh = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at FROM admin_users WHERE id = :id');
        $fresh->execute(array(':id' => (int) $id));
        admin_respond(admin_admin_data($fresh->fetch()), 200);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            admin_fail('This Telegram ID is already an admin', 'admin_exists', 409);
        }
        error_log('admin update error: ' . $error->getMessage());
        admin_fail('Admin could not be saved', 'save_failed', 500);
    } catch (Exception $error) {
        error_log('admin update error: ' . $error->getMessage());
        admin_fail('Admin could not be saved', 'save_failed', 500);
    }
}

if ($requestPath === '/admin/config') {
    admin_handle_config();
}

try {
    $pdo = content_db();
} catch (Exception $error) {
    error_log('admin database error: ' . $error->getMessage());
    admin_fail('Database unavailable', 'db_unavailable', 503);
}

try {
    if ($requestPath === '/admin/auth/telegram') {
        admin_handle_telegram_callback($pdo);
    } elseif ($requestPath === '/admin/auth/status') {
        admin_handle_status($pdo);
    } elseif ($requestPath === '/admin/auth/verify') {
        admin_handle_verify($pdo);
    } elseif ($requestPath === '/admin/auth/resend') {
        admin_handle_resend($pdo);
    } elseif ($requestPath === '/admin/session') {
        admin_handle_session($pdo);
    } elseif ($requestPath === '/admin/logout') {
        admin_handle_logout($pdo);
    } elseif ($requestPath === '/admin/logout-all') {
        admin_handle_logout_all($pdo);
    } elseif ($requestPath === '/admin/uploads') {
        admin_handle_upload($pdo);
    } elseif ($requestPath === '/admin/profile') {
        admin_handle_profile($pdo);
    } elseif ($requestPath === '/admin/projects') {
        admin_handle_projects($pdo);
    } elseif (preg_match('#^/admin/projects/([0-9]{1,10})/?$#', $requestPath, $matches)) {
        admin_handle_project_item($pdo, (int) $matches[1]);
    } elseif ($requestPath === '/admin/messages') {
        admin_handle_messages($pdo);
    } elseif (preg_match('#^/admin/messages/([0-9]{1,10})/?$#', $requestPath, $matches)) {
        admin_handle_message_item($pdo, (int) $matches[1]);
    } elseif ($requestPath === '/admin/sitemap') {
        admin_handle_sitemap($pdo);
    } elseif ($requestPath === '/admin/admins') {
        admin_handle_admins($pdo);
    } elseif (preg_match('#^/admin/admins/([0-9]{1,10})/?$#', $requestPath, $matches)) {
        admin_handle_admin_item($pdo, (int) $matches[1]);
    } else {
        admin_fail('Not found', 'not_found', 404);
    }
} catch (Exception $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('admin api error: ' . $error->getMessage());
    admin_fail('Admin request failed', 'admin_error', 500);
}
