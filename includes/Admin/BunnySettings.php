<?php
/**
 * Bunny.net Settings Page
 * Provides a WordPress admin page for storing Bunny.net credentials.
 *
 * @package WPBunnyStream\Admin
 * @since 0.1.0
 */

namespace WPBunnyStream\Integration;

use WPBunnyStream\Integration\BunnyApi;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BunnySettings {

    /**
     * Option keys for storing credentials.
     */
    const OPTION_ACCESS_KEY = 'bunny_net_access_key';
    const OPTION_LIBRARY_ID = 'bunny_net_library_id';

    /**
     * BunnyApi instance.
     */
    private $bunnyApi;

    /**
     * Initialize the settings page.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);

        // AJAX handlers
        add_action('wp_ajax_bunny_manual_create_video', [$this, 'handleManualVideoCreationAjax']);
        add_action('wp_ajax_bunny_create_library', [$this, 'handleCreateLibraryAjax']);

        // Initialize BunnyApi instance
        $accessKey = get_option(self::OPTION_ACCESS_KEY, '');
        $libraryId = get_option(self::OPTION_LIBRARY_ID, '');
        $this->bunnyApi = new BunnyApi($accessKey, $libraryId);

        // Hook to check and create video object when options are updated
        add_action('update_option_' . self::OPTION_ACCESS_KEY, [$this, 'checkAndCreateVideoObject'], 10, 2);
        add_action('update_option_' . self::OPTION_LIBRARY_ID, [$this, 'checkAndCreateVideoObject'], 10, 2);
    }

    /**
     * Add the Bunny.net settings page to the WordPress admin menu.
     */
    public function addSettingsPage() {
        add_options_page(
            __('Bunny.net Settings', 'wp-bunnystream'),
            __('Bunny.net Settings', 'wp-bunnystream'),
            'manage_options',
            'bunny-net-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Register settings for Bunny.net credentials.
     */
    public function registerSettings() {
        register_setting('bunny_net_settings', self::OPTION_ACCESS_KEY);
        register_setting('bunny_net_settings', self::OPTION_LIBRARY_ID);

        add_settings_section(
            'bunny_net_credentials',
            __('Bunny.net Credentials', 'wp-bunnystream'),
            null,
            'bunny-net-settings'
        );

        add_settings_field(
            self::OPTION_ACCESS_KEY,
            __('Access Key', 'wp-bunnystream'),
            [$this, 'renderAccessKeyField'],
            'bunny-net-settings',
            'bunny_net_credentials'
        );

        add_settings_field(
            'bunny_library_creation',
            __('Library Creation', 'wp-bunnystream'),
            [$this, 'renderLibraryCreationField'],
            'bunny-net-settings',
            'bunny_net_credentials'
        );

        add_settings_field(
            self::OPTION_LIBRARY_ID,
            __('Library ID', 'wp-bunnystream'),
            [$this, 'renderLibraryIdField'],
            'bunny-net-settings',
            'bunny_net_credentials'
        );

        add_settings_field(
            'bunny_manual_video_creation',
            __('Manual Video Creation', 'wp-bunnystream'),
            [$this, 'renderManualVideoCreationButton'],
            'bunny-net-settings',
            'bunny_net_credentials'
        );

    }

    /**
     * Render the Access Key field.
     */
    public function renderAccessKeyField() {
        $value = esc_attr(get_option(self::OPTION_ACCESS_KEY, ''));
        echo "<input type='text' name='" . self::OPTION_ACCESS_KEY . "' value='$value' class='regular-text' />";
        echo '<p class="description">';
        echo sprintf(
            __('To learn how to obtain your stream API key, see <a href="%s" target="_blank">How to obtain your Stream API key guide</a>.', 'wp-bunnystream'),
            'https://support.bunny.net/hc/en-us/articles/13503339878684-How-to-find-your-stream-API-key'
        );
        echo '</p>';
    }

    /**
     * Render the Library ID field.
     */
    public function renderLibraryIdField() {
        $library_id = get_option(self::OPTION_LIBRARY_ID, '');
        echo '<input type="text" id="bunny-library-id" name="bunny_library_id" value="' . esc_attr($library_id) . '" class="regular-text" readonly />';
    }    

    /**
     * Render the Manual Video Creation button.
     */
    public function renderManualVideoCreationButton() {
        echo '<p>' . esc_html__('Before uploading any video content, you must first create a video object. A "test" video object is automatically created when the Library ID and API Key are initially saved or when they are changed, but you can manually create a new "test" video object if necessary.', 'wp-bunnystream') . '</p>';
        echo '<button id="bunny-create-video-object" class="button button-secondary">' . esc_html__('Create Video Object', 'wp-bunnystream') . '</button>';

        // Add JavaScript for the AJAX request
        echo '<script>
            document.getElementById("bunny-create-video-object").addEventListener("click", function(event) {
                event.preventDefault();

                fetch(ajaxurl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ action: "bunny_manual_create_video", title: "test" })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Video object created successfully!");
                    } else {
                        const errorMessage = data.data?.message || "An unknown error occurred.";
                        alert("Error: " + errorMessage);
                        console.error("Error response:", data);
                    }
                })
                .catch(error => {
                    alert("An unexpected error occurred.");
                    console.error(error);
                });
            });

        </script>';
    }

    /**
     * Render the Library Creation field.
     */
    public function renderLibraryCreationField() {
        echo '<p>' . esc_html__('Create a new video library if you don’t already have one.', 'wp-bunnystream') . '</p>';
        echo '<input type="text" id="bunny-library-name" placeholder="Enter Library Name" class="regular-text" />';
        echo '<button id="bunny-create-library" class="button button-primary">' . esc_html__('Create Library', 'wp-bunnystream') . '</button>';
        echo '<p id="bunny-library-creation-status"></p>';

        // Add AJAX script for handling library creation
        echo '<script>
            document.getElementById("bunny-create-library").addEventListener("click", function (event) {
                event.preventDefault();

                const libraryName = document.getElementById("bunny-library-name").value;

                if (!libraryName) {
                    alert("Please enter a library name.");
                    return;
                }

                fetch(ajaxurl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: new URLSearchParams({
                        action: "bunny_create_library",
                        library_name: libraryName,
                    }),
                })
                    .then((response) => response.json())
                    .then((data) => {
                        const statusElement = document.getElementById("bunny-library-creation-status");
                        if (data.success) {
                            statusElement.textContent = "Library created successfully.";
                            document.getElementById("bunny-library-id").value = data.libraryId; // Dynamically update Library ID
                            document.getElementById("bunny-library-name").value = ""; // Clear the input field
                        } else {
                            statusElement.textContent = "Error: " + (data.message || "An unknown error occurred.");
                            console.error("Error response:", data);
                        }
                    })
                    .catch((error) => {
                        console.error("AJAX error:", error);
                        alert("An unexpected error occurred. Please check the console for more details.");
                    });
            });
        </script>';
    }

    /**
     * Render the settings page.
     */
    public function renderSettingsPage() {
        echo "<form action='options.php' method='post'>";
        settings_fields('bunny_net_settings');
        do_settings_sections('bunny-net-settings');
        submit_button(__('Save Settings', 'wp-bunnystream'));
        echo "</form>";
    }

    /**
     * Handle AJAX request to create a video object.
     */
    public function handleManualVideoCreationAjax() {
        error_log('handleManualVideoCreationAjax called');

        $access_key = get_option(self::OPTION_ACCESS_KEY, '');
        $library_id = get_option(self::OPTION_LIBRARY_ID, '');

        $title = sanitize_text_field($_POST['title'] ?? 'test'); // Default title

        error_log("Access Key: {$access_key}, Library ID: {$library_id}, Title: {$title}");

        if (empty($title) || empty($library_id)) {
            wp_send_json_error(['message' => __('Title or Library ID is missing.', 'wp-bunnystream')], 400);
        }

        $response = $this->bunnyApi->createVideoObject($title);

        if (is_wp_error($response)) {
            error_log('Error creating video object: ' . $response->get_error_message());
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        if (isset($response['guid'])) {
            error_log('Video object created successfully: ' . $response['guid']);
            wp_send_json_success([
                'message' => __('Video object created successfully.', 'wp-bunnystream'),
            ]);            
        } else {
            error_log('Failed to create video object. Response: ' . print_r($response, true));
            wp_send_json_error(['message' => __('Failed to create video object.', 'wp-bunnystream')], 500);
        }
    }        

    /**
     * Handle AJAX request to create a library.
     */
    public function handleCreateLibraryAjax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'wp-bunnystream')], 403);
        }

        $libraryName = sanitize_text_field($_POST['library_name'] ?? '');

        if (empty($libraryName)) {
            wp_send_json_error(['message' => __('Library name is required.', 'wp-bunnystream')], 400);
        }

        // Call the Bunny API to create a library
        $response = $this->bunnyApi->createLibrary($libraryName);

        // Debug logging to check the API response
        error_log('Bunny API Create Library Response: ' . print_r($response, true));

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        if (isset($response['guid'])) {
            // Update the Library ID option in WordPress
            update_option(self::OPTION_LIBRARY_ID, $response['guid']);

            error_log('Library ID updated in settings: ' . $response['guid']);

            wp_send_json_success([
                'message' => __('Library created successfully.', 'wp-bunnystream'),
                'libraryId' => $response['guid'], // Include Library ID in response
            ]);
        } else {
            error_log('Failed to create library. Response: ' . print_r($response, true));
            wp_send_json_error(['message' => __('Failed to create library. Check API response.', 'wp-bunnystream')], 500);
        }
    }    

}
