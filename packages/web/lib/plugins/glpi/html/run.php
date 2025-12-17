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

require_once dirname(__FILE__) . '/../../commons/init.php';

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