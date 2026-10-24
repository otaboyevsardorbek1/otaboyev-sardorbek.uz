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
require_once __DIR__ . '/../backend/contact.php';

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

echo "\n== UTF-8 length helpers ==\n";

check('contact_strlen counts cyrillic characters', contact_strlen('Привет') === 6);
check('contact_strlen counts an emoji as one character', contact_strlen('🙂') === 1);
check('contact_limit_chars keeps 160 cyrillic characters',
    contact_limit_chars(str_repeat('А', 160), 160) === str_repeat('А', 160));
check('contact_limit_chars trims beyond the character limit',
    contact_strlen(contact_limit_chars(str_repeat('А', 170), 160)) === 160);

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
$pdo->exec('DELETE FROM contact_messages');

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

echo "\n== bot_health (no secrets) ==\n";

$health = bot_health();
check('bot_health returns array', is_array($health));
foreach (array('configured', 'webhook_secret', 'imap', 'database') as $key) {
    check('bot_health ' . $key . ' is bool', isset($health[$key]) && is_bool($health[$key]));
}
check('bot_health reports database reachable', $health['database'] === true);
check('bot_health exposes only boolean flags',
    array_keys($health) === array('configured', 'webhook_secret', 'imap', 'database')
    && count(array_filter($health, 'is_bool')) === 4);

echo "\n== bot_alert_admin (best-effort) ==\n";

$alerted = bot_alert_admin('guard test alert');
check('bot_alert_admin returns bool and does not throw', is_bool($alerted));

echo "\n== bot_notify_admins (best-effort, fake token) ==\n";

// The fake token cannot deliver anything, so bot_api() fails. This proves the
// broadcast is best-effort: it returns an int, never throws, and a failing
// transport does not break the caller (the contact form).
$sent = bot_notify_admins('test');
check('bot_notify_admins returns int and does not throw', is_int($sent) && $sent >= 0);

echo "\n== admin_mail_send (shared delivery path) ==\n";

$mailLog = '/tmp/test-mail.log';
@unlink($mailLog);
$mailResult = admin_mail_send('dest@example.com', 'Guard test', 'plain body', '<p>html</p>', 'reply@example.com');
check('admin_mail_send returns [bool, string]',
    is_array($mailResult) && count($mailResult) === 2 && is_bool($mailResult[0]) && is_string($mailResult[1]));
check('admin_mail_send accepted the message', $mailResult[0] === true);
$logged = (string) @file_get_contents($mailLog);
check('log driver wrote recipient, subject and body',
    strpos($logged, 'to=dest@example.com') !== false
    && strpos($logged, 'subject=Guard test') !== false
    && strpos($logged, 'plain body') !== false);

$badRecipient = admin_mail_send('not-an-email', 'Guard test', 'body', '<p>body</p>');
check('admin_mail_send rejects an invalid recipient', $badRecipient[0] === false);

// OTP keeps the local-only `code=` marker for the log driver.
@unlink($mailLog);
$otp = admin_send_login_code('dest@example.com', '123456', 10);
check('OTP send accepted', $otp[0] === true);
$otpLog = (string) @file_get_contents($mailLog);
check('OTP log line keeps code= marker', strpos($otpLog, 'code=123456') !== false);

// bot_send_email goes through the same mailer, so a log-driver install records
// /reply and /send messages instead of silently falling back to mail().
@unlink($mailLog);
$botMail = bot_send_email('dest2@example.com', 'Bot subject', 'Bot body', 'reply2@example.com');
check('bot_send_email accepted', $botMail[0] === true);
$botLogged = (string) @file_get_contents($mailLog);
check('bot_send_email wrote through the shared mailer',
    strpos($botLogged, 'to=dest2@example.com') !== false
    && strpos($botLogged, 'subject=Bot subject') !== false
    && strpos($botLogged, 'Bot body') !== false);
check('bot_send_email rejects an invalid recipient',
    bot_send_email('not-an-email', 's', 'b')[0] === false);

echo "\n== contact_native_submit (UTF-8 characters) ==\n";

$_POST = array('lang' => 'ru');
$cyrillicName = str_repeat('А', 100); // 200 bytes, 100 characters
list($submitOk, , $submitCode) = contact_native_submit(array(
    'ed-f-1' => $cyrillicName,
    'ed-f-2' => 'utf8@example.com',
    'ed-f-3' => 'Тема сообщения',
    'ed-f-4' => 'Текст сообщения',
));
check('100-character cyrillic name accepted', $submitOk === true && $submitCode === 200);
$storedName = $pdo->query('SELECT name FROM contact_messages ORDER BY id DESC LIMIT 1')->fetchColumn();
check('stored name is not byte-truncated', $storedName === $cyrillicName);

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
