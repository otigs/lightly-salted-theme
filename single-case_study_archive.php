<?php

use Timber\Timber;

$context = Timber::context();
$context['case_studies'] = Timber::get_posts([
    'post_type' => 'case_study',
    'post_status' => 'publish',
    'posts_per_page' => 9,
]);
Timber::render('templates/single-case_study_archive.twig', $context);
