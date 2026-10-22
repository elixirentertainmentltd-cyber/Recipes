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
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/styles.css?v=4-print-branding">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a><div class="scroll-progress" aria-hidden="true"></div>
<?php require __DIR__ . '/site-header.php'; ?>
<main id="main-content">
<?php if (!$recipe): ?>
<section class="not-found shell"><p class="eyebrow"><span></span> 404 / Missing recipe</p><h1>This one has <span class="rainbow-text">gone walkies.</span></h1><p>That recipe does not exist, or it has been removed.</p><a class="button button-primary" href="/">Back to recipes</a></section>
<?php else: ?>
<article class="recipe-page">
<header class="print-brand-header shell"><img src="/assets/elixir-recipes-logo.webp" alt="You Batter Believe It, Elixir Recipes" width="220" height="110"></header>
<section class="recipe-hero shell">
<div class="recipe-hero-copy reveal"><p class="eyebrow"><span></span> <?= h($recipe['category'] ?? 'Recipe') ?></p><h1><?= h($recipe['title'] ?? '') ?></h1><p class="hero-lead"><?= h($recipe['summary'] ?? '') ?></p><?php if (is_admin()): ?><div class="hero-actions"><a class="button button-primary" href="/admin.php?edit=<?= h($recipe['id'] ?? '') ?>">Edit this recipe</a><a class="button button-ghost" href="/admin.php">Manage all recipes</a></div><?php endif; ?><div class="hero-actions recipe-tools"><a class="button button-primary" href="#ingredients">Jump to ingredients <span aria-hidden="true">↓</span></a><a class="button button-ghost" href="#method">Method</a><button class="button button-ghost print-recipe" type="button" hidden>Print recipe</button></div><div class="recipe-meta"><div><span>Prep</span><strong><?= (int) ($recipe['prep_minutes'] ?? 0) ?> min</strong></div><div><span>Cook</span><strong><?= (int) ($recipe['cook_minutes'] ?? 0) ?> min</strong></div><div><span>Total</span><strong><?= total_time($recipe) ?> min</strong></div><div><span>Serves</span><strong><?= h((string) ($recipe['servings'] ?? '')) ?></strong></div></div></div>
<div class="recipe-hero-image reveal"><?php if (!empty($recipe['image'])): ?><img src="<?= h($recipe['image']) ?>" alt="<?= h($recipe['title'] ?? '') ?>"><?php else: ?><div class="recipe-placeholder large"><span>✦</span></div><?php endif; ?></div>
</section>
<section class="recipe-content shell">
<aside class="ingredients-card reveal" id="ingredients"><p class="section-number">Ingredients</p><h2>What you need</h2><ul><?php foreach ($ingredientLines as $ingredientIndex => $ingredient): ?><?php if ($isIngredientHeading($ingredientLines, $ingredientIndex)): ?><?php $heading = preg_replace('/^##\s*/', '', trim((string) $ingredient)); ?><li style="padding:24px 0 8px;border-bottom:0"><strong style="font-family:Manrope,sans-serif;font-size:1.08rem;letter-spacing:-.02em"><?= h(rtrim((string) $heading, ':')) ?></strong></li><?php else: ?><li><label class="ingredient-check"><input type="checkbox"><span><?= h($ingredient) ?></span></label></li><?php endif; ?><?php endforeach; ?></ul></aside>
<div class="method-card reveal" id="method"><p class="section-number">Method</p><h2>Make it happen</h2><ol><?php foreach (($recipe['method'] ?? []) as $step): ?><li><span><?= h(str_pad((string) (($loopIndex ?? 0) + 1), 2, '0', STR_PAD_LEFT)) ?></span><p><?= h($step) ?></p></li><?php $loopIndex = ($loopIndex ?? 0) + 1; endforeach; ?></ol><?php if (!empty($recipe['notes'])): ?><div class="cook-note"><strong>Cook's note</strong><p><?= nl2br(h($recipe['notes'])) ?></p></div><?php endif; ?></div>
</section>
<?php if (!empty($recipe['tags'])): ?><div class="shell tags-row"><?php foreach ($recipe['tags'] as $tag): ?><span>#<?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
<footer class="print-brand-footer shell"><p>You Batter Believe It by We Are Elixir</p><p>Find more at <a href="https://recipes.weareelixir.co.uk/">https://recipes.weareelixir.co.uk/</a></p></footer>
</article>
<?php endif; ?>
</main>
<?php require __DIR__ . '/site-footer.php'; ?><script src="/script.js?v=2"></script>
</body></html>

