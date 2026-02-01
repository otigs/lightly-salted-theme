# Lightly Salted Theme

Custom Flynt-based WordPress theme for Lightly Salted. Built on [Flynt](https://www.flyntwp.com/) (Timber/Twig + ACF Pro) for component-based development.

## Requirements

- PHP 7.4+
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (LTS)
- WordPress with **ACF Pro** and this theme installed in `wp-content/themes/`

## Installation

1. **Clone or deploy** this repo into `wp-content/themes/` (e.g. as `lightly-salted-theme` or your theme folder name).

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node dependencies:**
   ```bash
   npm install
   ```

4. **Build assets** (CSS/JS) for production:
   ```bash
   npm run build
   ```
   This runs linting and produces compiled files in the `dist/` folder.

5. **Activate the theme** in WordPress: **Appearance → Themes → Lightly Salted Theme**.

## Development

- **Dev server** (with hot reload):
  ```bash
  npm run dev
  ```
- **Watch mode** (rebuild on file changes):
  ```bash
  npm run watch
  ```

## Deployment (Cloudways / Git push)

This theme is set up for **direct Git push** to Cloudways. Both `vendor/` and `dist/` are **tracked in the repo** so that PHP dependencies and built CSS/JS are deployed without running `composer install` or a build step on the server.

**Before pushing:**

1. Run `composer install` (if you added/updated PHP deps) so `vendor/` is up to date.
2. Run `npm run build` so `dist/` is up to date.
3. Commit and push; the server will use the committed `vendor/` and `dist/` assets.

## File structure (root)

| Path | Purpose |
|------|---------|
| `style.css` | Theme metadata (name, description, version). |
| `functions.php` | Theme bootstrap. |
| `composer.json` | PHP dependencies (Timber, ACF field group composer, etc.). |
| `package.json` | Node scripts and front-end build (Vite). |
| `vendor/` | Composer PHP dependencies (committed for deployment). |
| `dist/` | Compiled CSS/JS (committed for deployment). |
| `assets/` | Source SCSS/JS and static assets. |
| `Components/` | Flynt components (ACF + Twig + scripts/styles). |
| `inc/` | PHP includes (field groups, options, Timber, etc.). |
| `lib/` | Theme PHP library (Flynt namespace). |
| `templates/` | Twig layout templates. |

## Adding components

Add new components under `Components/` following the existing structure (e.g. `functions.php`, `index.twig`, `_style.scss`, `script.js`). Register them in `inc/fieldGroups/pageComponents.php` (and optionally in `reusableComponents.php` for reusable blocks).

## License

MIT (see [LICENSE.md](LICENSE.md)).
