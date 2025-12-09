<?php
/**
 * HostInfoUpdate - Service endpoint for receiving host IP and hostname updates from clients
 *
 * PHP version 5
 *
 * @category HostInfoUpdate
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * HostInfoUpdate - Service endpoint for receiving host IP and hostname updates from clients
 *
 * @category HostInfoUpdate
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

// Set content type for the response
header('Content-Type: text/plain');

try {
    // Strip and decode incoming request data
    FOGCore::stripAndDecode($_REQUEST);
    
    // Get the host object based on MAC address or other identifiers
    FOGCore::getHostItem(
        false,  // Not a service call
        false,  // Not encoded
        false,  // Host not required (will create error if not found)
        false,  // Don't return only MACs
        false,  // Not an override
        isset($_REQUEST['mac']) ? $_REQUEST['mac'] : ''
    );
    
    // Check if we have a valid host
    if (!FOGCore::$Host instanceof Host || !FOGCore::$Host->isValid()) {
        throw new Exception(_('Invalid host or host not found'));
    }
    
    // Validate that this is not a browser request (should be client-only)
    if (FOGCore::$useragent) {
        throw new Exception(_('Cannot access from browser'));
    }
    
    // Check for required parameters
    if (!isset($_REQUEST['hostname']) || !isset($_REQUEST['ip'])) {
        throw new Exception(_('Missing required parameters: hostname and/or ip'));
    }
    
    // Additional security: Check if the host has the module enabled
    $hostModInfo = self::getSubObjectIDs(
        'Module',
        array(
            'id' => FOGCore::$Host->get('modules'),
            'shortName' => 'hostinfoupdater'
        ),
        'shortName'
    );
    
    if (!in_array('hostinfoupdater', $hostModInfo)) {
        throw new Exception(_('HostInfoUpdater module not enabled for this host'));
    }
    
    // Rate limiting: Check if this host has made too many recent update requests
    // This prevents abuse and accidental flooding
    $recentUpdates = self::getClass('HistoryManager')->find(array(
        'hText' => array('LIKE', '%HostInfoUpdate%'),
        'hIP' => FOGCore::$Host->get('ip')
    ), '', 'hTime DESC', array('limit' => 5));
    
    if (count($recentUpdates) > 3) {
        $lastUpdateTime = strtotime($recentUpdates[0]->get('hTime'));
        if (time() - $lastUpdateTime < 3600) { // 1 hour cooldown
            throw new Exception(_('Too many recent update requests. Please wait before trying again.'));
        }
    }
    
    $clientHostname = trim($_REQUEST['hostname']);
    $clientIP = trim($_REQUEST['ip']);
    
    // Validate hostname format
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $clientHostname)) {
        throw new Exception(_('Invalid hostname format'));
    }
    
    // Validate IP address format
    if (!filter_var($clientIP, FILTER_VALIDATE_IP)) {
        throw new Exception(_('Invalid IP address format'));
    }
    
    // Use the HostManager to update host information
    $hostManager = FOGCore::getClass('HostManager');
    $result = $hostManager->updateHostInfoFromClient(FOGCore::$Host, $clientHostname, $clientIP);
    
    if ($result['success']) {
        if ($result['updated']) {
            // Log the update
            FOGCore::log(sprintf(
                'Host %s: %s',
                FOGCore::$Host->get('name'),
                $result['message']
            ));
        }
        
        echo '#!ok' . PHP_EOL;
        echo $result['message'] . PHP_EOL;
    } else {
        throw new Exception(_('Host update failed'));
    }
    
} catch (Exception $e) {
    // Handle any errors and return appropriate response
    echo '#!error' . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    
    // Log the error
    FOGCore::log(sprintf(
        'HostInfoUpdate Error: %s',
        $e->getMessage()
    ));
    
    exit(1);
}