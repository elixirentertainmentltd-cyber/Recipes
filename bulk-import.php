<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/import-lib.php';
header('Cache-Control: no-store');
if (!is_admin()) { header('Location: /admin.php'); exit; }
$error = ''; $result = null; $json = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!valid_csrf($_POST['csrf'] ?? null)) { http_response_code(403); $error = 'Your session expired. Please try again.'; }
    else {
        try {
            if (!is_string($_POST['recipes_json'] ?? null)) throw new InvalidArgumentException('Paste a JSON recipe batch.');
            $json = $_POST['recipes_json'];
            $payload = import_payload($json);
            $payload['dry_run'] = ($_POST['mode'] ?? '') !== 'publish';
            $result = import_recipes($payload);
            if (!$payload['dry_run']) $json = '';
        } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
        catch (Throwable $e) { $error = 'Import could not be completed. Check storage and backup permissions before retrying.'; }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Bulk Import Recipes | Elixir Recipes</title><link rel="stylesheet" href="/styles.css?v=3"></head><body class="admin-body"><a class="skip-link" href="#main-content">Skip to main content</a>
<?php require __DIR__ . '/site-header.php'; ?>
<main class="admin-main shell" id="main-content"><div class="admin-heading"><div><p class="section-number">Recipe manager</p><h1>Bulk Import Recipes</h1><p>Add up to 100 recipes at a time. Existing recipes are kept and duplicate titles are skipped.</p></div><a class="button button-ghost" href="/admin.php">Back to dashboard</a></div>
<?php if ($error): ?><p class="alert error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($result): ?><div class="alert success" role="status"><?php if ($result['dry_run']): ?>Preview: <?= $result['ready'] ?> ready to add.<?php else: ?><?= $result['added'] ?> recipes added. <?= $result['backup_created'] ? 'A backup was created before the update.' : 'The recipe book was unchanged.' ?><?php endif; ?> <?= $result['skipped'] ?> duplicates skipped.<?php if ($result['skipped_titles']): ?><ul><?php foreach ($result['skipped_titles'] as $title): ?><li><?= h($title) ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endif; ?>
<form method="post" class="recipe-form"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><div class="form-section"><label><span>Recipe batch (JSON)</span><textarea name="recipes_json" rows="18" required spellcheck="false" aria-describedby="import-help"><?= h($json) ?></textarea></label><p class="muted-copy" id="import-help">Use ingredients and instructions as lists, timings in minutes and one of the site's dish types. Images can be HTTPS image URLs or base64 JPG, PNG or WebP files under 2 MB each. Maximum batch size: 20 MB.</p><div class="hero-actions"><button type="submit" name="mode" value="preview" class="button button-primary">Preview import</button><?php if ($result && $result['dry_run'] && $result['ready']): ?><button type="submit" name="mode" value="publish" class="button button-light">Import recipes</button><?php endif; ?></div></div></form>
<section class="form-section" style="margin-top:24px"><h2>Connect your recipe tools</h2><p>The import API uses a separate administrator import key. An administrator must configure it on the server before connecting ChatGPT. Keep the key private and replace its configured hash to revoke access.</p><a class="text-link" href="/api/openapi.json">Download the OpenAPI specification</a><p>Example batch:</p><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= h('{"recipes":[{"title":"Your recipe title","category":"Baking","ingredients":["Ingredient and amount"],"instructions":["First step"],"prep_minutes":10,"cook_minutes":20,"servings":4}]}') ?></pre></section>
</main><?php require __DIR__ . '/site-footer.php'; ?><script src="/script.js?v=2"></script></body></html>
