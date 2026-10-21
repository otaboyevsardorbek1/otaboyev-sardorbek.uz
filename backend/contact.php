<?php
// Contact form persistence. Included from api.php.
// Compatible with PHP 7.1.

require_once __DIR__ . '/config.php';

function contact_form_field_map(array $formConfig)
{
    $map = array();
    if (!isset($formConfig['children']) || !is_array($formConfig['children'])) {
        return $map;
    }

    foreach ($formConfig['children'] as $field) {
        if (!is_array($field) || !isset($field['formItem']) || !is_array($field['formItem'])) {
            continue;
        }
        $item = $field['formItem'];
        $label = '';
        $placeholder = '';
        if (isset($item['values']) && is_array($item['values'])) {
            foreach ($item['values'] as $attribute) {
                if (!is_array($attribute) || !isset($attribute['attribute'])) {
                    continue;
                }
                if ((int) $attribute['attribute'] === 2) {
                    $label = trim((string) $attribute['value']);
                } elseif ((int) $attribute['attribute'] === 14) {
                    $placeholder = trim((string) $attribute['value']);
                }
            }
        }
        if ($label === '') {
            $label = $placeholder !== '' ? $placeholder : 'ed-f-' . (isset($field['id']) ? $field['id'] : '');
        }
        if ($label !== '') {
            $map[$label] = isset($item['type']) ? (string) $item['type'] : '';
        }
    }

    return $map;
}

function contact_is_subject_label($label)
{
    $label = (string) $label;
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
    return strpos($normalized, 'subject') !== false
        || strpos($normalized, 'mavzu') !== false
        || strpos($normalized, 'тема') !== false;
}

function contact_extract_fields(array $formConfig, array $data)
{
    $types = contact_form_field_map($formConfig);
    $result = array(
        'name' => '',
        'email' => '',
        'subject' => '',
        'message' => '',
        'fields' => array(),
    );

    foreach ($data as $label => $value) {
        $label = trim((string) $label);
        if ($label === '') {
            continue;
        }
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = trim((string) $item);
                }
            }
            $value = implode(', ', $parts);
        }
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }

        $type = isset($types[$label]) ? $types[$label] : '';
        $result['fields'][$label] = $value;

        if ($result['subject'] === '' && contact_is_subject_label($label)) {
            $result['subject'] = $value;
        } elseif ($result['email'] === '' && $type === 'email') {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                $result['email'] = $value;
            }
        } elseif ($result['message'] === '' && $type === 'textarea') {
            $result['message'] = $value;
        } elseif ($result['name'] === '' && $type === 'text' && !contact_is_subject_label($label)) {
            $result['name'] = $value;
        }
    }

    if ($result['email'] === '') {
        foreach ($result['fields'] as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                $result['email'] = $value;
                break;
            }
        }
    }
    if ($result['message'] === '') {
        $longest = '';
        foreach ($result['fields'] as $value) {
            if (strlen($value) > strlen($longest)) {
                $longest = $value;
            }
        }
        $result['message'] = $longest;
    }
    if ($result['subject'] === '' && $result['message'] !== '') {
        $result['subject'] = contact_limit_text($result['message'], 120);
    }

    return $result;
}

