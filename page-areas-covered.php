<?php
/**
 * Template Name: Areas Covered
 *
 * Landing page for Areas Covered. Uses ACF field group "Areas Covered Page".
 */

use Timber\Timber;

$context = Timber::context();
$context['areas'] = Timber::get_posts([
    'post_type' => 'area',
    'post_status' => 'publish',
    'posts_per_page' => 12,
]);
Timber::render( 'templates/page-areas-covered.twig', $context );
