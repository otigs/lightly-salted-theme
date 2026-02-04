<?php
/**
 * WP-CLI: Import Notion backlog exports into WordPress.
 *
 * Usage:
 *   wp ls import-notion-backlog --dry-run --limit=5 --update-existing
 *   wp ls import-notion-backlog --source-dir="/Users/you/Downloads/ExportBlock-.../Backend/Tasks"
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WP_CLI\Utils;

class LS_Notion_Backlog_Importer {
	private const DEFAULT_SOURCE_DIR = '/Users/ollietigwell/Downloads/ExportBlock-0cc694b9-33fd-4871-b751-cfb89b073b0c-Part-1/HQ/2882cdcdfb5481fba9d20042f7b252e4/Backend/Tasks';
	private const PAGE_SITEMAP = 'https://www.lightlysalted.agency/page-sitemap.xml';
	private const AREA_SITEMAP = 'https://www.lightlysalted.agency/areas-covered-sitemap.xml';
	private const SERVICE_SITEMAP = 'https://www.lightlysalted.agency/service-sitemap.xml';

	public function __invoke( $args, $assoc_args ): void {
		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::error( 'ACF is not active. Install and activate Advanced Custom Fields.' );
		}

		$dry_run         = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit           = (int) Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$update_existing = (bool) Utils\get_flag_value( $assoc_args, 'update-existing', false );
		$source_dir      = (string) Utils\get_flag_value( $assoc_args, 'source-dir', self::DEFAULT_SOURCE_DIR );

		if ( ! is_dir( $source_dir ) ) {
			WP_CLI::error( 'Source directory not found: ' . $source_dir );
		}

		$files = glob( trailingslashit( $source_dir ) . '*.md' );
		if ( empty( $files ) ) {
			WP_CLI::warning( 'No markdown files found in: ' . $source_dir );
			return;
		}
		sort( $files );

		$items = [];
		foreach ( $files as $path ) {
			$basename = basename( $path );
			if ( strpos( $basename, 'Build Service' ) === 0 || strpos( $basename, 'Build Area' ) === 0 || strpos( $basename, 'Build Page' ) === 0 ) {
				$items[] = $path;
			}
		}

		if ( $limit > 0 ) {
			$items = array_slice( $items, 0, $limit );
		}

		if ( empty( $items ) ) {
			WP_CLI::warning( 'No matching Notion export files found.' );
			return;
		}

		$this->ensure_media_dependencies();

		$page_sitemap    = $this->fetch_sitemap_items( self::PAGE_SITEMAP );
		$area_sitemap    = $this->fetch_sitemap_items( self::AREA_SITEMAP );
		$service_sitemap = $this->fetch_sitemap_items( self::SERVICE_SITEMAP );

		$report = [
			'generated_at' => gmdate( 'c' ),
			'source_dir'   => $source_dir,
			'dry_run'      => $dry_run,
			'count'        => count( $items ),
			'items'        => [],
		];

		foreach ( $items as $path ) {
			$result = $this->import_single_item( $path, [
				'dry_run'         => $dry_run,
				'update_existing' => $update_existing,
				'page_sitemap'    => $page_sitemap,
				'area_sitemap'    => $area_sitemap,
				'service_sitemap' => $service_sitemap,
			] );
			$report['items'][] = $result;
		}

		$this->write_report( $report );
		WP_CLI::success( 'Notion backlog import finished.' );
	}

	private function import_single_item( string $path, array $options ): array {
		$content = file_get_contents( $path );
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return [
				'path'   => $path,
				'status' => 'failed_empty',
			];
		}

		$type = $this->infer_type_from_path( $path );
		if ( ! $type ) {
			return [
				'path'   => $path,
				'status' => 'skipped_unknown',
			];
		}

		$lines    = preg_split( '/\r\n|\n|\r/', $content );
		$title    = $this->extract_task_title( $content );
		$sections = $this->split_sections( $lines );

		$dry_run         = (bool) ( $options['dry_run'] ?? false );
		$update_existing = (bool) ( $options['update_existing'] ?? false );

		$post_id = 0;
		$slug    = '';
		$post_type = 'page';

		if ( 'service' === $type ) {
			$post_type = 'service';
			$slug = $this->map_service_slug( $title );
		} elseif ( 'area' === $type ) {
			$post_type = 'area';
			$slug = $this->map_area_slug( $title );
		} elseif ( 'page' === $type ) {
			$post_type = 'page';
			$slug = $this->map_page_slug( $title );
		}

		if ( ! $slug ) {
			return [
				'path'   => $path,
				'title'  => $title,
				'type'   => $type,
				'status' => 'failed_no_slug',
			];
		}

		$existing = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $existing && ! $update_existing ) {
			WP_CLI::log( "Skipping existing {$post_type}: {$slug}" );
			return [
				'path'    => $path,
				'title'   => $title,
				'type'    => $type,
				'slug'    => $slug,
				'post_id' => (int) $existing->ID,
				'status'  => 'skipped_existing',
			];
		}

		$post_title = $this->title_from_content( $type, $title, $sections );
		$post_id = $existing ? (int) $existing->ID : 0;

		if ( ! $dry_run ) {
			if ( $existing ) {
				wp_update_post( [
					'ID'         => $post_id,
					'post_title' => $post_title,
				] );
				WP_CLI::log( "Updated {$post_type}: {$slug} (ID {$post_id})" );
			} else {
				$post_id = wp_insert_post( [
					'post_title'   => $post_title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => $post_type,
					'post_author'  => 1,
					'post_content' => '',
				], true );
				if ( is_wp_error( $post_id ) ) {
					WP_CLI::warning( "Failed to create {$slug}: " . $post_id->get_error_message() );
					return [
						'path'   => $path,
						'title'  => $title,
						'type'   => $type,
						'slug'   => $slug,
						'status' => 'failed_create',
					];
				}
				WP_CLI::log( "Created {$post_type}: {$slug} (ID {$post_id})" );
			}
		} else {
			WP_CLI::log( "DRY RUN: Would create/update {$post_type} {$slug}" );
		}

		$attachments = [];
		$images = $this->get_sitemap_images( $type, $slug, $options );

		if ( 'service' === $type ) {
			$fields = $this->build_service_fields( $sections, $content );
			if ( ! $dry_run && $post_id ) {
				foreach ( $fields as $field => $value ) {
					update_field( $field, $value, $post_id );
				}
			}
			if ( ! empty( $images['hero'] ) && ! $dry_run && $post_id ) {
				$hero_id = $this->maybe_sideload_image( $images['hero'], $post_id, "{$post_title} hero", $dry_run );
				if ( $hero_id ) {
					$attachments['featured_image'] = $hero_id;
					set_post_thumbnail( $post_id, $hero_id );
				}
			}
		} elseif ( 'area' === $type ) {
			$fields = $this->build_area_fields( $sections );
			if ( ! $dry_run && $post_id ) {
				foreach ( $fields as $field => $value ) {
					update_field( $field, $value, $post_id );
				}
			}
			if ( ! empty( $images['hero'] ) && ! $dry_run && $post_id ) {
				$hero_id = $this->maybe_sideload_image( $images['hero'], $post_id, "{$post_title} hero", $dry_run );
				if ( $hero_id ) {
					$attachments['featured_image'] = $hero_id;
					set_post_thumbnail( $post_id, $hero_id );
				}
			}
		} elseif ( 'page' === $type ) {
			$page_components = $this->build_page_components( $title, $sections, $images, $post_id, $dry_run, $attachments );
			if ( ! $dry_run && $post_id ) {
				update_field( 'pageComponents', $page_components, $post_id );
			}
		}

		return [
			'path'        => $path,
			'title'       => $title,
			'type'        => $type,
			'slug'        => $slug,
			'post_id'     => $post_id,
			'status'      => $dry_run ? 'dry_run' : ( $existing ? 'updated' : 'created' ),
			'attachments' => $attachments,
		];
	}

	private function infer_type_from_path( string $path ): string {
		$basename = basename( $path );
		if ( strpos( $basename, 'Build Service' ) === 0 ) {
			return 'service';
		}
		if ( strpos( $basename, 'Build Area' ) === 0 ) {
			return 'area';
		}
		if ( strpos( $basename, 'Build Page' ) === 0 ) {
			return 'page';
		}
		return '';
	}

	private function extract_task_title( string $content ): string {
		if ( preg_match( '/^#\s+Build\s+(?:Service|Area|Page):\s+(.+)$/m', $content, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	private function map_service_slug( string $title ): string {
		$lookup = [
			'WordPress Hosting and Maintenance' => 'wordpress-hosting-and-maintenance',
		];
		if ( isset( $lookup[ $title ] ) ) {
			return $lookup[ $title ];
		}
		return $this->slugify( $title );
	}

	private function map_area_slug( string $title ): string {
		$lookup = [
			'Bournemouth' => 'web-design-in-bournemouth',
			'Poole'       => 'web-design-in-poole',
			'Dorset'      => 'web-design-in-dorset',
		];
		return $lookup[ $title ] ?? $this->slugify( $title );
	}

	private function map_page_slug( string $title ): string {
		$lookup = [
			'About'        => 'about',
			'Contact'      => 'contact',
			'Team'         => 'our-team',
			'Values'       => 'our-values',
			'Human Health' => 'websites-for-businesses-in-human-health',
			'Environment'  => 'websites-for-businesses-that-work-in-the-environment',
		];
		return $lookup[ $title ] ?? $this->slugify( $title );
	}

	private function title_from_content( string $type, string $fallback, array $sections ): string {
		if ( 'service' === $type ) {
			$hero = $this->find_section_lines( $sections, [ 'Service Hero', 'Hero' ] );
			$title = $this->extract_label_value( $hero, [ 'Title (H1)', 'Title', 'Headline' ] );
			return $title ?: $fallback;
		}
		if ( 'area' === $type ) {
			$hero = $this->find_section_lines( $sections, [ 'Hero' ] );
			$title = $this->extract_label_value( $hero, [ 'Headline', 'Title (H1)', 'Title' ] );
			return $title ?: $fallback;
		}
		if ( 'page' === $type ) {
			$hero = $this->find_section_lines( $sections, [ 'Hero' ] );
			$title = $this->extract_label_value( $hero, [ 'Title (H1)', 'Title', 'Headline' ] );
			return $title ?: $fallback;
		}
		return $fallback;
	}

	private function build_service_fields( array $sections, string $content ): array {
		$hero = $this->find_section_lines( $sections, [ 'Service Hero', 'Hero' ] );
		$service_name = $this->extract_label_value( $hero, [ 'Title (H1)', 'Title', 'Headline' ] );
		$tagline = $this->extract_label_value( $hero, [ 'Tagline', 'Subheadline' ] );
		$description = $this->extract_label_value( $hero, [ 'Description', 'Introduction' ] );
		$price_indicator = $this->extract_label_value( $hero, [ 'Price indicator', 'Price' ] );
		$primary_cta_label = $this->extract_label_value( $hero, [ 'Primary CTA', 'CTA' ] );

		$included = $this->find_section_lines( $sections, [ "What's Included", 'Included', 'Features' ] );
		$included_heading = $this->extract_label_value( $included, [ 'Section heading', 'Heading' ] );
		$included_features = $this->extract_bulleted_features( $included, [ 'Features grid', 'Features', 'Service Categories' ] );

		$process = $this->find_section_lines( $sections, [ 'Our Process', 'Process', 'How It Works' ] );
		$process_heading = $this->extract_label_value( $process, [ 'Section heading', 'Headline' ] );
		$process_steps = $this->extract_process_steps( $process );

		$why = $this->find_section_lines( $sections, [ 'Why Choose', 'Why Green Hosting', 'Why Maintenance', 'Why Bundle', 'Our Approach', 'The Problem' ] );
		$why_choose_heading = $this->extract_label_value( $why, [ 'Section heading', 'Headline' ] );
		$differentiators = $this->extract_bulleted_features( $why, [ 'Differentiators', 'Key Differentiators', 'Benefits', 'Risks' ] );
		if ( empty( $differentiators ) && ! empty( $why ) ) {
			$body = $this->extract_body_paragraphs( $why );
			if ( $body ) {
				$differentiators[] = [
					'diff_title'  => $why_choose_heading ?: 'Why it matters',
					'diff_detail' => $body,
				];
			}
		}

		$testimonial = $this->extract_label_value( $content, [ 'Testimonial placeholder', 'Testimonial' ] );
		$related_case_study_note = $this->extract_label_value( $content, [ 'Related Case Study', 'Case Study Cards', 'Content placeholder' ] );

		$pricing = $this->find_section_lines( $sections, [ 'Pricing', 'Investment' ] );
		$investment_heading = $this->extract_label_value( $pricing, [ 'Section heading', 'Headline' ] );
		$investment_body = $this->build_section_html( $pricing, [ 'Section heading', 'Headline', 'Pricing tiers', 'Price' ] );

		$faq = $this->find_section_lines( $sections, [ 'FAQ', 'Questions' ] );
		$faq_heading = $this->extract_label_value( $faq, [ 'Section heading', 'Headline' ] );
		$faqs = $this->extract_faqs( $faq );

		$final = $this->find_section_lines( $sections, [ 'Final CTA' ] );
		$final_cta_headline = $this->extract_label_value( $final, [ 'Headline' ] );
		$final_cta_body = $this->extract_label_value( $final, [ 'Body', 'Body copy' ] );

		return [
			'service_name'         => $service_name,
			'tagline'              => $tagline,
			'description'          => $description,
			'price_indicator'      => $price_indicator,
			'primary_cta_label'    => $primary_cta_label,
			'included_heading'     => $included_heading,
			'included_features'    => $this->normalize_repeater_rows( $included_features, [ 'feature_title', 'feature_detail', 'feature_icon' ] ),
			'process_heading'      => $process_heading,
			'process_steps'        => $this->normalize_repeater_rows( $process_steps, [ 'step_title', 'step_detail', 'step_timing' ] ),
			'why_choose_heading'   => $why_choose_heading,
			'differentiators'      => $this->normalize_repeater_rows( $differentiators, [ 'diff_title', 'diff_detail' ] ),
			'testimonial'          => $testimonial,
			'related_case_study_note' => $related_case_study_note,
			'investment_heading'   => $investment_heading,
			'investment_body'      => $investment_body,
			'faq_heading'          => $faq_heading,
			'faqs'                 => $this->normalize_repeater_rows( $faqs, [ 'question', 'answer' ] ),
			'final_cta_headline'   => $final_cta_headline,
			'final_cta_body'       => $final_cta_body,
		];
	}

	private function build_area_fields( array $sections ): array {
		$hero = $this->find_section_lines( $sections, [ 'Hero' ] );
		$hero_title = $this->extract_label_value( $hero, [ 'Headline', 'Title (H1)', 'Title' ] );
		$hero_intro = $this->extract_label_value( $hero, [ 'Subheadline', 'Introduction' ] );
		$primary_cta_label = $this->extract_label_value( $hero, [ 'CTA', 'Primary CTA' ] );

		$intro = $this->find_section_lines( $sections, [ 'Local Introduction' ] );
		$services = $this->find_section_lines( $sections, [ 'Services for', 'Services' ] );
		$services_heading = $this->extract_label_value( $services, [ 'Headline', 'Section heading' ] );
		$services_note = $this->extract_body_paragraphs( $services );
		if ( ! $services_note ) {
			$services_note = $this->summarize_service_cards( $services );
		}

		$case_studies = $this->find_section_lines( $sections, [ 'Case Studies', 'Success Stories' ] );
		$local_case_studies_heading = $this->extract_label_value( $case_studies, [ 'Headline', 'Section heading' ] );
		$local_case_studies_note = $this->extract_body_paragraphs( $case_studies );

		$local = $this->find_section_lines( $sections, [ 'Why Local', 'Local Advantage' ] );
		$local_agency_heading = $this->extract_label_value( $local, [ 'Headline', 'Section heading' ] );
		$local_agency_benefits = $this->extract_benefits( $local );

		$nearby = $this->find_section_lines( $sections, [ 'Related Areas', 'Nearby Areas' ] );
		$nearby_areas_heading = $this->extract_label_value( $nearby, [ 'Headline', 'Section heading' ] );
		$nearby_areas = $this->extract_nearby_areas( $nearby );

		$final = $this->find_section_lines( $sections, [ 'Final CTA' ] );
		$final_cta_headline = $this->extract_label_value( $final, [ 'Headline' ] );
		$final_cta_body = $this->extract_label_value( $final, [ 'Body Copy', 'Body' ] );
		$contact_note = $this->extract_label_value( $final, [ 'Secondary CTA', 'Secondary' ] );

		return [
			'area_name'                => $hero_title ?: '',
			'hero_title'               => $hero_title,
			'hero_intro'               => $hero_intro,
			'primary_cta_label'        => $primary_cta_label,
			'services_heading'         => $services_heading,
			'services_note'            => $services_note,
			'local_case_studies_heading' => $local_case_studies_heading,
			'local_case_studies_note'  => $local_case_studies_note,
			'local_agency_heading'     => $local_agency_heading,
			'local_agency_body'        => '',
			'local_agency_benefits'    => $this->normalize_repeater_rows( $local_agency_benefits, [ 'benefit_title', 'benefit_detail' ] ),
			'testimonial_note'         => '',
			'nearby_areas_heading'     => $nearby_areas_heading,
			'nearby_areas'             => $this->normalize_repeater_rows( $nearby_areas, [ 'nearby_area_name', 'nearby_area_url' ] ),
			'final_cta_headline'       => $final_cta_headline,
			'final_cta_body'           => $final_cta_body,
			'contact_note'             => $contact_note,
		];
	}

	private function build_page_components( string $title, array $sections, array $images, int $post_id, bool $dry_run, array &$attachments ): array {
		$components = [];

		$hero = $this->find_section_lines( $sections, [ 'Hero' ] );
		$hero_html = $this->section_html_with_heading( $hero );
		if ( $hero_html ) {
			if ( ! empty( $images['hero'] ) && $post_id && ! $dry_run ) {
				$image_id = $this->maybe_sideload_image( $images['hero'], $post_id, "{$title} hero", $dry_run );
				if ( $image_id ) {
					$attachments['hero'] = $image_id;
					$components[] = $this->make_block_image_text( $hero_html, $image_id, 'left' );
				} else {
					$components[] = $this->make_block_wysiwyg( $hero_html );
				}
			} else {
				$components[] = $this->make_block_wysiwyg( $hero_html );
			}
		}

		foreach ( $sections as $section ) {
			$title_lower = strtolower( $section['title'] );
			if ( strpos( $title_lower, 'hero' ) !== false ) {
				continue;
			}

			if ( strpos( $title_lower, 'values' ) !== false || strpos( $title_lower, 'credentials' ) !== false || strpos( $title_lower, 'commitments' ) !== false ) {
				$components[] = $this->make_grid_image_text(
					$this->section_heading_html( $section['title'] ),
					$this->extract_grid_items( $section['lines'] )
				);
				continue;
			}

			if ( strpos( $title_lower, 'team grid' ) !== false || strpos( $title_lower, 'team' ) !== false && strpos( $title_lower, 'grid' ) !== false ) {
				$components[] = $this->make_grid_image_text(
					$this->section_heading_html( $section['title'] ),
					$this->extract_grid_items( $section['lines'] )
				);
				continue;
			}

			if ( strpos( $title_lower, 'services' ) !== false && strpos( $title_lower, 'cards' ) !== false ) {
				$components[] = $this->make_grid_image_text(
					$this->section_heading_html( $section['title'] ),
					$this->extract_grid_items( $section['lines'] )
				);
				continue;
			}

			$components[] = $this->make_block_wysiwyg( $this->section_html_with_heading( $section['lines'], $section['title'] ) );
		}

		return $components;
	}

	private function make_block_wysiwyg( string $html ): array {
		return [
			'acf_fc_layout' => 'blockWysiwyg',
			'contentHtml'   => $html,
			'options'       => [
				'theme'     => '',
				'size'      => 'medium',
				'align'     => 'center',
				'textAlign' => 'left',
			],
		];
	}

	private function make_block_image_text( string $html, int $image_id, string $position ): array {
		return [
			'acf_fc_layout' => 'blockImageText',
			'imagePosition' => $position,
			'image'         => $image_id,
			'contentHtml'   => $html,
			'options'       => [
				'theme' => '',
			],
		];
	}

	private function make_grid_image_text( string $pre_content_html, array $items ): array {
		return [
			'acf_fc_layout' => 'gridImageText',
			'titleAlignment' => 'left',
			'preContentHtml' => $pre_content_html,
			'items'          => $items,
			'options'        => [
				'theme'      => '',
				'maxColumns' => 3,
				'card'       => 0,
			],
		];
	}

	private function extract_grid_items( array $lines ): array {
		$items = [];
		$bullets = $this->collect_bullets( $lines );
		foreach ( $bullets as $bullet ) {
			$items[] = [
				'image'       => 0,
				'contentHtml' => '<p>' . esc_html( $bullet ) . '</p>',
			];
		}
		if ( empty( $items ) ) {
			$body = $this->extract_body_paragraphs( $lines );
			if ( $body ) {
				$items[] = [
					'image'       => 0,
					'contentHtml' => '<p>' . esc_html( $body ) . '</p>',
				];
			}
		}
		return $items;
	}

	private function extract_bulleted_features( array $lines, array $labels ): array {
		$bullets = $this->collect_bullets_after_label( $lines, $labels );
		$features = [];
		$group = '';
		foreach ( $bullets as $bullet ) {
			$clean = $this->strip_formatting( $bullet );
			if ( preg_match( '/^\*\*(.+)\*\*$/', $bullet, $matches ) ) {
				$group = $this->strip_formatting( $matches[1] );
				continue;
			}
			$title = $clean;
			$detail = '';
			if ( strpos( $clean, '—' ) !== false ) {
				$parts = array_map( 'trim', explode( '—', $clean, 2 ) );
				$title = $parts[0] ?? $clean;
				$detail = $parts[1] ?? '';
			}
			if ( $group ) {
				$title = $group . ': ' . $title;
			}
			$features[] = [
				'feature_title'  => $title,
				'feature_detail' => $detail,
				'feature_icon'   => 0,
			];
		}
		return $features;
	}

	private function extract_process_steps( array $lines ): array {
		$steps = [];
		$numbers = $this->collect_numbered_lines( $lines );
		foreach ( $numbers as $line ) {
			$line = $this->strip_formatting( $line );
			$line = preg_replace( '/^\d+\.\s*/', '', $line );
			$title = $line;
			$detail = '';
			$timing = '';
			if ( preg_match( '/^(.+?)\s*\(([^)]+)\)\s*—\s*(.+)$/', $line, $matches ) ) {
				$title = trim( $matches[1] );
				$timing = trim( $matches[2] );
				$detail = trim( $matches[3] );
			} elseif ( strpos( $line, '—' ) !== false ) {
				$parts = array_map( 'trim', explode( '—', $line, 2 ) );
				$title = $parts[0] ?? $line;
				$detail = $parts[1] ?? '';
			}
			$steps[] = [
				'step_title'  => $title,
				'step_detail' => $detail,
				'step_timing' => $timing,
			];
		}
		return $steps;
	}

	private function extract_benefits( array $lines ): array {
		$bullets = $this->collect_bullets( $lines );
		$benefits = [];
		$current_title = '';
		foreach ( $bullets as $bullet ) {
			$clean = $this->strip_formatting( $bullet );
			if ( preg_match( '/^\*\*(.+)\*\*$/', $bullet, $matches ) ) {
				$current_title = $this->strip_formatting( $matches[1] );
				continue;
			}
			if ( $current_title ) {
				$benefits[] = [
					'benefit_title'  => $current_title,
					'benefit_detail' => $clean,
				];
				$current_title = '';
				continue;
			}
			if ( strpos( $clean, '—' ) !== false ) {
				$parts = array_map( 'trim', explode( '—', $clean, 2 ) );
				$benefits[] = [
					'benefit_title'  => $parts[0] ?? $clean,
					'benefit_detail' => $parts[1] ?? '',
				];
			} else {
				$benefits[] = [
					'benefit_title'  => $clean,
					'benefit_detail' => '',
				];
			}
		}
		return $benefits;
	}

	private function extract_faqs( array $lines ): array {
		$faqs = [];
		$bullets = $this->collect_bullets_after_label( $lines, [ 'FAQs', 'FAQ' ] );
		foreach ( $bullets as $bullet ) {
			$question = $this->strip_formatting( $bullet );
			$faqs[] = [
				'question' => $question,
				'answer'   => '',
			];
		}

		foreach ( $lines as $index => $line ) {
			if ( preg_match( '/^\*\*Q:\s*(.+)\*\*$/i', trim( $line ), $match ) || preg_match( '/^Q:\s*(.+)$/i', trim( $line ), $match ) ) {
				$question = $this->strip_formatting( $match[1] );
				$answer = '';
				for ( $i = $index + 1; $i < count( $lines ); $i++ ) {
					$next = trim( $lines[ $i ] );
					if ( preg_match( '/^A:\s*(.+)$/i', $next, $answer_match ) ) {
						$answer = $this->strip_formatting( $answer_match[1] );
						break;
					}
				}
				if ( $question ) {
					$faqs[] = [
						'question' => $question,
						'answer'   => $answer,
					];
				}
			}
		}

		return $faqs;
	}

	private function summarize_service_cards( array $lines ): string {
		$bullets = $this->collect_bullets( $lines );
		$summaries = [];
		foreach ( $bullets as $bullet ) {
			$clean = $this->strip_formatting( $bullet );
			if ( strpos( $clean, '—' ) !== false ) {
				$parts = array_map( 'trim', explode( '—', $clean, 2 ) );
				$summaries[] = $parts[0] . ': ' . ( $parts[1] ?? '' );
			} else {
				$summaries[] = $clean;
			}
		}
		return implode( "\n\n", array_filter( $summaries ) );
	}

	private function extract_nearby_areas( array $lines ): array {
		$areas = [];
		$bullets = $this->collect_bullets( $lines );
		foreach ( $bullets as $bullet ) {
			$clean = $this->strip_formatting( $bullet );
			$name = trim( preg_replace( '/→$/', '', $clean ) );
			if ( ! $name ) {
				continue;
			}
			$areas[] = [
				'nearby_area_name' => $name,
				'nearby_area_url'  => $this->map_nearby_area_url( $name ),
			];
		}
		return $areas;
	}

	private function map_nearby_area_url( string $label ): string {
		$label = strtolower( $label );
		if ( strpos( $label, 'bournemouth' ) !== false ) {
			return 'https://www.lightlysalted.agency/areas-covered/web-design-in-bournemouth/';
		}
		if ( strpos( $label, 'poole' ) !== false ) {
			return 'https://www.lightlysalted.agency/areas-covered/web-design-in-poole/';
		}
		if ( strpos( $label, 'dorset' ) !== false ) {
			return 'https://www.lightlysalted.agency/areas-covered/web-design-in-dorset/';
		}
		if ( strpos( $label, 'areas we cover' ) !== false || strpos( $label, 'view all areas' ) !== false ) {
			return 'https://www.lightlysalted.agency/areas-covered/';
		}
		return '';
	}

	private function split_sections( array $lines ): array {
		$sections = [];
		$current = [
			'title' => '',
			'lines' => [],
		];

		foreach ( $lines as $line ) {
			if ( preg_match( '/^#{2,3}\s+(.*)$/', $line, $matches ) ) {
				if ( $current['title'] || ! empty( $current['lines'] ) ) {
					$sections[] = $current;
				}
				$current = [
					'title' => trim( $matches[1] ),
					'lines' => [],
				];
				continue;
			}
			$current['lines'][] = $line;
		}

		if ( $current['title'] || ! empty( $current['lines'] ) ) {
			$sections[] = $current;
		}

		return $sections;
	}

	private function find_section_lines( array $sections, array $keywords ): array {
		foreach ( $sections as $section ) {
			$title = strtolower( $section['title'] );
			foreach ( $keywords as $keyword ) {
				if ( strpos( $title, strtolower( $keyword ) ) !== false ) {
					return $section['lines'];
				}
			}
		}
		return [];
	}

	private function extract_label_value( $source, array $labels ): string {
		$lines = is_array( $source ) ? $source : preg_split( '/\r\n|\n|\r/', (string) $source );
		foreach ( $lines as $index => $line ) {
			foreach ( $labels as $label ) {
				if ( stripos( $line, $label ) === false ) {
					continue;
				}
				if ( preg_match( '/:\s*(.+)$/', $line, $matches ) ) {
					$value = trim( $matches[1] );
					if ( $value ) {
						return $this->strip_formatting( $value );
					}
				}
				$blockquote = $this->collect_blockquote_lines( $lines, $index + 1 );
				if ( $blockquote ) {
					return $this->strip_formatting( $blockquote );
				}
			}
		}
		return '';
	}

	private function extract_body_paragraphs( array $lines ): string {
		$body = [];
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				continue;
			}
			if ( preg_match( '/^\s*(?:-|\d+\.)\s+/', $trimmed ) ) {
				continue;
			}
			if ( strpos( $trimmed, '**' ) === 0 && strpos( $trimmed, '**' ) !== false ) {
				continue;
			}
			if ( strpos( $trimmed, '---' ) === 0 ) {
				continue;
			}
			if ( strpos( $trimmed, '>' ) === 0 ) {
				$trimmed = ltrim( $trimmed, '> ' );
			}
			$body[] = $this->strip_formatting( $trimmed );
		}
		return implode( "\n\n", $body );
	}

	private function build_section_html( array $lines, array $ignore_labels ): string {
		$html = '';
		$paragraphs = [];
		$list_items = [];

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				continue;
			}
			foreach ( $ignore_labels as $label ) {
				if ( stripos( $trimmed, $label ) !== false ) {
					continue 2;
				}
			}
			if ( preg_match( '/^\s*(?:-|\d+\.)\s+(.+)$/', $trimmed, $matches ) ) {
				$list_items[] = $this->strip_formatting( $matches[1] );
				continue;
			}
			if ( strpos( $trimmed, '>' ) === 0 ) {
				$trimmed = ltrim( $trimmed, '> ' );
			}
			$paragraphs[] = $this->strip_formatting( $trimmed );
		}

		foreach ( $paragraphs as $paragraph ) {
			$html .= '<p>' . esc_html( $paragraph ) . '</p>';
		}
		if ( ! empty( $list_items ) ) {
			$html .= '<ul>';
			foreach ( $list_items as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	private function section_heading_html( string $title ): string {
		return '<h2>' . esc_html( $title ) . '</h2>';
	}

	private function section_html_with_heading( array $lines, string $title = '' ): string {
		$body = $this->extract_body_paragraphs( $lines );
		$html = '';
		if ( $title ) {
			$html .= '<h2>' . esc_html( $title ) . '</h2>';
		}
		if ( $body ) {
			foreach ( preg_split( '/\n{2,}/', $body ) as $paragraph ) {
				$html .= '<p>' . esc_html( $paragraph ) . '</p>';
			}
		}
		$bullets = $this->collect_bullets( $lines );
		if ( $bullets ) {
			$html .= '<ul>';
			foreach ( $bullets as $bullet ) {
				$html .= '<li>' . esc_html( $bullet ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	private function collect_blockquote_lines( array $lines, int $start_index ): string {
		$values = [];
		for ( $i = $start_index; $i < count( $lines ); $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( '' === $line ) {
				break;
			}
			if ( strpos( $line, '>' ) === 0 ) {
				$values[] = ltrim( $line, '> ' );
				continue;
			}
			break;
		}
		return trim( implode( ' ', $values ) );
	}

	private function collect_bullets_after_label( array $lines, array $labels ): array {
		$start_index = -1;
		foreach ( $lines as $index => $line ) {
			foreach ( $labels as $label ) {
				if ( stripos( $line, $label ) !== false ) {
					$start_index = $index + 1;
					break 2;
				}
			}
		}
		if ( -1 === $start_index ) {
			return [];
		}
		$slice = array_slice( $lines, $start_index );
		return $this->collect_bullets( $slice );
	}

	private function collect_bullets( array $lines ): array {
		$bullets = [];
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				continue;
			}
			if ( preg_match( '/^\s*-\s+(.+)$/', $trimmed, $matches ) ) {
				$bullets[] = $this->strip_formatting( $matches[1] );
			}
		}
		return $bullets;
	}

	private function collect_numbered_lines( array $lines ): array {
		$items = [];
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $matches ) ) {
				$items[] = $matches[0];
			}
		}
		return $items;
	}

	private function strip_formatting( string $text ): string {
		$text = preg_replace( '/\*\*(.+?)\*\*/', '$1', $text );
		$text = preg_replace( '/`(.+?)`/', '$1', $text );
		$text = str_replace( '**', '', $text );
		$text = preg_replace( '/^\*+\s*/', '', $text );
		$text = preg_replace( '/\s*\*+$/', '', $text );
		return trim( $text );
	}

	private function normalize_repeater_rows( array $rows, array $keys ): array {
		$normalized = [];
		foreach ( $rows as $row ) {
			$entry = [];
			foreach ( $keys as $key ) {
				$entry[ $key ] = $row[ $key ] ?? ( strpos( $key, 'image' ) !== false || strpos( $key, 'icon' ) !== false ? 0 : '' );
			}
			$normalized[] = $entry;
		}
		return $normalized;
	}

	private function get_sitemap_images( string $type, string $slug, array $options ): array {
		$map = [];
		if ( 'service' === $type ) {
			$map = $options['service_sitemap'] ?? [];
		} elseif ( 'area' === $type ) {
			$map = $options['area_sitemap'] ?? [];
		} elseif ( 'page' === $type ) {
			$map = $options['page_sitemap'] ?? [];
		}

		$images = $map[ $slug ]['images'] ?? [];
		return [
			'hero'   => $images[0] ?? '',
			'others' => array_slice( $images, 1 ),
		];
	}

	private function fetch_sitemap_items( string $sitemap_url ): array {
		$response = wp_remote_get( $sitemap_url, [ 'timeout' => 30 ] );
		if ( is_wp_error( $response ) ) {
			WP_CLI::warning( 'Failed to fetch sitemap: ' . $response->get_error_message() );
			return [];
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return [];
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		if ( ! $xml ) {
			return [];
		}

		$namespaces = $xml->getNamespaces( true );
		$image_ns  = $namespaces['image'] ?? null;

		$items = [];
		foreach ( $xml->url as $url_node ) {
			$loc = trim( (string) $url_node->loc );
			if ( empty( $loc ) ) {
				continue;
			}
			$slug = $this->slug_from_url( $loc );
			if ( ! $slug ) {
				continue;
			}
			$images = [];
			if ( $image_ns ) {
				foreach ( $url_node->children( $image_ns )->image as $image_node ) {
					$image_loc = trim( (string) $image_node->loc );
					if ( $image_loc ) {
						$images[] = $image_loc;
					}
				}
			}
			$items[ $slug ] = [
				'url'    => $loc,
				'images' => $images,
			];
		}

		return $items;
	}

	private function ensure_media_dependencies(): void {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
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

	private function slug_from_url( string $url ): string {
		$path = parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path, '/' ) : '';
		if ( ! $path ) {
			return '';
		}
		$parts = explode( '/', $path );
		return end( $parts );
	}

	private function slugify( string $text ): string {
		$text = strtolower( trim( $text ) );
		$text = preg_replace( '/[^a-z0-9\s-]/', '', $text );
		$text = preg_replace( '/\s+/', '-', $text );
		$text = preg_replace( '/-+/', '-', $text );
		return trim( $text, '-' );
	}

	private function write_report( array $report ): void {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			WP_CLI::warning( 'Uploads directory not available, skipping report.' );
			return;
		}

		$filename = 'notion-backlog-import-report-' . gmdate( 'Ymd-His' ) . '.json';
		$path     = trailingslashit( $uploads['basedir'] ) . $filename;
		$encoded  = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === file_put_contents( $path, $encoded ) ) {
			WP_CLI::warning( 'Failed to write report to uploads.' );
			return;
		}

		WP_CLI::log( 'Report written to: ' . $path );
	}
}

WP_CLI::add_command( 'ls import-notion-backlog', 'LS_Notion_Backlog_Importer' );
