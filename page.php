<?php

declare(strict_types=1);

/**
 * Page template.
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = \Timber\Timber::context();
$context['post'] = \Timber\Timber::get_post();

\Timber\Timber::render('page.twig', $context);
