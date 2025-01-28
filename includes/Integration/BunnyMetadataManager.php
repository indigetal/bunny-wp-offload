<?php
namespace WPBunnyStream\Integration;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BunnyMetadataManager {

    /**
     * Updates the video metadata (_video) for the specified post.
     *
     * @param int   $postId    The post ID.
     * @param array $videoData The video data to save.
     *
     * @return bool True if metadata updated successfully, false otherwise.
     */
    public function updatePostVideoMetadata($postId, $videoData) {
        if (empty($postId) || empty($videoData)) {
            error_log('BunnyMetadataManager: Invalid parameters for updatePostVideoMetadata.');
            return false;
        }

        // Validate video data structure
        if (!isset($videoData['source']) || !isset($videoData['source_bunnynet'])) {
            error_log('BunnyMetadataManager: Missing required video data fields.');
            return false;
        }

        // Update metadata
        $result = update_post_meta($postId, '_video', $videoData);

        if (!$result) {
            error_log("BunnyMetadataManager: Failed to update metadata for post ID {$postId}.");
            return false;
        }

        return true;
    }

    /**
     * Helper method to retrieve video metadata for a specific post.
     *
     * @param int $postId The post ID.
     *
     * @return array|null The video metadata or null if not found.
     */
    public function getPostVideoMetadata($postId) {
        if (empty($postId)) {
            error_log('BunnyMetadataManager: Invalid post ID for getPostVideoMetadata.');
            return null;
        }

        $videoData = get_post_meta($postId, '_video', true);

        if (!$videoData) {
            error_log("BunnyMetadataManager: No video metadata found for post ID {$postId}.");
            return null;
        }

        return $videoData;
    }

    /**
     * Deletes the video metadata for a specific post.
     *
     * @param int $postId The post ID.
     *
     * @return bool True if metadata deleted successfully, false otherwise.
     */
    public function deletePostVideoMetadata($postId) {
        if (empty($postId)) {
            error_log('BunnyMetadataManager: Invalid post ID for deletePostVideoMetadata.');
            return false;
        }

        $result = delete_post_meta($postId, '_video');

        if (!$result) {
            error_log("BunnyMetadataManager: Failed to delete metadata for post ID {$postId}.");
            return false;
        }

        return true;
    }
}
