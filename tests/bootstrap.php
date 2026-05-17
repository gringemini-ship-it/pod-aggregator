<?php
/**
 * POD Aggregator — PHPUnit Bootstrap
 *
 * Sets up a minimal WordPress testing environment using the
 * WordPress PHPUnit Polyfills (yoast/phpunit-polyfills).
 *
 * Before running tests, copy this file to tests/bootstrap.php and
 * configure the WordPress test environment constants below.
 *
 * @package POD_Aggregator
 */

// -----------------------------------------------------------------------------
// Test environment configuration
// Copy these into your phpunit.xml <php> section or set here:
// -----------------------------------------------------------------------------

if (!defined('ABSPATH')) {
    define('ABSPATH', getenv('WP_TEST_DIR') ?: '/var/www/html/');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
if (!defined('WP_TESTS_MULTISITE')) {
    define('WP_TESTS_MULTISITE', true);
}

// -----------------------------------------------------------------------------
// Load Composer autoloader (for plugin classes)
// -----------------------------------------------------------------------------
$composer_autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// -----------------------------------------------------------------------------
// Mock WordPress functions used by the plugin that aren't in core
// -----------------------------------------------------------------------------

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0x0fff) | 0x4000,
            wp_rand(0, 0x3fff) | 0x8000,
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff)
        );
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int
    {
        return $min + (mt_rand() % ($max - $min + 1));
    }
}

