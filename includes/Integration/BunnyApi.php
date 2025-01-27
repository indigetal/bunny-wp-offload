<?php
/**
 * Bunny.net API Handler Class
 * Handles interactions with Bunny.net HTTP API for video uploads, retrieval, and management.
 *
 * @package TutorLMSBunnyNetIntegration\Integration
 * @since 2.0.0
 */

namespace Tutor\BunnyNetIntegration\Integration;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BunnyApi {

    /**
     * Bunny.net API Access Key
     * @var string
     */
    private $access_key;

    /**
     * Bunny.net Video Library ID
     * @var string
     */
    private $library_id;

    /**
     * Bunny.net Base API URL
     * @var string
     */
    private $api_url = 'https://video.bunnycdn.com/library';

    /**
     * Whether a video object has been created
     * @var bool
     */
    private $video_created = false;

    /**
     * Constructor
     *
     * @param string $access_key Bunny.net API Access Key.
     * @param string $library_id Bunny.net Video Library ID.
     */
    public function __construct($access_key, $library_id) {
        $this->access_key = $access_key;
        $this->library_id = $library_id;
    }

    /**
     * Send a request to the Bunny.net API.
     *
     * @param string $endpoint The API endpoint (e.g., '/library').
     * @param array  $body     The request body as an associative array.
     * @return array|WP_Error  The API response or WP_Error on failure.
     */
    public function sendRequest($endpoint, $method = 'POST', $body = []) {
        $url = $this->api_url . $endpoint;
    
        $response = wp_remote_request($url, [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
        ]);
    
        error_log('Bunny API Request: ' . json_encode([
            'url' => $url,
            'method' => $method,
            'body' => $body,
        ]));
    
        if (is_wp_error($response)) {
            error_log('Bunny API Error: ' . $response->get_error_message());
            return $response;
        }
    
        error_log('Bunny API Response: ' . wp_remote_retrieve_body($response));
        return json_decode(wp_remote_retrieve_body($response), true);
    }        

    /**
     * Create a new video library in Bunny.net.
     *
     * @param string $name The name of the library.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function createLibrary($name) {
        return $this->sendRequest('', 'POST', [
            'name' => $name,
            'readOnly' => false,
            'replicationRegions' => [],
        ]);
    }                

    /**
     * Create a new video object in Bunny.net.
     *
     * @param string $title The title of the video.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function createVideo($title) {
        if (empty($this->library_id)) {
            return new \WP_Error('invalid_library_id', __('Library ID is missing.', 'tutor-lms-bunnynet-integration'));
        }
    
        return $this->sendRequest('/' . rawurlencode($this->library_id) . '/videos', 'POST', [
            'title' => $title,
        ]);
    }    

    /**
     * Check if a video object has been created.
     *
     * @return bool
     */
    public function isVideoCreated() {
        return $this->video_created;
    }

    /**
     * Upload video content to Bunny.net.
     *
     * @param string $file_path Path to the video file.
     * @param string $video_guid The GUID of the video.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function uploadVideo($file_path, $video_guid) {
        $url = $this->api_url . '/' . $this->library_id . '/videos/' . $video_guid;
    
        // Open the file and attach it to the request
        $file = file_get_contents($file_path);
        if ($file === false) {
            return new \WP_Error('file_read_error', __('Failed to read the video file.', 'tutor-lms-bunnynet-integration'));
        }
    
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_key,
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $file,
        ]);
    
        if (is_wp_error($response)) {
            error_log('Bunny API Upload Error: ' . $response->get_error_message());
            return $response;
        }
    
        return json_decode(wp_remote_retrieve_body($response), true);
    }        

    /**
     * Retrieve video playback URL.
     *
     * @param string $video_guid The GUID of the video.
     * @return string The playback URL.
     */
    public function getPlaybackUrl($video_guid) {
        return 'https://' . $this->library_id . '.b-cdn.net/' . $video_guid . '/play';
    }

    /**
     * Check the transcoding status of a video.
     *
     * @param string $video_guid The GUID of the video.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function getVideoStatus($video_guid) {
        $url = $this->api_url . '/' . $this->library_id . '/videos/' . $video_guid;
    
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_key,
            ],
        ]);
    
        if (is_wp_error($response)) {
            error_log('Bunny API Video Status Error: ' . $response->get_error_message());
            return $response;
        }
    
        $response_body = wp_remote_retrieve_body($response);
        error_log('Bunny API Video Status Response: ' . $response_body);
    
        return json_decode($response_body, true);
    }        
}
