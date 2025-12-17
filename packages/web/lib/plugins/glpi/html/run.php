<?php
/**
 * GLPI Plugin entry point
 *
 * PHP version 5
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

// Try to find commons/init.php from various possible locations
$possiblePaths = [
    dirname(__FILE__) . '/../../../commons/init.php',
    dirname(__FILE__) . '/../../commons/init.php',
    dirname(__FILE__) . '/../../../../commons/init.php',
    '../../../commons/init.php',
    '../../commons/init.php'
];

$initFile = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $initFile = $path;
        break;
    }
}

if (!$initFile) {
    error_log('GLPI Plugin: Could not find FOG init.php. Checked paths: ' . implode(', ', $possiblePaths));
    die('FOG initialization file not found. Please check plugin installation.');
}

require_once $initFile;

// Load the GLPI plugin manager
$GlpiManager = new GlpiManager();

// Handle different plugin actions based on URL parameters
$node = $_REQUEST['node'] ?? 'glpi';
$sub = $_REQUEST['sub'] ?? 'general';

// Route to appropriate controller/action
switch ($node) {
    case 'glpi':
        switch ($sub) {
            case 'general':
                // Show main GLPI management page
                $GlpiManager->displayManagementPage();
                break;
                
            case 'settings':
                // Handle settings page
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $GlpiManager->saveSettings();
                } else {
                    $GlpiManager->displaySettingsPage();
                }
                break;
                
            case 'test':
                // Test GLPI connection
                $GlpiManager->testConnection();
                break;
                
            default:
                // Default to main page
                $GlpiManager->displayManagementPage();
                break;
        }
        break;
        
    default:
        // Fallback to main management page
        $GlpiManager->displayManagementPage();
        break;
}