# Gitea Snapin Integration for FOG Project

This document explains how to use the Gitea snapin integration feature in FOG Project, which allows you to import PowerShell scripts from Gitea repositories as FOG snapins.

## Overview

The Gitea snapin integration follows the same pattern as the AppInstaller application, allowing you to:

- Fetch available snapin repositories from a Gitea organization
- Import repositories as FOG snapins using metadata from `app.json` files
- Execute `install.ps1` scripts directly from the Gitea server
- Manage snapins through the familiar FOG interface

## Requirements

- FOG Project 1.5.10 or later
- Gitea server with API access
- Gitea organization containing snapin repositories
- PowerShell execution enabled on client machines

## Configuration

### Step 1: Configure Gitea Settings

1. **Navigate to FOG Settings**:
   - Log in to your FOG web interface
   - Go to **FOG Configuration** > **FOG Settings**

2. **Set Gitea Server URL**:
   - Find the setting **FOG_SNAPIN_GITEA_SERVER**
   - Enter your Gitea server base URL (e.g., `https://servgitea.domman.ad`)
   - Save the settings

3. **Set Gitea Organization**:
   - Find the setting **FOG_SNAPIN_GITEA_ORG**
   - Enter the organization name that contains your snapin repositories (e.g., `PSappInstalls`)
   - Save the settings

### Step 2: Repository Structure

Each snapin repository in your Gitea organization should follow this structure:

```
my-snapin-repo/
├── app.json          # Required: Metadata file
└── install.ps1       # Required: Installation script
```

#### app.json Format

The `app.json` file must contain at least the following:

```json
{
  "name": "My Snapin Name",
  "description": "Description of what this snapin does",
  "version": "1.0.0",
  "requiresAdmin": true,
  "estimatedSize": "50MB"
}
```

**Required fields:**
- `name`: Display name for the snapin

**Optional fields:**
- `description`: Brief description of the snapin
- `version`: Version number
- `requiresAdmin`: Whether administrative privileges are needed (boolean)
- `estimatedSize`: Estimated size of the installation

#### install.ps1 Format

The `install.ps1` file should be a PowerShell script that performs the installation. Example:

```powershell
# Installation script for My Application
Write-Host "Installing My Application..."

# Your installation logic here
# Example: copy files, create shortcuts, etc.

Write-Host "Installation completed successfully!"
exit 0
```

## Using the Gitea Snapin Management Interface

### Accessing the Interface

1. Log in to your FOG web interface
2. Navigate to **Snapin Management**
3. Click on **Gitea Snapin Management** in the submenu

### Importing Snapins

1. **Refresh Repository List**: Click the "Refresh Repository List" button to fetch the latest repositories from your Gitea organization
2. **Select Repositories**: Check the boxes next to the repositories you want to import
3. **Import Selected**: Click "Import Selected" to import all checked repositories as FOG snapins
4. **Individual Import**: Alternatively, click the "Import" button next to individual repositories

### Automatic Updates

The system includes a powerful automatic update feature that can keep your snapins current without manual intervention:

#### Manual Update Checking

1. Click the "Check for Updates" button
2. The system will compare your imported snapins with the current repository state
3. If updates are available, you'll see a summary of:
   - **Metadata Updates**: Snapins where the `app.json` has changed (name, description, etc.)
   - **New Repositories**: New repositories that haven't been imported yet
4. Click "Apply All Updates" to update existing snapins and import new ones

#### Automatic Update Configuration

1. **Enable Auto-Refresh**: Check the "Enable automatic repository refresh" box
2. **Set Refresh Interval**: Specify how often to check for updates (in hours)
3. **Save Settings**: Click "Save Settings" to apply your configuration

#### Cron Job Setup (Optional)

For fully automated updates, set up a cron job to run the auto-update script:

```bash
# Edit crontab
crontab -e

# Add this line to run every hour
0 * * * * php /opt/fog/utils/gitea_snapin_auto_update.php >> /var/log/fog/gitea_auto_update.log 2>&1
```

The script will:
- Respect the auto-refresh interval setting
- Only run when it's time (based on last check timestamp)
- Automatically apply all available updates
- Log all actions for auditing

### Managing Imported Snapins

After importing, the snapins will appear in the regular **Snapin Management** interface where you can:

- Assign snapins to hosts or groups
- Schedule snapin deployments
- Monitor snapin execution status
- Edit snapin properties

## How It Works

### Repository Discovery

The system uses the Gitea API to fetch all repositories from the configured organization:

```
GET https://{gitea-server}/api/v1/orgs/{organization}/repos
```

### Metadata Processing

For each repository, the system fetches the `app.json` file:

```
GET https://{gitea-server}/{organization}/{repo}/raw/branch/main/app.json
```

### Snapin Creation

The system creates a FOG snapin with the following properties:

- **Name**: From `app.json` name field
- **Description**: From `app.json` description field
- **File**: `{repo-name}.ps1`
- **URL**: Direct link to the `install.ps1` script on Gitea
- **Run With**: `powershell.exe`
- **Run With Args**: `-ExecutionPolicy Bypass -NoProfile -File`
- **URL Type**: `https`

### Execution Flow

When a snapin is deployed to a client:

1. The FOG client downloads the `install.ps1` script from the Gitea server
2. The client executes the script using PowerShell with bypassed execution policy
3. The script runs with the specified arguments
4. Execution logs are captured and returned to the FOG server

## Security Considerations

### URL Validation

- Only HTTPS URLs are used for script execution
- The system validates that URLs are accessible before creating snapins
- Domain restrictions can be configured using `FOG_SNAPIN_URL_DOMAINS`

