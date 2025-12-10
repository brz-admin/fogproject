<?php
/**
 * GLPI Host Controller
 * 
 * Handles GLPI integration tab on host edit pages
 * 
 * @category Controller
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI Host Controller
 */
class GlpiHostController extends FOGController {
    
    /**
     * Show GLPI integration tab
     * 
     * @param Host $host
     */
    public function showGlpiTab(Host $host) {
        if (!GlpiPlugin::isEnabled()) {
            echo '<div class="alert alert-warning">';
            echo _('GLPI integration is disabled. Please enable it in the plugin settings.');
            echo '</div>';
            return;
        }
        
        $mappingService = new GlpiMappingService();
        $status = $mappingService->getMappingStatus($host->get('id'));
        
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h4 class="panel-title">' . _('GLPI Integration') . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        if ($status['linked']) {
            $this->showLinkedComputer($host, $status);
        } else {
            $this->showSearchForm($host);
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Show linked computer information
     * 
     * @param Host $host
     * @param array $status
     */
    protected function showLinkedComputer(Host $host, $status) {
        echo '<div class="alert alert-success">';
        echo '<i class="fa fa-link"></i> ' . sprintf(
            _('This host is linked to GLPI computer #%d: %s'),
            $status['glpiComputerID'],
            $status['computerName']
        );
        echo '</div>';
        
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<h4>' . _('GLPI Computer Details') . '</h4>';
        echo '<dl class="dl-horizontal">';
        echo '<dt>' . _('Serial Number') . ':</dt><dd>' . ($status['serialNumber'] ?? _('N/A')) . '</dd>';
        echo '<dt>' . _('Last Sync') . ':</dt><dd>' . ($status['lastSyncTime'] ?? _('Never')) . '</dd>';
        echo '<dt>' . _('Status') . ':</dt><dd><span class="label label-' . 
             ($status['syncStatus'] === 'synced' ? 'success' : 'warning') . '">' . 
             ucfirst(str_replace('_', ' ', $status['syncStatus'])) . '</span></dd>';
        echo '</dl>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<h4>' . _('Actions') . '</h4>';
        echo '<div class="btn-group">';
        echo '<button class="btn btn-info" onclick="syncWithGlpi(' . $host->get('id') . ')">';
        echo '<i class="fa fa-refresh"></i> ' . _('Sync Now');
        echo '</button>';
        echo '<button class="btn btn-danger" onclick="unlinkFromGlpi(' . $host->get('id') . ')">';
        echo '<i class="fa fa-unlink"></i> ' . _('Unlink');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Show additional computer details if available
        $mapping = GlpiHostMapping::findByHostID($host->get('id'));
        if ($mapping && $mapping->isValid()) {
            $computerData = $mapping->getGlpiComputerData();
            if ($computerData) {
                $this->showComputerDetails($computerData);
            }
        }
    }
    
    /**
     * Show computer details from GLPI
     * 
     * @param array $computerData
     */
    protected function showComputerDetails($computerData) {
        echo '<div class="panel panel-default mt-20">';
        echo '<div class="panel-heading">';
        echo '<h4 class="panel-title">' . _('Additional GLPI Information') . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="row">';
        
        // Left column
        echo '<div class="col-md-6">';
        echo '<dl class="dl-horizontal">';
        
        if (!empty($computerData['locations_id'])) {
            echo '<dt>' . _('Location') . ':</dt><dd>' . $computerData['locations_id'] . '</dd>';
        }
        
        if (!empty($computerData['users_id'])) {
            echo '<dt>' . _('User') . ':</dt><dd>' . $computerData['users_id'] . '</dd>';
        }
        
        if (!empty($computerData['groups_id'])) {
            echo '<dt>' . _('Group') . ':</dt><dd>' . $computerData['groups_id'] . '</dd>';
        }
        
        echo '</dl>';
        echo '</div>';
        
        // Right column
        echo '<div class="col-md-6">';
        echo '<dl class="dl-horizontal">';
        
        if (!empty($computerData['manufacturers_id'])) {
            echo '<dt>' . _('Manufacturer') . ':</dt><dd>' . $computerData['manufacturers_id'] . '</dd>';
        }
        
        if (!empty($computerData['computermodels_id'])) {
            echo '<dt>' . _('Model') . ':</dt><dd>' . $computerData['computermodels_id'] . '</dd>';
        }
        
        if (!empty($computerData['operatingsystems_id'])) {
            echo '<dt>' . _('OS') . ':</dt><dd>' . $computerData['operatingsystems_id'] . '</dd>';
        }
        
        echo '</dl>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Show search form for GLPI computers
     * 
     * @param Host $host
     */
    protected function showSearchForm(Host $host) {
        echo '<div class="alert alert-info">';
        echo '<i class="fa fa-info-circle"></i> ' . _('This host is not linked to a GLPI computer.');
        echo '</div>';
        
        $serialNumber = (new GlpiMappingService())->getHostSerialNumber($host);
        
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h4 class="panel-title">' . _('Link to GLPI Computer') . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        if ($serialNumber) {
            echo '<div class="form-group">';
            echo '<label>' . _('Host Serial Number') . ':</label>';
            echo '<div class="well well-sm">' . htmlspecialchars($serialNumber) . '</div>';
            echo '</div>';
            
            echo '<button class="btn btn-primary" onclick="searchGlpiComputers(' . $host->get('id') . ', \'' . 
                 addslashes($serialNumber) . '\')">';
            echo '<i class="fa fa-search"></i> ' . _('Search GLPI for this serial number');
            echo '</button>';
        } else {
            echo '<div class="alert alert-warning">';
            echo _('No serial number found for this host. Please ensure the host has inventory data with a serial number.');
            echo '</div>';
        }
        
        echo '<div id="searchResults" class="mt-20"></div>';
        
        echo '</div>';
        echo '</div>';
        
        $this->addSearchJavascript($host->get('id'));
    }
    
    /**
     * Add JavaScript for search functionality
     * 
     * @param int $hostId
     */
    protected function addSearchJavascript($hostId) {
        echo '<script>';
        echo 'function searchGlpiComputers(hostId, serialNumber) {';
        echo '    $("#searchResults").html("<i class=\"fa fa-spinner fa-spin\"></i> ' . _('Searching...') . '");';
        echo '    ';
        echo '    $.ajax({';
        echo '        url: "?node=host&sub=glpiSearch",';
        echo '        method: "POST",';
        echo '        data: {';
        echo '            hostId: hostId,';
        echo '            serialNumber: serialNumber';
        echo '        },';
        echo '        success: function(response) {';
        echo '            $("#searchResults").html(response);';
        echo '        },';
        echo '        error: function(xhr, status, error) {';
        echo '            $("#searchResults").html("<div class=\"alert alert-danger\">' . _('Search failed: ') . '" + error + "</div>");';
        echo '        }';
        echo '    });';
        echo '}';
        echo ' ';
        echo 'function syncWithGlpi(hostId) {';
        echo '    if (!confirm("' . _('Are you sure you want to sync this host with GLPI?') . '")) {';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    $.ajax({';
        echo '        url: "?node=host&sub=glpiSync",';
        echo '        method: "POST",';
        echo '        data: {hostId: hostId},';
        echo '        success: function(response) {';
        echo '            location.reload();';
        echo '        },';
        echo '        error: function(xhr, status, error) {';
        echo '            alert("' . _('Sync failed: ') . '" + error);';
        echo '        }';
        echo '    });';
        echo '}';
        echo ' ';
        echo 'function unlinkFromGlpi(hostId) {';
        echo '    if (!confirm("' . _('Are you sure you want to unlink this host from GLPI?') . '")) {';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    $.ajax({';
        echo '        url: "?node=host&sub=glpiUnlink",';
        echo '        method: "POST",';
        echo '        data: {hostId: hostId},';
        echo '        success: function(response) {';
        echo '            location.reload();';
        echo '        },';
        echo '        error: function(xhr, status, error) {';
        echo '            alert("' . _('Unlink failed: ') . '" + error);';
        echo '        }';
        echo '    });';
        echo '}';
        echo '</script>';
    }
    
    /**
     * Search GLPI computers by serial number
     */
    public function searchComputers() {
        try {
            $hostId = intval($_POST['hostId']);
            $serialNumber = trim($_POST['serialNumber']);
            
            if (empty($serialNumber)) {
                throw new Exception(_('Serial number is required'));
            }
            
            $host = new Host($hostId);
            if (!$host->isValid()) {
                throw new Exception(_('Invalid host'));
            }
            
            $mappingService = new GlpiMappingService();
            $computers = $mappingService->findGlpiComputerForHost($host);
            
            if (empty($computers)) {
                echo '<div class="alert alert-warning">';
                echo sprintf(_('No GLPI computers found with serial number: %s'), htmlspecialchars($serialNumber));
                echo '</div>';
                return;
            }
            
            echo '<div class="panel panel-success">';
            echo '<div class="panel-heading">';
            echo '<h4 class="panel-title">' . _('Search Results') . '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            
            if (count($computers) === 1) {
                $this->showSingleComputerResult($host, $computers[0]);
            } else {
                $this->showMultipleComputerResults($host, $computers);
            }
            
            echo '</div>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">';
            echo _('Search failed: ') . $e->getMessage();
            echo '</div>';
        }
    }
    
    /**
     * Show single computer result
     * 
     * @param Host $host
     * @param array $computer
     */
    protected function showSingleComputerResult(Host $host, $computer) {
        echo '<div class="alert alert-info">';
        echo sprintf(_('Found GLPI computer #%d: %s'), $computer['id'], $computer['name']);
        echo '</div>';
        
        echo '<dl class="dl-horizontal">';
        echo '<dt>' . _('Serial Number') . ':</dt><dd>' . ($computer['serial'] ?? _('N/A')) . '</dd>';
        echo '<dt>' . _('Location') . ':</dt><dd>' . ($computer['locations_id'] ?? _('N/A')) . '</dd>';
        echo '<dt>' . _('User') . ':</dt><dd>' . ($computer['users_id'] ?? _('N/A')) . '</dd>';
        echo '</dl>';
        
        echo '<button class="btn btn-success" onclick="linkToComputer(' . $host->get('id') . ', ' . $computer['id'] . ')">';
        echo '<i class="fa fa-link"></i> ' . _('Link to this computer');
        echo '</button>';
        
        echo '<script>';
        echo 'function linkToComputer(hostId, computerId) {';
        echo '    if (!confirm("' . _('Are you sure you want to link this host to the selected GLPI computer?') . '")) {';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    $.ajax({';
        echo '        url: "?node=host&sub=glpiLink",';
        echo '        method: "POST",';
        echo '        data: {';
        echo '            hostId: hostId,';
        echo '            computerId: computerId';
        echo '        },';
        echo '        success: function(response) {';
        echo '            location.reload();';
        echo '        },';
        echo '        error: function(xhr, status, error) {';
        echo '            alert("' . _('Link failed: ') . '" + error);';
        echo '        }';
        echo '    });';
        echo '}';
        echo '</script>';
    }
    
    /**
     * Show multiple computer results
     * 
     * @param Host $host
     * @param array $computers
     */
    protected function showMultipleComputerResults(Host $host, $computers) {
        echo '<div class="alert alert-info">';
        echo sprintf(_('Found %d GLPI computers with matching serial number'), count($computers));
        echo '</div>';
        
        echo '<table class="table table-striped table-hover">';
        echo '<thead><tr>';
        echo '<th>' . _('ID') . '</th>';
        echo '<th>' . _('Name') . '</th>';
        echo '<th>' . _('Serial') . '</th>';
        echo '<th>' . _('Location') . '</th>';
        echo '<th>' . _('User') . '</th>';
        echo '<th>' . _('Actions') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($computers as $computer) {
            echo '<tr>';
            echo '<td>' . $computer['id'] . '</td>';
            echo '<td>' . ($computer['name'] ?? _('N/A')) . '</td>';
            echo '<td>' . ($computer['serial'] ?? _('N/A')) . '</td>';
            echo '<td>' . ($computer['locations_id'] ?? _('N/A')) . '</td>';
            echo '<td>' . ($computer['users_id'] ?? _('N/A')) . '</td>';
            echo '<td>';
            echo '<button class="btn btn-sm btn-success" onclick="linkToComputer(' . $host->get('id') . ', ' . $computer['id'] . ')">';
            echo '<i class="fa fa-link"></i> ' . _('Link');
            echo '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        echo '<script>';
        echo 'function linkToComputer(hostId, computerId) {';
        echo '    if (!confirm("' . _('Are you sure you want to link this host to the selected GLPI computer?') . '")) {';
        echo '        return;';
        echo '    }';
        echo '    ';
        echo '    $.ajax({';
        echo '        url: "?node=host&sub=glpiLink",';
        echo '        method: "POST",';
        echo '        data: {';
        echo '            hostId: hostId,';
        echo '            computerId: computerId';
        echo '        },';
        echo '        success: function(response) {';
        echo '            location.reload();';
        echo '        },';
        echo '        error: function(xhr, status, error) {';
        echo '            alert("' . _('Link failed: ') . '" + error);';
        echo '        }';
        echo '    });';
        echo '}';
        echo '</script>';
    }
    
    /**
     * Link host to GLPI computer
     */
    public function linkComputer() {
        try {
            $hostId = intval($_POST['hostId']);
            $computerId = intval($_POST['computerId']);
            
            $host = new Host($hostId);
            if (!$host->isValid()) {
                throw new Exception(_('Invalid host'));
            }
            
            $apiClient = GlpiPlugin::getApiClient();
            if (!$apiClient) {
                throw new Exception(_('GLPI API client not available'));
            }
            
            // Get computer data from GLPI
            $computerData = $apiClient->getComputer($computerId);
            
            if (empty($computerData)) {
                throw new Exception(_('GLPI computer not found'));
            }
            
            // Create mapping
            $mapping = GlpiHostMapping::createOrUpdateMapping($host, $computerData);
            
            if ($mapping) {
                echo json_encode(['success' => true, 'message' => _('Host linked to GLPI computer successfully!')]);
            } else {
                throw new Exception(_('Failed to create mapping'));
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Sync host with GLPI
     */
    public function syncHost() {
        try {
            $hostId = intval($_POST['hostId']);
            
            $host = new Host($hostId);
            if (!$host->isValid()) {
                throw new Exception(_('Invalid host'));
            }
            
            $mappingService = new GlpiMappingService();
            $mapping = $mappingService->syncHostWithGlpi($host);
            
            if ($mapping) {
                echo json_encode(['success' => true, 'message' => _('Host synced with GLPI successfully!')]);
            } else {
                echo json_encode(['success' => false, 'message' => _('No GLPI computer found for this host')]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Unlink host from GLPI
     */
    public function unlinkHost() {
        try {
            $hostId = intval($_POST['hostId']);
            
            $mappingService = new GlpiMappingService();
            $result = $mappingService->removeMapping($hostId);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => _('Host unlinked from GLPI successfully!')]);
            } else {
                throw new Exception(_('Failed to remove mapping'));
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }
}