<?php
/**
 * Gitea Snapin Management Page
 *
 * This page allows administrators to manage snapins from a Gitea repository.
 */

// Ensure this is being accessed through FOG management interface
if (!defined('FOG_CORE')) {
    die('Direct access not allowed');
}

// Display main Gitea snapin management page
echo '<div class="col-xs-9">';
echo '<div class="panel panel-info">';
echo '<div class="panel-heading text-center">';
echo '<h4 class="title">Gitea Snapin Management</h4>';
echo '</div>';
echo '<div class="panel-body">';

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
    
    echo '<form method="post" id="gitea-settings-form">';
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

echo '<script>';
echo '$(document).ready(function() {';
echo '    $("#save-gitea-settings").click(function() {';
echo '        var server = $("#gitea_server").val();';
echo '        var org = $("#gitea_org").val();';
echo '        ';
echo '        if (!server || !org) {';
echo '            $("#save-status").html("<span class=\"text-danger\">Please provide both server URL and organization name.</span>");';
echo '            return;';
echo '        }';
echo '        ';
echo '        $("#save-status").html("<i class=\"fa fa-spinner fa-spin\"></i> Saving...");';
echo '        ';
echo '        $.ajax({';
echo '            url: "../lib/fog/giteasnapin.class.php",';
echo '            type: "POST",';
echo '            data: {';
echo '                action: "saveSettings",';
echo '                gitea_server: server,';
echo '                gitea_org: org';
echo '            },';
echo '            success: function(response) {';
echo '                try {';
echo '                    var result = JSON.parse(response);';
echo '                    if (result.success) {';
echo '                        $("#save-status").html("<span class=\"text-success\">" + result.message + "</span>");';
echo '                        setTimeout(function() {';
echo '                            location.reload();';
echo '                        }, 2000);';
echo '                    } else {';
echo '                        $("#save-status").html("<span class=\"text-danger\">" + result.message + "</span>");';
echo '                    }';
echo '                } catch (e) {';
echo '                    $("#save-status").html("<span class=\"text-danger\">Invalid response from server</span>");';
echo '                }';
echo '            },';
echo '            error: function() {';
echo '                $("#save-status").html("<span class=\"text-danger\">Error communicating with server</span>");';
echo '            }';
echo '        });';
echo '    });';
echo '});';
echo '</script>';

?>