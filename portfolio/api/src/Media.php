<?php

declare(strict_types=1);

namespace App;

/**
 * Medienbibliothek: Upload, Thumbnail-Erzeugung, Auflistung, Löschen.
 *
 * Dateien landen nach Monat sortiert unter `/uploads/YYYY/MM/` – das hält
 * die Verzeichnisse klein und macht manuelle Backups übersichtlich.
 */
final class Media
{
    public function __construct(private Database $db, private array $config) {}

    /** @return array<int, array<string, mixed>> */
    public function list(?string $kind = null, int $limit = 200, int $offset = 0): array
    {
        $where = $kind !== null && $kind !== '' && $kind !== 'all' ? 'WHERE kind = :kind' : '';
        $params = $where !== '' ? ['kind' => $kind] : [];

        $rows = $this->db->all(
            "SELECT * FROM media {$where} ORDER BY id DESC LIMIT " . max(1, min($limit, 500)) . ' OFFSET ' . max(0, $offset),
            $params
        );

        return array_map(fn (array $row): array => self::present($row, $this->config), $rows);
    }

    public function find(int $id): ?array
    {
        $row = $this->db->first('SELECT * FROM media WHERE id = ?', [$id]);

        return $row === null ? null : self::present($row, $this->config);
    }

    /** Nimmt einen Eintrag aus $_FILES entgegen und legt ihn ab. */
    public function store(array $file, string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Http::error($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)), 422);
        }

        $maxBytes = (int) $this->config['max_upload_mb'] * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            Http::error(sprintf('Datei ist zu groß (max. %d MB).', $this->config['max_upload_mb']), 422);
        }

        $mime = $this->detectMime($file['tmp_name'], $file['name']);
        $kind = $this->kindFor($mime, $file['name']);
        if ($kind === null) {
            Http::error('Dateityp wird nicht unterstützt: ' . $mime, 422);
        }

        $relativeDir = gmdate('Y/m');
        $targetDir = rtrim($this->config['uploads_path'], '/') . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            Http::error('Upload-Ordner konnte nicht angelegt werden. Schreibrechte prüfen.', 500);
        }

        $extension = $this->extensionFor($file['name'], $mime);
        $safeName = Str::slug(pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?: 'datei';
        $filename = $safeName . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $absolute = $targetDir . '/' . $filename;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $absolute)
            : rename($file['tmp_name'], $absolute);

        if (!$moved) {
            Http::error('Datei konnte nicht gespeichert werden.', 500);
        }
        @chmod($absolute, 0644);

        [$width, $height] = $this->dimensions($absolute, $kind);
        $thumb = $kind === 'image' ? $this->makeThumbnail($absolute, $targetDir, $filename, $mime) : null;

        $id = $this->db->insert('media', [
            'filename' => $filename,
            'path' => $relativeDir . '/' . $filename,
            'thumb_path' => $thumb === null ? null : $relativeDir . '/' . $thumb,
            'mime' => $mime,
            'kind' => $kind,
            'size' => (int) $file['size'],
            'width' => $width,
            'height' => $height,
            'alt' => trim($alt),
            'created_at' => gmdate('c'),
        ]);

        return $this->find($id) ?? [];
    }

    public function updateAlt(int $id, string $alt): ?array
    {
        $this->db->update('media', ['alt' => trim($alt)], 'id = :id', ['id' => $id]);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $row = $this->db->first('SELECT * FROM media WHERE id = ?', [$id]);
        if ($row === null) {
            return;
        }

        $base = rtrim($this->config['uploads_path'], '/') . '/';
        foreach ([$row['path'], $row['thumb_path']] as $path) {
            if ($path !== null && $path !== '' && is_file($base . $path)) {
                @unlink($base . $path);
            }
        }

        $this->db->run('DELETE FROM media WHERE id = ?', [$id]);
    }

    /** Wandelt eine DB-Zeile in die öffentliche Form inkl. fertiger URLs. */
    public static function present(array $row, array $config): array
    {
        $base = rtrim((string) $config['uploads_url'], '/');

        return [
            'id' => (int) $row['id'],
            'url' => $base . '/' . ltrim((string) $row['path'], '/'),
            'thumbUrl' => empty($row['thumb_path']) ? null : $base . '/' . ltrim((string) $row['thumb_path'], '/'),
            'filename' => $row['filename'],
            'mime' => $row['mime'],
            'kind' => $row['kind'],
            'size' => (int) $row['size'],
            'width' => $row['width'] === null ? null : (int) $row['width'],
            'height' => $row['height'] === null ? null : (int) $row['height'],
            'alt' => $row['alt'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
        ];
    }

    // ------------------------------------------------------------------

    private function detectMime(string $tmpPath, string $originalName): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
                    return $mime;
                }
            }
        }

        return match (mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
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
            'gltf' => 'model/gltf+json',
            default => 'application/octet-stream',
        };
    }

    private function kindFor(string $mime, string $filename): ?string
    {
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($mime, $this->config['allowed_image_types'], true)) {
            return 'image';
        }
        if (in_array($mime, $this->config['allowed_video_types'], true)) {
            return 'video';
        }
        if (in_array($extension, ['glb', 'gltf'], true)) {
            return 'model';
        }

        return null;
    }

    private function extensionFor(string $filename, string $mime): string
    {
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (preg_match('/^[a-z0-9]{2,5}$/', $extension) === 1) {
            return $extension;
        }

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => 'bin',
        };
    }

    /** @return array{0: ?int, 1: ?int} */
    private function dimensions(string $path, string $kind): array
    {
        if ($kind !== 'image' || !function_exists('getimagesize')) {
            return [null, null];
        }

        $info = @getimagesize($path);

        return $info === false ? [null, null] : [(int) $info[0], (int) $info[1]];
    }

    /**
     * Erzeugt ein WebP-Thumbnail für die Mediathek und das Grid.
     * Ohne GD-Erweiterung wird still übersprungen – dann greift das Original.
     */
    private function makeThumbnail(string $absolute, string $targetDir, string $filename, string $mime): ?string
    {
        if (!function_exists('imagecreatetruecolor') || $mime === 'image/svg+xml') {
            return null;
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolute),
            'image/png' => @imagecreatefrompng($absolute),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            'image/gif' => @imagecreatefromgif($absolute),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($absolute) : false,
            default => false,
        };

        if ($source === false || $source === null) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min((int) $this->config['thumb_width'], $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / max(1, $width))));

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $thumbName = pathinfo($filename, PATHINFO_FILENAME) . '-thumb.webp';
        $ok = function_exists('imagewebp')
            ? imagewebp($thumb, $targetDir . '/' . $thumbName, 82)
            : imagejpeg($thumb, $targetDir . '/' . ($thumbName = pathinfo($filename, PATHINFO_FILENAME) . '-thumb.jpg'), 82);

        imagedestroy($thumb);
        imagedestroy($source);

        return $ok ? $thumbName : null;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei überschreitet das Upload-Limit des Servers (siehe api/.user.ini bzw. KAS-Einstellungen).',
            UPLOAD_ERR_PARTIAL => 'Der Upload wurde abgebrochen. Bitte erneut versuchen.',
            UPLOAD_ERR_NO_FILE => 'Es wurde keine Datei übertragen.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Der Server konnte die Datei nicht zwischenspeichern.',
            default => 'Upload fehlgeschlagen (Code ' . $code . ').',
        };
    }
}
