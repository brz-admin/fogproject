<?php
/**
 * GLPI Mapping Service
 * 
 * Handles the mapping between FOG hosts and GLPI computers
 * 
 * @category Service
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Mapping Service
 * 
 * Provides methods to find, create, and manage mappings between FOG hosts and GLPI computers
 */
class GlpiMappingService {
    
    /**
     * Find GLPI computer for a FOG host by serial number
     * 
     * @param Host $host
     * @return array|null GLPI computer data or null if not found
     * @throws Exception
     */
    public function findGlpiComputerForHost(Host $host) {
        $serialNumber = $this->getHostSerialNumber($host);
        
        if (empty($serialNumber)) {
            return null;
        }
        
        $apiClient = GlpiPlugin::getApiClient();
        
        if (!$apiClient) {
            throw new Exception(_('GLPI API client not available. Please check plugin configuration.'));
        }
        
        try {
            $computers = $apiClient->searchComputersBySerial($serialNumber);
            
            // Return first matching computer
            return !empty($computers[0]) ? $computers[0] : null;
            
        } catch (Exception $e) {
            throw new Exception(_('Failed to search GLPI computers: ') . $e->getMessage());
        }
    }
    
    /**
     * Get host serial number from inventory data
     * 
     * @param Host $host
     * @return string|null
     */
    protected function getHostSerialNumber(Host $host) {
        try {
            // Get inventory data for the host
            $inventory = $host->get('inventory');
            
            if ($inventory && $inventory->isValid()) {
                $inventoryData = $inventory->getAllData();
                
                // Try different possible fields for serial number
                $possibleFields = ['serial', 'serialnumber', 'system_serial', 'bios_serial'];
                
                foreach ($possibleFields as $field) {
                    if (!empty($inventoryData[$field])) {
                        return trim($inventoryData[$field]);
                    }
                }
            }
            
            // Fallback: try to get from host description or other fields
            $description = $host->get('description');
            if (preg_match('/serial[\s:]+([A-Z0-9\-]+)/i', $description, $matches)) {
                return trim($matches[1]);
            }
            
        } catch (Exception $e) {
            // Ignore errors and return null
        }
        
        return null;
    }
    
    /**
     * Sync host with GLPI
     * 
     * @param Host $host
     * @param bool $forceCreate Create GLPI computer if not found
     * @return GlpiHostMapping|null
     * @throws Exception
     */
    public function syncHostWithGlpi(Host $host, $forceCreate = false) {
        // Check if already mapped
        $existingMapping = GlpiHostMapping::findByHostID($host->get('id'));
        
        if ($existingMapping && $existingMapping->isValid()) {
            // Update existing mapping
            return $this->updateExistingMapping($host, $existingMapping);
        }
        
        // Find GLPI computer
        $glpiComputer = $this->findGlpiComputerForHost($host);
        
        if ($glpiComputer) {
            // Create new mapping
            return GlpiHostMapping::createOrUpdateMapping($host, $glpiComputer);
        } elseif ($forceCreate) {
            // Create new GLPI computer and mapping
            return $this->createGlpiComputerAndMapping($host);
        }
        
        return null;
    }
    
    /**
     * Update existing mapping
     * 
     * @param Host $host
     * @param GlpiHostMapping $mapping
     * @return GlpiHostMapping
     * @throws Exception
     */
    protected function updateExistingMapping(Host $host, GlpiHostMapping $mapping) {
        $apiClient = GlpiPlugin::getApiClient();
        
        if (!$apiClient) {
            throw new Exception(_('GLPI API client not available'));
        }
        
        try {
            // Get latest computer data from GLPI
            $glpiComputer = $apiClient->getComputer($mapping->get('glpiComputerID'));
            
            if ($glpiComputer) {
                // Update mapping with latest data
                $mapping->set('glpiData', json_encode($glpiComputer));
                $mapping->set('lastSyncTime', date('Y-m-d H:i:s'));
                $mapping->set('syncStatus', 'synced');
                
                if ($mapping->save()) {
                    return $mapping;
                }
            }
            
        } catch (Exception $e) {
            $mapping->set('syncStatus', 'error: ' . $e->getMessage());
            $mapping->save();
            throw $e;
        }
        
        throw new Exception(_('Failed to update GLPI mapping'));
    }
    
