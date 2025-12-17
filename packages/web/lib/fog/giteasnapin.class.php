<?php
/**
 * Gitea Snapin Management AJAX Handler
 *
 * Handles AJAX requests for Gitea snapin operations
 */

// Ensure this is being accessed through proper channels
if (!defined('FOG_CORE') && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    die('Direct access not allowed');
}

// Set content type to JSON
header('Content-Type: application/json');

// Get action
$action = filter_input(INPUT_POST, 'action');

try {
    switch ($action) {
        case 'saveSettings':
            $serverUrl = trim(filter_input(INPUT_POST, 'gitea_server', FILTER_SANITIZE_URL));
            $orgName = trim(filter_input(INPUT_POST, 'gitea_org'));
            
            if (empty($serverUrl) || empty($orgName)) {
                echo json_encode(array('success' => false, 'message' => 'Please provide both server URL and organization name.'));
                exit;
            }
            
            // Save settings using FOGBase
            FOGBase::setSetting('FOG_SNAPIN_GITEA_SERVER', $serverUrl);
            FOGBase::setSetting('FOG_SNAPIN_GITEA_ORG', $orgName);
            
            echo json_encode(array('success' => true, 'message' => 'Gitea configuration updated successfully!'));
            break;
            
        case 'fetchRepos':
            $giteaServer = FOGBase::getSetting('FOG_SNAPIN_GITEA_SERVER');
            $giteaOrg = FOGBase::getSetting('FOG_SNAPIN_GITEA_ORG');
            
            if (empty($giteaServer) || empty($giteaOrg)) {
                echo json_encode(array('success' => false, 'message' => 'Gitea server or organization not configured'));
                exit;
            }
            
            // Remove trailing slash
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
                echo json_encode(array('success' => false, 'message' => "Failed to fetch repositories. HTTP Code: {$httpCode}"));
                exit;
            }
            
            $repos = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(array('success' => false, 'message' => 'Failed to parse repository data'));
                exit;
            }
            
            echo json_encode(array('success' => true, 'repos' => $repos));
            break;
            
        case 'importRepo':
            $repoName = trim(filter_input(INPUT_POST, 'repo'));
            
            if (empty($repoName)) {
                echo json_encode(array('success' => false, 'message' => 'Repository name is required'));
                exit;
            }
            
            $giteaServer = FOGBase::getSetting('FOG_SNAPIN_GITEA_SERVER');
            $giteaOrg = FOGBase::getSetting('FOG_SNAPIN_GITEA_ORG');
            
            if (empty($giteaServer) || empty($giteaOrg)) {
                echo json_encode(array('success' => false, 'message' => 'Gitea server or organization not configured'));
                exit;
            }
            
            // Remove trailing slash
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
            
            // If app.json not found, create basic snapin
            if ($httpCode !== 200) {
                // Create basic snapin without app.json
                try {
                    $Snapin = FOGBase::getClass('Snapin')
                        ->set('name', $repoName)
                        ->set('description', "Gitea snapin: {$repoName}")
                        ->set('file', $repoName . '.ps1')
                        ->set('url', "{$giteaServer}/{$giteaOrg}/{$repoName}/raw/branch/main/install.ps1")
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
                        throw new Exception('Failed to save snapin');
                    }
                    
                    $Snapin->setPrimaryGroup($storageGroupID);
                    
                    echo json_encode(array('success' => true, 'message' => 'Snapin imported successfully (basic configuration)'));
                    
                } catch (Exception $e) {
                    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
                }
                break;
            }
            
            $appData = json_decode($appJsonResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(array('success' => false, 'message' => 'Failed to parse app.json data'));
                exit;
            }
            
            // Set defaults
            $snapinName = isset($appData['name']) ? $appData['name'] : $repoName;
            $description = isset($appData['description']) ? $appData['description'] : '';
            $version = isset($appData['version']) ? $appData['version'] : '1.0.0';
            
            // Build install script URL
            $installScriptUrl = "{$giteaServer}/{$giteaOrg}/{$repoName}/raw/branch/main/install.ps1";
            
            // Create snapin
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
                    throw new Exception('Failed to save snapin');
                }
                
                $Snapin->setPrimaryGroup($storageGroupID);
                
                echo json_encode(array('success' => true, 'message' => 'Snapin imported successfully'));
                
            } catch (Exception $e) {
                echo json_encode(array('success' => false, 'message' => $e->getMessage()));
            }
            break;
            
        default:
            echo json_encode(array('success' => false, 'message' => 'Unknown action'));
            break;
    }
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
?>