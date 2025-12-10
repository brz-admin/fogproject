# GLPI Integration Plugin for FOG

This plugin provides seamless integration between FOG and GLPI inventory management system. It allows you to link FOG hosts with GLPI computers using their serial numbers and synchronize data between the two systems.

## Features

- **Automatic Host ↔ Computer Linking**: Link FOG hosts with GLPI computers based on serial numbers
- **Manual Linking**: Search and manually link hosts to GLPI computers
- **Automatic Synchronization**: Scheduled synchronization to keep data up-to-date
- **Bulk Operations**: Sync multiple hosts at once
- **Status Tracking**: Visual indicators for linked/unlinked hosts
- **Detailed Computer Information**: View GLPI computer details directly in FOG

## Requirements

### FOG Side
- FOG 1.5.10+
- PHP 7.4+
- MySQL 5.7+
- cURL extension enabled

### GLPI Side
- GLPI 10.0+ with REST API enabled
- API access configured
- Sufficient permissions for computer operations
- Unique serial numbers for computers

## Installation

1. **Copy the plugin**: Place the `glpi` directory in `packages/web/lib/plugins/`
2. **Enable the plugin**: The plugin will automatically register with FOG
3. **Configure settings**: Go to FOG Settings → GLPI Integration and configure:
   - GLPI URL (e.g., `https://glpi.example.com`)
   - API Token (with sufficient permissions)
   - Synchronization options

## Configuration

### GLPI API Token Setup

1. Log in to your GLPI instance as an administrator
2. Go to **Setup → General → API**
3. Enable the REST API if not already enabled
4. Create a new API token with the following permissions:
   - `Computer` (read, create, update)
   - `Location` (read)
   - `User` (read)
   - `Manufacturer` (read)
   - `Computer Model` (read)
   - `Operating System` (read)

### FOG Plugin Settings

1. Navigate to **FOG Settings → GLPI Integration**
2. Enter your GLPI URL and API token
3. Test the connection
4. Configure automatic synchronization options (optional)
5. Save settings

## Usage

### Linking a Host to GLPI Computer

1. Navigate to **Host Management → [Select Host] → GLPI Integration**
2. If the host has a serial number, click "Search GLPI for this serial number"
3. Review the search results
4. Click "Link to this computer" to create the mapping

### Manual Linking

1. Go to the host's GLPI Integration tab
2. Click "Search GLPI" and enter search criteria
3. Select the appropriate computer from the results
4. Confirm the linking

### Automatic Synchronization

1. Enable automatic synchronization in plugin settings
2. Set the sync interval (hours)
3. The plugin will automatically sync hosts with GLPI based on the schedule

### Bulk Synchronization

1. Go to **Host Management**
2. Select multiple hosts using checkboxes
3. Use the bulk actions to sync with GLPI

## Troubleshooting

### Connection Issues

- **"Connection failed"**: Verify GLPI URL and API token
- **"Unauthorized"**: Check API token permissions
- **"Not Found"**: Verify GLPI REST API is enabled and accessible

### Serial Number Issues

- **"No serial number found"**: Ensure the host has inventory data with a serial number
- **"No matching computers found"**: Verify the serial number exists in GLPI
- **"Multiple computers found"**: Check for duplicate serial numbers in GLPI

### Synchronization Issues

- **"Sync failed"**: Check FOG and GLPI logs for details
- **"No changes detected"**: Verify data differences between FOG and GLPI
- **"Permission denied"**: Ensure API token has sufficient permissions

## Database Schema

The plugin creates two database tables:

### `fogGlpiSettings`

Stores plugin configuration:
- `glpiUrl`: GLPI base URL
- `apiToken`: GLPI API token
- `syncInterval`: Automatic sync interval (hours)
- `autoSyncEnabled`: Automatic sync enabled flag
- `lastSyncTime`: Last sync timestamp
- `lastSyncStatus`: Last sync status

### `fogGlpiHostMappings`

Stores host ↔ computer mappings:
- `hostID`: FOG host ID
- `glpiComputerID`: GLPI computer ID
- `glpiSerialNumber`: Computer serial number
- `lastSyncTime`: Last sync timestamp
- `syncStatus`: Sync status
- `glpiData`: Cached GLPI computer data (JSON)

## API Reference

### GLPI API Client Methods

- `testConnection()`: Test GLPI connection
- `searchComputersBySerial($serialNumber)`: Search computers by serial
- `getComputer($computerID)`: Get computer details
- `createComputer($computerData)`: Create new computer
- `updateComputer($computerID, $computerData)`: Update computer

### Mapping Service Methods

- `findGlpiComputerForHost($host)`: Find GLPI computer for host
- `syncHostWithGlpi($host, $forceCreate)`: Sync host with GLPI
- `getMappingStatus($hostID)`: Get mapping status
- `bulkSyncHosts($hostIds, $forceCreate)`: Bulk sync hosts

### Sync Service Methods

- `runAutomaticSync($forceSync)`: Run automatic synchronization
- `isSyncNeeded()`: Check if sync is needed
- `getSyncStatus()`: Get synchronization status

## Security Considerations

- **API Token Security**: Store tokens encrypted in database
- **Rate Limiting**: Implement rate limiting for API calls
- **Input Validation**: Validate all inputs and outputs
- **Error Handling**: Graceful error handling and logging
- **Access Control**: FOG role-based permissions for plugin access

## Development

### Extending the Plugin

The plugin provides several extension points:
- **Custom Field Mapping**: Extend `prepareComputerDataFromHost()` to map additional fields
- **Advanced Search**: Extend `searchComputers()` method for complex queries
- **Event Hooks**: Add hooks for pre/post sync events

### Contributing

1. Fork the repository
2. Create a feature branch
3. Implement your changes
4. Write tests
5. Submit a pull request

## Support

For issues and feature requests, please use the FOG Project issue tracker or forums.

## License

This plugin is licensed under the GNU GPLv3 license.