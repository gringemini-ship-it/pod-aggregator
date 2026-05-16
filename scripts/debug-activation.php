<?php
/**
 * POD Aggregator — Activation Debug Script
 *
 * Run this script on your WordPress server to validate the plugin
 * can be loaded without activating it. It tests all the critical
 * paths that run during activation.
 *
 * Usage:
 *   php scripts/debug-activation.php
 *   or
 *   wp eval-file scripts/debug-activation.php
 */

// Simulate WordPress environment for standalone testing.
$wp_load = dirname(__DIR__, 3) . '/wp-load.php';
if (file_exists($wp_load)) {
    require_once $wp_load;
    echo "[OK] WordPress loaded from: $wp_load\n";
} else {
    // Try common locations.
    $locations = [
        '/var/www/html/wp-load.php',
        '/www/wwwroot/*/wp-load.php',
        dirname(__DIR__, 4) . '/wp-load.php',
    ];
    $found = false;
    foreach ($locations as $loc) {
        foreach (glob($loc) as $file) {
            if (file_exists($file)) {
                require_once $file;
                echo "[OK] WordPress loaded from: $file\n";
                $found = true;
                break 2;
            }
        }
    }
    if (!$found) {
        echo "[WARN] Could not find wp-load.php — testing with minimal mock\n";
        // Minimal mock so we can at least syntax-check.
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/');
        }
        // Stub WP functions if not available.
        if (!function_exists('plugin_dir_path')) {
            function plugin_dir_path($file) {
                return dirname($file) . '/';
            }
        }
        if (!function_exists('plugin_dir_url')) {
            function plugin_dir_url($file) {
                return 'file://' . dirname($file) . '/';
            }
        }
        if (!function_exists('plugin_basename')) {
            function plugin_basename($file) {
                return basename(dirname($file)) . '/' . basename($file);
            }
        }
    }
}

echo "\n=== POD Aggregator — Activation Debug ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Plugin dir:  " . __DIR__ . "/../\n\n";

// Step 1: Check PHP syntax of all files.
echo "--- Step 1: PHP Syntax Check ---\n";
$errors = [];
$plugin_root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_root, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    // Skip vendor, .git, tests.
    if (strpos($path, '/vendor/') !== false) continue;
    if (strpos($path, '/.git/') !== false) continue;
    if (strpos($path, '/tests/') !== false) continue;

    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return);
    $result = implode("\n", $output);
    if ($return !== 0 || strpos($result, 'No syntax errors') === false) {
        $rel = str_replace($plugin_root . '/', '', $path);
        $errors[] = "  FAIL: $rel — $result";
    }
}

if (empty($errors)) {
    echo "  All PHP files pass syntax check.\n";
} else {
    echo "  SYNTAX ERRORS FOUND:\n";
    foreach ($errors as $e) {
        echo "$e\n";
    }
}

// Step 2: Try loading the plugin file.
echo "\n--- Step 2: Plugin File Loading ---\n";
$plugin_file = dirname(__DIR__) . '/pod-aggregator.php';

try {
    // Capture output to suppress any HTML.
    ob_start();
    $loaded = include $plugin_file;
    ob_end_clean();
    echo "  Plugin file loaded successfully.\n";
} catch (\Throwable $e) {
    echo "  FATAL: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  Trace:\n" . $e->getTraceAsString() . "\n";
}

// Step 3: Check all required classes exist.
echo "\n--- Step 3: Class Availability ---\n";
$required_classes = [
    'POD_Aggregator\Loader',
    'POD_Aggregator\CPT_Registrar',
    'POD_Aggregator\Provider_Interface',
    'POD_Aggregator\Crons\Scheduler',
    'POD_Aggregator\WooCommerce\Integration',
    'POD_Aggregator\REST\Controller',
    'POD_Aggregator\ProductCustomizer\Design',
    'POD_Aggregator\ProductCustomizer\Design_Element',
    'POD_Aggregator\ProductCustomizer\Design_Storage',
    'POD_Aggregator\ProductCustomizer\Print_Generator',
    'POD_Aggregator\ProductCustomizer\REST_Controller',
    'POD_Aggregator\Admin\Admin',
    'POD_Aggregator\Admin\Settings',
    'POD_Aggregator\Admin\Preset_Templates',
    'POD_Aggregator\Public\Shortcodes',
    'POD_Aggregator\Public\POD_Customizer_Editor',
    'POD_Aggregator\Provider\Printful_Adapter',
    'POD_Aggregator\Provider\Printify_Adapter',
    'POD_Aggregator\Provider\Gelato_Adapter',
];

$missing = [];
foreach ($required_classes as $class) {
    if (!class_exists($class) && !interface_exists($class)) {
        $missing[] = $class;
    }
}

if (empty($missing)) {
    echo "  All required classes/interface available.\n";
} else {
    echo "  MISSING CLASSES:\n";
    foreach ($missing as $m) {
        echo "    - $m\n";
    }
}

// Step 4: Simulate activation callback.
echo "\n--- Step 4: Activation Callback Simulation ---\n";

if (!function_exists('pod_aggregator_create_tables')) {
    echo "  FAIL: pod_aggregator_create_tables() function not found.\n";
} else {
    echo "  pod_aggregator_create_tables() — exists.\n";
}

if (!class_exists('\POD_Aggregator\Crons\Scheduler')) {
    echo "  FAIL: Scheduler class not found.\n";
} else {
    echo "  Scheduler class — exists.\n";
    if (method_exists('\POD_Aggregator\Crons\Scheduler', 'schedule_crons')) {
        echo "  Scheduler::schedule_crons() — exists.\n";
    } else {
        echo "  FAIL: schedule_crons() method not found.\n";
    }
    if (method_exists('\POD_Aggregator\Crons\Scheduler', 'clear_crons')) {
        echo "  Scheduler::clear_crons() — exists.\n";
    } else {
        echo "  FAIL: clear_crons() method not found.\n";
    }
}

// Check multisite functions.
echo "\n--- Step 5: WordPress Function Checks ---\n";
$wp_funcs = [
    'get_site_option',
    'update_site_option',
    'add_site_option',
    'delete_site_option',
    'get_site_transient',
    'set_site_transient',
    'delete_site_transient',
    'wp_next_scheduled',
    'wp_schedule_event',
    'wp_clear_scheduled_hook',
    'flush_rewrite_rules',
    'dbDelta',
    'deactivate_plugins',
    'esc_html__',
    'esc_html',
    'esc_attr',
    'esc_url',
];

foreach ($wp_funcs as $func) {
    if (function_exists($func)) {
        echo "  $func() — OK\n";
    } else {
        echo "  $func() — NOT FOUND (may cause fatal error)\n";
    }
}

echo "\n=== Debug Complete ===\n";
