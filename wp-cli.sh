#!/bin/bash
# WP-CLI wrapper script for KTPWP project
# Usage: ./wp-cli.sh [wp-cli commands]

# Set the WordPress path
WP_PATH="/Users/kantanpro/Desktop/ktpwp/wordpress"
WP_CLI_PHAR="/Users/kantanpro/Desktop/ktpwp/wordpress/wp-content/plugins/KantanPro/wp-cli.phar"

# Check if wp-cli.phar exists
if [ ! -f "$WP_CLI_PHAR" ]; then
    echo "Error: wp-cli.phar not found at $WP_CLI_PHAR"
    exit 1
fi

# Check if WordPress directory exists
if [ ! -d "$WP_PATH" ]; then
    echo "Error: WordPress directory not found at $WP_PATH"
    exit 1
fi

# Execute WP-CLI with the specified path
php "$WP_CLI_PHAR" --path="$WP_PATH" "$@"
