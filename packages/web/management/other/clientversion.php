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
    $currentVersion = FOGBase::getSetting('FOG_CLIENT_VERSION');
} catch (Exception $e) {
    $currentVersion = '0.13.0'; // Default version
    // Create the setting if it doesn't exist
    try {
        FOGBase::setSetting('FOG_CLIENT_VERSION', $currentVersion);
        FOGBase::log('Created FOG_CLIENT_VERSION setting with default value: ' . $currentVersion);
    } catch (Exception $createError) {
        FOGBase::log('Failed to create FOG_CLIENT_VERSION setting: ' . $createError->getMessage());
    }
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
        FOGBase::setSetting('FOG_CLIENT_VERSION', $newVersion);
        
        // Log the change
        FOGBase::log(sprintf('Updated expected FOG client version from %s to %s', $currentVersion, $newVersion));
        
        $currentVersion = $newVersion;
        $successMessage = _('Client version updated successfully!');
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Handle test group designation
if (isset($_POST['toggle_test_group'])) {
    try {
        $groupId = intval($_POST['group_id']);
        $isTestGroup = isset($_POST['is_test_group']) ? 1 : 0;
        
        // Validate group ID
        if ($groupId <= 0) {
            throw new Exception(_('Invalid group ID'));
        }
        
        // Get the group and update test group status
        $group = FOGBase::getClass('Group', $groupId);
        if (!$group || !$group->isValid()) {
            throw new Exception(_('Group not found'));
        }
        
        $group->set('isTestGroup', $isTestGroup);
        if ($group->save()) {
            $action = $isTestGroup ? _('designated as test group') : _('removed from test groups');
            FOGBase::log(sprintf('Group %s (ID: %d) %s', $group->get('name'), $groupId, $action));
            $successMessage = sprintf(_('Group %s successfully %s!'), $group->get('name'), $action);
        } else {
            throw new Exception(_('Failed to update group test status'));
        }
        
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
        
        // Check if we should deploy to test groups only
        $deployToTestGroups = isset($_POST['deploy_to_test_groups']) && $_POST['deploy_to_test_groups'] == '1';
        
        // Create snapin for client distribution
        $snapinName = sprintf('FOG Client %s Update', $currentVersion);
        if ($deployToTestGroups) {
            $snapinName .= ' (Test) ' . date('Y-m-d');
        }
        $snapinDescription = sprintf('Automated update to FOG Client version %s', $currentVersion);
        if ($deployToTestGroups) {
            $snapinDescription .= ' - Test Deployment';
        }
        
        // Save the file to snapin storage
        $storagePath = sprintf('/var/www/fog/service/ipxe/snapins/%s', basename($fileInfo['name']));
        
        if (move_uploaded_file($fileInfo['tmp_name'], $storagePath)) {
            // Create snapin record
            $snapin = FOGBase::getClass('Snapin')
                ->set('name', $snapinName)
                ->set('description', $snapinDescription)
                ->set('file', basename($fileInfo['name']))
                ->set('args', '')
                ->set('runWith', '')
                ->set('runWithArgs', '')
                ->set('timeout', '300')
                ->set('reboot', '1')
                ->set('hidden', '0')
                ->set('createdBy', FOGBase::$username);
            
            if ($snapin->save()) {
                FOGBase::log(sprintf('Created client update snapin: %s (ID: %d)', $snapinName, $snapin->get('id')));
                
                // Automatically assign to test groups if requested
                if ($deployToTestGroups && !empty($testGroups)) {
                    $successCount = 0;
                    foreach ($testGroups as $testGroup) {
                        try {
                            $association = FOGBase::getClass('SnapinGroupAssociation')
                                ->set('snapinID', $snapin->get('id'))
                                ->set('groupID', $testGroup->get('id'));
                            
                            if ($association->save()) {
                                FOGBase::log(sprintf('Assigned snapin %s to test group %s (ID: %d)', 
                                    $snapinName, $testGroup->get('name'), $testGroup->get('id')));
                                $successCount++;
                            }
                        } catch (Exception $e) {
                            FOGBase::log('Failed to assign snapin to group ' . $testGroup->get('name') . ': ' . $e->getMessage());
                        }
                    }
                    
                    if ($successCount > 0) {
                        $successMessage = sprintf(_('Client file uploaded, snapin created, and automatically assigned to %d test group(s)!'), $successCount);
                    } else {
                        $successMessage = _('Client file uploaded and snapin created successfully!');
                    }
                } else {
                    $successMessage = _('Client file uploaded and snapin created successfully!');
                }
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

// Handle phased rollout promotion
if (isset($_POST['promote_to_production'])) {
    try {
        $snapinId = intval($_POST['snapin_id']);
        $confirmPromotion = isset($_POST['confirm_promotion']) && $_POST['confirm_promotion'] == '1';
        
        if ($snapinId <= 0) {
            throw new Exception(_('Invalid snapin ID'));
        }
        
        // Get the snapin
        $snapin = FOGBase::getClass('Snapin', $snapinId);
        if (!$snapin || !$snapin->isValid()) {
            throw new Exception(_('Snapin not found'));
        }
        
        // Check if this is a test snapin
        if (strpos($snapin->get('name'), '(Test)') === false) {
            throw new Exception(_('This snapin is not a test deployment and cannot be promoted'));
        }
        
        if (!$confirmPromotion) {
            // Show confirmation dialog
            echo '<div class="modal fade" id="promotionConfirmModal" tabindex="-1" role="dialog" aria-labelledby="promotionConfirmLabel">';
            echo '<div class="modal-dialog" role="document">';
            echo '<div class="modal-content">';
            echo '<form method="post">';
            echo '<div class="modal-header">';
            echo '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            echo '<h4 class="modal-title" id="promotionConfirmLabel">' . _('Confirm Production Promotion') . '</h4>';
            echo '</div>';
            echo '<div class="modal-body">';
            echo '<p>' . sprintf(_('You are about to promote the test deployment "%s" to all production groups. This will:'), htmlspecialchars($snapin->get('name'))) . '</p>';
            echo '<ul>';
            echo '<li>' . _('Create a new production snapin (without the "Test" designation)') . '</li>';
            echo '<li>' . _('Assign the production snapin to ALL non-test groups') . '</li>';
            echo '<li>' . _('Keep the original test snapin for reference') . '</li>';
            echo '</ul>';
            echo '<p class="text-danger"><strong>' . _('This action cannot be undone. Are you sure you want to proceed?') . '</strong></p>';
            echo '<input type="hidden" name="snapin_id" value="' . $snapinId . '">';
            echo '<input type="hidden" name="confirm_promotion" value="1">';
            echo '</div>';
            echo '<div class="modal-footer">';
            echo '<button type="button" class="btn btn-default" data-dismiss="modal">' . _('Cancel') . '</button>';
            echo '<button type="submit" name="promote_to_production" class="btn btn-primary">' . _('Confirm Promotion') . '</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<script>';
            echo '$("#promotionConfirmModal").modal("show");';
            echo '</script>';
        } else {
            // Perform the actual promotion
            
            // Get all non-test groups
            $productionGroups = array_filter($allGroups, function($group) {
                return $group->get('isTestGroup') == 0;
            });
            
            if (empty($productionGroups)) {
                throw new Exception(_('No production groups available for promotion'));
            }
            
            // Create production snapin (copy of test snapin without "Test" designation)
            $productionSnapinName = str_replace(' (Test) ' . date('Y-m-d'), '', $snapin->get('name'));
            $productionSnapinDescription = str_replace(' - Test Deployment', '', $snapin->get('description'));
            
            $productionSnapin = FOGBase::getClass('Snapin')
                ->set('name', $productionSnapinName)
                ->set('description', $productionSnapinDescription)
                ->set('file', $snapin->get('file'))
                ->set('args', $snapin->get('args'))
                ->set('runWith', $snapin->get('runWith'))
                ->set('runWithArgs', $snapin->get('runWithArgs'))
                ->set('timeout', $snapin->get('timeout'))
                ->set('reboot', $snapin->get('reboot'))
                ->set('hidden', $snapin->get('hidden'))
                ->set('createdBy', FOGBase::$username);
            
            if ($productionSnapin->save()) {
                FOGBase::log(sprintf('Promoted test snapin %s (ID: %d) to production as %s (ID: %d)', 
                    $snapin->get('name'), $snapin->get('id'), $productionSnapinName, $productionSnapin->get('id')));
                
                // Assign to all production groups
                $successCount = 0;
                foreach ($productionGroups as $prodGroup) {
                    try {
                        $association = FOGBase::getClass('SnapinGroupAssociation')
                            ->set('snapinID', $productionSnapin->get('id'))
                            ->set('groupID', $prodGroup->get('id'));
                        
                        if ($association->save()) {
                            FOGBase::log(sprintf('Assigned production snapin %s to group %s (ID: %d)', 
                                $productionSnapinName, $prodGroup->get('name'), $prodGroup->get('id')));
                            $successCount++;
                        }
                    } catch (Exception $e) {
                        FOGBase::log('Failed to assign production snapin to group ' . $prodGroup->get('name') . ': ' . $e->getMessage());
                    }
                }
                
                $successMessage = sprintf(_('Successfully promoted test deployment to production! Production snapin created and assigned to %d groups.'), $successCount);
                
                // Mark the original test snapin as hidden for cleanup
                $snapin->set('hidden', '1');
                $snapin->save();
                FOGBase::log(sprintf('Marked original test snapin %s (ID: %d) as hidden', $snapin->get('name'), $snapin->get('id')));
                
            } else {
                throw new Exception(_('Failed to create production snapin'));
            }
        }
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get list of existing client-related snapins
try {
    $clientSnapins = FOGBase::getClass('SnapinManager')->find(
        array('name' => array('LIKE', 'FOG Client%')),
        '',
        'name DESC'
    );
} catch (Exception $e) {
    $clientSnapins = array();
}

// Get client version statistics
try {
    $versionStats = FOGBase::getClass('HostManager')->getSubObjectIDs(
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
$totalHostsWithVersion = count($versionStats);

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

// Test Group Management Panel
echo '<div class="panel panel-default">';
echo '    <div class="panel-heading">';
echo '        <h4 class="panel-title">' . _('Test Group Management') . '</h4>';
echo '    </div>';
echo '    <div class="panel-body">';

// Handle bulk test group operations
if (isset($_POST['bulk_test_group_action'])) {
    try {
        $action = $_POST['bulk_action'];
        $groupIds = isset($_POST['group_ids']) ? $_POST['group_ids'] : array();
        
        if (!empty($groupIds) && in_array($action, ['add', 'remove'])) {
            $successCount = 0;
            foreach ($groupIds as $groupId) {
                $groupId = intval($groupId);
                if ($groupId <= 0) continue;
                
                $group = FOGBase::getClass('Group', $groupId);
                if ($group && $group->isValid()) {
                    $isTestGroup = ($action === 'add') ? 1 : 0;
                    $group->set('isTestGroup', $isTestGroup);
                    if ($group->save()) {
                        $successCount++;
                    }
                }
            }
            
            if ($successCount > 0) {
                $actionText = ($action === 'add') ? _('added to test groups') : _('removed from test groups');
                FOGBase::log(sprintf('Bulk operation: %d groups %s', $successCount, $actionText));
                $successMessage = sprintf(_('Successfully %s %d group(s)'), $actionText, $successCount);
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get all groups
try {
    $allGroups = FOGBase::getClass('GroupManager')->find('', '', 'name ASC');
    $testGroups = array_filter($allGroups, function($group) {
        return $group->get('isTestGroup') == 1;
    });
    $normalGroups = array_filter($allGroups, function($group) {
        return $group->get('isTestGroup') == 0;
    });
} catch (Exception $e) {
    $allGroups = array();
    $testGroups = array();
    $normalGroups = array();
}

echo '<div class="row">';
echo '    <div class="col-md-6">';
echo '        <h4>' . _('Current Test Groups') . ' <span class="badge">' . count($testGroups) . '</span></h4>';
        
if (!empty($testGroups)) {
    echo '<form method="post">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr><th><input type="checkbox" id="select_all_test"></th><th>' . _('Group Name') . '</th><th>' . _('Host Count') . '</th><th>' . _('Actions') . '</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($testGroups as $group) {
        $hostCount = $group->getHostCount();
        echo '<tr>';
        echo '<td><input type="checkbox" name="group_ids[]" value="' . $group->get('id') . '"></td>';
        echo '<td>' . htmlspecialchars($group->get('name')) . '</td>';
        echo '<td>' . $hostCount . '</td>';
        echo '<td>';
        echo '<form method="post" style="display: inline;">';
        echo '<input type="hidden" name="group_id" value="' . $group->get('id') . '">';
        echo '<button type="submit" name="toggle_test_group" class="btn btn-sm btn-warning" title="' . _('Remove from test groups') . '">';
        echo '<i class="fa fa-times"></i> ' . _('Remove');
        echo '</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<div class="form-group">';
    echo '<button type="submit" name="bulk_test_group_action" class="btn btn-sm btn-danger" onclick="return confirm(\'' . _('Are you sure you want to remove all selected groups from test groups?') . '\')">';
    echo '<i class="fa fa-trash"></i> ' . _('Remove Selected');
    echo '</button>';
    echo '<input type="hidden" name="bulk_action" value="remove">';
    echo '</div>';
    echo '</form>';
} else {
    echo '<div class="alert alert-info">' . _('No test groups designated yet.') . '</div>';
}

echo '    </div>';
    
    echo '    <div class="col-md-6">';
echo '        <h4>' . _('Available Groups') . ' <span class="badge">' . count($normalGroups) . '</span></h4>';
        
if (!empty($normalGroups)) {
    echo '<form method="post">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr><th><input type="checkbox" id="select_all_available"></th><th>' . _('Group Name') . '</th><th>' . _('Host Count') . '</th><th>' . _('Actions') . '</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($normalGroups as $group) {
        $hostCount = $group->getHostCount();
        echo '<tr>';
        echo '<td><input type="checkbox" name="group_ids[]" value="' . $group->get('id') . '"></td>';
        echo '<td>' . htmlspecialchars($group->get('name')) . '</td>';
        echo '<td>' . $hostCount . '</td>';
        echo '<td>';
        echo '<form method="post" style="display: inline;">';
        echo '<input type="hidden" name="group_id" value="' . $group->get('id') . '">';
        echo '<input type="hidden" name="is_test_group" value="1">';
        echo '<button type="submit" name="toggle_test_group" class="btn btn-sm btn-success" title="' . _('Designate as test group') . '">';
        echo '<i class="fa fa-plus"></i> ' . _('Add');
        echo '</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<div class="form-group">';
    echo '<button type="submit" name="bulk_test_group_action" class="btn btn-sm btn-primary" onclick="return confirm(\'' . _('Are you sure you want to add all selected groups to test groups?') . '\')">';
    echo '<i class="fa fa-plus-circle"></i> ' . _('Add Selected');
    echo '</button>';
    echo '<input type="hidden" name="bulk_action" value="add">';
    echo '</div>';
    echo '</form>';
} else {
    echo '<div class="alert alert-info">' . _('No additional groups available.') . '</div>';
}

echo '    </div>';
echo '</div>';

echo '<div class="alert alert-info">';
echo '<i class="fa fa-info-circle"></i> ';
echo _('Test groups allow you to deploy client updates to a small subset of hosts before rolling out to your entire environment. This helps ensure compatibility and stability before widespread deployment.');
echo '</div>';

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
            
            // Only show test group option if there are test groups designated
            if (!empty($testGroups)) {
                echo '            <div class="form-group">';
                echo '                <div class="col-sm-offset-3 col-sm-6">';
                echo '                    <div class="checkbox">';
                echo '                        <label>';
                echo '                            <input type="checkbox" name="deploy_to_test_groups" value="1" checked>';
                echo '                            ' . sprintf(_('Deploy to test groups only (%d groups)'), count($testGroups));
                echo '                        </label>';
                echo '                    </div>';
                echo '                    <p class="help-block">';
                echo _('Check this box to automatically assign this update to all designated test groups for initial testing before full deployment.');
                echo '                    </p>';
                echo '                </div>';
                echo '            </div>';
            }
            
            echo '            <div class="form-group">';
            echo '                <div class="col-sm-offset-3 col-sm-6">';
            echo '                    <button type="submit" name="upload_client" class="btn btn-success">';
            echo '                        <i class="fa fa-upload"></i> ' . _('Upload & Create Snapin') . '</button>';
            
            // Show warning if no test groups designated
            if (empty($testGroups)) {
                echo '                    <div class="alert alert-warning" style="margin-top: 10px;">';
                echo '                        <i class="fa fa-warning"></i> ';
                echo sprintf(_('No test groups designated. This update will be available for manual assignment to any group. <a href="#test-groups">Designate test groups</a> first for phased deployment.'));
                echo '                    </div>';
            }
            
            echo '                </div>';
            echo '            </div>';
echo '        </form>';
echo '    </div>';
echo '</div>';

// Test Group Monitoring Dashboard
if (!empty($testGroups)) {
    echo '<div class="panel panel-default">';
    echo '    <div class="panel-heading">';
    echo '        <h4 class="panel-title">' . _('Test Group Monitoring Dashboard') . '</h4>';
    echo '    </div>';
    echo '    <div class="panel-body">';
    
    // Get test group deployment statistics
    try {
        $testGroupStats = array();
        $totalTestHosts = 0;
        $compliantTestHosts = 0;
        
        foreach ($testGroups as $testGroup) {
            $groupHosts = $testGroup->get('hosts');
            $groupHostCount = count($groupHosts);
            $totalTestHosts += $groupHostCount;
            
            $groupCompliant = 0;
            foreach ($groupHosts as $host) {
                if ($host->get('clientVersion') === $currentVersion) {
                    $groupCompliant++;
                }
            }
            $compliantTestHosts += $groupCompliant;
            
            $testGroupStats[] = array(
                'name' => $testGroup->get('name'),
                'hostCount' => $groupHostCount,
                'compliantCount' => $groupCompliant,
                'complianceRate' => $groupHostCount > 0 ? round(($groupCompliant / $groupHostCount) * 100) : 0
            );
        }
        
        $overallTestCompliance = $totalTestHosts > 0 ? round(($compliantTestHosts / $totalTestHosts) * 100) : 0;
        
        echo '<div class="row">';
        echo '    <div class="col-md-6">';
        echo '        <div class="panel panel-success">';
        echo '            <div class="panel-heading">';
        echo '                <h4 class="panel-title">' . _('Overall Test Group Compliance') . '</h4>';
        echo '            </div>';
        echo '            <div class="panel-body">';
        echo '                <div class="row">';
        echo '                    <div class="col-xs-6">';
        echo '                        <h3>' . $overallTestCompliance . '%</h3>';
        echo '                        <p>' . _('Compliance Rate') . '</p>';
        echo '                    </div>';
        echo '                    <div class="col-xs-6 text-right">';
        echo '                        <h4>' . $compliantTestHosts . ' / ' . $totalTestHosts . '</h4>';
        echo '                        <p>' . _('Hosts Updated') . '</p>';
        echo '                    </div>';
        echo '                </div>';
        echo '                <div class="progress">';
        echo '                    <div class="progress-bar progress-bar-success" style="width: ' . $overallTestCompliance . '%"></div>';
        echo '                </div>';
        echo '            </div>';
        echo '        </div>';
        echo '    </div>';
    
    echo '    <div class="col-md-6">';
    echo '        <div class="panel panel-info">';
    echo '            <div class="panel-heading">';
    echo '                <h4 class="panel-title">' . _('Test Group Summary') . '</h4>';
    echo '            </div>';
    echo '            <div class="panel-body">';
    echo '                <dl class="dl-horizontal">';
    echo '                    <dt>' . _('Test Groups') . ':</dt>';
    echo '                    <dd>' . count($testGroups) . '</dd>';
    echo '                    <dt>' . _('Total Hosts') . ':</dt>';
    echo '                    <dd>' . $totalTestHosts . '</dd>';
    echo '                    <dt>' . _('Updated Hosts') . ':</dt>';
    echo '                    <dd>' . $compliantTestHosts . '</dd>';
    echo '                    <dt>' . _('Expected Version') . ':</dt>';
    echo '                    <dd><strong>' . htmlspecialchars($currentVersion) . '</strong></dd>';
    echo '                </dl>';
    echo '            </div>';
    echo '        </div>';
    echo '    </div>';
    echo '</div>';
    
    // Detailed test group breakdown
    echo '<div class="panel panel-default">';
    echo '    <div class="panel-heading">';
    echo '        <h4 class="panel-title">' . _('Test Group Deployment Status') . '</h4>';
    echo '    </div>';
    echo '    <div class="panel-body">';
    
    if (!empty($testGroupStats)) {
        echo '<table class="table table-striped table-hover">';
        echo '<thead><tr>';
        echo '<th>' . _('Group Name') . '</th>';
        echo '<th>' . _('Total Hosts') . '</th>';
        echo '<th>' . _('Updated Hosts') . '</th>';
        echo '<th>' . _('Compliance') . '</th>';
        echo '<th>' . _('Status') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($testGroupStats as $stats) {
            $statusClass = $stats['complianceRate'] >= 100 ? 'success' : ($stats['complianceRate'] >= 50 ? 'warning' : 'danger');
            $statusText = $stats['complianceRate'] >= 100 ? _('Complete') : ($stats['complianceRate'] >= 50 ? _('Partial') : _('Needs Attention'));
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($stats['name']) . '</td>';
            echo '<td>' . $stats['hostCount'] . '</td>';
            echo '<td>' . $stats['compliantCount'] . '</td>';
            echo '<td>';
            echo '<div class="progress" style="margin-bottom: 0;">';
            echo '<div class="progress-bar progress-bar-' . $statusClass . '" style="width: ' . $stats['complianceRate'] . '%"></div>';
            echo '</div>';
            echo '</td>';
            echo '<td><span class="label label-' . $statusClass . '">' . $statusText . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    echo '    </div>';
    echo '</div>';
    
    echo '    </div>';
    echo '</div>';
    
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">' . _('Error loading test group statistics: ') . $e->getMessage() . '</div>';
    }
}

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
        
        // Show promote button for test snapins
        if (strpos($snapin->get('name'), '(Test)') !== false) {
            echo '<form method="post" style="display: inline; margin-left: 5px;">';
            echo '<input type="hidden" name="snapin_id" value="' . $snapin->get('id') . '">';
            echo '<button type="submit" name="promote_to_production" class="btn btn-sm btn-success" title="' . _('Promote this test deployment to production') . '">';
            echo '<i class="fa fa-rocket"></i> ' . _('Promote');
            echo '</button>';
            echo '</form>';
        }
        
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
echo '';
echo '    // Add bulk selection functionality';
echo '    $("#select_all_test").change(function() {';
echo '        $("#select_all_test").closest("form").find("input[name=\"group_ids[]\"]").prop("checked", this.checked);';
echo '    });';
echo '';
echo '    $("#select_all_available").change(function() {';
echo '        $("#select_all_available").closest("form").find("input[name=\"group_ids[]\"]").prop("checked", this.checked);';
echo '    });';
echo '});';
echo '</script>';