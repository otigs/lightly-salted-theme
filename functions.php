<?php

declare(strict_types=1);

/**
 * Lightly Salted Theme – Timber setup and template locations.
 *
 * Requires PHP 8.1+, WordPress 6.x, Timber 2.x.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Composer autoload (run `composer install` in theme root).
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Theme setup – supports, menus.
 */
add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', [
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary' => __('Primary Menu', 'lightly-salted'),
    ]);
});

/**
 * Set Timber template directory to /templates.
 */
add_filter('timber/locations', function (array $paths): array {
    $paths['theme'][] = get_stylesheet_directory() . '/templates';
    return $paths;
});

/**
 * Enqueue theme styles and scripts.
 */
add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style(
        'lightly-salted-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );

    if (file_exists(get_stylesheet_directory() . '/src/style.css')) {
        wp_enqueue_style(
            'lightly-salted-custom',
            get_stylesheet_directory_uri() . '/src/style.css',
            ['lightly-salted-style'],
            (string) filemtime(get_stylesheet_directory() . '/src/style.css')
        );
    }

    if (file_exists(get_stylesheet_directory() . '/src/main.js')) {
        wp_enqueue_script(
            'lightly-salted-script',
            get_stylesheet_directory_uri() . '/src/main.js',
            [],
            (string) filemtime(get_stylesheet_directory() . '/src/main.js'),
            true
        );
    }
});
