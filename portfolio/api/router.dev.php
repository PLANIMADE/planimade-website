<?php

/**
 * Router für den eingebauten PHP-Entwicklungsserver.
 * Wird nur lokal genutzt (`npm run dev:api`), auf all-inkl übernimmt Apache.
 *
 *   php -S 127.0.0.1:8787 -t . api/router.dev.php
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Hochgeladene Dateien direkt ausliefern.
if (str_starts_with($path, '/uploads/')) {
    $file = __DIR__ . '/..' . $path;
    if (is_file($file)) {
        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'glb' => 'model/gltf-binary',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Access-Control-Allow-Origin: *');
        readfile($file);

        return true;
    }
}

// Einrichtungs-Skripte direkt durchreichen.
if (preg_match('#^/api/scripts/([a-z.]+\.php)$#', $path, $m) === 1 && is_file(__DIR__ . '/scripts/' . $m[1])) {
    require __DIR__ . '/scripts/' . $m[1];

    return true;
}

require __DIR__ . '/index.php';
