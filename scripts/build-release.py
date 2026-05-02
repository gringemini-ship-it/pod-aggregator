#!/usr/bin/env python3
"""
build-release.py — Build a WordPress.org-ready release ZIP for POD Aggregator.
No external dependencies beyond Python 3 stdlib.

Usage:
    python3 scripts/build-release.py          # Interactive
    python3 scripts/build-release.py 1.0.0    # Non-interactive build
    python3 scripts/build-release.py check     # Lint + syntax only
"""

import sys
import os
import re
import zipfile
import subprocess
from pathlib import Path

PLUGIN_SLUG = "pod-aggregator"
TRUNK_DIR = Path("pod-aggregator/trunk")
DIST_FILE = Path("pod-aggregator.zip")

# Files/directories to exclude from the release
EXCLUDE_DIRS = {
    ".git", ".github", "tests", "vendor", "node_modules",
    "scripts", "references", "docs", ".github",
    # Never copy build artifacts or nested copies of the plugin
    "pod-aggregator",
}
EXCLUDE_FILES = {
    "composer.json", "composer.lock", "phpunit.xml.dist",
    ".phpunit.result.cache", "install.sh",
}
EXCLUDE_SUFFIXES = {".md", ".txt", ".log", ".map", ".xml"}
EXCLUDE_BASENAMES = {".phpcs.xml", ".phpcs.xml.dist", "phpcs.xml", "phpcs.xml.dist"}


def log(msg):
    print(f"[build] {msg}")


def die(msg):
    print(f"[build] ERROR: {msg}", file=sys.stderr)
    sys.exit(1)


def need(cmd):
    if not Path(cmd).exists() and subprocess.call(f"command -v {cmd} >/dev/null 2>&1", shell=True) != 0:
        die(f"Required tool not found: {cmd}")


def php_syntax_check():
    """Check all PHP files for syntax errors."""
    need("php")
    log("Checking PHP syntax...")
    php_files = []
    for root, dirs, files in os.walk("."):
        # Prune excluded dirs in-place
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS and not d.startswith(".")]

        for f in files:
            if f.endswith(".php"):
                php_files.append(os.path.join(root, f))

    for filepath in php_files:
        result = subprocess.run(
            ["php", "-l", filepath],
            capture_output=True, text=True
        )
        if "No syntax errors" not in result.stdout:
            print(result.stdout, file=sys.stderr)
            die(f"PHP syntax error in {filepath}")

    log(f"PHP syntax OK ({len(php_files)} files checked)")


def phpcs_check():
    """Run PHP CodeSniffer if available."""
    if subprocess.call("command -v phpcs >/dev/null 2>&1", shell=True) != 0:
        log("PHPCS not found — skipping lint")
        return

    log("Running PHP CodeSniffer (WordPress rules)...")
    result = subprocess.run(
        ["phpcs", "--standard=WordPress", "--extensions=php", PLUGIN_SLUG],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        print(result.stdout, file=sys.stderr)
        die("PHPCS found violations")
    log("PHPCS OK")


def cmd_check():
    log("Running pre-build checks...")
    php_syntax_check()
    phpcs_check()
    log("All checks passed")


def get_version_from_php():
    """Extract version from the plugin main file."""
    main_file = Path("pod-aggregator.php")
    content = main_file.read_text()
    m = re.search(r"^\s*\*?\s*Version:\s+(\S+)", content, re.MULTILINE)
    if not m:
        die("Could not find Version in pod-aggregator.php")
    return m.group(1)


def validate_version(version):
    if not re.match(r"^\d+\.\d+\.\d+$", version):
        die(f"Invalid version format: {version} (expected e.g. 1.2.3)")


def copy_files(src_root, dst_root):
    """Copy plugin files to trunk dir, excluding dev artifacts."""
    dst_root = Path(dst_root)
    dst_root.mkdir(parents=True, exist_ok=True)

    count = 0
    for root, dirs, files in os.walk(src_root):
        # Prune excluded dirs
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS and not d.startswith(".")]

        for f in files:
            filepath = Path(root) / f

            # Exclude by suffix
            if any(filepath.suffix == s for s in EXCLUDE_SUFFIXES):
                continue
            # Exclude by exact basename
            if f in EXCLUDE_FILES or any(f.endswith(e) for e in EXCLUDE_BASENAMES):
                continue
            # Exclude hidden files
            if f.startswith("."):
                continue

            rel = filepath.relative_to(src_root)
            dst = dst_root / rel
            dst.parent.mkdir(parents=True, exist_ok=True)
            dst.write_bytes(filepath.read_bytes())
            count += 1

    return count


def build_zip(trunk_dir, zip_path):
    """Create a ZIP from the trunk directory."""
    log(f"Creating {zip_path}...")
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for filepath in trunk_dir.rglob("*"):
            if filepath.is_file():
                arcname = filepath.relative_to(trunk_dir)
                zf.write(filepath, arcname)

    log(f"ZIP contains {len(zf.namelist())} files")


def cmd_build(version):
    validate_version(version)

    php_ver = get_version_from_php()
    if php_ver != version:
        log(f"Note: pod-aggregator.php has Version: {php_ver}, building as v{version}")

    log(f"Building POD Aggregator v{version}")

    # Clean previous builds
    if TRUNK_DIR.exists():
        import shutil
        shutil.rmtree(TRUNK_DIR)
    if DIST_FILE.exists():
        DIST_FILE.unlink()

    # Stage files
    log(f"Staging files into {TRUNK_DIR}...")
    count = copy_files(Path("."), TRUNK_DIR)
    log(f"Copied {count} files to {TRUNK_DIR}")

    # Verify expected key files are present
    key_files = ["pod-aggregator.php", "uninstall.php", "admin", "includes", "public"]
    for kf in key_files:
        if not (TRUNK_DIR / kf).exists():
            die(f"Critical file missing after copy: {kf}")

    # Build ZIP
    build_zip(TRUNK_DIR, DIST_FILE)

    # Verify
    with zipfile.ZipFile(DIST_FILE) as zf:
        names = zf.namelist()
        log(f"ZIP verification: {len(names)} files")
        # Spot-check
        for check in ["pod-aggregator.php", "uninstall.php", "admin/class-settings.php",
                      "includes/class-loader.php", "public/"]:
            found = any(check in n for n in names)
            if not found:
                die(f"Expected file not in ZIP: {check}")

    print("")
    log("=" * 50)
    log(f"Build complete: {DIST_FILE}")
    log(f"Version:        v{version}")
    log(f"Trunk staged:   {TRUNK_DIR}/")
    log("=" * 50)
    print("")
    log("Next steps:")
    log(f"  1. Test the ZIP: wp plugin install {DIST_FILE} --activate-network")
    log("  2. Tag the release on GitHub with v{version} and attach the ZIP")
    log("  3. For wordpress.org: upload ZIP to your SVN repo")


def main():
    cmd = sys.argv[1] if len(sys.argv) > 1 else ""

    if cmd in ("help", "--help", "-h", ""):
        print(__doc__)
        return

    if cmd == "check":
        cmd_check()
        return

    # Treat as version number
    if re.match(r"^\d+\.\d+\.\d+$", cmd):
        cmd_build(cmd)
        return

    die(f"Unknown argument: {cmd}. Run: python3 scripts/build-release.py help")


if __name__ == "__main__":
    main()
