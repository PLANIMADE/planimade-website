<?php

/**
 * Front-Controller der Portfolio-API.
 *
 * Alle Anfragen unter /api/... landen hier (siehe .htaccess). Zusätzlich
 * werden /work/<slug> und /sitemap.xml hierher umgeleitet, damit Case-Studies
 * serverseitig mit Meta-Tags ausgeliefert werden.
 */

declare(strict_types=1);

use App\Analytics;
use App\Auth;
use App\Bewerbung;
use App\Database;
use App\Http;
use App\Media;
use App\Messages;
use App\Pages;
use App\Projects;
use App\Router;
use App\Security;
use App\ServerFiles;
use App\Settings;
use App\SocialCard;
use App\SystemCheck;
use App\Testimonials;

$config = require __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$isDev = $config['app_env'] === 'development';
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting($isDev ? E_ALL : E_ALL & ~E_DEPRECATED);

set_exception_handler(static function (Throwable $e) use ($isDev, $config): void {
    @file_put_contents(
        $config['storage_path'] . '/error.log',
        sprintf("[%s] %s in %s:%d\n", gmdate('c'), $e->getMessage(), $e->getFile(), $e->getLine()),
        FILE_APPEND
    );

    Http::json([
        'error' => $isDev ? $e->getMessage() : 'Interner Serverfehler. Details stehen in api/storage/error.log.',
        'trace' => $isDev ? explode("\n", $e->getTraceAsString()) : null,
    ], 500);
});

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/*
 * Fehlende Serverdateien ergänzen.
 *
 * `.htaccess` beginnt mit einem Punkt und gilt damit als versteckt – die
 * meisten FTP-Programme lassen solche Dateien beim Hochladen aus. Ohne sie
 * wären Datenbank und Protokolle über die Adresszeile abrufbar, und keine
 * Anfrage erreichte diesen Front-Controller auf dem üblichen Weg.
 *
 * Der Regelfall kostet vier Dateiabfragen. Geschrieben wird nur, was fehlt –
 * eigene Anpassungen bleiben unangetastet.
 */
if (!ServerFiles::complete($config)) {
    ServerFiles::ensure($config);
}

// Container – bewusst simpel, ohne DI-Framework.
$db = new Database($config);

// Adresse und Mailversand werden im Dashboard gepflegt. Sie müssen deshalb
// feststehen, bevor die übrigen Dienste ihre Konfiguration bekommen.
$settings = new Settings($db, $config);
$config = Settings::applyToConfig($config, $settings->all());

$security = new Security($db, $config);
$auth = new Auth($db, $security, $config);
$projects = new Projects($db, $config);
$media = new Media($db, $config);
$messages = new Messages($db, $security, $config);
$analytics = new Analytics($db, $security);
$testimonials = new Testimonials($db, $config);
$socialCard = new SocialCard($config);
$systemCheck = new SystemCheck($db, $config);
$bewerbung = new Bewerbung($db, $config);
$pages = new Pages($projects, $settings, $analytics, $config);

// Seiten-Routen (kommen per Rewrite aus dem Root-.htaccess)
$page = (string) Http::query('_page', '');
if ($page !== '') {
    if ($page === 'sitemap') {
        $pages->sitemap();
    }
    if (str_starts_with($page, 'work/')) {
        $pages->project(substr($page, 5));
    }
    Http::error('Unbekannte Seite.', 404);
}

$router = new Router();

// ---------------------------------------------------------------- öffentlich

$router->get('health', static function () use ($db): void {
    Http::json([
        'ok' => true,
        'php' => PHP_VERSION,
        'gd' => function_exists('imagewebp'),
        'projects' => (int) $db->value('SELECT COUNT(*) FROM projects'),
        'time' => gmdate('c'),
    ]);
});

$router->get('projects', static function () use ($projects, $auth): void {
    $includeDrafts = $auth->user() !== null && Http::query('drafts') === '1';
    Http::json([
        'projects' => $projects->list($includeDrafts, (string) Http::query('category', '') ?: null),
        'categories' => $projects->categories(),
    ]);
});

