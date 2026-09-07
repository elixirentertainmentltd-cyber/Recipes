<?php

declare(strict_types=1);

session_start();

const RECIPES_FILE = __DIR__ . '/data/recipes.json';
const UPLOADS_DIR = __DIR__ . '/uploads';

function config(): array
{
    $defaults = [
        'admin_password' => '',
        'site_name' => 'Elixir Recipes',
    ];

    $path = __DIR__ . '/config.local.php';
    if (!is_file($path)) {
        return $defaults;
    }

    $loaded = require $path;
    return is_array($loaded) ? array_merge($defaults, $loaded) : $defaults;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function load_recipes(): array
{
    if (!is_file(RECIPES_FILE)) {
        return [];
    }

    $json = file_get_contents(RECIPES_FILE);
    $data = json_decode($json ?: '[]', true);
    return is_array($data) ? $data : [];
}

function save_recipes(array $recipes): bool
{
    if (!is_dir(dirname(RECIPES_FILE))) {
        mkdir(dirname(RECIPES_FILE), 0775, true);
    }

    $json = json_encode(array_values($recipes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(RECIPES_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function slugify(string $value): string
{
    $value = trim(strtolower($value));

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'recipe';
}

function unique_slug(string $title, ?string $currentId = null): string
{
    $base = slugify($title);
    $slug = $base;
    $suffix = 2;
    $recipes = load_recipes();

    while (true) {
        $conflict = false;

        foreach ($recipes as $recipe) {
            if (($recipe['slug'] ?? '') === $slug && ($recipe['id'] ?? '') !== $currentId) {
                $conflict = true;
                break;
            }
        }

        if (!$conflict) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function split_lines(string $value): array
{
    $lines = preg_split('/\R/u', trim($value)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
}

function recipe_browse_options(): array
{
    return [
        'category' => [
            'Breakfast', 'Brunch', 'Lunch', 'Dinner', 'Starter', 'Main course', 'Side dish',
            'Dessert', 'Baking', 'Snack', 'Soup', 'Salad', 'Sandwich', 'Pasta', 'Curry',
            'Pie', 'One-pot', 'Slow cooker', 'Air fryer', 'Drinks',
        ],
        'cuisine' => [
            'British', 'Italian', 'Indian', 'Chinese', 'Mexican', 'Thai', 'Mediterranean',
            'American', 'French', 'Japanese', 'Middle Eastern', 'Spanish', 'Greek',
        ],
        'diet' => [
            'Vegetarian', 'Vegan', 'Gluten-free', 'Dairy-free', 'Healthy', 'High-protein',
            'Low-calorie', 'Family-friendly',
        ],
        'occasion' => [
            'Quick & easy', 'Weeknight', 'Family meal', 'Batch cooking', 'Budget', 'Party food',
            'BBQ', 'Picnic', 'Date night', 'Christmas', 'Easter', 'Halloween',
        ],
    ];
}

function recipe_browse_labels(): array
{
    return [
        'category' => 'Dish type',
        'cuisine' => 'Cuisine',
        'diet' => 'Diet & lifestyle',
        'occasion' => 'Occasion',
    ];
}

function recipe_categories(array $recipes): array
{
    $categories = array_fill_keys(recipe_browse_options()['category'], true);
    foreach ($recipes as $recipe) {
        $category = trim((string) ($recipe['category'] ?? ''));
        if ($category !== '') {
            $categories[$category] = true;
        }
    }
    $list = array_keys($categories);
    natcasesort($list);
    return array_values($list);
}

function total_time(array $recipe): int
{
    return max(0, (int) ($recipe['prep_minutes'] ?? 0)) + max(0, (int) ($recipe['cook_minutes'] ?? 0));
}

function recipe_url(array $recipe): string
{
    return '/recipe/' . rawurlencode((string) ($recipe['slug'] ?? ''));
}

function is_admin(): bool
{
    return !empty($_SESSION['recipes_admin']);
}

function admin_configured(): bool
{
    $password = (string) (config()['admin_password'] ?? '');
    return $password !== '' && $password !== 'CHANGE_ME';
}

function login_admin(string $password): bool
{
    $configured = (string) (config()['admin_password'] ?? '');
    if (!admin_configured() || !hash_equals($configured, $password)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['recipes_admin'] = true;
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function valid_csrf(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function upload_recipe_image(array $file, ?string $existing = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed. Use a JPG, PNG or WebP image under 8 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Image upload failed. Use a JPG, PNG or WebP image.');
    }

    if (!is_dir(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0775, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $destination = UPLOADS_DIR . '/' . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        throw new RuntimeException('The image could not be saved.');
    }

    if ($existing && str_starts_with($existing, '/uploads/')) {
        $old = __DIR__ . $existing;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    return '/uploads/' . $filename;
}

function recipe_by_slug(string $slug): ?array
{
    foreach (load_recipes() as $recipe) {
        if (($recipe['slug'] ?? '') === $slug) {
            return $recipe;
        }
    }
    return null;
}

function recipe_by_id(string $id): ?array
{
    foreach (load_recipes() as $recipe) {
        if (($recipe['id'] ?? '') === $id) {
            return $recipe;
        }
    }
    return null;
}
