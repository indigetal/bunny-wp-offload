<?php
namespace WP_BunnyStream\Integration;

use WP_BunnyStream\Integration\BunnyApi;
use WP_BunnyStream\Integration\BunnyMetadataManager;
use WP_BunnyStream\Integration\BunnyDatabaseManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BunnyMediaLibrary {
    private $bunnyApi;
    private $metadataManager;
    private $databaseManager;

    public function __construct() {
        $this->bunnyApi = BunnyApi::getInstance();
        $this->metadataManager = new BunnyMetadataManager();
        $this->databaseManager = new BunnyDatabaseManager();

        add_filter('wp_handle_upload', [$this, 'interceptUpload'], 10, 2);
        add_action('add_attachment', [$this, 'handleAttachmentMetadata'], 10, 1);
    }

    /**
     * Helper function to log messages in a structured way.
     *
     * @param string $message The log message.
     * @param string $type    The type of message (info, warning, error).
     */
    private function log( $message, $type = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_entry = sprintf( '[BunnyMediaLibrary] [%s] %s', strtoupper( $type ), $message );
            error_log( $log_entry );
        }
    }

    /**
     * Intercepts video uploads and, if applicable, offloads them to Bunny.net.
     *
     * @param array $upload Data array representing the uploaded file.
     * @param mixed $context Additional context.
     * @return array|WP_Error The modified upload array or WP_Error on failure.
     */
    public function interceptUpload( $upload, $context ) {
        // Only process video files.
        if ( ! isset( $upload['type'] ) || strpos( $upload['type'], 'video/' ) !== 0 ) {
            return $upload;
        }

        if ( ! isset( $upload['post_id'] ) ) {
            $this->log( "Missing post_id in upload array.", 'error' );
            return new \WP_Error( 'missing_post_id', __( 'Missing post_id for video upload.', 'wp-bunnystream' ) );
        }

        // Check if the video has already been offloaded.
        $existingVideoId = get_post_meta( $upload['post_id'], '_bunny_video_id', true );
        if ( $existingVideoId ) {
            $this->log( "Skipping offload; video already offloaded (ID: {$existingVideoId}).", 'info' );
            return $upload;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            // If user isn't logged in, simply return the upload unmodified.
            return $upload;
        }

        // Delegate the offloading process to offloadVideo().
        $result = $this->offloadVideo( $upload, $upload['post_id'], $user_id );
        return is_wp_error( $result ) ? $upload : $result;
    }

    /**
     * Offloads a video to Bunny.net with enhanced error handling and logging.
     *
     * @param array $upload  Data array representing the uploaded file.
     * @param int   $post_id The attachment post ID.
     * @param int   $user_id The ID of the user performing the upload.
     * @return array|WP_Error The modified upload array including Bunny.net video details or WP_Error on failure.
     */
    public function offloadVideo( $upload, $post_id, $user_id ) {
        // Validate file existence.
        if ( ! isset( $upload['file'] ) || ! file_exists( $upload['file'] ) ) {
            $this->log( 'Invalid file path provided for video offloading.', 'error' );
            return new \WP_Error( 'invalid_file_path', __( 'The provided file path is invalid.', 'wp-bunnystream' ) );
        }
        $filePath = $upload['file'];

        // Validate MIME type.
        $mimeValidation = $this->bunnyApi->validateMimeType( $filePath );
        if ( is_wp_error( $mimeValidation ) ) {
            $this->log( 'MIME type validation failed: ' . $mimeValidation->get_error_message(), 'error' );
            return $mimeValidation;
        }

        // Determine the user's collection.
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            $this->log( 'Could not retrieve user data for user ID ' . $user_id, 'error' );
            return new \WP_Error( 'invalid_user', __( 'Invalid user specified.', 'wp-bunnystream' ) );
        }
        $collectionName = sanitize_title( $user->user_login );
        $collectionId   = $this->databaseManager->getUserCollectionId( $user_id );
        if ( ! $collectionId ) {
            // Create a new collection if it doesn't exist.
            $collectionId = $this->bunnyApi->createCollection( $collectionName, [], $user_id );
            if ( is_wp_error( $collectionId ) ) {
                $this->log( 'Failed to create collection: ' . $collectionId->get_error_message(), 'error' );
                return $collectionId;
            }
        }

        // Offload the video file using BunnyApi.
        $uploadResponse = $this->bunnyApi->uploadVideo( $filePath, $collectionId, $post_id, $user_id );
        if ( is_wp_error( $uploadResponse ) ) {
            $this->log( 'Video upload failed: ' . $uploadResponse->get_error_message(), 'error' );
            return $uploadResponse;
        }

        // Validate API response.
        if ( ! is_array( $uploadResponse ) || ! isset( $uploadResponse['videoId'] ) || empty( $uploadResponse['videoUrl'] ) ) {
            $this->log( 'Invalid API response received: ' . json_encode( $uploadResponse ), 'error' );
            return new \WP_Error( 'invalid_api_response', __( 'Bunny.net did not return a valid videoId or videoUrl.', 'wp-bunnystream' ) );
        }

        // Optionally, delete the local file.
        if ( file_exists( $filePath ) ) {
            @unlink( $filePath );
        }

        // Update the upload data with Bunny.net details.
        $upload['bunny_video_url'] = $uploadResponse['videoUrl'];
        $upload['video_id']        = $uploadResponse['videoId'];

        // Store metadata for later reference.
        $this->metadataManager->storeVideoMetadata( $post_id, [
            'source'      => 'bunnycdn',
            'videoUrl'    => $uploadResponse['videoUrl'],
            'collectionId'=> $collectionId,
            'videoGuid'   => $uploadResponse['videoId'],
        ] );

        $this->log( 'Video offloaded successfully. Video ID: ' . $uploadResponse['videoId'], 'info' );
        return $upload;
    }

    /**
     * Store Bunny.net metadata when an attachment is added
     */
    public function handleAttachmentMetadata($post_id) {
        $videoMetadata = $this->metadataManager->getVideoMetadata($post_id);
        if (!empty($videoMetadata['videoUrl'])) {
            return; // Already processed
        }

        $bunny_video_id = get_post_meta($post_id, '_bunny_video_id', true);
        if (!$bunny_video_id) {
            return;
        }

        // Fetch video URL if not already stored
        $bunny_video_url = $this->bunnyApi->getVideoPlaybackUrl($bunny_video_id);
        if (is_wp_error($bunny_video_url) || empty($bunny_video_url['playbackUrl'])) {
            error_log("Warning: Playback URL not found for Video ID {$bunny_video_id}");
            return;
        }

        $this->metadataManager->storeVideoMetadata($post_id, [
            'source' => 'bunnycdn',
            'videoUrl' => $bunny_video_url['playbackUrl'],
            'videoGuid' => $bunny_video_id,
        ]);
    }
}

// Initialize the media library integration
new BunnyMediaLibrary();