$router->get('projects/{slug}', static function (string $slug) use ($projects, $auth): void {
    $project = $projects->findBySlug($slug, $auth->user() !== null);
    if ($project === null) {
        Http::error('Projekt nicht gefunden.', 404);
    }
    Http::json(['project' => $project]);
});

$router->get('settings', static function () use ($settings, $auth): void {
    $data = $settings->all();

    if ($auth->user() === null) {
        // Bewerbungsdaten (Anschrift, Telefon, Geburtsjahr, Kurzprofil) haben
        // in einer offenen Antwort nichts verloren – sie gehören nur dir.
        unset($data['cv']);

        // Ebenso die Versanddaten: `mailTo` ist der private Posteingang, eine
        // offen abrufbare Adresse steht in Minuten in jeder Spam-Liste – und
        // seit dem eigenen Postfach steht hier auch ein Passwort. Deshalb
        // wird nicht einzeln entfernt, sondern nur durchgelassen, was
        // unbedenklich ist: die Adresse der Website, die ohnehin im Browser
        // steht. Kommt später ein Feld dazu, ist es damit automatisch dicht.
        $data['site'] = ['url' => $data['site']['url'] ?? ''];
    }

    Http::json(['settings' => $data]);
});

/**
 * Alles, was der Bewerbungs-Lebenslauf braucht – nur für dich.
 *
 * Die Seite /lebenslauf/ ist zwar statisch ausgeliefert, bleibt ohne diese
 * Antwort aber leer: Ohne Login gibt es hier 401 und damit kein Dokument.
 */
$router->get('cv', static function () use ($settings, $projects, $auth): void {
    $auth->requireUser();

    // Die Projekte kommen gleich mit: Der Abschnitt „Ausgewählte Projekte"
    // soll die echten Arbeiten aus dem Portfolio zeigen und nicht verlangen,
    // dass dieselben Projekte ein zweites Mal im Werdegang stehen.
    Http::json([
        'settings' => $settings->all(),
        'projects' => $projects->list(),
    ]);
});

$router->get('testimonials', static function () use ($testimonials, $auth): void {
    Http::json(['testimonials' => $testimonials->list($auth->user() !== null)]);
});

$router->post('contact', static function () use ($messages): void {
    Http::json($messages->submit(Http::body()));
});

$router->post('events', static function () use ($analytics, $settings): void {
    $features = $settings->get('features', []);
    if (($features['analytics'] ?? true) === true) {
        $analytics->track(Http::body());
    }
    Http::noContent();
});

// --------------------------------------------------------------------- login

/**
 * Erste Einrichtung – findet im Dashboard statt, nicht in einer eigenen Datei.
 *
 * Solange kein Zugang existiert, zeigt `/admin/` statt des Logins ein Formular
 * zum Anlegen. Damit gibt es genau eine Adresse, die man sich merken muss.
 */
$router->get('auth/setup', static function () use ($db): void {
    Http::json(['required' => (int) $db->value('SELECT COUNT(*) FROM users') === 0]);
});

