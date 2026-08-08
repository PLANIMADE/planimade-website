<?php

declare(strict_types=1);

namespace App;

/**
 * Minimaler Router: Muster wie "projects/{slug}" werden zu Regex kompiliert,
 * benannte Platzhalter landen als Argumente im Handler.
 */
final class Router
{
    /** @var array<int, array{method: string, regex: string, keys: array<int, string>, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];

                return '([^/]+)';
            },
            trim($pattern, '/')
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'keys' => $keys,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            array_shift($matches);
            $args = array_combine($route['keys'], $matches) ?: [];
            ($route['handler'])(...array_values($args));

            return;
        }

        if ($pathMatched) {
            Http::error('Methode für diese Route nicht erlaubt.', 405);
        }

        Http::error('Endpunkt nicht gefunden: ' . $path, 404);
    }
}
