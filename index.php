<?php

declare(strict_types=1);

/**
 * Main template – blog index / fallback.
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = \Timber\Timber::context();
$context['posts'] = new \Timber\PostQuery();

\Timber\Timber::render('index.twig', $context);
