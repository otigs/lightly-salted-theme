<?php
/**
 * WP-CLI: Import field group values from MD templates.
 *
 * Usage:
 *   wp ls import-md-field-groups --source-dir="/path/to/docs/field-groups/23.47" --dry-run
 *   wp ls import-md-field-groups --source-dir="/path/to/docs/field-groups/23.47" --update-existing
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WP_CLI\Utils;

class LS_MD_Field_Group_Importer {
	private const DEFAULT_SOURCE_DIR = '/Users/ollietigwell/Local Sites/lightly-salted/app/public/wp-content/themes/lightly-salted-theme/docs/field-groups/23.47';

	private const FILE_MAPPING = [
		'single-area-bournemouth.md'   => '/areas-covered/web-design-in-bournemouth/',
		'single-area-poole.md'         => '/areas-covered/web-design-in-poole/',
		'single-area-wimborne.md'      => '/areas-covered/web-design-in-wimborne/',
		'single-service-growth.md'     => '/service/digital-growth-support/',
		'single-service-web-design.md' => '/service/web-design/',
		'single-service-hosting.md'    => '/service/wordpress-hosting-and-maintenance/',
		'single-service-seo.md'        => '/service/seo/',
		'archive-services.md'          => '/services/',
		'archive-areas-covered.md'     => '/areas-covered/',
	];

	public function __invoke( $args, $assoc_args ): void {
		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::error( 'ACF is not active. Install and activate Advanced Custom Fields.' );
		}

		$source_dir = (string) Utils\get_flag_value( $assoc_args, 'source-dir', self::DEFAULT_SOURCE_DIR );
		$dry_run    = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$overwrite  = (bool) Utils\get_flag_value( $assoc_args, 'update-existing', true );

		if ( ! is_dir( $source_dir ) ) {
			WP_CLI::error( 'Source directory not found: ' . $source_dir );
		}

		$files = $this->find_md_files( $source_dir );
		if ( empty( $files ) ) {
			WP_CLI::warning( 'No markdown files found in: ' . $source_dir );
			return;
		}

		$report = [
			'generated_at' => gmdate( 'c' ),
			'source_dir'   => $source_dir,
			'dry_run'      => $dry_run,
			'items'        => [],
		];

		foreach ( $files as $file_path ) {
			$basename = basename( $file_path );
			if ( ! isset( self::FILE_MAPPING[ $basename ] ) ) {
				continue;
			}
			$target_url = self::FILE_MAPPING[ $basename ];
			$result = $this->import_single_file( $file_path, $target_url, [
				'dry_run'   => $dry_run,
				'overwrite' => $overwrite,
			] );
			$report['items'][] = $result;
		}

		$this->write_report( $report );
		WP_CLI::success( 'MD field group import finished.' );
	}

	private function import_single_file( string $file_path, string $target_url, array $options ): array {
		$dry_run   = (bool) ( $options['dry_run'] ?? false );
		$overwrite = (bool) ( $options['overwrite'] ?? true );

		$content = file_get_contents( $file_path );
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return [
				'file'   => $file_path,
				'url'    => $target_url,
				'status' => 'failed_empty',
			];
		}

		$fields = $this->parse_fields_from_md( $content );
		if ( empty( $fields ) ) {
			return [
				'file'   => $file_path,
				'url'    => $target_url,
				'status' => 'failed_no_fields',
			];
		}

		$post_type = $this->infer_post_type_from_file( basename( $file_path ) );
		$slug = $this->slug_from_url( $target_url );
		$target = get_page_by_path( $slug, OBJECT, $post_type );

		if ( ! $target ) {
			return [
				'file'      => $file_path,
				'url'       => $target_url,
				'post_type' => $post_type,
				'slug'      => $slug,
				'status'    => 'failed_no_target',
			];
		}

		$post_id = (int) $target->ID;
		$updated = [];

		foreach ( $fields as $field ) {
			$value = $this->coerce_value( $field['type'], $field['value'] );
			if ( ! $overwrite ) {
				$current = get_field( $field['name'], $post_id );
				if ( ! $this->is_empty_value( $current ) ) {
					continue;
				}
			}
			if ( ! $dry_run ) {
				update_field( $field['key'], $value, $post_id );
			}
			$updated[] = [
				'key'   => $field['key'],
				'name'  => $field['name'],
				'type'  => $field['type'],
				'value' => $value,
			];
		}

		return [
			'file'      => $file_path,
			'url'       => $target_url,
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'status'    => $dry_run ? 'dry_run' : 'updated',
			'updated'   => $updated,
		];
	}

	private function parse_fields_from_md( string $content ): array {
		$lines = preg_split( '/\r\n|\n|\r/', $content );
		$fields = [];
		$count = count( $lines );

		for ( $i = 0; $i < $count; $i++ ) {
			$line = $lines[ $i ];
			if ( ! preg_match( '/^\s*-\s+\*\*.+\*\*\s+\(`(field_[^`]+)`\)\s*$/', $line, $matches ) ) {
				continue;
			}

			$field_key = $matches[1];
			$indent = $this->line_indent( $line );
			$meta = [
				'key'   => $field_key,
				'name'  => '',
				'type'  => '',
				'value' => '',
			];

			$sub_fields = [];
			$sub_indent = null;

			for ( $j = $i + 1; $j < $count; $j++ ) {
				$next = $lines[ $j ];
				if ( $this->line_indent( $next ) <= $indent && preg_match( '/^\s*-\s+\*\*.+\*\*\s+\(`(field_[^`]+)`\)/', $next ) ) {
					break;
				}

				if ( preg_match( '/^\s*-\s+Name:\s+`([^`]+)`/', $next, $m ) ) {
					$meta['name'] = $m[1];
					continue;
				}

				if ( preg_match( '/^\s*-\s+Type:\s+`([^`]+)`/', $next, $m ) ) {
					$meta['type'] = $m[1];
					continue;
				}

				if ( preg_match( '/^\s*-\s+Value:\s*(.*)$/', $next, $m ) ) {
					$value = trim( $m[1] );
					if ( '' === $value ) {
						$value = $this->collect_multiline_value( $lines, $j + 1, $indent + 2 );
					}
					$meta['value'] = $this->decode_value( $value );
					continue;
				}

				if ( preg_match( '/^\s*-\s+Sub fields:\s*$/', $next ) ) {
					$sub_indent = $this->line_indent( $next ) + 2;
					$sub_fields = $this->parse_sub_fields( $lines, $j + 1, $sub_indent );
					continue;
				}
			}

			$fields[] = $meta;
			foreach ( $sub_fields as $sub ) {
				$fields[] = $sub;
			}
		}

		return $fields;
	}

	private function parse_sub_fields( array $lines, int $start, int $indent ): array {
		$fields = [];
		$count = count( $lines );
		for ( $i = $start; $i < $count; $i++ ) {
			$line = $lines[ $i ];
			if ( $this->line_indent( $line ) < $indent ) {
				break;
			}
			if ( ! preg_match( '/^\s*-\s+\*\*.+\*\*\s+\(`(field_[^`]+)`\)\s*$/', $line, $matches ) ) {
				continue;
			}
			$field_key = $matches[1];
			$meta = [
				'key'   => $field_key,
				'name'  => '',
				'type'  => '',
				'value' => '',
			];
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$next = $lines[ $j ];
				if ( $this->line_indent( $next ) <= $indent && preg_match( '/^\s*-\s+\*\*.+\*\*\s+\(`(field_[^`]+)`\)/', $next ) ) {
					break;
				}
				if ( preg_match( '/^\s*-\s+Name:\s+`([^`]+)`/', $next, $m ) ) {
					$meta['name'] = $m[1];
					continue;
				}
				if ( preg_match( '/^\s*-\s+Type:\s+`([^`]+)`/', $next, $m ) ) {
					$meta['type'] = $m[1];
					continue;
				}
				if ( preg_match( '/^\s*-\s+Value:\s*(.*)$/', $next, $m ) ) {
					$value = trim( $m[1] );
					if ( '' === $value ) {
						$value = $this->collect_multiline_value( $lines, $j + 1, $indent + 2 );
					}
					$meta['value'] = $this->decode_value( $value );
					continue;
				}
			}
			$fields[] = $meta;
		}
		return $fields;
	}

	private function collect_multiline_value( array $lines, int $start, int $indent ): string {
		$chunks = [];
		$count = count( $lines );
		for ( $i = $start; $i < $count; $i++ ) {
			$line = $lines[ $i ];
			if ( $this->line_indent( $line ) < $indent ) {
				break;
			}
			$trim = trim( $line );
			if ( '' === $trim ) {
				continue;
			}
			if ( preg_match( '/^\s*-\s+(Name|Type|Value|Sub fields):/', $line ) ) {
				break;
			}
			$chunks[] = ltrim( $line );
		}
		return trim( implode( "\n", $chunks ) );
	}

	private function decode_value( string $value ) {
		$trim = trim( $value );
		if ( '' === $trim || '""' === $trim || "''" === $trim ) {
			return '';
		}
		if ( ( str_starts_with( $trim, '"' ) && str_ends_with( $trim, '"' ) )
			|| ( str_starts_with( $trim, "'" ) && str_ends_with( $trim, "'" ) ) ) {
			$trim = substr( $trim, 1, -1 );
		}
		if ( $this->looks_like_json( $trim ) ) {
			$decoded = json_decode( $trim, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}
		}
		if ( 'true' === strtolower( $trim ) ) {
			return true;
		}
		if ( 'false' === strtolower( $trim ) ) {
			return false;
		}
		if ( is_numeric( $trim ) ) {
			return $trim + 0;
		}
		return $trim;
	}

	private function looks_like_json( string $value ): bool {
		return ( strpos( $value, '{' ) === 0 && strrpos( $value, '}' ) === strlen( $value ) - 1 )
			|| ( strpos( $value, '[' ) === 0 && strrpos( $value, ']' ) === strlen( $value ) - 1 );
	}

	private function coerce_value( string $type, $value ) {
		if ( 'repeater' === $type ) {
			return is_array( $value ) ? $value : ( '' === $value ? [] : [] );
		}
		if ( 'group' === $type ) {
			return is_array( $value ) ? $value : ( '' === $value ? [] : [] );
		}
		if ( 'image' === $type ) {
			return '' === $value ? 0 : (int) $value;
		}
		if ( 'true_false' === $type ) {
			return (bool) $value;
		}
		return is_array( $value ) ? wp_json_encode( $value ) : $value;
	}

	private function is_empty_value( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( $value );
		}
		return '' === trim( (string) $value );
	}

	private function infer_post_type_from_file( string $filename ): string {
		if ( strpos( $filename, 'single-service' ) === 0 ) {
			return 'service';
		}
		if ( strpos( $filename, 'single-area' ) === 0 ) {
			return 'area';
		}
		return 'page';
	}

	private function slug_from_url( string $url ): string {
		$path = parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path, '/' ) : '';
		if ( ! $path ) {
			return '';
		}
		$parts = explode( '/', $path );
		return end( $parts );
	}

	private function find_md_files( string $dir ): array {
		$files = [];
		$iterator = new DirectoryIterator( $dir );
		foreach ( $iterator as $item ) {
			if ( $item->isFile() && strtolower( $item->getExtension() ) === 'md' ) {
				$files[] = $item->getPathname();
			}
		}
		sort( $files );
		return $files;
	}

	private function line_indent( string $line ): int {
		preg_match( '/^\s*/', $line, $matches );
		return strlen( $matches[0] ?? '' );
	}

	private function write_report( array $report ): void {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			WP_CLI::warning( 'Uploads directory not available, skipping report.' );
			return;
		}

		$filename = 'md-field-group-import-report-' . gmdate( 'Ymd-His' ) . '.json';
		$path     = trailingslashit( $uploads['basedir'] ) . $filename;
		$encoded  = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === file_put_contents( $path, $encoded ) ) {
			WP_CLI::warning( 'Failed to write report to uploads.' );
			return;
		}

		WP_CLI::log( 'Report written to: ' . $path );
	}
}

WP_CLI::add_command( 'ls import-md-field-groups', 'LS_MD_Field_Group_Importer' );
