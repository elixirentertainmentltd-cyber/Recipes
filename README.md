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

The site is designed for standard PHP hosting. The recipe store lives in `data/recipes.json` and uploaded recipe images are stored in `uploads/`.

Before using the admin area, copy `config.example.php` to `config.local.php` and set a strong admin password. `config.local.php` is ignored by Git.

The web server must be able to write to `data/recipes.json` and `uploads/`.

## Brand reference

The build follows the WE ARE ELIXIR web design standard documented in ElixirBrain and uses the live We Are Elixir website as the visual reference. The supplied You Batter Believe It / Elixir Recipes artwork is used as the primary site logo.
