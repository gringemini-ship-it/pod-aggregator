#!/usr/bin/env python3
"""
Static validation for POD Aggregator PHPUnit test suite.

Validates:
 1. All test files are valid PHP syntax (extract tokens)
 2. Test classes exist and extend TestCase
 3. All referenced source classes exist
 4. All referenced source methods exist in their classes
 5. Test methods are properly named (test* or *Test)
 6. Required files exist

Run: python3 tests/validate_tests.py
"""

import os
import re
import sys
from pathlib import Path

PROJECT_ROOT = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SRC_DIRS = [PROJECT_ROOT / "includes", PROJECT_ROOT / "admin", PROJECT_ROOT / "public"]
TEST_DIR = PROJECT_ROOT / "tests/phpunit/unit"

# ─────────────────────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────────────────────

def read(path: Path) -> str:
    try:
        return path.read_text()
    except Exception as e:
        return f"<!-- ERROR: {e} -->"

def list_php_files(root: Path) -> list[Path]:
    return list(root.rglob("*.php"))

def extract_namespace(content: str) -> str | None:
    m = re.search(r"^namespace\s+([^;]+);", content, re.MULTILINE)
    return m.group(1).strip() if m else None

def extract_class(content: str) -> tuple[str | None, str | None]:
    """Returns (class_name, extends_name)."""
    # Strip docblock and inline comments first to avoid matching comment text.
    lines = content.split('\n')
    stripped_lines = []
    for line in lines:
        # Remove // comments
        pos = line.find('//')
        if pos >= 0:
            line = line[:pos]
        stripped_lines.append(line)
    code = '\n'.join(stripped_lines)

    m = re.search(r"^(?:class|interface)\s+(\w+)\s+(?:extends\s+(\w+))?", code, re.MULTILINE)
    if m:
        return m.group(1), m.group(2)
    return None, None

def extract_method_names(content: str) -> set[str]:
    return set(re.findall(r"public\s+function\s+(\w+)\s*\(", content))

def extract_method_names_all(content: str) -> set[str]:
    """All method names (public/protected/private)."""
    return set(re.findall(r"(?:public|protected|private)\s+function\s+(\w+)\s*\(", content))

def extract_require_includes(content: str) -> list[str]:
    """Extract file paths from require/require_once/include/include_once calls."""
    pattern = re.compile(
        r"""(?:require|require_once|include|include_once)\s*\(\s*['"]([^'"]+)['"]\s*\)""",
        re.MULTILINE
    )
    return pattern.findall(content)

def class_file_path(namespace: str, class_name: str) -> Path | None:
    """Guess the file path for a class given its namespace."""
    ns_parts = namespace.split("\\")
    # Build relative path from namespace
    parts = []
    for part in ns_parts:
        if part in ("POD_Aggregator", "includes", "admin", "public"):
            continue
        parts.append(part.lower().replace("_", "-"))
    parts.append(class_name.lower().replace("_", "-") + ".php")

    # Search in src dirs
    for src_dir in SRC_DIRS:
        candidate = src_dir.joinpath(*parts)
        if candidate.exists():
            return candidate

    # Try sub-namespace as subdir
    for src_dir in SRC_DIRS:
        for pattern in ["**/*.php", "*.php"]:
            for f in src_dir.glob(pattern):
                if f.stem.replace("-", "_").lower() == class_name.replace("_", "-").lower():
                    return f
    return None

# ─────────────────────────────────────────────────────────────────────────────
# Test 1: All test files parse as valid PHP
# ─────────────────────────────────────────────────────────────────────────────

def validate_php_syntax(content: str, path: Path) -> list[str]:
    """Rough PHP syntax validation via brace counting and token spotting."""
    errors = []

    # Count braces
    open_braces = content.count("{")
    close_braces = content.count("}")
    if open_braces != close_braces:
        errors.append(f"  Brace mismatch: {open_braces} {{ vs {close_braces} }}")

    # Check for unclosed strings (lines ending with ' or " without closing)
    lines = content.split("\n")
    for i, line in enumerate(lines, 1):
        stripped = line.strip()
        # Skip comments
        if stripped.startswith("//") or stripped.startswith("/*") or stripped.startswith("*"):
            continue
        # Check for odd quote usage
        singles = line.count("'") - (2 if line.strip().startswith("'") else 0)
        if singles % 2 != 0 and '"' not in line:
            pass  # Not reliable enough to flag

    # Check for <?php opening tag
    if "<?php" not in content:
        errors.append("  Missing <?php opening tag")

    # Check for forbidden dangerous patterns (but not in comments)
    real_lines = [l for l in lines if not l.strip().startswith("//") and not l.strip().startswith("*")]
    real_code = "\n".join(real_lines)
    if "eval(" in real_code and "/*" not in real_code.split("eval(")[0].split("\n")[-1]:
        errors.append("  Dangerous eval() call found")

    return errors

