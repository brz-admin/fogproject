<?php
/**
 * Gitea Snapin Auto-Update Script
 *
 * This script can be run via cron to automatically check for and apply updates
 * to Gitea-based snapins.
 *
 * Usage: php /path/to/fogproject/utils/gitea_snapin_auto_update.php
 */

// Set up FOG environment
define('BASEPATH', dirname(__FILE__, 2) . '/packages/web/');
require_once BASEPATH . '../commons/base.inc.php';

// Check if auto-refresh is enabled
$autoRefreshEnabled = self::getSetting('FOG_GITEA_AUTO_REFRESH') !== '0';

if (!$autoRefreshEnabled) {
    echo "Auto-refresh is disabled. Exiting.\n";
    exit(0);
}

// Check if it's time to run (based on interval)
$lastCheck = (int)self::getSetting('FOG_GITEA_LAST_CHECK');
$interval = (int)self::getSetting('FOG_GITEA_REFRESH_INTERVAL');

if ($interval <= 0) {
    $interval = 86400; // Default to 24 hours
}

$currentTime = time();
$timeSinceLastCheck = $currentTime - $lastCheck;

if ($timeSinceLastCheck < $interval) {
    echo "Last check was " . floor($timeSinceLastCheck / 3600) . " hours ago. Not time yet.\n";
    exit(0);
}

echo "Starting Gitea snapin auto-update...\n";

try {
    // Include the Gitea snapin management class
    require_once BASEPATH . 'lib/pages/giteasnapinmanagementpage.class.php';
    
    $giteaPage = new GiteaSnapinManagementPage();
    
    // Check for updates
    echo "Checking for updates...\n";
    $checkResult = $giteaPage->checkForUpdates();
    
    if (isset($checkResult['error'])) {
        echo "Error checking for updates: " . $checkResult['error'] . "\n";
        exit(1);
    }
    
    $totalUpdates = $checkResult['total_updates'];
    $totalNew = $checkResult['total_new'];
    
    echo "Found " . $totalUpdates . " updates and " . $totalNew . " new repositories.\n";
    
    if ($totalUpdates === 0 && $totalNew === 0) {
        echo "All snapins are up to date.\n";
        // Update last check time
        self::getClass('FOGSettingsManager')->update(
            array('name' => 'FOG_GITEA_LAST_CHECK'), 
            '', 
            array('value' => $currentTime)
        );
        exit(0);
    }
    
    // Apply updates
    echo "Applying updates...\n";
    $applyResult = $giteaPage->applyAllUpdates();
    
    if (isset($applyResult['error'])) {
        echo "Error applying updates: " . $applyResult['error'] . "\n";
        exit(1);
    }
    
    echo $applyResult['message'] . "\n";
    
    if (!empty($applyResult['errors'])) {
        echo "\nThe following errors occurred:\n";
        foreach ($applyResult['errors'] as $error) {
            echo "- " . $error . "\n";
        }
    }
    
    // Update last check time
    self::getClass('FOGSettingsManager')->update(
        array('name' => 'FOG_GITEA_LAST_CHECK'), 
        '', 
        array('value' => $currentTime)
    );
    
    echo "Auto-update completed successfully.\n";
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
