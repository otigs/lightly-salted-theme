<?php

namespace Flynt;

use Flynt\Api;
use Flynt\Defaults;
use Flynt\Utils\Options;
use Timber;

/**
 * Responsible for initializing the theme.
 */
class Init
{
    /**
     * Initialize the theme.
     */
    public static function initTheme(): void
    {
        Defaults::init();
        Options::init();
        Timber\Timber::init();

        // Fronted related actions.
        if (!is_admin()) {
            Api::registerHooks();
        }
    }

    /**
     * Load components.
     */
    public static function loadComponents(): void
    {
        $basePath = get_template_directory() . '/Components';
        Api::registerComponentsFromPath($basePath);
        do_action('Flynt/afterRegisterComponents');
    }
}
