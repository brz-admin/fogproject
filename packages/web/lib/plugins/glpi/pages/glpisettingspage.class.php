<?php
/**
 * GLPI Settings Management Page
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
 * GLPI Settings Management Page
 *
 * @category GLPI
 * @package  FOGProject
 * @author   FOG Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GlpiSettingsManagementPage extends FOGPage
{
    /**
     * Node name.
     *
     * @var string
     */
    public $node = 'glpi';
    
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        $this->name = _('GLPI Settings');
        parent::__construct();
        $this->subMenu = array(
            'general' => _('General'),
            'settings' => _('Settings'),
            'test' => _('Test Connection'),
        );
        
        $this->headerData = array(_('Setting'), _('Value'));
    }
    
    /**
     * Form processing.
     *
     * @return void
     */
    public function formPost()
    {
        $manager = new glpiManager();
        
        // Handle form submission
        if ($_POST['update_settings']) {
            $settings = array(
                'FOG_GLPI_ENABLED' => $_POST['FOG_GLPI_ENABLED'] ?? '0',
                'FOG_GLPI_URL' => $_POST['FOG_GLPI_URL'] ?? '',
                'FOG_GLPI_API_TOKEN' => $_POST['FOG_GLPI_API_TOKEN'] ?? '',
                'FOG_GLPI_USERNAME' => $_POST['FOG_GLPI_USERNAME'] ?? '',
                'FOG_GLPI_PASSWORD' => $_POST['FOG_GLPI_PASSWORD'] ?? '',
                'FOG_GLPI_AUTO_SYNC' => $_POST['FOG_GLPI_AUTO_SYNC'] ?? '0',
                'FOG_GLPI_SYNC_INTERVAL' => $_POST['FOG_GLPI_SYNC_INTERVAL'] ?? '24',
                'FOG_GLPI_USE_CREDENTIALS' => $_POST['FOG_GLPI_USE_CREDENTIALS'] ?? '0'
            );
            
            foreach ($settings as $key => $value) {
                self::setSetting($key, $value);
            }
            
            $this->setMessage(_('Settings updated successfully'));
            $this->redirect('?node=glpi');
            return;
        }
        
        // Handle connection test
        if ($_POST['test_connection']) {
            $testResult = $manager->testConnection();
            
            if ($testResult) {
                $this->setMessage(_('GLPI connection test successful'));
            } else {
                $this->setMessage(_('GLPI connection test failed'));
            }
            
            $this->redirect('?node=glpi');
            return;
        }
        
        // Handle sync unmapped hosts
        if ($_POST['sync_unmapped']) {
            $results = $manager->syncAllUnmappedHosts();
            
            $this->data = array(
                'sync_results' => $results
            );
        }
    }
    
    /**
     * Display page.
     *
     * @return void
     */
    public function index()
    {
        $manager = new glpiManager();
        $this->data = array(
            'glpi_enabled' => $manager->getSetting('FOG_GLPI_ENABLED'),
            'glpi_url' => $manager->getSetting('FOG_GLPI_URL'),
            'glpi_api_token' => $manager->getSetting('FOG_GLPI_API_TOKEN'),
            'glpi_username' => $manager->getSetting('FOG_GLPI_USERNAME'),
            'glpi_password' => $manager->getSetting('FOG_GLPI_PASSWORD'),
            'glpi_use_credentials' => $manager->getSetting('FOG_GLPI_USE_CREDENTIALS'),
            'glpi_auto_sync' => $manager->getSetting('FOG_GLPI_AUTO_SYNC'),
            'glpi_sync_interval' => $manager->getSetting('FOG_GLPI_SYNC_INTERVAL'),
        );
    }
    
    /**
     * Create content for the page.
     *
     * @return void
     */
    public function indexContent()
    {
        echo '<div class="col-xs-12">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4>' . _('GLPI Integration Settings') . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        // Settings form
        echo '<form method="post" class="form-horizontal">';
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('Enable GLPI Integration') . '</label>';
        echo '<div class="col-xs-8">';
        $enabled = $this->data['glpi_enabled'] == '1' ? 'checked' : '';
        echo '<input type="checkbox" name="FOG_GLPI_ENABLED" value="1" ' . $enabled . '>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('GLPI Server URL') . '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="text" name="FOG_GLPI_URL" class="form-control" value="' . htmlspecialchars($this->data['glpi_url']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('Authentication Method') . '</label>';
        echo '<div class="col-xs-8">';
        $useCreds = $this->data['glpi_use_credentials'] == '1' ? 'checked' : '';
        echo '<label>';
        echo '<input type="radio" name="FOG_GLPI_USE_CREDENTIALS" value="0" ' . ($useCreds ? '' : 'checked') . '> API Token';
        echo '<input type="radio" name="FOG_GLPI_USE_CREDENTIALS" value="1" ' . $useCreds . '> Username/Password';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        // API Token fields
        echo '<div id="api_token_fields" style="' . ($this->data['glpi_use_credentials'] == '1' ? 'display:none;' : '') . '">';
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('API Token') . '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="password" name="FOG_GLPI_API_TOKEN" class="form-control" value="' . htmlspecialchars($this->data['glpi_api_token']) . '">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Credential fields
        echo '<div id="credential_fields" style="' . ($this->data['glpi_use_credentials'] == '0' ? 'display:none;' : '') . '">';
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('Username') . '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="text" name="FOG_GLPI_USERNAME" class="form-control" value="' . htmlspecialchars($this->data['glpi_username']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('Password') . '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="password" name="FOG_GLPI_PASSWORD" class="form-control" value="' . htmlspecialchars($this->data['glpi_password']) . '">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('Auto Sync') . '</label>';
        echo '<div class="col-xs-8">';
        $autoSync = $this->data['glpi_auto_sync'] == '1' ? 'checked' : '';
        echo '<input type="checkbox" name="FOG_GLPI_AUTO_SYNC" value="1" ' . $autoSync . '>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label class="col-xs-4 control-label">' . _('Sync Interval (hours)') . '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="number" name="FOG_GLPI_SYNC_INTERVAL" class="form-control" value="' . htmlspecialchars($this->data['glpi_sync_interval']) . '" min="1" max="168">';
        echo '</div>';
        echo '</div>';
        
        // Submit buttons
        echo '<div class="form-group">';
        echo '<div class="col-xs-6">';
        echo '<button type="submit" name="update_settings" class="btn btn-info btn-block">' . _('Update Settings') . '</button>';
        echo '</div>';
        echo '<div class="col-xs-6">';
        echo '<button type="submit" name="test_connection" class="btn btn-warning btn-block">' . _('Test Connection') . '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</form>';
        
        // Sync unmapped hosts button
        echo '<hr>';
        echo '<h4>' . _('Sync Unmapped Hosts') . '</h4>';
        echo '<form method="post">';
        echo '<button type="submit" name="sync_unmapped" class="btn btn-success btn-block">' . _('Sync All Unmapped Hosts') . '</button>';
        echo '</form>';
        
        // Display sync results if available
        if (isset($this->data['sync_results'])) {
            echo '<hr>';
            echo '<h4>' . _('Sync Results') . '</h4>';
            echo '<table class="table table-bordered">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . _('Host Name') . '</th>';
            echo '<th>' . _('MAC Address') . '</th>';
            echo '<th>' . _('Result') . '</th>';
            echo '<th>' . _('GLPI Computer ID') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($this->data['sync_results'] as $result) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($result['host']) . '</td>';
                echo '<td>' . htmlspecialchars($result['mac']) . '</td>';
                echo '<td>' . ($result['result']['success'] ? '<span class="label label-success">Success</span>' : '<span class="label label-danger">' . $result['result']['message'] . '</span>') . '</td>';
                echo '<td>';
                if ($result['result']['success'] && isset($result['result']['glpi_id'])) {
                    echo '<a href="javascript:void(0)" onclick="window.open(\'' . self::getSetting('FOG_GLPI_URL') . '/front/computer.form.php?id=' . $result['result']['glpi_id'] . '\', \'_blank\')">' . $result['result']['glpi_id'] . '</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}