/**
 * FOG Theme Management
 *
 * Handles dark/light theme switching and persistence
 *
 * @category FOGProject
 * @package  FOGProject
 * @author   Mistral Vibe
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

(function($) {
    'use strict';

    // Theme management class
    var FOGTheme = {
        
        // Initialize theme management
        init: function() {
            this.setupThemeToggle();
            this.loadThemePreference();
            this.setupThemePersistence();
        },
        
        // Setup the theme toggle button
        setupThemeToggle: function() {
            // Create theme toggle button as a list item for footer
            var toggleButton = $('<li class="dark-mode-toggle-li pull-right">')
                .append($('<button type="button" class="btn btn-default dark-mode-toggle-btn">')
                    .append($('<i class="fa fa-moon-o">'))
                    .append(' <span class="toggle-text">Dark Mode</span>'));
            
            // Add to footer navbar next to version number
            var footerNav = $('footer.footer nav.navbar ul.nav');
            if (footerNav.length) {
                // Find the version li (pull-right) and insert before it
                var versionLi = footerNav.find('li.pull-right');
                if (versionLi.length) {
                    versionLi.before(toggleButton);
                } else {
                    // If no version li, add to end
                    footerNav.append(toggleButton);
                }
            } else {
                // Fallback to footer if navbar structure doesn't exist
                var footer = $('footer.footer');
                if (footer.length) {
                    footer.prepend(toggleButton);
                } else {
                    // Final fallback to body
                    $('body').append(toggleButton);
                }
            }
            
            // Toggle functionality
            toggleButton.find('button').on('click', function() {
                FOGTheme.toggleTheme();
            });
        },
        
        // Toggle between dark and light themes
        toggleTheme: function() {
            $('body').toggleClass('dark-mode');
            
            // Update button text based on current theme
            var isDarkMode = $('body').hasClass('dark-mode');
            var toggleButton = $('.dark-mode-toggle-li button');
            
            if (isDarkMode) {
                toggleButton.find('i').removeClass('fa-moon-o').addClass('fa-sun-o');
                toggleButton.find('.toggle-text').text('Light Mode');
            } else {
                toggleButton.find('i').removeClass('fa-sun-o').addClass('fa-moon-o');
                toggleButton.find('.toggle-text').text('Dark Mode');
            }
            
            // Save preference
            FOGTheme.saveThemePreference();
        },
        
        // Load theme preference from localStorage
        loadThemePreference: function() {
            var savedTheme = localStorage.getItem('fogThemePreference');
            
            if (savedTheme === 'dark') {
                $('body').addClass('dark-mode');
                var toggleButton = $('.dark-mode-toggle-li button');
                toggleButton.find('i').removeClass('fa-moon-o').addClass('fa-sun-o');
                toggleButton.find('.toggle-text').text('Light Mode');
            }
        },
        
        // Save theme preference to localStorage
        saveThemePreference: function() {
            var isDarkMode = $('body').hasClass('dark-mode');
            localStorage.setItem('fogThemePreference', isDarkMode ? 'dark' : 'light');
        },
        
        // Setup theme persistence across page loads
        setupThemePersistence: function() {
            // Apply theme immediately on page load
            this.loadThemePreference();
        }
    };

    // Initialize theme management when DOM is ready
    $(document).ready(function() {
        FOGTheme.init();
    });

})(jQuery);