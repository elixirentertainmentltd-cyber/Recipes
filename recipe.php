<?php
require __DIR__ . '/lib.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$recipe = recipe_by_slug($slug);
if (!$recipe) {
    http_response_code(404);
}
$siteName = (string) (config()['site_name'] ?? 'Elixir Recipes');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#070707">
<title><?= $recipe ? h($recipe['title'] ?? '') . ' | ' . h($siteName) : 'Recipe not found | ' . h($siteName) ?></title>
<meta name="description" content="<?= h($recipe['summary'] ?? 'Recipe from Elixir Recipes') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/styles.css?v=1">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a><div class="scroll-progress" aria-hidden="true"></div>
<header class="site-header"><a class="brand" href="/"><img src="/assets/elixir-recipes-logo.webp" alt="Elixir Recipes" width="220" height="110"></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav"><span class="menu-icon"><span></span><span></span><span></span></span><span>Menu</span></button><nav id="site-nav" class="site-nav"><a href="/#recipes">Recipes</a><a href="/#about">About</a><a class="nav-cta" href="/admin.php">Add a recipe</a></nav></header>
<main id="main-content">
<?php if (!$recipe): ?>
<section class="not-found shell"><p class="eyebrow"><span></span> 404 / Missing recipe</p><h1>This one has <span class="rainbow-text">gone walkies.</span></h1><p>That recipe does not exist, or it has been removed.</p><a class="button button-primary" href="/">Back to recipes</a></section>
<?php else: ?>
<article class="recipe-page">
<section class="recipe-hero shell">
<div class="recipe-hero-copy reveal"><p class="eyebrow"><span></span> <?= h($recipe['category'] ?? 'Recipe') ?></p><h1><?= h($recipe['title'] ?? '') ?></h1><p class="hero-lead"><?= h($recipe['summary'] ?? '') ?></p><div class="recipe-meta"><div><span>Prep</span><strong><?= (int) ($recipe['prep_minutes'] ?? 0) ?> min</strong></div><div><span>Cook</span><strong><?= (int) ($recipe['cook_minutes'] ?? 0) ?> min</strong></div><div><span>Total</span><strong><?= total_time($recipe) ?> min</strong></div><div><span>Serves</span><strong><?= h((string) ($recipe['servings'] ?? '')) ?></strong></div></div></div>
<div class="recipe-hero-image reveal"><?php if (!empty($recipe['image'])): ?><img src="<?= h($recipe['image']) ?>" alt="<?= h($recipe['title'] ?? '') ?>"><?php else: ?><div class="recipe-placeholder large"><span>✦</span></div><?php endif; ?></div>
</section>
<section class="recipe-content shell">
<aside class="ingredients-card reveal"><p class="section-number">Ingredients</p><h2>What you need</h2><ul><?php foreach (($recipe['ingredients'] ?? []) as $ingredient): ?><li><?= h($ingredient) ?></li><?php endforeach; ?></ul></aside>
<div class="method-card reveal"><p class="section-number">Method</p><h2>Make it happen</h2><ol><?php foreach (($recipe['method'] ?? []) as $step): ?><li><span><?= h(str_pad((string) (($loopIndex ?? 0) + 1), 2, '0', STR_PAD_LEFT)) ?></span><p><?= h($step) ?></p></li><?php $loopIndex = ($loopIndex ?? 0) + 1; endforeach; ?></ol><?php if (!empty($recipe['notes'])): ?><div class="cook-note"><strong>Cook's note</strong><p><?= nl2br(h($recipe['notes'])) ?></p></div><?php endif; ?></div>
</section>
<?php if (!empty($recipe['tags'])): ?><div class="shell tags-row"><?php foreach ($recipe['tags'] as $tag): ?><span>#<?= h($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
</article>
<?php endif; ?>
</main>
<footer class="site-footer"><div class="shell footer-bottom"><p>© <?= date('Y') ?> We Are Elixir Studio Ltd. All rights reserved.</p><a href="/">All recipes ↑</a></div></footer><script src="/script.js?v=1"></script>
</body></html>
