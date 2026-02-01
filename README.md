# Lightly Salted Theme

WordPress theme for **Lightly Salted**, built with [Timber](https://timber.github.io/docs/) (Twig templating).

- **PHP:** 8.1+
- **WordPress:** 6.x
- **Timber:** 2.x (via Composer)

## Setup

1. Copy the theme into `wp-content/themes/lightly-salted-theme` (or your theme directory).
2. From the theme root, run:
   ```bash
   composer install
   ```
3. In WordPress admin: **Appearance → Themes** → activate **Lightly Salted**.

## Structure

Theme files live at the **repo root** (clone the repo into `wp-content/themes/` as the theme folder):

- `style.css` – Theme headers and base styles
- `functions.php` – Timber init, theme support, enqueue
- `index.php`, `single.php`, `page.php` – Template loaders
- `header.php`, `footer.php` – Classic WP templates (render header.twig / footer.twig via Timber)
- `templates/` – All `.twig` files (base, index, single, page, header, footer)
- `assets/` – Custom CSS (`assets/css/style.css`) and JS (`assets/js/main.js`)
- `template-parts/` – PHP partials for `get_template_part()` (optional)

## Customisation

- **CSS:** Edit `assets/css/style.css` or `style.css`.
- **JS:** Edit `assets/js/main.js`.
- **Templates:** Edit the `.twig` files in `templates/`.
- **Menus:** In WP admin, assign a menu to **Primary Menu** (Appearance → Menus).