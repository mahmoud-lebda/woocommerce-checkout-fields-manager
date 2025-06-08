<?php
/**
 * Admin Notices Handler
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Admin Notices Class
 */
class WCFM_Admin_Notices {
    
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
        add_action('admin_notices', array($this, 'display_notices'));
    }
    
    /**
     * Display admin notices
     */
    public function display_notices() {
        // Only show on plugin pages or when relevant
        if (!$this->should_show_notices()) {
            return;
        }
        
        // Check for compatibility issues
        $this->check_compatibility_notices();
        
        // Check for plugin conflicts
        $this->check_plugin_conflicts();
        
        // Check database status
        $this->check_database_status();
        
        // Display success/error messages
        $this->display_status_messages();
    }
    
    /**
     * Should show notices
     */
    private function should_show_notices() {
        $screen = get_current_screen();
        
        // Show on plugin pages
        if (isset($_GET['page']) && $_GET['page'] === 'wcfm-checkout-fields') {
            return true;
        }
        
        // Show on plugins page
        if ($screen && $screen->id === 'plugins') {
            return true;
        }
        
        // Show on WooCommerce pages
        if ($screen && strpos($screen->id, 'woocommerce') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check compatibility notices
     */
    private function check_compatibility_notices() {
        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '7.0', '<')) {
            $this->display_notice(
                sprintf(
                    __('WooCommerce Checkout Fields Manager recommends WooCommerce 7.0 or higher. You are currently running version %s.', WCFM_TEXT_DOMAIN),
                    WC_VERSION
                ),
                'warning'
            );
        }
        
        // Check for Block Checkout
        if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
            $this->display_notice(
                __('For full Block Checkout support, please ensure WooCommerce Blocks plugin is installed and active.', WCFM_TEXT_DOMAIN),
                'info'
            );
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $this->display_notice(
                sprintf(
                    __('WooCommerce Checkout Fields Manager requires PHP 7.4 or higher. You are currently running version %s. Please contact your hosting provider to upgrade.', WCFM_TEXT_DOMAIN),
                    PHP_VERSION
                ),
                'error'
            );
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $this->display_notice(
                sprintf(
                    __('WooCommerce Checkout Fields Manager requires WordPress 5.0 or higher. You are currently running version %s.', WCFM_TEXT_DOMAIN),
                    get_bloginfo('version')
                ),
                'warning'
            );
        }
    }
    
    /**
     * Check for plugin conflicts
     */
    private function check_plugin_conflicts() {
        $conflicting_plugins = array(
            'woocommerce-checkout-field-editor/checkout-field-editor.php' => 'WooCommerce Checkout Field Editor',
            'wc-checkout-field-editor/wc-checkout-field-editor.php' => 'Checkout Field Editor for WooCommerce',
            'flexible-checkout-fields/flexible-checkout-fields.php' => 'Flexible Checkout Fields',
            'checkout-field-editor/checkout-field-editor.php' => 'Checkout Field Editor',
            'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' => 'Brazilian Market on WooCommerce',
        );
        
        $active_conflicts = array();
        
        foreach ($conflicting_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $active_conflicts[] = $plugin_name;
            }
        }
        
        if (!empty($active_conflicts)) {
            $this->display_notice(
                '<strong>' . __('Plugin Conflict Warning:', WCFM_TEXT_DOMAIN) . '</strong><br>' .
                sprintf(
                    __('The following plugins may conflict with WooCommerce Checkout Fields Manager: %s. Consider deactivating them to avoid conflicts.', WCFM_TEXT_DOMAIN),
                    implode(', ', $active_conflicts)
                ),
                'warning'
            );
        }
    }
    
    /**
     * Check database status
     */
    private function check_database_status() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
        
        if (!$table_exists) {
            $repair_url = admin_url('admin.php?page=wcfm-checkout-fields&tab=advanced');
            
            $this->display_notice(
                sprintf(
                    __('Database table for custom fields is missing. Please go to the <a href="%s">Advanced tab</a> and click "Repair Database Tables".', WCFM_TEXT_DOMAIN),
                    $repair_url
                ),
                'error'
            );
        }
    }
    
    /**
     * Display status messages
     */
    private function display_status_messages() {
        // Check for messages in URL
        if (isset($_GET['wcfm_message'])) {
            $message = sanitize_text_field($_GET['wcfm_message']);
            $type = isset($_GET['wcfm_type']) ? sanitize_text_field($_GET['wcfm_type']) : 'success';
            
            $this->display_notice($message, $type, true);
        }
        
        // Check for transient messages
        $transient_message = get_transient('wcfm_admin_notice');
        if ($transient_message) {
            $this->display_notice(
                $transient_message['message'],
                $transient_message['type'] ?? 'info',
                true
            );
            delete_transient('wcfm_admin_notice');
        }
    }
    
    /**
     * Display notice
     */
    private function display_notice($message, $type = 'info', $is_dismissible = false) {
        $classes = array('notice', 'notice-' . $type);
        
        if ($is_dismissible) {
            $classes[] = 'is-dismissible';
        }
        
        printf(
            '<div class="%s"><p>%s</p></div>',
            esc_attr(implode(' ', $classes)),
            wp_kses_post($message)
        );
    }
    
    /**
     * Add notice to transient (for delayed display)
     */
    public static function add_notice($message, $type = 'info') {
        set_transient('wcfm_admin_notice', array(
            'message' => $message,
            'type' => $type,
        ), 60); // Show for 1 minute
    }
    
    /**
     * Check theme compatibility
     */
    private function check_theme_compatibility() {
        $theme = wp_get_theme();
        $known_incompatible_themes = array(
            'Divi' => array(
                'message' => __('Divi theme may require additional CSS customization for optimal field display.', WCFM_TEXT_DOMAIN),
                'type' => 'info'
            ),
            'Avada' => array(
                'message' => __('Avada theme users should check field styling in checkout page.', WCFM_TEXT_DOMAIN),
                'type' => 'info'
            ),
        );
        
        $theme_name = $theme->get('Name');
        
        if (isset($known_incompatible_themes[$theme_name])) {
            $theme_info = $known_incompatible_themes[$theme_name];
            $this->display_notice($theme_info['message'], $theme_info['type']);
        }
    }
    
    /**
     * Check for WooCommerce settings conflicts
     */
    private function check_woocommerce_settings() {
        // Check if checkout page exists
        $checkout_page_id = wc_get_page_id('checkout');
        if ($checkout_page_id === -1 || get_post_status($checkout_page_id) !== 'publish') {
            $this->display_notice(
                __('WooCommerce checkout page is not properly configured. This may affect field display.', WCFM_TEXT_DOMAIN),
                'warning'
            );
        }
        
        // Check if guest checkout is enabled when email field is disabled
        $settings = WCFM_Core::get_settings();
        $guest_checkout = get_option('woocommerce_enable_guest_checkout', 'yes');
        $email_enabled = isset($settings['billing_fields']['billing_email']['enabled']) ? 
                        $settings['billing_fields']['billing_email']['enabled'] : true;
        
        if ($guest_checkout === 'yes' && !$email_enabled) {
            $this->display_notice(
                __('Guest checkout is enabled but email field is disabled. This may cause checkout issues.', WCFM_TEXT_DOMAIN),
                'warning'
            );
        }
    }
    
    /**
     * Display plugin activation notice
     */
    public static function display_activation_notice() {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('WooCommerce Checkout Fields Manager activated!', WCFM_TEXT_DOMAIN); ?></strong>
                    <br>
                    <?php 
                    printf(
                        __('Go to <a href="%s">WooCommerce > Checkout Fields</a> to start customizing your checkout fields.', WCFM_TEXT_DOMAIN),
                        admin_url('admin.php?page=wcfm-checkout-fields')
                    ); 
                    ?>
                </p>
            </div>
            <?php
        });
    }
    
    /**
     * Display plugin deactivation notice
     */
    public static function display_deactivation_notice() {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('WooCommerce Checkout Fields Manager deactivated.', WCFM_TEXT_DOMAIN); ?></strong>
                    <br>
                    <?php _e('Your field settings have been preserved and will be restored when you reactivate the plugin.', WCFM_TEXT_DOMAIN); ?>
                </p>
            </div>
            <?php
        });
    }
}