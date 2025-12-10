<?php
/**
 * GLPI Settings Controller
 * 
 * Handles settings page and configuration for GLPI integration
 * 
 * @category Controller
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Settings Controller
 */
class GlpiSettingsController extends FOGController {
    
    /**
     * Show settings page
     */
    public function index() {
        $this->title = _('GLPI Integration Settings');
        
        // Check if plugin is enabled
        $enabled = GlpiPlugin::isEnabled();
        
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">' . $this->title . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        // Show enable/disable toggle
        $this->showEnableToggle($enabled);
        
        if ($enabled) {
            $this->showSettingsForm();
        } else {
            echo '<div class="alert alert-info">';
            echo _('GLPI integration is currently disabled. Enable it to configure the connection.');
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Show enable/disable toggle
     * 
     * @param bool $enabled
     */
    protected function showEnableToggle($enabled) {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h4 class="panel-title">' . _('Plugin Status') . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        echo '<form method="post" class="form-horizontal">';
        echo '<div class="form-group">';
        echo '<label class="col-sm-3 control-label">' . _('Enable GLPI Integration') . '</label>';
        echo '<div class="col-sm-6">';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="enable_plugin" ' . ($enabled ? 'checked' : '') . '> ';
        echo _('Enable integration with GLPI');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-sm-3">';
        echo '<button type="submit" name="toggle_plugin" class="btn btn-primary">';
        echo '<i class="fa fa-save"></i> ' . _('Save');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Show settings form
     */
    protected function showSettingsForm() {
        echo '<form method="post" class="form-horizontal" id="glpiSettingsForm">';
        
        // GLPI URL
        echo '<div class="form-group">';
        echo '<label for="glpiUrl" class="col-sm-3 control-label">' . _('GLPI URL') . '</label>';
        echo '<div class="col-sm-6">';
        echo '<input type="text" name="glpiUrl" id="glpiUrl" ';
        echo 'class="form-control" placeholder="https://glpi.example.com" ';
        echo 'value="' . htmlspecialchars(self::getSetting('FOG_GLPI_URL')) . '" required>';
        echo '<p class="help-block">' . _('Base URL of your GLPI instance') . '</p>';
        echo '</div>';
        echo '</div>';
        
        // API Token
        echo '<div class="form-group">';
        echo '<label for="apiToken" class="col-sm-3 control-label">' . _('API Token') . '</label>';
        echo '<div class="col-sm-6">';
        echo '<input type="password" name="apiToken" id="apiToken" ';
        echo 'class="form-control" ';
        echo 'value="' . htmlspecialchars(self::getSetting('FOG_GLPI_API_TOKEN')) . '" required>';
        echo '<p class="help-block">' . _('GLPI API token with sufficient permissions') . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Test Connection
        echo '<div class="form-group">';
        echo '<div class="col-sm-offset-3 col-sm-6">';
        echo '<button type="button" id="testConnection" class="btn btn-info">';
        echo '<i class="fa fa-plug"></i> ' . _('Test Connection');
        echo '</button>';
        echo '<span id="connectionStatus" class="ml-10"></span>';
        echo '</div>';
        echo '</div>';
        
        // Auto Sync Options
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h4 class="panel-title">' . _('Automatic Synchronization') . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        echo '<div class="form-group">';
        echo '<label class="col-sm-3 control-label">' . _('Enable Auto-Sync') . '</label>';
        echo '<div class="col-sm-6">';
        echo '<div class="checkbox">';
        echo '<label>';
        $autoSync = self::getSetting('FOG_GLPI_AUTO_SYNC') == '1';
        echo '<input type="checkbox" name="autoSync" ' . ($autoSync ? 'checked' : '') . '> ';
        echo _('Automatically sync hosts with GLPI');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label for="syncInterval" class="col-sm-3 control-label">' . _('Sync Interval') . '</label>';
        echo '<div class="col-sm-6">';
        echo '<div class="input-group">';
        echo '<input type="number" name="syncInterval" id="syncInterval" ';
        echo 'class="form-control" min="1" max="168" ';
        echo 'value="' . htmlspecialchars(self::getSetting('FOG_GLPI_SYNC_INTERVAL')) . '" required>';
        echo '<span class="input-group-addon">' . _('hours') . '</span>';
        echo '</div>';
        echo '<p class="help-block">' . _('How often to automatically sync (1-168 hours)') . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        // Save button
        echo '<div class="form-group">';
        echo '<div class="col-sm-offset-3 col-sm-6">';
        echo '<button type="submit" name="save_settings" class="btn btn-primary">';
        echo '<i class="fa fa-save"></i> ' . _('Save Settings');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</form>';
        
        $this->addJavascript();
    }
    
    /**
     * Add JavaScript for the settings page
     */
    protected function addJavascript() {
        echo '<script>';
        echo '$(document).ready(function() {';
        echo '$("#testConnection").click(function() {';
        echo '    var url = $("#glpiUrl").val();';
        echo '    var token = $("#apiToken").val();';
        echo '    ';
        echo '    if (!url || !token) {';
        echo '        $("#connectionStatus").html("<span class=\"text-danger\">' . _('Please enter URL and API token') . '</span>");';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    $("#connectionStatus").html("<i class=\"fa fa-spinner fa-spin\"></i> ' . _('Testing...') . '");';
        echo '    ';
        echo '    $.ajax({';
        echo '        url: "?node=glpi&sub=testConnection",';
        echo '        method: "POST",';
        echo '        data: {';
        echo '            glpiUrl: url,';
        echo '            apiToken: token';
        echo '        },';
        echo '        success: function(response) {';
        echo '            if (response.success) {';
        echo '                $("#connectionStatus").html("<span class=\"text-success\">' . _('Connection successful!') . '</span>");';
        echo '            } else {';
        echo '                $("#connectionStatus").html("<span class=\"text-danger\">" + response.message + "</span>");';
        echo '            }';
        echo '        },';
        echo '        error: function(xhr, status, error) {';
        echo '            $("#connectionStatus").html("<span class=\"text-danger\">' . _('Connection failed: ') . '" + error + "</span>");';
        echo '        }';
        echo '    });';
        echo '});';
        echo '});';
        echo '</script>';
    }
    
    /**
     * Handle form submission
     */
    public function indexPost() {
        if (isset($_POST['toggle_plugin'])) {
            $this->handleTogglePlugin();
        } elseif (isset($_POST['save_settings'])) {
            $this->handleSaveSettings();
        }
    }
    
    /**
     * Handle plugin toggle
     */
    protected function handleTogglePlugin() {
        $enabled = isset($_POST['enable_plugin']) && $_POST['enable_plugin'] == 'on';
        
        try {
            self::setSetting('FOG_GLPI_ENABLED', $enabled ? '1' : '0');
            
            $message = $enabled ? _('GLPI integration enabled successfully!') : _('GLPI integration disabled successfully!');
            self::setMessage($message);
            
        } catch (Exception $e) {
            self::setMessage(_('Failed to update plugin status: ') . $e->getMessage(), 'error');
        }
        
        self::redirect('?node=glpi');
    }
    
    /**
     * Handle save settings
     */
    protected function handleSaveSettings() {
        try {
            $settings = [
                'FOG_GLPI_URL' => trim($_POST['glpiUrl']),
                'FOG_GLPI_API_TOKEN' => trim($_POST['apiToken']),
                'FOG_GLPI_AUTO_SYNC' => isset($_POST['autoSync']) ? '1' : '0',
                'FOG_GLPI_SYNC_INTERVAL' => intval($_POST['syncInterval'])
            ];
            
            // Validate settings
            if (empty($settings['FOG_GLPI_URL'])) {
                throw new Exception(_('GLPI URL is required'));
            }
            
            if (empty($settings['FOG_GLPI_API_TOKEN'])) {
                throw new Exception(_('API Token is required'));
            }
            
            if ($settings['FOG_GLPI_SYNC_INTERVAL'] < 1 || $settings['FOG_GLPI_SYNC_INTERVAL'] > 168) {
                throw new Exception(_('Sync interval must be between 1 and 168 hours'));
            }
            
            // Save settings
            foreach ($settings as $key => $value) {
                self::setSetting($key, $value);
            }
            
            self::setMessage(_('GLPI settings saved successfully!'));
            
        } catch (Exception $e) {
            self::setMessage(_('Failed to save settings: ') . $e->getMessage(), 'error');
        }
        
        self::redirect('?node=glpi');
    }
    
    /**
     * Test connection endpoint
     */
    public function testConnection() {
        try {
            $url = $_POST['glpiUrl'];
            $token = $_POST['apiToken'];
            
            if (empty($url) || empty($token)) {
                throw new Exception(_('URL and API token are required'));
            }
            
            $client = new GlpiApiClient($url, $token);
            
            if ($client->testConnection()) {
                echo json_encode(['success' => true, 'message' => _('Connection successful!')]);
            } else {
                echo json_encode(['success' => false, 'message' => _('Connection failed')]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }
}