#!/bin/bash

echo "=== FOG Dark Mode Update Script ==="
echo ""
echo "This script updates only the dark mode related files."
echo ""

# Check if we're in the right directory
if [ ! -d "packages/web" ]; then
    echo "❌ Error: This script must be run from the FOG project root directory."
    exit 1
fi

echo "Dark mode files that were modified:"
echo "1. packages/web/management/js/fog/fog.theme.js (toggle placement)"
echo "2. packages/web/management/css/dark-mode.css (table styling)"
echo ""

# Show the specific changes made
echo "Changes made to fog.theme.js:"
echo "  - Dark mode toggle now only appears in footer (not navbar)"
echo "  - Added fallback to body if footer doesn't exist"
echo ""

echo "Changes made to dark-mode.css:"
echo "  - Fixed bright table elements (.tablesorter-bootstrap > tbody > tr.even > td)"
echo "  - Added proper dark background for even rows in tables"
echo ""

read -p "Do you want to update these dark mode files? (y/n): " confirm

if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
    echo ""
    echo "🔄 Updating dark mode files..."
    
    # Check if files exist
    theme_js="packages/web/management/js/fog/fog.theme.js"
    dark_css="packages/web/management/css/dark-mode.css"
    
    if [ -f "$theme_js" ]; then
        echo "✅ Found: $theme_js"
    else
        echo "❌ Not found: $theme_js"
    fi
    
    if [ -f "$dark_css" ]; then
        echo "✅ Found: $dark_css"
    else
        echo "❌ Not found: $dark_css"
    fi
    
    echo ""
    echo "To update these files on your FOG server, you would run:"
    echo ""
    
    # Determine possible web server paths
    echo "Common FOG installation paths:"
    echo "  - /var/www/fog/management/js/fog/fog.theme.js"
    echo "  - /var/www/fog/management/css/dark-mode.css"
    echo "  - /var/www/html/fog/management/js/fog/fog.theme.js"
    echo "  - /var/www/html/fog/management/css/dark-mode.css"
    echo ""
    
    echo "Example update commands:"
    echo "  sudo cp $theme_js /var/www/fog/management/js/fog/fog.theme.js"
    echo "  sudo cp $dark_css /var/www/fog/management/css/dark-mode.css"
    echo ""
    
    echo "After updating, you may need to:"
    echo "  1. Clear browser cache"
    echo "  2. Restart web server: sudo service apache2 restart"
    echo "  3. Refresh FOG web interface"
    
    echo ""
    read -p "Do you want to copy these files to a specific destination? (y/n): " copy_confirm
    
    if [ "$copy_confirm" = "y" ] || [ "$copy_confirm" = "Y" ]; then
        read -p "Enter the destination base path (e.g., /var/www/fog): " dest_path
        
        if [ -d "$dest_path" ]; then
            echo "Copying files to $dest_path..."
            
            # Create directories if they don't exist
            mkdir -p "$dest_path/management/js/fog"
            mkdir -p "$dest_path/management/css"
            
            # Copy the files
            cp "$theme_js" "$dest_path/management/js/fog/fog.theme.js"
            cp "$dark_css" "$dest_path/management/css/dark-mode.css"
            
            echo "✅ Files copied successfully!"
            echo ""
            echo "Updated files:"
            echo "  - $dest_path/management/js/fog/fog.theme.js"
            echo "  - $dest_path/management/css/dark-mode.css"
            
            # Set proper permissions
            echo "Setting proper permissions..."
            chown www-data:www-data "$dest_path/management/js/fog/fog.theme.js"
            chown www-data:www-data "$dest_path/management/css/dark-mode.css"
            chmod 644 "$dest_path/management/js/fog/fog.theme.js"
            chmod 644 "$dest_path/management/css/dark-mode.css"
            
            echo "✅ Permissions set!"
            
        else
            echo "❌ Destination path does not exist: $dest_path"
        fi
    else
        echo "Copy operation cancelled."
    fi
    
else
    echo "Dark mode update cancelled."
fi

echo ""
echo "=== Dark Mode Update Complete ==="