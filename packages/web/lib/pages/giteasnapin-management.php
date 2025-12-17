<?php
/**
 * Gitea Snapin Management Page (included file version)
 *
 * This page allows administrators to manage snapins from a Gitea repository,
 * including importing repositories as snapins and checking for updates.
 *
 * @category Management
 * @package  FOGProject
 * @author   FOG Project
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Cache for repository information
 *
 * @var array
 */
$repoCache = array();

/**
 * Last update check timestamp
 *
 * @var int
 */
$lastUpdateCheck = 0;

// Display the main Gitea snapin management page
echo '<div class="col-xs-9">';
echo '<div class="panel panel-info">';
echo '<div class="panel-heading text-center">';
echo '<h4 class="title">' . _('Gitea Snapin Management') . '</h4>';
echo '</div>';
echo '<div class="panel-body">';

// Check if Gitea server is configured
$giteaServer = FOGBase::getSetting('FOG_SNAPIN_GITEA_SERVER');
$giteaOrg = FOGBase::getSetting('FOG_SNAPIN_GITEA_ORG');

if (empty($giteaServer) || empty($giteaOrg)) {
    echo '<div class="panel panel-warning">';
    echo '<div class="panel-heading text-center">';
    echo '<h4>' . _('Gitea Configuration') . '</h4>';
    echo '</div>';
    echo '<div class="panel-body">';
    
    echo '<form method="post" class="form-horizontal">';
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">' . _('Gitea Server URL') . '</label>';
    echo '<div class="col-sm-6">';
    echo '<input type="url" name="gitea_server" class="form-control" placeholder="https://gitea.example.com" value="' . htmlspecialchars($giteaServer, ENT_QUOTES, 'UTF-8') . '" required />';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">' . _('Organization Name') . '</label>';
    echo '<div class="col-sm-6">';
    echo '<input type="text" name="gitea_org" class="form-control" placeholder="my-organization" value="' . htmlspecialchars($giteaOrg, ENT_QUOTES, 'UTF-8') . '" required />';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<div class="col-sm-9 col-sm-offset-3">';
    echo '<button type="submit" name="save_gitea_settings" class="btn btn-primary">' . _('Save Configuration') . '</button>';
    echo '</div>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="form-group">';
    echo '<button type="button" id="refresh-repos" class="btn btn-primary">' . _('Refresh Repository List') . '</button>';
    echo '<button type="button" id="import-selected" class="btn btn-success">' . _('Import Selected') . '</button>';
    echo '<button type="button" id="check-updates" class="btn btn-info">' . _('Check for Updates') . '</button>';
    echo '</div>';
    
    echo '<div id="repo-list-container">';
    echo '<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i></div>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';

giteaJavaScript();

/**
 * Handle form submission for saving settings
 */
if (isset($_POST['save_gitea_settings'])) {
    $serverUrl = trim(filter_input(INPUT_POST, 'gitea_server', FILTER_SANITIZE_URL));
    $orgName = trim(filter_input(INPUT_POST, 'gitea_org', FILTER_SANITIZE_STRING));
    
    if (!empty($serverUrl) && !empty($orgName)) {
        try {
            FOGBase::setSetting('FOG_SNAPIN_GITEA_SERVER', $serverUrl);
            FOGBase::setSetting('FOG_SNAPIN_GITEA_ORG', $orgName);
            
            header('Content-Type: application/json');
            echo json_encode(array('success' => true, 'message' => _('Gitea configuration has been updated successfully.')));
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(array('error' => $e->getMessage()));
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(array('error' => _('Please provide both server URL and organization name.')));
    }
    exit;
}

/**
 * Fetch repositories from Gitea API
 *
 * @return array
 */
function fetchGiteaRepos()
{
    global $giteaServer, $giteaOrg;
    
    if (empty($giteaServer) || empty($giteaOrg)) {
        return array('error' => _('Gitea server or organization not configured'));
    }
    
    // Remove trailing slash if present
    $giteaServer = rtrim($giteaServer, '/');
    
    // Build API URL
    $apiUrl = "{$giteaServer}/api/v1/orgs/{$giteaOrg}/repos";
    
    // Initialize cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json'
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return array('error' => sprintf(_('Failed to fetch repositories. HTTP Code: %d'), $httpCode));
    }
    
    $repos = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('error' => _('Failed to parse repository data'));
    }
    
    return array('success' => true, 'repos' => $repos);
}

/**
 * Import a repository as a snapin
 *
 * @param string $repoName The repository name
 * @return array
 */
