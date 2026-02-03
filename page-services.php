<?php
/**
 * Template Name: Services
 *
 * Landing page for Services. Uses ACF field group "Services Page".
 */

use Timber\Timber;

$context = Timber::context();
Timber::render( 'templates/page-services.twig', $context );
