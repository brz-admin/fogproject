<?php
/**
 * Database migration script to add test group column to groups table
 *
 * This script adds the groupIsTestGroup column to the groups table
 * for existing installations to support test group functionality.
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
    // Check if the column already exists
    $schemaManager = FOGCore::getClass('SchemaManager');
    $columnExists = $schemaManager->columnExists('groups', 'groupIsTestGroup');
    
    if (!$columnExists) {
        // Add the new column
        $query = "ALTER TABLE `groups` ADD COLUMN `groupIsTestGroup` TINYINT(1) NOT NULL DEFAULT 0";
        $result = self::$DB->query($query);
        
        if ($result) {
            FOGCore::log('Successfully added groupIsTestGroup column to groups table');
            echo "SUCCESS: Added groupIsTestGroup column to groups table\n";
        } else {
            throw new Exception('Failed to add groupIsTestGroup column to groups table');
        }
    } else {
        FOGCore::log('groupIsTestGroup column already exists in groups table');
        echo "INFO: groupIsTestGroup column already exists in groups table\n";
    }
    
} catch (Exception $e) {
    FOGCore::log('Database migration error: ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);