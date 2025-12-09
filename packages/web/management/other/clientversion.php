<?php
/**
 * FOG Client Version Management Page
 *
 * This page allows administrators to manage the expected FOG client version
 * and distribute client updates via the snapin system.
 *
 * @category Management
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

// Ensure this is being accessed through the FOG management interface
if (!defined('FOG_CORE')) {
    die('Direct access not allowed');
}

// Get current client version setting
try {
    $currentVersion = self::getSetting('FOG_CLIENT_VERSION');
} catch (Exception $e) {
    $currentVersion = '0.13.0'; // Default version
}

// Handle form submission for updating client version
if (isset($_POST['update_client_version'])) {
    try {
        $newVersion = trim($_POST['client_version']);
        
        // Validate version format
        if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $newVersion)) {
            throw new Exception(_('Invalid version format. Only alphanumeric characters, periods, and hyphens are allowed.'));
        }
        
        // Update the setting
        self::setSetting('FOG_CLIENT_VERSION', $newVersion);
        
        // Log the change
        self::log(sprintf('Updated expected FOG client version from %s to %s', $currentVersion, $newVersion));
        
        $currentVersion = $newVersion;
        $successMessage = _('Client version updated successfully!');
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Handle client upload via snapin system
if (isset($_POST['upload_client'])) {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['client_file']) || $_FILES['client_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(_('No client file was uploaded or there was an upload error.'));
        }
        
        $fileInfo = $_FILES['client_file'];
        
        // Validate file
        $allowedExtensions = ['exe', 'msi', 'zip', 'tar', 'gz'];
        $fileExtension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception(_('Invalid file type. Only EXE, MSI, ZIP, TAR, and GZ files are allowed.'));
        }
        
        // Create snapin for client distribution
        $snapinName = sprintf('FOG Client %s Update', $currentVersion);
        $snapinDescription = sprintf('Automated update to FOG Client version %s', $currentVersion);
        
        // Save the file to snapin storage
        $storagePath = sprintf('/var/www/fog/service/ipxe/snapins/%s', basename($fileInfo['name']));
        
        if (move_uploaded_file($fileInfo['tmp_name'], $storagePath)) {
            // Create snapin record
            $snapin = self::getClass('Snapin')
                ->set('name', $snapinName)
                ->set('description', $snapinDescription)
                ->set('file', basename($fileInfo['name']))
                ->set('args', '')
                ->set('runWith', '')
                ->set('runWithArgs', '')
                ->set('timeout', '300')
                ->set('reboot', '1')
                ->set('hidden', '0')
                ->set('createdBy', self::$username);
            
            if ($snapin->save()) {
                self::log(sprintf('Created client update snapin: %s (ID: %d)', $snapinName, $snapin->get('id')));
                $successMessage = _('Client file uploaded and snapin created successfully!');
            } else {
                throw new Exception(_('Failed to create snapin record.'));
            }
        } else {
            throw new Exception(_('Failed to move uploaded file to storage location.'));
        }
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get list of existing client-related snapins
try {
    $clientSnapins = self::getClass('SnapinManager')->find(
        array('name' => array('LIKE', 'FOG Client%')),
        '',
        'name DESC'
    );
} catch (Exception $e) {
    $clientSnapins = array();
}

// Get client version statistics
try {
    $versionStats = self::getClass('HostManager')->getSubObjectIDs(
        'Host',
        array('clientVersion' => array('NOT LIKE', '')),
        'clientVersion',
        false,
        'clientVersion',
        'clientVersion'
    );
    
    // Count hosts by version
    $versionCounts = array();
    foreach ($versionStats as $version) {
        if (!isset($versionCounts[$version])) {
            $versionCounts[$version] = 0;
        }
        $versionCounts[$version]++;
    }
    arsort($versionCounts); // Sort by count descending
} catch (Exception $e) {
    $versionCounts = array();
}

// Get total hosts with version information
totalHostsWithVersion = count($versionStats);

// Calculate compliance percentage
$compliantHosts = isset($versionCounts[$currentVersion]) ? $versionCounts[$currentVersion] : 0;
$compliancePercentage = $totalHostsWithVersion > 0 ? round(($compliantHosts / $totalHostsWithVersion) * 100) : 0;

// Start HTML output
echo '<div class="row">';
echo '    <div class="col-lg-12">';
echo '        <div class="panel panel-default">';
echo '            <div class="panel-heading">';
echo '                <h3 class="panel-title">' . _('FOG Client Version Management') . '</h3>';
echo '            </div>';
echo '            <div class="panel-body">';

// Show success/error messages
if (isset($successMessage)) {
    echo '<div class="alert alert-success">' . $successMessage . '</div>';
}
if (isset($errorMessage)) {
    echo '<div class="alert alert-danger">' . $errorMessage . '</div>';
}

// Client Version Compliance Dashboard
echo '<div class="row">';
echo '    <div class="col-md-6">';
echo '        <div class="panel panel-info">';
echo '            <div class="panel-heading">';
echo '                <h4 class="panel-title">' . _('Client Version Compliance') . '</h4>';
echo '            </div>';
echo '            <div class="panel-body">';
echo '                <div class="row">';
echo '                    <div class="col-xs-6">';
echo '                        <h3>' . $compliancePercentage . '%</h3>';
echo '                        <p>' . _('Compliance') . '</p>';
echo '                    </div>';
echo '                    <div class="col-xs-6 text-right">';
echo '                        <h4>' . $compliantHosts . ' / ' . $totalHostsWithVersion . '</h4>';
echo '                        <p>' . _('Hosts Updated') . '</p>';
echo '                    </div>';
echo '                </div>';
echo '                <div class="progress">';
echo '                    <div class="progress-bar progress-bar-info" style="width: ' . $compliancePercentage . '%"></div>';
echo '                </div>';
echo '            </div>';
echo '        </div>';
echo '    </div>';

echo '    <div class="col-md-6">';
echo '        <div class="panel panel-primary">';
echo '            <div class="panel-heading">';
echo '                <h4 class="panel-title">' . _('Current Settings') . '</h4>';
echo '            </div>';
echo '            <div class="panel-body">';
echo '                <dl class="dl-horizontal">';
echo '                    <dt>' . _('Expected Version') . ':</dt>';
echo '                    <dd><strong>' . htmlspecialchars($currentVersion) . '</strong></dd>';
echo '                    <dt>' . _('Total Hosts') . ':</dt>';
echo '                    <dd>' . $totalHostsWithVersion . '</dd>';
echo '                    <dt>' . _('Compliant Hosts') . ':</dt>';
echo '                    <dd>' . $compliantHosts . '</dd>';
echo '                </dl>';
echo '            </div>';
echo '        </div>';
echo '    </div>';
echo '</div>';

// Client Version Update Form
echo '<div class="panel panel-default">';
echo '    <div class="panel-heading">';
echo '        <h4 class="panel-title">' . _('Update Expected Client Version') . '</h4>';
echo '    </div>';
echo '    <div class="panel-body">';
echo '        <form method="post" class="form-horizontal">';
echo '            <div class="form-group">';
echo '                <label for="client_version" class="col-sm-3 control-label">' . _('Expected Version') . '</label>';
echo '                <div class="col-sm-6">';
echo '                    <input type="text" class="form-control" id="client_version" name="client_version" ';
echo '                           value="' . htmlspecialchars($currentVersion) . '" placeholder="e.g., 0.13.0" required>';
echo '                </div>';
echo '            </div>';
echo '            <div class="form-group">';
echo '                <div class="col-sm-offset-3 col-sm-6">';
echo '                    <button type="submit" name="update_client_version" class="btn btn-primary">';
echo '                        <i class="fa fa-save"></i> ' . _('Update Version') . '</button>';
echo '                </div>';
echo '            </div>';
echo '        </form>';
echo '    </div>';
echo '</div>';

// Client Upload Form
echo '<div class="panel panel-default">';
echo '    <div class="panel-heading">';
echo '        <h4 class="panel-title">' . _('Upload Client Update') . '</h4>';
echo '    </div>';
echo '    <div class="panel-body">';
echo '        <form method="post" class="form-horizontal" enctype="multipart/form-data">';
echo '            <div class="form-group">';
echo '                <label for="client_file" class="col-sm-3 control-label">' . _('Client File') . '</label>';
echo '                <div class="col-sm-6">';
echo '                    <input type="file" class="form-control" id="client_file" name="client_file" required>';
echo '                    <p class="help-block">' . _('Upload client installer (EXE, MSI, ZIP, TAR, or GZ)') . '</p>';
echo '                </div>';
echo '            </div>';
echo '            <div class="form-group">';
echo '                <div class="col-sm-offset-3 col-sm-6">';
echo '                    <button type="submit" name="upload_client" class="btn btn-success">';
echo '                        <i class="fa fa-upload"></i> ' . _('Upload & Create Snapin') . '</button>';
echo '                </div>';
echo '            </div>';
echo '        </form>';
echo '    </div>';
echo '</div>';

// Client Version Statistics
echo '<div class="panel panel-default">';
echo '    <div class="panel-heading">';
echo '        <h4 class="panel-title">' . _('Client Version Statistics') . '</h4>';
echo '    </div>';
echo '    <div class="panel-body">';

if (!empty($versionCounts)) {
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr>';
    echo '<th>' . _('Version') . '</th>';
    echo '<th>' . _('Host Count') . '</th>';
    echo '<th>' . _('Percentage') . '</th>';
    echo '<th>' . _('Status') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($versionCounts as $version => $count) {
        $percentage = round(($count / $totalHostsWithVersion) * 100);
        $isCurrent = ($version === $currentVersion);
        $status = $isCurrent ? _('Current') : _('Outdated');
        $statusClass = $isCurrent ? 'success' : 'warning';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($version) . '</td>';
        echo '<td>' . $count . '</td>';
        echo '<td>' . $percentage . '%</td>';
        echo '<td><span class="label label-' . $statusClass . '">' . $status . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-info">' . _('No client version data available yet.') . '</div>';
}

echo '    </div>';
echo '</div>';

// Existing Client Snapins
echo '<div class="panel panel-default">';
echo '    <div class="panel-heading">';
echo '        <h4 class="panel-title">' . _('Existing Client Update Snapins') . '</h4>';
echo '    </div>';
echo '    <div class="panel-body">';

if (!empty($clientSnapins)) {
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr>';
    echo '<th>' . _('Snapin Name') . '</th>';
    echo '<th>' . _('Description') . '</th>';
    echo '<th>' . _('File') . '</th>';
    echo '<th>' . _('Actions') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($clientSnapins as $snapin) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($snapin->get('name')) . '</td>';
        echo '<td>' . htmlspecialchars($snapin->get('description')) . '</td>';
        echo '<td>' . htmlspecialchars($snapin->get('file')) . '</td>';
        echo '<td>';
        echo '    <a href="?node=snapin&sub=edit&id=' . $snapin->get('id') . '" class="btn btn-sm btn-primary">' . _('Edit') . '</a>';
        echo '    <a href="?node=snapin&sub=assign&id=' . $snapin->get('id') . '" class="btn btn-sm btn-info">' . _('Assign') . '</a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-info">' . _('No client update snapins found. Upload a client to create one.') . '</div>';
}

echo '    </div>';
echo '</div>';

echo '            </div>';
echo '        </div>';
echo '    </div>';
echo '</div>';

echo '<script>';
echo '$(document).ready(function() {';
echo '    // Add version format validation';
echo '    $("form[method=\"post\"]").submit(function(e) {';
echo '        var versionInput = $("#client_version");';
echo '        if (versionInput.length && !/^[a-zA-Z0-9\.\-]+$/.test(versionInput.val())) {';
echo '            alert("' . _('Invalid version format. Only alphanumeric characters, periods, and hyphens are allowed.') . '");';
echo '            versionInput.focus();';
echo '            e.preventDefault();';
echo '            return false;';
echo '        }';
echo '    });';
echo '});';
echo '</script>';