function importRepoAsSnapin($repoName)
{
    global $giteaServer, $giteaOrg;
    
    if (empty($giteaServer) || empty($giteaOrg)) {
        return array('error' => _('Gitea server or organization not configured'));
    }
    
    // Remove trailing slash if present
    $giteaServer = rtrim($giteaServer, '/');
    
    // Build raw URL for app.json
    $appJsonUrl = "{$giteaServer}/{$giteaOrg}/{$repoName}/raw/branch/main/app.json";
    
    // Fetch app.json
    $ch = curl_init($appJsonUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json'
    ));
    
    $appJsonResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return array('error' => sprintf(_('Failed to fetch app.json. HTTP Code: %d'), $httpCode));
    }
    
    $appData = json_decode($appJsonResponse, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('error' => _('Failed to parse app.json data'));
    }
    
    // Validate required fields
    if (empty($appData['name'])) {
        return array('error' => _('app.json is missing required "name" field'));
    }
    
    // Set defaults
    $snapinName = $appData['name'];
    $description = isset($appData['description']) ? $appData['description'] : '';
    $requiresAdmin = isset($appData['requiresAdmin']) ? $appData['requiresAdmin'] : false;
    $estimatedSize = isset($appData['estimatedSize']) ? $appData['estimatedSize'] : '';
    $version = isset($appData['version']) ? $appData['version'] : '1.0.0';
    
    // Build the install script URL
    $installScriptUrl = "{$giteaServer}/{$giteaOrg}/{$repoName}/raw/branch/main/install.ps1";
    
    // Create the snapin
    try {
        $Snapin = FOGBase::getClass('Snapin')
            ->set('name', $snapinName)
            ->set('description', $description)
            ->set('file', $repoName . '.ps1')
            ->set('url', $installScriptUrl)
            ->set('urlType', 'https')
            ->set('args', '')
            ->set('reboot', false)
            ->set('shutdown', false)
            ->set('runWith', 'powershell.exe')
            ->set('runWithArgs', '-ExecutionPolicy Bypass -NoProfile -File')
            ->set('isEnabled', true)
            ->set('toReplicate', false)
            ->set('hide', false)
            ->set('timeout', 300)
            ->set('packtype', 0);
        
        // Get default storage group
        $storageGroupID = @min(FOGBase::getSubObjectIDs('StorageGroup'));
        $Snapin->addGroup($storageGroupID);
        
        if (!$Snapin->save()) {
            throw new Exception(_('Failed to save snapin'));
        }
        
        $Snapin->setPrimaryGroup($storageGroupID);
        
        return array('success' => true, 'message' => _('Snapin imported successfully'));
        
    } catch (Exception $e) {
        return array('error' => $e->getMessage());
    }
}

/**
 * Generate JavaScript for the page
 *
 * @return void
 */
