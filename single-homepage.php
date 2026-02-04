<?php

use Timber\Timber;
use function get_field;

$context = Timber::context();

$featured = function_exists('get_field') ? get_field('featured_work') : null;
$context['featured_case_study'] = $featured ? Timber::get_post($featured) : null;

$context['case_studies'] = Timber::get_posts([
    'post_type'      => 'case_study',
    'post_status'    => 'publish',
    'posts_per_page' => 3,
    'meta_query'     => [
        [
            'key'   => 'feature_this_case_study',
            'value' => '1',
        ],
    ],
]);
Timber::render('templates/page-homepage.twig', $context);
