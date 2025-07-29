#!/bin/zsh
# WP-CLI aliases for KTPWP project
# Source this file in your shell: source wp-cli-aliases.sh

# Base WP-CLI command
WP_BASE="php /Users/kantanpro/Desktop/ktpwp/wordpress/wp-content/plugins/KantanPro/wp-cli.phar --path=/Users/kantanpro/Desktop/ktpwp/wordpress"
# Basic aliases
alias wp="$WP_BASE"
alias wpcli="$WP_BASE"

# Common WordPress operations
alias wp-version="$WP_BASE core version"
alias wp-plugins="$WP_BASE plugin list"
alias wp-themes="$WP_BASE theme list"
alias wp-users="$WP_BASE user list"
alias wp-options="$WP_BASE option list"
alias wp-flush="$WP_BASE cache flush && $WP_BASE rewrite flush"

# Database operations
alias wp-dbsize="$WP_BASE db size"
alias wp-dbcheck="$WP_BASE db check"
alias wp-dboptimize="$WP_BASE db optimize"

# Plugin management
alias wp-plugin-active="$WP_BASE plugin list --status=active"
alias wp-plugin-inactive="$WP_BASE plugin list --status=inactive"

# Development helpers
alias wp-debug-on="$WP_BASE config set WP_DEBUG true --raw"
alias wp-debug-off="$WP_BASE config set WP_DEBUG false --raw"

# Show available aliases
wp-help-aliases() {
    echo "Available WP-CLI aliases:"
    echo "  wp                  - Base WP-CLI command"
    echo "  wp-version         - Show WordPress version"
    echo "  wp-plugins         - List all plugins"
    echo "  wp-themes          - List all themes"
    echo "  wp-users           - List all users"
    echo "  wp-options         - List all options"
    echo "  wp-flush           - Clear cache and rewrite rules"
    echo "  wp-dbsize          - Show database size"
    echo "  wp-dbcheck         - Check database"
    echo "  wp-dboptimize      - Optimize database"
    echo "  wp-plugin-active   - List active plugins"
    echo "  wp-plugin-inactive - List inactive plugins"
    echo "  wp-debug-on        - Enable debug mode"
    echo "  wp-debug-off       - Disable debug mode"
    echo "  wp-help-aliases    - Show this help message"
}

echo "WP-CLI aliases loaded! Type 'wp-help-aliases' to see all available commands."
