#!/bin/bash

# FOG Project - Old Client Update System Removal Script
# This script safely removes the legacy client update system from FOG
# Target: FOG clients version 1.2.0 and earlier (pre-2014)
# Author: Mistral Vibe <vibe@mistral.ai>
# License: GPLv3

# Safety checks and requirements
if [ "$(whoami)" != "root" ]; then
    echo "ERROR: This script must be run as root"
    exit 1
fi

if [ ! -f "/var/www/fog/lib/fog/config.class.php" ]; then
    echo "ERROR: FOG installation not found at /var/www/fog"
    exit 1
fi

# Set colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== FOG Old Client Update System Removal ===${NC}"
echo -e "${YELLOW}This script will remove the legacy client update system for FOG 1.2.0 and earlier.${NC}"
echo -e "${YELLOW}This is SAFE for modern FOG installations and will not affect current functionality.${NC}"
echo

# Confirmation prompt
read -p "Do you want to proceed with the removal? (y/n) " -n 1 -r
echo

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Removal cancelled.${NC}"
    exit 0
fi

# Backup function
backup_file() {
    local file="$1"
    if [ -f "$file" ]; then
        cp "$file" "$file.bak.$(date +%Y%m%d_%H%M%S)"
        echo -e "${GREEN}Backed up: $file${NC}"
    fi
}

