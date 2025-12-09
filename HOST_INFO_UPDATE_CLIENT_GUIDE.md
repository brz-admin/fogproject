# FOG Host Information Update Client Implementation Guide

This guide explains how to implement client-side support for the new Host Information Update feature in FOG.

## Overview

The Host Information Update feature allows FOG clients to report their current hostname and IP address to the server, ensuring that the FOG database stays synchronized with the actual client state.

## Client Implementation Requirements

### 1. Client Module Integration

The client needs to implement support for the `hostinfoupdater` module. This involves:

#### For C++/C# Clients:

```cpp
// Example C++ implementation
#include <string>
#include <vector>

class HostInfoUpdater {
public:
    std::string getModuleName() const {
        return "hostinfoupdater";
    }
    
    std::string collectHostInfo() {
        // Get current hostname
        char hostname[256];
        gethostname(hostname, sizeof(hostname));
        
        // Get current IP address
        // Implementation depends on your network stack
        std::string ip = getLocalIPAddress();
        
        // Format the data for sending
        return "hostname=" + std::string(hostname) + "&ip=" + ip;
    }
    
    bool sendUpdate(const std::string& fogServer, const std::string& macAddress) {
        std::string url = fogServer + "/fog/service/hostinfoupdate.php?mac=" + macAddress;
        std::string postData = collectHostInfo();
        
        // Use your HTTP client to send the request
        HTTPClient client;
        std::string response = client.post(url, postData);
        
        // Parse response - should start with "#!ok" on success
        return response.find("#!ok") == 0;
    }
};
```

#### For PowerShell Clients:

```powershell
function Invoke-HostInfoUpdate {
    param (
        [string]$FogServer,
        [string]$MacAddress
    )
    
    # Get current hostname
    $hostname = hostname
    
    # Get current IP address
    $ip = (Test-Connection -ComputerName $env:COMPUTERNAME -Count 1).Address
    
    # Build the URL
    $url = "$FogServer/fog/service/hostinfoupdate.php?mac=$MacAddress&hostname=$hostname&ip=$ip"
    
    try {
        $response = Invoke-WebRequest -Uri $url -Method Get -UseBasicParsing
        
        if ($response.Content -like "#!ok*") {
            Write-Output "Host information update successful"
            return $true
        } else {
            Write-Output "Host information update failed: $($response.Content)"
            return $false
        }
    } catch {
        Write-Output "Error updating host information: $_"
        return $false
    }
}
```

### 2. Service Endpoint Details

**Endpoint URL:** `/fog/service/hostinfoupdate.php`

**Required Parameters:**
- `mac` - The MAC address of the host (for authentication)
- `hostname` - The current hostname of the client
- `ip` - The current IP address of the client

**Optional Parameters:**
- None currently

**Response Format:**
- Success: `#!ok` followed by update message
- Error: `#!error` followed by error message

### 3. Data Collection Requirements

The client must be able to collect:

1. **Hostname**: The current computer name
   - Windows: `hostname` command or `Environment.GetEnvironmentVariable("COMPUTERNAME")`
   - Linux: `hostname` command or `/etc/hostname` file
   - macOS: `scutil --get ComputerName`

2. **IP Address**: The primary network interface IP
   - Should be the IP used to communicate with the FOG server
   - Should exclude loopback (127.0.0.1) and link-local addresses
   - Prefer IPv4 for compatibility

### 4. Scheduling Recommendations

- **Frequency**: Every 1-4 hours (adjust based on network conditions)
- **Initial Delay**: 5-10 minutes after client startup
- **Retry Logic**: If update fails, retry after 30 minutes
- **Offline Handling**: Queue updates when offline, send when connection restored

### 5. Error Handling

Clients should handle these potential error conditions:

- **Network Unavailable**: Queue the update for later
- **Server Unreachable**: Retry with exponential backoff
- **Authentication Failed**: Log error, don't retry immediately
- **Rate Limited**: Respect the cooldown period (1 hour)
- **Invalid Data**: Validate data before sending

### 6. Module Configuration

The `hostinfoupdater` module must be enabled for each host in the FOG web interface:

1. Navigate to the host in FOG web UI
2. Go to "Module Configuration"
3. Enable "Host Info Updater" module
4. Save changes

### 7. Security Considerations

- Use HTTPS if available
- Validate server certificate (if using HTTPS)
- Don't store sensitive data in logs
- Handle timeouts appropriately
- Implement proper error logging

## Example Implementation Flow

```
1. Client starts
2. Wait 5 minutes (initial delay)
3. Collect current hostname and IP
4. Send to FOG server
5. If success:
   - Log success
   - Schedule next update in 4 hours
6. If failure:
   - Log error
   - Retry in 30 minutes
   - After 3 failures, wait 4 hours
```

## Validation Rules

The server will validate:
- **Hostname**: Only alphanumeric, hyphens, and underscores (a-z, A-Z, 0-9, -, _)
- **IP Address**: Valid IPv4 or IPv6 format
- **MAC Address**: Must match a registered host
- **Module Enabled**: Host must have the module enabled
- **Rate Limiting**: Maximum 3 updates per hour per host

## Testing Your Implementation

You can test the service endpoint manually:

```bash
# Test with curl
curl "http://your-fog-server/fog/service/hostinfoupdate.php?mac=00:11:22:33:44:55&hostname=test-pc&ip=192.168.1.100"
```

Expected successful response:
```
#!ok
Host information is already up to date
```

Or if changes were made:
```
#!ok
Hostname updated from old-name to test-pc. IP address updated from 192.168.1.50 to 192.168.1.100.
```

## Troubleshooting

**Common Issues:**
- **Module not enabled**: Enable the module in FOG web interface
- **Invalid MAC**: Use the correct MAC address registered in FOG
- **Rate limited**: Wait 1 hour before trying again
- **Invalid hostname/IP**: Check your validation logic
- **Network issues**: Verify connectivity to FOG server

**Debugging:**
- Check FOG server logs for detailed error messages
- Verify the host exists in FOG database
- Confirm the module is enabled for the host
- Test with simple tools like curl first

## Compatibility

- Works with FOG 1.5.10+
- Supports both legacy and JSON client protocols
- Compatible with all major operating systems
- No database schema changes required

## Performance Considerations

- Minimal server impact (simple database updates)
- Lightweight network traffic
- Designed for frequent updates without performance degradation
- Includes rate limiting to prevent abuse

This implementation provides a robust way for FOG clients to keep their host information synchronized with the server, improving inventory accuracy and management capabilities.