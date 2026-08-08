<?php

declare(strict_types=1);

namespace App;

/**
 * Kleine Helfer rund um Request und Response.
 * Bewusst statisch und ohne Framework – die API hat keine Composer-Abhängigkeiten,
 * damit ein FTP-Upload auf all-inkl genügt.
 */
final class Http
{
    private static ?array $jsonBody = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Pfad relativ zur API, z. B. "projects/mein-projekt". */
    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);

        // Alles bis einschließlich "/api" abschneiden – funktioniert auch in Unterordnern.
        if (preg_match('#/api/?(.*)$#', $uri, $m) === 1) {
            $uri = $m[1];
        }

        return trim($uri, '/');
    }

    /** Dekodierter JSON-Body (leeres Array bei Formular-Uploads). */
    public static function body(): array
    {
        if (self::$jsonBody !== null) {
            return self::$jsonBody;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'x-www-form-urlencoded')) {
            return self::$jsonBody = $_POST;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        return self::$jsonBody = is_array($decoded) ? $decoded : [];
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return self::body()[$key] ?? $default;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$key] ?? '');
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400, array $extra = []): never
    {
        self::json(array_merge(['error' => $message], $extra), $status);
    }

    /** Setzt ein HttpOnly-Cookie – Secure automatisch, sobald HTTPS aktiv ist. */
    public static function cookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    }
}
