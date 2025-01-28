<?php
/**
 * Plugin Name: WP Bunny Stream
 * Description: Offload and stream videos from Bunny's Stream Service via WordPress Media Library.
 * Version: 0.1.0
 * Author: Brandon Meyer
 * Text Domain: wp-bunnystream
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include Bunny.net API handler.
require_once plugin_dir_path(__FILE__) . 'includes/Integration/BunnyApi.php';

// Include Bunny.net settings page.
require_once plugin_dir_path(__FILE__) . 'includes/Admin/BunnySettings.php';

// Include Bunny.net database manager.
require_once plugin_dir_path(__FILE__) . 'includes/Integration/BunnyDatabaseManager.php';

// Include Bunny.net user integration.
require_once plugin_dir_path(__FILE__) . 'includes/Integration/BunnyUserIntegration.php';

/**
 * Initialize the plugin.
 */
function wp_bunnystream_init() {
    // Initialize Bunny.net settings and API handler.
    $access_key = get_option('bunny_net_access_key', '');
    $library_id = get_option('bunny_net_library_id', '');

    // Initialize BunnySettings to ensure the settings page appears.
    new \WP_BunnyStream\Admin\BunnySettings();

    // Initialize database manager for Bunny.net collections.
    new \WP_BunnyStream\Integration\BunnyDatabaseManager();

    // Initialize user integration for handling collections.
    new \WP_BunnyStream\Integration\BunnyUserIntegration();

    // Global Bunny.net API instance (optional).
    if (!empty($access_key) && !empty($library_id)) {
        $GLOBALS['bunny_net_api'] = new \WP_BunnyStream\Integration\BunnyApi($access_key, $library_id);
    }
}
add_action('plugins_loaded', 'wp_bunnystream_init');

/**
 * Enqueue admin scripts for Bunny.net integration.
 */
function wp_bunnystream_enqueue_admin_scripts($hook) {
    // Check if we are on the Media Library or post editor page
    if ('upload.php' === $hook || 'post.php' === $hook || 'post-new.php' === $hook) {
        wp_enqueue_script(
            'bunny-video-upload',
            plugin_dir_url(__FILE__) . 'assets/js/bunny-video-upload.js',
            ['jquery'], // Add jQuery as a dependency if needed
            '1.0.0',
            true // Load the script in the footer
        );

        // Add localized data for AJAX and other variables
        wp_localize_script('bunny-video-upload', 'bunnyVideoUpload', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('bunny_video_upload_nonce'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'wp_bunnystream_enqueue_admin_scripts');
