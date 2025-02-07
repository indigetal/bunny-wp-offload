<?php

namespace WP_BunnyStream\API;

use WP_BunnyStream\API\BunnyApiClient;
use WP_BunnyStream\Utils\BunnyLogger;

class BunnyCollectionHandler {
    private static $instance = null;
    private $apiClient;

    private function __construct() {
        $this->apiClient = BunnyApiClient::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

        /**
     * Create a new collection within a library.
     *
     * @param string $collectionName The name of the collection.
     * @param array $additionalData (Optional) Additional data for the collection, like a description.
     * @param int|null $userId (Optional) The user ID for associating the collection in the database.
     * @return array|WP_Error The created collection data or WP_Error on failure.
     */
    public function createCollection($userId, $additionalData = []) {
        $library_id = $this->apiClient->getLibraryId();
        if (empty($library_id)) {
            return new \WP_Error('missing_library_id', __('Library ID is required to create a collection.', 'wp-bunnystream'));
        }
    
        if (empty($userId)) {
            return new \WP_Error('missing_user_id', __('User ID is required to create a collection.', 'wp-bunnystream'));
        }
    
        // Ensure the collection name follows our naming convention
        $collectionName = "wpbs_{$userId}";
    
        // Step 1: Prevent duplicate collection creation using a transient lock
        $lock_key = "wpbs_collection_lock_{$userId}";
        if (get_transient($lock_key)) {
            return new \WP_Error('collection_creation_locked', __('Collection creation is already in progress. Try again later.', 'wp-bunnystream'));
        }
    
        // Set transient lock to prevent simultaneous requests
        set_transient($lock_key, true, 10); // Lock expires after 10 seconds
    
        // Step 2: Check if the collection already exists on Bunny.net
        $collections = $this->listCollections();
        if (!is_wp_error($collections)) {
            foreach ($collections as $collection) {
                if ($collection['name'] === $collectionName) {
                    delete_transient($lock_key); // Remove lock since no new collection is needed
                    return $collection['guid']; // Return existing collection ID
                }
            }
        }
    
        // Step 3: Create the collection on Bunny.net with the correct JSON format
        $endpoint = "library/{$library_id}/collections";
        $data = array_merge(['name' => $collectionName], $additionalData);
        
        $response = $this->apiClient->sendJsonToBunny($endpoint, 'POST', $data);
    
        // Remove the transient lock after request completes
        delete_transient($lock_key);
    
        if (is_wp_error($response) || empty($response['guid'])) {
            return new \WP_Error('collection_creation_failed', __('Failed to create collection on Bunny.net.', 'wp-bunnystream'));
        }
        return $response['guid'];        
    }                                                                             

    /**
     * Check if a specific collection exists in the list of collections.
     *
     * @param string $collectionId The ID of the collection to check.
     * @return array|WP_Error The collection details or WP_Error if it doesn't exist.
     */
    public function getCollection($collectionId) {
        $collections = $this->listCollections();
        
        if (is_wp_error($collections)) {
            return $collections; // Return error if listing collections fails
        }
    
        foreach ($collections as $collection) {
            if ($collection['guid'] === $collectionId) {
                return $collection;
            }
        }
    
        return null; // Instead of returning a WP_Error, return null if not found
    }              
    
    /**
     * Delete a collection by its ID.
     * 
     * @param string $collectionId The ID of the collection to delete.
     * @return bool|WP_Error True on success, or WP_Error on failure.
     */
    public function deleteCollection($collectionId, $userId = null) {
        $library_id = $this->apiClient->getLibraryId();
        if (empty($library_id)) {
            BunnyLogger::log('Library ID is missing or not set.', 'warning');
            return new \WP_Error('missing_library_id', __('Library ID is required to delete a collection.', 'wp-bunnystream'));
        }
    
        if (empty($collectionId)) {
            return new \WP_Error('missing_collection_id', __('Collection ID is required.', 'wp-bunnystream'));
        }
    
        $endpoint = "library/{$library_id}/collections/{$collectionId}";
        $response = $this->apiClient->sendJsonToBunny($endpoint, 'DELETE');
    
        if (is_wp_error($response)) {
            return $response;
        }
    
        if ($userId) {
            delete_user_meta($userId, '_bunny_collection_id');
        }        
    
        return true;
    } 

    /**
     * Retrieve a list of all collections for a given video library.
     *
     * @return array|WP_Error The collection list or WP_Error on failure.
     */
    public function listCollections() {
        $library_id = $this->apiClient->getLibraryId();
        if (empty($library_id)) {
            return new \WP_Error('missing_library_id', __('Library ID is required to fetch collections.', 'wp-bunnystream'));
        }

        $endpoint = "library/{$library_id}/collections?page=1&itemsPerPage=100";
        $response = $this->apiClient->sendJsonToBunny($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        if (!isset($response['items']) || !is_array($response['items'])) {
            return new \WP_Error('invalid_collection_list', __('Invalid response from Bunny.net when listing collections.', 'wp-bunnystream'));
        }

        return $response['items'];
    }

    /**
     * Update the details of an existing collection.
     * 
     * @param string $collectionId The ID of the collection to update.
     * @param array $data The updated data for the collection (e.g., name, metadata).
     * @return array|WP_Error The updated collection details or WP_Error on failure.
     */
    public function updateCollection($collectionId, $data) {
        $library_id = $this->apiClient->getLibraryId();
        if (empty($library_id)) {
            BunnyLogger::log('Library ID is missing or not set.', 'warning');
            return new \WP_Error('missing_library_id', __('Library ID is required to update a collection.', 'wp-bunnystream'));
        }
    
        if (empty($collectionId)) {
            return new \WP_Error('missing_collection_id', __('Collection ID is required.', 'wp-bunnystream'));
        }
    
        if (empty($data) || !is_array($data)) {
            return new \WP_Error('missing_update_data', __('Update data is required and must be an array.', 'wp-bunnystream'));
        }
    
        $endpoint = "library/{$library_id}/collections/{$collectionId}";
        
        // Remove empty or unchanged values before sending the update
        $filteredData = array_filter($data, function($value) {
            return !is_null($value) && $value !== '';
        });
    
        if (empty($filteredData)) {
            return new \WP_Error('no_update_data', __('No changes detected for the collection update.', 'wp-bunnystream'));
        }
    
        return $this->apiClient->sendJsonToBunny($endpoint, 'PUT', $filteredData);
    }
  
}
