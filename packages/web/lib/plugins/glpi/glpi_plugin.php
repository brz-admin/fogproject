<?php
/**
 * GLPI Integration Plugin for FOG
 * 
 * Main plugin class that handles registration, initialization, and core functionality
 * 
 * @category Plugin
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Integration Plugin
 * 
 * Provides integration between FOG hosts and GLPI inventory system
 */
class GlpiPlugin extends FOGBase {
    
    /**
     * Plugin name
     * @var string
     */
    public $name = 'GLPI Integration';
    
    /**
     * Plugin description
     * @var string
     */
    public $description = 'Integrate FOG with GLPI inventory system';
    
    /**
     * Plugin version
     * @var string
     */
    public $version = '1.0.0';
    
    /**
     * Plugin author
     * @var string
     */
    public $author = 'FOG Team';
    
    /**
     * Plugin menu node
     * @var string
     */
    public $node = 'glpi';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->name = 'GLPI Integration';
        $this->description = 'Integrate FOG with GLPI inventory system';
    }
    
    /**
     * Install the plugin
     * 
     * @return bool
     */
    public function install() {
        try {
            // Install database tables
            $this->installDatabase();
            
            // Register hooks
            $this->registerHooks();
            
            // Add default settings
            $this->installDefaultSettings();
            
            // Log installation
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
    public function uninstall() {
        try {
            // Remove database tables
            $this->uninstallDatabase();
            
            // Remove settings
            $this->uninstallSettings();
            
            // Log uninstallation
            error_log('GLPI Integration plugin uninstalled successfully');
            
            return true;
            
        } catch (Exception $e) {
            error_log('GLPI Integration plugin uninstallation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Install database tables
     */
    protected function installDatabase() {
        $migration = new GlpiDatabaseMigration();
        $migration->install();
    }
    
    /**
     * Uninstall database tables
     */
    protected function uninstallDatabase() {
        $migration = new GlpiDatabaseMigration();
        $migration->uninstall();
    }
    
    /**
     * Install default settings
     */
    protected function installDefaultSettings() {
        $settings = [
            'FOG_GLPI_ENABLED' => '0',
            'FOG_GLPI_URL' => '',
            'FOG_GLPI_API_TOKEN' => '',
            'FOG_GLPI_AUTO_SYNC' => '0',
            'FOG_GLPI_SYNC_INTERVAL' => '24',
            'FOG_GLPI_LAST_SYNC' => '',
            'FOG_GLPI_LAST_SYNC_STATUS' => ''
        ];
        
        foreach ($settings as $key => $value) {
            try {
                self::getSetting($key); // Check if exists
            } catch (Exception $e) {
                self::setSetting($key, $value); // Create if doesn't exist
            }
        }
    }
    
    /**
     * Uninstall settings
     */
    protected function uninstallSettings() {
        $settings = [
            'FOG_GLPI_ENABLED',
            'FOG_GLPI_URL',
            'FOG_GLPI_API_TOKEN',
            'FOG_GLPI_AUTO_SYNC',
            'FOG_GLPI_SYNC_INTERVAL',
            'FOG_GLPI_LAST_SYNC',
            'FOG_GLPI_LAST_SYNC_STATUS'
        ];
        
        foreach ($settings as $key) {
            try {
                self::getClass('ServiceManager')->destroy(['name' => $key]);
            } catch (Exception $e) {
                // Setting doesn't exist, ignore
            }
        }
    }
    
    /**
     * Register plugin hooks
     */
    public function registerHooks() {
        // Register with FOG hook system
        global $HookManager;
        $hookManager = $HookManager;
        
        // Add GLPI tab to host edit page
        $hookManager->register('HOST_EDIT', [$this, 'addGlpiTab']);
        
        // Add GLPI column to host list
        $hookManager->register('HOST_LIST', [$this, 'addGlpiColumn']);
        
        // Add settings page
        $hookManager->register('SETTINGS', [$this, 'addSettingsPage']);
        
        // Register menu item
        $hookManager->register('MENU', [$this, 'addMenuItem']);
    }
    
    /**
     * Add GLPI tab to host edit page
     * 
     * @param array $arguments
     */
    public function addGlpiTab($arguments) {
        if (self::getSetting('FOG_GLPI_ENABLED') != '1') {
            return;
        }
        
        $host = $arguments['object'];
        if ($host instanceof Host && $host->isValid()) {
            $arguments['subMenu']['glpi'] = 'GLPI Integration';
        }
    }
    
    /**
     * Add GLPI column to host list
     * 
     * @param array $arguments
     */
    public function addGlpiColumn($arguments) {
        if (self::getSetting('FOG_GLPI_ENABLED') != '1') {
            return;
        }
        
        // Add GLPI status column
        array_push($arguments['headerData'], 'GLPI Status');
        array_push($arguments['templates'], '${glpi_status}');
        array_push($arguments['attributes'], ['width' => 100]);
        
// Note: GLPI status column integration would need proper FOG system integration
        // This is handled by the hook system in the individual hook files
    }
    
    /**
     * Add settings page
     * 
     * @param array $arguments
     */
    public function addSettingsPage($arguments) {
        if (isset($arguments['id']) && $arguments['id'] === 'glpi') {
            $controller = new GlpiSettingsController();
            $controller->index();
            exit;
        }
    }
    
    /**
     * Add menu item
     * 
     * @param array $arguments
     */
    public function addMenuItem($arguments) {
        if (isset($arguments['menu']['plugins'])) {
            $arguments['menu']['plugins']['glpi'] = 'GLPI Integration';
        }
    }
    
    /**
     * Check if plugin is enabled
     * 
     * @return bool
     */
    public static function isEnabled() {
        try {
            return self::getSetting('FOG_GLPI_ENABLED') == '1';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get GLPI API client instance
     * 
     * @return GlpiApiClient|null
     */
    public static function getApiClient() {
        if (!self::isEnabled()) {
            return null;
        }
        
        try {
            $url = self::getSetting('FOG_GLPI_URL');
            $token = self::getSetting('FOG_GLPI_API_TOKEN');
            
            if (empty($url) || empty($token)) {
                return null;
            }
            
            return new GlpiApiClient($url, $token);
            
        } catch (Exception $e) {
            error_log('Failed to create GLPI API client: ' . $e->getMessage());
            return null;
        }
    }
}