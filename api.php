<?php
// Compatible with PHP 7.1

// Never expose stack traces or paths in responses. Errors must go to the
// server log only; a stray APP_ENV value must not turn them back on.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 0');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Permitted-Cross-Domain-Policies: none');

require_once __DIR__ . '/backend/contact.php';

// HSTS is only meaningful over HTTPS. Sending it on a plain HTTP response is
// ignored by browsers and hides misconfiguration; gate it explicitly.
if (api_is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$response = '';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $optionsPath = getApiRequestPath();
    if (api_is_public_read_path($optionsPath)) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Api-Token, Authorization');
    }
    http_response_code(204);
    exit;
}

function getApiRequestPath(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $apiFile = '/' . basename(__FILE__);

    // Only strip an exact /api.php prefix. A substring replace would also
    // turn paths such as /api.phpadmin/... into /admin/... routes.
    if ($path === $apiFile) {
        return '/';
    }
    if (strpos($path, $apiFile . '/') === 0) {
        $path = substr($path, strlen($apiFile));
    }

    return '/' . trim($path, '/');
}

function api_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    // Only trust the forwarded header when the deployment explicitly opts in.
    $config = admin_config();
    if (!empty($config['trust_proxy'])
        && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

// Wildcard CORS is limited to public, read-only JSON endpoints. Mutation
// endpoints (form, bot, admin, token) stay same-origin only.
function api_is_public_read_path(string $path): bool
{
    if (in_array($path, array('/profile', '/projects', '/health', '/status'), true)) {
        return true;
    }

    return preg_match('#^/projects/[a-z0-9-]{1,80}/?$#', $path) === 1;
}

function getConfiguredApiToken(): ?string
{
    $token = getenv('API_TOKEN') ?: getenv('SITEHUB_API_TOKEN') ?: getenv('OTABOYEV_API_TOKEN');
    if (is_string($token) && trim($token) !== '') {
        return trim($token);
    }

    // The public dev token is never accepted in production. Run local tests
    // with APP_ENV=local (or set API_TOKEN) instead of relying on this fallback.
    $config = admin_config();
    $appEnv = isset($config['app_env']) ? strtolower((string) $config['app_env']) : 'production';
    if (in_array($appEnv, array('local', 'development', 'test'), true)) {
        return 'otaboyev-dev-token';
    }

    return null;
}

function getRequestApiToken(): ?string
{
    $candidates = [
        'HTTP_X_API_TOKEN',
        'REDIRECT_HTTP_X_API_TOKEN',
        'HTTP_AUTHORIZATION',
        'X_API_TOKEN',
    ];

    foreach ($candidates as $key) {
        if (!isset($_SERVER[$key])) {
            continue;
        }

        $value = trim((string) $_SERVER[$key]);

        if ($key === 'HTTP_AUTHORIZATION' && preg_match('/Bearer\s+(.+)/i', $value, $matches)) {
            $value = trim($matches[1]);
        }

        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function verifyApiToken(): bool
{
    $expected = getConfiguredApiToken();
    $actual = getRequestApiToken();

    if ($expected === null || $actual === null) {
        return false;
    }

    return hash_equals($expected, $actual);
}

function api_clean_header($value, $max = 0)
{
    $value = trim(str_replace(array("\r", "\n", "\0"), '', (string) $value));
    if ($max > 0) {
        $value = substr($value, 0, $max);
    }
    return $value;
}

function api_valid_http_url($value)
{
    $value = (string) $value;
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

function api_escape_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sendFormSummaryByEmail(array $formConfig, array $data, array $attachments)
{
    if (!isset($formConfig['id'], $formConfig['form']) || !is_array($formConfig['form'])) {
        return false;
    }

    $formId = (string) $formConfig['id'];
    $form = $formConfig['form'];
    $destinationEmail = api_clean_header(isset($form['emailTo']) ? $form['emailTo'] : '', 320);
    if ($destinationEmail === '') {
        $destinationEmail = 'host9590@otaboyev-sardorbek.uz';
    }

    $recipients = array();
    foreach (explode(',', $destinationEmail) as $recipient) {
        $recipient = api_clean_header($recipient, 320);
        if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            $recipients[] = $recipient;
        }
    }
    if (!$recipients) {
        return false;
    }
    $destinationEmail = implode(', ', $recipients);

    $senderEmail = api_clean_header(isset($form['emailFrom']) ? $form['emailFrom'] : '', 320);
    if ($senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
        $senderEmail = '';
    }

    $replyToEmail = null;
    $subject = api_clean_header(isset($form['emailSubject']) ? $form['emailSubject'] : '', 200);
    if ($subject === '') {
        $subject = 'Form submission';
    }
    $textHeader = isset($form['emailText']) ? (string) $form['emailText'] : '';
    $htmlSummary = '';
    $formData = isset($_POST['form_' . $formId]) && is_array($_POST['form_' . $formId]) ? $_POST['form_' . $formId] : array();
    $formFieldsConfig = isset($formConfig['children']) && is_array($formConfig['children']) ? $formConfig['children'] : array();

    foreach ($formFieldsConfig as $formFieldConfig) {
        if (!is_array($formFieldConfig) || !isset($formFieldConfig['id'])) {
            continue;
        }
        $formItemConfig = isset($formFieldConfig['formItem']) && is_array($formFieldConfig['formItem']) ? $formFieldConfig['formItem'] : null;
        $formFieldName = sprintf('ed-f-%d', $formFieldConfig['id']);
        if ($formItemConfig
            && isset($formItemConfig['type'])
            && $formItemConfig['type'] === 'email'
            && isset($formData[$formFieldName])
            && is_scalar($formData[$formFieldName])
            && filter_var((string) $formData[$formFieldName], FILTER_VALIDATE_EMAIL) !== false) {
            $replyToEmail = api_clean_header($formData[$formFieldName], 320);
        }
    }

    foreach ($data as $label => $value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $label = api_clean_header($label, 160);
        if (strpos($label, 'ed-f') === 0) {
            $label = '';
        }
        $htmlSummary .= '<strong>' . api_escape_html($label) . '</strong><br />'
            . nl2br(api_escape_html((string) $value)) . '<br /><br />';
    }

    foreach ($attachments as $label => $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $label = api_clean_header($label, 160);
        if (strpos($label, 'ed-f') === 0) {
            $label = '';
        }
        $attachmentName = api_clean_header(isset($attachment['name']) ? $attachment['name'] : '', 255);
        if ($attachmentName === '') {
            $attachmentName = 'attachment';
        }
        $htmlSummary .= '<strong>' . api_escape_html($label) . '</strong><br />'
            . api_escape_html($attachmentName) . '<br /><br />';
    }

    $mailBody = sprintf(
        '<html><body>%s<br/><br/>%s</body></html>',
        nl2br(api_escape_html($textHeader)),
        $htmlSummary
    );

    $headers = "MIME-Version: 1.0\r\n";
    if ($senderEmail !== '') {
        $headers .= 'From: ' . $senderEmail . "\r\n";
    }
    if ($replyToEmail !== null) {
        $headers .= 'Reply-To: ' . $replyToEmail . "\r\n";
    }
    if (!empty($form['sendCsv'])) {
        $attachments[] = array(
            'type' => 'text/csv',
            'name' => 'form-data.csv',
            'data' => createCsvData($data),
        );
    }

    if (count($attachments)) {
        $boundary = 'b' . bin2hex(random_bytes(16));
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

        $body = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=utf-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($mailBody));

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $fileType = api_clean_header(isset($attachment['type']) ? $attachment['type'] : '', 100);
            if (!preg_match('#^[A-Za-z0-9.+/_-]+$#', $fileType)) {
                $fileType = 'application/octet-stream';
            }
            $fileName = api_clean_header(isset($attachment['name']) ? $attachment['name'] : '', 255);
            $fileName = str_replace(array('\\', '/', '"'), '', basename($fileName));
            if ($fileName === '') {
                $fileName = 'attachment';
            }

            if (isset($attachment['data'])) {
                $fileContent = (string) $attachment['data'];
            } elseif (isset($attachment['tempFile']) && is_file($attachment['tempFile'])) {
                $fileContent = (string) file_get_contents($attachment['tempFile']);
            } else {
                $fileContent = '';
            }
            $encodedFileContent = chunk_split(base64_encode($fileContent));

            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Type: ' . $fileType . '; name="' . $fileName . '"' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $fileName . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'X-Attachment-Id: ' . bin2hex(random_bytes(8)) . "\r\n\r\n";
            $body .= $encodedFileContent;
        }

        $mailBody = $body;
    } else {
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    }

    return mail(
        $destinationEmail,
        $subject,
        $mailBody,
        $headers,
        $senderEmail !== '' ? '-f ' . $senderEmail : ''
    );
}

function csv_safe_cell($value)
{
    $value = str_replace("\0", '', (string) $value);
    if (preg_match('/^[=+\-@\t\r]/', $value)) {
        $value = "'" . $value;
    }
    return '"' . str_replace('"', '""', $value) . '"';
}

function createCsvData(array $data): string
{
    $headers = array();
    $values = array();
    $delimiter = ';';

    foreach ($data as $label => $value) {
        $headers[] = csv_safe_cell($label);
        $values[] = csv_safe_cell(is_array($value) ? implode(',', $value) : $value);
    }

    return implode($delimiter, $headers) . "\n" . implode($delimiter, $values);
}

function getFormData($formId, $formFieldsConfig): array
{
    $data = [];
    $attachments = [];
    $formData = $_POST['form_' . $formId];
    $formFiles = isset($_FILES['form_' . $formId]) ? $_FILES['form_' . $formId] : [];

    foreach ($formFieldsConfig as $formFieldConfig) {
        $formItemConfig = $formFieldConfig['formItem'];
        if (!$formItemConfig || in_array($formItemConfig['type'], ['captcha', 'button'])) {
            continue;
        }

        $formFieldName = sprintf('ed-f-%d', $formFieldConfig['id']);
        $placeholder = null;
        $label = null;
        $multiple = false;
        foreach ($formItemConfig['values'] as $attributeValue) {
            if ($attributeValue['attribute'] == 14) { // placeholder
                $placeholder = $attributeValue['value'];
            }
            if ($attributeValue['attribute'] == 2) { // label
                $label = $attributeValue['value'];
            }
            if ($attributeValue['attribute'] == 29) { // select-multiple
                $multiple = (bool)$attributeValue['value'];
            }
        }

        if (!$label) {
            $label = $placeholder ?: $formFieldName;
        }


        if ($formItemConfig['type'] === 'upload' && file_exists($formFiles['tmp_name'][$formFieldName])) {
            $attachments[$label] = [
                'name'     => $formFiles['name'][$formFieldName],
                'type'     => $formFiles['type'][$formFieldName],
                'tempFile' => $formFiles['tmp_name'][$formFieldName]
            ];
        } elseif ($formItemConfig['type'] === 'checkbox') {
            $submittedData = $formData[$formFieldName] ? array_diff($formData[$formFieldName], ['']) : [];
            $data[$label] = [];
            foreach ($submittedData as $itemSortOrder) {
                foreach ($formItemConfig['choices'] as $selectChoice) {
                    if ((string)$itemSortOrder === (string)$selectChoice['sort']) {
                        $data[$label][$itemSortOrder] = $selectChoice['value'];
                    }
                }
            }
            $data[$label] = implode(',', $data[$label]);
        } elseif ($formItemConfig['type'] === 'radio') {
            foreach ($formItemConfig['choices'] as $selectChoice) {
                if ($formData[$formFieldName] == $selectChoice['sort']) {
                    $data[$label] = $selectChoice['value'];
                    break;
                }
            }
            continue;
        } elseif ($formItemConfig['type'] === 'select') {
            if ($multiple) {
                $submittedData = isset($formData[$formFieldName]) ? array_diff($formData[$formFieldName], ['']) : [];
                $data[$label] = [];
                foreach ($submittedData as $itemSortOrder) {
                    foreach ($formItemConfig['choices'] as $selectChoice) {
                        if ((string)$itemSortOrder === (string)$selectChoice['sort']) {
                            $data[$label][$itemSortOrder] = $selectChoice['value'];
                        }
                    }
                }
                $data[$label] = implode(',', $data[$label]);
            } else {
                $submittedData = isset($formData[$formFieldName]) ? $formData[$formFieldName] : null;
                $data[$label] = [];
                foreach ($formItemConfig['choices'] as $selectChoice) {
                    if ((string)$submittedData === (string)$selectChoice['sort']) {
                        $data[$label] = $selectChoice['value'];
                        break;
                    }
                }
                continue;
            }
        } elseif (isset($formData[$formFieldName])) {
            $data[$label] = $formData[$formFieldName];
        }
    }

    return [$data, $attachments];
}

function handleFormSubmission()
{
    $apiHost = 'https://api.sitehub.io';
    $httpCode = 400;
    $isValid = false;
    $response = false;

    define('ATTRIBUTE_CAPTCHA_TYPE', 127);
    define('ATTRIBUTE_CAPTCHA_TURNSTILE_SECRET', 130);

    if (!isset($_POST['id']) || !trim((string) $_POST['id'])) {
        return ['Form ID is missing', ['http_code' => 400]];
    }

    $formId = trim((string) $_POST['id']);
    if (!isset($_POST['form_' . $formId]) || !is_array($_POST['form_' . $formId])) {
        return ['Form payload is missing', ['http_code' => 400]];
    }

    $json = curl(sprintf('%s/website/elements/%s', $apiHost, rawurlencode($formId)));
    if (!$json) {
        return ['Could not load form configuration', ['http_code' => 400]];
    }

    $formConfig = json_decode($json, true);
    if (!is_array($formConfig) || empty($formConfig['form']) || !is_array($formConfig['form'])) {
        return ['Invalid form configuration', ['http_code' => 400]];
    }
    $formFieldsConfig = isset($formConfig['children']) && is_array($formConfig['children']) ? $formConfig['children'] : array();

    $sendMail = $formConfig['form']['sendEmail'] ?? false;
    $successMessage = isset($formConfig['form']['successMessage'])
        ? (string) $formConfig['form']['successMessage']
        : 'Thank you!';

    foreach ($_POST['form_' . $formId] as $fieldName => $fieldData) {
        $fieldId = str_replace('ed-f-', '', $fieldName);
        $isTurnstileCaptcha = false;
        $turnstileCaptchaSecret = null;

        foreach ($formFieldsConfig as $formFieldConfig) {
            if ($formFieldConfig['id'] == $fieldId && $formFieldConfig['type'] == 'form-captcha') {
                $formItemConfig = $formFieldConfig['formItem'];
                $attributes = $formItemConfig['values'];
                foreach ($attributes as $attribute) {
                    if ($attribute['attribute'] == ATTRIBUTE_CAPTCHA_TYPE) {
                        $isTurnstileCaptcha = $attribute['value'] === 'external';
                    } elseif ($attribute['attribute'] == ATTRIBUTE_CAPTCHA_TURNSTILE_SECRET) {
                        $turnstileCaptchaSecret = $attribute['value'];
                    }
                }
            }
        }

        if (is_array($fieldData)) {
            if (isset($fieldData['hash']) && isset($fieldData['text'])) {
                $clean = strtoupper(trim((string) $fieldData['text']));
                $hashedText = hash('sha256', $clean);
                if ($hashedText !== $fieldData['hash']) {
                    $isValid = false;
                    $httpCode = 400;
                    $response = 'Wrong security code';
                    break;
                }

                $isValid = true;
                $httpCode = 200;
                $response = '<div class="wv-message wv-success">' . api_escape_html($successMessage) . '</div>';
            }
        } elseif ($isTurnstileCaptcha) {
            $captchaResponse = $fieldData;
            $payload = http_build_query([
                'secret' => $turnstileCaptchaSecret,
                'response' => $captchaResponse,
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $result = curl_exec($ch);
            $httpCodeCaptcha = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $responseCaptcha = json_decode($result, true);
            if (is_array($responseCaptcha) && !($responseCaptcha['success'] ?? false) && !empty($responseCaptcha['error-codes']) && is_array($responseCaptcha['error-codes'])) {
                $isValid = false;
                $httpCode = 400;
                $response = 'Captcha validation failed: ' . api_escape_html(implode(', ', $responseCaptcha['error-codes']));
                break;
            }

            if ($httpCodeCaptcha !== 200 || !$result) {
                $isValid = false;
                $httpCode = 400;
                $response = 'Could not validate captcha';
                break;
            }

            $isValid = true;
            $httpCode = 200;
            $response = '<div class="wv-message wv-success">' . api_escape_html($successMessage) . '</div>';
        }
    }

    if ($isValid) {
        list($data, $attachments) = getFormData($formId, $formFieldsConfig);

        // Persist the message for the admin inbox. A missing DB or table must
        // never break the existing form/email flow.
        contact_store_message($formConfig, $formId, $data, $attachments);

        if (!empty($formConfig['form']['webhookUrl'])) {
            pushWebhook($formConfig, $data, $attachments);
        }

        $redirectTo = isset($formConfig['form']['redirectTo']) ? (string) $formConfig['form']['redirectTo'] : '';
        $isSafeRelativeRedirect = strpos($redirectTo, '/') === 0
            && substr($redirectTo, 0, 2) !== '//'
            && strpos($redirectTo, '\\') === false
            && !preg_match('/[\\x00-\\x1F\\x7F]/', $redirectTo);
        if ($redirectTo !== '' && (api_valid_http_url($redirectTo) || $isSafeRelativeRedirect)) {
            $encodedRedirect = json_encode(
                $redirectTo,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
            $response = '<script type="text/javascript">window.setTimeout(function() { window.location.href=' . $encodedRedirect . '; }, 1000);</script>';
        }

        if ($sendMail) {
            if (!sendFormSummaryByEmail($formConfig, $data, $attachments)) {
                $response = 'Could not send e-mail';
                $httpCode = 400;
            }
        }
    }

    return [$response ?: 'Processing...', ['http_code' => $httpCode]];
}

function pushWebhook($formConfig, array $data, array $attachments)
{
    $formId = isset($formConfig['id']) ? (string) $formConfig['id'] : '';
    $formName = isset($formConfig['form']['name']) ? (string) $formConfig['form']['name'] : '';
    $webhookUrl = isset($formConfig['form']['webhookUrl']) ? (string) $formConfig['form']['webhookUrl'] : '';
    if (!api_valid_http_url($webhookUrl)) {
        return;
    }

    foreach ($attachments as $label => $fileData) {
        if (!is_array($fileData) || !isset($fileData['tempFile']) || !is_file($fileData['tempFile'])) {
            continue;
        }
        $data[$label] = [
            'name' => isset($fileData['name']) ? $fileData['name'] : 'attachment',
            'type' => isset($fileData['type']) ? $fileData['type'] : 'application/octet-stream',
            'body' => filesize($fileData['tempFile']) < 1024 * 1024 * 1024 ? base64_encode(file_get_contents($fileData['tempFile'])) : 'too_large',
        ];
    }

    $payload = http_build_query([
        'data' => json_encode($data),
        'form_id' => $formId,
        'form_name' => $formName,
        'submitted_at' => date('r')
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Length: ' . strlen($payload)]);
    curl_exec($ch);
    curl_close($ch);
}

function forwardToApi($url, $requestMethod, $postData = null)
{
    $apiHost = 'https://api.sitehub.io';
    $contentType = 'application/x-www-form-urlencoded';

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $contentTypeParts = explode(';', $_SERVER['CONTENT_TYPE']);
        $contentType = $contentTypeParts[0];
    }

    $apiUrl = (strpos($url, '/images') === 0 ? 'https://inter-cdn.com' : $apiHost) . $url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

    if ($requestMethod === 'POST') {
        $postData = $postData ?: $_POST;
        curl_setopt($ch, CURLOPT_POST, true);
        switch ($contentType) {
            case 'application/json':
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=UTF-8']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
                break;
            case 'multipart/form-data':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                break;
            default:
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                break;
        }
    }

    $response = curl_exec($ch);
    $headers = curl_getinfo($ch);
    curl_close($ch);
    return [$response, $headers];
}

function curl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    $return = curl_exec($ch);
    curl_close($ch);
    return $return;
}

function handleFormRequest()
{
    $formId = isset($_POST['id']) ? trim((string) $_POST['id']) : '';

    // Global per-IP flood guard for every form id (native and Sitejet-proxied).
    if (contact_rate_limit_exceeded(10, 10)) {
        return array('Too many submissions. Please try again later.', array('http_code' => 429));
    }

    if ($formId === 'contact') {
        $payload = isset($_POST['form_contact']) && is_array($_POST['form_contact'])
            ? $_POST['form_contact']
            : array();
        $native = contact_native_submit($payload);
        return array($native[1], array('http_code' => $native[2]));
    }

    return handleFormSubmission();
}

$requestPath = getApiRequestPath();

if (api_is_public_read_path($requestPath)) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Api-Token, Authorization');
}

if ($requestPath === '/form_container/submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = handleFormRequest();
    $body = $result[0] ?? 'Processing...';
    $code = (int) ($result[1]['http_code'] ?? 200);

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($code);
    echo is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($requestPath === '/websites' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verifyApiToken()) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or missing API token'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'site' => 'otaboyev-sardorbek.uz',
        'token_ok' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($requestPath === '/health' || $requestPath === '/status') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($requestPath === '/bot/health') {
    require __DIR__ . '/backend/bot.php';
    bot_handle_health();
    exit;
}

if ($requestPath === '/bot/webhook') {
    require __DIR__ . '/backend/bot.php';
    bot_handle_webhook();
    exit;
}

if (strpos($requestPath, '/admin') === 0) {
    require __DIR__ . '/backend/admin.php';
    exit;
}

if ($requestPath === '/profile' || $requestPath === '/projects' || preg_match('#^/projects/[a-z0-9-]+/?$#', $requestPath)) {
    require __DIR__ . '/backend/content.php';
    exit;
}

if (preg_match('#^/websites/?$#', $requestPath)) {
    if (!verifyApiToken()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => 'Invalid API token', 'status' => 'error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'websites' => [
            ['id' => 'portfolio-uz', 'name' => 'Sardorbek Otaboyev Portfolio', 'lang' => 'uz', 'url' => 'https://otaboyev-sardorbek.uz/uz/', 'active' => true],
            ['id' => 'portfolio-ru', 'name' => 'Sardorbek Otaboyev Portfolio', 'lang' => 'ru', 'url' => 'https://otaboyev-sardorbek.uz/ru/', 'active' => true],
            ['id' => 'portfolio-en', 'name' => 'Sardorbek Otaboyev Portfolio', 'lang' => 'en', 'url' => 'https://otaboyev-sardorbek.uz/en/', 'active' => true],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($requestPath, '/form_container/submit') !== false) {
    list($response, $headers) = handleFormRequest();
} else {
    list($response, $headers) = forwardToApi($requestPath, $_SERVER['REQUEST_METHOD']);
    $contentType = $headers['content_type'] ?? 'text/plain';
    header('Content-Type: ' . $contentType);

    $cdnHosts = [
        'https://inter-cdn.com',
        'https://cdn1.site-media.eu',
        'https://cdn2.site-media.eu',
        'https://cdn3.site-media.eu',
        'https://cdn4.site-media.eu',
        'https://cdn5.site-media.eu',
        'https://cdn6.site-media.eu',
        'https://cdn7.site-media.eu',
        'https:\/\/inter-cdn.com',
        'https:\/\/cdn1.site-media.eu',
        'https:\/\/cdn2.site-media.eu',
        'https:\/\/cdn3.site-media.eu',
        'https:\/\/cdn4.site-media.eu',
        'https:\/\/cdn5.site-media.eu',
        'https:\/\/cdn6.site-media.eu',
        'https:\/\/cdn7.site-media.eu'
    ];
    $response = str_replace($cdnHosts, '/api.php', $response);
}

if (isset($headers['http_code'])) {
    http_response_code((int) $headers['http_code']);
}

echo $response;
