<?php
/**
 * WP-CLI: Import case studies from the live sitemap.
 *
 * Usage:
 *   wp ls import-case-studies --dry-run --limit=5 --update-existing
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WP_CLI\Utils;

class LS_Case_Studies_Importer {
	private const DEFAULT_SITEMAP = 'https://www.lightlysalted.agency/case-study-sitemap.xml';
	private const SOURCE_HOST = 'www.lightlysalted.agency';

	public function __invoke( $args, $assoc_args ): void {
		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::error( 'ACF is not active. Install and activate Advanced Custom Fields.' );
		}

		$dry_run         = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit           = (int) Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$update_existing = (bool) Utils\get_flag_value( $assoc_args, 'update-existing', false );
		$sitemap_url     = (string) Utils\get_flag_value( $assoc_args, 'sitemap', self::DEFAULT_SITEMAP );

		WP_CLI::log( 'Fetching sitemap: ' . $sitemap_url );
		$items = $this->fetch_sitemap_items( $sitemap_url );
		if ( empty( $items ) ) {
			WP_CLI::warning( 'No case studies found in sitemap.' );
			return;
		}

		if ( $limit > 0 ) {
			$items = array_slice( $items, 0, $limit );
		}

		$this->ensure_media_dependencies();

		$report = [
			'generated_at' => gmdate( 'c' ),
			'sitemap'      => $sitemap_url,
			'dry_run'      => $dry_run,
			'count'        => count( $items ),
			'items'        => [],
		];

		foreach ( $items as $item ) {
			$result = $this->import_single_case_study( $item, [
				'dry_run'         => $dry_run,
				'update_existing' => $update_existing,
			] );
			$report['items'][] = $result;
		}

		$this->write_report( $report );
		WP_CLI::success( 'Case study import finished.' );
	}

	private function fetch_sitemap_items( string $sitemap_url ): array {
		$response = wp_remote_get( $sitemap_url, [ 'timeout' => 30 ] );
		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Failed to fetch sitemap: ' . $response->get_error_message() );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			WP_CLI::error( 'Sitemap response was empty.' );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		if ( ! $xml ) {
			WP_CLI::error( 'Unable to parse sitemap XML.' );
		}

		$namespaces = $xml->getNamespaces( true );
		$image_ns  = $namespaces['image'] ?? null;

		$items = [];
		foreach ( $xml->url as $url_node ) {
			$loc = trim( (string) $url_node->loc );
			if ( empty( $loc ) ) {
				continue;
			}

			$image_url = '';
			if ( $image_ns ) {
				$image_node = $url_node->children( $image_ns )->image->loc ?? '';
				$image_url  = trim( (string) $image_node );
			}

			$items[] = [
				'url'       => $loc,
				'image_url' => $image_url,
			];
		}

		return $items;
	}

	private function import_single_case_study( array $item, array $options ): array {
		$url             = $item['url'];
		$sitemap_image   = $item['image_url'] ?? '';
		$dry_run         = (bool) ( $options['dry_run'] ?? false );
		$update_existing = (bool) ( $options['update_existing'] ?? false );

		$slug = $this->slug_from_url( $url );
		if ( ! $slug ) {
			WP_CLI::warning( 'Skipping URL with invalid slug: ' . $url );
			return [
				'url'    => $url,
				'slug'   => '',
				'status' => 'skipped',
			];
		}

		$existing = get_page_by_path( $slug, OBJECT, 'case_study' );
		if ( $existing && ! $update_existing ) {
			WP_CLI::log( "Skipping existing case study: {$slug}" );
			return [
				'url'     => $url,
				'slug'    => $slug,
				'post_id' => (int) $existing->ID,
				'status'  => 'skipped_existing',
			];
		}

		$page_html = $this->fetch_html( $url );
		if ( ! $page_html ) {
			WP_CLI::warning( 'No HTML content for: ' . $url );
			return [
				'url'    => $url,
				'slug'   => $slug,
				'status' => 'failed_no_html',
			];
		}

		$parsed = $this->parse_case_study_page( $page_html );

		$title = $parsed['client_name'] ?: $this->title_from_slug( $slug );
		$post_id = $existing ? (int) $existing->ID : 0;

		if ( ! $dry_run ) {
			if ( $existing ) {
				wp_update_post( [
					'ID'         => $post_id,
					'post_title' => $title,
				] );
				WP_CLI::log( "Updated case study: {$slug} (ID {$post_id})" );
			} else {
				$post_id = wp_insert_post( [
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'case_study',
					'post_author'  => 1,
					'post_content' => '',
				], true );
				if ( is_wp_error( $post_id ) ) {
					WP_CLI::warning( "Failed to create {$slug}: " . $post_id->get_error_message() );
					return [
						'url'    => $url,
						'slug'   => $slug,
						'status' => 'failed_create',
					];
				}
				WP_CLI::log( "Created case study: {$slug} (ID {$post_id})" );
			}
		} else {
			WP_CLI::log( "DRY RUN: Would create/update case study {$slug}" );
		}

		$attachments = [];
		$hero_image_id   = $this->maybe_sideload_image( $sitemap_image ?: $parsed['hero_image'], $post_id, "{$title} hero", $dry_run );
		$client_logo_id  = $this->maybe_sideload_image( $parsed['client_logo'], $post_id, "{$title} logo", $dry_run );

		if ( $hero_image_id ) {
			$attachments['hero_image'] = $hero_image_id;
		}
		if ( $client_logo_id ) {
			$attachments['client_logo'] = $client_logo_id;
		}

		$solution_gallery = [];
		if ( ! empty( $parsed['solution_gallery'] ) ) {
			foreach ( $parsed['solution_gallery'] as $gallery_item ) {
				$img_id = $this->maybe_sideload_image( $gallery_item['url'], $post_id, "{$title} gallery", $dry_run );
				if ( $img_id ) {
					$solution_gallery[] = [
						'image'   => $img_id,
						'caption' => $gallery_item['caption'],
					];
					$attachments['solution_gallery'][] = $img_id;
				}
			}
		}

		$approach_highlights = [];
		if ( ! empty( $parsed['approach_highlights'] ) ) {
			foreach ( $parsed['approach_highlights'] as $highlight ) {
				$approach_highlights[] = [ 'highlight' => $highlight ];
			}
		}

		if ( ! $dry_run && $post_id ) {
			update_field( 'client_name', $parsed['client_name'], $post_id );
			update_field( 'client_logo', $client_logo_id ?: 0, $post_id );
			update_field( 'project_headline', $parsed['project_headline'], $post_id );
			update_field( 'hero_image', $hero_image_id ?: 0, $post_id );
			update_field( 'challenge', $parsed['challenge'], $post_id );
			update_field( 'approach', $parsed['approach'], $post_id );
			update_field( 'approach_highlights', $approach_highlights, $post_id );
			update_field( 'solution', $parsed['solution'], $post_id );
			update_field( 'solution_gallery', $solution_gallery, $post_id );
			update_field( 'results', $parsed['results'], $post_id );
			update_field( 'project_overview', [
				'industry'    => $parsed['industry'],
				'services'    => '',
				'timeline'    => '',
				'website_url' => $parsed['website_url'],
			], $post_id );
		}

		return [
			'url'          => $url,
			'slug'         => $slug,
			'post_id'      => $post_id,
			'status'       => $dry_run ? 'dry_run' : ( $existing ? 'updated' : 'created' ),
			'attachments'  => $attachments,
			'field_summary' => [
				'client_name'     => $parsed['client_name'],
				'project_headline' => $parsed['project_headline'],
				'industry'        => $parsed['industry'],
				'website_url'     => $parsed['website_url'],
			],
		];
	}

	private function fetch_html( string $url ): string {
		$response = wp_remote_get( $url, [ 'timeout' => 30 ] );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = wp_remote_retrieve_body( $response );
		return is_string( $body ) ? $body : '';
	}

	private function parse_case_study_page( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$root  = $xpath->query( '//main' )->item( 0 );
		if ( ! $root ) {
			$root = $xpath->query( '//*[@id="content"]' )->item( 0 );
		}
		if ( ! $root ) {
			$root = $dom->documentElement;
		}

		$client_name = $this->get_first_text( $xpath, './/h1', $root );
		$project_headline = $this->get_first_subtitle( $xpath, $root );

		$industry = $this->get_heading_label_before_h1( $xpath, $root );

		$background = $this->get_section_html( $xpath, $root, [ 'Background' ] );
		$challenge  = $this->get_section_html( $xpath, $root, [ 'Challenge' ] );
		$approach   = $this->get_section_html( $xpath, $root, [ 'Our approach', 'Approach' ] );
		$solution   = $this->get_section_html( $xpath, $root, [ 'Solution' ] );
		$results    = $this->get_section_html( $xpath, $root, [ 'Results' ] );

		$approach_highlights = $approach['list_items'];
		$solution_gallery    = $solution['images'];

		$challenge_html = $challenge['html'];
		if ( empty( $approach['html'] ) && ! empty( $background['html'] ) ) {
			$challenge_html = $this->combine_section_html( $background['html'], $challenge['html'] );
		}

		$website_url = $this->get_see_for_yourself_link( $xpath, $root );
		$images      = $this->get_images( $xpath, $root );

		$hero_image  = $images[0]['url'] ?? '';
		$client_logo = $this->find_logo_image( $images );

		return [
			'client_name'        => $client_name,
			'project_headline'   => $project_headline ?: $client_name,
			'industry'           => $industry,
			'website_url'        => $website_url,
			'challenge'          => $challenge_html,
			'approach'           => $approach['html'],
			'approach_highlights' => $approach_highlights,
			'solution'           => $solution['html'],
			'solution_gallery'   => $solution_gallery,
			'results'            => $results['html'],
			'hero_image'         => $hero_image,
			'client_logo'        => $client_logo,
		];
	}

	private function get_first_text( DOMXPath $xpath, string $query, DOMNode $root ): string {
		$node = $xpath->query( $query, $root )->item( 0 );
		return $node ? $this->normalize_text( $node->textContent ) : '';
	}

	private function get_first_subtitle( DOMXPath $xpath, DOMNode $root ): string {
		$section_titles = [ 'background', 'challenge', 'our approach', 'approach', 'solution', 'results', 'see for yourself', 'related case studies' ];
		foreach ( $xpath->query( './/h2', $root ) as $h2 ) {
			$text = $this->normalize_text( $h2->textContent );
			if ( ! $text ) {
				continue;
			}
			if ( in_array( strtolower( $text ), $section_titles, true ) ) {
				continue;
			}
			return $text;
		}
		return '';
	}

	private function get_heading_label_before_h1( DOMXPath $xpath, DOMNode $root ): string {
		$h1 = $xpath->query( './/h1', $root )->item( 0 );
		if ( ! $h1 || ! $h1->parentNode ) {
			return '';
		}
		$label = '';
		foreach ( $h1->parentNode->childNodes as $node ) {
			if ( $node->isSameNode( $h1 ) ) {
				break;
			}
			if ( $node->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}
			$text = $this->normalize_text( $node->textContent );
			if ( $text && strlen( $text ) <= 60 ) {
				$label = $text;
			}
		}
		return $label;
	}

	private function get_section_html( DOMXPath $xpath, DOMNode $root, array $heading_titles ): array {
		$elements = [];
		foreach ( $xpath->query( './/*', $root ) as $node ) {
			$elements[] = $node;
		}

		$start_index = -1;
		foreach ( $elements as $index => $node ) {
			if ( $node->nodeType !== XML_ELEMENT_NODE || strtolower( $node->nodeName ) !== 'h2' ) {
				continue;
			}
			$text = $this->normalize_text( $node->textContent );
			if ( in_array( strtolower( $text ), array_map( 'strtolower', $heading_titles ), true ) ) {
				$start_index = $index;
				break;
			}
		}

		if ( $start_index === -1 ) {
			return [
				'html'       => '',
				'list_items' => [],
				'images'     => [],
			];
		}

		$allowed_tags = [ 'p', 'ul', 'ol', 'blockquote', 'figure', 'img', 'h3', 'h4' ];
		$html_chunks  = [];
		$list_items   = [];
		$images       = [];

		for ( $i = $start_index + 1; $i < count( $elements ); $i++ ) {
			$node = $elements[ $i ];
			if ( $node->nodeType === XML_ELEMENT_NODE && strtolower( $node->nodeName ) === 'h2' ) {
				break;
			}
			if ( $node->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}
			$tag = strtolower( $node->nodeName );
			if ( ! in_array( $tag, $allowed_tags, true ) ) {
				continue;
			}
			$html_chunks[] = $node->ownerDocument->saveHTML( $node );

			if ( $tag === 'ul' || $tag === 'ol' ) {
				foreach ( $node->getElementsByTagName( 'li' ) as $li ) {
					$item_text = $this->normalize_text( $li->textContent );
					if ( $item_text ) {
						$list_items[] = $item_text;
					}
				}
			}

			if ( $tag === 'img' ) {
				$images[] = [
					'url'     => $node->getAttribute( 'src' ),
					'caption' => $node->getAttribute( 'alt' ),
				];
			}

			if ( $tag === 'figure' ) {
				foreach ( $node->getElementsByTagName( 'img' ) as $img ) {
					$images[] = [
						'url'     => $img->getAttribute( 'src' ),
						'caption' => $img->getAttribute( 'alt' ),
					];
				}
			}
		}

		return [
			'html'       => $this->collapse_html( $html_chunks ),
			'list_items' => $list_items,
			'images'     => $images,
		];
	}

	private function combine_section_html( string $background, string $challenge ): string {
		$chunks = [];
		if ( $background ) {
			$chunks[] = '<p><strong>Background</strong></p>' . $background;
		}
		if ( $challenge ) {
			$chunks[] = '<p><strong>Challenge</strong></p>' . $challenge;
		}
		return $this->collapse_html( $chunks );
	}

	private function get_see_for_yourself_link( DOMXPath $xpath, DOMNode $root ): string {
		foreach ( $xpath->query( './/a', $root ) as $link ) {
			$text = $this->normalize_text( $link->textContent );
			if ( strpos( strtolower( $text ), 'go to' ) !== false || strpos( strtolower( $text ), 'see for yourself' ) !== false ) {
				return $link->getAttribute( 'href' );
			}
		}
		foreach ( $xpath->query( './/a', $root ) as $link ) {
			$href = $link->getAttribute( 'href' );
			if ( $href && strpos( $href, self::SOURCE_HOST ) === false ) {
				return $href;
			}
		}
		return '';
	}

	private function get_images( DOMXPath $xpath, DOMNode $root ): array {
		$images = [];
		foreach ( $xpath->query( './/img', $root ) as $img ) {
			$url = $img->getAttribute( 'src' );
			if ( ! $url ) {
				continue;
			}
			$images[] = [
				'url'     => $url,
				'caption' => $img->getAttribute( 'alt' ),
				'class'   => $img->getAttribute( 'class' ),
			];
		}
		return $images;
	}

	private function find_logo_image( array $images ): string {
		foreach ( $images as $image ) {
			$alt   = strtolower( $image['caption'] ?? '' );
			$class = strtolower( $image['class'] ?? '' );
			$url   = strtolower( $image['url'] ?? '' );

			if ( strpos( $alt, 'logo' ) !== false || strpos( $class, 'logo' ) !== false || strpos( $url, 'logo' ) !== false ) {
				return $image['url'];
			}
		}
		return '';
	}

	private function maybe_sideload_image( string $url, int $post_id, string $desc, bool $dry_run ): int {
		if ( ! $url ) {
			return 0;
		}
		if ( $dry_run || ! $post_id ) {
			return 0;
		}
		$attachment_id = media_sideload_image( $url, $post_id, $desc, 'id' );
		return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
	}

	private function ensure_media_dependencies(): void {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
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

	private function title_from_slug( string $slug ): string {
		$title = str_replace( [ '-', '_' ], ' ', $slug );
		return ucwords( $title );
	}

	private function normalize_text( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		return $text;
	}

	private function collapse_html( array $chunks ): string {
		$chunks = array_filter( array_map( 'trim', $chunks ) );
		return implode( "\n", $chunks );
	}

	private function write_report( array $report ): void {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			WP_CLI::warning( 'Uploads directory not available, skipping report.' );
			return;
		}

		$filename = 'case-study-import-report-' . gmdate( 'Ymd-His' ) . '.json';
		$path     = trailingslashit( $uploads['basedir'] ) . $filename;
		$encoded  = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === file_put_contents( $path, $encoded ) ) {
			WP_CLI::warning( 'Failed to write report to uploads.' );
			return;
		}

		WP_CLI::log( 'Report written to: ' . $path );
	}
}

WP_CLI::add_command( 'ls import-case-studies', 'LS_Case_Studies_Importer' );
