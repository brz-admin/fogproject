<?php
/**
 * Database migration script to add FOG_CLIENT_VERSION setting
 *
 * This script adds the FOG_CLIENT_VERSION setting to the globalSettings table
 * for existing installations.
 *
 * @category DatabaseMigration
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

try {
    // Check if the setting already exists
    $settingExists = self::getClass('ServiceManager')->exists('name', 'FOG_CLIENT_VERSION');
    
    if (!$settingExists) {
        // Add the new setting
        $service = self::getClass('Service')
            ->set('name', 'FOG_CLIENT_VERSION')
            ->set('description', 'Expected FOG Client Version')
            ->set('value', '0.13.0')
            ->set('category', 'FOG Client');
        
        if ($service->save()) {
            FOGCore::log('Successfully added FOG_CLIENT_VERSION setting');
            echo "SUCCESS: Added FOG_CLIENT_VERSION setting with default value 0.13.0\n";
        } else {
            throw new Exception('Failed to add FOG_CLIENT_VERSION setting');
        }
    } else {
        FOGCore::log('FOG_CLIENT_VERSION setting already exists');
        echo "INFO: FOG_CLIENT_VERSION setting already exists\n";
    }
    
} catch (Exception $e) {
    FOGCore::log('Database migration error: ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);