<?php
/**
 * GLPI Plugin Manager
 *
 * Handles installation, uninstallation, and management of the GLPI plugin
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
class GlpiManager extends FOGBase
{
    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings = array();
    
    /**
     * Constructor
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
            
            // Log installation
            $this->log('GLPI Integration plugin installed successfully');
            
            return true;
            
        } catch (Exception $e) {
            $this->log('GLPI Integration plugin installation failed: ' . $e->getMessage());
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
            
            // Log uninstallation
            $this->log('GLPI Integration plugin uninstalled successfully');
            
            return true;
            
        } catch (Exception $e) {
            $this->log('GLPI Integration plugin uninstallation failed: ' . $e->getMessage());
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
        // Load migration class
        $migrationPath = dirname(__FILE__) . '/../migrations/GlpiDatabaseMigration.php';
        if (file_exists($migrationPath)) {
            require_once $migrationPath;
            $migration = new GlpiDatabaseMigration();
            $migration->install();
        }
    }
    
    /**
     * Uninstall database tables
     *
     * @return void
     */
    private function uninstallDatabase()
    {
        // Load migration class
        $migrationPath = dirname(__FILE__) . '/../migrations/GlpiDatabaseMigration.php';
        if (file_exists($migrationPath)) {
            require_once $migrationPath;
            $migration = new GlpiDatabaseMigration();
            $migration->uninstall();
        }
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
            'FOG_GLPI_AUTO_SYNC' => '0',
            'FOG_GLPI_SYNC_INTERVAL' => '24',
            'FOG_GLPI_LAST_SYNC' => '',
            'FOG_GLPI_LAST_SYNC_STATUS' => ''
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
            'FOG_GLPI_AUTO_SYNC',
            'FOG_GLPI_SYNC_INTERVAL',
            'FOG_GLPI_LAST_SYNC',
            'FOG_GLPI_LAST_SYNC_STATUS'
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
            'FOG_GLPI_AUTO_SYNC' => self::getSetting('FOG_GLPI_AUTO_SYNC'),
            'FOG_GLPI_SYNC_INTERVAL' => self::getSetting('FOG_GLPI_SYNC_INTERVAL'),
            'FOG_GLPI_LAST_SYNC' => self::getSetting('FOG_GLPI_LAST_SYNC'),
            'FOG_GLPI_LAST_SYNC_STATUS' => self::getSetting('FOG_GLPI_LAST_SYNC_STATUS')
        );
    }
    
    /**
     * Display management page
     *
     * @return void
     */
    public function displayManagementPage()
    {
        echo '<div class="col-xs-12">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">GLPI Integration</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<p>GLPI Integration allows you to synchronize FOG hosts with GLPI inventory system.</p>';
        echo '<div class="row">';
        echo '<div class="col-xs-6">';
        echo '<a href="?node=glpi&sub=settings" class="btn btn-info btn-block">Settings</a>';
        echo '</div>';
        echo '<div class="col-xs-6">';
        echo '<a href="?node=glpi&sub=test" class="btn btn-warning btn-block">Test Connection</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display settings page
     *
     * @return void
     */
    public function displaySettingsPage()
    {
        echo '<div class="col-xs-12">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">GLPI Settings</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form method="post" class="form-horizontal">';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">Enable GLPI</label>';
        echo '<div class="col-xs-8">';
        $enabled = $this->settings['FOG_GLPI_ENABLED'] == '1' ? 'checked' : '';
        echo '<input type="checkbox" name="FOG_GLPI_ENABLED" value="1" ' . $enabled . '>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">GLPI URL</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="text" name="FOG_GLPI_URL" class="form-control" value="' . htmlspecialchars($this->settings['FOG_GLPI_URL']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">API Token</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="password" name="FOG_GLPI_API_TOKEN" class="form-control" value="' . htmlspecialchars($this->settings['FOG_GLPI_API_TOKEN']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">Auto Sync</label>';
        echo '<div class="col-xs-8">';
        $autoSync = $this->settings['FOG_GLPI_AUTO_SYNC'] == '1' ? 'checked' : '';
        echo '<input type="checkbox" name="FOG_GLPI_AUTO_SYNC" value="1" ' . $autoSync . '>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">Sync Interval (hours)</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="number" name="FOG_GLPI_SYNC_INTERVAL" class="form-control" value="' . htmlspecialchars($this->settings['FOG_GLPI_SYNC_INTERVAL']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<div class="col-xs-12">';
        echo '<button type="submit" class="btn btn-info btn-block">Save Settings</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Save settings
     *
     * @return void
     */
    public function saveSettings()
    {
        $settings = array(
            'FOG_GLPI_ENABLED',
            'FOG_GLPI_URL',
            'FOG_GLPI_API_TOKEN',
            'FOG_GLPI_AUTO_SYNC',
            'FOG_GLPI_SYNC_INTERVAL'
        );
        
        foreach ($settings as $key) {
            $value = $_POST[$key] ?? '';
            if ($key === 'FOG_GLPI_ENABLED' || $key === 'FOG_GLPI_AUTO_SYNC') {
                $value = $value ? '1' : '0';
            }
            self::setSetting($key, $value);
        }
        
        $this->loadSettings();
        $this->displayManagementPage();
    }
    
    /**
     * Test GLPI connection
     *
     * @return void
     */
    public function testConnection()
    {
        echo '<div class="col-xs-12">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">GLPI Connection Test</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        if ($this->settings['FOG_GLPI_ENABLED'] != '1') {
            echo '<div class="alert alert-warning">GLPI Integration is not enabled.</div>';
        } elseif (empty($this->settings['FOG_GLPI_URL']) || empty($this->settings['FOG_GLPI_API_TOKEN'])) {
            echo '<div class="alert alert-warning">GLPI URL and API Token are required.</div>';
        } else {
            // Load API client
            $apiPath = dirname(__FILE__) . '/../api/GlpiApiClient.php';
            if (file_exists($apiPath)) {
                require_once $apiPath;
                $client = new GlpiApiClient($this->settings['FOG_GLPI_URL'], $this->settings['FOG_GLPI_API_TOKEN']);
                $result = $client->testConnection();
                
                if ($result) {
                    echo '<div class="alert alert-success">Connection to GLPI successful!</div>';
                } else {
                    echo '<div class="alert alert-danger">Connection to GLPI failed. Please check your settings.</div>';
                }
            } else {
                echo '<div class="alert alert-danger">GLPI API client not found.</div>';
            }
        }
        
        echo '<a href="?node=glpi" class="btn btn-info">Back</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}