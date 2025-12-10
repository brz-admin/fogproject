<?php
/**
 * GLPI Host Mapping Model
 * 
 * Represents the mapping between FOG hosts and GLPI computers
 * 
 * @category Model
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Host Mapping Model
 */
class GlpiHostMapping extends FOGModel {
    
    /**
     * Database table name
     * @var string
     */
    protected $databaseTable = 'fogGlpiHostMappings';
    
    /**
     * Database field mappings
     * @var array
     */
    protected $databaseFields = [
        'id' => 'id',
        'hostID' => 'hostID',
        'glpiComputerID' => 'glpiComputerID',
        'glpiSerialNumber' => 'glpiSerialNumber',
        'lastSyncTime' => 'lastSyncTime',
        'syncStatus' => 'syncStatus',
        'glpiData' => 'glpiData'
    ];
    
    /**
     * Required fields
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'glpiComputerID',
        'glpiSerialNumber'
    ];
    
    /**
     * Additional fields
     * @var array
     */
    protected $additionalFields = [
        'host',
        'glpiComputer'
    ];
    
    /**
     * Database field relationships
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Host' => [
            'id',
            'hostID',
            'host'
        ]
    ];
    
    /**
     * Find mapping by host ID
     * 
     * @param int $hostID
     * @return GlpiHostMapping|null
     */
    public static function findByHostID($hostID) {
        try {
            return self::getClass('GlpiHostMappingManager')->find([
                'hostID' => $hostID
            ]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Find mapping by GLPI computer ID
     * 
     * @param int $glpiComputerID
     * @return GlpiHostMapping|null
     */
    public static function findByGlpiComputerID($glpiComputerID) {
        try {
            return self::getClass('GlpiHostMappingManager')->find([
                'glpiComputerID' => $glpiComputerID
            ]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Find mapping by serial number
     * 
     * @param string $serialNumber
     * @return GlpiHostMapping|null
     */
    public static function findBySerialNumber($serialNumber) {
        try {
            return self::getClass('GlpiHostMappingManager')->find([
                'glpiSerialNumber' => $serialNumber
            ]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Create or update mapping for a host
     * 
     * @param Host $host
     * @param array $glpiComputerData
     * @return GlpiHostMapping
     */
    public static function createOrUpdateMapping(Host $host, $glpiComputerData) {
        // Find existing mapping
        $existingMapping = self::findByHostID($host->get('id'));
        
        if ($existingMapping && $existingMapping->isValid()) {
            // Update existing mapping
            $mapping = $existingMapping;
        } else {
            // Create new mapping
            $mapping = new self();
        }
        
        // Set mapping data
        $mapping->set('hostID', $host->get('id'))
               ->set('glpiComputerID', $glpiComputerData['id'])
               ->set('glpiSerialNumber', $glpiComputerData['serial'] ?? '')
               ->set('glpiData', json_encode($glpiComputerData))
               ->set('lastSyncTime', date('Y-m-d H:i:s'))
               ->set('syncStatus', 'synced');
        
        if ($mapping->save()) {
            return $mapping;
        }
        
        throw new Exception(_('Failed to save GLPI host mapping'));
    }
    
    /**
     * Remove mapping for a host
     * 
     * @param int $hostID
     * @return bool
     */
    public static function removeMapping($hostID) {
        try {
            $mapping = self::findByHostID($hostID);
            if ($mapping && $mapping->isValid()) {
                return $mapping->destroy();
            }
            return true;
        } catch (Exception $e) {
            self::log('Failed to remove GLPI mapping: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get GLPI computer data
     * 
     * @return array|null
     */
    public function getGlpiComputerData() {
        $data = $this->get('glpiData');
        if (empty($data)) {
            return null;
        }
        
        try {
            return json_decode($data, true);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if host is linked to GLPI
     * 
     * @param int $hostID
     * @return bool
     */
    public static function isHostLinked($hostID) {
        try {
            $mapping = self::findByHostID($hostID);
            return $mapping && $mapping->isValid();
        } catch (Exception $e) {
            return false;
        }
    }
}