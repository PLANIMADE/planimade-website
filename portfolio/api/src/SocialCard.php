<?php

declare(strict_types=1);

namespace App;

/**
 * Erzeugt für jedes Projekt ein eigenes Vorschaubild (1200 × 630) – das Bild,
 * das erscheint, wenn jemand den Link bei LinkedIn, WhatsApp oder Slack teilt.
 *
 * Aufbau: das Titelbild des Projekts, abgedunkelt, darüber Kategorie, Titel
 * und Name. Ohne Titelbild entsteht ein Verlauf in der Akzentfarbe.
 *
 * Die Datei wird bei jedem Speichern neu erzeugt und liegt unter
 * `uploads/og/<slug>.jpg`.
 */
final class SocialCard
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    public function __construct(private array $config) {}

    public function available(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagettftext')
            && is_file($this->font('bold'));
    }

    /** @return string|null Relativer Pfad unterhalb von uploads/, oder null. */
    public function generate(array $project): ?string
    {
        if (!$this->available()) {
            return null;
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $accent = $this->hexToRgb((string) ($project['accent'] ?? '#a855f7'));

        $this->paintBackground($canvas, $project, $accent);
        $this->paintText($canvas, $project, $accent);

        $dir = rtrim($this->config['uploads_path'], '/') . '/og';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($canvas);

            return null;
        }

        $name = $project['slug'] . '.jpg';
        $ok = imagejpeg($canvas, $dir . '/' . $name, 88);
        imagedestroy($canvas);

        return $ok ? 'og/' . $name : null;
    }

    public function delete(string $slug): void
    {
        $file = rtrim($this->config['uploads_path'], '/') . '/og/' . $slug . '.jpg';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    // ------------------------------------------------------------------

    private function paintBackground(\GdImage $canvas, array $project, array $accent): void
    {
        $cover = $this->coverPath($project);

        if ($cover !== null) {
            $source = $this->load($cover);
            if ($source !== null) {
                // Titelbild formatfüllend einpassen (Mitte, ohne Verzerrung).
                $sourceWidth = imagesx($source);
                $sourceHeight = imagesy($source);
                $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
                $targetWidth = (int) ceil($sourceWidth * $scale);
                $targetHeight = (int) ceil($sourceHeight * $scale);

                imagecopyresampled(
                    $canvas,
                    $source,
                    (int) ((self::WIDTH - $targetWidth) / 2),
                    (int) ((self::HEIGHT - $targetHeight) / 2),
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $sourceWidth,
                    $sourceHeight
                );
                imagedestroy($source);

                // Verlauf von unten, damit die Schrift immer lesbar bleibt.
                for ($y = 0; $y < self::HEIGHT; $y++) {
                    $strength = min(1.0, max(0.0, ($y / self::HEIGHT - 0.15) / 0.85));
                    $alpha = (int) round(110 * $strength * $strength) + 18;
                    $color = imagecolorallocatealpha($canvas, 3, 3, 7, 127 - min(127, $alpha));
                    imagefilledrectangle($canvas, 0, $y, self::WIDTH, $y, $color);
                }

                return;
            }
        }

        // Ohne Titelbild: Verlauf in der Akzentfarbe des Projekts.
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x += 4) {
                $u = $x / self::WIDTH;
                $v = $y / self::HEIGHT;
                $intensity = max(0.0, 1.0 - sqrt((($u - 0.25) ** 2) * 1.2 + (($v - 0.3) ** 2) * 1.8));
                $color = imagecolorallocate(
                    $canvas,
                    (int) min(255, 5 + $accent[0] * $intensity * 0.75),
                    (int) min(255, 5 + $accent[1] * $intensity * 0.75),
                    (int) min(255, 10 + $accent[2] * $intensity * 0.8)
                );
                imagefilledrectangle($canvas, $x, $y, $x + 4, $y, $color);
            }
        }
    }

    private function paintText(\GdImage $canvas, array $project, array $accent): void
    {
        $white = imagecolorallocate($canvas, 237, 237, 242);
        $muted = imagecolorallocate($canvas, 190, 190, 205);
        $accentColor = imagecolorallocate($canvas, $accent[0], $accent[1], $accent[2]);

        $left = 72;
        $bottom = self::HEIGHT - 76;

        // Kategorie mit kurzem Strich davor
        $category = mb_strtoupper((string) ($project['category'] ?? ''));
        if ($category !== '') {
            imagefilledrectangle($canvas, $left, 92, $left + 40, 93, $accentColor);
            imagettftext($canvas, 15, 0, $left + 58, 99, $muted, $this->font('medium'), $this->spaced($category));
        }

        // Titel, bei Bedarf auf zwei Zeilen umgebrochen
        $title = (string) ($project['title'] ?? '');
        $lines = $this->wrap($title, 58, self::WIDTH - $left * 2, $this->font('bold'));
        $lineHeight = 74;
        $y = $bottom - 84 - (count($lines) - 1) * $lineHeight;

        foreach ($lines as $line) {
            imagettftext($canvas, 58, 0, $left, $y, $white, $this->font('bold'), $line);
            $y += $lineHeight;
        }

        // Fußzeile: Name und Jahr
        $footer = array_filter([
            (string) ($this->config['og_name'] ?? 'Dominic Majewski'),
            $project['year'] ? (string) $project['year'] : '',
        ]);
        imagettftext($canvas, 19, 0, $left, $bottom, $muted, $this->font('medium'), implode('  ·  ', $footer));
    }

    /** Bricht Text so um, dass er in die gegebene Breite passt (max. 2 Zeilen). */
    private function wrap(string $text, int $size, int $maxWidth, string $font): array
    {
        $words = preg_split('/\s+/', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            $box = imagettfbbox($size, 0, $font, $candidate);

            if ($box !== false && ($box[2] - $box[0]) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;

                if (count($lines) === 2) {
                    break;
                }
            } else {
                $current = $candidate;
            }
        }

        if (count($lines) < 2 && $current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 2);
    }

    /** Fügt Sperrsatz ein – GD kennt kein letter-spacing. */
    private function spaced(string $text): string
    {
        return implode(' ', preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function coverPath(array $project): ?string
    {
        $url = $project['cover']['url'] ?? null;
        if (!is_string($url) || $url === '') {
            return null;
        }

        $base = rtrim((string) $this->config['uploads_url'], '/');
        $relative = str_starts_with($url, $base) ? substr($url, strlen($base)) : $url;
        $path = rtrim($this->config['uploads_path'], '/') . '/' . ltrim($relative, '/');

        return is_file($path) ? $path : null;
    }

    private function load(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        $image = match ($info === false ? '' : $info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    private function font(string $weight): string
    {
        return dirname(__DIR__) . '/assets/Archivo-' . ($weight === 'bold' ? 'Bold' : 'Medium') . '.ttf';
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function hexToRgb(string $hex): array
    {
        $clean = ltrim($hex, '#');
        if (strlen($clean) === 3) {
            $clean = preg_replace('/(.)/', '$1$1', $clean) ?? 'a855f7';
        }
        $value = (int) hexdec(substr($clean, 0, 6) ?: 'a855f7');

        return [($value >> 16) & 255, ($value >> 8) & 255, $value & 255];
    }
}
