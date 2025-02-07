<?php

namespace WP_BunnyStream\API;

use WP_BunnyStream\Admin\BunnySettings;
use WP_BunnyStream\API\BunnyApiKeyManager;
use WP_BunnyStream\Utils\BunnyLogger;
use WP_BunnyStream\Utils\Constants;

Constants::MAX_FILE_SIZE;

class BunnyApiClient {
    private static $instance = null;
    public $video_base_url = 'https://video.bunnycdn.com/';
    private $access_key;
    private $library_id;

    private function __construct() {
        $this->access_key = BunnyApiKeyManager::getApiKey();
        $this->library_id = BunnyApiKeyManager::decrypt_api_key(get_option('bunny_net_library_id', ''));
    }    

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self(); // Correct way to call private constructor
        }
        return self::$instance;
    }    

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    public function getAccessKey() {
        return $this->access_key;
    }    

    /**
     * Generic method to send JSON requests to Bunny.net with retry logic.
     */
    public function sendJsonToBunny($endpoint, $method, $data = []) {
        $url = $this->video_base_url . ltrim($endpoint, '/');

        // Validate HTTP method
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            return new \WP_Error('invalid_http_method', __('Invalid HTTP method provided.', 'wp-bunnystream'));
        }

        // Prepare headers
        $headers = [
            'AccessKey' => $this->access_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Content-Length' => strlen(json_encode($data)), // Ensure correct length
        ];        

        // Build request arguments
        $args = [
            'method'  => $method,
            'headers' => $headers,
        ];

        // Add body if not a GET request
        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        // Log API request details before making the request
        BunnyLogger::log("Sending API request to Bunny.net. Endpoint: {$endpoint}, Method: {$method}, Library ID: {$this->library_id}", 'debug');
        BunnyLogger::log("Headers: " . json_encode($headers), 'debug');
        if (!empty($data)) {
            BunnyLogger::log("Request Body: " . json_encode($data), 'debug');
        }

        return $this->retryApiCall(function() use ($url, $args, $endpoint) {
            $response = wp_remote_request($url, $args);
            if (is_wp_error($response)) {
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response) ?: 'No response body';

            if ($response_code < 200 || $response_code >= 300) {
                BunnyLogger::log("Failed Request to $endpoint (HTTP $response_code)", 'error');
                BunnyLogger::log("Response Body: " . $response_body, 'debug');
                return new \WP_Error('bunny_api_http_error', sprintf(
                    __('Bunny.net API Error (HTTP %d): %s', 'wp-bunnystream'),
                    $response_code, 
                    $response_body
                ));
            }

            BunnyLogger::log("sendJsonToBunny Response: " . print_r($response_body, true), 'debug');

            return json_decode($response_body, true);
        });
        
    }    

    /**
     * Retry failed API calls with exponential backoff and collection validation.
     */
    protected function retryApiCall($callback, $maxAttempts = 3) {
        $attempt = 0;
    
        while ($attempt < $maxAttempts) {
            BunnyLogger::log("API Attempt #" . ($attempt + 1), 'info');
    
            // Check if a transient exists for rate-limiting
            $retry_after_time = get_transient('bunny_api_retry_after');
            if ($retry_after_time && time() < $retry_after_time) {
                sleep($retry_after_time - time());
            }
    
            $response = $callback();
            if (!is_wp_error($response)) {
                BunnyLogger::log("API Response (Success): " . json_encode($response), 'debug');
                return $response;
            }
    
            $error_message = $response->get_error_message();
            $response_code = wp_remote_retrieve_response_code($response);
    
            // Handle 429 Too Many Requests
            if ($response_code === 429) {
                $retry_after = wp_remote_retrieve_header($response, 'Retry-After');
                $retry_after_seconds = $retry_after ? (int) $retry_after : (2 ** $attempt);
    
                // Store retry-after in a transient to prevent immediate retries
                set_transient('bunny_api_retry_after', time() + $retry_after_seconds, $retry_after_seconds);
    
                BunnyLogger::log("Rate limit hit (429). Respecting Retry-After: {$retry_after_seconds} seconds.", 'warning');
                sleep($retry_after_seconds);
            } else {
                // Log and retry with exponential backoff for other errors
                BunnyLogger::log("API Call Failed (Error: {$error_message}). Retrying in " . (2 ** $attempt) . " seconds...", 'warning');
                sleep(2 ** $attempt);
            }
    
            $attempt++;
        }
    
        return new \WP_Error('api_failure', __('Bunny.net API failed after multiple attempts.', 'wp-bunnystream'));
    }  
    
    public function executeWithRetry($callback, $maxAttempts = 3) {
        return $this->retryApiCall($callback, $maxAttempts);
    }    
    
    public function getLibraryId() {
        return $this->library_id;
    }                         
    
}
