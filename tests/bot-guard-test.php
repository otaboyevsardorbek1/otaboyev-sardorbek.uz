<?php
// Bot guard unit tests. Uses a real test DB; does NOT hit Telegram.
//
// Run locally (TCP database):
//   DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=test_portfolio DB_USER=root DB_PASS='' \
//     php tests/bot-guard-test.php
//
// Run locally (socket database):
//   DB_SOCKET=/tmp/mysql.sock DB_NAME=test_portfolio DB_USER=root DB_PASS='' \
//     php tests/bot-guard-test.php
//
// Requires:
//   - database/schema.sql already imported (admin_users.role exists)
//   - database/migrate-bot-guards.sql applied
//   - php extensions: pdo_mysql
//
// Exit code 0 = all pass; 1 = at least one failure.

error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$socket = getenv('DB_SOCKET');
$name = getenv('DB_NAME') ?: 'test_portfolio';
$user = getenv('DB_USER') ?: 'test';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'test';

putenv("DB_HOST=$host");
putenv("DB_PORT=$port");
putenv('DB_SOCKET=' . ($socket === false ? '' : $socket));
putenv("DB_NAME=$name");
putenv("DB_USER=$user");
putenv("DB_PASS=$pass");

// Minimal admin_config() inputs so backend/bot.php can require config.php
// without failing the `enabled` check. A non-empty fake token is required:
// an empty env value falls back to config.local.php. The fake token only
// reaches api.telegram.org with an invalid bot id, so no real message can be
// delivered and the tests stay independent of production credentials.
putenv('APP_ENV=local');
putenv('APP_SECRET=' . str_repeat('a', 64));
putenv('TELEGRAM_BOT_TOKEN=123456789:AAFakeTokenFakeTokenFakeTokenFakeToken');
putenv('TELEGRAM_BOT_USERNAME=test_bot');
putenv('ADMIN_TELEGRAM_ID=123456789');
putenv('ADMIN_EMAIL=test@example.com');
putenv('ADMIN_MAIL_DRIVER=log');
putenv('ADMIN_MAIL_LOG=/tmp/test-mail.log');
putenv('ADMIN_MAIL_FROM=no-reply@example.com');

require_once __DIR__ . '/../backend/bot.php';

$failures = 0;
$total = 0;

function check($label, $ok)
{
    global $failures, $total;
    $total++;
    if ($ok) {
        echo "PASS: $label\n";
    } else {
        echo "FAIL: $label\n";
        $failures++;
    }
}

echo "== Pure helpers ==\n";

check('bot_clean_header strips CR/LF',
    bot_clean_header("a\r\nb\0c", 100) === 'abc');

check('bot_clean_body keeps newlines',
    bot_clean_body("line1\nline2", 100) === "line1\nline2");

$ctrl = bot_clean_body("a\x01b\x02c", 100);
check('bot_clean_body strips control chars',
    strpos($ctrl, "\x01") === false && strpos($ctrl, "\x02") === false && strpos($ctrl, 'abc') !== false);

echo "\n== Database setup ==\n";

try {
    $pdo = content_db();
} catch (Exception $e) {
    echo "FAIL: cannot connect to DB: " . $e->getMessage() . "\n";
    exit(1);
}

$pdo->exec('DELETE FROM bot_updates_seen');
$pdo->exec('DELETE FROM bot_rate_limit');
$pdo->exec('DELETE FROM admin_audit_log');
$pdo->exec('DELETE FROM admin_sessions');
$pdo->exec('DELETE FROM admin_login_challenges');
$pdo->exec('DELETE FROM admin_users');

echo "DB reset OK\n\n== bot_find_admin ==\n";

check('unknown telegram_id -> null',
    bot_find_admin('111111') === null);

$pdo->exec("INSERT INTO admin_users (telegram_id, email, display_name, is_active, role) VALUES (222222, 'inactive@example.com', 'Inactive', 0, 'admin')");
check('inactive admin -> null',
    bot_find_admin('222222') === null);

$pdo->exec("INSERT INTO admin_users (telegram_id, email, display_name, is_active, role) VALUES (333333, 'admin@example.com', 'Admin', 1, 'admin')");
$admin = bot_find_admin('333333');
check('active admin -> row',
    is_array($admin) && (int) $admin['telegram_id'] === 333333 && $admin['role'] === 'admin');

$pdo->exec("INSERT INTO admin_users (telegram_id, email, display_name, is_active, role) VALUES (444444, 'super@example.com', 'Super', 1, 'super')");
$super = bot_find_admin('444444');
check('active super -> row (role is not a bot gate)',
    is_array($super) && (int) $super['telegram_id'] === 444444 && $super['role'] === 'super');

echo "\n== bot_rate_limit_ok (per telegram_id) ==\n";

$pdo->exec('DELETE FROM bot_rate_limit');

check('rate 1/3 allowed', bot_rate_limit_ok(333333, 'selftest', 3) === true);
check('rate 2/3 allowed', bot_rate_limit_ok(333333, 'selftest', 3) === true);
check('rate 3/3 allowed', bot_rate_limit_ok(333333, 'selftest', 3) === true);
check('rate 4/3 blocked', bot_rate_limit_ok(333333, 'selftest', 3) === false);
check('rate limit isolated per telegram_id',
    bot_rate_limit_ok(444444, 'selftest', 3) === true);

echo "\n== bot_is_duplicate_update ==\n";

$pdo->exec('DELETE FROM bot_updates_seen');
check('first update_id not duplicate', bot_is_duplicate_update(1001) === false);
check('same update_id detected as duplicate', bot_is_duplicate_update(1001) === true);
check('new update_id not duplicate', bot_is_duplicate_update(1002) === false);

$seenCount = (int) $pdo->query('SELECT COUNT(*) FROM bot_updates_seen')->fetchColumn();
check('bot_updates_seen has exactly 2 rows', $seenCount === 2);

echo "\n== bot_audit ==\n";

$adminId = (int) $admin['id'];
bot_audit(array('id' => $adminId, 'telegram_id' => '333333'), 'bot_test_action', array('k' => 'v'));

$row = $pdo->query("SELECT admin_id, action, entity, details FROM admin_audit_log ORDER BY id DESC LIMIT 1")->fetch();
check('bot_audit writes admin_id',   $row && (int) $row['admin_id'] === $adminId);
check('bot_audit writes action',     $row && $row['action'] === 'bot_test_action');
check('bot_audit writes entity=bot', $row && $row['entity'] === 'bot');
check('bot_audit writes details JSON', $row && json_decode($row['details'], true) === array('k' => 'v'));

echo "\n== bot_notify_admins (best-effort, fake token) ==\n";

// The fake token cannot deliver anything, so bot_api() fails. This proves the
// broadcast is best-effort: it returns an int, never throws, and a failing
// transport does not break the caller (the contact form).
$sent = bot_notify_admins('test');
check('bot_notify_admins returns int and does not throw', is_int($sent) && $sent >= 0);

echo "\n== Bot config sanity ==\n";

$config = bot_config();
check('webhook_secret is a string',      is_string($config['webhook_secret']));
check('imap_ssl is bool',                is_bool($config['imap_ssl']));
check('admin_id is numeric string',      ctype_digit((string) $config['admin_id']));

echo "\n";
if ($failures === 0) {
    echo "ALL {$total} TESTS PASSED\n";
    exit(0);
}
echo "{$failures} OF {$total} TESTS FAILED\n";
exit(1);
