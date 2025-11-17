<?php

// bunny.net WordPress Plugin
// Copyright (C) 2024-2025 BunnyWay d.o.o.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
declare(strict_types=1);

namespace Bunny\Wordpress\Service;

use Bunny\Wordpress\Api\Client;
use Bunny\Wordpress\Api\Storagezone;
use Bunny\Wordpress\Config\Offloader as OffloaderConfig;
use Bunny\Wordpress\Utils\Offloader as OffloaderUtils;

class OffloaderSetup
{
    // Simplified - Pullzone/CDN setup removed, Storage Zone only
    private Client $api;
    private OffloaderUtils $offloaderUtils;
    private string $pathPrefix;

    public function __construct(Client $api, OffloaderUtils $offloaderUtils, string $pathPrefix)
    {
        $this->api = $api;
        $this->offloaderUtils = $offloaderUtils;
        $this->pathPrefix = $pathPrefix;
    }

    /**
     * @param array<string, mixed> $postData
     * Simplified - no Pullzone/CDN setup, Storage Zone only
     */
    public function perform(array $postData): void
    {
        $postData['storage_replication'] = $postData['storage_replication'] ?? [];
        $postData['sync_existing'] = $postData['sync_existing'] ?? '';
        $this->validatePost($postData);
        
        [$syncToken, $syncTokenHash] = $this->offloaderUtils->generateSyncToken();
        
        // Create Storage Zone for media offloading
        $storageZone = $this->createStorageZone($postData['storage_replication']);
        
        // No Pullzone/edge rule configuration - CDN setup removed
        // Storage Zone will be accessed directly for media delivery
        
        // Save offloader configuration
        update_option('bunnycdn_offloader_enabled', true);
        update_option('bunnycdn_offloader_excluded', []);
        update_option('bunnycdn_offloader_storage_zone', $storageZone->getName());
        update_option('bunnycdn_offloader_storage_zoneid', $storageZone->getId());
        update_option('bunnycdn_offloader_storage_password', $storageZone->getPassword());
        update_option('bunnycdn_offloader_sync_existing', '1' === $postData['sync_existing']);
        update_option('bunnycdn_offloader_sync_path_prefix', $this->pathPrefix);
        update_option('bunnycdn_offloader_sync_token_hash', $syncTokenHash);
        update_option('_bunnycdn_offloader_last_sync', time());
    }

    /**
     * @param array<string, mixed> $postData
     */
    private function validatePost(array $postData): void
    {
        if (!isset($postData['enable_confirmed']) || '1' !== $postData['enable_confirmed']) {
            throw new \Exception('Needs confirmation');
        }
        foreach ($postData['storage_replication'] as $replicationRegion) {
            if (OffloaderConfig::STORAGE_REGION_SSD_MAIN === $replicationRegion) {
                throw new \Exception('Do not repeat the main region in the replication regions.');
            }
            if (empty($replicationRegion) || !isset(OffloaderConfig::STORAGE_REGIONS_SSD[$replicationRegion])) {
                throw new \Exception('Invalid replication region: '.$replicationRegion);
            }
        }
    }

    /**
     * @param string[] $replicationRegions
     */
    private function createStorageZone(array $replicationRegions): Storagezone\Details
    {
        for ($i = 0; $i < 5; ++$i) {
            try {
                $name = 'wp-offloader-'.strtolower(wp_generate_password(16, false));

                return $this->api->createStorageZone($name, OffloaderConfig::STORAGE_REGION_SSD_MAIN, $replicationRegions);
            } catch (\Exception $e) {
                if ('The storage zone name is already taken.' === $e->getMessage()) {
                    continue;
                }
                trigger_error('bunnycdn: offloader: '.$e->getMessage(), \E_USER_WARNING);
                throw $e;
            }
        }
        throw new \Exception('Could not create storage zone.');
    }

}
