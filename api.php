<?php
// Compatible with PHP 7.1

// Do not expose stack traces or paths on production responses.
if (getenv('APP_ENV') !== 'local') {
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/backend/contact.php';

// Parse the URL part to be proxied
$url = str_replace('/' . basename(__FILE__), '', $_SERVER['REQUEST_URI']);
$requestMethod = $_SERVER["REQUEST_METHOD"]; // get the request method
$host = $_SERVER['HTTP_HOST'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$contentType = '';

$response = '';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $optionsPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    if (strpos((string) $optionsPath, '/admin') === false) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Api-Token, Authorization');
    }
    http_response_code(204);
    exit;
}

function getApiRequestPath(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';

    if (strpos($path, '/' . basename(__FILE__)) !== false) {
        $path = str_replace('/' . basename(__FILE__), '', $path);
    }

    return '/' . trim($path, '/');
}

function getConfiguredApiToken(): string
{
    $token = getenv('API_TOKEN') ?: getenv('SITEHUB_API_TOKEN') ?: getenv('OTABOYEV_API_TOKEN');
    if (is_string($token) && trim($token) !== '') {
        return trim($token);
    }

    // Local fallback for development only. Set a real token in the environment on production.
    return 'otaboyev-dev-token';
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

    if ($actual === null) {
        return false;
    }

    return hash_equals($expected, $actual);
}

function sendFormSummaryByEmail(array $formConfig, array $data, array $attachments)
{
    $formId = $formConfig['id'];
    $destinationEmail = $formConfig['form']['emailTo'] ? $formConfig['form']['emailTo'] : 'host9590@otaboyev-sardorbek.uz';
    $senderEmail = $formConfig['form']['emailFrom'];
    $replyToEmail = null;
    $subject = $formConfig['form']['emailSubject'];
    $textHeader = $formConfig['form']['emailText'];
    $htmlSummary = '';
    $formData = $_POST['form_' . $formId];
    $formFieldsConfig = $formConfig['children'];

    foreach ($formFieldsConfig as $formFieldConfig) {
        $formItemConfig = $formFieldConfig['formItem'];
        $formFieldName = sprintf('ed-f-%d', $formFieldConfig['id']);
        if ($formItemConfig &&
            $formItemConfig['type'] == 'email' &&
            filter_var(
                $formData[$formFieldName],
                FILTER_VALIDATE_EMAIL
            ) !== false) {
            $replyToEmail = $formData[$formFieldName];
        }
    }

    foreach ($data as $label => $value) {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        if (strpos($label, 'ed-f') === 0) {
            $label = '';
        }
        $htmlSummary .= sprintf('<strong>%s</strong><br />%s<br /><br />', $label, $value);
    }

    foreach ($attachments as $label => $attachment) {
        if (strpos($label, 'ed-f') === 0) {
            $label = '';
        }
        $htmlSummary .= sprintf('<strong>%s</strong><br />%s<br /><br />', $label, $attachment['name']);
    }

    $mailBody = sprintf(
        "<html><body>%s<br/><br/>%s</body></html>",
        $textHeader,
        $htmlSummary
    );

    //header
    $headers = "MIME-Version: 1.0\r\n"; // Defining the MIME version
    if ($senderEmail) {
        $headers .= "From: $senderEmail" . "\r\n"; // Sender Email
    }
    if ($replyToEmail) {
        $headers .= "Reply-To: $replyToEmail" . "\r\n"; // Email address to reach back
    }
    if ($formConfig['form']['sendCsv']) {
        $attachments[] = [
            'type' => 'text/csv',
            'name' => 'form-data.csv',
            'data' => createCsvData($data)
        ];
    }

    if (count($attachments)) {
        $boundary = md5("boundary"); // define boundary with a md5 hashed value
        $headers .= "Content-Type: multipart/mixed;"; // Defining Content-Type
        $headers .= "boundary = $boundary" . "\r\n"; //Defining the Boundary

        //html
        $body = "--$boundary\r\n";
        $body .= "Content-type: text/html; charset=utf-8" . "\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($mailBody));

        //attachment
        foreach ($attachments as $attachment) {
            $fileType = $attachment['type'];
            $fileName = $attachment['name'];

            $fileContent = $attachment['data'] ?? file_get_contents($attachment['tempFile']);
            $encodedFileContent = chunk_split(base64_encode($fileContent));

            $body .= "--$boundary\r\n";
            $body .= "Content-Type: $fileType; name=" . $fileName . "\r\n";
            $body .= "Content-Disposition: attachment; filename=" . $fileName . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "X-Attachment-Id: " . rand(1000, 99999) . "\r\n\r\n";
            $body .= $encodedFileContent; // Attaching the encoded file with email
        }

        $mailBody = $body;
    } else {
        $headers .= "Content-type: text/html; charset=utf-8" . "\r\n";
    }

    return mail($destinationEmail, $subject, $mailBody, $headers, $senderEmail ? "-f $senderEmail" : "");
}

