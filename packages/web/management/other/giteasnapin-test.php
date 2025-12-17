<?php
// Debug file to test include
echo "<!-- Gitea Management File Loaded -->";
echo "<!-- FOG_CORE defined: " . (defined('FOG_CORE') ? 'YES' : 'NO') . " -->";
echo "<!-- BASEPATH: " . BASEPATH . " -->";

// Test basic functionality
try {
    echo '<div class="col-xs-9">';
    echo '<div class="panel panel-info">';
    echo '<div class="panel-heading text-center">';
    echo '<h4 class="title">Gitea Snapin Management - Test</h4>';
    echo '</div>';
    echo '<div class="panel-body">';
    echo '<p>This is a test to ensure the page loads correctly.</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>