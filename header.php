<?php

declare(strict_types=1);

/**
 * Header template – used by get_header(). Renders header.twig via Timber.
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = \Timber\Timber::context();
\Timber\Timber::render('header.twig', $context);
