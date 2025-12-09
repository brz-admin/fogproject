<?php
/**
 * HostInfoUpdater - Client module for sending host IP and hostname updates
 *
 * PHP version 5
 *
 * @category HostInfoUpdater
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * HostInfoUpdater - Client module for sending host IP and hostname updates
 *
 * @category HostInfoUpdater
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostInfoUpdater extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'hostinfoupdater';
    
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        // Get current hostname from the host record
        $currentHostname = self::$Host->get('name');
        $currentIP = self::$Host->get('ip');
        
        // In a real client implementation, this would collect the actual
        // current hostname and IP from the client system
        // For now, we'll use the host record data as a placeholder
        $clientHostname = $currentHostname;
        $clientIP = $currentIP;
        
        // Check if there are any differences that need updating
        $needsUpdate = false;
        $updateData = array();
        
        // In a real implementation, the client would send its actual current values
        // and the server would compare them to the stored values
        
        return array(
            'hostname' => $clientHostname,
            'ip' => $clientIP,
            'timestamp' => time(),
            'clientVersion' => '1.0'
        );
    }
    
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        ob_start();
        echo '#!ok';
        
        // Get current host information
        $hostname = self::$Host->get('name');
        $ip = self::$Host->get('ip');
        
        // In a real client, this would be the actual current hostname and IP
        // from the client system. For this implementation, we'll use the
        // host record data as a starting point.
        
        printf(
            "hostname=%s\n",
            $hostname
        );
        printf(
            "ip=%s\n",
            $ip
        );
        printf(
            "timestamp=%s\n",
            time()
        );
        
        $this->sendMe = ob_get_clean();
    }
}