// -----------------------------------------------------------------------------
// Minimal WordPress stubs for unit testing (no database/server needed)
// These are only used in unit tests. Integration tests use wp @wordpress/wp.
// -----------------------------------------------------------------------------

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color(?string $color): ?string {
        if (null === $color) return null;
        $color = ltrim($color, '#');
        if ($color === '') return null;
        if (!preg_match('/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $color)) return null;
        return '#' . $color;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('absint')) {
    function absint($value): int {
        return abs((int) $value);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type): string {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(string $value): string {
        return stripslashes($value);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args(array $args, array $defaults = []): array {
        return array_merge($defaults, $args);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $string): string {
        return strip_tags($string);
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string {
        return preg_replace('/[^a-z0-9._-]/i', '', basename($filename));
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): bool {
        if (is_dir($dir)) {
            return true;
        }
        return @mkdir($dir, 0755, true);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $str, string $domain = ''): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $str, string $domain = ''): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $str, string $domain = ''): void {
        echo htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $str, string $domain = ''): void {
        echo htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash(string $value): string {
        return addslashes($value);
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str(string $str, array &$array): bool {
        parse_str($str, $array);
        return true;
    }
}

if (!function_exists('sanitize_sql_orderby')) {
    function sanitize_sql_orderby(string $orderby): string {
        return preg_replace('/[^a-z0-9_, ]/i', '', $orderby);
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, bool $display = true) {
        return $checked === $current ? ($display ? ' checked="checked"' : true) : false;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, bool $display = true) {
        return $selected === $current ? ($display ? ' selected="selected"' : true) : false;
    }
}

if (!function_exists('disabled')) {
    function disabled($disabled, $current = true, bool $display = true) {
        return $disabled === $current ? ($display ? ' disabled="disabled"' : true) : false;
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url(string $url, $action = -1): string {
        return $url;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, string $name = '_wpnonce', bool $referer = true): string {
        return '<input type="hidden" name="' . $name . '" value="testnonce" />';
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, $action = -1): int {
        return 1;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string {
        return 'testnonce';
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, bool $die = true) {
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool {
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!defined('ENT_QUOTES')) {
    define('ENT_QUOTES', 3);
}

class WP_Error {
    public $code = '';
    public $message = '';
    public $data = [];

    public function __construct(string $code = '', string $message = '', $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
    }

    public function add(string $code, string $message, $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}

class WP_REST_Request {}

class WP_REST_Response {
    public $data = [];
    public function __construct(array $data = [], int $status = 200) {
        $this->data = $data;
    }
}

// Mock wp_upload_dir
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false): array {
        $uploads_dir = sys_get_temp_dir() . '/pod-aggregator-test-uploads';
        if ($create_dir && !is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }
        return [
            'path'    => $uploads_dir,
            'url'     => 'file://' . $uploads_dir,
            'subdir'  => '',
            'basedir' => $uploads_dir,
            'baseurl' => 'file://' . $uploads_dir,
            'error'   => false,
        ];
    }
}

// Mock wp_remote_get / wp_remote_post
if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []) {
        return [
            'body'     => json_encode(['result' => [
                ['id' => 1, 'name' => 'Mock Product', 'variants' => []],
            ]]),
            'response' => ['code' => 200],
        ];
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []) {
        return [
            'body'     => json_encode(['result' => ['id' => 'mock_order_123']]),
            'response' => ['code' => 200],
        ];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string {
        return is_array($response) ? ($response['body'] ?? '') : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int {
        return is_array($response) ? ($response['response']['code'] ?? 200) : 200;
    }
}

// Mock get_site_option / update_site_option / add_site_option
// NOTE: Uses $GLOBALS so update_site_option writes persist to get_site_option reads.
if (!function_exists('get_site_option')) {
    function get_site_option(string $key, $default = false) {
        return $GLOBALS['_pod_site_options'][$key] ?? $default;
    }
}

if (!function_exists('update_site_option')) {
    function update_site_option(string $key, $value): bool {
        $GLOBALS['_pod_site_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('add_site_option')) {
    function add_site_option(string $key, $value): bool {
        return true;
    }
}

if (!function_exists('delete_site_option')) {
    function delete_site_option(string $key): bool {
        return true;
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient(string $key) {
        return false;
    }
}

if (!function_exists('set_site_transient')) {
    function set_site_transient(string $key, $value, int $expire = 0): bool {
        return true;
    }
}

if (!function_exists('delete_site_transient')) {
    function delete_site_transient(string $key): bool {
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $key) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expire = 0): bool {
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool {
        return true;
    }
}

// Mock wp_schedule_event / wp_clear_scheduled_hook / wp_next_scheduled
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool {
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook, array $args = []): int {
        return 0;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []): bool {
        return false;
    }
}

// Mock register_activation_hook / register_deactivation_hook (no-op for tests)
if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void {}
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void {}
}

// Mock plugin_basename
if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string {
        return 'pod-aggregator/pod-aggregator.php';
    }
}

// Mock plugin_dir_path / plugin_dir_url
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string {
        return __DIR__ . '/../';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return 'file://' . __DIR__ . '/../';
    }
}

// Mock load_plugin_textdomain
if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(string $domain, bool $deprecated = false, string $plugin_rel_path = ''): bool {
        return true;
    }
}

// Mock flush_rewrite_rules
if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(bool $hard = true): void {}
}

// Mock do_action / did_action
if (!function_exists('do_action')) {
    function do_action(string $tag, ...$args): void {}
}

if (!function_exists('did_action')) {
    function did_action(string $tag): int {
        return 0;
    }
}

// Mock get_post / wp_insert_post / wp_update_post / wp_delete_post
if (!function_exists('get_post')) {
    function get_post($post = null, $output = 'OBJECT') {
        if ($post && isset($GLOBALS['_pod_posts'][$post])) {
            $p = $GLOBALS['_pod_posts'][$post];
            if ($output === 'ARRAY_A') {
                return ['ID' => $post, 'post_title' => $p['post_title'] ?? '', 'post_type' => $p['post_type'] ?? '', 'post_status' => $p['post_status'] ?? 'publish'];
            }
            return (object) ['ID' => $post, 'post_title' => $p['post_title'] ?? '', 'post_type' => $p['post_type'] ?? '', 'post_status' => $p['post_status'] ?? 'publish'];
        }
        return null;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post(array $postarr, bool $wp_error = false) {
        static $id = 100;
        $new_id = $id++;
        $GLOBALS['_pod_posts'][$new_id] = $postarr;
        return $wp_error ? new WP_Error() : $new_id;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post(array $postarr, bool $wp_error = false) {
        return $postarr['ID'] ?? 100;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, bool $force = false) {
        return null;
    }
}

if (!function_exists('get_posts')) {
    function get_posts(array $args = []): array {
        return [];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $key, $value): bool {
        $GLOBALS['_pod_postmeta'][$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false) {
        $meta = $GLOBALS['_pod_postmeta'][$post_id] ?? [];
        if ($key === '') {
            return $single ? ($meta ?: '') : ($meta ?: ['']);
        }
        $val = $meta[$key] ?? null;
        if ($val === null) {
            return $single ? '' : [''];
        }
        return $single ? $val : [$val];
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta(int $post_id, string $key, $value = ''): bool {
        return true;
    }
}

// Mock register_post_type
if (!function_exists('register_post_type')) {
    function register_post_type(string $post_type, array $args = []): void {}
}

// Mock add_shortcode / remove_shortcode
if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): bool {
        return true;
    }
}

// Mock add_menu_page / add_submenu_page
if (!function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = '', string $icon_url = '', int $position = null): string {
        return $menu_slug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page(?string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = ''): string {
        return $menu_slug;
    }
}

// Mock admin_url / network_admin_url
if (!function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('network_admin_url')) {
    function network_admin_url(string $path = '', string $scheme = 'admin'): string {
        return 'http://example.com/wp-admin/network/' . ltrim($path, '/');
    }
}

// Mock settings_fields / do_settings_sections / submit_button
if (!function_exists('settings_fields')) {
    function settings_fields(string $option_group): void {}
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections(string $page): void {}
}

if (!function_exists('submit_button')) {
    function submit_button(string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = ''): void {}
}

// Mock add_settings_error
if (!function_exists('add_settings_error')) {
    function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void {}
}

// Mock wp_localize_script
if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $object_name, array $l10n): bool {
        return true;
    }
}

// Mock wp_enqueue_style / wp_enqueue_script / wp_enqueue_media
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, $src = '', array $deps = [], $ver = false, $media = 'all'): void {}
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, $src = '', array $deps = [], $ver = false, $in_footer = false): void {}
}

if (!function_exists('wp_enqueue_media')) {
    function wp_enqueue_media(): void {}
}

// Mock get_attached_file
if (!function_exists('get_attached_file')) {
    function get_attached_file(int $attachment_id, bool $unfiltered = false): string {
        return '';
    }
}

// Mock add_query_arg
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        if (is_array($args[0])) {
            return http_build_query($args[0]);
        }
        return $args[0] . (strpos($args[0], '?') !== false ? '&' : '?') . http_build_query(array_slice($args, 1));
    }
}

