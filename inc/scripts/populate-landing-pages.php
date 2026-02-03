<?php
/**
 * Populate landing pages and their ACF fields from inc/page-content-data.json
 *
 * Run from WordPress root (where wp-config.php lives):
 *   wp eval-file wp-content/themes/lightly-salted-theme/inc/scripts/populate-landing-pages.php
 *
 * Requires: WordPress, ACF Pro (or ACF with update_field), and the theme's ACF field groups
 * to be imported (e.g. from acf-export-lightly-salted-v2.json).
 */

if ( ! defined( 'ABSPATH' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	exit;
}

$theme_dir = function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : dirname( __DIR__, 2 );
$json_path = $theme_dir . '/inc/page-content-data.json';

if ( ! is_readable( $json_path ) ) {
	if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
		WP_CLI::error( 'JSON file not found or not readable: ' . $json_path );
	}
	exit( 1 );
}

$data = json_decode( file_get_contents( $json_path ), true );
if ( ! is_array( $data ) ) {
	if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
		WP_CLI::error( 'Invalid JSON in page-content-data.json' );
	}
	exit( 1 );
}

$pages_config = [
	[
		'key'      => 'homepage',
		'title'    => 'Homepage',
		'slug'     => 'home',
		'template' => 'page-homepage.php',
	],
	[
		'key'      => 'services_page',
		'title'    => 'Services',
		'slug'     => 'services',
		'template' => 'page-services.php',
	],
	[
		'key'      => 'case_studies_page',
		'title'    => 'Case Studies',
		'slug'     => 'case-studies',
		'template' => 'page-case-studies.php',
	],
	[
		'key'      => 'blog_page',
		'title'    => 'Blog',
		'slug'     => 'blog',
		'template' => 'page-blog.php',
	],
	[
		'key'      => 'areas_covered_page',
		'title'    => 'Areas Covered',
		'slug'     => 'areas-covered',
		'template' => 'page-areas-covered.php',
	],
];

if ( ! function_exists( 'update_field' ) ) {
	if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
		WP_CLI::error( 'ACF is not active. Install and activate Advanced Custom Fields.' );
	}
	exit( 1 );
}

foreach ( $pages_config as $config ) {
	$key   = $config['key'];
	$title = $config['title'];
	$slug  = $config['slug'];
	$template = $config['template'];

	$page = get_page_by_path( $slug, OBJECT, 'page' );

	if ( ! $page ) {
		$page_id = wp_insert_post( [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => 1,
			'post_content' => '',
		], true );

		if ( is_wp_error( $page_id ) ) {
			if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
				WP_CLI::warning( "Failed to create page: {$title} – " . $page_id->get_error_message() );
			}
			continue;
		}
		$page = get_post( $page_id );
		if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
			WP_CLI::log( "Created page: {$title} (ID {$page_id})." );
		}
	} else {
		$page_id = (int) $page->ID;
		if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
			WP_CLI::log( "Page already exists: {$title} (ID {$page_id})." );
		}
	}

	update_post_meta( $page_id, '_wp_page_template', $template );

	if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
		continue;
	}

	foreach ( $data[ $key ] as $field_name => $value ) {
		// ACF expects empty image as 0 or null when return format is ID
		if ( is_string( $value ) && $value === '' && in_array( $field_name, [ 'badge_image', 'area_image' ], true ) ) {
			$value = 0;
		}
		// Repeater rows: ensure image sub_fields are int/0 for empty
		if ( is_array( $value ) && ! empty( $value ) && isset( $value[0] ) && is_array( $value[0] ) ) {
			foreach ( $value as $row_index => $row ) {
				foreach ( $row as $sub_key => $sub_val ) {
					if ( ( strpos( $sub_key, 'image' ) !== false || $sub_key === 'area_image' ) && $sub_val === '' ) {
						$value[ $row_index ][ $sub_key ] = 0;
					}
				}
			}
		}
		update_field( $field_name, $value, $page_id );
	}
}

if ( function_exists( 'WP_CLI' ) && WP_CLI::log ) {
	WP_CLI::success( 'Landing pages and ACF fields have been updated from page-content-data.json.' );
}