### PowerShell Execution

- Scripts are executed with `-ExecutionPolicy Bypass` to avoid PowerShell restrictions
- Administrative privileges are requested when `requiresAdmin` is true in `app.json`
- All script execution is logged by the FOG client

### Authentication

- The Gitea API is accessed without authentication (public repositories)
- For private repositories, consider using a Gitea access token in the URL

## Troubleshooting

### Common Issues

**Issue: "Failed to fetch repositories"**
- Verify the Gitea server URL is correct and accessible
- Check that the organization name is spelled correctly
- Ensure the Gitea API is enabled and accessible

**Issue: "Failed to fetch app.json"**
- Verify the repository contains an `app.json` file in the root directory
- Check that the file is on the `main` branch
- Ensure the file is valid JSON

**Issue: "Snapin import failed"**
- Check that a snapin with the same name doesn't already exist
- Verify the `app.json` contains at least the `name` field
- Check FOG server logs for detailed error information

### Debugging

1. **Check FOG Logs**: Review `/var/log/fog/fog.log` for detailed error messages
2. **Test API Access**: Use `curl` to test Gitea API access from the FOG server:
   ```bash
   curl -v https://servgitea.domman.ad/api/v1/orgs/PSappInstalls/repos
   ```
3. **Verify JSON**: Use a JSON validator to check your `app.json` files

## Best Practices

### Repository Naming

- Use descriptive, unique names for repositories
- Avoid special characters (use hyphens or underscores instead of spaces)
- Include version information in the repository name if needed

### Script Development

- Test PowerShell scripts thoroughly before pushing to Gitea
- Use clear, descriptive messages in your scripts for end users
- Handle errors gracefully and return appropriate exit codes
- Document prerequisites in the repository README

### Version Management

- Use semantic versioning in your `app.json` files
- Create new repositories for major version changes
- Update the `version` field in `app.json` for each release

### Performance

- Keep `install.ps1` scripts as small as possible
- Use external downloads for large files rather than including them in the repository
- Consider script execution time when setting the snapin timeout

## Example: Creating a New Snapin Repository

### Step 1: Create Repository on Gitea

1. Log in to your Gitea server
2. Navigate to your organization (e.g., `PSappInstalls`)
3. Click "New Repository"
4. Enter repository name (e.g., `notepad-plus-plus`)
5. Add description
6. Make the repository public
7. Click "Create Repository"

### Step 2: Initialize Repository Locally

```bash
# Clone the empty repository
git clone https://servgitea.domman.ad/PSappInstalls/notepad-plus-plus.git
cd notepad-plus-plus

# Initialize the repository
git init
git branch -M main
```

### Step 3: Add Required Files

Create `app.json`:

```json
{
  "name": "Notepad++",
  "description": "Notepad++ text editor installation",
  "version": "8.6.1",
  "category": "Utility",
  "requiresAdmin": true,
  "estimatedSize": "10MB"
}
```

Create `install.ps1`:

```powershell
# Notepad++ Installation Script
Write-Host "Installing Notepad++..."

# Download installer
$installerUrl = "https://github.com/notepad-plus-plus/notepad-plus-plus/releases/download/v8.6.1/npp.8.6.1.Installer.x64.exe"
$installerPath = "$env:TEMP\npp-installer.exe"

try {
    # Download the installer
    Invoke-WebRequest -Uri $installerUrl -OutFile $installerPath
    
    # Run the installer silently
    Start-Process -FilePath $installerPath -ArgumentList "/S" -Wait
    
    # Clean up
    Remove-Item -Path $installerPath -Force
    
    Write-Host "Notepad++ installed successfully!"
    exit 0
} catch {
    Write-Host "Failed to install Notepad++: $_"
    exit 1
}
```

### Step 4: Push to Repository

```bash
# Add all files
git add .

# Make your first commit
git commit -m "Initial commit - Add Notepad++ installation files"

# Configure your identity if not already done
git config user.name "Your Name"
git config user.email "your.email@domain.com"

# Push to main branch
git push -u origin main
```

### Step 5: Import into FOG

1. Go to **Gitea Snapin Management** in FOG
2. Click "Refresh Repository List"
3. Find "notepad-plus-plus" in the list
4. Click "Import"
5. The snapin will now be available in **Snapin Management**

## Advanced Configuration

### Custom PowerShell Arguments

You can customize the PowerShell execution arguments by editing the snapin after import:

1. Go to **Snapin Management**
2. Edit the imported snapin
3. Modify the "Snapin Run With Arguments" field
4. Save changes

### Multiple Branches

The current implementation uses the `main` branch. To use a different branch:

1. Import the snapin normally
2. Edit the snapin URL to point to your desired branch
3. Update the URL to: `https://{gitea-server}/{organization}/{repo}/raw/branch/{branch-name}/install.ps1`

### Private Repositories

For private repositories, you can:

1. Use a Gitea access token in the URL
2. Configure basic authentication in the FOG server
3. Use a reverse proxy to handle authentication

## Migration from Existing Snapins

To migrate existing snapins to use the Gitea integration:

1. Create a new repository for each snapin
2. Add the `app.json` and `install.ps1` files
3. Import the repository into FOG
4. Test the new snapin
5. Remove the old snapin once verified

## Support

For issues or questions about the Gitea snapin integration:

- Check the FOG Project documentation
- Review the FOG forums and community resources
- Submit issues to the FOG Project GitHub repository

## License

This feature is part of the FOG Project and is licensed under the GPLv3 license.
