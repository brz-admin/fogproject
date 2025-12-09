<?php
/**
 * Database migration script to add client version column to hosts table
 *
 * This script should be run during FOG updates to add the new hostClientVersion column
 * to existing installations.
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
    $columnExists = $schemaManager->columnExists('hosts', 'hostClientVersion');
    
    if (!$columnExists) {
        // Add the new column
        $query = "ALTER TABLE `hosts` ADD COLUMN `hostClientVersion` varchar(20) NOT NULL DEFAULT ''";
        $result = self::$DB->query($query);
        
        if ($result) {
            FOGCore::log('Successfully added hostClientVersion column to hosts table');
            echo "SUCCESS: Added hostClientVersion column to hosts table\n";
        } else {
            throw new Exception('Failed to add hostClientVersion column to hosts table');
        }
    } else {
        FOGCore::log('hostClientVersion column already exists in hosts table');
        echo "INFO: hostClientVersion column already exists in hosts table\n";
    }
    
} catch (Exception $e) {
    FOGCore::log('Database migration error: ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);