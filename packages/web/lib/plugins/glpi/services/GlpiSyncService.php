<?php
/**
 * GLPI Sync Service
 * 
 * Handles automatic synchronization between FOG hosts and GLPI computers
 * 
 * @category Service
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Sync Service
 * 
 * Provides automatic synchronization functionality
 */
class GlpiSyncService {
    
    /**
     * Run automatic synchronization
     * 
     * @param bool $forceSync Ignore sync interval
     * @return array
     */
    public function runAutomaticSync($forceSync = false) {
        if (!GlpiPlugin::isEnabled()) {
            return [
                'success' => false,
                'message' => _('GLPI integration is disabled')
            ];
        }
        
        $autoSyncEnabled = self::getSetting('FOG_GLPI_AUTO_SYNC') == '1';
        
        if (!$autoSyncEnabled && !$forceSync) {
            return [
                'success' => false,
                'message' => _('Automatic synchronization is disabled')
            ];
        }
        
        // Check sync interval
        if (!$forceSync) {
            $lastSyncTime = self::getSetting('FOG_GLPI_LAST_SYNC');
            $syncInterval = intval(self::getSetting('FOG_GLPI_SYNC_INTERVAL'));
            
            if (!empty($lastSyncTime)) {
                $lastSyncTimestamp = strtotime($lastSyncTime);
                $nextSyncTimestamp = $lastSyncTimestamp + ($syncInterval * 3600);
                
                if (time() < $nextSyncTimestamp) {
                    return [
                        'success' => false,
                        'message' => sprintf(
                            _('Next sync scheduled in %s hours'),
                            round(($nextSyncTimestamp - time()) / 3600)
                        )
                    ];
                }
            }
        }
        
        try {
            $mappingService = new GlpiMappingService();
            
            // Get all hosts that have inventory data
            $hosts = $this->getHostsWithInventory();
            
            $successCount = 0;
            $errorCount = 0;
            $notFoundCount = 0;
            
            foreach ($hosts as $host) {
                try {
                    $mapping = $mappingService->syncHostWithGlpi($host);
                    
                    if ($mapping) {
                        $successCount++;
                    } else {
                        $notFoundCount++;
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    self::log(sprintf(
                        'GLPI Sync Error for host %s: %s',
                        $host->get('name'),
                        $e->getMessage()
                    ));
                }
            }
            
            // Update last sync time and status
            self::setSetting('FOG_GLPI_LAST_SYNC', date('Y-m-d H:i:s'));
            self::setSetting('FOG_GLPI_LAST_SYNC_STATUS', sprintf(
                'Success: %d, Not Found: %d, Errors: %d',
                $successCount,
                $notFoundCount,
                $errorCount
            ));
            
            return [
                'success' => true,
                'message' => sprintf(
                    _('Synchronization completed: %d hosts synced, %d not found, %d errors'),
                    $successCount,
                    $notFoundCount,
                    $errorCount
                ),
                'details' => [
                    'success' => $successCount,
                    'not_found' => $notFoundCount,
                    'errors' => $errorCount
                ]
            ];
            
        } catch (Exception $e) {
            self::log('GLPI Sync Service Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => _('Synchronization failed: ') . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get hosts with inventory data
     * 
     * @return array
     */
    protected function getHostsWithInventory() {
        try {
            // Get all hosts that have inventory records
            $hosts = self::getClass('HostManager')->find([
                'description' => array('NOT LIKE', '')
            ], '', '', ['limit' => 100]); // Limit to 100 for performance
            
            $result = [];
            
            foreach ($hosts as $host) {
                if ($host->isValid()) {
                    $inventory = $host->get('inventory');
                    
                    if ($inventory && $inventory->isValid()) {
                        $result[] = $host;
                    }
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            self::log('Failed to get hosts with inventory: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if sync is needed
     * 
     * @return bool
     */
    public function isSyncNeeded() {
        if (!GlpiPlugin::isEnabled()) {
            return false;
        }
        
        $autoSyncEnabled = self::getSetting('FOG_GLPI_AUTO_SYNC') == '1';
        
        if (!$autoSyncEnabled) {
            return false;
        }
        
        $lastSyncTime = self::getSetting('FOG_GLPI_LAST_SYNC');
        $syncInterval = intval(self::getSetting('FOG_GLPI_SYNC_INTERVAL'));
        
        if (empty($lastSyncTime)) {
            return true; // Never synced before
        }
        
        $lastSyncTimestamp = strtotime($lastSyncTime);
        $nextSyncTimestamp = $lastSyncTimestamp + ($syncInterval * 3600);
        
        return time() >= $nextSyncTimestamp;
    }
    
    /**
     * Get sync status
     * 
     * @return array
     */
    public function getSyncStatus() {
        if (!GlpiPlugin::isEnabled()) {
            return [
                'enabled' => false,
                'autoSyncEnabled' => false,
                'lastSync' => null,
                'lastSyncStatus' => null,
                'nextSync' => null,
                'syncNeeded' => false
            ];
        }
        
        $autoSyncEnabled = self::getSetting('FOG_GLPI_AUTO_SYNC') == '1';
        $lastSyncTime = self::getSetting('FOG_GLPI_LAST_SYNC');
        $lastSyncStatus = self::getSetting('FOG_GLPI_LAST_SYNC_STATUS');
        $syncInterval = intval(self::getSetting('FOG_GLPI_SYNC_INTERVAL'));
        
        $nextSync = null;
        $syncNeeded = false;
        
        if (!empty($lastSyncTime)) {
            $lastSyncTimestamp = strtotime($lastSyncTime);
            $nextSyncTimestamp = $lastSyncTimestamp + ($syncInterval * 3600);
            $nextSync = date('Y-m-d H:i:s', $nextSyncTimestamp);
            $syncNeeded = time() >= $nextSyncTimestamp;
        } else {
            $syncNeeded = $autoSyncEnabled;
        }
        
        return [
            'enabled' => true,
            'autoSyncEnabled' => $autoSyncEnabled,
            'lastSync' => $lastSyncTime,
            'lastSyncStatus' => $lastSyncStatus,
            'nextSync' => $nextSync,
            'syncNeeded' => $syncNeeded
        ];
    }
    
    /**
     * Sync specific hosts
     * 
     * @param array $hostIds
     * @param bool $forceCreate
     * @return array
     */
    public function syncSpecificHosts($hostIds, $forceCreate = false) {
        if (!GlpiPlugin::isEnabled()) {
            return [
                'success' => false,
                'message' => _('GLPI integration is disabled')
            ];
        }
        
        try {
            $mappingService = new GlpiMappingService();
            $result = $mappingService->bulkSyncHosts($hostIds, $forceCreate);
            
            return [
                'success' => true,
                'message' => sprintf(
                    _('Synced %d hosts: %d success, %d errors'),
                    count($hostIds),
                    $result['success'],
                    $result['errors']
                ),
                'details' => $result['details']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => _('Sync failed: ') . $e->getMessage()
            ];
        }
    }
}