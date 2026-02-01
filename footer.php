<?php

declare(strict_types=1);

/**
 * Footer template – used by get_footer(). Renders footer.twig via Timber.
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = \Timber\Timber::context();
\Timber\Timber::render('footer.twig', $context);
