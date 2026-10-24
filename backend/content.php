<?php
// Public content API. Included from api.php after routing.
// Compatible with PHP 7.1.

require_once __DIR__ . '/config.php';

if (!isset($requestPath)) {
    http_response_code(404);
    exit;
}

function content_lang($value)
{
    $lang = strtolower(substr(trim((string) $value), 0, 2));
    return in_array($lang, array('uz', 'en', 'ru'), true) ? $lang : 'uz';
}

function content_list($value)
{
    $list = json_decode((string) $value, true);
    if (!is_array($list)) {
        return array();
    }

    $clean = array();
    foreach ($list as $item) {
        if (is_string($item) && trim($item) !== '') {
            $clean[] = $item;
        }
    }
    return $clean;
}

function content_tech($value)
{
    return content_list($value);
}

function content_respond($data, $code)
{
    http_response_code((int) $code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    echo json_encode(
        array('status' => 'ok', 'data' => $data),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function content_fail($message, $errorCode, $httpCode)
{
    http_response_code((int) $httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        array('status' => 'error', 'message' => $message, 'code' => $errorCode),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    content_fail('Method not allowed', 'method_not_allowed', 405);
}

$lang = content_lang(isset($_GET['lang']) && is_string($_GET['lang']) ? $_GET['lang'] : 'uz');

try {
    $pdo = content_db();

    if ($requestPath === '/profile') {
        $statement = $pdo->prepare(
            'SELECT p.name, p.email, p.github, p.telegram, p.telegram_handle, p.avatar_url,
                    COALESCE(t.role, u.role) AS role,
                    COALESCE(t.bio, u.bio) AS bio,
                    COALESCE(t.quick_text, u.quick_text) AS quick_text,
                    COALESCE(t.location, u.location) AS location
             FROM profiles p
             LEFT JOIN profile_translations t ON t.profile_id = p.id AND t.lang = :lang
             LEFT JOIN profile_translations u ON u.profile_id = p.id AND u.lang = \'uz\'
             WHERE p.id = 1
             LIMIT 1'
        );
        $statement->execute(array(':lang' => $lang));
        $profile = $statement->fetch();

        if (!$profile || $profile['name'] === null) {
            content_fail('Profile not found', 'profile_not_found', 404);
        }

        content_respond(
            array(
                'name' => $profile['name'],
                'role' => $profile['role'],
                'bio' => $profile['bio'],
                'quick_text' => $profile['quick_text'],
                'location' => $profile['location'],
                'email' => $profile['email'],
                'github' => $profile['github'],
                'telegram' => $profile['telegram'],
                'telegram_handle' => $profile['telegram_handle'],
                'avatar_url' => $profile['avatar_url'],
            ),
            200
        );
    }

    if ($requestPath === '/projects') {
        $statement = $pdo->prepare(
            'SELECT pr.slug, pr.category, pr.repo_url, pr.demo_url, pr.image_url,
                    COALESCE(t.tag, u.tag) AS tag,
                    COALESCE(t.title, u.title) AS title,
                    COALESCE(t.summary, u.summary) AS summary,
                    COALESCE(t.tech, u.tech) AS tech
             FROM projects pr
             LEFT JOIN project_translations t ON t.project_id = pr.id AND t.lang = :lang
             LEFT JOIN project_translations u ON u.project_id = pr.id AND u.lang = \'uz\'
             WHERE pr.is_active = 1
             ORDER BY pr.sort_order ASC, pr.id ASC'
        );
        $statement->execute(array(':lang' => $lang));
        $rows = $statement->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            if ($row['title'] === null) {
                continue;
            }
            $projects[] = array(
                'slug' => $row['slug'],
                'category' => $row['category'],
                'tag' => $row['tag'],
                'title' => $row['title'],
                'summary' => $row['summary'],
                'tech' => content_tech($row['tech']),
                'repo_url' => $row['repo_url'],
                'demo_url' => $row['demo_url'],
                'detail_url' => '/' . $lang . '/projects/' . rawurlencode($row['slug']) . '/',
            );
        }

        content_respond($projects, 200);
    }

    if (preg_match('#^/projects/([a-z0-9-]{1,80})/?$#', $requestPath, $matches)) {
        $statement = $pdo->prepare(
            'SELECT pr.slug, pr.category, pr.repo_url, pr.demo_url, pr.image_url,
                    COALESCE(t.tag, u.tag) AS tag,
                    COALESCE(t.title, u.title) AS title,
                    COALESCE(t.summary, u.summary) AS summary,
                    COALESCE(t.description, u.description) AS description,
                    COALESCE(t.tech, u.tech) AS tech,
                    COALESCE(t.results, u.results) AS results,
                    COALESCE(t.stack_notes, u.stack_notes) AS stack_notes
             FROM projects pr
             LEFT JOIN project_translations t ON t.project_id = pr.id AND t.lang = :lang
             LEFT JOIN project_translations u ON u.project_id = pr.id AND u.lang = \'uz\'
             WHERE pr.slug = :slug AND pr.is_active = 1
             LIMIT 1'
        );
        $statement->execute(array(':lang' => $lang, ':slug' => $matches[1]));
        $row = $statement->fetch();

        if (!$row || $row['title'] === null) {
            content_fail('Project not found', 'project_not_found', 404);
        }

        content_respond(
            array(
                'slug' => $row['slug'],
                'category' => $row['category'],
                'tag' => $row['tag'],
                'title' => $row['title'],
                'summary' => $row['summary'],
                'description' => $row['description'],
                'tech' => content_tech($row['tech']),
                'results' => content_list($row['results']),
                'stack_notes' => content_list($row['stack_notes']),
                'repo_url' => $row['repo_url'],
                'demo_url' => $row['demo_url'],
                'image_url' => $row['image_url'],
                'detail_url' => '/' . $lang . '/projects/' . rawurlencode($row['slug']) . '/',
            ),
            200
        );
    }

    content_fail('Not found', 'not_found', 404);
} catch (Exception $error) {
    error_log('content api error: ' . $error->getMessage());
    content_fail('Database unavailable', 'db_unavailable', 503);
}
