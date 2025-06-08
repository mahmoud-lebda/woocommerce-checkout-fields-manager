<?php
/**
 * Core functionality class
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
class WCFM_Core {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Add hooks
        $this->add_hooks();
    }
    
    /**
     * Add hooks
     */
    private function add_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_checkout() || is_wc_endpoint_url('order-pay')) {
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
            
            // Localize script
            wp_localize_script('wcfm-frontend-script', 'wcfm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfm_nonce'),
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wcfm') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wcfm-admin-style',
            WCFM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
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
            ),
        ));
    }
    
    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option('wcfm_settings', array());
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings($settings) {
        return update_option('wcfm_settings', $settings);
    }
    
    /**
     * Get field settings
     */
    public static function get_field_settings($section = null, $field = null) {
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
    public static function is_field_enabled($section, $field) {
        $field_settings = self::get_field_settings($section, $field);
        return isset($field_settings['enabled']) ? $field_settings['enabled'] : true;
    }
    
    /**
     * Check if field is required
     */
    public static function is_field_required($section, $field) {
        $field_settings = self::get_field_settings($section, $field);
        return isset($field_settings['required']) ? $field_settings['required'] : false;
    }
    
    /**
     * Get field priority
     */
    public static function get_field_priority($section, $field) {
        $field_settings = self::get_field_settings($section, $field);
        return isset($field_settings['priority']) ? $field_settings['priority'] : 10;
    }
    
    /**
     * Get default field labels
     */
    public static function get_default_field_labels() {
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
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] ' . $message);
        }
    }
}