    /**
     * Create GLPI computer and mapping
     * 
     * @param Host $host
     * @return GlpiHostMapping
     * @throws Exception
     */
    protected function createGlpiComputerAndMapping(Host $host) {
        $apiClient = GlpiPlugin::getApiClient();
        
        if (!$apiClient) {
            throw new Exception(_('GLPI API client not available'));
        }
        
        // Prepare computer data
        $computerData = $this->prepareComputerDataFromHost($host);
        
        try {
            // Create computer in GLPI
            $newComputer = $apiClient->createComputer($computerData);
            
            if (isset($newComputer['id'])) {
                // Create mapping
                return GlpiHostMapping::createOrUpdateMapping($host, $newComputer);
            }
            
        } catch (Exception $e) {
            throw new Exception(_('Failed to create GLPI computer: ') . $e->getMessage());
        }
        
        throw new Exception(_('Failed to create GLPI computer and mapping'));
    }
    
    /**
     * Prepare computer data from host
     * 
     * @param Host $host
     * @return array
     */
    protected function prepareComputerDataFromHost(Host $host) {
        $inventory = $host->get('inventory');
        $inventoryData = $inventory && $inventory->isValid() ? $inventory->getAllData() : [];
        
        $computerData = [
            'name' => $host->get('name'),
            'serial' => $this->getHostSerialNumber($host) ?? '',
            'comment' => sprintf('Managed by FOG (Host ID: %d)', $host->get('id')),
            'entities_id' => 0, // Root entity
            'is_deleted' => 0,
            'states_id' => 1 // Active state
        ];
        
        // Add additional fields from inventory if available
        if (!empty($inventoryData['manufacturer'])) {
            $computerData['manufacturers_id'] = $this->getManufacturerId($inventoryData['manufacturer']);
        }
        
        if (!empty($inventoryData['model'])) {
            $computerData['computermodels_id'] = $this->getComputerModelId(
                $inventoryData['manufacturer'] ?? '',
                $inventoryData['model']
            );
        }
        
        if (!empty($inventoryData['os_name'])) {
            $computerData['operatingsystems_id'] = $this->getOsId($inventoryData['os_name']);
        }
        
        return $computerData;
    }
    
    /**
     * Get manufacturer ID (simplified - in real implementation would cache these)
     * 
     * @param string $manufacturerName
     * @return int
     */
    protected function getManufacturerId($manufacturerName) {
        // This would be enhanced to actually look up manufacturers in GLPI
        // For now, return 0 (root) or try to find by name
        return 0;
    }
    
    /**
     * Get computer model ID
     * 
     * @param string $manufacturer
     * @param string $model
     * @return int
     */
    protected function getComputerModelId($manufacturer, $model) {
        // This would be enhanced to actually look up models in GLPI
        return 0;
    }
    
    /**
     * Get OS ID
     * 
     * @param string $osName
     * @return int
     */
    protected function getOsId($osName) {
        // This would be enhanced to actually look up OS in GLPI
        return 0;
    }
    
    /**
     * Remove mapping for host
     * 
     * @param int $hostID
     * @return bool
     */
    public function removeMapping($hostID) {
        return GlpiHostMapping::removeMapping($hostID);
    }
    
    /**
     * Get mapping status for host
     * 
     * @param int $hostID
     * @return array
     */
    public function getMappingStatus($hostID) {
        $mapping = GlpiHostMapping::findByHostID($hostID);
        
        if ($mapping && $mapping->isValid()) {
            $computerData = $mapping->getGlpiComputerData();
            
            return [
                'linked' => true,
                'glpiComputerID' => $mapping->get('glpiComputerID'),
                'serialNumber' => $mapping->get('glpiSerialNumber'),
                'computerName' => $computerData['name'] ?? _('Unknown'),
                'lastSyncTime' => $mapping->get('lastSyncTime'),
                'syncStatus' => $mapping->get('syncStatus')
            ];
        }
        
        return [
            'linked' => false,
            'glpiComputerID' => null,
            'serialNumber' => null,
            'computerName' => null,
            'lastSyncTime' => null,
            'syncStatus' => 'not_linked'
        ];
    }
    
    /**
     * Bulk sync hosts with GLPI
     * 
     * @param array $hostIds
     * @param bool $forceCreate
     * @return array
     */
    public function bulkSyncHosts($hostIds, $forceCreate = false) {
        $results = [
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($hostIds as $hostId) {
            try {
                $host = new Host($hostId);
                
                if ($host->isValid()) {
                    $mapping = $this->syncHostWithGlpi($host, $forceCreate);
                    
                    if ($mapping) {
                        $results['success']++;
                        $results['details'][] = [
                            'hostId' => $hostId,
                            'hostName' => $host->get('name'),
                            'status' => 'success',
                            'glpiComputerID' => $mapping->get('glpiComputerID')
                        ];
                    } else {
                        $results['errors']++;
                        $results['details'][] = [
                            'hostId' => $hostId,
                            'hostName' => $host->get('name'),
                            'status' => 'not_found',
                            'message' => _('No matching GLPI computer found')
                        ];
                    }
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'hostId' => $hostId,
                    'hostName' => $host->get('name'),
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}