<?php
require __DIR__ . '/lib.php';

$recipes = load_recipes();
usort($recipes, static fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

$q = trim((string) ($_GET['q'] ?? ''));
$browse = trim((string) ($_GET['browse'] ?? ''));
$browseOptions = recipe_browse_options();
$browseLabels = recipe_browse_labels();
$browseType = '';
$browseValue = '';

if ($browse !== '' && str_contains($browse, ':')) {
    [$candidateType, $candidateValue] = array_pad(explode(':', $browse, 2), 2, '');
    if (array_key_exists($candidateType, $browseOptions) && in_array($candidateValue, $browseOptions[$candidateType], true)) {
        $browseType = $candidateType;
        $browseValue = $candidateValue;
    }
}

$filtered = array_values(array_filter($recipes, static function ($recipe) use ($q, $browseType, $browseValue) {
    if ($browseType !== '' && strcasecmp((string) ($recipe[$browseType] ?? ''), $browseValue) !== 0) {
        return false;
    }
    if ($q === '') {
        return true;
    }
    $haystack = implode(' ', [
        (string) ($recipe['title'] ?? ''),
        (string) ($recipe['summary'] ?? ''),
        (string) ($recipe['category'] ?? ''),
        (string) ($recipe['cuisine'] ?? ''),
        (string) ($recipe['diet'] ?? ''),
        (string) ($recipe['occasion'] ?? ''),
        implode(' ', $recipe['tags'] ?? []),
        implode(' ', $recipe['ingredients'] ?? []),
    ]);
    return stripos($haystack, $q) !== false;
}));

$siteName = (string) (config()['site_name'] ?? 'Elixir Recipes');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Elixir Recipes. Good food, brighter days. Browse colourful home recipes by dish, cuisine, diet and occasion.">
  <meta name="theme-color" content="#070707">
  <title><?= h($siteName) ?> | You Batter Believe It</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css?v=2">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="scroll-progress" aria-hidden="true"></div>
<header class="site-header">
  <a class="brand" href="/" aria-label="Elixir Recipes home">
    <img src="/assets/elixir-recipes-logo.webp" alt="" width="220" height="110">
  </a>
  <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
    <span class="menu-icon"><span></span><span></span><span></span></span><span>Menu</span>
  </button>
  <nav id="site-nav" class="site-nav" aria-label="Primary navigation">
    <a href="#recipes">Recipes</a>
    <a href="#browse">Browse</a>
    <a href="#about">About</a>
    <?php if (is_admin()): ?><a href="/admin.php">Manage recipes</a><?php endif; ?>
    <a class="nav-cta" href="/admin.php"><?= is_admin() ? 'Add or edit recipes' : 'Add a recipe' ?></a>
  </nav>
</header>

<main id="main-content">
  <section class="hero shell">
    <div class="hero-copy reveal">
      <p class="eyebrow"><span></span> Good food. Brighter days.</p>
      <h1>You batter <span class="rainbow-text">believe it.</span></h1>
      <p class="hero-lead">A colourful corner for recipes worth making again. Browse by dish, cuisine, diet or occasion and get straight to something delicious.</p>
      <div class="hero-actions">
        <a class="button button-primary" href="#recipes">Find a recipe <span aria-hidden="true">↓</span></a>
        <a class="text-link" href="/?browse=occasion%3AQuick%20%26%20easy#recipes">Quick & easy <span aria-hidden="true">→</span></a>
      </div>
      <ul class="hero-principles">
        <li><a href="/?browse=category%3ADinner#recipes">Dinner</a></li>
        <li><a href="/?browse=category%3ABaking#recipes">Baking</a></li>
        <li><a href="/?browse=category%3ADessert#recipes">Desserts</a></li>
        <li><a href="/?browse=diet%3AVegetarian#recipes">Vegetarian</a></li>
      </ul>
    </div>
    <div class="hero-stage reveal">
      <img src="/assets/elixir-recipes-logo.webp" alt="You Batter Believe It, Elixir Recipes" width="220" height="110">
    </div>
  </section>

  <section class="statement-band" id="about">
    <div class="shell statement-layout reveal">
      <p class="section-number">01 / The recipe book</p>
      <div>
        <h2>Food should feel <span class="rainbow-text">fun to make.</span></h2>
        <p>This is the Elixir recipe book. No clutter, no life story before the ingredients, just good food, clear steps and a much easier way to browse what you fancy.</p>
      </div>
    </div>
  </section>

  <section class="recipes-section shell" id="recipes">
    <div class="section-intro reveal" id="browse">
      <p class="section-number">02 / Browse the recipe book</p>
      <h2>What are we making?</h2>
      <p>Search everything or browse the same way you would on a larger recipe site, by dish type, cuisine, diet and occasion.</p>
    </div>

    <form class="recipe-filters reveal" method="get" action="/#recipes">
      <label class="search-field"><span>Search recipes</span><input type="search" name="q" value="<?= h($q) ?>" placeholder="Try pasta, chocolate, chicken..."></label>
      <label><span>Browse recipes</span><select name="browse"><option value="">Everything</option><?php foreach ($browseOptions as $type => $items): ?><optgroup label="<?= h($browseLabels[$type] ?? ucfirst($type)) ?>"><?php foreach ($items as $item): ?><?php $value = $type . ':' . $item; ?><option value="<?= h($value) ?>" <?= $browse === $value ? 'selected' : '' ?>><?= h($item) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
      <button class="button button-primary" type="submit">Find recipes</button>
      <?php if ($q !== '' || $browse !== ''): ?><a class="button button-ghost" href="/#recipes">Clear</a><?php endif; ?>
    </form>

    <?php if ($browseValue !== ''): ?><p class="card-kicker" style="margin:0 0 22px">Showing <?= h($browseLabels[$browseType] ?? ucfirst($browseType)) ?>: <?= h($browseValue) ?></p><?php endif; ?>

    <?php if (!$filtered): ?>
      <div class="empty-state reveal"><span>🍳</span><h3>No recipes found yet.</h3><p>That category is ready to use, but you have not added a matching recipe yet.</p><a class="button button-primary" href="/admin.php">Add a recipe</a></div>
    <?php else: ?>
      <div class="recipe-grid">
      <?php foreach ($filtered as $recipe): ?>
        <article class="recipe-card reveal">
          <a class="recipe-card-media" href="<?= h(recipe_url($recipe)) ?>">
            <?php if (!empty($recipe['image'])): ?><img src="<?= h($recipe['image']) ?>" alt="<?= h($recipe['title'] ?? '') ?>" loading="lazy"><?php else: ?><div class="recipe-placeholder"><span>✦</span></div><?php endif; ?>
            <span class="recipe-category"><?= h($recipe['category'] ?? 'Recipe') ?></span>
          </a>
          <div class="recipe-card-body">
            <p class="card-kicker"><?= total_time($recipe) ?> min · <?= h((string) ($recipe['servings'] ?? '')) ?> servings<?= !empty($recipe['cuisine']) ? ' · ' . h($recipe['cuisine']) : '' ?></p>
            <h3><a href="<?= h(recipe_url($recipe)) ?>"><?= h($recipe['title'] ?? '') ?></a></h3>
            <p><?= h($recipe['summary'] ?? '') ?></p>
            <div class="hero-actions" style="margin-top:18px">
              <a class="text-link" href="<?= h(recipe_url($recipe)) ?>">Make this <span aria-hidden="true">→</span></a>
              <?php if (is_admin()): ?><a class="text-link" href="/admin.php?edit=<?= h($recipe['id'] ?? '') ?>">Edit <span aria-hidden="true">↗</span></a><?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="cta-band">
    <div class="shell cta-layout reveal"><div><p class="section-number">03 / Keep cooking</p><h2>Got another favourite?</h2><p>Add it to the Elixir recipe book, choose its dish type, cuisine, diet and occasion, then it is instantly ready to browse.</p></div><a class="button button-light" href="/admin.php">Add a recipe <span aria-hidden="true">↗</span></a></div>
  </section>
</main>

<footer class="site-footer"><div class="shell footer-grid"><div><img class="footer-logo" src="/assets/elixir-recipes-logo.webp" alt="Elixir Recipes" width="220" height="110"><p>Good food. Brighter days.</p></div><div><p class="footer-label">Explore</p><a href="#recipes">Recipes</a><a href="#browse">Browse</a><a href="/admin.php">Recipe admin</a></div><div><p class="footer-label">Elixir family</p><a href="https://weareelixir.co.uk/">We Are Elixir ↗</a></div></div><div class="shell footer-bottom"><p>© <?= date('Y') ?> We Are Elixir Studio Ltd. All rights reserved.</p><a href="#main-content">Back to top ↑</a></div></footer>
<script src="/script.js?v=1"></script>
</body>
</html>
