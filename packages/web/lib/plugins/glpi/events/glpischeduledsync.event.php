<?php
/**
 * GLPI Scheduled Sync Event
 *
 * Handles scheduled synchronization of unmapped hosts to GLPI
 *
 * PHP version 5
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Scheduled Sync Event
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GlpiScheduledSyncEvent extends Event
{
    /**
     * Name of the event.
     *
     * @var string
     */
    public $name = 'GlpiScheduledSyncEvent';
    /**
     * Description of the event.
     *
     * @var string
     */
    public $description = 'Handles scheduled synchronization of unmapped hosts to GLPI';
    /**
     * Active or not?
     *
     * @var bool
     */
    public $active = true;
    
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$EventManager->register('TASK_SCHEDULER', $this);
    }
    
    /**
     * Handle the event.
     *
     * @param string $event The event to handle.
     * @param mixed $data The data to work with.
     *
     * @return void
     */
    public function onEvent($event, $data)
    {
        if ($event != 'TASK_SCHEDULER') {
            return;
        }
        
        $manager = new glpiManager();
        
        if (!$manager->isEnabled()) {
            error_log('GLPI Sync: Plugin is not enabled, skipping scheduled sync');
            return;
        }
        
        // Check if it's time to run sync (run every 24 hours)
        $lastSync = self::getSetting('FOG_GLPI_LAST_SYNC');
        $syncInterval = (int)$manager->getSetting('FOG_GLPI_SYNC_INTERVAL');
        
        if (!empty($lastSync)) {
            // First time running, sync immediately
            $this->performSync($manager);
        } else {
            $lastSyncTime = strtotime($lastSync);
            $currentTime = time();
            $nextSyncTime = $lastSyncTime + ($syncInterval * 3600);
            
            if ($currentTime >= $nextSyncTime) {
                $this->performSync($manager);
            }
        }
    }
    
    /**
     * Perform the sync operation.
     *
     * @param object $manager The GLPI manager.
     *
     * @return void
     */
    private function performSync($manager)
    {
        error_log('GLPI Scheduled Sync: Starting sync of unmapped hosts');
        
        $results = $manager->syncAllUnmappedHosts();
        
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($results as $result) {
            if ($result['result']['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }
        
        // Update last sync time and status
        self::setSetting('FOG_GLPI_LAST_SYNC', date('Y-m-d H:i:s'));
        self::setSetting('FOG_GLPI_LAST_SYNC_STATUS', sprintf(
            'Synced %d hosts, %d failed',
            $successCount,
            $failureCount
        ));
        
        error_log(sprintf(
            'GLPI Scheduled Sync completed: %d successful, %d failed',
            $successCount,
            $failureCount
        ));
    }
}