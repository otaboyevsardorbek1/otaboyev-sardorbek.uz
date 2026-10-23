<?php
// Telegram admin bot: a private, allow-listed email bridge.
// Compatible with PHP 7.1.
//
// The webhook is protected by Telegram's secret-token header and the numeric
// admin Telegram ID. It never exposes raw IMAP errors, message headers or
// credentials to the chat.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

function bot_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = content_local_settings();
    $bot = isset($local['bot']) && is_array($local['bot']) ? $local['bot'] : array();
    $admin = admin_config();
    $mail = $admin['mail'];

    $config = array(
        'token' => $admin['telegram_bot_token'],
        'admin_id' => (string) $admin['telegram_id'],
        'webhook_secret' => isset($bot['webhook_secret']) ? (string) $bot['webhook_secret'] : '',
        'imap_host' => isset($bot['imap_host']) ? (string) $bot['imap_host'] : 'panel2.eskiz.uz',
        'imap_port' => isset($bot['imap_port']) ? (int) $bot['imap_port'] : 993,
        'imap_user' => isset($bot['imap_user']) ? (string) $bot['imap_user'] : $admin['email'],
        'imap_pass' => isset($bot['imap_pass']) ? (string) $bot['imap_pass'] : '',
        'imap_ssl' => !isset($bot['imap_ssl']) || (bool) $bot['imap_ssl'],
        'from' => $mail['from'],
        'from_name' => $mail['from_name'],
        'driver' => $mail['driver'],
    );

    return $config;
}

function bot_clean_header($value, $max)
{
    $value = trim(str_replace(array("\r", "\n", "\0"), '', (string) $value));
    if ($max > 0 && strlen($value) > $max) {
        $value = function_exists('mb_strcut') ? mb_strcut($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
    return $value;
}

function bot_clean_body($value, $max)
{
    $value = str_replace(array("\r\n", "\r", "\0"), "\n", (string) $value);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
    // Remove control characters but keep newlines and tabs. The previous
    // pattern relied on \P{C} semantics and could return null.
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if (is_string($cleaned)) {
        $value = $cleaned;
    }
    if ($max > 0 && strlen($value) > $max) {
        $value = (function_exists('mb_strcut') ? mb_strcut($value, 0, $max, 'UTF-8') : substr($value, 0, $max)) . "\n…";
    }
    return trim($value);
}

function bot_db()
{
    try {
        return content_db();
    } catch (Exception $error) {
        error_log('bot database unavailable: ' . $error->getMessage());
        return null;
    }
}

// Resolve a Telegram user against admin_users. The bot must follow the web
// panel: an admin switched off (is_active = 0) or deleted loses bot access on
// the next message. When the database or the table is unavailable, only the
// bootstrap telegram_id from config keeps working so the owner is not locked
// out by a temporary DB outage.
function bot_find_admin($telegramId)
{
    static $cache = array();
    $telegramId = (string) $telegramId;
    if ($telegramId === '') {
        return null;
    }
    if (array_key_exists($telegramId, $cache)) {
        return $cache[$telegramId];
    }

    $config = bot_config();
    $pdo = bot_db();

    $bootstrap = array(
        'id' => 0,
        'telegram_id' => $telegramId,
        'email' => $config['from'],
        'display_name' => null,
        'telegram_username' => null,
        'is_active' => 1,
        'role' => 'super',
    );

    if (!$pdo) {
        $cache[$telegramId] = ($telegramId === $config['admin_id']) ? $bootstrap : null;
        return $cache[$telegramId];
    }

    try {
        $statement = $pdo->prepare(
            'SELECT id, telegram_id, email, display_name, telegram_username, is_active, role
             FROM admin_users WHERE telegram_id = :tg LIMIT 1'
        );
        $statement->execute(array(':tg' => $telegramId));
        $row = $statement->fetch();

        if ($row && (int) $row['is_active'] === 1) {
            $cache[$telegramId] = $row;
            return $cache[$telegramId];
        }

        // An existing admin_users table is authoritative: bootstrap fallback
        // only applies while the table is still empty (first install).
        $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        $cache[$telegramId] = ($count === 0 && $telegramId === $config['admin_id']) ? $bootstrap : null;
        return $cache[$telegramId];
    } catch (Exception $error) {
        error_log('bot admin lookup failed: ' . $error->getMessage());
        $cache[$telegramId] = ($telegramId === $config['admin_id']) ? $bootstrap : null;
        return $cache[$telegramId];
    }
}

function bot_audit($admin, $action, $details = null)
{
    $pdo = bot_db();
    if (!$pdo || !is_array($admin)) {
        return;
    }
    $adminId = isset($admin['id']) ? (int) $admin['id'] : 0;
    try {
        $statement = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity, ip_hash, details)
             VALUES (:aid, :a, :e, NULL, :d)'
        );
        $statement->execute(array(
            ':aid' => $adminId > 0 ? $adminId : null,
            ':a' => (string) $action,
            ':e' => 'bot',
            ':d' => $details === null ? null
                : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ));
    } catch (Exception $error) {
        error_log('bot audit failed: ' . $error->getMessage());
    }
}

