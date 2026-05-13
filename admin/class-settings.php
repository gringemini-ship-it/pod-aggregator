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
                'network'           => true,
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

        // ---- Section: Printify API ----
        add_settings_section(
            'pod_aggregator_printify_section',
            __('Printify Configuration', 'pod-aggregator'),
            [$this, 'render_printify_section_desc'],
            'pod_aggregator_settings'
        );

        add_settings_field(
            'printify_api_key',
            __('Printify API Token', 'pod-aggregator'),
            [$this, 'render_printify_api_key_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printify_section'
        );

        add_settings_field(
            'printify_shop_id',
            __('Printify Shop ID', 'pod-aggregator'),
            [$this, 'render_printify_shop_id_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printify_section'
        );

        add_settings_field(
            'printify_default_markup',
            __('Default Markup (%)', 'pod-aggregator'),
            [$this, 'render_printify_markup_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printify_section'
        );

        add_settings_field(
            'printify_webhook_secret',
            __('Webhook Secret', 'pod-aggregator'),
            [$this, 'render_printify_webhook_secret_field'],
            'pod_aggregator_settings',
            'pod_aggregator_printify_section'
        );

        // ---- Section: Gelato API ----
        add_settings_section(
            'pod_aggregator_gelato_section',
            __('Gelato Configuration', 'pod-aggregator'),
            [$this, 'render_gelato_section_desc'],
            'pod_aggregator_settings'
        );

        add_settings_field(
            'gelato_api_key',
            __('Gelato API Token', 'pod-aggregator'),
            [$this, 'render_gelato_api_key_field'],
            'pod_aggregator_settings',
            'pod_aggregator_gelato_section'
        );

        add_settings_field(
            'gelato_default_markup',
            __('Default Markup (%)', 'pod-aggregator'),
            [$this, 'render_gelato_markup_field'],
            'pod_aggregator_settings',
            'pod_aggregator_gelato_section'
        );
    }

    // -------------------------------------------------------------------------
    // Section descriptions
    // -------------------------------------------------------------------------

    public function render_printful_section_desc()
    {
        $controller = new \POD_Aggregator\REST\Controller();
        $webhook_url = esc_url($controller->get_webhook_url('printful'));
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
        <p class="description">
            <strong><?php esc_html_e('Webhook URL:', 'pod-aggregator'); ?></strong>
            <code style="display:block;margin-top:4px;padding:6px;background:#f0f0f0;font-size:12px;word-break:break-all;"><?php echo $webhook_url; ?></code>
            <?php esc_html_e('Register this URL in your Printful dashboard under "Webhooks" to receive order status updates.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printify_section_desc()
    {
        $controller = new \POD_Aggregator\REST\Controller();
        $webhook_url = esc_url($controller->get_webhook_url('printify'));
        ?>
        <p id="pod_printify_desc">
            <?php
            printf(
                /* translators: %s = Printify developer docs URL */
                esc_html__('Enter your Printify API token and Shop ID to connect. Find your credentials at %s.', 'pod-aggregator'),
                '<a href="https://developers.printify.com/" target="_blank" rel="noopener">developers.printify.com</a>'
            );
            ?>
        </p>
        <p class="description">
            <?php esc_html_e('You can find your Shop ID in your Printify dashboard under "My Profile" → "Shops".', 'pod-aggregator'); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Webhook URL:', 'pod-aggregator'); ?></strong>
            <code style="display:block;margin-top:4px;padding:6px;background:#f0f0f0;font-size:12px;word-break:break-all;"><?php echo $webhook_url; ?></code>
            <?php esc_html_e('Register this URL in your Printify dashboard to receive order status updates.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printify_api_key_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['printify_api_key']) ? esc_attr($settings['printify_api_key']) : '';
        ?>
        <input
            type="password"
            id="printify_api_key"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printify_api_key]"
            value="<?php echo $value; ?>"
            class="regular-text"
            autocomplete="off"
            spellcheck="false"
        />
        <p class="description">
            <?php esc_html_e('Your Printify API token. Keep this secret.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printify_shop_id_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['printify_shop_id']) ? esc_attr($settings['printify_shop_id']) : '';
        ?>
        <input
            type="text"
            id="printify_shop_id"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printify_shop_id]"
            value="<?php echo $value; ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e('Your Printify Shop ID (numeric).', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printify_markup_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['printify_default_markup']) ? (int) $settings['printify_default_markup'] : 30;
        ?>
        <input
            type="number"
            id="printify_default_markup"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printify_default_markup]"
            value="<?php echo $value; ?>"
            class="small-text"
            min="0"
            max="500"
            step="1"
        />
        <span><?php esc_html_e('%', 'pod-aggregator'); ?></span>
        <p class="description">
            <?php esc_html_e('Percentage added to the provider cost. Default: 30%.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_printify_webhook_secret_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['printify_webhook_secret']) ? esc_attr($settings['printify_webhook_secret']) : '';
        ?>
        <input
            type="password"
            id="printify_webhook_secret"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[printify_webhook_secret]"
            value="<?php echo $value; ?>"
            class="regular-text"
            autocomplete="off"
            spellcheck="false"
        />
        <p class="description">
            <?php esc_html_e('Optional. Used to verify incoming Printify webhook signatures.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_gelato_section_desc()
    {
        $controller = new \POD_Aggregator\REST\Controller();
        $webhook_url = esc_url($controller->get_webhook_url('gelato'));
        ?>
        <p id="pod_gelato_desc">
            <?php
            printf(
                /* translators: %s = Gelato API docs URL */
                esc_html__('Enter your Gelato API token to connect. Find your token at %s.', 'pod-aggregator'),
                '<a href="https://www.gelato.com/developers" target="_blank" rel="noopener">gelato.com/developers</a>'
            );
            ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Webhook URL:', 'pod-aggregator'); ?></strong>
            <code style="display:block;margin-top:4px;padding:6px;background:#f0f0f0;font-size:12px;word-break:break-all;"><?php echo $webhook_url; ?></code>
            <?php esc_html_e('Register this URL in your Gelato dashboard to receive order status updates.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_gelato_api_key_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['gelato_api_key']) ? esc_attr($settings['gelato_api_key']) : '';
        ?>
        <input
            type="password"
            id="gelato_api_key"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[gelato_api_key]"
            value="<?php echo $value; ?>"
            class="regular-text"
            autocomplete="off"
            spellcheck="false"
        />
        <p class="description">
            <?php esc_html_e('Your Gelato API token. Keep this secret.', 'pod-aggregator'); ?>
        </p>
        <?php
    }

    public function render_gelato_markup_field()
    {
        $settings = get_site_option(self::SETTINGS_KEY, []);
        $value    = isset($settings['gelato_default_markup']) ? (int) $settings['gelato_default_markup'] : 30;
        ?>
        <input
            type="number"
            id="gelato_default_markup"
            name="<?php echo esc_attr(self::SETTINGS_KEY); ?>[gelato_default_markup]"
            value="<?php echo $value; ?>"
            class="small-text"
            min="0"
            max="500"
            step="1"
        />
        <span><?php esc_html_e('%', 'pod-aggregator'); ?></span>
        <p class="description">
            <?php esc_html_e('Percentage added to the provider cost. Default: 30%.', 'pod-aggregator'); ?>
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
        if ($sanitized['sync_interval_hours'] > 168) {
            $sanitized['sync_interval_hours'] = 168;
        }

        // Validate Printful API key with a quick test request.
        // If validation fails, clear the key so it is not saved.
        if (!empty($sanitized['printful_api_key'])) {
            $valid = $this->validate_printful_key($sanitized['printful_api_key']);
            if (!$valid) {
                $sanitized['printful_api_key'] = '';
            }
        }

        // Printify API token.
        $sanitized['printify_api_key'] = sanitize_text_field(wp_unslash($input['printify_api_key'] ?? ''));

        // Printify Shop ID — numeric string.
        $sanitized['printify_shop_id'] = sanitize_text_field(wp_unslash($input['printify_shop_id'] ?? ''));

        // Printify markup — integer 0–500.
        $sanitized['printify_default_markup'] = absint($input['printify_default_markup'] ?? 30);
        if ($sanitized['printify_default_markup'] > 500) {
            $sanitized['printify_default_markup'] = 500;
        }

        // Printify webhook secret — optional, any non-empty string.
        $sanitized['printify_webhook_secret'] = sanitize_text_field(wp_unslash($input['printify_webhook_secret'] ?? ''));

        // Gelato API token.
        $sanitized['gelato_api_key'] = sanitize_text_field(wp_unslash($input['gelato_api_key'] ?? ''));

        // Gelato markup — integer 0–500.
        $sanitized['gelato_default_markup'] = absint($input['gelato_default_markup'] ?? 30);
        if ($sanitized['gelato_default_markup'] > 500) {
            $sanitized['gelato_default_markup'] = 500;
        }

        return $sanitized;
    }

    /**
     * Validate Printful API key by calling the whoAmI endpoint.
     *
     * @param string $api_key
     * @return bool True if valid; false if invalid or unreachable.
     */
    private function validate_printful_key(string $api_key): bool
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
            return false;
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
            return false;
        }

        return true;
    }
}