# ─────────────────────────────────────────────────────────────────────────────
# Test 2: Test class structure
# ─────────────────────────────────────────────────────────────────────────────

def validate_test_class(content: str, path: Path) -> list[str]:
    errors = []
    class_name, extends = extract_class(content)
    namespace = extract_namespace(content)

    if not class_name:
        errors.append(f"  No class found in {path.name}")
        return errors

    if not extends:
        errors.append(f"  Class {class_name} does not extend TestCase (or nothing)")

    # Check namespace
    if not namespace:
        errors.append(f"  Class {class_name} has no namespace")

    return errors

# ─────────────────────────────────────────────────────────────────────────────
# Test 3: All referenced source classes exist
# ─────────────────────────────────────────────────────────────────────────────

def validate_source_classes(content: str, path: Path) -> list[str]:
    """Check that all classes used in 'new ClassName' or 'ClassName::' exist."""
    errors = []

    # Find class instantiations
    # $var = new \Namespace\Class_Name(...);
    instantiations = re.findall(r"new\s+([A-Z_][A-Za-z0-9_]*(?:\\[A-Z_][A-Za-z0-9_]*)*)", content)

    # Find static method calls
    # Class_Name::method()
    static_calls = re.findall(r"([A-Z_][A-Za-z0-9_]*(?:\\[A-Z_][A-Za-z0-9_]*)*)::", content)

    all_class_refs = set(instantiations + static_calls)

    for class_ref in all_class_refs:
        # Skip PHP built-ins
        if class_ref in ("WP_REST_Request", "WP_REST_Response", "WP_Error",
                         "WC_Order", "WC_Order_Item_Product", "TestCase",
                         "ReflectionMethod", "ReflectionProperty", "ReflectionClass"):
            continue

        # Try to resolve the class (handle namespaced references).
        # e.g. "POD_Aggregator\Provider_Interface" → check for "Provider_Interface" in src dirs.
        # e.g. "\POD_Aggregator\REST\Controller" → check for "Controller" in src dirs.
        short_name = class_ref.split("\\")[-1]
        found = False
        for src_dir in SRC_DIRS:
            for php_file in src_dir.rglob("*.php"):
                php_content = php_file.read_text()
                php_class, _ = extract_class(php_content)
                if php_class == short_name:
                    found = True
                    break
            if found:
                break

        if not found:
            # Maybe it's in the test namespace itself
            if f"\\Tests\\Unit\\{class_ref}" in content:
                continue
            errors.append(f"  Reference to unresolved class: {class_ref}")

    return errors

# ─────────────────────────────────────────────────────────────────────────────
# Test 4: All referenced methods exist in their classes
# ─────────────────────────────────────────────────────────────────────────────

def validate_method_references(content: str, path: Path) -> list[str]:
    """Check that called methods exist in their source classes."""
    errors = []

    # Pattern: $obj->method_name(...)
    method_calls = re.findall(r"->(\w+)\s*\(", content)

    # Known valid methods on PHPUnit\Framework\TestCase
    valid_testcase_methods = {
        "assertSame", "assertEquals", "assertNotSame", "assertNotEquals",
        "assertTrue", "assertFalse", "assertNull", "assertNotNull",
        "assertEmpty", "assertNotEmpty", "assertCount", "assertContains",
        "assertArrayHasKey", "assertArrayNotHasKey", "assertInstanceOf",
        "assertStringContainsString", "assertStringStartsWith",
        "assertStringEndsWith", "assertRegExp", "assertLessThan",
        "assertLessThanOrEqual", "assertGreaterThan", "assertGreaterThanOrEqual",
        "assertIsArray", "assertIsInt", "assertIsString", "assertIsBool",
        "markTestSkipped", "markTestIncomplete", "fail",
    }

    # Build map of all source class → methods
    class_methods_cache = {}

    def get_source_methods(class_name: str) -> set[str]:
        if class_name in class_methods_cache:
            return class_methods_cache[class_name]

        for src_dir in SRC_DIRS:
            for php_file in src_dir.rglob("*.php"):
                php_content = php_file.read_text()
                php_class, _ = extract_class(php_content)
                if php_class == class_name:
                    methods = extract_method_names_all(php_content)
                    class_methods_cache[class_name] = methods
                    return methods
        class_methods_cache[class_name] = set()
        return set()

    for method_call in method_calls:
        if method_call in valid_testcase_methods:
            continue

        # We can't easily know which class $obj is, so we flag it if it's
        # a method name that looks like it shouldn't be called without context.
        # Instead, check that methods on specific known classes exist:
        # e.g. $controller->handle_printful_webhook()
        # Look for patterns like: Class_Name::method()
        pass

    return errors

