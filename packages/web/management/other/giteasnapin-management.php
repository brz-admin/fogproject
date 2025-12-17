<?php
/**
 * Gitea Snapin Management Page
 *
 * This page allows administrators to manage snapins from a Gitea repository.
 */

// Debug: Start
echo "<!-- Gitea Management Start -->";

// Ensure this is being accessed through FOG management interface
if (!defined('FOG_CORE')) {
    echo "<!-- FOG_CORE not defined, dying... -->";
    die('Direct access not allowed');
}

echo "<!-- FOG_CORE defined, continuing... -->";

// Display main Gitea snapin management page
echo '<div class="col-xs-9">';
echo '<div class="panel panel-info">';
echo '<div class="panel-heading text-center">';
echo '<h4 class="title">Gitea Snapin Management</h4>';
echo '</div>';
echo '<div class="panel-body">';

echo "<!-- Starting FOGBase::getSetting... -->";

// Check if Gitea server is configured
try {
    $giteaServer = FOGBase::getSetting('FOG_SNAPIN_GITEA_SERVER');
    $giteaOrg = FOGBase::getSetting('FOG_SNAPIN_GITEA_ORG');
} catch (Exception $e) {
    $giteaServer = '';
    $giteaOrg = '';
}

if (empty($giteaServer) || empty($giteaOrg)) {
    echo '<div class="panel panel-warning">';
    echo '<div class="panel-heading text-center">';
    echo '<h4>Gitea Configuration</h4>';
    echo '</div>';
    echo '<div class="panel-body">';
    
    echo '<form method="post" class="form-horizontal" id="gitea-settings-form">';
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">Gitea Server URL</label>';
    echo '<div class="col-sm-6">';
    echo '<input type="url" class="form-control" name="gitea_server" id="gitea_server" placeholder="https://gitea.example.com" value="' . htmlspecialchars($giteaServer) . '">';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label class="col-sm-3 control-label">Gitea Organization</label>';
    echo '<div class="col-sm-6">';
    echo '<input type="text" class="form-control" name="gitea_org" id="gitea_org" placeholder="my-org" value="' . htmlspecialchars($giteaOrg) . '">';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<div class="col-sm-offset-3 col-sm-6">';
    echo '<button type="button" class="btn btn-info" id="save-gitea-settings">Save Settings</button>';
    echo ' <span id="save-status"></span>';
    echo '</div>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="form-group">';
    echo '<div class="col-sm-12">';
    echo '<button class="btn btn-warning" id="configure-gitea">Reconfigure Gitea Settings</button>';
    echo '<button class="btn btn-info" id="refresh-repos">Refresh Repositories</button>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="panel panel-info">';
    echo '<div class="panel-heading text-center">';
    echo '<h4>Repository List</h4>';
    echo '</div>';
    echo '<div class="panel-body" id="repo-list">';
    echo '<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i></div>';
    echo '</div>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';

?>

<script>
$(document).ready(function() {
    // Load repository list on page load
    loadRepoList();
    
    // Save settings
    $('#save-gitea-settings').click(function() {
        var server = $('#gitea_server').val();
        var org = $('#gitea_org').val();
        
        if (!server || !org) {
            $('#save-status').html('<span class="text-danger">Please provide both server URL and organization name.</span>');
            return;
        }
        
        $('#save-status').html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        
        $.ajax({
            url: '../lib/fog/giteasnapin.class.php',
            type: 'POST',
            data: {
                action: 'saveSettings',
                gitea_server: server,
                gitea_org: org
            },
            success: function(response) {
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        $('#save-status').html('<span class="text-success">' + result.message + '</span>');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#save-status').html('<span class="text-danger">' + result.message + '</span>');
                    }
                } catch (e) {
                    $('#save-status').html('<span class="text-danger">Invalid response from server</span>');
                }
            },
            error: function() {
                $('#save-status').html('<span class="text-danger">Error communicating with server</span>');
            }
        });
    });
    
    // Configure button
    $('#configure-gitea').click(function() {
        location.reload();
    });
    
    // Refresh button
    $('#refresh-repos').click(function() {
        loadRepoList();
    });
    
    function loadRepoList() {
        $('#repo-list').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');
        
        $.ajax({
            url: '../lib/fog/giteasnapin.class.php',
            type: 'POST',
            data: { action: 'fetchRepos' },
            success: function(response) {
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        displayRepos(result.repos);
                    } else {
                        $('#repo-list').html('<div class="alert alert-danger">' + result.message + '</div>');
                    }
                } catch (e) {
                    $('#repo-list').html('<div class="alert alert-danger">Invalid response from server</div>');
                }
            },
            error: function() {
                $('#repo-list').html('<div class="alert alert-danger">Error communicating with server</div>');
            }
        });
    }
    
    function displayRepos(repos) {
        if (!repos || repos.length === 0) {
            $('#repo-list').html('<div class="alert alert-info">No repositories found in this organization.</div>');
            return;
        }
        
        var html = '<div class="table-responsive">';
        html += '<table class="table table-striped">';
        html += '<thead><tr>';
        html += '<th><input type="checkbox" id="select-all-repos"></th>';
        html += '<th>Repository Name</th>';
        html += '<th>Description</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        repos.forEach(function(repo) {
            html += '<tr>';
            html += '<td><input type="checkbox" name="repo[]" value="' + repo.name + '"></td>';
            html += '<td>' + repo.name + '</td>';
            html += '<td>' + (repo.description || 'No description') + '</td>';
            html += '<td><button class="btn btn-sm btn-primary" onclick="importRepo(\'' + repo.name + '\')">Import</button></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
        html += '<div class="form-group">';
        html += '<button class="btn btn-success" id="import-selected">Import Selected</button>';
        html += '</div>';
        
        $('#repo-list').html(html);
    }
    
    window.importRepo = function(repoName) {
        if (!confirm('Import "' + repoName + '" as a snapin?')) {
            return;
        }
        
        $.ajax({
            url: '../lib/fog/giteasnapin.class.php',
            type: 'POST',
            data: {
                action: 'importRepo',
                repo: repoName
            },
            success: function(response) {
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('Snapin imported successfully!');
                        loadRepoList();
                    } else {
                        alert('Import failed: ' + result.message);
                    }
                } catch (e) {
                    alert('Invalid response from server');
                }
            },
            error: function() {
                alert('Error communicating with server');
            }
        });
    };
});
</script>