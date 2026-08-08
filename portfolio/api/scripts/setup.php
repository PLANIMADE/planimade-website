<?php

/**
 * Einmalige Einrichtung: Datenbank anlegen, Admin-Zugang erstellen, Beispiel-
 * inhalte einspielen.
 *
 * Aufruf lokal:
 *   php api/scripts/setup.php --email=du@example.de --password=GeheimesPasswort --demo
 *
 * Auf all-inkl (SSH-Zugang vorhanden):
 *   php8.4 api/scripts/setup.php --email=... --password=...
 *
 * Ohne SSH: einmalig `api/scripts/setup.web.php` per Browser aufrufen.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/** @var array<string, string|bool> $options */
$options = [];
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $m) === 1) {
        $options[$m[1]] = $m[2] ?? true;
    }
}

$email = (string) ($options['email'] ?? getenv('ADMIN_EMAIL') ?: '');
$password = (string) ($options['password'] ?? getenv('ADMIN_PASSWORD') ?: '');

if ($email === '' || $password === '') {
    fwrite(STDERR, "Bitte --email und --password angeben.\n");
    fwrite(STDERR, "Beispiel: php api/scripts/setup.php --email=du@example.de --password='MeinPasswort123' --demo\n");
    exit(1);
}

$result = portfolio_setup($email, $password, isset($options['demo']), isset($options['force']));

foreach ($result['log'] as $line) {
    echo $line . "\n";
}

echo "\nFertig. Dashboard nach dem Deploy unter /admin/ erreichbar.\n";
