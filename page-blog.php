<?php
/**
 * Template Name: Blog
 *
 * Landing page for Blog. Uses ACF field group "Blog Page".
 */

use Timber\Timber;

$context = Timber::context();
$context['posts'] = Timber::get_posts([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => (int) get_option('posts_per_page'),
    'paged' => max(1, (int) get_query_var('paged')),
]);
Timber::render( 'templates/page-blog.twig', $context );