$router->post('auth/setup', static function () use ($db, $auth, $security): void {
    // Nur solange es niemanden gibt. Danach ist der Weg dauerhaft zu –
    // sonst könnte sich jeder einen zweiten Zugang anlegen.
    if ((int) $db->value('SELECT COUNT(*) FROM users') > 0) {
        Http::error('Es existiert bereits ein Zugang. Bitte einloggen.', 409);
    }

    // Auch der erste Aufruf wird gebremst: Ohne Grenze liesse sich das
    // Formular als Weg nutzen, den Server mit Passwort-Hashes zu beschäftigen.
    if (!$security->attempt('setup:' . $security->ipHash(), 10, 900)) {
        Http::error('Zu viele Versuche. Bitte in einer Viertelstunde erneut probieren.', 429);
    }

    $body = Http::body();
    $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    $name = trim((string) ($body['name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Http::error('Bitte eine gültige E-Mail-Adresse angeben.', 422, ['email' => 'Ungültige Adresse.']);
    }
    if (mb_strlen($password) < 10) {
        Http::error('Das Passwort braucht mindestens 10 Zeichen.', 422, ['password' => 'Zu kurz.']);
    }

    $db->insert('users', [
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name !== '' ? $name : 'Portfolio',
        'created_at' => gmdate('c'),
    ]);

    if (($body['demo'] ?? false) === true) {
        require_once __DIR__ . '/scripts/bootstrap.php';
        portfolio_seed_demo($db);
    }

    // Direkt anmelden – ein zweites Formular gleich danach wäre reine Schikane.
    Http::json($auth->login($email, $password));
});

$router->post('auth/login', static function () use ($auth): void {
    $body = Http::body();
    Http::json($auth->login((string) ($body['email'] ?? ''), (string) ($body['password'] ?? '')));
});

$router->post('auth/logout', static function () use ($auth): void {
    $auth->logout();
    Http::json(['ok' => true]);
});

$router->get('auth/me', static function () use ($auth): void {
    $user = $auth->user();
    if ($user === null) {
        Http::json(['user' => null], 200);
    }
    Http::json(['user' => $auth->publicUser($user), 'csrfToken' => $auth->csrfToken()]);
});

$router->post('auth/password', static function () use ($auth): void {
    $user = $auth->requireWrite();
    $body = Http::body();
    $auth->changePassword($user, (string) ($body['current'] ?? ''), (string) ($body['next'] ?? ''));
    Http::json(['ok' => true]);
});

// ------------------------------------------------------------------ geschützt

$router->post('projects', static function () use ($projects, $auth, $socialCard): void {
    $auth->requireWrite();
    $project = $projects->create(Http::body());
    $socialCard->generate($project);

    Http::json(['project' => $project], 201);
});

$router->put('projects/{id}', static function (string $id) use ($projects, $auth, $socialCard): void {
    $auth->requireWrite();
    $project = $projects->update((int) $id, Http::body());
    // Vorschaubild bei jedem Speichern neu erzeugen – Titel oder Titelbild
    // können sich geändert haben.
    $socialCard->generate($project);

    Http::json(['project' => $project]);
});

$router->delete('projects/{id}', static function (string $id) use ($projects, $auth): void {
    $auth->requireWrite();
    $projects->delete((int) $id);
    Http::json(['ok' => true]);
});

// ------------------------------------------------------------------ Papierkorb

$router->get('trash', static function () use ($projects, $auth): void {
    $auth->requireUser();
    Http::json(['projects' => $projects->trash()]);
});

$router->post('trash/{id}/restore', static function (string $id) use ($projects, $auth): void {
    $auth->requireWrite();
    Http::json(['project' => $projects->restore((int) $id)]);
});

$router->delete('trash/{id}', static function (string $id) use ($projects, $auth, $socialCard, $db): void {
    $auth->requireWrite();

    $row = $db->first('SELECT slug FROM projects WHERE id = ?', [(int) $id]);
    $projects->purge((int) $id);
    if ($row !== null) {
        $socialCard->delete((string) $row['slug']);
    }

    Http::json(['ok' => true]);
});

$router->post('projects-reorder', static function () use ($projects, $auth): void {
    $auth->requireWrite();
    $ids = Http::input('ids', []);
    if (!is_array($ids)) {
        Http::error('Erwartet wird eine Liste von IDs.', 422);
    }
    $projects->reorder(array_map('intval', $ids));
    Http::json(['ok' => true]);
});

$router->get('media', static function () use ($media, $auth): void {
    $auth->requireUser();
    Http::json([
        'media' => $media->list(
            (string) Http::query('kind', '') ?: null,
            (int) Http::query('limit', 200),
            (int) Http::query('offset', 0),
            Http::query('missingAlt') === '1'
        ),
    ]);
});

$router->post('media', static function () use ($media, $auth): void {
    $auth->requireWrite();
    if (!isset($_FILES['file'])) {
        Http::error('Keine Datei erhalten. Feldname muss "file" sein.', 422);
    }
    Http::json(['media' => $media->store($_FILES['file'], (string) ($_POST['alt'] ?? ''))], 201);
});

$router->patch('media/{id}', static function (string $id) use ($media, $auth): void {
    $auth->requireWrite();
    Http::json(['media' => $media->updateAlt((int) $id, (string) Http::input('alt', ''))]);
});

$router->delete('media/{id}', static function (string $id) use ($media, $auth): void {
    $auth->requireWrite();
    $media->delete((int) $id);
    Http::json(['ok' => true]);
});

$router->get('messages', static function () use ($messages, $auth): void {
    $auth->requireUser();
    Http::json([
        'messages' => $messages->list((string) Http::query('status', '') ?: null),
        'unread' => $messages->unreadCount(),
    ]);
});

$router->patch('messages/{id}', static function (string $id) use ($messages, $auth): void {
    $auth->requireWrite();
    $messages->setStatus((int) $id, (string) Http::input('status', 'read'));
    Http::json(['ok' => true]);
});

$router->delete('messages/{id}', static function (string $id) use ($messages, $auth): void {
    $auth->requireWrite();
    $messages->delete((int) $id);
    Http::json(['ok' => true]);
});

$router->put('settings', static function () use ($settings, $auth): void {
    $auth->requireWrite();
    Http::json(['settings' => $settings->save(Http::body())]);
});

$router->post('testimonials', static function () use ($testimonials, $auth): void {
    $auth->requireWrite();
    Http::json(['testimonial' => $testimonials->save(null, Http::body())], 201);
});

$router->put('testimonials/{id}', static function (string $id) use ($testimonials, $auth): void {
    $auth->requireWrite();
    Http::json(['testimonial' => $testimonials->save((int) $id, Http::body())]);
});

$router->delete('testimonials/{id}', static function (string $id) use ($testimonials, $auth): void {
    $auth->requireWrite();
    $testimonials->delete((int) $id);
    Http::json(['ok' => true]);
});

$router->get('stats', static function () use ($analytics, $projects, $messages, $auth, $db): void {
    $auth->requireUser();
    Http::json([
        'analytics' => $analytics->summary((int) Http::query('days', 30)),
        'counts' => [
            'projects' => (int) $db->value('SELECT COUNT(*) FROM projects'),
            'published' => (int) $db->value("SELECT COUNT(*) FROM projects WHERE status = 'published'"),
            'drafts' => (int) $db->value("SELECT COUNT(*) FROM projects WHERE status = 'draft'"),
            'media' => (int) $db->value('SELECT COUNT(*) FROM media'),
            'unreadMessages' => $messages->unreadCount(),
        ],
        'mostViewed' => array_slice(array_map(
            static fn (array $p): array => ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'views' => $p['views']],
            (static function (array $list): array {
                usort($list, static fn (array $a, array $b): int => $b['views'] <=> $a['views']);

                return $list;
            })($projects->list(true))
        ), 0, 5),
    ]);
});

// ----------------------------------------------------------------- Systemcheck

$router->get('system', static function () use ($systemCheck, $auth): void {
    $auth->requireUser();
    Http::json($systemCheck->run());
});

$router->post('system/mail-test', static function () use ($systemCheck, $auth): void {
    $auth->requireWrite();
    Http::json($systemCheck->sendTestMail());
});

// ---------------------------------------------------------- Bewerbungs-Radar
//
// Alles hier verlangt einen Login. Die Liste enthält Kontaktdaten und den
// laufenden Bewerbungsstand – sie geht niemanden sonst etwas an. Deshalb
// steht `requireUser()` in jeder einzelnen Route und nicht nur davor: Eine
// vergessene Zeile fällt sonst niemandem auf.

$router->get('bewerbung', static function () use ($bewerbung, $auth): void {
    $auth->requireUser();
    Http::json($bewerbung->alles());
});

/** Mehrere auf einmal – der Import aus einer Tabelle. */
$router->post('bewerbung/eintraege', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    $zeilen = Http::input('zeilen', []);
    if (!is_array($zeilen) || $zeilen === []) {
        Http::error('Die Tabelle enthielt keine Zeilen.', 422);
    }
    Http::json($bewerbung->anlegenViele($zeilen), 201);
});

