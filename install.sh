#!/bin/bash
#
# POD Aggregator — Quick Install Script
# Run this on your 1Panel WordPress server as root
#
# Usage:
#   curl -sL https://raw.githubusercontent.com/gringemini-ship-it/pod-aggregator/master/install.sh | bash
#

set -e

REPO="https://github.com/gringemini-ship-it/pod-aggregator.git"

echo "[POD Aggregator] Starting installation..."

# Find the WordPress plugins directory
if [ -d "/www/wwwroot" ]; then
    PLUGIN_TARGET=$(find /www/wwwroot -name "plugins" -type d 2>/dev/null | grep -v "mu-plugins" | head -1)
fi

if [ -z "$PLUGIN_TARGET" ]; then
    echo "[ERROR] Could not find WordPress plugins directory."
    exit 1
fi

echo "[POD Aggregator] Found plugins directory: $PLUGIN_TARGET"

# Remove existing installation if present
if [ -d "$PLUGIN_TARGET/pod-aggregator" ]; then
    echo "[POD Aggregator] Removing existing installation..."
    rm -rf "$PLUGIN_TARGET/pod-aggregator"
fi

# Clone from public repo
echo "[POD Aggregator] Cloning repository..."
git clone --depth=1 "$REPO" "$PLUGIN_TARGET/pod-aggregator"

echo "[POD Aggregator] Cloned successfully!"

# Install Composer dependencies (generates vendor/autoload.php for class autoloading).
if command -v composer &> /dev/null; then
    echo "[POD Aggregator] Installing Composer dependencies..."
    cd "$PLUGIN_TARGET/pod-aggregator"
    composer install --no-dev --no-interaction --quiet
    cd - > /dev/null
else
    echo "[POD Aggregator] WARNING: Composer not found — classes will be loaded manually."
    echo "  Install Composer (https://getcomposer.org) for PSR-4 autoloading."
fi

# Set permissions
chown -R www-data:www-data "$PLUGIN_TARGET/pod-aggregator"
chmod -R 755 "$PLUGIN_TARGET/pod-aggregator"

echo ""
echo "[POD Aggregator] Installation complete!"
echo ""
echo "Next steps:"
echo "  1. Log into WordPress network admin"
echo "  2. Go to My Sites → Network Admin → Plugins"
echo "  3. Find 'POD Aggregator' and click 'Network Activate'"
echo "  4. Go to POD Aggregator → Settings and enter your Printful API key"
echo ""
