# Bulk recipe imports

The dashboard's **Bulk Import Recipes** button is available to signed-in admins. Paste a JSON batch, preview it, then import. The whole batch is validated before publishing. Duplicate normalised titles, existing slugs and repeated titles within the batch are skipped. Existing records are never replaced. Similar recipes with substantially different titles still need editorial review.

## Storage and backups

Imports use the same `recipe_data_file()` as the editor. Set `recipes_file` in `config.local.php` to `__DIR__ . '/data/recipes.json'` **only if that is your existing active store**. Do not switch away from the active runtime store, or hosted recipes will appear missing. The legacy JSON file is imported automatically only when no active store exists.

A timestamped JSON backup of the complete previous store is created before a non-empty update. Backups go in `elixir-recipes-backups` beside the server's document root, outside public hosting. If this directory cannot be created/written, import stops. Give PHP write access there. Backups use private file permissions. Copy a chosen backup back to the configured active store during maintenance to restore it. Keep backups in your hosting backup process and review retention manually; imports never delete backups.

A common file lock serialises bulk imports and dashboard saves/deletes. Imports write images first and commit the merged recipe list with an atomic rename. On failure the original store is retained and newly created images are cleaned up. External/manual writers must use the same `.lock` protocol. Never edit hosted JSON during a save/import.

## API setup

1. Deploy this branch after review. Configure your existing admin password as usual.
2. Generate a separate random key and SHA-256 hash on a trusted terminal:

```sh
php -r '$key=bin2hex(random_bytes(32)); echo "Key: ".$key.PHP_EOL."SHA-256: ".hash("sha256",$key).PHP_EOL;'
```

3. In the **private**, git-ignored `config.local.php`, add `import_api_key_hash` with the hash. Never commit the key or hash to the public repository. The API is disabled until a valid hash and admin password are configured.
4. In your ChatGPT integration, import `https://recipes.weareelixir.co.uk/api/openapi.json` and set bearer authentication to the original key using its secret settings. This repository does not automatically connect the API to ChatGPT. Test a preview before publishing.
5. Rotate or clear the configured hash to revoke the key. The key grants additive import access only; it cannot edit/delete recipes or sign into the dashboard. Only give it to authorised administrators.

Send `POST /api/import-recipes.php` over HTTPS with `Authorization: Bearer YOUR_KEY` and `Content-Type: application/json`. Do not place keys in URLs. If Apache strips Authorization, configure forwarding in the host. Behind a TLS proxy, only trust `X-Forwarded-Proto` when your host overwrites it; HTTPS should be enforced at the hosting edge too. No cross-origin browser API access is enabled.

```json
{
  "dry_run": true,
  "recipes": [{
    "title": "Your recipe title",
    "category": "Baking",
    "ingredients": ["Ingredient and amount"],
    "instructions": ["First cooking step"],
    "prep_minutes": 10,
    "cook_minutes": 20,
    "servings": 4
  }]
}
```

`dry_run` defaults to true. Set false only to publish. Response reports `ready`, `added`, `skipped`, `skipped_titles` and `backup_created`. Retrying an identical successful batch skips its titles. A malformed recipe rejects the batch with 422 before any publication.

Limits: 1–100 recipes, 20 MB JSON, 150 ingredient lines and 100 instruction lines per recipe. Timings are integer minutes, 0–10080; servings are 1–1000. Use one of the site's exact dish types for category. `method` is accepted as an alternative to `instructions`. Unknown fields are rejected. Do not include database IDs, slugs or timestamps.

Images: choose optional `image_url` (HTTPS, no embedded credentials; stored by reference and never fetched by the server) or `image_base64` (raw base64 JPG/PNG/WebP, max 2 MB decoded and 20 megapixels). Base64 images are validated and saved under random names in uploads. The total request limit also applies. Do not invent image bytes or rights.

## Validation

CI runs PHP syntax, existing 13 template scenarios, import tests (preservation, duplicates, dry runs, backup failure, corruption, input validation and image import), and API method/HTTPS/auth/body-limit checks. Before release, test a real authenticated preview → import on staging, verify the generated private backup, confirm Apache forwards bearer headers and check hosting PHP request limits. No live recipes are imported by these tests.


Cuisine, occasion and tags have been retired from the site. Older batches containing those fields remain accepted for compatibility, but their values are ignored and are not added to new recipe records. Existing recipes are not migrated or deleted.