// Mock wp_remote_get / wp_remote_post stubs already above; add more if needed.

// Mock __return_true / __return_false
if (!function_exists('__return_true')) {
    function __return_true(): bool { return true; }
}

if (!function_exists('__return_false')) {
    function __return_false(): bool { return false; }
}

// Mock rest_url
if (!function_exists('rest_url')) {
    function rest_url(string $path = '', string $scheme = 'rest'): string {
        return 'http://example.com/wp-json/' . ltrim($path, '/');
    }
}

// Mock wp_generate_password (needed by WC)
if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[wp_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }
}

// Mock get_user_by
if (!function_exists('get_user_by')) {
    function get_user_by(string $field, $value) {
        return null;
    }
}

// Mock wp_get_current_user / wp_get_current_user
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        static $user = null;
        if ($user === null) {
            $user = new \stdClass();
            $user->ID = 1;
            $user->user_login = 'test_admin';
            $user->user_email = 'admin@test.com';
            $user->has_cap = function($cap) { return true; };
            $user->exists = function() { return true; };
        }
        return $user;
    }
}

// Mock get_option / update_option / add_option
if (!function_exists('get_option')) {
    function get_option(string $key, $default = false) {
        static $opts = [];
        return $opts[$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, $value, bool $autoload = false): bool {
        return true;
    }
}

// Mock wp_send_json_success / wp_send_json_error
// Throw a catchable exception instead of calling exit so tests can inspect the response.
class POD_Test_Ajax_Response extends \RuntimeException {
    public bool $success;
    public $data;
    public function __construct(bool $success, $data = null) {
        $this->success = $success;
        $this->data = $data;
        parent::__construct($success ? 'AJAX success' : 'AJAX error');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, int $status = null): void {
        throw new POD_Test_Ajax_Response(true, $data);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, int $status = null): void {
        throw new POD_Test_Ajax_Response(false, $data);
    }
}

// Mock delete_site_option and get_blog_option
if (!function_exists('delete_site_option')) {
    function delete_site_option(string $key): bool { return true; }
}
if (!function_exists('get_blog_option')) {
    function get_blog_option(int $blog_id, string $key, $default = false) { return $default; }
}
if (!function_exists('switch_to_blog')) {
    function switch_to_blog(int $blog_id, bool $to_cache = true) {}
}
if (!function_exists('restore_current_blog')) {
    function restore_current_blog(): bool { return true; }
}

// Mock wp_die
if (!function_exists('wp_die')) {
    function wp_die($message = '', string $title = '', array $args = []) { exit; }
}

// Mock deactivate_plugins
if (!function_exists('deactivate_plugins')) {
    function deactivate_plugins(string $plugin, bool $silent = false, bool $network_wide = false): void {}
}

// Mock WP_CONTENT_DIR
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wptest/wp-content');
}

