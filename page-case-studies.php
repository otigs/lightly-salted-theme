<?php
/**
 * Template Name: Case Studies
 *
 * Landing page for Case Studies. Uses ACF field group "Case Studies Page".
 */

use Timber\Timber;

$context = Timber::context();
$context['case_studies'] = Timber::get_posts([
    'post_type' => 'case_study',
    'post_status' => 'publish',
    'posts_per_page' => 6,
]);
Timber::render( 'templates/page-case-studies.twig', $context );
