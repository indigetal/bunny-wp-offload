<?php
/**
 * Bunny.net Settings Page
 * Provides a WordPress admin page for storing Bunny.net credentials.
 *
 * @package TutorLMSBunnyNetIntegration\Admin
 * @since 2.0.0
 */

 namespace Tutor\BunnyNetIntegration\Admin;

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
      * Initialize the settings page.
      */
     public function __construct() {
         add_action('admin_menu', [$this, 'addSettingsPage']);
         add_action('admin_init', [$this, 'registerSettings']);
 
         // Check for video object creation after saving settings
         add_action('update_option_' . self::OPTION_ACCESS_KEY, [$this, 'checkAndCreateVideoObject'], 10, 2);
         add_action('update_option_' . self::OPTION_LIBRARY_ID, [$this, 'checkAndCreateVideoObject'], 10, 2);
 
         // AJAX handler for manual video object creation
         add_action('wp_ajax_bunny_manual_create_video', [$this, 'handleManualVideoCreationAjax']);
 
         // AJAX handler for creating a library
         add_action('wp_ajax_bunny_create_library', [$this, 'handleCreateLibraryAjax']);
     }
 
     /**
      * Add the Bunny.net settings page to the WordPress admin menu.
      */
     public function addSettingsPage() {
         add_options_page(
             __('Bunny.net Settings', 'tutor-lms-bunnynet-integration'),
             __('Bunny.net Settings', 'tutor-lms-bunnynet-integration'),
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
             __('Bunny.net Credentials', 'tutor-lms-bunnynet-integration'),
             null,
             'bunny-net-settings'
         );
 
         add_settings_field(
             self::OPTION_ACCESS_KEY,
             __('Access Key', 'tutor-lms-bunnynet-integration'),
             [$this, 'renderAccessKeyField'],
             'bunny-net-settings',
             'bunny_net_credentials'
         );
 
         add_settings_field(
             self::OPTION_LIBRARY_ID,
             __('Library ID', 'tutor-lms-bunnynet-integration'),
             [$this, 'renderLibraryIdField'],
             'bunny-net-settings',
             'bunny_net_credentials'
         );
 
         add_settings_field(
             'bunny_library_creation',
             __('Create Video Library', 'tutor-lms-bunnynet-integration'),
             [$this, 'renderLibraryCreationField'],
             'bunny-net-settings',
             'bunny_net_credentials'
         );
 
         add_settings_field(
             'bunny_manual_video_creation',
             __('Manually Create Video Object', 'tutor-lms-bunnynet-integration'),
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
         echo '<input type="text" name="' . self::OPTION_ACCESS_KEY . '" value="' . $value . '" class="regular-text" />';
         echo '<p><a href="https://support.bunny.net/hc/en-us/articles/13503339878684-How-to-find-your-stream-API-key" target="_blank">'
             . __('To learn how to obtain your stream API key, see How to obtain your Stream API key guide.', 'tutor-lms-bunnynet-integration')
             . '</a></p>';
     }
 
     /**
      * Render the Library ID field.
      */
     public function renderLibraryIdField() {
         $value = esc_attr(get_option(self::OPTION_LIBRARY_ID, ''));
         echo '<input type="text" name="' . self::OPTION_LIBRARY_ID . '" value="' . $value . '" class="regular-text" />';
     }
 
     /**
      * Render the Library Creation field.
      */
     public function renderLibraryCreationField() {
         echo '<p>' . esc_html__('Create a new video library if you don’t already have one.', 'tutor-lms-bunnynet-integration') . '</p>';
         echo '<input type="text" id="bunny-library-name" placeholder="Enter Library Name" class="regular-text" />';
         echo '<button id="bunny-create-library" class="button button-primary">' . esc_html__('Create Library', 'tutor-lms-bunnynet-integration') . '</button>';
         echo '<p id="bunny-library-creation-status"></p>';
 
         // Add AJAX script for handling library creation
         echo '<script>
             document.getElementById("bunny-create-library").addEventListener("click", function(event) {
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
                        library_name: libraryName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const statusElement = document.getElementById("bunny-library-creation-status");
                    if (data.success) {
                        statusElement.textContent = "Library created successfully: " + data.data.libraryId;
                        document.getElementById("bunny-library-name").value = "";
                    } else {
                        statusElement.textContent = "Error: " + (data.data?.message || "An unknown error occurred.");
                        console.error("Error response:", data);
                    }
                })
                .catch(error => {
                    console.error("AJAX error:", error);
                    alert("An unexpected error occurred.");
                });
            });

         </script>';
        }

    /**
     * Render the settings page HTML.
     */
    public function renderSettingsPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Bunny.net Settings', 'tutor-lms-bunnynet-integration') . '</h1>';
    
        // Display success message if video object was created
        if (get_transient('bunny_net_video_created')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('A Bunny.net video object was successfully created.', 'tutor-lms-bunnynet-integration') . '</p>';
            echo '</div>';
    
            // Clear the transient so the message only shows once
            delete_transient('bunny_net_video_created');
        }
    
        echo '<form method="post" action="options.php">';
        settings_fields('bunny_net_settings');
        do_settings_sections('bunny-net-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }    

    /**
     * Check and create a video object if Access Key or Library ID changes.
     *
     * @param mixed $old_value The old option value.
     * @param mixed $value The new option value.
     */
    public function checkAndCreateVideoObject($old_value, $value) {
        $access_key = get_option(self::OPTION_ACCESS_KEY, '');
        $library_id = get_option(self::OPTION_LIBRARY_ID, '');

        if (!empty($access_key) && !empty($library_id)) {
            // Use the BunnyApi class to create a test video object
            $api = new \Tutor\BunnyNetIntegration\Integration\BunnyApi($access_key, $library_id);
            $response = $api->createVideo(__('Test Video', 'tutor-lms-bunnynet-integration'));

            if (is_wp_error($response)) {
                error_log('Bunny API Error: ' . $response->get_error_message());
                set_transient('bunny_net_video_created', false, 60);
                return;
            }

            if (isset($response['guid'])) {
                error_log('Bunny API Success: Video object created with GUID ' . $response['guid']);
                set_transient('bunny_net_video_created', true, 60);
            } else {
                error_log('Bunny API Error: ' . json_encode($response));
                set_transient('bunny_net_video_created', false, 60);
            }
        } else {
            error_log('Bunny API Error: Missing Access Key or Library ID.');
        }
    }

    /**
     * Render the Manual Video Creation button.
     */
    public function renderManualVideoCreationButton() {
        echo '<p>' . esc_html__('A video object is automatically created when the Library ID and API Key are saved, but you can manually create a new video object if necessary.', 'tutor-lms-bunnynet-integration') . '</p>';
        echo '<button id="bunny-create-video-object" class="button button-secondary">' . esc_html__('Create Video Object', 'tutor-lms-bunnynet-integration') . '</button>';

        // Add JavaScript for the AJAX request
        echo '<script>
            document.getElementById("bunny-create-video-object").addEventListener("click", function(event) {
                event.preventDefault();

                fetch(ajaxurl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ action: "bunny_manual_create_video" })
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
     * Handle AJAX request for manual video object creation.
     */
    public function handleManualVideoCreationAjax() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tutor-lms-bunnynet-integration')], 403);
        }
    
        // Get stored credentials
        $access_key = get_option(self::OPTION_ACCESS_KEY, '');
        $library_id = get_option(self::OPTION_LIBRARY_ID, '');
    
        if (empty($access_key) || empty($library_id)) {
            wp_send_json_error(['message' => __('API Key or Library ID is missing.', 'tutor-lms-bunnynet-integration')], 400);
        }
    
        // Call the Bunny API to create a video object
        $api = new \Tutor\BunnyNetIntegration\Integration\BunnyApi($access_key, $library_id);
    
        $response = $api->sendRequest('/library/' . $library_id . '/videos', [
            'title' => __('Manual Video Object', 'tutor-lms-bunnynet-integration'),
        ]);
    
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }
    
        if (isset($response['guid'])) {
            wp_send_json_success(['message' => __('Video object created successfully.', 'tutor-lms-bunnynet-integration')]);
        } else {
            wp_send_json_error(['message' => __('Failed to create video object. Check the logs for details.', 'tutor-lms-bunnynet-integration')], 500);
        }
    }    

    public function handleCreateLibraryAjax() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'tutor-lms-bunnynet-integration')], 403);
        }
    
        // Validate input
        $library_name = sanitize_text_field($_POST['library_name'] ?? '');
        if (empty($library_name)) {
            wp_send_json_error(['message' => __('Library name is required.', 'tutor-lms-bunnynet-integration')], 400);
        }
    
        // Call the Bunny API to create a library
        $access_key = get_option(self::OPTION_ACCESS_KEY, '');
        $api = new \Tutor\BunnyNetIntegration\Integration\BunnyApi($access_key, null);
    
        $response = $api->sendRequest('/library', [
            'name' => $library_name,
            'readOnly' => false,
            'replicationRegions' => [],
        ]);
    
        // Debugging log to check API response
        error_log('Bunny API Create Library Response: ' . json_encode($response));
    
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }
    
        if (isset($response['guid'])) {
            wp_send_json_success(['libraryId' => $response['guid']]);
        } else {
            wp_send_json_error(['message' => __('Failed to create library. Check API response.', 'tutor-lms-bunnynet-integration')], 500);
        }
    }
            

}

// Initialize the settings page.
new BunnySettings();
