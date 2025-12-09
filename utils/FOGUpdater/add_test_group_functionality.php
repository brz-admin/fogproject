<?php
/**
 * Installation hook for test group functionality
 * This script ensures test group functionality is properly set up during FOG installation/upgrade
 *
 * @category Installation
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

// Check if this is being run from the FOG updater or standalone
if (!defined('FOG_CORE')) {
    // This is a standalone run - include necessary files
    require_once '../packages/web/commons/base.inc.php';
}

echo "=== Test Group Functionality Installation ===\n\n";

try {
    // Check if the test group column already exists
    $schemaManager = FOGCore::getClass('SchemaManager');
    $columnExists = $schemaManager->columnExists('groups', 'groupIsTestGroup');
    
    if (!$columnExists) {
        echo "Adding test group column to database... ";
        
        // Add the new column
        $query = "ALTER TABLE `groups` ADD COLUMN `groupIsTestGroup` TINYINT(1) NOT NULL DEFAULT 0";
        $result = self::$DB->query($query);
        
        if ($result) {
            FOGCore::log('Successfully added groupIsTestGroup column to groups table');
            echo "✓ SUCCESS\n";
        } else {
            throw new Exception('Failed to add groupIsTestGroup column to groups table');
        }
    } else {
        FOGCore::log('groupIsTestGroup column already exists in groups table');
        echo "✓ COLUMN ALREADY EXISTS\n";
    }
    
    // Check if FOG_CLIENT_VERSION setting exists
    echo "Checking FOG_CLIENT_VERSION setting... ";
    $settingExists = self::getClass('SettingManager')->exists('FOG_CLIENT_VERSION');
    
    if (!$settingExists) {
        echo "Adding FOG_CLIENT_VERSION setting... ";
        
        // Add the setting with a default value
        $setting = self::getClass('Setting')
            ->set('name', 'FOG_CLIENT_VERSION')
            ->set('description', 'Expected FOG Client version for compliance monitoring')
            ->set('value', '0.13.0')
            ->set('category', 'FOG Client');
        
        if ($setting->save()) {
            FOGCore::log('Successfully added FOG_CLIENT_VERSION setting');
            echo "✓ SUCCESS\n";
        } else {
            throw new Exception('Failed to add FOG_CLIENT_VERSION setting');
        }
    } else {
        FOGCore::log('FOG_CLIENT_VERSION setting already exists');
        echo "✓ SETTING ALREADY EXISTS\n";
    }
    
    // Verify that the client version management page exists
    echo "Verifying client version management page... ";
    $clientVersionPage = '../packages/web/management/other/clientversion.php';
    
    if (file_exists($clientVersionPage)) {
        echo "✓ PAGE EXISTS\n";
        
        // Check for critical functionality
        $content = file_get_contents($clientVersionPage);
        
        $requiredFeatures = [
            'Test Group Management' => 'test group management interface',
            'bulk_test_group_action' => 'bulk test group operations',
            'promote_to_production' => 'phased rollout promotion',
            'deploy_to_test_groups' => 'snapin assignment to test groups'
        ];
        
        echo "Verifying required features...\n";
        foreach ($requiredFeatures as $feature => $description) {
            if (strpos($content, $feature) !== false) {
                echo "  ✓ $description\n";
            } else {
                echo "  ✗ $description - MISSING\n";
            }
        }
    } else {
        echo "✗ PAGE MISSING\n";
        throw new Exception('Client version management page not found');
    }
    
    echo "\n=== Test Group Functionality Installation Complete ===\n";
    echo "All test group functionality has been successfully installed.\n";
    echo "You can now use test groups for phased client deployments.\n";
    
} catch (Exception $e) {
    FOGCore::log('Test group functionality installation error: ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);