// Telegram retries webhook updates. Remember every update_id so a retried
// /send cannot deliver the same email twice. Missing tables only disable the
// guard; the bot keeps working.
function bot_is_duplicate_update($updateId)
{
    $updateId = (int) $updateId;
    if ($updateId < 1) {
        return false;
    }
    $pdo = bot_db();
    if (!$pdo) {
        return false;
    }

    try {
        $statement = $pdo->prepare('INSERT IGNORE INTO bot_updates_seen (update_id, seen_at) VALUES (:update_id, UTC_TIMESTAMP())');
        $statement->execute(array(':update_id' => $updateId));
        return $statement->rowCount() === 0;
    } catch (Exception $error) {
        error_log('bot idempotency guard unavailable: ' . $error->getMessage());
        return false;
    }
}

function bot_rate_limit_ok($telegramId, $command, $maxPerMinute)
{
    $telegramId = (int) $telegramId;
    if ($telegramId < 1) {
        // Bootstrap config always has a numeric telegram_id, so this is only
        // hit for malformed calls. Do not lock the owner out.
        return true;
    }
    $pdo = bot_db();
    if (!$pdo) {
        // Do not lock the owner out when the DB is temporarily unavailable.
        return true;
    }

    try {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM bot_rate_limit
             WHERE admin_id = :admin_id AND command = :command
               AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)'
        );
        $statement->execute(array(':admin_id' => $telegramId, ':command' => (string) $command));
        if ((int) $statement->fetchColumn() >= (int) $maxPerMinute) {
            return false;
        }

        $insert = $pdo->prepare('INSERT INTO bot_rate_limit (admin_id, command, created_at) VALUES (:admin_id, :command, UTC_TIMESTAMP())');
        $insert->execute(array(':admin_id' => $telegramId, ':command' => (string) $command));

        if (random_int(1, 50) === 1) {
            $pdo->exec('DELETE FROM bot_rate_limit WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
            $pdo->exec('DELETE FROM bot_updates_seen WHERE seen_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
        }

        return true;
    } catch (Exception $error) {
        error_log('bot rate limit unavailable: ' . $error->getMessage());
        return true;
    }
}

