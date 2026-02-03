<?php
/**
 * Bootstrap to run populate-landing-pages.php when WP-CLI is not in PATH.
 *
 * Run from the WordPress root (where wp-config.php lives):
 *   php wp-content/themes/lightly-salted-theme/inc/scripts/run-populate-landing-pages.php
 *
 * Or from the theme directory (Local may set CWD to app/public):
 *   php inc/scripts/run-populate-landing-pages.php
 */

$script_dir = __DIR__;
// Find WordPress root: from inc/scripts go up to wp-content, then one more to public.
$wp_content = dirname( $script_dir, 4 );
$wp_root    = dirname( $wp_content );

if ( ! is_file( $wp_root . '/wp-load.php' ) ) {
	// Maybe we were run from WordPress root already.
	$wp_root = getcwd();
	if ( ! is_file( $wp_root . '/wp-load.php' ) ) {
		fwrite( STDERR, "WordPress root not found. Run from the directory that contains wp-config.php.\n" );
		exit( 1 );
	}
}

require_once $wp_root . '/wp-load.php';
require_once $script_dir . '/populate-landing-pages.php';
