<?php
require __DIR__ . '/lib.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$recipe = recipe_by_slug($slug);
if (!$recipe) {
    http_response_code(404);
}
$siteName = (string) (config()['site_name'] ?? 'Elixir Recipes');

$ingredientLines = $recipe['ingredients'] ?? [];
$isIngredientHeading = static function (array $ingredients, int $index): bool {
    $line = trim((string) ($ingredients[$index] ?? ''));
    if ($line === '') {
        return false;
    }

    if (str_starts_with($line, '## ')) {
        return true;
    }

    if (str_ends_with($line, ':')) {
        return true;
    }

    if (mb_strlen($line) > 55 || preg_match('/\d/u', $line)) {
        return false;
    }

    if (preg_match('/\b(pinch|handful|few|zest|juice|clove|cloves|sprig|sprigs|slice|slices|can|tin|pack|bunch|dash|drizzle|to taste|optional)\b/i', $line)) {
        return false;
    }

    $next = trim((string) ($ingredients[$index + 1] ?? ''));
    return $next !== '' && preg_match('/^[\d¼½¾⅓⅔⅛⅜⅝⅞]/u', $next) === 1;
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#070707">
<title><?= $recipe ? h($recipe['title'] ?? '') . ' | ' . h($siteName) : 'Recipe not found | ' . h($siteName) ?></title>
<meta name="description" content="<?= h($recipe['summary'] ?? 'Recipe from Elixir Recipes') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/styles.css?v=2">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a><div class="scroll-progress" aria-hidden="true"></div>
<header class="site-header"><a class="brand" href="/"><img src="/assets/elixir-recipes-logo.webp" alt="Elixir Recipes" width="220" height="110"></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav"><span class="menu-icon"><span></span><span></span><span></span></span><span>Menu</span></button><nav id="site-nav" class="site-nav"><a href="/#recipes">Recipes</a><a href="/#about">About</a><?php if (is_admin()): ?><a href="/admin.php">Manage recipes</a><?php endif; ?><a class="nav-cta" href="<?= $recipe && is_admin() ? '/admin.php?edit=' . h($recipe['id'] ?? '') : '/admin.php' ?>"><?= $recipe && is_admin() ? 'Edit recipe' : 'Add a recipe' ?></a></nav></header>
<main id="main-content">
<?php if (!$recipe): ?>
<section class="not-found shell"><p class="eyebrow"><span></span> 404 / Missing recipe</p><h1>This one has <span class="rainbow-text">gone walkies.</span></h1><p>That recipe does not exist, or it has been removed.</p><a class="button button-primary" href="/">Back to recipes</a></section>
<?php else: ?>
<article class="recipe-page">
<section class="recipe-hero shell">
<div class="recipe-hero-copy reveal"><p class="eyebrow"><span></span> <?= h($recipe['category'] ?? 'Recipe') ?></p><h1><?= h($recipe['title'] ?? '') ?></h1><p class="hero-lead"><?= h($recipe['summary'] ?? '') ?></p><?php if (is_admin()): ?><div class="hero-actions"><a class="button button-primary" href="/admin.php?edit=<?= h($recipe['id'] ?? '') ?>">Edit this recipe</a><a class="button button-ghost" href="/admin.php">Manage all recipes</a></div><?php endif; ?><div class="recipe-meta"><div><span>Prep</span><strong><?= (int) ($recipe['prep_minutes'] ?? 0) ?> min</strong></div><div><span>Cook</span><strong><?= (int) ($recipe['cook_minutes'] ?? 0) ?> min</strong></div><div><span>Total</span><strong><?= total_time($recipe) ?> min</strong></div><div><span>Serves</span><strong><?= h((string) ($recipe['servings'] ?? '')) ?></strong></div></div></div>
<div class="recipe-hero-image reveal"><?php if (!empty($recipe['image'])): ?><img src="<?= h($recipe['image']) ?>" alt="<?= h($recipe['title'] ?? '') ?>"><?php else: ?><div class="recipe-placeholder large"><span>✦</span></div><?php endif; ?></div>
</section>
<section class="recipe-content shell">
<aside class="ingredients-card reveal"><p class="section-number">Ingredients</p><h2>What you need</h2><ul><?php foreach ($ingredientLines as $ingredientIndex => $ingredient): ?><?php if ($isIngredientHeading($ingredientLines, $ingredientIndex)): ?><?php $heading = preg_replace('/^##\s*/', '', trim((string) $ingredient)); ?><li style="padding:24px 0 8px;border-bottom:0"><strong style="font-family:Syne,Manrope,sans-serif;font-size:1.08rem;letter-spacing:-.02em"><?= h(rtrim((string) $heading, ':')) ?></strong></li><?php else: ?><li><?= h($ingredient) ?></li><?php endif; ?><?php endforeach; ?></ul></aside>
<div class="method-card reveal"><p class="section-number">Method</p><h2>Make it happen</h2><ol><?php foreach (($recipe['method'] ?? []) as $step): ?><li><span><?= h(str_pad((string) (($loopIndex ?? 0) + 1), 2, '0', STR_PAD_LEFT)) ?></span><p><?= h($step) ?></p></li><?php $loopIndex = ($loopIndex ?? 0) + 1; endforeach; ?></ol><?php if (!empty($recipe['notes'])): ?><div class="cook-note"><strong>Cook's note</strong><p><?= nl2br(h($recipe['notes'])) ?></p></div><?php endif; ?></div>
</section>
<?php if (!empty($recipe['tags'])): ?><div class="shell tags-row"><?php foreach ($recipe['tags'] as $tag): ?><span>#<?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
</article>
<?php endif; ?>
</main>
<footer class="site-footer"><div class="shell footer-bottom"><p>© <?= date('Y') ?> We Are Elixir Studio Ltd. All rights reserved.</p><a href="/">All recipes ↑</a></div></footer><script src="/script.js?v=1"></script>
</body></html>
