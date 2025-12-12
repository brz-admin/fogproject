# Gitea Snapin Auto-Update Script

This script automates the process of checking for and applying updates to Gitea-based snapins in FOG Project.

## Overview

The `gitea_snapin_auto_update.php` script can be run manually or scheduled via cron to automatically:

1. Check if auto-refresh is enabled
2. Verify if it's time to run based on the configured interval
3. Fetch the latest repository information from Gitea
4. Detect updates to existing snapins
5. Import new repositories as snapins
6. Update the last check timestamp

## Requirements

- FOG Project 1.5.10+
- PHP CLI
- Properly configured Gitea snapin integration
- Auto-refresh enabled in FOG settings

## Installation

### Manual Setup

1. **Copy the script** to your FOG server:
   ```bash
   cp gitea_snapin_auto_update.php /opt/fog/utils/
   ```

2. **Make it executable**:
   ```bash
   chmod +x /opt/fog/utils/gitea_snapin_auto_update.php
   ```

3. **Test the script**:
   ```bash
   php /opt/fog/utils/gitea_snapin_auto_update.php
   ```

### Cron Setup

To run the script automatically, set up a cron job:

1. **Edit the crontab**:
   ```bash
   crontab -e
   ```

2. **Add the following line** (runs every hour):
   ```bash
   0 * * * * php /opt/fog/utils/gitea_snapin_auto_update.php >> /var/log/fog/gitea_auto_update.log 2>&1
   ```

3. **For more frequent checks** (every 15 minutes):
   ```bash
   */15 * * * * php /opt/fog/utils/gitea_snapin_auto_update.php >> /var/log/fog/gitea_auto_update.log 2>&1
   ```

## Configuration

The script uses the following FOG settings:

- **FOG_GITEA_AUTO_REFRESH**: Enable/disable automatic updates (1=enabled, 0=disabled)
- **FOG_GITEA_REFRESH_INTERVAL**: How often to check for updates in seconds (default: 86400 = 24 hours)
- **FOG_GITEA_LAST_CHECK**: Timestamp of the last update check

### Configure via FOG Web Interface

1. Go to **FOG Configuration** > **FOG Settings**
2. Find the Gitea snapin settings:
   - **FOG_GITEA_AUTO_REFRESH**: Check to enable automatic updates
   - **FOG_GITEA_REFRESH_INTERVAL**: Set the interval in seconds

## Usage

### Manual Execution

```bash
php /opt/fog/utils/gitea_snapin_auto_update.php
```

### Command Line Options

The script currently doesn't accept command line arguments, but you can modify the behavior by:

- **Force run** (ignore interval): Temporarily set `FOG_GITEA_LAST_CHECK` to 0 in the database
- **Dry run**: Modify the script to add a `--dry-run` flag
- **Verbose output**: Modify the script to add a `--verbose` flag

## How It Works

### Execution Flow

1. **Check if enabled**: Verifies `FOG_GITEA_AUTO_REFRESH` is set to 1
2. **Check interval**: Compares current time with `FOG_GITEA_LAST_CHECK`
3. **Fetch repositories**: Calls Gitea API to get latest repository list
4. **Detect changes**: Compares with existing snapins to find updates
5. **Apply updates**: Updates existing snapins and imports new ones
6. **Update timestamp**: Sets `FOG_GITEA_LAST_CHECK` to current time

### Update Detection

The script detects two types of updates:

1. **Metadata Updates**: When `app.json` changes (e.g., name, description)
2. **New Repositories**: Repositories that haven't been imported yet

### Update Application

For each detected update:
- **Metadata updates**: Updates the snapin's name and description
- **New repositories**: Imports as new snapins using the standard import process

## Logging

### Log Location

The script outputs to stdout, which can be redirected to a log file:

```bash
php /opt/fog/utils/gitea_snapin_auto_update.php >> /var/log/fog/gitea_auto_update.log 2>&1
```

### Log Rotation

