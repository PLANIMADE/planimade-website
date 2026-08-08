<?php

/**
 * Vorlage für die private Konfiguration.
 *
 * Kopiere diese Datei nach `api/.env.php` und trage deine Werte ein.
 * Nur Werte angeben, die vom Standard in `config.php` abweichen sollen.
 */

declare(strict_types=1);

return [
    'app_env' => 'production',
    'site_url' => 'https://dominic-majewski.de',

    // Absender muss bei all-inkl eine echte Adresse der eigenen Domain sein.
    'mail_from' => 'website@dominic-majewski.de',
    'mail_to' => 'hello.dominicmajewski@gmail.com',
];
