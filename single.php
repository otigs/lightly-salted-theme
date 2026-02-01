<?php

declare(strict_types=1);

/**
 * Single post template.
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = \Timber\Timber::context();
$context['post'] = \Timber\Timber::get_post();

\Timber\Timber::render('single.twig', $context);
