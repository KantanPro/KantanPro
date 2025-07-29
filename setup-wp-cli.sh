#!/bin/bash
# WP-CLI setup script for KTPWP project
# This script downloads WP-CLI if it doesn't exist

WP_CLI_VERSION="2.8.1"
WP_CLI_URL="https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"
WP_CLI_FILE="wp-cli.phar"

echo "🚀 Setting up WP-CLI for KTPWP project..."

# Check if wp-cli.phar already exists
if [ -f "$WP_CLI_FILE" ]; then
    echo "✅ WP-CLI already exists at $WP_CLI_FILE"
    echo "ℹ️  Current version:"
    php "$WP_CLI_FILE" --version
    exit 0
fi

echo "📥 Downloading WP-CLI v$WP_CLI_VERSION..."

# Download WP-CLI
if command -v curl >/dev/null 2>&1; then
    curl -L "$WP_CLI_URL" -o "$WP_CLI_FILE"
elif command -v wget >/dev/null 2>&1; then
    wget "$WP_CLI_URL" -O "$WP_CLI_FILE"
else
    echo "❌ Error: Neither curl nor wget is available. Please install one of them."
    exit 1
fi

# Check if download was successful
if [ ! -f "$WP_CLI_FILE" ]; then
    echo "❌ Error: Failed to download WP-CLI"
    exit 1
fi

# Make executable
chmod +x "$WP_CLI_FILE"

echo "✅ WP-CLI v$WP_CLI_VERSION downloaded successfully!"
echo "🔧 Testing WP-CLI installation..."

# Test WP-CLI
if php "$WP_CLI_FILE" --version >/dev/null 2>&1; then
    echo "✅ WP-CLI is working correctly!"
    php "$WP_CLI_FILE" --version
else
    echo "❌ Error: WP-CLI is not working properly"
    exit 1
fi

echo ""
echo "🎉 Setup complete! You can now use WP-CLI with:"
echo "   source wp-cli-aliases.sh"
echo "   wp-version"
echo ""
echo "📖 See QUICK-START.md for usage instructions"
