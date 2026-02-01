<?php

namespace Flynt;

use Flynt\Utils\FileLoader;

require_once __DIR__ . '/vendor/autoload.php';

if (!defined('WP_ENV')) {
    define('WP_ENV', function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production');
} elseif (!defined('WP_ENVIRONMENT_TYPE')) {
    define('WP_ENVIRONMENT_TYPE', WP_ENV);
}

// Theme setup and component loading (init = after plugins loaded).
add_action('init', function (): void {
    FileLoader::loadPhpFiles('inc');
    Init::initTheme();
    Init::loadComponents();
}, 0);

// Load translations at init or later (WordPress 6.7+).
add_action('init', function (): void {
    load_theme_textdomain('flynt', get_template_directory() . '/languages');
}, 1);

// Remove the admin-bar inline-CSS as it isn't compatible with the sticky footer CSS.
add_theme_support('admin-bar', ['callback' => '__return_false']);
