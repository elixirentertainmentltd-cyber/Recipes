# Elixir Recipes

A recipe library for the WE ARE ELIXIR family, built around the **You Batter Believe It** identity and the shared Elixir web design language.

## What this site includes

- Public recipe library with search and category filters
- Individual recipe pages with ingredients, method, timings and notes
- Private recipe management area for adding, editing and deleting recipes
- Recipe image uploads
- Responsive mobile navigation and layouts
- Accessible keyboard focus states and form controls
- Elixir family colour system, typography, cards, spacing and rainbow accents
- JSON file storage so the first version does not require a database

## Hosting

The site is designed for standard PHP hosting. The recipe store uses `recipes_file` from `config.local.php` when configured, otherwise a writable sibling `elixir-recipes-storage/recipes.json`, with `data/recipes.runtime.json` as fallback. `data/recipes.json` is a legacy import source. Uploaded recipe images are stored in `uploads/`.

Before using the admin area, copy `config.example.php` to `config.local.php` and set a strong admin password. `config.local.php` is ignored by Git.

The web server must be able to write to the configured recipe store and `uploads/`. Preserve these hosted files during deployment.

## Brand reference

The build follows the WE ARE ELIXIR web design standard documented in ElixirBrain and uses the live We Are Elixir website as the visual reference. The supplied You Batter Believe It / Elixir Recipes artwork is used as the primary site logo.


## Elixir design refresh

Home, recipe pages (including 404), login, setup and recipe management share the same header and footer. The black/rainbow family styling retains the existing logo, wording, recipe records and admin workflow. Batterex uses the existing supplied artwork, resized and compressed for the web; the mascot introduction sits below the recipe library.

The home feature follows the latest recipe, with a branded cover for an empty library. Search and filters submit without JavaScript. Navigation is visible without JavaScript; enhanced mobile navigation closes on Escape, outside click, focus leaving, link selection and breakpoint changes. Recipe checkboxes are temporary cooking aids and reset on reload. Print styles omit navigation, photos and controls.

Validation: `php -l` for PHP source, `python3 tests/render.py` for 13 isolated template scenarios, and `node --check script.js`. CI uses PHP 8.3 with mbstring. Tests copy source to a temporary directory and use fixture data, never live storage.

Before release, check 320px, 390px, 768px and desktop layouts, keyboard menus, print preview and the existing authenticated add/edit/upload workflow on staging. Visual browser checks and hosted uploads cannot be confirmed by CLI rendering.