function bot_api($method, array $payload)
{
    $config = bot_config();
    if ($config['token'] === '' || !function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init('https://api.telegram.org/bot' . $config['token'] . '/' . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $result = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        error_log('bot api transport error: ' . $error);
        return false;
    }
    if ($status < 200 || $status >= 300) {
        error_log('bot api HTTP status: ' . $status);
        return false;
    }

    return true;
}

function bot_send_message($chatId, $text)
{
    $text = bot_clean_body($text, 4000);
    if ($text === '') {
        return false;
    }

    $chunks = array();
    while ($text !== '') {
        if (strlen($text) <= 3900) {
            $chunks[] = $text;
            break;
        }
        $part = function_exists('mb_strcut') ? mb_strcut($text, 0, 3900, 'UTF-8') : substr($text, 0, 3900);
        $break = strrpos($part, "\n");
        if ($break === false || $break < 2000) {
            $break = strlen($part);
        }
        $chunks[] = substr($text, 0, $break);
        $text = ltrim(substr($text, $break));
    }

    foreach ($chunks as $chunk) {
        if (!bot_api('sendMessage', array(
            'chat_id' => $chatId,
            'text' => $chunk,
            'disable_web_page_preview' => true,
        ))) {
            return false;
        }
    }

    return true;
}

// Best-effort broadcast to every active admin. Used by the contact form and
// other server-side events; one failing recipient never blocks the others.
function bot_notify_admins($text)
{
    $pdo = bot_db();
    if (!$pdo) {
        return 0;
    }

    $sent = 0;
    try {
        // Cap the fan-out so a contact submission cannot stall on many chats.
        $rows = $pdo->query('SELECT telegram_id FROM admin_users WHERE is_active = 1 ORDER BY id ASC LIMIT 10')->fetchAll();
        foreach ($rows as $row) {
            $tgId = isset($row['telegram_id']) ? (string) $row['telegram_id'] : '';
            if ($tgId !== '' && bot_send_message($tgId, $text)) {
                $sent++;
            }
        }
    } catch (Exception $error) {
        error_log('bot notify admins failed: ' . $error->getMessage());
    }

    return $sent;
}

function bot_imap_open()
{
    $config = bot_config();
    if ($config['imap_user'] === '' || $config['imap_pass'] === '' || !function_exists('imap_open')) {
        return false;
    }

    $flags = $config['imap_ssl'] ? '/imap/ssl' : '/imap/notls';
    $mailbox = '{' . $config['imap_host'] . ':' . $config['imap_port'] . $flags . '}INBOX';
    // Open read-only so /inbox and /read never flip messages to \Seen.
    $inbox = @imap_open($mailbox, $config['imap_user'], $config['imap_pass'], OP_READONLY, 1);
    if (!$inbox) {
        error_log('bot imap connection failed');
        return false;
    }

    return $inbox;
}

function bot_imap_close($inbox)
{
    if ($inbox) {
        @imap_close($inbox);
    }
}

function bot_mail_list($inbox, $limit)
{
    $limit = max(1, min(10, (int) $limit));
    $sequence = @imap_sort($inbox, SORTDATE, 1);
    if (!is_array($sequence) || count($sequence) === 0) {
        return array();
    }

    $rows = array();
    foreach (array_slice($sequence, 0, $limit) as $msgno) {
        $uid = @imap_uid($inbox, $msgno);
        $overview = @imap_fetch_overview($inbox, (string) $msgno, 0);
        $row = is_array($overview) && isset($overview[0]) ? (array) $overview[0] : array();
        $rows[] = array(
            'uid' => (int) $uid,
            'from' => isset($row['from']) ? bot_clean_header($row['from'], 120) : '',
            'subject' => isset($row['subject']) ? bot_clean_header($row['subject'], 160) : '',
            'date' => isset($row['date']) ? bot_clean_header($row['date'], 80) : '',
            'seen' => isset($row['seen']) ? (bool) $row['seen'] : false,
        );
    }

    return $rows;
}

function bot_mail_read($inbox, $uid)
{
    $uid = (int) $uid;
    if ($uid < 1) {
        return null;
    }

    $msgno = @imap_msgno($inbox, $uid);
    if (!$msgno) {
        return null;
    }

    $overview = @imap_fetch_overview($inbox, (string) $msgno, 0);
    $row = is_array($overview) && isset($overview[0]) ? (array) $overview[0] : array();
    $body = @imap_fetchbody($inbox, $msgno, '1');
    if ($body === false || trim((string) $body) === '') {
        $body = @imap_body($inbox, $msgno);
    }
    $body = quoted_printable_decode((string) $body);
    $body = html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8');
    $body = preg_replace('/[ \t]+/', ' ', $body);
    $body = preg_replace('/\n{3,}/', "\n\n", $body);

    return array(
        'uid' => $uid,
        'from' => isset($row['from']) ? bot_clean_header($row['from'], 160) : '',
        'subject' => isset($row['subject']) ? bot_clean_header($row['subject'], 200) : '',
        'date' => isset($row['date']) ? bot_clean_header($row['date'], 100) : '',
        'body' => bot_clean_body($body, 3500),
    );
}

function bot_send_email($to, $subject, $body, $replyTo = '')
{
    $config = bot_config();
    $to = bot_clean_header($to, 320);
    $subject = bot_clean_header($subject, 200);
    $body = bot_clean_body($body, 10000);
    $from = bot_clean_header($config['from'], 320);
    $fromName = bot_clean_header($config['from_name'], 120);
    $replyTo = bot_clean_header($replyTo, 320);
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
        $replyTo = '';
    }

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return array(false, 'Invalid email address');
    }
    if ($subject === '' || $body === '') {
        return array(false, 'Subject and body are required');
    }

    if ($config['driver'] === 'log') {
        $mail = admin_config()['mail'];
        $appEnv = isset(admin_config()['app_env']) ? (string) admin_config()['app_env'] : 'production';
        if ($appEnv === 'production' || $mail['log_file'] === '') {
            return array(false, 'Log mail driver is disabled');
        }
        $line = gmdate('c') . ' to=' . $to . ' subject=' . $subject . ' body=' . $body . "\n";
        $written = @file_put_contents($mail['log_file'], $line, FILE_APPEND | LOCK_EX);
        return $written === false ? array(false, 'Unable to write mail log') : array(true, '');
    }

    if ($config['driver'] === 'smtp') {
        $mail = admin_config()['mail'];
        $html = '<pre style="white-space:pre-wrap;font-family:Arial,sans-serif">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre>';
        // admin_mail_send_smtp() does not accept Reply-To; the mail driver below does.
        return admin_mail_send_smtp($mail, $to, $subject, $body, $html);
    }

    $headers = 'From: ' . ($fromName !== '' ? $fromName . ' <' . $from . '>' : $from) . "\r\n"
        . 'Reply-To: ' . ($replyTo !== '' ? $replyTo : $from) . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n";
    $sent = @mail($to, $subject, $body, $headers, '-f ' . $from);

    return $sent ? array(true, '') : array(false, 'Mail service rejected the message');
}

