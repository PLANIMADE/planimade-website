<?php

/**
 * Lokale Produktions-Vorschau.
 *
 * Startet den fertigen `deploy/`-Ordner so, wie ihn all-inkl später ausliefert –
 * inklusive der Weiterleitungen aus der .htaccess (die der eingebaute
 * PHP-Server nicht selbst versteht).
 *
 *   php -S 127.0.0.1:8080 -t deploy scripts/preview.php
 *
 * Danach: http://127.0.0.1:8080
 */

declare(strict_types=1);

$deploy = dirname(__DIR__) . '/deploy';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// 1. Case-Studies über PHP ausliefern (wie die RewriteRule in der .htaccess)
if (preg_match('#^/work/([a-zA-Z0-9-]+)/?$#', $path, $m) === 1 && $m[1] !== 'detail') {
    $_GET['_page'] = 'work/' . $m[1];
    require $deploy . '/api/index.php';

    return true;
}

// 2. Sitemap
if ($path === '/sitemap.xml') {
    $_GET['_page'] = 'sitemap';
    require $deploy . '/api/index.php';

    return true;
}

// 3. API
if (str_starts_with($path, '/api/')) {
    require $deploy . '/api/index.php';

    return true;
}

// 4. Vorhandene Dateien direkt ausliefern
$file = $deploy . $path;
if (is_file($file)) {
    return false;
}

// 5. Verzeichnis-Index (/about/ → /about/index.html)
$index = rtrim($file, '/') . '/index.html';
if (is_file($index)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($index);

    return true;
}

// 6. Alles andere: 404-Seite
http_response_code(404);
if (is_file($deploy . '/404.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($deploy . '/404.html');
}

return true;
