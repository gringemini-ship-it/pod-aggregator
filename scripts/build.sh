#!/usr/bin/env bash
#
# build.sh — Build a WordPress.org-ready release ZIP for POD Aggregator.
#
# Usage:
#   ./scripts/build.sh          # Interactive: asks for version number
#   ./scripts/build.sh 1.2.3    # Non-interactive, uses provided version
#   ./scripts/build.sh check    # Run PHPCS + PHP syntax checks only, no ZIP
#   ./scripts/build.sh help     # Show this help
#
# Output:
#   pod-aggregator/trunk/  — staged files for SVN deployment
#   pod-aggregator.zip     — release ZIP artifact

set -euo pipefail

PLUGIN_SLUG="pod-aggregator"
TRUNK_DIR="pod-aggregator/trunk"
ASSETS_DIR="pod-aggregator/assets"
DIST_FILE="pod-aggregator.zip"

# Files/directories to exclude from the release ZIP
EXCLUDE_PATTERNS=(
    "--exclude=.git*"
    "--exclude=.github*"
    "--exclude=*.md"
    "--exclude=*.txt"
    "--exclude=tests/"
    "--exclude=phpunit*"
    "--exclude=composer.json"
    "--exclude=composer.lock"
    "--exclude=vendor/"
    "--exclude=node_modules/"
    "--exclude=scripts/"
    "--exclude=references/"
    "--exclude=*.map"
    "--exclude=*.log"
    "--exclude=phpcs.xml*"
    "--exclude=.phpcs.xml*"
    "--exclude=.phpunit.result.cache"
    "--exclude=references/"
)

# ----------------------------------------------------------------------
# Helper functions
# ----------------------------------------------------------------------

log()  { echo "[build] $*"; }
warn() { echo "[build] WARNING: $*" >&2; }
die()  { echo "[build] ERROR: $*" >&2; exit 1; }

need() {
    command -v "$1" >/dev/null 2>&1 || die "Required tool not found: $1 (install it or add to PATH)"
}

php_check() {
    need php
    log "Checking PHP syntax..."
    # Find all PHP files, ignoring vendor and .git
    find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*" -print0 | \
        while IFS= read -r -d '' file; do
            result=$(php -l "$file" 2>&1)
            if echo "$result" | grep -qv "No syntax errors"; then
                echo "$result"
                exit 1
            fi
        done
    log "PHP syntax OK"
}

phpcs_check() {
    if ! command -v phpcs >/dev/null 2>&1; then
        warn "PHPCS not found — skipping lint. Run 'composer lint' to install."
        return 0
    fi
    log "Running PHP CodeSniffer..."
    phpcs --standard=WordPress wp-plugin ."$PLUGIN_SLUG" || die "PHPCS found violations"
    log "PHPCS OK"
}

# ----------------------------------------------------------------------
# Main commands
# ----------------------------------------------------------------------

cmd_help() {
    cat <<'EOF'
Usage: ./scripts/build.sh [command|version] [--help]

Commands:
  help        Show this help message
  check       Run PHPCS + PHP syntax checks only (no ZIP)
  <version>   Build a release ZIP for the given version (e.g. 1.2.3)

Examples:
  ./scripts/build.sh          # Interactive build
  ./scripts/build.sh 1.0.0    # Build v1.0.0 release
  ./scripts/build.sh check    # Lint + syntax check only
EOF
}

cmd_check() {
    log "Running pre-build checks..."
    (php_check && phpcs_check)
    log "All checks passed"
}

cmd_build() {
    need zip

    local version="$1"
    [ -z "$version" ] && die "Version number required. Run: $0 <version>"

    # Validate version format (semver-ish)
    if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        die "Invalid version format: $version (expected e.g. 1.2.3)"
    fi

    log "Building POD Aggregator v${version}"

    # Clean up previous build artifacts
    rm -rf "$TRUNK_DIR" "$DIST_FILE"

    # Stage the trunk directory
    log "Staging files into $TRUNK_DIR..."
    mkdir -p "$TRUNK_DIR"

    # Copy plugin files, excluding dev artifacts.
    # IMPORTANT: no trailing slash on directory sources — trailing slash
    # copies only the CONTENTS (flattening subdirectories), while without
    # the slash it copies the directory itself, preserving the tree.
    rsync -a \
        pod-aggregator.php \
        uninstall.php \
        admin \
        includes \
        public \
        assets \
        "$TRUNK_DIR/" \
        "${EXCLUDE_PATTERNS[@]}"

    # Ensure composer.json and composer.lock are excluded from trunk
    # (WordPress.org doesn't use Composer for runtime deps)
    rm -f "$TRUNK_DIR/composer.json" "$TRUNK_DIR/composer.lock"

    # Create the ZIP from trunk/ so files are at the ZIP root
    # (WordPress expects plugin files at root, not inside a trunk/ wrapper).
    log "Creating $DIST_FILE..."
    (cd "$TRUNK_DIR" && zip -r ../../"$DIST_FILE" . -x "\
.git/*" "*.git*" "*.md" "*.txt" "tests/*" "phpunit*" "composer.json" "composer.lock" \
"vendor/*" "node_modules/*" "scripts/*" "references/*" "*.map" "*.log" "phpcs.xml*" ".phpcs.xml*" \
".phpunit.result.cache" 2>/dev/null || \
zip -r ../../"$DIST_FILE" . -x ".git/*" "*.md" "*.txt" "tests/*" "phpunit*" "composer.json" \
"composer.lock" "vendor/*" "node_modules/*" "scripts/*" "references/*" "*.map" "*.log" \
"phpcs.xml*" ".phpcs.xml*" ".phpunit.result.cache" 2>/dev/null)

    # Verify ZIP contents
    log "Verifying ZIP contents..."
    local file_count
    file_count=$(unzip -l "$DIST_FILE" | tail -1 | awk '{print $2}')
    log "ZIP contains $file_count files"

    # Report
    echo ""
    log "=========================================="
    log "Build complete: $DIST_FILE"
    log "Version:        v$version"
    log "Trunk staged:   $TRUNK_DIR/"
    log "=========================================="
    echo ""
    log "Next steps:"
    log "  1. Test the ZIP: wp plugin install $DIST_FILE --activate-network"
    log "  2. Commit trunk/ to SVN: svn add trunk/ && svn ci -m 'Tag v$version'"
    log "  3. Tag the release: svn cp trunk/ tags/$version/"
    log "  4. Upload $DIST_FILE to wordpress.org"
}

# ----------------------------------------------------------------------
# Entry point
# ----------------------------------------------------------------------

main() {
    cd "$(dirname "$0")/.." || die "Cannot cd to parent of scripts/"

    local cmd="${1:-}"

    case "$cmd" in
        help|--help|-h|"")
            cmd_help
            ;;
        check)
            cmd_check
            ;;
        *)
            if [[ "$cmd" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
                cmd_build "$cmd"
            else
                die "Unknown argument: $cmd. Run '$0 help' for usage."
            fi
            ;;
    esac
}

main "$@"
