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
        $value = substr($value, 0, $max);
    }
    return $value;
}

function bot_clean_body($value, $max)
{
    $value = str_replace(array("\r\n", "\r", "\0"), "\n", (string) $value);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
    $cleaned = preg_replace('/[^\P{C}\n\t]+/u', '', $value);
    if ($cleaned !== null) {
        $value = $cleaned;
    }
    if ($max > 0 && strlen($value) > $max) {
        $value = substr($value, 0, $max) . "\n…";
    }
    return trim($value);
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
        $part = substr($text, 0, 3900);
        $break = strrpos($part, "\n");
        if ($break === false || $break < 2000) {
            $break = 3900;
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

function bot_imap_open()
{
    $config = bot_config();
    if ($config['imap_user'] === '' || $config['imap_pass'] === '' || !function_exists('imap_open')) {
        return false;
    }

    $flags = $config['imap_ssl'] ? '/imap/ssl' : '/imap/notls';
    $mailbox = '{' . $config['imap_host'] . ':' . $config['imap_port'] . $flags . '}INBOX';
    $inbox = @imap_open($mailbox, $config['imap_user'], $config['imap_pass'], 0, 1);
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

function bot_send_email($to, $subject, $body)
{
    $config = bot_config();
    $to = bot_clean_header($to, 320);
    $subject = bot_clean_header($subject, 200);
    $body = bot_clean_body($body, 10000);
    $from = bot_clean_header($config['from'], 320);
    $fromName = bot_clean_header($config['from_name'], 120);

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return array(false, 'Invalid email address');
    }
    if ($subject === '' || $body === '') {
        return array(false, 'Subject and body are required');
    }

    if ($config['driver'] === 'smtp') {
        $mail = admin_config()['mail'];
        $html = '<pre style="white-space:pre-wrap;font-family:Arial,sans-serif">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre>';
        return admin_mail_send_smtp($mail, $to, $subject, $body, $html);
    }

    $headers = 'From: ' . ($fromName !== '' ? $fromName . ' <' . $from . '>' : $from) . "\r\n"
        . 'Reply-To: ' . $from . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n";
    $sent = @mail($to, $subject, $body, $headers, '-f ' . $from);

    return $sent ? array(true, '') : array(false, 'Mail service rejected the message');
}

function bot_help_text()
{
    return "Admin email bridge\n\n"
        . "/inbox — oxirgi 5 xat\n"
        . "/read <uid> — xatni o‘qish\n"
        . "/send <email> | <mavzu> | <matn> — xat yuborish\n"
        . "/help — yordam\n\n"
        . "Faqat ruxsat etilgan Telegram akkaunt ishlata oladi.";
}

function bot_handle_update(array $update)
{
    if (!isset($update['message']) || !is_array($update['message'])) {
        return;
    }

    $message = $update['message'];
    $chat = isset($message['chat']) && is_array($message['chat']) ? $message['chat'] : array();
    $from = isset($message['from']) && is_array($message['from']) ? $message['from'] : array();
    $config = bot_config();
    $chatId = isset($chat['id']) ? (string) $chat['id'] : '';
    $fromId = isset($from['id']) ? (string) $from['id'] : '';

    if ($chatId === '' || $fromId === '' || $chatId !== $config['admin_id'] || $fromId !== $config['admin_id']) {
        return;
    }
    if (isset($chat['type']) && $chat['type'] !== 'private') {
        return;
    }

    $text = isset($message['text']) && is_scalar($message['text']) ? trim((string) $message['text']) : '';
    if ($text === '' || strlen($text) > 12000) {
        bot_send_message($chatId, 'Matn bo‘sh yoki juda uzun.');
        return;
    }

    if ($text === '/start' || $text === '/help' || strpos($text, '/start@') === 0 || strpos($text, '/help@') === 0) {
        bot_send_message($chatId, bot_help_text());
        return;
    }

    if ($text === '/inbox' || $text === '/mail' || strpos($text, '/inbox@') === 0) {
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

    if (preg_match('#^/read(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})$#', $text, $matches)) {
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

    if (preg_match('#^/send(?:@[A-Za-z0-9_]+)?\s+(.+)$#s', $text, $matches)) {
        $parts = explode('|', $matches[1], 3);
        if (count($parts) < 3) {
            bot_send_message($chatId, 'Format: /send email@example.com | Mavzu | Matn');
            return;
        }
        $result = bot_send_email(trim($parts[0]), trim($parts[1]), trim($parts[2]));
        if ($result[0]) {
            bot_send_message($chatId, 'Email yuborildi: ' . bot_clean_header(trim($parts[0]), 320));
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
