<?php
/**
 * GLPI Database Migration
 * 
 * Handles database schema creation and updates for GLPI integration plugin
 * 
 * @category Migration
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Database Migration
 */
class glpiDatabaseMigration extends FOGBase {
    
    /**
     * Install database tables
     * 
     * @return bool
     */
    public function install() {
        try {
            $this->createSettingsTable();
            $this->createHostMappingsTable();
            return true;
        } catch (Exception $e) {
            error_log('GLPI Database Migration failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Uninstall database tables
     * 
     * @return bool
     */
    public function uninstall() {
        try {
            $this->dropHostMappingsTable();
            $this->dropSettingsTable();
            return true;
        } catch (Exception $e) {
            error_log('GLPI Database Migration uninstall failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create settings table
     */
    protected function createSettingsTable() {
        $tableName = 'fogGlpiSettings';
        
        // Check if table exists
        $result = self::$DB->query("SHOW TABLES LIKE '{$tableName}'");
        if ($result && $result->num_rows > 0) {
            return;
        }
        
        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `glpiUrl` VARCHAR(255) NOT NULL,
            `apiToken` VARCHAR(255) NOT NULL,
            `syncInterval` INT DEFAULT 24,
            `autoSyncEnabled` BOOLEAN DEFAULT FALSE,
            `lastSyncTime` DATETIME NULL,
            `lastSyncStatus` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC";
        
        self::$DB->query($sql);
    }
    
    /**
     * Create host mappings table
     */
    protected function createHostMappingsTable() {
        $tableName = 'fogGlpiHostMappings';
        
        // Check if table exists
        $result = self::$DB->query("SHOW TABLES LIKE '{$tableName}'");
        if ($result && $result->num_rows > 0) {
            return;
        }
        
        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `hostID` INT NOT NULL,
            `glpiComputerID` INT NOT NULL,
            `glpiSerialNumber` VARCHAR(100) NOT NULL,
            `lastSyncTime` DATETIME NULL,
            `syncStatus` VARCHAR(50) DEFAULT 'pending',
            `glpiData` TEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_host_mapping` (`hostID`),
            INDEX `idx_glpi_computer_id` (`glpiComputerID`),
            INDEX `idx_serial_number` (`glpiSerialNumber`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC";
        
        self::$DB->query($sql);
    }
    
    /**
     * Drop settings table
     */
    protected function dropSettingsTable() {
        $tableName = 'fogGlpiSettings';
        
        $result = self::$DB->query("SHOW TABLES LIKE '{$tableName}'");
        if ($result && $result->num_rows > 0) {
            self::$DB->query("DROP TABLE `{$tableName}`");
        }
    }
    
    /**
     * Drop host mappings table
     */
    protected function dropHostMappingsTable() {
        $tableName = 'fogGlpiHostMappings';
        
        $result = self::$DB->query("SHOW TABLES LIKE '{$tableName}'");
        if ($result && $result->num_rows > 0) {
            self::$DB->query("DROP TABLE `{$tableName}`");
        }
    }
}