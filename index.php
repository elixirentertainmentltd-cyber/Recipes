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
  <meta name="description" content="Elixir Recipes. Good food, brighter days. Browse colourful home recipes by dish type.">
  <meta name="theme-color" content="#070707">
  <title><?= h($siteName) ?> | You Batter Believe It</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/styles.css?v=5-simple-browsing">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="scroll-progress" aria-hidden="true"></div>
<?php require __DIR__ . '/site-header.php'; ?>

<main id="main-content">
  <section class="hero shell">
    <div class="hero-copy reveal">
      <p class="eyebrow"><span></span> Good food. Brighter days.</p>
      <h1>You batter <span class="rainbow-text">believe it.</span></h1>
      <p class="hero-lead">A colourful corner for recipes worth making again. Browse by dish type and get straight to something delicious.</p>
      <div class="hero-actions">
        <a class="button button-primary" href="#recipes">Find a recipe <span aria-hidden="true">↓</span></a>
        <a class="text-link" href="#browse">Browse dish types <span aria-hidden="true">→</span></a>
      </div>
      <ul class="hero-principles">
        <li><a href="/?browse=category%3ADinner#recipes">Dinner</a></li>
        <li><a href="/?browse=category%3ABaking#recipes">Baking</a></li>
        <li><a href="/?browse=category%3ADessert#recipes">Desserts</a></li>
        <li><a href="/?browse=category%3ABreakfast#recipes">Breakfast</a></li>
      </ul>
    </div>
    <div class="hero-stage reveal">
      <?php if (!empty($recipes)): $featured = $recipes[0]; ?>
      <a class="featured-recipe" href="<?= h(recipe_url($featured)) ?>">
        <div class="featured-media"><?php if (!empty($featured['image'])): ?><img src="<?= h($featured['image']) ?>" alt="<?= h($featured['title'] ?? '') ?>" fetchpriority="high"><?php else: ?><div class="recipe-placeholder"><span aria-hidden="true">✦</span><p>Something good is cooking.</p></div><?php endif; ?></div>
        <div class="featured-copy"><p class="card-kicker">Fresh from the recipe book</p><h2><?= h($featured['title'] ?? '') ?></h2><p><?= total_time($featured) ?> min · <?= h($featured['category'] ?? 'Recipe') ?></p><span class="text-link">Make this <span aria-hidden="true">↗</span></span></div>
      </a>
      <?php else: ?>
      <div class="recipe-book-cover"><p class="card-kicker">The Elixir recipe book</p><img src="/assets/elixir-recipes-logo.webp" alt="You Batter Believe It, Elixir Recipes" width="220" height="110"><p>Everyday favourites.<br>A little kitchen magic.</p><a class="text-link" href="#browse">Find your next favourite <span aria-hidden="true">↓</span></a></div>
      <?php endif; ?>
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
      <p>Search the recipe book or choose a dish type.</p>
    </div>

    <form class="recipe-filters reveal" method="get" action="/#recipes">
      <label class="search-field"><span>Search recipes</span><input type="search" name="q" value="<?= h($q) ?>" placeholder="Try pasta, chocolate, chicken..."></label>
      <label><span>Browse recipes</span><select name="browse"><option value="">Everything</option><?php foreach ($browseOptions as $type => $items): ?><optgroup label="<?= h($browseLabels[$type] ?? ucfirst($type)) ?>"><?php foreach ($items as $item): ?><?php $value = $type . ':' . $item; ?><option value="<?= h($value) ?>" <?= $browse === $value ? 'selected' : '' ?>><?= h($item) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
      <button class="button button-primary" type="submit">Find recipes</button>
      <?php if ($q !== '' || $browse !== ''): ?><a class="button button-ghost" href="/#recipes">Clear</a><?php endif; ?>
    </form>

    <?php if ($browseValue !== ''): ?><p class="card-kicker" style="margin:0 0 22px">Dish type: <?= h($browseValue) ?></p><?php endif; ?>

    <p class="results-count" role="status"><?= count($filtered) ?> recipe<?= count($filtered) === 1 ? '' : 's' ?><?= $q !== '' || $browse !== '' ? ' found' : ' in the recipe book' ?></p>
    <?php if (!$filtered): ?>
      <div class="empty-state reveal"><span>🍳</span><h3>No recipes found yet.</h3><p><?= $recipes ? 'Try a different search or clear your filters to explore the recipe book.' : 'The recipe book is getting started. Check back soon for something delicious.' ?></p><?php if ($recipes): ?><a class="button button-primary" href="/#recipes">Clear filters</a><?php elseif (is_admin()): ?><a class="button button-primary" href="/admin.php">Add a recipe</a><?php endif; ?></div>
    <?php else: ?>
      <div class="recipe-grid">
      <?php foreach ($filtered as $recipe): ?>
        <article class="recipe-card reveal">
          <a class="recipe-card-media" href="<?= h(recipe_url($recipe)) ?>">
            <?php if (!empty($recipe['image'])): ?><img src="<?= h($recipe['image']) ?>" alt="<?= h($recipe['title'] ?? '') ?>" loading="lazy"><?php else: ?><div class="recipe-placeholder"><span>✦</span></div><?php endif; ?>
            <span class="recipe-category"><?= h($recipe['category'] ?? 'Recipe') ?></span>
          </a>
          <div class="recipe-card-body">
            <p class="card-kicker"><?= total_time($recipe) ?> min · <?= h((string) ($recipe['servings'] ?? '')) ?> servings</p>
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

  <section class="mascot-intro shell" aria-labelledby="batterex-title"><img src="/assets/batterex.webp" alt="Batterex holding a mixing bowl and whisk" width="600" height="681" loading="lazy"><div><p class="section-number">A little kitchen company</p><h2 id="batterex-title">Meet <span class="rainbow-text">Batterex.</span></h2><p>The mascot of You Batter Believe It. With a mixing bowl, a whisk and a splash of colour, Batterex brings the fun of making something to the Elixir recipe book.</p><a class="text-link" href="#recipes">Back to the recipe book <span aria-hidden="true">↑</span></a></div></section>

  <section class="cta-band">
    <div class="shell cta-layout reveal"><div><p class="section-number">03 / Keep cooking</p><h2>Got another favourite?</h2><p>Add it to the Elixir recipe book, choose its dish type, then it is instantly ready to browse.</p></div><a class="button button-light" href="/admin.php">Add a recipe <span aria-hidden="true">↗</span></a></div>
  </section>
</main>

<?php require __DIR__ . '/site-footer.php'; ?>
<script src="/script.js?v=2"></script>
</body>
</html>


