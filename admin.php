<?php
require __DIR__ . '/lib.php';

$message = '';
$error = '';

if (isset($_GET['logout'])) {
    logout_admin();
    header('Location: /admin.php');
    exit;
}

if (!is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!valid_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (login_admin((string) ($_POST['password'] ?? ''))) {
        header('Location: /admin.php');
        exit;
    } else {
        $error = 'That password was not accepted.';
    }
}

if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!valid_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            $existing = $id !== '' ? recipe_by_id($id) : null;
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Give the recipe a title.');
            }

            $image = upload_recipe_image($_FILES['image'] ?? [], $existing['image'] ?? null);
            $recipe = [
                'id' => $existing['id'] ?? bin2hex(random_bytes(8)),
                'slug' => unique_slug($title, $existing['id'] ?? null),
                'title' => $title,
                'summary' => trim((string) ($_POST['summary'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? '')) ?: 'Other',
                'prep_minutes' => max(0, (int) ($_POST['prep_minutes'] ?? 0)),
                'cook_minutes' => max(0, (int) ($_POST['cook_minutes'] ?? 0)),
                'servings' => max(1, (int) ($_POST['servings'] ?? 1)),
                'ingredients' => split_lines((string) ($_POST['ingredients'] ?? '')),
                'method' => split_lines((string) ($_POST['method'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? ''))))),
                'image' => $image,
                'created_at' => $existing['created_at'] ?? gmdate('c'),
                'updated_at' => gmdate('c'),
            ];

            if (!$recipe['ingredients'] || !$recipe['method']) {
                throw new RuntimeException('Add at least one ingredient and one method step.');
            }

            $recipes = load_recipes();
            $found = false;
            foreach ($recipes as $index => $item) {
                if (($item['id'] ?? '') === $recipe['id']) {
                    $recipes[$index] = $recipe;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $recipes[] = $recipe;
            }
            if (!save_recipes($recipes)) {
                throw new RuntimeException('The recipe could not be saved. Check file permissions on the data folder.');
            }
            header('Location: /admin.php?saved=1');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!valid_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $id = trim((string) ($_POST['id'] ?? ''));
        $recipes = load_recipes();
        $target = recipe_by_id($id);
        $recipes = array_values(array_filter($recipes, static fn($recipe) => ($recipe['id'] ?? '') !== $id));
        if ($target && !empty($target['image']) && str_starts_with((string) $target['image'], '/uploads/')) {
            $path = __DIR__ . $target['image'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        save_recipes($recipes);
        header('Location: /admin.php?deleted=1');
        exit;
    }
}

if (isset($_GET['saved'])) $message = 'Recipe saved.';
if (isset($_GET['deleted'])) $message = 'Recipe deleted.';

$edit = is_admin() && !empty($_GET['edit']) ? recipe_by_id((string) $_GET['edit']) : null;
$recipes = is_admin() ? load_recipes() : [];
usort($recipes, static fn($a, $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#070707"><title>Recipe Admin | Elixir Recipes</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/styles.css?v=1"></head><body class="admin-body">
<header class="site-header"><a class="brand" href="/"><img src="/assets/elixir-recipes-logo.webp" alt="Elixir Recipes" width="220" height="110"></a><nav class="site-nav admin-nav"><a href="/">View site</a><?php if (is_admin()): ?><a class="nav-cta" href="/admin.php?logout=1">Sign out</a><?php endif; ?></nav></header>
<main class="admin-main shell">
<?php if (!admin_configured()): ?>
<section class="admin-login"><p class="eyebrow"><span></span> Setup required</p><h1>One tiny <span class="rainbow-text">setup step.</span></h1><p>Copy <code>config.example.php</code> to <code>config.local.php</code>, then replace <code>CHANGE_ME</code> with a strong admin password. The public recipe site already works without this.</p></section>
<?php elseif (!is_admin()): ?>
<section class="admin-login"><p class="eyebrow"><span></span> Recipe admin</p><h1>Welcome back, <span class="rainbow-text">chef.</span></h1><p>Sign in to add, edit or remove recipes.</p><?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?><form method="post" class="login-form"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><label><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label><button class="button button-primary" type="submit">Sign in</button></form></section>
<?php else: ?>
<div class="admin-heading"><div><p class="section-number">Recipe manager</p><h1><?= $edit ? 'Edit recipe' : 'Add a new recipe' ?></h1><p>One line per ingredient and one line per method step.</p></div><?php if ($edit): ?><a class="button button-ghost" href="/admin.php">Cancel edit</a><?php endif; ?></div>
<?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
<div class="admin-grid">
<form class="recipe-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
<div class="form-section"><p class="card-kicker">The basics</p><div class="field-grid"><label class="full"><span>Recipe title</span><input name="title" required value="<?= h($edit['title'] ?? '') ?>" placeholder="Sticky toffee pudding"></label><label class="full"><span>Short description</span><textarea name="summary" rows="3" placeholder="What makes this one worth cooking?"><?= h($edit['summary'] ?? '') ?></textarea></label><label><span>Category</span><input name="category" value="<?= h($edit['category'] ?? '') ?>" placeholder="Dinner, baking, dessert..."></label><label><span>Tags</span><input name="tags" value="<?= h(implode(', ', $edit['tags'] ?? [])) ?>" placeholder="quick, cosy, chocolate"></label></div></div>
<div class="form-section"><p class="card-kicker">Timing</p><div class="field-grid thirds"><label><span>Prep minutes</span><input type="number" min="0" name="prep_minutes" value="<?= h((string) ($edit['prep_minutes'] ?? 10)) ?>"></label><label><span>Cook minutes</span><input type="number" min="0" name="cook_minutes" value="<?= h((string) ($edit['cook_minutes'] ?? 20)) ?>"></label><label><span>Servings</span><input type="number" min="1" name="servings" value="<?= h((string) ($edit['servings'] ?? 4)) ?>"></label></div></div>
<div class="form-section"><p class="card-kicker">The good stuff</p><label><span>Ingredients, one per line</span><textarea name="ingredients" rows="10" required placeholder="250g flour&#10;2 eggs&#10;200ml milk"><?= h(implode("\n", $edit['ingredients'] ?? [])) ?></textarea></label><label><span>Method, one step per line</span><textarea name="method" rows="10" required placeholder="Heat the oven to 180°C.&#10;Mix the dry ingredients.&#10;Bake until golden."><?= h(implode("\n", $edit['method'] ?? [])) ?></textarea></label><label><span>Cook's note</span><textarea name="notes" rows="4"><?= h($edit['notes'] ?? '') ?></textarea></label></div>
<div class="form-section"><p class="card-kicker">Picture</p><label class="upload-field"><span>Recipe image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Maximum 8 MB.</small></label><?php if (!empty($edit['image'])): ?><img class="admin-image-preview" src="<?= h($edit['image']) ?>" alt="Current recipe image"><?php endif; ?></div>
<button class="button button-primary" type="submit"><?= $edit ? 'Save changes' : 'Publish recipe' ?> <span aria-hidden="true">↗</span></button>
</form>
<aside class="recipe-list"><div class="recipe-list-head"><p class="card-kicker">Your recipe book</p><strong><?= count($recipes) ?> recipe<?= count($recipes) === 1 ? '' : 's' ?></strong></div><?php if (!$recipes): ?><p class="muted-copy">Nothing here yet. Your first recipe will appear here after you publish it.</p><?php endif; ?><?php foreach ($recipes as $recipe): ?><div class="admin-recipe-card"><?php if (!empty($recipe['image'])): ?><img src="<?= h($recipe['image']) ?>" alt="" loading="lazy"><?php else: ?><span class="mini-placeholder">✦</span><?php endif; ?><div><small><?= h($recipe['category'] ?? 'Recipe') ?></small><strong><?= h($recipe['title'] ?? '') ?></strong><div class="admin-recipe-actions"><a href="<?= h(recipe_url($recipe)) ?>">View</a><a href="/admin.php?edit=<?= h($recipe['id'] ?? '') ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this recipe?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($recipe['id'] ?? '') ?>"><button type="submit">Delete</button></form></div></div></div><?php endforeach; ?></aside>
</div>
<?php endif; ?>
</main><script src="/script.js?v=1"></script></body></html>
