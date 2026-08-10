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

// Das ausgelieferte Verzeichnis kommt vom Server (`php -S ... -t <ordner>`).
// Fest auf `deploy/` zu zeigen wäre falsch, sobald man eine Kopie prüft –
// dann liefe die API gegen die eine und die Website gegen die andere.
$deploy = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($deploy === '' || !is_dir($deploy)) {
    $deploy = dirname(__DIR__) . '/deploy';
}
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

// 3. API – vorhandene PHP-Dateien direkt ausführen, alles andere über den
//    Front-Controller. Genau die Reihenfolge steht auch in api/.htaccess –
//    ohne diese Unterscheidung würde die Vorschau etwas anderes zeigen als
//    der echte Server.
if (str_starts_with($path, '/api/')) {
    $ziel = $deploy . $path;
    if (is_file($ziel) && str_ends_with($ziel, '.php')) {
        require $ziel;

        return true;
    }

    require $deploy . '/api/index.php';

    return true;
}

// 4. Vorhandene Dateien direkt ausliefern
$file = $deploy . $path;
if (is_file($file)) {
    /*
     * Videos brauchen Teilabrufe (`Range`). Apache beherrscht die von Haus
     * aus, der eingebaute PHP-Server nicht: Dort meldet der Browser das Video
     * als nicht spulbar, das Standbild bleibt beim ersten Bild stehen und die
     * Vorschau zeigt etwas anderes als der echte Server. Deshalb hier von
     * Hand – nur für Videos, alles andere geht den kurzen Weg.
     */
    if (preg_match('/\.(mp4|webm|mov|m4v)$/i', $file) === 1) {
        $groesse = filesize($file) ?: 0;
        $typ = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };

        header('Content-Type: ' . $typ);
        header('Accept-Ranges: bytes');

        $range = $_SERVER['HTTP_RANGE'] ?? '';
        $von = 0;
        $bis = $groesse - 1;

        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $treffer) === 1) {
            $von = $treffer[1] === '' ? 0 : (int) $treffer[1];
            $bis = $treffer[2] === '' ? $groesse - 1 : (int) $treffer[2];
            $bis = min($bis, $groesse - 1);

            http_response_code(206);
            header("Content-Range: bytes {$von}-{$bis}/{$groesse}");
        }

        header('Content-Length: ' . ($bis - $von + 1));

        $zeiger = fopen($file, 'rb');
        fseek($zeiger, $von);
        echo fread($zeiger, $bis - $von + 1) ?: '';
        fclose($zeiger);

        return true;
    }

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