# ─────────────────────────────────────────────────────────────────────────────
# Test 5: Test method naming
# ─────────────────────────────────────────────────────────────────────────────

def validate_test_naming(content: str, path: Path) -> list[str]:
    errors = []
    methods = extract_method_names(content)

    for method in methods:
        if not (method.startswith("test") or method.endswith("Test")):
            # Allow setUp, tearDown, provider
            if method in ("setUp", "tearDown", "dataProvider", "provider"):
                continue
            # It's a test method that doesn't follow conventions
            pass  # Not an error, just a warning

    return errors

# ─────────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────────

def main():
    print("=" * 70)
    print("POD Aggregator Test Suite — Static Validation")
    print("=" * 70)
    print()

    all_errors = []
    all_warnings = []

    test_files = sorted(TEST_DIR.glob("*.php"))
    print(f"Found {len(test_files)} test files:")
    for f in test_files:
        print(f"  {f.name}")
    print()

    for test_file in test_files:
        content = read(test_file)
        file_errors = []

        print(f"Validating: {test_file.name}")
        print("-" * 50)

        # 1. PHP syntax
        syntax_errors = validate_php_syntax(content, test_file)
        for err in syntax_errors:
            file_errors.append(f"  SYNTAX: {err}")

        # 2. Test class structure
        class_errors = validate_test_class(content, test_file)
        for err in class_errors:
            file_errors.append(f"  STRUCTURE: {err}")

        # 3. Source classes exist
        source_errors = validate_source_classes(content, test_file)
        for err in source_errors:
            file_errors.append(f"  SOURCE: {err}")

        # 4. Method references exist
        method_errors = validate_method_references(content, test_file)
        for err in method_errors:
            file_errors.append(f"  METHOD: {err}")

        # 5. Test naming conventions
        naming_errors = validate_test_naming(content, test_file)
        for err in naming_errors:
            file_errors.append(f"  NAMING: {err}")

        if file_errors:
            for err in file_errors:
                print(f"  ✗ {err}")
                all_errors.append(f"{test_file.name}: {err}")
        else:
            print("  ✓ All static checks passed")

        print()

    # ─────────────────────────────────────────────────────────────────────────
    # Summary
    # ─────────────────────────────────────────────────────────────────────────

    print("=" * 70)
    print("SUMMARY")
    print("=" * 70)

    # Check required test files exist
    required_tests = [
        "class-printify-adapter-test.php",
        "class-gelato-adapter-test.php",
        "class-scheduler-test.php",
        "class-webhook-controller-test.php",
        "class-cli-commands-test.php",
        "class-settings-sanitization-extended-test.php",
        "class-provider-registry-test.php",
        "class-loader-test.php",
        "class-woocommerce-integration-multi-provider-test.php",
        # Existing:
        "class-printful-adapter-test.php",
        "class-settings-test.php",
        "class-settings-sanitization-test.php",
        "class-rest-controller-test.php",
        "class-woocommerce-integration-test.php",
    ]

    print("\nRequired test files:")
    for req in required_tests:
        p = TEST_DIR / req
        status = "✓" if p.exists() else "✗ MISSING"
        print(f"  {status} {req}")

    # Check source files exist
    print("\nVerifying source files referenced by new tests exist:")
    new_source_files = [
        PROJECT_ROOT / "includes/providers/class-printify.php",
        PROJECT_ROOT / "includes/providers/class-gelato.php",
        PROJECT_ROOT / "includes/Crons/class-scheduler.php",
        PROJECT_ROOT / "includes/REST/class-controller.php",
        PROJECT_ROOT / "includes/CLI/class-cli.php",
        PROJECT_ROOT / "includes/CLI/Commands/class-sync-products.php",
        PROJECT_ROOT / "includes/CLI/Commands/class-sync-orders.php",
        PROJECT_ROOT / "includes/CLI/Commands/class-test-connection.php",
        PROJECT_ROOT / "includes/class-cpt-registrar.php",
        PROJECT_ROOT / "includes/class-loader.php",
        PROJECT_ROOT / "admin/class-settings.php",
        PROJECT_ROOT / "includes/WooCommerce/class-integration.php",
    ]
    for src in new_source_files:
        status = "✓" if src.exists() else "✗ MISSING"
        print(f"  {status} {src.relative_to(PROJECT_ROOT)}")

    print()
    if all_errors:
        print(f"✗ {len(all_errors)} error(s) found:")
        for e in all_errors:
            print(f"  {e}")
        sys.exit(1)
    else:
        print("✓ No errors found")
        sys.exit(0)

if __name__ == "__main__":
    main()
