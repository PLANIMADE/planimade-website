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
use App\Database;
use App\Http;
use App\Media;
use App\Messages;
use App\Pages;
use App\Projects;
use App\Router;
use App\Security;
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

// Container – bewusst simpel, ohne DI-Framework.
$db = new Database($config);
$security = new Security($db, $config);
$auth = new Auth($db, $security, $config);
$projects = new Projects($db, $config);
$media = new Media($db, $config);
$messages = new Messages($db, $security, $config);
$settings = new Settings($db, $config);
$analytics = new Analytics($db, $security);
$testimonials = new Testimonials($db, $config);
$socialCard = new SocialCard($config);
$systemCheck = new SystemCheck($db, $config);
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

$router->get('settings', static function () use ($settings): void {
    Http::json(['settings' => $settings->all()]);
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
    Http::json(['media' => $media->list((string) Http::query('kind', '') ?: null, (int) Http::query('limit', 200), (int) Http::query('offset', 0))]);
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