function bot_help_text()
{
    return "Admin email & inbox bridge\n\n"
        . "/whoami — joriy admin ma’lumoti\n"
        . "/messages — oxirgi 10 contact xabar\n"
        . "/unread — faqat o‘qilmagan xabarlar\n"
        . "/message <id> — contact xabarni o‘qish\n"
        . "/reply <id> | matn — foydalanuvchiga email javob\n"
        . "/stats — statistika\n\n"
        . "/inbox — oxirgi 5 email (IMAP)\n"
        . "/read <uid> — emailni o‘qish\n"
        . "/send <email> | <mavzu> | <matn> — email yuborish\n"
        . "/help — yordam\n\n"
        . "Faqat faol adminlar foydalana oladi (web panelda boshqariladi).";
}

// Contact inbox helpers. Both return null when the database is unavailable so
// the handler can tell "no data" apart from "no rows".
function bot_web_inbox_list($limit, $unreadOnly)
{
    $pdo = bot_db();
    if (!$pdo) {
        return null;
    }
    $limit = max(1, min(20, (int) $limit));
    $where = $unreadOnly ? ' WHERE is_read = 0' : '';
    try {
        $statement = $pdo->query(
            'SELECT id, name, email, subject, is_read, created_at
             FROM contact_messages' . $where . '
             ORDER BY is_read ASC, created_at DESC
             LIMIT ' . $limit
        );
        return $statement->fetchAll();
    } catch (Exception $error) {
        error_log('bot web inbox list failed: ' . $error->getMessage());
        return null;
    }
}

