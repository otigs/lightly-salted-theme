<?php
/**
 * Template Name: Homepage
 *
 * Renders the Lightly Salted homepage with Hero, Services, Case Study, Values, Contact, and Footer.
 * Uses ACF field group "Homepage" for all content.
 */

use Timber\Timber;

$context = Timber::context();
Timber::render('templates/page-homepage.twig', $context);
