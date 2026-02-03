<?php
/**
 * Template Name: Blog
 *
 * Landing page for Blog. Uses ACF field group "Blog Page".
 */

use Timber\Timber;

$context = Timber::context();
Timber::render( 'templates/page-blog.twig', $context );