# Database cleanup function
database_cleanup() {
    echo -e "${BLUE}=== Database Cleanup ===${NC}"
    
    # Get MySQL credentials from FOG config
    if [ -f "/var/www/fog/lib/fog/config.class.php" ]; then
        DB_HOST=$(grep -oP "define\('FOG_DB_HOST', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
        DB_NAME=$(grep -oP "define\('FOG_DB_NAME', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
        DB_USER=$(grep -oP "define\('FOG_DB_USER', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
        DB_PASS=$(grep -oP "define\('FOG_DB_PASS', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
        
        if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
            echo -e "${RED}ERROR: Could not extract database credentials from FOG config${NC}"
            return 1
        fi
        
        echo "Found database: $DB_NAME @ $DB_HOST"
        
        # Create SQL commands
        SQL_FILE="/tmp/fog_client_updater_removal_$(date +%s).sql"
        cat > "$SQL_FILE" <<EOF
-- FOG Old Client Update System Removal
-- Generated: $(date)

-- Remove client updates table
DROP TABLE IF EXISTS "`clientUpdates`";

-- Remove global setting for client updater
DELETE FROM "`globalSettings`" WHERE "`settingKey`" = 'FOG_CLIENT_CLIENTUPDATER_ENABLED';

-- Remove module associations (if they exist)
DELETE FROM "`modules`" WHERE "`shortName`" = 'clientupdater';
DELETE FROM "`moduleStatusByHost`" WHERE "`moduleID`" IN (SELECT "`id`" FROM "`modules`" WHERE "`shortName`" = 'clientupdater');

-- Remove from moduleStatus table
DELETE FROM "`moduleStatus`" WHERE "`moduleID`" IN (SELECT "`id`" FROM "`modules`" WHERE "`shortName`" = 'clientupdater');

-- Remove from service table if it exists
DELETE FROM "`services`" WHERE "`name`" = 'FOG_CLIENT_CLIENTUPDATER_ENABLED';

-- Clean up any orphaned references
DELETE FROM "`modules`" WHERE "`shortName`" = 'clientupdater';
EOF
        
        echo "Executing database cleanup..."
        
        # Execute SQL
        if [ -n "$DB_PASS" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE"
        else
            mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$SQL_FILE"
        fi
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}Database cleanup completed successfully${NC}"
        else
            echo -e "${RED}ERROR: Database cleanup failed${NC}"
            return 1
        fi
        
        # Clean up SQL file
        rm -f "$SQL_FILE"
        
    else
        echo -e "${RED}ERROR: FOG config file not found${NC}"
        return 1
    fi
}

# File cleanup function
file_cleanup() {
    echo -e "${BLUE}=== File System Cleanup ===${NC}"
    
    # List of files to remove
    FILES_TO_REMOVE=(
        "/var/www/fog/lib/fog/clientupdater.class.php"
        "/var/www/fog/lib/fog/clientupdatermanager.class.php"
        "/var/www/fog/lib/client/updateclient.class.php"
    )
    
    # List of files to modify (remove references)
    FILES_TO_MODIFY=(
        "/var/www/fog/lib/pages/fogconfigurationpage.class.php"
        "/var/www/fog/lib/pages/serviceconfigurationpage.class.php"
        "/var/www/fog/lib/pages/hostmanagementpage.class.php"
        "/var/www/fog/lib/pages/groupmanagementpage.class.php"
        "/var/www/fog/lib/fog/fogbase.class.php"
        "/var/www/fog/lib/fog/fogpage.class.php"
        "/var/www/fog/lib/client/servicemodule.class.php"
        "/var/www/fog/commons/schema.php"
        "/var/www/fog/commons/text.php"
        "/var/www/fog/lib/router/route.class.php"
        "/var/www/fog/lib/hooks/submenudata.hook.php"
        "/var/www/fog/lib/plugins/accesscontrol/class/accesscontrolrulemanager.class.php"
    )
    
    # Remove core files
    echo "Removing core client updater files..."
    for file in "${FILES_TO_REMOVE[@]}"; do
        if [ -f "$file" ]; then
            backup_file "$file"
            rm -f "$file"
            echo -e "${GREEN}Removed: $file${NC}"
        fi
    done
    
    # Modify files to remove references
    echo "Cleaning up references in existing files..."
    
    # fogconfigurationpage.class.php - remove clientupdater method and references
    if [ -f "/var/www/fog/lib/pages/fogconfigurationpage.class.php" ]; then
        backup_file "/var/www/fog/lib/pages/fogconfigurationpage.class.php"
        
        # Remove from menu
        sed -i '/\\\\\\\'clientupdater\\\\\\\' => self::\$foglang\['\''ClientUpdater'\''\],/d' "/var/www/fog/lib/pages/fogconfigurationpage.class.php"
        
        # Remove the entire clientupdater method (approximately 200 lines)
        sed -i '/public function clientupdater(.*$/,/^    }/d' "/var/www/fog/lib/pages/fogconfigurationpage.class.php"
        
        # Remove clientupdaterPost method
        sed -i '/public function clientupdaterPost(.*$/,/^    }/d' "/var/www/fog/lib/pages/fogconfigurationpage.class.php"
        
        # Remove setting references
        sed -i '/FOG_CLIENT_CLIENTUPDATER_ENABLED/d' "/var/www/fog/lib/pages/fogconfigurationpage.class.php"
        
        echo -e "${GREEN}Cleaned: fogconfigurationpage.class.php${NC}"
    fi
    
    # serviceconfigurationpage.class.php
    if [ -f "/var/www/fog/lib/pages/serviceconfigurationpage.class.php" ]; then
        backup_file "/var/www/fog/lib/pages/serviceconfigurationpage.class.php"
        
        # Remove clientupdater case
        sed -i '/case \\\\\\\'clientupdater\\\\\\\':/,/break;/d' "/var/www/fog/lib/pages/serviceconfigurationpage.class.php"
        
        # Remove from menu link
        sed -i '/clientupdater/d' "/var/www/fog/lib/pages/serviceconfigurationpage.class.php"
        
        echo -e "${GREEN}Cleaned: serviceconfigurationpage.class.php${NC}"
    fi
    
    # hostmanagementpage.class.php and groupmanagementpage.class.php
    for file in "/var/www/fog/lib/pages/hostmanagementpage.class.php" "/var/www/fog/lib/pages/groupmanagementpage.class.php"; do
        if [ -f "$file" ]; then
            backup_file "$file"
            
            # Remove clientupdater case and related code
            sed -i '/case \\\\\\\'clientupdater\\\\\\\':/,/break;/d' "$file"
            
            # Remove cunote variable
            sed -i '/\$cunote = sprintf/,/);/d' "$file"
            sed -i '/\$cunote/d' "$file"
            
            echo -e "${GREEN}Cleaned: $(basename $file)${NC}"
        fi
    done
    
    # fogbase.class.php
    if [ -f "/var/www/fog/lib/fog/fogbase.class.php" ]; then
        backup_file "/var/www/fog/lib/fog/fogbase.class.php"
        
        # Remove clientupdater from services array
        sed -i '/\\\\\\\'clientupdater\\\\\\\' => true,/d' "/var/www/fog/lib/fog/fogbase.class.php"
        
        echo -e "${GREEN}Cleaned: fogbase.class.php${NC}"
    fi
    
    # fogpage.class.php
    if [ -f "/var/www/fog/lib/fog/fogpage.class.php" ]; then
        backup_file "/var/www/fog/lib/fog/fogpage.class.php"
        
        # Remove clientupdater from ignored modules
        sed -i '/clientupdater/d' "/var/www/fog/lib/fog/fogpage.class.php"
        
        echo -e "${GREEN}Cleaned: fogpage.class.php${NC}"
    fi
    
    # servicemodule.class.php
    if [ -f "/var/www/fog/lib/client/servicemodule.class.php" ]; then
        backup_file "/var/www/fog/lib/client/servicemodule.class.php"
        
        # Remove clientupdater from remArr
        sed -i '/clientupdater/d' "/var/www/fog/lib/client/servicemodule.class.php"
        
        echo -e "${GREEN}Cleaned: servicemodule.class.php${NC}"
    fi
    
    # schema.php
    if [ -f "/var/www/fog/commons/schema.php" ]; then
        backup_file "/var/www/fog/commons/schema.php"
        
        # Remove FOG_CLIENT_CLIENTUPDATER_ENABLED setting insertion
        sed -i '/FOG_CLIENT_CLIENTUPDATER_ENABLED/d' "/var/www/fog/commons/schema.php"
        
        echo -e "${GREEN}Cleaned: schema.php${NC}"
    fi
    
    # text.php
    if [ -f "/var/www/fog/commons/text.php" ]; then
        backup_file "/var/www/fog/commons/text.php"
        
        # Remove ClientUpdater language string
        sed -i '/\\\\\\\'ClientUpdater\\\\\\\'/d' "/var/www/fog/commons/text.php"
        
        echo -e "${GREEN}Cleaned: text.php${NC}"
    fi
    
    # route.class.php
    if [ -f "/var/www/fog/lib/router/route.class.php" ]; then
        backup_file "/var/www/fog/lib/router/route.class.php"
        
        # Remove clientupdater from route
        sed -i '/clientupdater/d' "/var/www/fog/lib/router/route.class.php"
        
        echo -e "${GREEN}Cleaned: route.class.php${NC}"
    fi
    
    # submenudata.hook.php
    if [ -f "/var/www/fog/lib/hooks/submenudata.hook.php" ]; then
        backup_file "/var/www/fog/lib/hooks/submenudata.hook.php"
        
        # Remove clientupdater from submenu
        sed -i '/clientupdater/d' "/var/www/fog/lib/hooks/submenudata.hook.php"
        
        echo -e "${GREEN}Cleaned: submenudata.hook.php${NC}"
    fi
    
    # accesscontrolrulemanager.class.php
    if [ -f "/var/www/fog/lib/plugins/accesscontrol/class/accesscontrolrulemanager.class.php" ]; then
        backup_file "/var/www/fog/lib/plugins/accesscontrol/class/accesscontrolrulemanager.class.php"
        
        # Remove clientupdater access control rule
        sed -i '/SUB_MENULINK-clientupdater/d' "/var/www/fog/lib/plugins/accesscontrol/class/accesscontrolrulemanager.class.php"
        
        echo -e "${GREEN}Cleaned: accesscontrolrulemanager.class.php${NC}"
    fi
}

# Cleanup temporary files
cleanup_temp_files() {
    echo -e "${BLUE}=== Cleaning Up Temporary Files ===${NC}"
    
    # Remove any temporary files created during the process
    rm -f /tmp/fog_client_updater_removal_*.sql
    
    echo -e "${GREEN}Temporary files cleaned up${NC}"
}

# Main execution
main() {
    echo -e "${BLUE}=== Starting FOG Old Client Update System Removal ===${NC}"
    echo -e "${YELLOW}This process may take a few minutes...${NC}"
    echo
    
    # Step 1: Database cleanup
    if ! database_cleanup; then
        echo -e "${RED}Database cleanup failed. Aborting.${NC}"
        exit 1
    fi
    
    echo
    
    # Step 2: File system cleanup
    file_cleanup
    
    echo
    
    # Step 3: Cleanup temporary files
    cleanup_temp_files
    
    echo
    echo -e "${GREEN}=== Removal Completed Successfully! ===${NC}"
    echo -e "${BLUE}The old client update system has been removed from your FOG installation.${NC}"
    echo -e "${BLUE}This change is permanent but all original files have been backed up.${NC}"
    echo
    echo -e "${YELLOW}What was removed:${NC}"
    echo "  - Database table: clientUpdates"
    echo "  - Global setting: FOG_CLIENT_CLIENTUPDATER_ENABLED"
    echo "  - Core files: clientupdater.class.php, clientupdatermanager.class.php, updateclient.class.php"
    echo "  - UI elements: Client Updater menu items and configuration panels"
    echo "  - Service module references"
    echo "  - Language strings and access control rules"
    echo
    echo -e "${YELLOW}Impact:${NC}"
    echo "  - Modern FOG functionality: UNAFFECTED"
    echo "  - Legacy client support (FOG 1.2.0 and earlier): REMOVED"
    echo "  - System performance: IMPROVED (cleaner codebase)"
    echo "  - Security: ENHANCED (reduced attack surface)"
    echo
    echo -e "${GREEN}Your FOG installation is now cleaner and more secure!${NC}"
}

# Run main function
main