function bot_web_message_read($id)
{
    $pdo = bot_db();
    if (!$pdo) {
        return null;
    }
    $id = (int) $id;
    if ($id < 1) {
        return null;
    }
    try {
        $statement = $pdo->prepare(
            'SELECT id, form_id, locale, name, email, subject, message, is_read, created_at
             FROM contact_messages WHERE id = :id LIMIT 1'
        );
        $statement->execute(array(':id' => $id));
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        if ((int) $row['is_read'] === 0) {
            $update = $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = :id');
            $update->execute(array(':id' => $id));
            $row['is_read'] = 1;
        }
        return $row;
    } catch (Exception $error) {
        error_log('bot web message read failed: ' . $error->getMessage());
        return null;
    }
}

function bot_handle_update(array $update)
{
    if (!isset($update['message']) || !is_array($update['message'])) {
        return;
    }

    $message = $update['message'];
    $chat = isset($message['chat']) && is_array($message['chat']) ? $message['chat'] : array();
    $from = isset($message['from']) && is_array($message['from']) ? $message['from'] : array();
    $chatId = isset($chat['id']) ? (string) $chat['id'] : '';
    $fromId = isset($from['id']) ? (string) $from['id'] : '';

    if ($chatId === '' || $fromId === '') {
        return;
    }
    if (isset($chat['type']) && $chat['type'] !== 'private') {
        return;
    }
    if ($chatId !== $fromId) {
        return;
    }

    // Only active admin_users rows may talk to the bot. Unknown or disabled
    // accounts are dropped silently so the bot does not reveal itself.
    $admin = bot_find_admin($fromId);
    if (!$admin) {
        return;
    }

    $text = isset($message['text']) && is_scalar($message['text']) ? trim((string) $message['text']) : '';
    if ($text === '' || strlen($text) > 12000) {
        bot_send_message($chatId, 'Matn bo‘sh yoki juda uzun.');
        return;
    }

    $tgId = (int) $admin['telegram_id'];

    // --- /start, /help -------------------------------------------------
    if ($text === '/start' || $text === '/help'
        || strpos($text, '/start@') === 0 || strpos($text, '/help@') === 0) {
        bot_send_message($chatId, bot_help_text());
        return;
    }

    // --- /whoami -------------------------------------------------------
    if ($text === '/whoami' || strpos($text, '/whoami@') === 0) {
        $name = isset($admin['display_name']) && $admin['display_name']
            ? $admin['display_name'] : 'Admin';
        $email = isset($admin['email']) ? $admin['email'] : '';
        bot_send_message($chatId,
            $name . "\nTelegram ID: " . $admin['telegram_id'] . "\nEmail: " . $email);
        return;
    }

    // --- /messages (contact inbox) -------------------------------------
    if ($text === '/messages' || $text === '/msgs' || strpos($text, '/messages@') === 0) {
        if (!bot_rate_limit_ok($tgId, 'messages', 20)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $rows = bot_web_inbox_list(10, false);
        if ($rows === null) {
            bot_send_message($chatId, 'Xabarlar bazasi mavjud emas.');
            return;
        }
        if (count($rows) === 0) {
            bot_send_message($chatId, 'Contact inbox bo‘sh.');
            return;
        }
        $lines = array('Contact inbox (oxirgi ' . count($rows) . '):');
        foreach ($rows as $row) {
            $lines[] = ((int) $row['is_read'] === 0 ? '● ' : '  ')
                . '#' . $row['id'] . ' — ' . ($row['subject'] ? $row['subject'] : '(mavzusiz)') . "\n   "
                . ($row['name'] ? $row['name'] : $row['email']) . ' · ' . $row['created_at'];
        }
        $lines[] = 'Ochish uchun: /message <id>';
        bot_send_message($chatId, implode("\n\n", $lines));
        return;
    }

    // --- /unread -------------------------------------------------------
    if ($text === '/unread' || strpos($text, '/unread@') === 0) {
        if (!bot_rate_limit_ok($tgId, 'unread', 20)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov.');
            return;
        }
        $rows = bot_web_inbox_list(10, true);
        if ($rows === null) {
            bot_send_message($chatId, 'Xabarlar bazasi mavjud emas.');
            return;
        }
        if (count($rows) === 0) {
            bot_send_message($chatId, 'O‘qilmagan xabar yo‘q.');
            return;
        }
        $lines = array('O‘qilmagan xabarlar (' . count($rows) . '):');
        foreach ($rows as $row) {
            $lines[] = '#' . $row['id'] . ' — ' . ($row['subject'] ? $row['subject'] : '(mavzusiz)') . "\n   "
                . ($row['name'] ? $row['name'] : $row['email']) . ' · ' . $row['created_at'];
        }
        bot_send_message($chatId, implode("\n\n", $lines));
        return;
    }

    // --- /message <id> -------------------------------------------------
    if (preg_match('#^/message(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})$#', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'message', 30)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov.');
            return;
        }
        $row = bot_web_message_read((int) $matches[1]);
        if (!$row) {
            bot_send_message($chatId, 'Xabar topilmadi.');
            return;
        }
        bot_send_message($chatId,
            'Xabar #' . $row['id'] . "\n"
            . 'Kimdan: ' . ($row['name'] ? $row['name'] : '(ism yo‘q)') . ' <' . ($row['email'] ? $row['email'] : '-') . ">\n"
            . 'Mavzu: ' . ($row['subject'] ? $row['subject'] : '(mavzusiz)') . "\n"
            . 'Sana: ' . $row['created_at'] . "\n"
            . 'Locale: ' . ($row['locale'] ? $row['locale'] : '-') . "\n\n"
            . ($row['message'] ? $row['message'] : '(matn yo‘q)') . "\n\n"
            . 'Javob: /reply ' . $row['id'] . ' | <matn>');
        bot_audit($admin, 'bot_message_read', array('message_id' => (int) $row['id']));
        return;
    }

    // --- /reply <id> | matn -------------------------------------------
    if (preg_match('#^/reply(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})\s*\|\s*(.+)$#s', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'reply', 10)) {
            bot_send_message($chatId, 'Juda ko‘p email javob so‘rovi.');
            return;
        }
        $id = (int) $matches[1];
        $body = trim((string) $matches[2]);
        if ($body === '' || strlen($body) > 5000) {
            bot_send_message($chatId, 'Javob matni 1–5000 belgi oralig‘ida bo‘lishi kerak.');
            return;
        }
        $pdo = bot_db();
        if (!$pdo) {
            bot_send_message($chatId, 'Baza mavjud emas.');
            return;
        }
        try {
            $statement = $pdo->prepare('SELECT id, email, subject FROM contact_messages WHERE id = :id LIMIT 1');
            $statement->execute(array(':id' => $id));
            $original = $statement->fetch();
        } catch (Exception $error) {
            error_log('bot reply lookup failed: ' . $error->getMessage());
            $original = null;
        }
        if (!$original || !$original['email']) {
            bot_send_message($chatId, 'Xabar yoki email topilmadi.');
            return;
        }
        $replyToEmail = isset($admin['email']) ? (string) $admin['email'] : '';
        $subject = 'Re: ' . ($original['subject'] ? $original['subject'] : 'Your message');
        $result = bot_send_email($original['email'], $subject, $body, $replyToEmail);
        if ($result[0]) {
            bot_send_message($chatId, 'Javob yuborildi: ' . $original['email']);
            bot_audit($admin, 'bot_message_reply', array('message_id' => $id, 'to' => $original['email']));
        } else {
            bot_send_message($chatId, 'Javob yuborilmadi: ' . $result[1]);
        }
        return;
    }

    // --- /stats -------------------------------------------------------
    if ($text === '/stats' || strpos($text, '/stats@') === 0) {
        $pdo = bot_db();
        if (!$pdo) {
            bot_send_message($chatId, 'Baza mavjud emas.');
            return;
        }
        try {
            $unread = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
            $total = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
            $activeAdmins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE is_active = 1')->fetchColumn();
            bot_send_message($chatId,
                "Statistika:\n"
                . "O‘qilmagan xabar: $unread\n"
                . "Jami xabar: $total\n"
                . "Faol adminlar: $activeAdmins");
        } catch (Exception $error) {
            error_log('bot stats failed: ' . $error->getMessage());
            bot_send_message($chatId, 'Statistika olinmadi.');
        }
        return;
    }

    // --- /inbox (IMAP email) ------------------------------------------
    if ($text === '/inbox' || $text === '/mail' || strpos($text, '/inbox@') === 0) {
        if (!bot_rate_limit_ok($tgId, 'inbox', 10)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $inbox = bot_imap_open();
        if (!$inbox) {
            bot_send_message($chatId, 'Email qutisiga ulanib bo‘lmadi. Keyinroq urinib ko‘ring.');
            return;
        }
        $rows = bot_mail_list($inbox, 5);
        bot_imap_close($inbox);
        if (count($rows) === 0) {
            bot_send_message($chatId, 'Inbox bo‘sh.');
            return;
        }
        $lines = array('Inbox — oxirgi xatlar:');
        foreach ($rows as $row) {
            $lines[] = '#' . $row['uid'] . ' ' . ($row['seen'] ? '' : '● ') . $row['subject'] . "\n"
                . '   ' . $row['from'] . ' · ' . $row['date'];
        }
        bot_send_message($chatId, implode("\n\n", $lines));
        return;
    }

    // --- /read <uid> (IMAP) -------------------------------------------
    if (preg_match('#^/read(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})$#', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'read', 20)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $inbox = bot_imap_open();
        if (!$inbox) {
            bot_send_message($chatId, 'Email qutisiga ulanib bo‘lmadi.');
            return;
        }
        $mail = bot_mail_read($inbox, (int) $matches[1]);
        bot_imap_close($inbox);
        if (!$mail) {
            bot_send_message($chatId, 'Xat topilmadi yoki uid noto‘g‘ri.');
            return;
        }
        bot_send_message($chatId, 'Kimdan: ' . $mail['from'] . "\n"
            . 'Mavzu: ' . $mail['subject'] . "\n"
            . 'Sana: ' . $mail['date'] . "\n\n"
            . $mail['body']);
        return;
    }

    // --- /send <email> | mavzu | matn (IMAP) --------------------------
    if (preg_match('#^/send(?:@[A-Za-z0-9_]+)?\s+(.+)$#s', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'send', 10)) {
            bot_send_message($chatId, 'Juda ko‘p email so‘rovi. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $parts = explode('|', $matches[1], 3);
        if (count($parts) < 3) {
            bot_send_message($chatId, 'Format: /send email@example.com | Mavzu | Matn');
            return;
        }
        $result = bot_send_email(trim($parts[0]), trim($parts[1]), trim($parts[2]));
        if ($result[0]) {
            bot_send_message($chatId, 'Email yuborildi: ' . bot_clean_header(trim($parts[0]), 320));
            bot_audit($admin, 'bot_email_sent', array('to' => bot_clean_header(trim($parts[0]), 320)));
        } else {
            bot_send_message($chatId, 'Email yuborilmadi: ' . $result[1]);
        }
        return;
    }

    bot_send_message($chatId, bot_help_text());
}

function bot_handle_webhook()
{
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    $config = bot_config();
    $secret = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
        ? (string) $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']
        : '';
    if ($config['webhook_secret'] === '' || strlen($config['webhook_secret']) < 32 || !hash_equals($config['webhook_secret'], $secret)) {
        http_response_code(403);
        exit;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 131072) {
        http_response_code(413);
        exit;
    }
    $update = json_decode($raw, true);
    if (!is_array($update)) {
        http_response_code(200);
        echo 'ok';
        exit;
    }

    $updateId = isset($update['update_id']) ? (int) $update['update_id'] : 0;
    if ($updateId > 0 && bot_is_duplicate_update($updateId)) {
        // Telegram retry of an update we already processed.
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ok';
        exit;
    }

    try {
        bot_handle_update($update);
    } catch (Throwable $error) {
        error_log('bot update error: ' . $error->getMessage());
    }

    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ok';
    exit;
}
