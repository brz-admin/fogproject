<?php
/**
 * GLPI Host Mapping model
 *
 * PHP version5
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Host Mapping model
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Glpi extends FOGController
{
    /**
     * The glpi table.
     *
     * @var string
     */
    protected $databaseTable = 'fogGlpiHostMappings';
    
    /**
     * The database fields and commonized items.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'gID',
        'hostID' => 'gHostID',
        'glpiComputerID' => 'gGlpiComputerID',
        'serialNumber' => 'gSerialNumber',
        'lastSyncTime' => 'gLastSyncTime',
        'syncStatus' => 'gSyncStatus',
        'glpiData' => 'gGlpiData',
    );
    
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'glpiComputerID',
    );
    
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'host',
    );
    
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Host' => array(
            'id',
            'hostID',
            'host'
        )
    );
    
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct($data = '')
    {
        parent::__construct($data);
    }
    
    /**
     * Return the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return $this->get('host');
    }
    
    /**
     * Get the GLPI URL for this host.
     *
     * @return string
     */
    public function getGlpiUrl()
    {
        $glpiUrl = self::getSetting('FOG_GLPI_URL');
        $computerId = $this->get('glpiComputerID');
        
        if (empty($glpiUrl) || empty($computerId)) {
            return '';
        }
        
        // Remove trailing slash from URL if present
        $glpiUrl = rtrim($glpiUrl, '/');
        
        $url = sprintf('%s/front/computer.form.php?id=%d', $glpiUrl, $computerId);
        return $url;
    }
    
    /**
     * Get sync status display.
     *
     * @return string
     */
    public function getSyncStatusDisplay()
    {
        $status = $this->get('syncStatus');
        if (empty($status)) {
            return '<span class="label label-default">Pending</span>';
        }
        
        if ($status == 'synced') {
            return '<span class="label label-success">Synced</span>';
        } elseif ($status == 'error') {
            return '<span class="label label-danger">Error</span>';
        } elseif ($status == 'syncing') {
            return '<span class="label label-info">Syncing</span>';
        } else {
            return '<span class="label label-default">Pending</span>';
        }
    }
}