Add to `/etc/logrotate.d/fog`:

```
/var/log/fog/gitea_auto_update.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 root root
}
```

## Troubleshooting

### Common Issues

**Issue: Script doesn't run**
- Check cron service is running: `systemctl status cron`
- Verify script path in cron job
- Check script permissions

**Issue: Updates not detected**
- Verify Gitea server and organization settings
- Check repository contains valid `app.json`
- Test API access manually

**Issue: Updates not applied**
- Check FOG database permissions
- Verify snapin creation permissions
- Review FOG logs for errors

### Debugging

1. **Run manually**: Execute the script directly to see output
2. **Check FOG logs**: Review `/var/log/fog/fog.log`
3. **Test API access**:
   ```bash
   curl -v https://your-gitea-server/api/v1/orgs/your-org/repos
   ```
4. **Verify settings**:
   ```sql
   SELECT * FROM fogSettings WHERE settingKey LIKE 'FOG_GITEA%';
   ```

## Security Considerations

### Script Permissions

- Run as the FOG web user (typically `www-data` or `apache`)
- Ensure script has read access to FOG configuration
- Limit write permissions to necessary directories only

### API Access

- The script uses the same API access as the web interface
- No additional authentication is required for public repositories
- For private repositories, configure Gitea access tokens

### Logging

- Log files may contain sensitive information
- Restrict log file permissions
- Consider log rotation for disk space management

## Best Practices

### Scheduling

- **Off-peak hours**: Schedule during low-usage periods
- **Frequency**: Balance between freshness and server load
- **Staggered runs**: If running multiple FOG servers, stagger cron jobs

### Monitoring

- **Log monitoring**: Set up alerts for errors in the log file
- **Success tracking**: Monitor the number of updates applied
- **Performance impact**: Watch for increased load during updates

### Maintenance

- **Regular testing**: Periodically test the script manually
- **Backup settings**: Backup FOG settings before major changes
- **Update script**: Keep the script updated with FOG upgrades

## Example Output

### Successful Run (No Updates)

```
Starting Gitea snapin auto-update...
Checking for updates...
Found 0 updates and 0 new repositories.
All snapins are up to date.
```

### Successful Run (With Updates)

```
Starting Gitea snapin auto-update...
Checking for updates...
Found 2 updates and 1 new repositories.
Applying updates...
2 updates applied, 1 new snapins imported
Auto-update completed successfully.
```

### Error Output

```
Starting Gitea snapin auto-update...
Checking for updates...
Error checking for updates: Failed to fetch repositories. HTTP Code: 404
```

## Integration with FOG

### Database Settings

The script interacts with these database tables:

- `fogSettings`: Reads/writes configuration settings
- `snapins`: Updates existing snapins
- `snapinGroupAssociation`: Manages snapin storage groups

### Web Interface

The auto-update functionality is controlled through:

- **FOG Settings**: Configuration of auto-refresh parameters
- **Gitea Snapin Management**: Manual update checking and application
- **Snapin Management**: Viewing and managing updated snapins

## Future Enhancements

### Potential Features

1. **Email notifications**: Send update summaries via email
2. **Webhook support**: Trigger updates via Gitea webhooks
3. **Detailed logging**: More comprehensive logging options
4. **Rollback capability**: Ability to revert updates
5. **Pre/post hooks**: Custom scripts before/after updates

### Performance Improvements

1. **Caching**: Cache repository lists to reduce API calls
2. **Batch processing**: Process updates in batches
3. **Parallel processing**: Update multiple snapins simultaneously
4. **Delta updates**: Only fetch changed repositories

## Support

For issues with the auto-update script:

1. **Check logs**: Review script output and FOG logs
2. **Test manually**: Run the script with verbose output
3. **Verify configuration**: Double-check all settings
4. **Consult documentation**: Review this README and FOG documentation
5. **Community support**: FOG forums and community resources

## License

This script is part of the FOG Project and is licensed under the GPLv3 license.
