<?php
/**
 * GLPI Database Migration
 * 
 * Handles database schema creation and updates for the GLPI integration plugin
 * 
 * @category Migration
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Database Migration
 */
class GlpiDatabaseMigration {
    
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
            self::log('GLPI Database Migration failed: ' . $e->getMessage());
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
            self::log('GLPI Database Migration uninstall failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create settings table
     */
    protected function createSettingsTable() {
        $tableName = 'fogGlpiSettings';
        
        if ($this->tableExists($tableName)) {
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
        
        if ($this->tableExists($tableName)) {
            return;
        }
        
        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `hostID` INT NOT NULL,
            `glpiComputerID` INT NOT NULL,
            `glpiSerialNumber` VARCHAR(100) NOT NULL,
            `lastSyncTime` DATETIME NULL,
            `syncStatus` VARCHAR(50) DEFAULT 'pending',
            `glpiData` JSON NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_host_mapping` (`hostID`),
            FOREIGN KEY (`hostID`) REFERENCES `hosts`(`hostID`) ON DELETE CASCADE,
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
        
        if ($this->tableExists($tableName)) {
            self::$DB->query("DROP TABLE `{$tableName}`");
        }
    }
    
    /**
     * Drop host mappings table
     */
    protected function dropHostMappingsTable() {
        $tableName = 'fogGlpiHostMappings';
        
        if ($this->tableExists($tableName)) {
            self::$DB->query("DROP TABLE `{$tableName}`");
        }
    }
    
    /**
     * Check if table exists
     * 
     * @param string $tableName
     * @return bool
     */
    protected function tableExists($tableName) {
        try {
            $result = self::$DB->query("SHOW TABLES LIKE '{$tableName}'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Add foreign key constraints if needed
     */
    protected function addForeignKeys() {
        // Check if foreign key exists
        $tableName = 'fogGlpiHostMappings';
        
        if (!$this->foreignKeyExists($tableName, 'hostID')) {
            $sql = "ALTER TABLE `{$tableName}` 
                    ADD CONSTRAINT `fk_{$tableName}_host` 
                    FOREIGN KEY (`hostID`) 
                    REFERENCES `hosts`(`hostID`) 
                    ON DELETE CASCADE";
            
            self::$DB->query($sql);
        }
    }
    
    /**
     * Check if foreign key exists
     * 
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    protected function foreignKeyExists($tableName, $columnName) {
        try {
            $result = self::$DB->query(
                "SELECT CONSTRAINT_NAME 
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_NAME = '{$tableName}' 
                 AND COLUMN_NAME = '{$columnName}'
                 AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}