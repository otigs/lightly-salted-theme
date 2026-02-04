<?php
/**
 * WP-CLI: Attach SVG icons from local filesystem to ACF image fields.
 *
 * Usage (from WordPress root):
 *   wp eval-file wp-content/themes/lightly-salted-theme/inc/scripts/apply-icons.php --dry-run
 *
 * Options:
 *   --dry-run      Only log what would be changed, do not write.
 *   --base=/path   Base directory containing SVGs
 *
 * Notes:
 *   - Adjust the ICON_MAP and base path to match your actual filenames.
 *   - By default this script looks for files like:
 *       {base}/services/{service-slug}.svg  -> service.service_icon
 *       {base}/areas/{area-slug}.svg       -> area.area_icon
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

use WP_CLI\Utils;

class LS_Icon_Importer
{
    private const DEFAULT_BASE = '/Volumes/Lightly Salted/Production Assets/Notioly-Collection';

    /**
     * Map of post types to ACF field + subdirectory under base.
     * Adjust to fit your naming and ACF field names.
     */
    private const ICON_MAP = [
        'service' => [
            'field'  => 'service_icon', // ACF image field on Service CPT
            'subdir' => 'services',     // e.g. {base}/services/{slug}.svg
        ],
        'area' => [
            'field'  => 'area_icon',    // ACF image field on Area CPT
            'subdir' => 'areas',        // e.g. {base}/areas/{slug}.svg
        ],
    ];

    public function __invoke($args, $assoc_args): void
    {
        if (!function_exists('update_field')) {
            WP_CLI::error('ACF is not active. Install and activate Advanced Custom Fields.');
        }

        $dry_run = (bool) Utils\get_flag_value($assoc_args, 'dry-run', false);
        $base    = (string) Utils\get_flag_value($assoc_args, 'base', self::DEFAULT_BASE);

        if (!is_dir($base)) {
            WP_CLI::error('Base icon directory does not exist: ' . $base);
        }

        $this->ensure_media_dependencies();

        foreach (self::ICON_MAP as $post_type => $config) {
            $this->process_post_type($post_type, $config, $base, $dry_run);
        }

        WP_CLI::success('Icon import completed.');
    }

    private function process_post_type(string $post_type, array $config, string $base, bool $dry_run): void
    {
        $field  = $config['field'];
        $subdir = trim($config['subdir'], '/');

        WP_CLI::log("Processing post type '{$post_type}' for field '{$field}'");

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if (empty($posts)) {
            WP_CLI::log("No posts found for post type {$post_type}.");
            return;
        }

        foreach ($posts as $post_id) {
            $slug = get_post_field('post_name', $post_id);
            $path = trailingslashit($base) . $subdir . '/' . $slug . '.svg';

            if (!file_exists($path)) {
                WP_CLI::log("No SVG found for {$post_type} {$slug} at {$path}");
                continue;
            }

            $current = get_field($field, $post_id);
            if ($current) {
                WP_CLI::log("{$post_type} {$slug}: field '{$field}' already set, skipping.");
                continue;
            }

            if ($dry_run) {
                WP_CLI::log("[DRY RUN] Would attach {$path} to {$post_type} {$slug} ({$field}).");
                continue;
            }

            $attachment_id = $this->import_svg_as_attachment($path, $post_id);
            if ($attachment_id) {
                update_field($field, $attachment_id, $post_id);
                WP_CLI::log("Attached icon to {$post_type} {$slug}: attachment {$attachment_id}");
            } else {
                WP_CLI::warning("Failed to import SVG for {$post_type} {$slug} from {$path}");
            }
        }
    }

    private function import_svg_as_attachment(string $file_path, int $post_id): int
    {
        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir']) || !is_dir($upload_dir['basedir'])) {
            WP_CLI::warning('Uploads directory not available.');
            return 0;
        }

        $filename   = basename($file_path);
        $contents   = file_get_contents($file_path);
        if ($contents === false) {
            WP_CLI::warning('Could not read file: ' . $file_path);
            return 0;
        }

        $upload = wp_upload_bits($filename, null, $contents);
        if (!empty($upload['error'])) {
            WP_CLI::warning('Upload error: ' . $upload['error']);
            return 0;
        }

        $filetype = wp_check_filetype($upload['file'], null);

        $attachment = [
            'post_mime_type' => $filetype['type'] ?: 'image/svg+xml',
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attach_id)) {
            WP_CLI::warning('Attachment insert failed: ' . $attach_id->get_error_message());
            return 0;
        }

        // For SVGs, metadata generation is minimal but this call is harmless.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));

        return (int) $attach_id;
    }

    private function ensure_media_dependencies(): void
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
}

WP_CLI::add_command('ls apply-icons', LS_Icon_Importer::class);

