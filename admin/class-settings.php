<?php
/**
 * POD Aggregator — Settings API page (Printful API key, sync settings).
 *
 * @package POD_Aggregator\Admin
 */

namespace POD_Aggregator\Admin;

/**
 * Registers and renders settings using the WordPress Settings API.
 *
 * @since 1.0.0
 */
class Settings
{
    /** Settings key. */
    public const SETTINGS_KEY = 'pod_aggregator_settings';

    /**
     * Register settings and their sanitization callback.
     *
     * @return void
     */
    public function register_settings()
    {
        register_setting(
            self::SETTINGS_KEY,
            self::SETTINGS_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => [],
            ]
        );

        // ---- Section: Printful API ----
        add_settings_section(
            'pod_aggregator_printful_section',
            __('Printful Configuration', 'pod-aggregator'),
            [$this, 'render_printful_section_desc'],
            'pod_aggregator_settings'
        );

        add_settings_field(
            'printful_api_key',
            __('Printful API Key', 'pod-aggregator'),
            [$this, 'render_printful_api_key_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printful_section'
        );

        add_settings_field(
            'printful_default_markup',
            __('Default Markup (%)', 'pod-aggregator'),
            [$this, 'render_printful_markup_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printful_section'
        );

        add_settings_field(
            'printful_debug_mode',
            __('Debug Mode', 'pod-aggregator'),
            [$this, 'render_printful_debug_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printful_section'
        );

        // ---- Section: Sync Settings ----
        add_settings_section(
            'pod_aggregator_sync_section',
            __('Sync Settings', 'pod-aggregator'),
            [$this, 'render_sync_section_desc'],
            'pod_aggregator_settings'
        );

        add_settings_field(
            'auto_sync_enabled',
            __('Enable Auto Sync', 'pod-aggregator'),
            [$this, 'render_auto_sync_field'],
            'pod_aggregator_settings',
            'pod_aggregator_sync_section'
        );

        add_settings_field(
            'sync_interval_hours',
            __('Product Sync Interval (hours)', 'pod-aggregator'),
            [$this, 'render_sync_interval_field'],
            'pod_aggregator_settings',
            'pod_aggregator_sync_section'
        );
    }

    // -------------------------------------------------------------------------
    // Section descriptions
    // -------------------------------------------------------------------------

    public function render_printful_section_desc()
    {
        ?>
        <p id="pod_printful_desc">
            <?php
            printf(
                /* translators: %s = Printful API docs URL */
                esc_html__('Enter your Printful API key to connect your store. Find your key at %s.', 'pod-aggregator'),
                '<a href="https://www.printful.com/docs/api" target="_blank" rel="noopener">printful.com/docs/api</a>'
            );
            ?>
        </p>
        <?php
    }

    public function render_sync_section_desc()
    {
        ?>
        <p id="pod_sync_desc">
            <?php esc_html_e('Configure automatic product and order synchronization with your POD providers.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    public function render_printful_api_key_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['printful_api_key']) ? esc_attr($settings['printful_api_key']) : '';
        ?>
        <input
            type="password"
            id="printful_api_key"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printful_api_key]"
            value="<?php echo $value; ?>"
            class="regular-text"
            autocomplete="off"
            spellcheck="false"
        />
        <p class="description">
            <?php esc_html_e('Your Printful API authorization token. Keep this secret.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printful_markup_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['printful_default_markup']) ? (int) $settings['printful_default_markup'] : 30;
        ?>
        <input
            type="number"
            id="printful_default_markup"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printful_default_markup]"
            value="<?php echo $value; ?>"
            class="small-text"
            min="0"
            max="500"
            step="1"
        />
        <span>%</span>
        <p class="description">
            <?php esc_html_e('Percentage added to provider cost. Default: 30%.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printful_debug_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $checked  = !empty($settings['printful_debug_mode']) ? 'checked' : '';
        ?>
        <input
            type="checkbox"
            id="printful_debug_mode"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printful_debug_mode]"
            value="1"
            <?php echo $checked; ?>
        />
        <label for="printful_debug_mode">
            <?php esc_html_e('Log all API requests and responses to the Sync Log.', 'pod-aggregator'); ?>
        </label>
        <?php
    }

    public function render_auto_sync_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $checked  = !empty($settings['auto_sync_enabled']) ? 'checked' : '';
        ?>
        <input
            type="checkbox"
            id="auto_sync_enabled"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[auto_sync_enabled]"
            value="1"
            <?php echo $checked; ?>
        />
        <label for="auto_sync_enabled">
            <?php esc_html_e('Automatically sync products and orders on schedule.', 'pod-aggregator'); ?>
        </label>
        <?php
    }

    public function render_sync_interval_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['sync_interval_hours']) ? (int) $settings['sync_interval_hours'] : 6;
        ?>
        <input
            type="number"
            id="sync_interval_hours"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[sync_interval_hours]"
            value="<?php echo $value; ?>"
            class="small-text"
            min="1"
            max="168"
            step="1"
        />
        <span><?php esc_html_e('hours', 'pod-aggregator'); ?></span>
        <p class="description">
            <?php esc_html_e('How often to sync the product catalog. Default: 6 hours.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    // -------------------------------------------------------------------------
    // Sanitization
    // -------------------------------------------------------------------------

    /**
     * Sanitize all settings on save.
     *
     * @param mixed $input Raw input.
     * @return array Sanitized array.
     */
    public function sanitize_settings($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];

        // Printful API key — any non-empty string.
        $api_key = sanitize_text_field(wp_unslash($input['printful_api_key'] ?? ''));
        $sanitized['printful_api_key'] = !empty($api_key) ? $api_key : '';

        // Markup — integer 0–500.
        $sanitized['printful_default_markup'] = absint($input['printful_default_markup'] ?? 30);
        if ($sanitized['printful_default_markup'] > 500) {
            $sanitized['printful_default_markup'] = 500;
        }

        // Debug mode — 0 or 1.
        $sanitized['printful_debug_mode'] = !empty($input['printful_debug_mode']) ? '1' : '0';

        // Auto sync — 0 or 1.
        $sanitized['auto_sync_enabled'] = !empty($input['auto_sync_enabled']) ? '1' : '0';

        // Sync interval — 1–168 hours.
        $sanitized['sync_interval_hours'] = absint($input['sync_interval_hours'] ?? 6);
        if ($sanitized['sync_interval_hours'] < 1) {
            $sanitized['sync_interval_hours'] = 1;
        }

        // Validate Printful API key with a quick test request.
        if (!empty($sanitized['printful_api_key'])) {
            $this->validate_printful_key($sanitized['printful_api_key']);
        }

        return $sanitized;
    }

    /**
     * Validate Printful API key by calling the whoAmI endpoint.
     *
     * @param string $api_key
     * @return void
     */
    private function validate_printful_key(string $api_key)
    {
        $response = wp_remote_get(
            'https://api.printful.com/store',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            add_settings_error(
                self::SETTINGS_KEY,
                'printful_api_invalid',
                __('Printful API key could not be reached. Check your server connectivity.', 'pod-aggregator'),
                'error'
            );
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            add_settings_error(
                self::SETTINGS_KEY,
                'printful_api_invalid',
                sprintf(
                    /* translators: %d = HTTP status code */
                    __('Printful API key is invalid (HTTP %d). Please check and try again.', 'pod-aggregator'),
                    $code
                ),
                'error'
            );
        }
    }
}