$router->post('bewerbung/eintrag', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json(['eintrag' => $bewerbung->anlegen(Http::body())], 201);
});

/** Status, Notiz, Kontaktdatum – das, was beim Arbeiten entsteht. */
$router->put('bewerbung/eintrag/{id}', static function (string $id) use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json(['eintrag' => $bewerbung->merken($id, Http::body())]);
});

/** Stammdaten (Name, Adresse, Schwerpunkte). */
$router->put('bewerbung/eintrag/{id}/daten', static function (string $id) use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json(['eintrag' => $bewerbung->bearbeiten($id, Http::body())]);
});

$router->delete('bewerbung/eintrag/{id}', static function (string $id) use ($bewerbung, $auth): void {
    $auth->requireWrite();
    $bewerbung->loeschen($id);
    Http::noContent();
});

/** Ergänzt neue Einträge aus der mitgelieferten Datei. */
$router->post('bewerbung/nachschub', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json($bewerbung->nachschub());
});

/** Übernimmt eine JSON-Sicherung aus der früheren Einzeldatei. */
$router->post('bewerbung/import', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json($bewerbung->importieren(Http::body()));
});

$router->put('bewerbung/vorlage', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json(['vorlage' => $bewerbung->vorlageSpeichern(Http::body())]);
});

$router->put('bewerbung/versand', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json(['versand' => $bewerbung->versandSpeichern(Http::body())]);
});

