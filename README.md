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

- `style.css` – Theme headers and base styles
- `functions.php` – Timber init, theme support, enqueue
- `index.php`, `single.php`, `page.php` – Template loaders
- `templates/` – All `.twig` files (base, index, single, page, header, footer)
- `src/` – Custom CSS (`style.css`) and JS (`main.js`) – edit these for your design

## Customisation

- **CSS:** Edit `src/style.css` or `style.css`.
- **JS:** Edit `src/main.js`.
- **Templates:** Edit the `.twig` files in `templates/`.
- **Menus:** In WP admin, assign a menu to **Primary Menu** (Appearance → Menus).