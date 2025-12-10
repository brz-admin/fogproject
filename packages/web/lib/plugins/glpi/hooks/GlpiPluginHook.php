<?php
/**
 * GLPI Plugin Hook
 * 
 * Handles plugin registration and integration with FOG
 * 
 * @category Hook
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Plugin Hook
 * 
 * Registers the GLPI plugin with FOG and handles plugin lifecycle
 */
class GlpiPluginHook extends FOGBase {
    
    /**
     * Plugin instance
     * @var GlpiPlugin|null
     */
    private static $plugin = null;
    
    /**
     * Get plugin instance
     * 
     * @return GlpiPlugin
     */
    public static function getPlugin() {
        if (self::$plugin === null) {
            self::$plugin = new GlpiPlugin();
        }
        return self::$plugin;
    }
    
    /**
     * Install the plugin
     * 
     * @return bool
     */
    public static function install() {
        try {
            $plugin = self::getPlugin();
            return $plugin->install();
        } catch (Exception $e) {
            self::log('GLPI Plugin installation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Uninstall the plugin
     * 
     * @return bool
     */
    public static function uninstall() {
        try {
            $plugin = self::getPlugin();
            return $plugin->uninstall();
        } catch (Exception $e) {
            self::log('GLPI Plugin uninstallation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if plugin is installed
     * 
     * @return bool
     */
    public static function isInstalled() {
        try {
            // Check if database tables exist
            $db = self::$DB;
            $result = $db->query("SHOW TABLES LIKE 'fogGlpiSettings'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Register plugin with FOG
     */
    public static function register() {
        try {
            $plugin = self::getPlugin();
            $plugin->registerHooks();
            return true;
        } catch (Exception $e) {
            self::log('GLPI Plugin registration failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle plugin initialization
     */
    public static function initialize() {
        // Load plugin classes
        self::autoloadClasses();
        
        // Register with FOG
        self::register();
    }
    
    /**
     * Autoload plugin classes
     */
    protected static function autoloadClasses() {
        $pluginDir = dirname(__FILE__) . '/../';
        
        // Register autoloader
        spl_autoload_register(function($class) use ($pluginDir) {
            $classFile = $pluginDir . str_replace('\\', '/', $class) . '.php';
            
            if (file_exists($classFile)) {
                require_once $classFile;
            }
        });
    }
    
    /**
     * Handle host edit page integration
     * 
     * @param array $arguments
     */
    public static function handleHostEdit($arguments) {
        if (isset($arguments['sub']) && $arguments['sub'] === 'glpi') {
            $host = $arguments['object'];
            if ($host instanceof Host && $host->isValid()) {
                $controller = new GlpiHostController();
                $controller->showGlpiTab($host);
                exit;
            }
        }
        
        // Add GLPI tab to submenu if not already present
        if (GlpiPlugin::isEnabled() && isset($arguments['subMenu'])) {
            $host = $arguments['object'];
            if ($host instanceof Host && $host->isValid()) {
                $arguments['subMenu']['glpi'] = _('GLPI Integration');
            }
        }
    }
    
    /**
     * Handle host list integration
     * 
     * @param array $arguments
     */
    public static function handleHostList($arguments) {
        if (GlpiPlugin::isEnabled()) {
            $plugin = self::getPlugin();
            $plugin->addGlpiColumn($arguments);
        }
    }
    
    /**
     * Handle settings page
     * 
     * @param array $arguments
     */
    public static function handleSettings($arguments) {
        if (isset($arguments['id']) && $arguments['id'] === 'glpi') {
            $controller = new GlpiSettingsController();
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->indexPost();
            } else {
                $controller->index();
            }
            exit;
        }
    }
    
    /**
     * Handle menu integration
     * 
     * @param array $arguments
     */
    public static function handleMenu($arguments) {
        if (isset($arguments['menu']['plugins'])) {
            $arguments['menu']['plugins']['glpi'] = _('GLPI Integration');
        }
    }
    
    /**
     * Handle AJAX requests
     * 
     * @param array $arguments
     */
    public static function handleAjax($arguments) {
        if (isset($_GET['node']) && $_GET['node'] === 'host') {
            $sub = $_GET['sub'] ?? '';
            
            switch ($sub) {
                case 'glpiSearch':
                    $controller = new GlpiHostController();
                    $controller->searchComputers();
                    exit;
                    
                case 'glpiLink':
                    $controller = new GlpiHostController();
                    $controller->linkComputer();
                    exit;
                    
                case 'glpiSync':
                    $controller = new GlpiHostController();
                    $controller->syncHost();
                    exit;
                    
                case 'glpiUnlink':
                    $controller = new GlpiHostController();
                    $controller->unlinkHost();
                    exit;
                    
                case 'testConnection':
                    $controller = new GlpiSettingsController();
                    $controller->testConnection();
                    exit;
            }
        }
    }
}