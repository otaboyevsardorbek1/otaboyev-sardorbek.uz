<?php
// Router for PHP's built-in server. It keeps local preview behavior close to
// the Apache deployment: deny private paths, return a real 404 page and keep
// the admin/project fallback routes working.
$root = realpath(dirname(__DIR__) . '/out');
if ($root === false) {
    http_response_code(500);
    echo 'out/ topilmadi';
    return true;
}

$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';
if ($path === '' || strpos($path, "\0") !== false) {
    http_response_code(400);
    echo 'Bad request';
    return true;
}
if (strpos($path, '\\') !== false) {
    http_response_code(400);
    echo 'Bad request';
    return true;
}

$segments = explode('/', trim($path, '/'));
if (in_array('..', $segments, true)) {
    http_response_code(400);
    echo 'Bad request';
    return true;
}

$blockedFile = '#(^|/)(config\.local\.php|config\.local\.js|github_keys\.txt|composer\.(json|lock)|package(-lock)?\.json|next\.config\.js|CodeAnalyzer_v2\.py)$#i';
$hidden = '#(^|/)\.(?!well-known(?:/|$))#';
$sourceDir = '#^/(backend|database|app|components|content|scripts|node_modules)(/|$)#i';
$sensitiveExt = '#\.(sql|log|bak|md)$#i';
if (preg_match($blockedFile, $path) || preg_match($hidden, $path) || preg_match($sourceDir, $path) || preg_match($sensitiveExt, $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    return true;
}

if ($path === '/api.php' || strpos($path, '/api.php/') === 0) {
    if (is_file($root . '/api.php')) {
        return false;
    }
}

$target = realpath($root . $path);
if ($target !== false) {
    if ($target !== $root && strpos($target, $root . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(403);
        echo 'Forbidden';
        return true;
    }
    if (is_file($target)) {
        return false;
    }
    if (is_dir($target)) {
        return false;
    }
}

// Admin-created project slugs have no static directory; send them to the
// client-rendered fallback route used by Apache as well.
if (preg_match('#^/(uz|en|ru)/projects/([a-z0-9-]+)/?$#', $path, $matches)) {
    header('Location: /' . $matches[1] . '/projects/view/?slug=' . rawurlencode($matches[2]), true, 302);
    return true;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    if (is_file($root . '/404.html')) {
        readfile($root . '/404.html');
    } else {
        echo 'Not found';
    }
}
return true;
