<?php

namespace Tutor\BunnyNetIntegration\Integration;

class BunnyApi {
    private $video_base_url = 'https://video.bunnycdn.com/'; // For video-related actions
    private $library_base_url = 'https://api.bunny.net/';    // For library-related actions
    private $access_key;
    private $library_id;

    public function __construct($access_key, $library_id) {
        $this->access_key = $access_key;
        $this->library_id = $library_id;
    }

    /**
     * Generic method to send JSON requests to Bunny.net
     */
    private function sendJsonToBunny($endpoint, $method, $data = [], $useLibraryBase = false) {
        $base_url = $useLibraryBase ? $this->library_base_url : $this->video_base_url;
        $url = $base_url . ltrim($endpoint, '/');

        // Validate HTTP method
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            return new \WP_Error('invalid_http_method', __('Invalid HTTP method provided.', 'tutor-lms-bunnynet-integration'));
        }

        // Prepare headers
        $headers = [
            'AccessKey' => $this->access_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Build request arguments
        $args = [
            'method' => $method,
            'headers' => $headers,
        ];

        // Add body if not a GET request
        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        // Debug logging
        error_log('Bunny API Request: ' . print_r(compact('url', 'args'), true));

        // Send request
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response; // Return WP_Error for error handling
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code < 200 || $response_code >= 300) {
            return new \WP_Error(
                'bunny_api_http_error',
                sprintf(__('HTTP Error %d: %s (Endpoint: %s)', 'tutor-lms-bunnynet-integration'), $response_code, $response_body, $endpoint)
            );
        }

        return json_decode($response_body, true);
    }

    /**
     * Create a new video library.
     */
    public function createLibrary($libraryName) {
        if (empty($libraryName)) {
            return new \WP_Error('missing_library_name', __('Library name is required to create a new library.', 'tutor-lms-bunnynet-integration'));
        }
    
        $endpoint = 'videolibrary'; // Library management endpoint
        $data = [
            'name' => $libraryName,
            'readOnly' => false,
            'replicationRegions' => [], // Optional: Update this based on desired regions
        ];
    
        $response = $this->sendJsonToBunny($endpoint, 'POST', $data, true); // Use library_base_url
    
        if (is_wp_error($response)) {
            return $response;
        }
    
        if (isset($response['guid'])) {
            return $response['guid'];
        }
    
        return new \WP_Error('library_creation_failed', __('Library creation failed. Response did not include a library ID.', 'tutor-lms-bunnynet-integration'));
    }    

    /**
     * Create a new video object.
     */
    public function createVideoObject($title) {
        if (empty($this->library_id)) {
            return new \WP_Error('missing_library_id', __('Library ID is not set in the plugin settings.', 'tutor-lms-bunnynet-integration'));
        }

        if (empty($title)) {
            return new \WP_Error('missing_video_title', __('Video title is required.', 'tutor-lms-bunnynet-integration'));
        }

        $endpoint = "library/{$this->library_id}/videos"; // Video management endpoint
        $data = ['title' => $title];

        return $this->sendJsonToBunny($endpoint, 'POST', $data); // Use video_base_url
    }            

    /**
     * Upload a video file to Bunny.net.
     *
     * @param string $libraryId The ID of the library where the video will be uploaded.
     * @param string $filePath The file path of the video to be uploaded.
     * @param string $title The title of the video.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function uploadVideo($filePath, $title) {
        if (empty($this->library_id)) {
            return new \WP_Error('missing_library_id', __('Library ID is not set in the plugin settings.', 'tutor-lms-bunnynet-integration'));
        }
    
        if (empty($filePath) || !file_exists($filePath)) {
            return new \WP_Error('missing_or_invalid_file', __('The file path is missing or invalid.', 'tutor-lms-bunnynet-integration'));
        }
    
        if (empty($title)) {
            return new \WP_Error('missing_video_title', __('Video title is required.', 'tutor-lms-bunnynet-integration'));
        }
    
        $file = fopen($filePath, 'r');
        if (!$file) {
            return new \WP_Error('file_error', __('Unable to open file for reading.', 'tutor-lms-bunnynet-integration'));
        }
    
        $endpoint = "library/{$this->library_id}/videos";
        $headers = [
            'AccessKey' => $this->access_key,
            'Content-Type' => 'application/octet-stream',
            'Title' => $title,
        ];
    
        error_log("Uploading video: LibraryID={$this->library_id}, FilePath={$filePath}, Title={$title}");
    
        $response = wp_remote_request($this->base_url . $endpoint, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $file,
            'timeout' => 20,
        ]);
    
        fclose($file);
    
        if (is_wp_error($response)) {
            error_log('Bunny API Upload Error: ' . $response->get_error_message());
            return $response;
        }
    
        $response_body = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);
    
        error_log("Bunny API Upload Response Code: {$response_code}");
        error_log("Bunny API Upload Response Body: {$response_body}");
    
        if ($response_code >= 400) {
            return new \WP_Error('bunny_api_upload_error', sprintf(__('HTTP Error %d: %s', 'tutor-lms-bunnynet-integration'), $response_code, $response_body));
        }
    
        return json_decode($response_body, true);
    }            

    /**
     * Retrieve the playback URL of a video.
     *
     * @param string $videoId The ID of the video.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function getVideoPlaybackUrl($videoId) {
        $endpoint = "videos/{$videoId}/playback";
        return $this->sendJsonToBunny($endpoint, 'GET', []);
    }

    /**
     * Check the transcoding status of a video.
     *
     * @param string $videoId The ID of the video.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function getVideoStatus($videoId) {
        $endpoint = "videos/{$videoId}/status";
        return $this->sendJsonToBunny($endpoint, 'GET', []);
    }

    /**
     * Check if a video has been successfully created.
     *
     * @param string $videoId The ID of the video.
     * @return bool|WP_Error True if the video is created, or WP_Error on failure.
     */
    public function isVideoCreated($videoId) {
        $status = $this->getVideoStatus($videoId);

        if (is_wp_error($status)) {
            return $status;
        }

        // Check if the status indicates the video is created
        return isset($status['status']) && $status['status'] === 'Success';
    }
    
}
