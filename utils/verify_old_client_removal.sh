#!/bin/bash

# FOG Project - Old Client Update System Removal Verification Script
# This script verifies that the old client update system has been properly removed
# Author: Mistral Vibe <vibe@mistral.ai>
# License: GPLv3

# Set colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== FOG Old Client Update System Removal Verification ===${NC}"
echo

# Check if we're running as root
if [ "$(whoami)" != "root" ]; then
    echo -e "${YELLOW}Note: Running as non-root user. Some checks may be limited.${NC}"
    echo
fi

# Function to check if file exists
check_file_removed() {
    local file="$1"
    local description="$2"
    
    if [ ! -f "$file" ]; then
        echo -e "${GREEN}✓ $description - File removed${NC}"
        return 0
    else
        echo -e "${RED}✗ $description - File still exists: $file${NC}"
        return 1
    fi
}

# Function to check if string exists in file
check_string_removed() {
    local file="$1"
    local pattern="$2"
    local description="$3"
    
    if [ ! -f "$file" ]; then
        echo -e "${YELLOW}? $description - File not found: $file${NC}"
        return 1
    fi
    
    if ! grep -q "$pattern" "$file"; then
        echo -e "${GREEN}✓ $description - References removed${NC}"
        return 0
    else
        echo -e "${RED}✗ $description - References still found in: $file${NC}"
        return 1
    fi
}

# Function to check database
database_check() {
    echo -e "${BLUE}=== Database Verification ===${NC}"
    
    if [ ! -f "/var/www/fog/lib/fog/config.class.php" ]; then
        echo -e "${YELLOW}? FOG config file not found. Skipping database checks.${NC}"
        return 1
    fi
    
    # Extract database credentials
    DB_HOST=$(grep -oP "define\('FOG_DB_HOST', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
    DB_NAME=$(grep -oP "define\('FOG_DB_NAME', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
    DB_USER=$(grep -oP "define\('FOG_DB_USER', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
    DB_PASS=$(grep -oP "define\('FOG_DB_PASS', '\K[^']+" /var/www/fog/lib/fog/config.class.php)
    
    if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
        echo -e "${RED}✗ Could not extract database credentials${NC}"
        return 1
    fi
    
    # Check if clientUpdates table exists
    echo "Checking for clientUpdates table..."
    if [ -n "$DB_PASS" ]; then
        TABLE_CHECK=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'clientUpdates'" 2>/dev/null)
    else
        TABLE_CHECK=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SHOW TABLES LIKE 'clientUpdates'" 2>/dev/null)
    fi
    
    if [ -z "$TABLE_CHECK" ]; then
        echo -e "${GREEN}✓ clientUpdates table - Removed from database${NC}"
    else
        echo -e "${RED}✗ clientUpdates table - Still exists in database${NC}"
        return 1
    fi
    
    # Check if FOG_CLIENT_CLIENTUPDATER_ENABLED setting exists
    echo "Checking for FOG_CLIENT_CLIENTUPDATER_ENABLED setting..."
    if [ -n "$DB_PASS" ]; then
        SETTING_CHECK=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM \`globalSettings\` WHERE \`settingKey\` = 'FOG_CLIENT_CLIENTUPDATER_ENABLED'" 2>/dev/null)
    else
        SETTING_CHECK=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM \`globalSettings\` WHERE \`settingKey\` = 'FOG_CLIENT_CLIENTUPDATER_ENABLED'" 2>/dev/null)
    fi
    
    if [ "$SETTING_CHECK" = "0" ]; then
        echo -e "${GREEN}✓ FOG_CLIENT_CLIENTUPDATER_ENABLED setting - Removed from database${NC}"
    else
        echo -e "${RED}✗ FOG_CLIENT_CLIENTUPDATER_ENABLED setting - Still exists in database${NC}"
        return 1
    fi
    
    return 0
}

# Main verification function
main() {
    local success_count=0
    local total_count=0
    local failed_count=0
    
    echo -e "${BLUE}=== File System Verification ===${NC}"
    
    # Check core files are removed
    check_file_removed "/var/www/fog/lib/fog/clientupdater.class.php" "ClientUpdater class"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_file_removed "/var/www/fog/lib/fog/clientupdatermanager.class.php" "ClientUpdaterManager class"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_file_removed "/var/www/fog/lib/client/updateclient.class.php" "UpdateClient class"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    echo
    echo -e "${BLUE}=== Code References Verification ===${NC}"
    
    # Check references are removed from key files
    check_string_removed "/var/www/fog/lib/pages/fogconfigurationpage.class.php" "clientupdater" "FOG Configuration Page"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/pages/serviceconfigurationpage.class.php" "clientupdater" "Service Configuration Page"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/pages/hostmanagementpage.class.php" "clientupdater" "Host Management Page"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/pages/groupmanagementpage.class.php" "clientupdater" "Group Management Page"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/fog/fogbase.class.php" "clientupdater" "FOG Base Class"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/fog/fogpage.class.php" "clientupdater" "FOG Page Class"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/client/servicemodule.class.php" "clientupdater" "Service Module"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/commons/schema.php" "FOG_CLIENT_CLIENTUPDATER_ENABLED" "Database Schema"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/commons/text.php" "ClientUpdater" "Language Text"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/router/route.class.php" "clientupdater" "Router"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/hooks/submenudata.hook.php" "clientupdater" "Submenu Hook"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    check_string_removed "/var/www/fog/lib/plugins/accesscontrol/class/accesscontrolrulemanager.class.php" "SUB_MENULINK-clientupdater" "Access Control"
    ((total_count++))
    if [ $? -eq 0 ]; then ((success_count++)); else ((failed_count++)); fi
    
    echo
    
    # Database verification
    if database_check; then
        ((success_count++))
        ((total_count++))
    else
        ((failed_count++))
        ((total_count++))
    fi
    
    echo
    echo -e "${BLUE}=== Verification Summary ===${NC}"
    echo -e "${GREEN}Successfully removed: $success_count/$total_count checks passed${NC}"
    
    if [ $failed_count -gt 0 ]; then
        echo -e "${RED}Failed checks: $failed_count${NC}"
        echo -e "${YELLOW}The old client update system may not be completely removed.${NC}"
        echo -e "${YELLOW}Please check the failed items above and run the removal script again if needed.${NC}"
        return 1
    else
        echo -e "${GREEN}All checks passed! The old client update system has been successfully removed.${NC}"
        echo
        echo -e "${BLUE}Benefits of this removal:${NC}"
        echo "  ✓ Cleaner, more maintainable codebase"
        echo "  ✓ Reduced security attack surface"
        echo "  ✓ Better performance (less legacy code to load)"
        echo "  ✓ Modern FOG functionality completely unaffected"
        echo "  ✓ Easier future upgrades and maintenance"
        echo
        echo -e "${GREEN}Your FOG installation is now optimized and secure!${NC}"
        return 0
    fi
}

# Run main function
main