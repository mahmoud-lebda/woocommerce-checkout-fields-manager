<?php

/**
 * Core functionality class - Enhanced for immediate field hiding
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Core Class
 */
class WCFM_Core
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize
     */
    public function init()
    {
        // Add hooks
        $this->add_hooks();
    }

    /**
     * Add hooks
     */
    private function add_hooks()
    {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add immediate field hiding CSS - HIGHEST PRIORITY
        add_action('wp_head', array($this, 'add_critical_field_hiding_css'), 1);
    }

    /**
     * Add critical field hiding CSS immediately - prevents any flash
     */
    public function add_critical_field_hiding_css()
    {
        if (is_checkout() && has_block('woocommerce/checkout')) {
            $settings = self::get_settings();
            
            if (empty($settings)) {
                return;
            }
            
            echo '<style id="wcfm-critical-hide" type="text/css">';
            echo '/* WCFM Critical Field Hiding - Loaded First */';
            
            $sections = array('billing_fields', 'shipping_fields', 'additional_fields');
            
            foreach ($sections as $section) {
                if (isset($settings[$section])) {
                    foreach ($settings[$section] as $field_key => $field_config) {
                        if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                            // Generate comprehensive CSS selectors
                            $field_base = str_replace(['billing_', 'shipping_'], '', $field_key);
                            
                            echo "
                                /* Hide {$field_key} */
                                input[name=\"{$field_key}\"],
                                textarea[name=\"{$field_key}\"],
                                select[name=\"{$field_key}\"],
                                input[id=\"{$field_key}\"],
                                textarea[id=\"{$field_key}\"],
                                select[id=\"{$field_key}\"],
                                input[id*=\"{$field_key}\"],
                                input[name*=\"{$field_key}\"],
                                input[id*=\"{$field_base}\"],
                                input[name*=\"{$field_base}\"],
                                .wc-block-components-text-input:has(input[name=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[name=\"{$field_key}\"]),
                                .wc-block-components-text-input:has(input[id=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[id=\"{$field_key}\"]),
                                .wc-block-components-text-input:has(input[id*=\"{$field_base}\"]),
                                .wc-block-components-form-row:has(input[id*=\"{$field_base}\"]),
                                .wc-block-components-text-input:has(input[name*=\"{$field_base}\"]),
                                .wc-block-components-form-row:has(input[name*=\"{$field_base}\"]) {
                                    display: none !important;
                                    visibility: hidden !important;
                                    opacity: 0 !important;
                                    height: 0 !important;
                                    overflow: hidden !important;
                                    margin: 0 !important;
                                    padding: 0 !important;
                                    position: absolute !important;
                                    left: -9999px !important;
                                    top: -9999px !important;
                                }
                            ";
                        }
                    }
                }
            }
            
            // Add helper classes
            echo '
                .wcfm-field-hidden {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    position: absolute !important;
                    left: -9999px !important;
                    top: -9999px !important;
                }
                
                .wcfm-field-enabled {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    position: relative !important;
                    left: auto !important;
                    top: auto !important;
                }
            ';
            
            echo '</style>';
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        if (is_checkout() || is_wc_endpoint_url('order-pay')) {
            // Always enqueue base styles and scripts for checkout pages
            wp_enqueue_style(
                'wcfm-frontend-style',
                WCFM_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WCFM_VERSION
            );

            wp_enqueue_script(
                'wcfm-frontend-script',
                WCFM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                WCFM_VERSION,
                true
            );

            // Localize script with basic data
            wp_localize_script('wcfm-frontend-script', 'wcfm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfm_nonce'),
            ));

            // Enhanced block checkout support
            if (has_block('woocommerce/checkout')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Block checkout detected, loading block assets');
                }

                // Enqueue block-specific styles with high priority
                wp_enqueue_style(
                    'wcfm-blocks-style',
                    WCFM_PLUGIN_URL . 'assets/css/blocks.css',
                    array('wcfm-frontend-style'),
                    WCFM_VERSION
                );

                // Enqueue block integration script with high priority
                wp_enqueue_script(
                    'wcfm-blocks-integration',
                    WCFM_PLUGIN_URL . 'assets/js/blocks-integration.js',
                    array('wcfm-frontend-script'),
                    WCFM_VERSION,
                    false // Load in head for immediate execution
                );

                // Localize with field settings for immediate access
                $field_settings = self::get_frontend_settings();
                wp_localize_script('wcfm-blocks-integration', 'wcfmBlocksSettings', array(
                    'fieldSettings' => $field_settings,
                    'isBlockCheckout' => true,
                    'strings' => array(
                        'required_field_error' => __('This field is required.', WCFM_TEXT_DOMAIN),
                        'invalid_email' => __('Please enter a valid email address.', WCFM_TEXT_DOMAIN),
                        'invalid_phone' => __('Please enter a valid phone number.', WCFM_TEXT_DOMAIN),
                    ),
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wcfm_blocks_nonce'),
                ));

                // Add body class for block checkout
                add_filter('body_class', function ($classes) {
                    $classes[] = 'wcfm-block-checkout-detected';
                    return $classes;
                });
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Classic checkout detected');
                }

                // Add body class for classic checkout
                add_filter('body_class', function ($classes) {
                    $classes[] = 'wcfm-classic-checkout-detected';
                    return $classes;
                });
            }
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, 'wcfm') === false && strpos($hook, 'woocommerce_page_wcfm-checkout-fields') === false) {
            return;
        }

        // Enqueue Dashicons for admin pages
        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'wcfm-admin-style',
            WCFM_PLUGIN_URL . 'assets/css/admin.css',
            array('dashicons'),
            WCFM_VERSION
        );

        wp_enqueue_script(
            'wcfm-admin-script',
            WCFM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            WCFM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wcfm-admin-script', 'wcfm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcfm_admin_nonce'),
            'strings' => array(
                'save_success' => __('Settings saved successfully!', WCFM_TEXT_DOMAIN),
                'save_error' => __('Error saving settings. Please try again.', WCFM_TEXT_DOMAIN),
                'confirm_delete' => __('Are you sure you want to delete this field?', WCFM_TEXT_DOMAIN),
                'confirm_reset' => __('Are you sure you want to reset all settings to defaults?', WCFM_TEXT_DOMAIN),
                'unsaved_changes' => __('You have unsaved changes. Are you sure you want to leave?', WCFM_TEXT_DOMAIN),
            ),
        ));
    }

    /**
     * Get plugin settings
     */
    public static function get_settings()
    {
        return get_option('wcfm_settings', array());
    }

    /**
     * Update plugin settings
     */
    public static function update_settings($settings)
    {
        // Check if database table exists first
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);

        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM Core] Database table missing, attempting to create...');
            }

            // Try to create the table
            $plugin_instance = WooCommerce_Checkout_Fields_Manager::get_instance();
            if (method_exists($plugin_instance, 'create_tables')) {
                // Call private method via reflection
                $reflection = new ReflectionClass($plugin_instance);
                $method = $reflection->getMethod('create_tables');
                $method->setAccessible(true);
                $method->invoke($plugin_instance);
            }
        }

        // Validate settings before saving
        if (!is_array($settings)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM Core] Settings must be an array, received: ' . gettype($settings));
            }
            return false;
        }

        // Get current settings to merge
        $current_settings = self::get_settings();

        // Merge with current settings to prevent data loss
        $merged_settings = array_merge($current_settings, $settings);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM Core] Updating settings with: ' . print_r($merged_settings, true));
        }

        $result = update_option('wcfm_settings', $merged_settings);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM Core] Update result: ' . ($result ? 'success' : 'failed'));

            if (!$result) {
                error_log('[WCFM Core] WordPress last error: ' . $wpdb->last_error);
            }
        }

        return $result;
    }

    /**
     * Get field settings
     */
    public static function get_field_settings($section = null, $field = null)
    {
        $settings = self::get_settings();

        if ($section && $field) {
            return isset($settings[$section][$field]) ? $settings[$section][$field] : array();
        }

        if ($section) {
            return isset($settings[$section]) ? $settings[$section] : array();
        }

        return $settings;
    }

    /**
     * Check if field is enabled
     */
    public static function is_field_enabled($section, $field)
    {
        $field_settings = self::get_field_settings($section, $field);
        return isset($field_settings['enabled']) ? $field_settings['enabled'] : true;
    }

    /**
     * Check if field is required
     */
    public static function is_field_required($section, $field)
    {
        $field_settings = self::get_field_settings($section, $field);
        return isset($field_settings['required']) ? $field_settings['required'] : false;
    }

    /**
     * Get field priority
     */
    public static function get_field_priority($section, $field)
    {
        $field_settings = self::get_field_settings($section, $field);
        return isset($field_settings['priority']) ? $field_settings['priority'] : 10;
    }

    /**
     * Get default field labels
     */
    public static function get_default_field_labels()
    {
        return array(
            'billing_first_name' => __('First Name', WCFM_TEXT_DOMAIN),
            'billing_last_name' => __('Last Name', WCFM_TEXT_DOMAIN),
            'billing_company' => __('Company Name', WCFM_TEXT_DOMAIN),
            'billing_country' => __('Country / Region', WCFM_TEXT_DOMAIN),
            'billing_address_1' => __('Street Address', WCFM_TEXT_DOMAIN),
            'billing_address_2' => __('Apartment, suite, etc.', WCFM_TEXT_DOMAIN),
            'billing_city' => __('Town / City', WCFM_TEXT_DOMAIN),
            'billing_state' => __('State / County', WCFM_TEXT_DOMAIN),
            'billing_postcode' => __('Postcode / ZIP', WCFM_TEXT_DOMAIN),
            'billing_phone' => __('Phone', WCFM_TEXT_DOMAIN),
            'billing_email' => __('Email Address', WCFM_TEXT_DOMAIN),
            'shipping_first_name' => __('First Name', WCFM_TEXT_DOMAIN),
            'shipping_last_name' => __('Last Name', WCFM_TEXT_DOMAIN),
            'shipping_company' => __('Company Name', WCFM_TEXT_DOMAIN),
            'shipping_country' => __('Country / Region', WCFM_TEXT_DOMAIN),
            'shipping_address_1' => __('Street Address', WCFM_TEXT_DOMAIN),
            'shipping_address_2' => __('Apartment, suite, etc.', WCFM_TEXT_DOMAIN),
            'shipping_city' => __('Town / City', WCFM_TEXT_DOMAIN),
            'shipping_state' => __('State / County', WCFM_TEXT_DOMAIN),
            'shipping_postcode' => __('Postcode / ZIP', WCFM_TEXT_DOMAIN),
            'order_comments' => __('Order Notes', WCFM_TEXT_DOMAIN),
        );
    }

    /**
     * Log debug information
     */
    public static function log($message, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] ' . $message);
        }
    }

    /**
     * Get frontend settings for JavaScript
     */
    public static function get_frontend_settings()
    {
        $settings = self::get_settings();
        $frontend_settings = array();

        // Process all field sections
        $sections = array('billing_fields', 'shipping_fields', 'additional_fields');

        foreach ($sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    $frontend_settings[$field_key] = array(
                        'enabled' => isset($field_config['enabled']) ? $field_config['enabled'] : true,
                        'required' => isset($field_config['required']) ? $field_config['required'] : false,
                        'priority' => isset($field_config['priority']) ? $field_config['priority'] : 10,
                        'label' => isset($field_config['label']) ? $field_config['label'] : '',
                        'section' => str_replace('_fields', '', $section),
                        'validation' => isset($field_config['validation']) ? $field_config['validation'] : array(),
                    );
                }
            }
        }

        return $frontend_settings;
    }

    /**
     * Check if current page has block checkout
     */
    public static function is_block_checkout()
    {
        return is_checkout() && has_block('woocommerce/checkout');
    }

    /**
     * Check if current page has classic checkout
     */
    public static function is_classic_checkout()
    {
        return is_checkout() && !has_block('woocommerce/checkout');
    }

    /**
     * Get disabled fields for immediate CSS hiding
     */
    public static function get_disabled_fields()
    {
        $settings = self::get_settings();
        $disabled_fields = array();

        $sections = array('billing_fields', 'shipping_fields', 'additional_fields');

        foreach ($sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                        $disabled_fields[] = $field_key;
                    }
                }
            }
        }

        return $disabled_fields;
    }

    /**
     * Generate CSS selectors for field hiding
     */
    public static function generate_field_hide_css($field_key)
    {
        $field_base = str_replace(['billing_', 'shipping_'], '', $field_key);
        
        $selectors = array(
            "input[name=\"{$field_key}\"]",
            "textarea[name=\"{$field_key}\"]",
            "select[name=\"{$field_key}\"]",
            "input[id=\"{$field_key}\"]",
            "textarea[id=\"{$field_key}\"]",
            "select[id=\"{$field_key}\"]",
            "input[id*=\"{$field_key}\"]",
            "input[name*=\"{$field_key}\"]",
            "input[id*=\"{$field_base}\"]",
            "input[name*=\"{$field_base}\"]",
            ".wc-block-components-text-input:has(input[name=\"{$field_key}\"])",
            ".wc-block-components-form-row:has(input[name=\"{$field_key}\"])",
            ".wc-block-components-text-input:has(input[id=\"{$field_key}\"])",
            ".wc-block-components-form-row:has(input[id=\"{$field_key}\"])",
            ".wc-block-components-text-input:has(input[id*=\"{$field_base}\"])",
            ".wc-block-components-form-row:has(input[id*=\"{$field_base}\"])",
            ".wc-block-components-text-input:has(input[name*=\"{$field_base}\"])",
            ".wc-block-components-form-row:has(input[name*=\"{$field_base}\"])"
        );

        return implode(',', $selectors);
    }

    /**
     * Add inline critical CSS for immediate field hiding
     */
    public static function add_inline_critical_css()
    {
        if (!is_checkout() || !has_block('woocommerce/checkout')) {
            return;
        }

        $disabled_fields = self::get_disabled_fields();
        
        if (empty($disabled_fields)) {
            return;
        }

        echo '<style id="wcfm-inline-critical">';
        echo '/* WCFM Inline Critical CSS */';
        
        foreach ($disabled_fields as $field_key) {
            $css_selectors = self::generate_field_hide_css($field_key);
            echo $css_selectors . ' { display: none !important; visibility: hidden !important; opacity: 0 !important; }';
        }
        
        echo '</style>';
    }

    /**
     * Check if we should apply immediate hiding
     */
    public static function should_apply_immediate_hiding()
    {
        // Only apply on block checkout pages
        if (!is_checkout() || !has_block('woocommerce/checkout')) {
            return false;
        }

        // Check if there are any disabled fields
        $disabled_fields = self::get_disabled_fields();
        return !empty($disabled_fields);
    }
}