$router->post('bewerbung/versand/test', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json($bewerbung->versandTest());
});

$router->post('bewerbung/senden', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    $ids = Http::input('ids', []);
    if (!is_array($ids) || $ids === []) {
        Http::error('Keine Empfänger ausgewählt.', 422);
    }
    Http::json($bewerbung->senden($ids));
});

$router->post('bewerbung/dateien', static function () use ($bewerbung, $auth): void {
    $auth->requireWrite();
    if (!isset($_FILES['file'])) {
        Http::error('Keine Datei empfangen.', 422);
    }
    Http::json($bewerbung->dateiHochladen($_FILES['file']), 201);
});

$router->delete('bewerbung/dateien/{name}', static function (string $name) use ($bewerbung, $auth): void {
    $auth->requireWrite();
    Http::json($bewerbung->dateiLoeschen($name));
});

/** Erzeugt fehlende Bildgrößen nach – stückweise, damit nichts in ein Zeitlimit läuft. */
$router->post('system/optimize', static function () use ($media, $auth): void {
    $auth->requireWrite();
    Http::json($media->backfillVariants((int) Http::input('limit', 20)));
});

/** Erzeugt alle Social-Vorschaubilder neu. */
$router->post('system/social-cards', static function () use ($projects, $auth, $socialCard): void {
    $auth->requireWrite();

    if (!$socialCard->available()) {
        Http::error('Vorschaubilder brauchen die GD-Erweiterung und die Schriften in api/assets/.', 422);
    }

    $count = 0;
    foreach ($projects->list(true) as $project) {
        if ($socialCard->generate($project) !== null) {
            $count++;
        }
    }

    Http::json(['generated' => $count]);
});

/** Vollständiges JSON-Backup aller Inhalte – ein Klick im Dashboard. */
$router->get('export', static function () use ($projects, $settings, $testimonials, $messages, $auth): void {
    $auth->requireUser();
    Http::json([
        'exportedAt' => gmdate('c'),
        'settings' => $settings->all(),
        'projects' => $projects->list(true),
        'testimonials' => $testimonials->list(true),
        'messages' => $messages->list(),
    ]);
});

$router->dispatch(Http::method(), Http::path());
