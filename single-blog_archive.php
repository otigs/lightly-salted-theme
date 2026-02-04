<?php

use Timber\Timber;

$context = Timber::context();
$context['posts'] = Timber::get_posts([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => (int) get_option('posts_per_page'),
    'paged' => max(1, (int) get_query_var('paged')),
]);
Timber::render('templates/single-blog_archive.twig', $context);
