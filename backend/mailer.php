<?php
// Admin email delivery. Compatible with PHP 7.1.
//
// Drivers: mail (cPanel sendmail), smtp, resend, brevo, log (local only).
// The one-time code is never written to logs in production.

require_once __DIR__ . '/config.php';

function admin_mail_clean_header($value)
{
    return trim(str_replace(array("\r", "\n", "\0"), '', (string) $value));
}

function admin_mail_from_header(array $mail)
{
    $from = admin_mail_clean_header($mail['from']);
    $name = admin_mail_clean_header($mail['from_name']);
    if ($name === '') {
        return $from;
    }
    return $name . ' <' . $from . '>';
}

function admin_mail_message($code, $ttlMinutes)
{
    $safeCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
    $ttl = (int) $ttlMinutes;
    $subject = 'Admin login code: ' . $code;
    $text = "Your admin login code is: " . $code . "\n\n"
        . "It expires in " . $ttl . " minutes. If you did not request it, ignore this email.\n";
    $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#0b1220;color:#eaf1ff;font-family:Arial,sans-serif">'
        . '<div style="max-width:520px;margin:0 auto;background:#111d30;border:1px solid #223552;border-radius:16px;padding:28px">'
        . '<p style="margin:0 0 8px;color:#8fb4e8;font-size:13px;letter-spacing:.08em;text-transform:uppercase">Sardorbek Otaboyev admin</p>'
        . '<h1 style="margin:0 0 14px;font-size:22px">Login code</h1>'
        . '<p style="margin:0 0 18px;color:#b8c7df">Enter this code to finish signing in:</p>'
        . '<div style="font-size:34px;font-weight:700;letter-spacing:.22em;padding:16px 20px;background:#0a1424;border-radius:12px;text-align:center">' . $safeCode . '</div>'
        . '<p style="margin:18px 0 0;color:#8fa3bf;font-size:13px">The code expires in ' . $ttl . ' minutes. If you did not request it, ignore this email.</p>'
        . '</div></body></html>';

    return array($subject, $text, $html);
}

function admin_mail_http_post($url, array $headers, $payload, $timeout)
{
    if (!function_exists('curl_init')) {
        return array(false, 'cURL is not available');
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return array(false, $error ?: 'HTTP request failed');
    }

    if ($status < 200 || $status >= 300) {
        return array(false, 'HTTP ' . $status);
    }

    return array(true, '');
}

function admin_mail_send_smtp(array $mail, $to, $subject, $text, $html)
{
    $host = admin_mail_clean_header($mail['smtp_host']);
    $port = (int) $mail['smtp_port'];
    $secure = strtolower((string) $mail['smtp_secure']);
    $user = (string) $mail['smtp_user'];
    $pass = (string) $mail['smtp_pass'];
    $from = admin_mail_clean_header($mail['from']);
    $to = admin_mail_clean_header($to);
    $fromHeader = admin_mail_from_header($mail);

    if ($host === '' || $port < 1 || $port > 65535) {
        return array(false, 'SMTP is not configured');
    }
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return array(false, 'Invalid SMTP sender');
    }
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return array(false, 'Invalid SMTP recipient');
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return array(false, 'SMTP connect failed: ' . ($errstr ?: $errno));
    }
    stream_set_timeout($socket, 10);

    $read = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $command = function ($line, $expected) use ($socket, $read) {
        if ($line !== null) {
            fwrite($socket, $line . "\r\n");
        }
        $response = $read();
        $code = (int) substr($response, 0, 3);
        if ($code !== (int) $expected) {
            return array(false, trim($response));
        }
        return array(true, $response);
    };

    $greeting = $read();
    if ((int) substr($greeting, 0, 3) !== 220) {
        fclose($socket);
        return array(false, 'SMTP greeting failed');
    }

    $hostname = isset($_SERVER['SERVER_NAME']) ? preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['SERVER_NAME']) : 'localhost';
    $result = $command('EHLO ' . ($hostname ?: 'localhost'), 250);
    if (!$result[0]) {
        fclose($socket);
        return array(false, 'SMTP EHLO failed');
    }

    if ($secure === 'tls' || $secure === 'starttls') {
        $result = $command('STARTTLS', 220);
        if (!$result[0]) {
            fclose($socket);
            return array(false, 'SMTP STARTTLS failed');
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return array(false, 'SMTP TLS negotiation failed');
        }
        $result = $command('EHLO ' . ($hostname ?: 'localhost'), 250);
        if (!$result[0]) {
            fclose($socket);
            return array(false, 'SMTP EHLO after TLS failed');
        }
    }

    if ($user !== '' || $pass !== '') {
        $result = $command('AUTH LOGIN', 334);
        if (!$result[0]) {
            fclose($socket);
            return array(false, 'SMTP AUTH failed');
        }
        $result = $command(base64_encode($user), 334);
        if (!$result[0]) {
            fclose($socket);
            return array(false, 'SMTP username rejected');
        }
        $result = $command(base64_encode($pass), 235);
        if (!$result[0]) {
            fclose($socket);
            return array(false, 'SMTP password rejected');
        }
    }

    $result = $command('MAIL FROM:<' . $from . '>', 250);
    if (!$result[0]) {
        fclose($socket);
        return array(false, 'SMTP MAIL FROM failed');
    }
    $result = $command('RCPT TO:<' . $to . '>', 250);
    if (!$result[0]) {
        fclose($socket);
        return array(false, 'SMTP RCPT TO failed');
    }
    $result = $command('DATA', 354);
    if (!$result[0]) {
        fclose($socket);
        return array(false, 'SMTP DATA failed');
    }

    $boundary = 'b' . bin2hex(random_bytes(12));
    $headers = 'Date: ' . date('r') . "\r\n"
        . 'From: ' . $fromHeader . "\r\n"
        . 'To: ' . $to . "\r\n"
        . 'Subject: ' . admin_mail_clean_header($subject) . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n"
        . '--' . $boundary . "--\r\n";

    // Prevent SMTP dot-stuffing issues.
    $body = preg_replace('/^\./m', '..', $body);
    fwrite($socket, $headers . $body . ".\r\n");
    $response = $read();
    $code = (int) substr($response, 0, 3);
    $command('QUIT', 221);
    fclose($socket);

    if ($code !== 250) {
        return array(false, 'SMTP message rejected');
    }
    return array(true, '');
}

