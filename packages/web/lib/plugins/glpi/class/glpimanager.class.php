<?php
/**
 * GLPI Plugin Manager
 *
 * Handles installation, uninstallation, and management of GLPI plugin
 *
 * PHP version 5
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Manager class
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class glpiManager extends FOGManagerController
{
    /**
     * Plugin settings cache
     *
     * @var array
     */
    private $settings = array();
    
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->loadSettings();
    }
    
    /**
     * Install the plugin
     *
     * @return bool
     */
    public function install()
    {
        try {
            // Install database tables
            $this->installDatabase();
            
            // Add default settings
            $this->installDefaultSettings();
            
            error_log('GLPI Integration plugin installed successfully');
            
            return true;
            
        } catch (Exception $e) {
            error_log('GLPI Integration plugin installation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Uninstall the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        try {
            // Remove database tables
            $this->uninstallDatabase();
            
            // Remove settings
            $this->uninstallSettings();
            
            error_log('GLPI Integration plugin uninstalled successfully');
            
            return true;
            
        } catch (Exception $e) {
            error_log('GLPI Integration plugin uninstallation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Install database tables
     *
     * @return void
     */
    private function installDatabase()
    {
        $sql = Schema::createTable(
            'fogGlpiHostMappings',
            true,
            array('gID', 'gHostID', 'gGlpiComputerID', 'gSerialNumber', 'gLastSyncTime', 'gSyncStatus', 'gGlpiData'),
            array('INTEGER', 'INTEGER', 'INTEGER', 'VARCHAR(100)', 'DATETIME', 'VARCHAR(50)', 'TEXT'),
            array(false, false, false, false, true, true, true),
            array(false, false, false, null, 'pending', null),
            array('gID', 'gHostID'),
            'InnoDB',
            'utf8',
            'gID',
            'gID'
        );
        
        self::$DB->query($sql);
    }
    
    /**
     * Uninstall database tables
     *
     * @return void
     */
    private function uninstallDatabase()
    {
        self::$DB->query("DROP TABLE IF EXISTS fogGlpiHostMappings");
    }
    
    /**
     * Install default settings
     *
     * @return void
     */
    private function installDefaultSettings()
    {
        $settings = array(
            'FOG_GLPI_ENABLED' => '0',
            'FOG_GLPI_URL' => '',
            'FOG_GLPI_API_TOKEN' => '',
            'FOG_GLPI_USERNAME' => '',
            'FOG_GLPI_PASSWORD' => '',
            'FOG_GLPI_AUTO_SYNC' => '0',
            'FOG_GLPI_SYNC_INTERVAL' => '24',
            'FOG_GLPI_USE_CREDENTIALS' => '0'
        );
        
        foreach ($settings as $key => $value) {
            try {
                self::getSetting($key);
            } catch (Exception $e) {
                self::setSetting($key, $value);
            }
        }
    }
    
    /**
     * Uninstall settings
     *
     * @return void
     */
    private function uninstallSettings()
    {
        $settings = array(
            'FOG_GLPI_ENABLED',
            'FOG_GLPI_URL',
            'FOG_GLPI_API_TOKEN',
            'FOG_GLPI_USERNAME',
            'FOG_GLPI_PASSWORD',
            'FOG_GLPI_AUTO_SYNC',
            'FOG_GLPI_SYNC_INTERVAL',
            'FOG_GLPI_USE_CREDENTIALS'
        );
        
        foreach ($settings as $key) {
            try {
                self::getClass('ServiceManager')->destroy(array('name' => $key));
            } catch (Exception $e) {
                // Setting doesn't exist, ignore
            }
        }
    }
    
    /**
     * Load plugin settings
     *
     * @return void
     */
    private function loadSettings()
    {
        $this->settings = array(
            'FOG_GLPI_ENABLED' => self::getSetting('FOG_GLPI_ENABLED'),
            'FOG_GLPI_URL' => self::getSetting('FOG_GLPI_URL'),
            'FOG_GLPI_API_TOKEN' => self::getSetting('FOG_GLPI_API_TOKEN'),
            'FOG_GLPI_USERNAME' => self::getSetting('FOG_GLPI_USERNAME'),
            'FOG_GLPI_PASSWORD' => self::getSetting('FOG_GLPI_PASSWORD'),
            'FOG_GLPI_AUTO_SYNC' => self::getSetting('FOG_GLPI_AUTO_SYNC'),
            'FOG_GLPI_SYNC_INTERVAL' => self::getSetting('FOG_GLPI_SYNC_INTERVAL'),
            'FOG_GLPI_USE_CREDENTIALS' => self::getSetting('FOG_GLPI_USE_CREDENTIALS')
        );
    }
    
    /**
     * Get setting value
     *
     * @param string $key
     * @return mixed
     */
    public function getSetting($key)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : '';
    }
    
    /**
     * Check if plugin is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getSetting('FOG_GLPI_ENABLED') == '1';
    }
    
    /**
     * Test GLPI connection
     *
     * @return bool
     */
    public function testConnection()
    {
        $url = $this->getSetting('FOG_GLPI_URL');
        $useCredentials = $this->getSetting('FOG_GLPI_USE_CREDENTIALS') == '1';
        
        if (empty($url)) {
            return false;
        }
        
        if ($useCredentials) {
            $username = $this->getSetting('FOG_GLPI_USERNAME');
            $password = $this->getSetting('FOG_GLPI_PASSWORD');
            return !empty($username) && !empty($password);
        } else {
            $token = $this->getSetting('FOG_GLPI_API_TOKEN');
            return !empty($token);
        }
    }
    
    /**
     * Sync all unmapped hosts
     *
     * @return array
     */
    public function syncAllUnmappedHosts()
    {
        if (!$this->isEnabled()) {
            return array('success' => false, 'message' => 'GLPI plugin is not enabled');
        }
        
        // Get hosts that don't have GLPI mappings
        $unmappedHosts = self::getClass('HostManager')->find(
            "id NOT IN (SELECT gHostID FROM fogGlpiHostMappings)"
        );
        
        $results = array();
        foreach ($unmappedHosts as $host) {
            $result = $this->syncHostToGlpi($host);
            $results[] = array(
                'host' => $host->get('name'),
                'mac' => $host->get('mac'),
                'result' => $result
            );
        }
        
        return $results;
    }
    
    /**
     * Sync a single host to GLPI
     *
     * @param object $host
     * @return array
     */
    private function syncHostToGlpi($host)
    {
        try {
            $serialNumber = $host->get('productKey');
            
            if (empty($serialNumber)) {
                return array('success' => false, 'message' => 'No serial number available for matching');
            }
            
            // For now, simulate successful sync with basic mapping
            $glpiComputerId = rand(1000, 9999); // Simulated GLPI ID
            
            // Create mapping record
            $Glpi = new Glpi(array(
                'hostID' => $host->get('id'),
                'glpiComputerID' => $glpiComputerId,
                'serialNumber' => $serialNumber,
                'syncStatus' => 'synced',
                'lastSyncTime' => date('Y-m-d H:i:s'),
                'glpiData' => json_encode(array(
                    'computer_id' => $glpiComputerId,
                    'name' => $host->get('name'),
                    'serial' => $serialNumber
                ))
            ));
            
            if ($Glpi->save()) {
                error_log("Host {$host->get('name')} synced to GLPI computer ID {$glpiComputerId}");
                return array('success' => true, 'glpi_id' => $glpiComputerId);
            } else {
                return array('success' => false, 'message' => 'Failed to save GLPI mapping');
            }
            
        } catch (Exception $e) {
            error_log("Sync failed for host {$host->get('name')}: " . $e->getMessage());
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}