function contact_limit_text($value, $max)
{
    $value = (string) $value;
    if (strlen($value) <= $max) {
        return $value;
    }
    if (function_exists('mb_strcut')) {
        return mb_strcut($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function contact_ip_hash()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $config = admin_config();
    $secret = isset($config['app_secret']) ? (string) $config['app_secret'] : '';
    return hash('sha256', 'contact|' . $ip . '|' . $secret);
}

function contact_store_message(array $formConfig, $formId, array $data, array $attachments)
{
    try {
        $pdo = content_db();
    } catch (Exception $error) {
        error_log('contact storage unavailable: ' . $error->getMessage());
        return false;
    }

    $extracted = contact_extract_fields($formConfig, $data);
    $payload = array(
        'fields' => $extracted['fields'],
        'attachments' => array(),
    );
    foreach ($attachments as $label => $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $payload['attachments'][] = array(
            'label' => (string) $label,
            'name' => isset($attachment['name']) ? (string) $attachment['name'] : '',
            'type' => isset($attachment['type']) ? (string) $attachment['type'] : '',
            'size' => isset($attachment['tempFile']) && is_file($attachment['tempFile']) ? (int) filesize($attachment['tempFile']) : 0,
        );
    }
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        $encoded = '{}';
    }

    $locale = isset($_POST['lang']) ? strtolower(trim((string) $_POST['lang'])) : '';
    if (!in_array($locale, array('uz', 'en', 'ru'), true)) {
        $locale = null;
    }

    $name = $extracted['name'] !== '' ? contact_limit_text($extracted['name'], 160) : null;
    $email = $extracted['email'] !== '' ? contact_limit_text($extracted['email'], 190) : null;
    $subject = $extracted['subject'] !== '' ? contact_limit_text($extracted['subject'], 255) : null;
    $message = $extracted['message'] !== '' ? contact_limit_text($extracted['message'], 60000) : null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    try {
        $statement = $pdo->prepare(
            'INSERT INTO contact_messages
             (form_id, locale, name, email, subject, message, payload, ip_hash, user_agent)
             VALUES (:form_id, :locale, :name, :email, :subject, :message, :payload, :ip_hash, :user_agent)'
        );
        $statement->execute(array(
            ':form_id' => substr((string) $formId, 0, 80),
            ':locale' => $locale,
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message,
            ':payload' => $encoded,
            ':ip_hash' => contact_ip_hash(),
            ':user_agent' => $userAgent,
        ));
        return (int) $pdo->lastInsertId();
    } catch (Exception $error) {
        error_log('contact storage error: ' . $error->getMessage());
        return false;
    }
}

function contact_native_form_config($lang)
{
    $labels = array(
        'uz' => array('Ism', 'Email', 'Mavzu', 'Xabar'),
        'en' => array('Name', 'Email', 'Subject', 'Message'),
        'ru' => array('Имя', 'Email', 'Тема', 'Сообщение'),
    );
    if (!isset($labels[$lang])) {
        $lang = 'uz';
    }
    $types = array('text', 'email', 'text', 'textarea');
    $children = array();
    foreach ($labels[$lang] as $index => $label) {
        $children[] = array(
            'id' => $index + 1,
            'formItem' => array(
                'type' => $types[$index],
                'values' => array(
                    array('attribute' => 2, 'value' => $label),
                ),
            ),
        );
    }
    return array('children' => $children);
}

function contact_native_submit(array $payload)
{
    $lang = isset($_POST['lang']) ? strtolower(trim((string) $_POST['lang'])) : 'uz';
    if (!in_array($lang, array('uz', 'en', 'ru'), true)) {
        $lang = 'uz';
    }
    $labels = array(
        'uz' => array('Ism', 'Email', 'Mavzu', 'Xabar'),
        'en' => array('Name', 'Email', 'Subject', 'Message'),
        'ru' => array('Имя', 'Email', 'Тема', 'Сообщение'),
    );

    if (!empty($_POST['website'])) {
        return array(false, 'Message rejected.', 400);
    }

    $values = array();
    for ($index = 1; $index <= 4; $index++) {
        $key = 'ed-f-' . $index;
        $value = isset($payload[$key]) && is_scalar($payload[$key]) ? trim((string) $payload[$key]) : '';
        $values[] = $value;
    }
    list($name, $email, $subject, $message) = $values;

    if (strlen($name) < 2 || strlen($name) > 160) {
        return array(false, 'Please enter your name.', 422);
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
        return array(false, 'Please enter a valid email address.', 422);
    }
    if (strlen($subject) < 2 || strlen($subject) > 255) {
        return array(false, 'Please enter a subject.', 422);
    }
    if (strlen($message) < 2 || strlen($message) > 60000) {
        return array(false, 'Please enter a message.', 422);
    }

    try {
        $pdo = content_db();
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM contact_messages
             WHERE form_id = :form_id AND ip_hash = :ip_hash
               AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $statement->execute(array(':form_id' => 'contact', ':ip_hash' => contact_ip_hash()));
        if ((int) $statement->fetchColumn() >= 5) {
            return array(false, 'Too many messages. Please try again later.', 429);
        }
    } catch (Exception $error) {
        error_log('contact rate check unavailable: ' . $error->getMessage());
    }

    $formConfig = contact_native_form_config($lang);
    $data = array(
        $labels[$lang][0] => $name,
        $labels[$lang][1] => $email,
        $labels[$lang][2] => $subject,
        $labels[$lang][3] => $message,
    );
    if (!contact_store_message($formConfig, 'contact', $data, array())) {
        return array(false, 'Message could not be saved. Please try again or email directly.', 503);
    }

    return array(true, 'Message sent successfully.', 200);
}
