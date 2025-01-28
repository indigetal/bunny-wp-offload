<?php 

namespace WP_BunnyStream\Integration;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BunnyDatabaseManager {

    /**
     * Create the collections table for storing user-collection associations.
     * Supports multisite environments.
     */
    public static function createCollectionsTable($networkWide = false) {
        global $wpdb;

        if ($networkWide && is_multisite()) {
            // Create a single network-wide table
            $table_name = $wpdb->base_prefix . 'bunny_collections';
        } else {
            // Create a site-specific table
            $table_name = $wpdb->prefix . 'bunny_collections';
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            collection_id VARCHAR(255) NOT NULL,
            library_id VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Retrieve the collection ID associated with a user.
     * Supports multisite environments.
     *
     * @param int $userId The user ID.
     * @param bool $networkWide Whether to query the network-wide table.
     * @return string|null The collection ID or null if not found.
     */
    public function getUserCollectionId($userId, $networkWide = false) {
        global $wpdb;

        $table_name = $networkWide && is_multisite()
            ? $wpdb->base_prefix . 'bunny_collections'
            : $wpdb->prefix . 'bunny_collections';

        $collection_id = $wpdb->get_var($wpdb->prepare(
            "SELECT collection_id FROM $table_name WHERE user_id = %d LIMIT 1",
            $userId
        ));

        return $collection_id ?: null;
    }

    /**
     * Store a user-to-collection association.
     * Supports multisite environments.
     *
     * @param int $userId The user ID.
     * @param string $collectionId The Bunny.net collection ID.
     * @param bool $networkWide Whether to use the network-wide table.
     * @return void
     */
    public function storeUserCollection($userId, $collectionId, $networkWide = false) {
        global $wpdb;

        $table_name = $networkWide && is_multisite()
            ? $wpdb->base_prefix . 'bunny_collections'
            : $wpdb->prefix . 'bunny_collections';

        $wpdb->insert(
            $table_name,
            [
                'user_id' => $userId,
                'collection_id' => $collectionId,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );
    }

    /**
     * Delete the collection record associated with a user.
     * Supports multisite environments.
     *
     * @param int $userId The user ID.
     * @param bool $networkWide Whether to use the network-wide table.
     * @return void
     */
    public function deleteUserCollection($userId, $networkWide = false) {
        global $wpdb;

        $table_name = $networkWide && is_multisite()
            ? $wpdb->base_prefix . 'bunny_collections'
            : $wpdb->prefix . 'bunny_collections';

        $wpdb->delete(
            $table_name,
            ['user_id' => $userId],
            ['%d']
        );
    }
}
