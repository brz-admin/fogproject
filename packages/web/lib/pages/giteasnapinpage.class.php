<?php
/**
 * Gitea Snapin management page
 *
 * PHP version 5
 *
 * @category GiteaSnapinManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Gitea Snapin management page
 *
 * @category GiteaSnapinManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GiteaSnapinPage extends FOGPage
{
    /**
     * The node this page operates off of.
     *
     * @var string
     */
    public $node = 'giteasnapin';
    
    /**
     * Cache for repository information
     *
     * @var array
     */
    private $repoCache = array();
    
    /**
     * Last update check timestamp
     *
     * @var int
     */
    private $lastUpdateCheck = 0;
    
    /**
     * Initializes the Gitea snapin page class
     *
     * @param string $name the name to pass
     *
     * @return void
     */
    public function __construct($name = '')
    {
        /**
         * The real name not using our name passer.
         */
        $this->name = self::$foglang['Gitea Snapin Management'];
        /**
         * Pull in the FOG Page class items.
         */
        parent::__construct($name);
        
        /**
         * The header data for list/search.
         */
        $this->headerData = array(
            '',
            _('Repository Name'),
            _('Description'),
            _('Action')
        );
        
        /**
         * The template for the list/search elements.
         */
        $this->templates = array(
            '<input type="checkbox" name="repo[]" value="${id}" />',
            '${name}',
            '${description}',
            '<button type="button" class="btn btn-info btn-sm import-btn" data-repo="${id}">Import</button>'
        );
        
        /**
         * The attributes for the table items.
         */
        $this->attributes = array(
            array('class' => 'filter-false', 'width' => 5),
            array(),
            array(),
            array('class' => 'filter-false', 'width' => 100)
        );
    }
    
    /**
     * Display the main Gitea snapin management page
     *
     * @return void
     */
    public function index()
    {
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">' . $this->name . '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        
        // Check if Gitea server is configured
        $giteaServer = self::getSetting('FOG_SNAPIN_GITEA_SERVER');
        $giteaOrg = self::getSetting('FOG_SNAPIN_GITEA_ORG');
        
        if (empty($giteaServer) || empty($giteaOrg)) {
            echo '<div class="alert alert-warning">';
            echo '<strong>' . _('Configuration Required') . '</strong><br/>';
            echo _('Please configure the Gitea server URL and organization name in FOG Settings.') . '<br/>';
            echo '<a href="?node=fog&sub=settings" class="btn btn-primary">' . _('Go to Settings') . '</a>';
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
        
        $this->javascript();
    }
    
    /**
     * Fetch repositories from Gitea API
     *
     * @return array
     */
    public function fetchGiteaRepos()
    {
        $giteaServer = self::getSetting('FOG_SNAPIN_GITEA_SERVER');
        $giteaOrg = self::getSetting('FOG_SNAPIN_GITEA_ORG');
        
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
    public function importRepoAsSnapin($repoName)
    {
        $giteaServer = self::getSetting('FOG_SNAPIN_GITEA_SERVER');
        $giteaOrg = self::getSetting('FOG_SNAPIN_GITEA_ORG');
        
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
            $Snapin = self::getClass('Snapin')
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
            $storageGroupID = @min(self::getSubObjectIDs('StorageGroup'));
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
     * Check for updates to existing snapins
     *
     * @return array
     */
    public function checkForUpdates()
    {
        $giteaServer = self::getSetting('FOG_SNAPIN_GITEA_SERVER');
        $giteaOrg = self::getSetting('FOG_SNAPIN_GITEA_ORG');
        
        if (empty($giteaServer) || empty($giteaOrg)) {
            return array('error' => _('Gitea server or organization not configured'));
        }
        
        // Fetch current repositories
        $currentReposResult = $this->fetchGiteaRepos();
        if (isset($currentReposResult['error'])) {
            return $currentReposResult;
        }
        
        $currentRepos = $currentReposResult['repos'];
        $updatesAvailable = array();
        $newRepos = array();
        
        // Get all existing Gitea-based snapins
        $existingSnapins = self::getClass('SnapinManager')->find(array('urlType' => 'https'));
        $existingRepoNames = array();
        
        foreach ($existingSnapins as $snapin) {
            $url = $snapin->get('url');
            if (strpos($url, $giteaServer) !== false && strpos($url, $giteaOrg) !== false) {
                // Extract repo name from URL
                $parts = explode('/', $url);
                $repoName = $parts[count($parts) - 3]; // Should be the repo name
                $existingRepoNames[$repoName] = array(
                    'snapin_id' => $snapin->get('id'),
                    'snapin_name' => $snapin->get('name'),
                    'current_url' => $url
                );
            }
        }
        
        // Check each repository
        foreach ($currentRepos as $repo) {
            $repoName = $repo['name'];
            
            if (isset($existingRepoNames[$repoName])) {
                // Repository exists, check if it needs update
                $appJsonUrl = "{$giteaServer}/{$giteaOrg}/{$repoName}/raw/branch/main/app.json";
                
                // Fetch app.json to check for metadata updates
                $ch = curl_init($appJsonUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
                
                $appJsonResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $appData = json_decode($appJsonResponse, true);
                    if ($appData && isset($appData['name'])) {
                        $existingName = $existingRepoNames[$repoName]['snapin_name'];
                        $newName = $appData['name'];
                        
                        // Check if name changed (indicating metadata update)
                        if ($existingName !== $newName) {
                            $updatesAvailable[] = array(
                                'repo_name' => $repoName,
                                'type' => 'metadata',
                                'snapin_id' => $existingRepoNames[$repoName]['snapin_id'],
                                'old_name' => $existingName,
                                'new_name' => $newName
                            );
                        }
                    }
                }
            } else {
                // New repository
                $newRepos[] = $repo;
            }
        }
        
        return array(
            'success' => true,
            'updates_available' => $updatesAvailable,
            'new_repos' => $newRepos,
            'total_updates' => count($updatesAvailable),
            'total_new' => count($newRepos)
        );
    }
    
    /**
     * Update an existing snapin from repository
     *
     * @param int $snapinID The snapin ID to update
     * @param string $repoName The repository name
     * @return array
     */
    public function updateSnapinFromRepo($snapinID, $repoName)
    {
        $giteaServer = self::getSetting('FOG_SNAPIN_GITEA_SERVER');
        $giteaOrg = self::getSetting('FOG_SNAPIN_GITEA_ORG');
        
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
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
        
        // Load the existing snapin
        $Snapin = new Snapin($snapinID);
        if (!$Snapin->isValid()) {
            return array('error' => _('Snapin not found'));
        }
        
        // Update snapin properties
        $snapinName = $appData['name'];
        $description = isset($appData['description']) ? $appData['description'] : '';
        
        // Build the install script URL
        $installScriptUrl = "{$giteaServer}/{$giteaOrg}/{$repoName}/raw/branch/main/install.ps1";
        
        try {
            $Snapin->set('name', $snapinName)
                ->set('description', $description)
                ->set('url', $installScriptUrl);
            
            if (!$Snapin->save()) {
                throw new Exception(_('Failed to update snapin'));
            }
            
            return array('success' => true, 'message' => _('Snapin updated successfully'));
            
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Apply all available updates
     *
     * @return array
     */
    public function applyAllUpdates()
    {
        $checkResult = $this->checkForUpdates();
        
        if (isset($checkResult['error'])) {
            return $checkResult;
        }
        
        $updatesApplied = 0;
        $newImports = 0;
        $errors = array();
        
        // Apply metadata updates
        foreach ($checkResult['updates_available'] as $update) {
            if ($update['type'] === 'metadata') {
                $result = $this->updateSnapinFromRepo($update['snapin_id'], $update['repo_name']);
                if (isset($result['success']) && $result['success']) {
                    $updatesApplied++;
                } else {
                    $errors[] = sprintf(_('Failed to update %s: %s'), $update['repo_name'], $result['error']);
                }
            }
        }
        
        // Import new repositories
        foreach ($checkResult['new_repos'] as $repo) {
            $result = $this->importRepoAsSnapin($repo['name']);
            if (isset($result['success']) && $result['success']) {
                $newImports++;
            } else {
                $errors[] = sprintf(_('Failed to import %s: %s'), $repo['name'], $result['error']);
            }
        }
        
        return array(
            'success' => true,
            'updates_applied' => $updatesApplied,
            'new_imports' => $newImports,
            'errors' => $errors,
            'message' => sprintf(_('%d updates applied, %d new snapins imported'), $updatesApplied, $newImports)
        );
    }
    
    /**
     * Check for updates AJAX endpoint
     *
     * @return void
     */
    public function checkUpdates()
    {
        $result = $this->checkForUpdates();
        echo json_encode($result);
        exit;
    }
    
    /**
     * Apply updates AJAX endpoint
     *
     * @return void
     */
    public function applyUpdates()
    {
        $result = $this->applyAllUpdates();
        echo json_encode($result);
        exit;
    }
    
    /**
     * Get last check time AJAX endpoint
     *
     * @return void
     */
    public function getLastCheckTime()
    {
        $lastCheck = self::getSetting('FOG_GITEA_LAST_CHECK');
        echo json_encode(array('last_check' => $lastCheck));
        exit;
    }
    
    /**
     * Save auto-refresh settings AJAX endpoint
     *
     * @return void
     */
    public function saveAutoRefreshSettings()
    {
        $enabled = filter_input(INPUT_POST, 'enabled') ? '1' : '0';
        $intervalHours = filter_input(INPUT_POST, 'interval', FILTER_VALIDATE_INT);
        
        if ($intervalHours === false || $intervalHours < 1) {
            $intervalHours = 24; // Default to 24 hours
        }
        
        $intervalSeconds = $intervalHours * 3600;
        
        // Save settings
        self::getClass('FOGSettingsManager')->update(array('name' => 'FOG_GITEA_AUTO_REFRESH'), '', array('value' => $enabled));
        self::getClass('FOGSettingsManager')->update(array('name' => 'FOG_GITEA_REFRESH_INTERVAL'), '', array('value' => $intervalSeconds));
        self::getClass('FOGSettingsManager')->update(array('name' => 'FOG_GITEA_LAST_CHECK'), '', array('value' => time()));
        
        echo json_encode(array('success' => true, 'message' => _('Auto-refresh settings saved')));
        exit;
    }
    
    /**
     * Generate JavaScript for the page
     *
     * @return void
     */
    private function javascript()
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
            
            // Save auto-refresh settings
            $('#save-auto-refresh').click(function() {
                saveAutoRefreshSettings();
            });
            
            // Load last check time
            loadLastCheckTime();
        });
        
        function loadRepoList() {
            $('#repo-list-container').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');
            
            $.ajax({
                url: '<?php echo $this->formAction; ?>&sub=fetchRepos',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#repo-list-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                    } else if (response.success && response.repos) {
                        renderRepoList(response.repos);
                    } else {
                        $('#repo-list-container').html('<div class="alert alert-warning">' + '<?php echo _('No repositories found'); ?>' + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#repo-list-container').html('<div class="alert alert-danger">' + '<?php echo _('Failed to load repositories'); ?>' + ': ' + error + '</div>');
                }
            });
        }
        
        function renderRepoList(repos) {
            if (repos.length === 0) {
                $('#repo-list-container').html('<div class="alert alert-info">' + '<?php echo _('No repositories found in the organization'); ?>' + '</div>');
                return;
            }
            
            var html = '<table class="table table-striped table-bordered table-hover">';
            html += '<thead><tr>';
            html += '<th><input type="checkbox" id="select-all-repos" /></th>';
            html += '<th>' + '<?php echo _('Repository Name'); ?>' + '</th>';
            html += '<th>' + '<?php echo _('Description'); ?>' + '</th>';
            html += '<th>' + '<?php echo _('Action'); ?>' + '</th>';
            html += '</tr></thead><tbody>';
            
            $.each(repos, function(index, repo) {
                html += '<tr>';
                html += '<td><input type="checkbox" name="repo[]" value="' + repo.name + '" /></td>';
                html += '<td>' + repo.name + '</td>';
                html += '<td>' + (repo.description || '') + '</td>';
                html += '<td><button type="button" class="btn btn-info btn-sm import-btn" data-repo="' + repo.name + '">' + '<?php echo _('Import'); ?>' + '</button></td>';
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
            
            $('#repo-list-container').prepend('<div id="import-progress" class="alert alert-info">' + 
                '<?php echo _('Importing repositories...'); ?>' + ' <span id="import-status">0/' + repoNames.length + '</span></div>');
            
            function importNext() {
                if (importCount >= repoNames.length) {
                    $('#import-progress').removeClass('alert-info').addClass('alert-success').html(
                        '<?php echo _('Import completed'); ?>' + ': ' + successCount + ' <?php echo _('successful'); ?>, ' + errorCount + ' <?php echo _('failed'); ?>'
                    );
                    return;
                }
                
                var repoName = repoNames[importCount];
                importCount++;
                
                $.ajax({
                    url: '<?php echo $this->formAction; ?>&sub=importRepo',
                    type: 'POST',
                    data: {repoName: repoName},
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
            $('#repo-list-container').prepend('<div id="update-check-progress" class="alert alert-info">' + 
                '<?php echo _('Checking for updates...'); ?>' + '</div>');
            
            $.ajax({
                url: '<?php echo $this->formAction; ?>&sub=checkUpdates',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#update-check-progress').remove();
                    
                    if (response.error) {
                        $('#repo-list-container').prepend('<div class="alert alert-danger">' + response.error + '</div>');
                        return;
                    }
                    
                    if (response.total_updates === 0 && response.total_new === 0) {
                        $('#repo-list-container').prepend('<div class="alert alert-success">' + 
                            '<?php echo _('All snapins are up to date!'); ?>' + '</div>');
                        return;
                    }
                    
                    var html = '<div class="panel panel-warning">';
                    html += '<div class="panel-heading">';
                    html += '<h4 class="panel-title">' + '<?php echo _('Updates Available'); ?>' + '</h4>';
                    html += '</div>';
                    html += '<div class="panel-body">';
                    
                    if (response.total_updates > 0) {
                        html += '<h5>' + '<?php echo _('Metadata Updates'); ?>' + ' (' + response.total_updates + ')</h5>';
                        html += '<ul>';
                        $.each(response.updates_available, function(index, update) {
                            html += '<li>' + update.repo_name + ': ' + update.old_name + ' → ' + update.new_name + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    if (response.total_new > 0) {
                        html += '<h5>' + '<?php echo _('New Repositories'); ?>' + ' (' + response.total_new + ')</h5>';
                        html += '<ul>';
                        $.each(response.new_repos, function(index, repo) {
                            html += '<li>' + repo.name + ' - ' + (repo.description || '') + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '<button type="button" id="apply-updates" class="btn btn-primary">' + 
                        '<?php echo _('Apply All Updates'); ?>' + '</button>';
                    html += '</div>';
                    html += '</div>';
                    
                    $('#repo-list-container').prepend(html);
                },
                error: function(xhr, status, error) {
                    $('#update-check-progress').removeClass('alert-info').addClass('alert-danger').html(
                        '<?php echo _('Failed to check for updates'); ?>' + ': ' + error
                    );
                }
            });
        }
        
        function applyAllUpdates() {
            $('#apply-updates').prop('disabled', true).text('<?php echo _('Applying...'); ?>');
            
            $.ajax({
                url: '<?php echo $this->formAction; ?>&sub=applyUpdates',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#repo-list-container').prepend('<div class="alert alert-danger">' + response.error + '</div>');
                        $('#apply-updates').prop('disabled', false).text('<?php echo _('Apply All Updates'); ?>');
                        return;
                    }
                    
                    $('#repo-list-container').prepend('<div class="alert alert-success">' + response.message + '</div>');
                    
                    if (response.errors.length > 0) {
                        var errorHtml = '<div class="alert alert-warning">';
                        errorHtml += '<h5>' + '<?php echo _('Some updates failed'); ?>' + ':</h5>';
                        errorHtml += '<ul>';
                        $.each(response.errors, function(index, error) {
                            errorHtml += '<li>' + error + '</li>';
                        });
                        errorHtml += '</ul>';
                        errorHtml += '</div>';
                        $('#repo-list-container').prepend(errorHtml);
                    }
                    
                    // Refresh the repository list to show updates
                    loadRepoList();
                },
                error: function(xhr, status, error) {
                    $('#repo-list-container').prepend('<div class="alert alert-danger">' + 
                        '<?php echo _('Failed to apply updates'); ?>' + ': ' + error + '</div>');
                    $('#apply-updates').prop('disabled', false).text('<?php echo _('Apply All Updates'); ?>');
                }
            });
        }
        
        function saveAutoRefreshSettings() {
            var enabled = $('#auto-refresh-enabled').is(':checked') ? 1 : 0;
            var interval = $('#auto-refresh-interval').val();
            
            $.ajax({
                url: '<?php echo $this->formAction; ?>&sub=saveAutoRefreshSettings',
                type: 'POST',
                data: {enabled: enabled, interval: interval},
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#repo-list-container').prepend('<div class="alert alert-success">' + response.message + '</div>');
                        loadLastCheckTime();
                    } else {
                        $('#repo-list-container').prepend('<div class="alert alert-danger">' + 
                            '<?php echo _('Failed to save settings'); ?>' + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#repo-list-container').prepend('<div class="alert alert-danger">' + 
                        '<?php echo _('Failed to save settings'); ?>' + ': ' + error + '</div>');
                }
            });
        }
        
        function loadLastCheckTime() {
            $.ajax({
                url: '<?php echo $this->formAction; ?>&sub=getLastCheckTime',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.last_check) {
                        var date = new Date(response.last_check * 1000);
                        $('#last-check-time').text(date.toLocaleString());
                    } else {
                        $('#last-check-time').text('<?php echo _('Never'); ?>');
                    }
                }
            });
        }
        
        // Handle apply updates button click (dynamically added)
        $(document).on('click', '#apply-updates', function() {
            applyAllUpdates();
        });
        </script>
        <?php
        $javascript = ob_get_clean();
        self::$HookManager->add('javascript', $javascript);
    }
    
    /**
     * Fetch repositories AJAX endpoint
     *
     * @return void
     */
    public function fetchRepos()
    {
        $result = $this->fetchGiteaRepos();
        echo json_encode($result);
        exit;
    }
    
    /**
     * Import repository AJAX endpoint
     *
     * @return void
     */
    public function importRepo()
    {
        $repoName = filter_input(INPUT_POST, 'repoName');
        
        if (empty($repoName)) {
            echo json_encode(array('error' => _('Repository name is required')));
            exit;
        }
        
        $result = $this->importRepoAsSnapin($repoName);
        echo json_encode($result);
        exit;
    }
}