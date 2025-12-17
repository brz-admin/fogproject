<?php
/**
 * GLPI plugin configuration file.
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
 * GLPI plugin configuration file.
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = array();
$fog_plugin['name'] = 'GLPI';
$fog_plugin['description'] = 'Integrate FOG with GLPI inventory system for automatic host synchronization and management.';
$fog_plugin['menuicon'] = 'fa fa-database fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';