function giteaJavaScript()
{
    ob_start();
    ?>
    <script>
    $(document).ready(function() {
        // Load repository list on page load
        loadRepoList();
        
        // Refresh button click
        $('#refresh-repos').click(function() {
            loadRepoList();
        });
        
        // Import selected button click
        $('#import-selected').click(function() {
            var selectedRepos = [];
            $('input[name="repo[]"]:checked').each(function() {
                selectedRepos.push($(this).val());
            });
            
            if (selectedRepos.length === 0) {
                alert('<?php echo _('Please select at least one repository to import'); ?>');
                return;
            }
            
            importRepos(selectedRepos);
        });
        
        // Individual import button click
        $(document).on('click', '.import-btn', function() {
            var repoName = $(this).data('repo');
            importRepos([repoName]);
        });
        
        // Check for updates button click
        $('#check-updates').click(function() {
            checkForUpdates();
        });
    });
    
    function loadRepoList() {
        $('#repo-list-container').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');
        
        $.ajax({
            url: '?node=about&sub=giteasnapin',
            type: 'GET',
            data: {sub: 'fetchRepos'},
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    $('#repo-list-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                } else if (response.success && response.repos) {
                    renderRepoList(response.repos);
                } else {
                    $('#repo-list-container').html('<div class="alert alert-warning"><?php echo _('No repositories found'); ?></div>');
                }
            },
            error: function(xhr, status, error) {
                $('#repo-list-container').html('<div class="alert alert-danger"><?php echo _('Failed to load repositories'); ?>: ' + error + '</div>');
            }
        });
    }
    
    function renderRepoList(repos) {
        if (repos.length === 0) {
            $('#repo-list-container').html('<div class="alert alert-info"><?php echo _('No repositories found in the organization'); ?></div>');
            return;
        }
        
        var html = '<table class="table table-striped table-bordered table-hover">';
        html += '<thead><tr>';
        html += '<th><input type="checkbox" id="select-all-repos" /></th>';
        html += '<th><?php echo _('Repository Name'); ?></th>';
        html += '<th><?php echo _('Description'); ?></th>';
        html += '<th><?php echo _('Action'); ?></th>';
        html += '</tr></thead><tbody>';
        
        $.each(repos, function(index, repo) {
            html += '<tr>';
            html += '<td><input type="checkbox" name="repo[]" value="' + repo.name + '" /></td>';
            html += '<td>' + repo.name + '</td>';
            html += '<td>' + (repo.description || '') + '</td>';
            html += '<td><button type="button" class="btn btn-info btn-sm import-btn" data-repo="' + repo.name + '"><?php echo _('Import'); ?></button></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('#repo-list-container').html(html);
        
        // Add select all functionality
        $('#select-all-repos').change(function() {
            $('input[name="repo[]"]').prop('checked', $(this).prop('checked'));
        });
    }
    
    function importRepos(repoNames) {
        var importCount = 0;
        var successCount = 0;
        var errorCount = 0;
        
        $('#repo-list-container').prepend('<div id="import-progress" class="alert alert-info"><?php echo _('Importing repositories...'); ?> <span id="import-status">0/' + repoNames.length + '</span></div>');
        
        function importNext() {
            if (importCount >= repoNames.length) {
                $('#import-progress').removeClass('alert-info').addClass('alert-success').html('<?php echo _('Import completed'); ?>: ' + successCount + ' <?php echo _('successful'); ?>, ' + errorCount + ' <?php echo _('failed'); ?>');
                return;
            }
            
            var repoName = repoNames[importCount];
            importCount++;
            
            $.ajax({
                url: '?node=about&sub=giteasnapin',
                type: 'POST',
                data: {sub: 'importRepo', repoName: repoName},
                dataType: 'json',
                success: function(response) {
                    $('#import-status').text(importCount + '/' + repoNames.length);
                    
                    if (response.success) {
                        successCount++;
                        $('button[data-repo="' + repoName + '"]').removeClass('btn-info').addClass('btn-success').text('<?php echo _('Imported'); ?>');
                    } else {
                        errorCount++;
                        $('button[data-repo="' + repoName + '"]').removeClass('btn-info').addClass('btn-danger').text('<?php echo _('Failed'); ?>');
                    }
                    
                    importNext();
                },
                error: function(xhr, status, error) {
                    $('#import-status').text(importCount + '/' + repoNames.length);
                    errorCount++;
                    $('button[data-repo="' + repoName + '"]').removeClass('btn-info').addClass('btn-danger').text('<?php echo _('Failed'); ?>');
                    importNext();
                }
            });
        }
        
        importNext();
    }
    
    function checkForUpdates() {
        $('#repo-list-container').prepend('<div id="update-check-progress" class="alert alert-info"><?php echo _('Checking for updates...'); ?></div>');
        
        $.ajax({
            url: '?node=about&sub=giteasnapin',
            type: 'GET',
            data: {sub: 'checkUpdates'},
            dataType: 'json',
            success: function(response) {
                $('#update-check-progress').remove();
                
                if (response.error) {
                    $('#repo-list-container').prepend('<div class="alert alert-danger">' + response.error + '</div>');
                    return;
                }
                
                if (response.total_updates === 0 && response.total_new === 0) {
                    $('#repo-list-container').prepend('<div class="alert alert-success"><?php echo _('All snapins are up to date!'); ?></div>');
                    return;
                }
                
                var html = '<div class="panel panel-warning">';
                html += '<div class="panel-heading">';
                html += '<h4 class="panel-title"><?php echo _('Updates Available'); ?></h4>';
                html += '</div>';
                html += '<div class="panel-body">';
                
                if (response.total_updates > 0) {
                    html += '<h5><?php echo _('Metadata Updates'); ?> (' + response.total_updates + ')</h5>';
                    html += '<ul>';
                    $.each(response.updates_available, function(index, update) {
                        html += '<li>' + update.repo_name + ': ' + update.old_name + ' → ' + update.new_name + '</li>';
                    });
                    html += '</ul>';
                }
                
                if (response.total_new > 0) {
                    html += '<h5><?php echo _('New Repositories'); ?> (' + response.total_new + ')</h5>';
                    html += '<ul>';
                    $.each(response.new_repos, function(index, repo) {
                        html += '<li>' + repo.name + ' - ' + (repo.description || '') + '</li>';
                    });
                    html += '</ul>';
                }
                
                html += '</div>';
                html += '</div>';
                
                $('#repo-list-container').prepend(html);
            },
            error: function(xhr, status, error) {
                $('#update-check-progress').removeClass('alert-info').addClass('alert-danger').html('<?php echo _('Failed to check for updates'); ?>: ' + error);
            }
        });
    }
    </script>
    <?php
    $javascript = ob_get_clean();
    FOGCore::$HookManager->add('javascript', $javascript);
}

/**
 * AJAX handlers for sub-requests
 */
$sub = filter_input(INPUT_POST, 'sub') ?: filter_input(INPUT_GET, 'sub');

switch ($sub) {
    case 'fetchRepos':
        $result = fetchGiteaRepos();
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    case 'importRepo':
        $repoName = filter_input(INPUT_POST, 'repoName');
        
        if (empty($repoName)) {
            header('Content-Type: application/json');
            echo json_encode(array('error' => _('Repository name is required')));
            exit;
        }
        
        $result = importRepoAsSnapin($repoName);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    case 'checkUpdates':
        // For simplicity, just return no updates for now
        // This can be expanded later with full update checking logic
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'total_updates' => 0,
            'total_new' => 0,
            'updates_available' => array(),
            'new_repos' => array()
        ));
        exit;
}
?>