<?php
/**
 * GLPI Settings Model
 * 
 * Represents GLPI plugin settings stored in the database
 * 
 * @category Model
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Settings Model
 */
class GlpiSettings extends FOGModel {
    
    /**
     * Database table name
     * @var string
     */
    protected $databaseTable = 'fogGlpiSettings';
    
    /**
     * Database field mappings
     * @var array
     */
    protected $databaseFields = [
        'id' => 'id',
        'glpiUrl' => 'glpiUrl',
        'apiToken' => 'apiToken',
        'syncInterval' => 'syncInterval',
        'autoSyncEnabled' => 'autoSyncEnabled',
        'lastSyncTime' => 'lastSyncTime',
        'lastSyncStatus' => 'lastSyncStatus'
    ];
    
    /**
     * Required fields
     * @var array
     */
    protected $databaseFieldsRequired = [
        'glpiUrl',
        'apiToken'
    ];
    
    /**
     * Get settings instance (singleton pattern)
     * 
     * @return GlpiSettings
     */
    public static function getInstance() {
        try {
            $settings = new self();
            if ($settings->load()) {
                return $settings;
            }
        } catch (Exception $e) {
            // Settings don't exist yet
        }
        
        // Create default settings if none exist
        return new self();
    }
    
    /**
     * Save settings
     * 
     * @param array $data
     * @return bool
     */
    public function saveSettings($data) {
        foreach ($data as $key => $value) {
            if (isset($this->databaseFields[$key])) {
                $this->set($key, $value);
            }
        }
        
        return $this->save();
    }
    
    /**
     * Test GLPI connection
     * 
     * @return array
     */
    public function testConnection() {
        try {
            $client = new GlpiApiClient($this->get('glpiUrl'), $this->get('apiToken'));
            
            if ($client->testConnection()) {
                return [
                    'success' => true,
                    'message' => _('Connection to GLPI successful!')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => _('Connection failed: Invalid response from GLPI')
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => _('Connection failed: ') . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get setting value
     * 
     * @param string $key
     * @return mixed
     */
    public function getSetting($key) {
        try {
            $settings = self::getInstance();
            return $settings->get($key);
        } catch (Exception $e) {
            return null;
        }
    }
}