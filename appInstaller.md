# Application Installer

A C# WPF application that provides a GUI for installing applications from the PSappInstalls organization on a Gitea server.

## Features

- Fetches available applications from `https://servgitea.domman.ad/PSappInstalls`
- Displays applications with metadata from `app.json` files
- Downloads and executes `install.ps1` directly from main branch
- **Post-install testing**: Executes `test.ps1` scripts to verify installations
- **Reminders system**: Displays user reminders from `reminders.json` files
- **French interface**: Complete French localization
- Clean, professional Windows interface
- Single executable deployment
- Comprehensive logging and error handling
- Sequential installation: One application at a time for safety

## Architecture

- **Models**: Data structures for Gitea API responses and application metadata
- **Services**: API client, PowerShell executor, logging
- **ViewModels**: MVVM pattern implementation
- **Views**: WPF user interface
- **CI/CD**: Automated builds and releases via Gitea Actions

## Building

### Prerequisites

- .NET 8 SDK
- For cross-compilation from Linux: .NET 8 SDK with WPF support

### Build Commands

```bash
# Build console version (for testing)
dotnet build -c Release

# Build Windows WPF version
dotnet build AppInstaller.Wpf.csproj -c Release -r win-x64 --self-contained

# Create Windows executable
dotnet publish AppInstaller.Wpf.csproj -c Release -r win-x64 --self-contained -p:PublishSingleFile=true -o ./publish

# Or use the build script
./build.sh
```

## Usage

1. Run the application on Windows
2. Applications are automatically loaded from PSappInstalls organization
3. Select desired applications using checkboxes
4. Click "Installer les Applications Sélectionnées" to begin installation
5. Applications are installed **sequentially** (one at a time) for safety
6. After installation, you can:
   - Run post-install tests for applications that have `test.ps1`
   - View reminders from `reminders.json` files
   - Save installation reports
7. Monitor progress and results in the interface

## Repository Structure

Each application repository should contain:

### app.json (required)
```json
{
  "name": "Application Display Name",
  "description": "Brief description of what this application does",
  "version": "1.0.0",
  "category": "Development",
  "requiresAdmin": true,
  "estimatedSize": "50MB"
}
```

### install.ps1 (required)
- PowerShell script that installs the application
- Should be placed at the repository root

### test.ps1 (optional)
- PowerShell script that tests the installation
- Should be placed at the repository root
- Returns exit code 0 for success, non-zero for failure

### reminders.json (optional)
- JSON file containing user reminders after installation
- Should be placed at the repository root
- Format:
```json
{
  "reminders": [
    {
      "title": "Restart Required",
      "message": "Please restart your computer to complete installation",
      "type": "warning",
      "priority": "high"
    }
  ]
}
```

## Configuration

Fixed configuration (not user-configurable):
- Gitea Server: `https://servgitea.domman.ad`
- Organization: `PSappInstalls`
- Metadata file: `app.json`
- Install script: `install.ps1`
- Test script: `test.ps1` (optional)
- Reminders file: `reminders.json` (optional)

## Development

### Project Structure
```
AppInstaller/
├── Models/           # Data models (ApplicationInfo, TestResult, Reminder, etc.)
├── Services/         # Business logic (API client, PowerShell executor, logging)
├── ViewModels/       # MVVM view models with commands
├── Views/           # WPF views (MainWindow, InstallationCompleteWindow)
├── Wpf/            # Windows-specific WPF files
├── Helpers/         # Constants and utilities
└── Converters/      # Value converters
```

## CI/CD

Automated builds are configured via Gitea Actions:
- **Build**: Triggers on push to main/develop branches
- **Release**: Triggers on version tags (v1.0.0, etc.)

## Security

- PowerShell scripts are executed with bypassed execution policy
- Administrative privileges are requested when required
- All script execution is logged
- Temporary files are cleaned up after installation

## Logging

Logs are stored in:
`%APPDATA%\AppInstaller\Logs\AppInstaller_YYYYMMDD.log`

## Tutorial: How to Publish a New Application

### Step 1: Create Repository on Gitea
1. Login to `https://servgitea.domman.ad`
2. Go to the `PSappInstalls` organization
3. Click "New Repository"
4. Name your repository (e.g., `my-application`)
5. Add a description
6. Make the repository public
7. Click "Create Repository"

### Step 2: Initialize Repository Locally
```bash
# Clone the empty repository
git clone https://servgitea.domman.ad/PSappInstalls/my-application.git
cd my-application

# Initialize the repository
git init
git branch -M main
```

### Step 3: Add Required Files

Create the `app.json` file:
```json
{
  "name": "My Application",
  "description": "Description of my application",
  "version": "1.0.0",
  "category": "Utility",
  "requiresAdmin": false,
  "estimatedSize": "25MB"
}
```

Create the `install.ps1` file:
```powershell
# Installation script for My Application
Write-Host "Installing My Application..."

# Your installation logic here
# Example: copy files, create shortcuts, etc.

Write-Host "Installation completed successfully!"
exit 0
```

Optionally, create `test.ps1`:
```powershell
# Test script for My Application
Write-Host "Testing installation of My Application..."

# Your test logic here
# Verify that the application is properly installed

if ($testSuccess) {
    Write-Host "Test passed!"
    exit 0
} else {
    Write-Host "Test failed!"
    exit 1
}
```

### Step 4: Push to Repository
```bash
# Add all files
git add .

# Make your first commit
git commit -m "Initial commit - Add installation files"

# Configure your identity if not already done
git config user.name "Your Name"
git config user.email "your.email@domain.com"

# Push to main branch
git push -u origin main
```

### Step 5: Verify Application
1. Run the Application Installer
2. Your new application should appear in the list
3. Select it and test the installation
4. Verify everything works as expected

### Best Practices
- Always test your PowerShell scripts before pushing
- Use clear messages in your scripts for end users
- Document prerequisites in the application description
- Version your application correctly in `app.json`
- Add tests when relevant to verify installation

### Troubleshooting
- If application doesn't appear: check that repository is in the correct organization
- If installation fails: check logs in `%APPDATA%\AppInstaller\Logs\`
- If scripts don't execute: verify permissions and PowerShell syntax