// Mock __() — WordPress translation function.
if (!function_exists('__')) {
    function __(string $str, string $domain = ''): string {
        return $str;
    }
}

// Mock _n() — WordPress plural translation.
if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = ''): string {
        return $number === 1 ? $single : $plural;
    }
}

// Mock $wpdb — WordPress database global.
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $base_prefix = 'wp_';
        public $prefix = 'wp_';
        public $postmeta = 'wp_postmeta';
        public $posts = 'wp_posts';
        public $siteid = 1;
        public $last_error = '';

        public function query(string $sql) {
            return true;
        }

        public function prepare(string $sql, ...$args): string {
            $i = 0;
            return preg_replace_callback('/(%[sd])/', function ($m) use ($args, &$i) {
                $val = $args[$i++] ?? '';
                if ($m[0] === '%d') {
                    return (int) $val;
                }
                return "'" . esc_sql($val) . "'";
            }, $sql);
        }

        public function get_var(string $sql) {
            return null;
        }

        public function get_results(string $sql, string $output = 'OBJECT') {
            return [];
        }

        public function insert(string $table, array $data, $format = null) {
            return 1;
        }

        public function esc_like(string $str): string {
            return addcslashes($str, '_%\\');
        }
    };
}

// Mock esc_sql().
if (!function_exists('esc_sql')) {
    function esc_sql($str): string {
        return addslashes((string) $str);
    }
}

// Mock HOUR_IN_SECONDS / MINUTE_IN_SECONDS.
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

// Mock wc_get_order — WooCommerce order lookup.
if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        return null;
    }
}

// Mock wc_get_product — WooCommerce product lookup.
if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id) {
        return new class($product_id) {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function get_meta(string $key, bool $single = true) {
                // Return values that allow POD cart item data tests to pass.
                if ($key === '_pod_enabled') return '1';
                if ($key === '_pod_provider') return 'printful';
                return get_post_meta($this->id, $key, $single);
            }
        };
    }
}

// Mock sanitize_title.
if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($title));
    }
}

// Mock ABSPATH if not defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wptest/');
}

// --- Additional mocks needed by Product_Importer and Product_Import ---

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $content): string {
        return strip_tags($content, '<p><br><strong><em><ul><ol><li><a><img>');
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms(int $object_id, $terms, string $taxonomy, bool $append = false) {
        return [];
    }
}

if (!function_exists('wc_sanitize_taxonomy_name')) {
    function wc_sanitize_taxonomy_name(string $name): string {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($name));
    }
}

