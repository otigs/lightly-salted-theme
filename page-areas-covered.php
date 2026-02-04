<?php
/**
 * Template Name: Areas Covered
 *
 * Landing page for Areas Covered. Uses ACF field group "Areas Covered Page".
 */

use Timber\Timber;

$context = Timber::context();
$context['areas'] = Timber::get_posts([
    'post_type'      => 'area',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

$context['featured_areas'] = Timber::get_posts([
    'post_type'   => 'area',
    'post_status' => 'publish',
    'meta_query'  => [
        [
            'key'   => 'feature_this_area',
            'value' => 'Yes',
        ],
    ],
]);
Timber::render( 'templates/page-areas-covered.twig', $context );
