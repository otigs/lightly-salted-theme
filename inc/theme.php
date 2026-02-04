<?php

namespace Flynt\Theme;

use Flynt\Utils\Options;

add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');

    /*
     * Remove type attribute from link and script tags.
     */
    add_theme_support('html5', ['script', 'style']);
});

/**
 * Ensure Case Study CPT supports featured image (thumbnail) and page-attributes.
 * ACF JSON may have these; this guarantees they are registered.
 */
add_action('init', function (): void {
    if (post_type_exists('case_study')) {
        add_post_type_support('case_study', 'thumbnail');
        add_post_type_support('case_study', 'page-attributes');
    }
}, 20);

/**
 * Explicitly add the Featured Image meta box for Case Study so it always appears
 * (e.g. if something removes it, or Screen Options isn’t visible in block editor).
 */
add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'postimagediv',
        __('Featured Image'),
        'post_thumbnail_meta_box',
        'case_study',
        'side',
        'default'
    );
}, 20);

add_filter('big_image_size_threshold', '__return_false');

add_filter('timber/context', function (array $context): array {
    $context['theme']->labels = Options::getTranslatable('Theme')['labels'] ?? [];
    return $context;
});

Options::addTranslatable('Theme', [
    [
        'label' => __('Labels', 'flynt'),
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Feed', 'flynt'),
                'instructions' => __('%s is placeholder for site title.', 'flynt'),
                'name' => 'feed',
                'type' => 'text',
                'default_value' => __('%s Feed', 'flynt'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Skip to main content', 'flynt'),
                'name' => 'skipToMainContent',
                'type' => 'text',
                'default_value' => __('Skip to main content', 'flynt'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Main Content – Aria Label', 'flynt'),
                'name' => 'mainContentAriaLabel',
                'type' => 'text',
                'default_value' => __('Content', 'flynt'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
