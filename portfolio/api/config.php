<?php

/**
 * Zentrale Konfiguration der Portfolio-API.
 *
 * Nichts hier direkt anpassen – stattdessen `api/.env.php` anlegen
 * (Vorlage: `api/.env.example.php`) und dort nur die Werte überschreiben,
 * die abweichen. Die Datei liegt im .gitignore und bleibt damit privat.
 */

declare(strict_types=1);

$defaults = [
    // 'development' lockert nur die Fehlerausgabe – auf all-inkl bitte 'production'.
    'app_env' => 'production',

    // Vollständige Basis-URL ohne Slash am Ende, z. B. https://dominic-majewski.de
    // Wird für Sitemap, Canonical-URLs und Open-Graph-Bilder gebraucht.
    'site_url' => '',

    // Wohin Kontaktanfragen zusätzlich per Mail gehen sollen.
    'mail_to' => 'hello.dominicmajewski@gmail.com',
    // Absender MUSS bei all-inkl eine Adresse der eigenen Domain sein,
    // sonst wird die Mail verworfen (z. B. website@dominic-majewski.de).
    'mail_from' => '',
    'mail_enabled' => true,

    // Speicherorte
    'storage_path' => __DIR__ . '/storage',
    // Öffentliches Web-Verzeichnis (dort liegen index.html, /admin, /uploads).
    'public_path' => dirname(__DIR__),
    'uploads_path' => dirname(__DIR__) . '/uploads',
    'uploads_url' => '/uploads',

    // Sessions (Login-Dauer in Sekunden – Standard: 14 Tage)
    'session_lifetime' => 60 * 60 * 24 * 14,
    'session_cookie' => 'dm_session',

    // Uploads
    'max_upload_mb' => 200,
    'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'image/svg+xml'],
    'allowed_video_types' => ['video/mp4', 'video/webm', 'video/quicktime'],
    'allowed_model_types' => ['model/gltf-binary', 'model/gltf+json', 'application/octet-stream'],
    // Dokumente für Print-Arbeiten (Broschüren, Booklets) zum Ansehen und Herunterladen.
    'allowed_document_types' => ['application/pdf'],
    'thumb_width' => 640,
    // Breiten, in denen Bilder zusätzlich abgelegt werden (für srcset).
    // Größere Stufen als das Original werden übersprungen.
    'image_widths' => [400, 800, 1600],

    // Wie lange gelöschte Projekte im Papierkorb bleiben.
    'trash_days' => 30,

    // Schutzmechanismen
    'login_max_attempts' => 8,
    'login_decay_minutes' => 15,
    'contact_max_per_hour' => 5,
];

$envFile = __DIR__ . '/.env.php';
$overrides = is_file($envFile) ? require $envFile : [];

return array_replace($defaults, is_array($overrides) ? $overrides : []);
