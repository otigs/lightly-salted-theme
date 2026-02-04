<?php

use Timber\Timber;

$context = Timber::context();
$context['areas'] = Timber::get_posts([
    'post_type' => 'area',
    'post_status' => 'publish',
    'posts_per_page' => 12,
]);
Timber::render('templates/single-area_archive.twig', $context);
