<?php
/**
 * Template Name: Case Studies
 *
 * Landing page for Case Studies. Uses ACF field group "Case Studies Page".
 */

use Timber\Timber;

$context = Timber::context();
Timber::render( 'templates/page-case-studies.twig', $context );
