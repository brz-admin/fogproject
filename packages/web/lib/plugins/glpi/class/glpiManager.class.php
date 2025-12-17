<?php
/**
 * Simple GLPI Manager for testing
 */
class glpiManager extends FOGBase
{
    public function install()
    {
        error_log('Simple glpiManager install() called');
        return true;
    }
    
    public function uninstall()
    {
        error_log('Simple glpiManager uninstall() called');
        return true;
    }
}