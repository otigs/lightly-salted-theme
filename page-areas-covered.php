<?php
/**
 * Template Name: Areas Covered
 *
 * Landing page for Areas Covered. Uses ACF field group "Areas Covered Page".
 */

use Timber\Timber;

$context = Timber::context();
Timber::render( 'templates/page-areas-covered.twig', $context );