function createCsvData(array $data): string
{
    $headers = [];
    $values = [];
    $delimiter = ';';
    $enclosure = '"';

    foreach ($data as $label => $value) {
        $headers[] = str_replace($enclosure, '\\' . $enclosure, $label);
        $values[] = str_replace($enclosure, '\\' . $enclosure, is_array($value) ? implode(',', $value) : $value);
    }

    return $enclosure . implode(
            $enclosure . $delimiter . $enclosure,
            $headers
        ) . $enclosure . "\n" . $enclosure . implode(
            $enclosure . $delimiter . $enclosure,
            $values
        ) . $enclosure;
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
    if (!is_array($formConfig) || empty($formConfig['form'])) {
        return ['Invalid form configuration', ['http_code' => 400]];
    }

    $sendMail = $formConfig['form']['sendEmail'] ?? false;

    foreach ($_POST['form_' . $formId] as $fieldName => $fieldData) {
        $fieldId = str_replace('ed-f-', '', $fieldName);
        $isTurnstileCaptcha = false;
        $turnstileCaptchaSecret = null;

        foreach ($formConfig['children'] as $formFieldConfig) {
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
                $response = sprintf('<div class="wv-message wv-success">%s</div>', $formConfig['form']['successMessage']);
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
                $response = 'Captcha validation failed: ' . implode(', ', $responseCaptcha['error-codes']);
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
            $response = sprintf('<div class="wv-message wv-success">%s</div>', $formConfig['form']['successMessage']);
        }
    }

    if ($isValid) {
        $formFieldsConfig = $formConfig['children'];
        list($data, $attachments) = getFormData($formId, $formFieldsConfig);

        // Persist the message for the admin inbox. A missing DB or table must
        // never break the existing form/email flow.
        contact_store_message($formConfig, $formId, $data, $attachments);

        if (!empty($formConfig['form']['webhookUrl'])) {
            pushWebhook($formConfig, $data, $attachments);
        }

        if (!empty($formConfig['form']['redirectTo']) && is_string($formConfig['form']['redirectTo'])) {
            $response = '<script type="text/javascript">window.setTimeout(function() { window.location.href=' . json_encode($formConfig['form']['redirectTo']) . '; }, 1000);</script>';
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
    $formId = $formConfig['id'];
    $formName = $formConfig['form']['name'];

    foreach ($attachments as $label => $fileData) {
        $data[$label] = [
            'name' => $fileData['name'],
            'type' => $fileData['type'],
            'body' => filesize($fileData['tempFile']) < 1024 * 1024 * 1024 ? base64_encode(file_get_contents($fileData['tempFile'])) : 'too_large',
        ];
    }

    $payload = http_build_query([
        'data' => json_encode($data),
        'form_id' => $formId,
        'form_name' => $formName,
        'submitted_at' => date('r')
    ]);

    $ch = curl_init($formConfig['form']['webhookUrl']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    $return = curl_exec($ch);
    curl_close($ch);
    return $return;
}

function handleFormRequest()
{
    $formId = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
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

if (strpos($requestPath, '/admin') !== 0) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Api-Token, Authorization');
}

if ($requestPath === '/form_container/submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = handleFormRequest();
    $body = $result[0] ?? 'Processing...';
    $code = (int) ($result[1]['http_code'] ?? 200);

    header('Content-Type: text/html; charset=utf-8');
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