if (!function_exists('wc_attribute_taxonomy_name')) {
    function wc_attribute_taxonomy_name(string $name): string {
        return 'pa_' . wc_sanitize_taxonomy_name($name);
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists(string $taxonomy): bool {
        return false;
    }
}

if (!function_exists('term_exists')) {
    function term_exists($term, string $taxonomy = '', int $parent = 0) {
        return null;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term(string $term, string $taxonomy, array $args = []) {
        static $id = 200;
        return ['term_id' => $id++, 'term_taxonomy_id' => $id++];
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail(int $post_id, int $thumb_id): bool {
        return true;
    }
}

if (!function_exists('wc_format_decimal')) {
    function wc_format_decimal(float $number, $dp = false): string {
        return number_format($number, wc_get_price_decimals(), '.', '');
    }
}

if (!function_exists('wc_get_price_decimals')) {
    function wc_get_price_decimals(): int {
        return 2;
    }
}

if (!function_exists('media_handle_sideload')) {
    function media_handle_sideload(array $file_array, int $post_id, string $desc = null, array $post_data = []) {
        static $id = 300;
        return $id++;
    }
}

if (!function_exists('download_url')) {
    function download_url(string $url, int $timeout = 300) {
        // Simulate a temp file path.
        $tmp = tempnam(sys_get_temp_dir(), 'pod_test_dl_');
        file_put_contents($tmp, 'mock_image_data');
        return $tmp;
    }
}

if (!function_exists('wp_basename')) {
    function wp_basename(string $path, string $suffix = ''): string {
        $base = basename($path);
        if ($suffix && substr($base, -strlen($suffix)) === $suffix) {
            $base = substr($base, 0, -strlen($suffix));
        }
        return $base;
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link(int $post_id = 0, string $context = 'display'): ?string {
        return 'http://example.com/wp-admin/post.php?post=' . $post_id . '&action=edit';
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink(int $post_id = 0, bool $leavename = false): string {
        return 'http://example.com/?p=' . $post_id;
    }
}

if (!function_exists('paginate_links')) {
    function paginate_links(array $args = []): string {
        return '<span class="page-numbers">1</span>';
    }
}

if (!function_exists('esc_js')) {
    function esc_js(string $text): string {
        return addslashes($text);
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return false;
    }
}

// -----------------------------------------------------------------------------
// Load all plugin class files AFTER all mocks are defined.
// Files use class- prefix naming which doesn't match PSR-4 expected file names,
// so we require them explicitly. Order matches pod-aggregator.php.
// -----------------------------------------------------------------------------
$plugin_dir = dirname(__DIR__) . '/';
require_once $plugin_dir . 'includes/class-pod-provider.php';
require_once $plugin_dir . 'includes/class-cpt-registrar.php';
require_once $plugin_dir . 'includes/providers/class-printful.php';
require_once $plugin_dir . 'includes/providers/class-printify.php';
require_once $plugin_dir . 'includes/providers/class-gelato.php';
require_once $plugin_dir . 'includes/CLI/class-cli.php';
require_once $plugin_dir . 'includes/WooCommerce/class-integration.php';
require_once $plugin_dir . 'includes/product-customizer/class-design-element.php';
require_once $plugin_dir . 'includes/product-customizer/class-design.php';
require_once $plugin_dir . 'includes/product-customizer/class-design-storage.php';
require_once $plugin_dir . 'includes/product-customizer/class-print-generator.php';
require_once $plugin_dir . 'includes/product-customizer/class-rest-controller.php';
require_once $plugin_dir . 'includes/REST/class-controller.php';
require_once $plugin_dir . 'includes/Crons/class-scheduler.php';
require_once $plugin_dir . 'includes/class-ajax.php';
require_once $plugin_dir . 'admin/class-admin.php';
require_once $plugin_dir . 'admin/class-settings.php';
require_once $plugin_dir . 'admin/class-preset-templates.php';
// class-customizer-editor.php has a pre-existing parse error (unmatched '}')
// and is only needed for frontend rendering, not unit tests.
require_once $plugin_dir . 'public/class-shortcodes.php';
require_once $plugin_dir . 'includes/class-loader.php';
require_once $plugin_dir . 'includes/class-product-importer.php';
require_once $plugin_dir . 'admin/class-product-import.php';
