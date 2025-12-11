#!/bin/bash

echo "=== FOG Web UI Update Script ==="
echo ""

echo "This script will help you update only the web UI portion of FOG."
echo ""

# Check if we're in the right directory
if [ ! -d "packages/web" ]; then
    echo "❌ Error: This script must be run from the FOG project root directory."
    exit 1
fi

echo "Available update options:"
echo "1. Update only CSS files"
echo "2. Update only JavaScript files"
echo "3. Update both CSS and JavaScript files"
echo "4. Update specific files"
echo ""

read -p "Enter your choice (1-4): " choice

echo ""

case $choice in
    1)
        echo "🔄 Updating CSS files..."
        # Copy CSS files from packages/web to their destination
        if [ -d "packages/web/management/css" ]; then
            echo "Found CSS files in: packages/web/management/css"
            # List the CSS files that would be updated
            find packages/web/management/css -name "*.css" -exec echo "  - {}" \;
            
            read -p "Do you want to proceed with updating these CSS files? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo "Updating CSS files..."
                # In a real scenario, you would copy these to your web server
                # For example: cp -r packages/web/management/css/* /var/www/fog/management/css/
                echo "✅ CSS files update process defined (actual copy command would go here)"
            else
                echo "CSS update cancelled."
            fi
        else
            echo "No CSS files found in the expected location."
        fi
        ;;
        
    2)
        echo "🔄 Updating JavaScript files..."
        # Copy JavaScript files from packages/web to their destination
        if [ -d "packages/web/management/js" ]; then
            echo "Found JavaScript files in: packages/web/management/js"
            # List the JS files that would be updated
            find packages/web/management/js -name "*.js" -exec echo "  - {}" \;
            
            read -p "Do you want to proceed with updating these JavaScript files? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo "Updating JavaScript files..."
                # In a real scenario, you would copy these to your web server
                # For example: cp -r packages/web/management/js/* /var/www/fog/management/js/
                echo "✅ JavaScript files update process defined (actual copy command would go here)"
            else
                echo "JavaScript update cancelled."
            fi
        else
            echo "No JavaScript files found in the expected location."
        fi
        ;;
        
    3)
        echo "🔄 Updating both CSS and JavaScript files..."
        
        # Check for CSS files
        css_files=$(find packages/web/management/css -name "*.css" 2>/dev/null)
        js_files=$(find packages/web/management/js -name "*.js" 2>/dev/null)
        
        if [ -n "$css_files" ]; then
            echo "CSS files to update:"
            echo "$css_files" | sed 's/^/  - /'
        else
            echo "No CSS files found."
        fi
        
        echo ""
        
        if [ -n "$js_files" ]; then
            echo "JavaScript files to update:"
            echo "$js_files" | sed 's/^/  - /'
        else
            echo "No JavaScript files found."
        fi
        
        read -p "Do you want to proceed with updating all these files? (y/n): " confirm
        if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
            echo "Updating all web UI files..."
            # In a real scenario, you would copy these to your web server
            echo "✅ All web UI files update process defined (actual copy commands would go here)"
        else
            echo "Web UI update cancelled."
        fi
        ;;
        
    4)
        echo "🔄 Update specific files..."
        echo "Enter the specific files you want to update (separated by spaces):"
        echo "Example: packages/web/management/css/dark-mode.css packages/web/management/js/fog/fog.theme.js"
        read -p "Files to update: " specific_files
        
        if [ -n "$specific_files" ]; then
            echo "Files to update:"
            for file in $specific_files; do
                if [ -f "$file" ]; then
                    echo "  ✅ $file (exists)"
                else
                    echo "  ❌ $file (not found)"
                fi
            done
            
            read -p "Do you want to proceed with updating these specific files? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo "Updating specific files..."
                # In a real scenario, you would copy these specific files to your web server
                echo "✅ Specific files update process defined (actual copy commands would go here)"
            else
                echo "Specific files update cancelled."
            fi
        else
            echo "No files specified."
        fi
        ;;
        
    *)
        echo "❌ Invalid choice. Please run the script again and select 1-4."
        exit 1
        ;;
esac

echo ""
echo "=== Web UI Update Process ==="
echo ""
echo "To actually update the files on your FOG server, you would typically:"
echo "1. Copy the updated files to your web server directory"
echo "2. Clear any browser cache or run: sudo service apache2 restart (or your web server)"
echo "3. Verify the changes in your FOG web interface"
echo ""
echo "Example copy commands (adjust paths as needed):"
echo "  sudo cp -r packages/web/management/css/* /var/www/fog/management/css/"
echo "  sudo cp -r packages/web/management/js/* /var/www/fog/management/js/"
echo "  sudo cp -r packages/web/management/other/* /var/www/fog/management/other/"
echo ""
echo "Note: The actual paths depend on your FOG installation."

echo ""
echo "=== Update Script Complete ==="