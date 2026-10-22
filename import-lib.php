<?php
declare(strict_types=1);

const IMPORT_MAX_BYTES = 20 * 1024 * 1024;

function recipe_store_lock()
{
    $file = recipe_data_file();
    if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, true)) {
        throw new RuntimeException('Recipe storage is unavailable.');
    }
    $lock = fopen($file . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Recipe storage is busy. Try again.');
    }
    return $lock;
}

function import_text($value, string $field, int $max, bool $required = false): string
{
    if (!is_string($value) || mb_strlen($value) > $max || str_contains($value, "\0")) {
        throw new InvalidArgumentException("Invalid $field.");
    }
    $value = trim($value);
    if ($required && $value === '') throw new InvalidArgumentException("$field is required.");
    return $value;
}

function import_lines($value, string $field, int $limit): array
{
    if (!is_array($value) || !array_is_list($value) || !$value || count($value) > $limit) {
        throw new InvalidArgumentException("$field must be a non-empty list (maximum $limit lines).");
    }
    return array_map(static fn($line) => import_text($line, $field, 2000, true), $value);
}

function import_payload(string $json): array
{
    if (strlen($json) > IMPORT_MAX_BYTES) throw new InvalidArgumentException('Import exceeds 20 MB.');
    try { $body = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new InvalidArgumentException('Invalid JSON.'); }
    if (!is_array($body) || array_diff(array_keys($body), ['recipes', 'dry_run']) || !isset($body['recipes']) || !is_array($body['recipes']) || !array_is_list($body['recipes']) || count($body['recipes']) < 1 || count($body['recipes']) > 100) {
        throw new InvalidArgumentException('Supply an object with 1–100 recipes.');
    }
    if (isset($body['dry_run']) && !is_bool($body['dry_run'])) throw new InvalidArgumentException('dry_run must be true or false.');
    $normalised = [];
    foreach ($body['recipes'] as $index => $row) {
        try {
            $allowed = ['title','summary','category','cuisine','occasion','tags','ingredients','instructions','method','prep_minutes','cook_minutes','servings','notes','image_url','image_base64'];
            if (!is_array($row) || array_is_list($row) || array_diff(array_keys($row), $allowed)) throw new InvalidArgumentException('Unknown recipe fields.');
            if (isset($row['method'], $row['instructions'])) throw new InvalidArgumentException('Use instructions or method, not both.');
            $item = [];
            foreach (['title'=>160,'summary'=>1000,'category'=>80,'cuisine'=>80,'occasion'=>80,'notes'=>5000] as $key=>$length) {
                $item[$key] = import_text($row[$key] ?? '', $key, $length, in_array($key, ['title','category'], true));
            }
            foreach (['category','cuisine','occasion'] as $key) {
                if ($item[$key] !== '' && !in_array($item[$key], recipe_browse_options()[$key], true)) throw new InvalidArgumentException("Unsupported $key. Use a category from the site.");
            }
            foreach (['prep_minutes'=>0,'cook_minutes'=>0,'servings'=>1] as $key=>$min) {
                $value = $row[$key] ?? $min;
                if (!is_int($value) || $value < $min || $value > ($key === 'servings' ? 1000 : 10080)) throw new InvalidArgumentException("Invalid $key.");
                $item[$key] = $value;
            }
            $item['ingredients'] = import_lines($row['ingredients'] ?? null, 'ingredients', 150);
            $item['method'] = import_lines($row['instructions'] ?? $row['method'] ?? null, 'instructions', 100);
            $tags = $row['tags'] ?? [];
            if (!is_array($tags) || !array_is_list($tags) || count($tags) > 30) throw new InvalidArgumentException('Invalid tags.');
            $item['tags'] = array_values(array_unique(array_map(static fn($tag) => import_text($tag, 'tag', 80, true), $tags)));
            $item['image'] = null;
            if (isset($row['image_url'], $row['image_base64'])) throw new InvalidArgumentException('Choose image_url or image_base64.');
            if (isset($row['image_url'])) {
                $url = import_text($row['image_url'], 'image_url', 2000, true);
                $parts = parse_url($url);
                if (!filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) throw new InvalidArgumentException('image_url must be a public HTTPS URL without credentials.');
                $item['image'] = $url;
            }
            if (isset($row['image_base64'])) {
                if (!is_string($row['image_base64']) || strlen($row['image_base64']) > 2800000) throw new InvalidArgumentException('Image must be under 2 MB.');
                $bytes = base64_decode($row['image_base64'], true);
                $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
                $mime = $bytes !== false ? (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : '';
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if ($bytes === false || strlen($bytes) > 2097152 || !$size || $size[0] * $size[1] > 20000000 || !isset($extensions[$mime]) || ($size['mime'] ?? '') !== $mime) throw new InvalidArgumentException('Use a valid JPG, PNG or WebP image under 2 MB and 20 megapixels.');
                $item['_image_bytes'] = $bytes;
                $item['_image_extension'] = $extensions[$mime];
            }
            $normalised[] = $item;
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Recipe ' . ($index + 1) . ': ' . $e->getMessage());
        }
    }
    return ['recipes'=>$normalised, 'dry_run'=>$body['dry_run'] ?? true];
}

function import_key(string $title): string
{
    return slugify(mb_strtolower(trim($title), 'UTF-8'));
}

function import_recipes(array $payload): array
{
    $lock = recipe_store_lock();
    $createdImages = [];
    $committed = false;
    try {
        $file = recipe_data_file();
        migrate_legacy_recipes_if_needed();
        $before = file_get_contents($file);
        try { $existing = json_decode($before ?: '', true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Recipe storage is not valid JSON. Import stopped.'); }
        if (!is_array($existing) || !array_is_list($existing)) throw new RuntimeException('Recipe storage is not a list. Import stopped.');
        $keys = []; $slugs = [];
        foreach ($existing as $row) {
            if (!is_array($row) || !isset($row['title'])) throw new RuntimeException('Recipe storage contains invalid records. Import stopped.');
            $keys[import_key((string)$row['title'])] = true;
            $slugs[(string)($row['slug'] ?? '')] = true;
        }
        $new = []; $skipped = [];
        foreach ($payload['recipes'] as $row) {
            $key = import_key($row['title']);
            if (isset($keys[$key]) || isset($slugs[$key])) { $skipped[] = $row['title']; continue; }
            $keys[$key] = true;
            $slug = $key; $n = 2;
            while (isset($slugs[$slug])) $slug = $key . '-' . $n++;
            $slugs[$slug] = true;
            $row['id'] = bin2hex(random_bytes(8)); $row['slug'] = $slug;
            $row['created_at'] = $row['updated_at'] = gmdate('c'); $row['diet'] = '';
            $new[] = $row;
        }
        $result = ['dry_run'=>$payload['dry_run'], 'added'=>0, 'ready'=>count($new), 'skipped'=>count($skipped), 'skipped_titles'=>$skipped, 'backup_created'=>false];
        if ($payload['dry_run'] || !$new) return $result;
        // Backups live outside the application/document root, never in a public assets folder.
        $documentRootValue = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        $documentRoot = $documentRootValue !== '' ? (realpath($documentRootValue) ?: __DIR__) : __DIR__;
        $backupDir = dirname($documentRoot) . '/elixir-recipes-backups';
        if ((!is_dir($backupDir) && !@mkdir($backupDir, 0700, true)) || !is_writable($backupDir)) throw new RuntimeException('A private backup could not be created. Import stopped.');
        $backup = $backupDir . '/recipes-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.json';
        $handle = fopen($backup, 'x');
        if (!$handle) throw new RuntimeException('Backup failed. Import stopped.');
        try { if (!chmod($backup, 0600) || fwrite($handle, $before) !== strlen($before) || !fflush($handle)) throw new RuntimeException('Backup failed. Import stopped.'); }
        finally { fclose($handle); }
        foreach ($new as &$row) {
            if (isset($row['_image_bytes'])) {
                if (!is_dir(UPLOADS_DIR) && !mkdir(UPLOADS_DIR, 0775, true)) throw new RuntimeException('Images could not be saved.');
                $name = bin2hex(random_bytes(16)) . '.' . $row['_image_extension'];
                $path = UPLOADS_DIR . '/' . $name;
                $createdImages[] = $path;
                if (file_put_contents($path, $row['_image_bytes'], LOCK_EX) !== strlen($row['_image_bytes'])) throw new RuntimeException('Image could not be saved.');
                $row['image'] = '/uploads/' . $name;
            }
            unset($row['_image_bytes'], $row['_image_extension']);
        }
        unset($row);
        if (!save_recipes(array_merge($existing, $new))) throw new RuntimeException('Recipe update failed. Existing recipes were preserved.');
        $committed = true;
        $result['added'] = count($new); $result['backup_created'] = true;
        return $result;
    } finally {
        if (!$committed) foreach ($createdImages as $path) @unlink($path);
        flock($lock, LOCK_UN); fclose($lock);
    }
}