function admin_send_login_code($to, $code, $ttlMinutes)
{
    $config = admin_config();
    $mail = $config['mail'];
    $to = admin_mail_clean_header($to);
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return array(false, 'Invalid recipient');
    }
    if (filter_var(admin_mail_clean_header($mail['from']), FILTER_VALIDATE_EMAIL) === false) {
        return array(false, 'Invalid sender');
    }

    list($subject, $text, $html) = admin_mail_message($code, $ttlMinutes);
    $driver = strtolower((string) $mail['driver']);

    if ($driver === 'log') {
        if ($config['app_env'] === 'production' || $mail['log_file'] === '') {
            return array(false, 'Log mail driver is disabled');
        }
        $line = gmdate('c') . ' to=' . $to . ' code=' . $code . "\n";
        $written = @file_put_contents($mail['log_file'], $line, FILE_APPEND | LOCK_EX);
        return $written === false ? array(false, 'Unable to write mail log') : array(true, '');
    }

    if ($driver === 'resend') {
        if ($mail['resend_api_key'] === '') {
            return array(false, 'Resend is not configured');
        }
        $payload = json_encode(array(
            'from' => admin_mail_from_header($mail),
            'to' => array($to),
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return admin_mail_http_post(
            'https://api.resend.com/emails',
            array('Authorization: Bearer ' . $mail['resend_api_key'], 'Content-Type: application/json'),
            $payload,
            12
        );
    }

    if ($driver === 'brevo') {
        if ($mail['brevo_api_key'] === '') {
            return array(false, 'Brevo is not configured');
        }
        $payload = json_encode(array(
            'sender' => array('name' => $mail['from_name'], 'email' => $mail['from']),
            'to' => array(array('email' => $to)),
            'subject' => $subject,
            'textContent' => $text,
            'htmlContent' => $html,
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return admin_mail_http_post(
            'https://api.brevo.com/v3/smtp/email',
            array('api-key: ' . $mail['brevo_api_key'], 'Content-Type: application/json'),
            $payload,
            12
        );
    }

    if ($driver === 'smtp') {
        return admin_mail_send_smtp($mail, $to, $subject, $text, $html);
    }

    if (function_exists('mail')) {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers = 'MIME-Version: 1.0' . "\r\n"
            . 'From: ' . admin_mail_from_header($mail) . "\r\n"
            . 'Reply-To: ' . admin_mail_clean_header($mail['from']) . "\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n"
            . '--' . $boundary . "--\r\n";
        $sent = @mail($to, $subject, $body, $headers, '-f ' . admin_mail_clean_header($mail['from']));
        return $sent ? array(true, '') : array(false, 'mail() failed');
    }

    return array(false, 'No mail driver available');
}
