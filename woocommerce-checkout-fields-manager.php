<?php
/**
 * Plugin Name: WooCommerce Checkout Fields Manager
 * Plugin URI: https://smartifysolutions.com/
 * Description: Advanced manager for WooCommerce checkout fields with complete control over billing, shipping fields for Block-based checkout
 * Version: 1.0.0
 * Author: Smartify Solutions
 * Author URI: https://smartifysolutions.com/
 * Text Domain: woo-checkout-fields-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCFM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WCFM_VERSION', '1.0.0');
define('WCFM_TEXT_DOMAIN', 'woo-checkout-fields-manager');

/**
 * Main Plugin Class
 */
class WooCommerce_Checkout_Fields_Manager {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
        add_action('plugins_loaded', array($this, 'init'));
        
        // Load textdomain early but properly
        add_action('init', array($this, 'load_textdomain'), 1);
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Check database version and update if needed
        $this->check_database_version();
        
        // Load plugin files
        $this->load_files();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Load textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            WCFM_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Check database version and update if needed
     */
    private function check_database_version() {
        $installed_version = get_option('wcfm_db_version', '0');
        
        if (version_compare($installed_version, WCFM_VERSION, '<')) {
            $this->create_tables();
        }
    }
    
    /**
     * Load plugin files
     */
    private function load_files() {
        // Core files
        require_once WCFM_PLUGIN_PATH . 'includes/class-wcfm-core.php';
        require_once WCFM_PLUGIN_PATH . 'includes/class-wcfm-fields-handler.php';
        require_once WCFM_PLUGIN_PATH . 'includes/class-wcfm-block-integration.php';
        
        // Admin files
        if (is_admin()) {
            require_once WCFM_PLUGIN_PATH . 'admin/class-wcfm-admin.php';
            require_once WCFM_PLUGIN_PATH . 'admin/class-wcfm-settings.php';
        }
        
        // Frontend files
        if (!is_admin()) {
            require_once WCFM_PLUGIN_PATH . 'frontend/class-wcfm-frontend.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize core
        WCFM_Core::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            WCFM_Admin::get_instance();
            WCFM_Settings::get_instance();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            WCFM_Frontend::get_instance();
        }
        
        // Initialize fields handler and block integration
        WCFM_Fields_Handler::get_instance();
        WCFM_Block_Integration::get_instance();
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce Checkout Fields Manager requires WooCommerce to be installed and active.', WCFM_TEXT_DOMAIN); ?></p>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create templates directory if it doesn't exist
        $templates_dir = WCFM_PLUGIN_PATH . 'admin/templates/';
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }
        
        // Force create database tables
        $this->create_tables();
        
        // Set default options only if they don't exist
        if (!get_option('wcfm_settings')) {
            $default_options = array(
                'billing_fields' => array(
                    'billing_first_name' => array('enabled' => true, 'required' => true, 'priority' => 10),
                    'billing_last_name' => array('enabled' => true, 'required' => true, 'priority' => 20),
                    'billing_company' => array('enabled' => true, 'required' => false, 'priority' => 30),
                    'billing_country' => array('enabled' => true, 'required' => true, 'priority' => 40),
                    'billing_address_1' => array('enabled' => true, 'required' => true, 'priority' => 50),
                    'billing_address_2' => array('enabled' => true, 'required' => false, 'priority' => 60),
                    'billing_city' => array('enabled' => true, 'required' => true, 'priority' => 70),
                    'billing_state' => array('enabled' => true, 'required' => true, 'priority' => 80),
                    'billing_postcode' => array('enabled' => true, 'required' => true, 'priority' => 90),
                    'billing_phone' => array('enabled' => true, 'required' => true, 'priority' => 100),
                    'billing_email' => array('enabled' => true, 'required' => true, 'priority' => 110),
                ),
                'shipping_fields' => array(
                    'shipping_first_name' => array('enabled' => true, 'required' => true, 'priority' => 10),
                    'shipping_last_name' => array('enabled' => true, 'required' => true, 'priority' => 20),
                    'shipping_company' => array('enabled' => true, 'required' => false, 'priority' => 30),
                    'shipping_country' => array('enabled' => true, 'required' => true, 'priority' => 40),
                    'shipping_address_1' => array('enabled' => true, 'required' => true, 'priority' => 50),
                    'shipping_address_2' => array('enabled' => true, 'required' => false, 'priority' => 60),
                    'shipping_city' => array('enabled' => true, 'required' => true, 'priority' => 70),
                    'shipping_state' => array('enabled' => true, 'required' => true, 'priority' => 80),
                    'shipping_postcode' => array('enabled' => true, 'required' => true, 'priority' => 90),
                ),
                'additional_fields' => array(
                    'order_comments' => array('enabled' => true, 'required' => false, 'priority' => 10),
                ),
                'product_type_rules' => array(
                    'virtual_products' => array(
                        'hide_shipping' => true,
                        'hide_billing_address' => true,
                    ),
                    'downloadable_products' => array(
                        'hide_shipping' => true,
                        'hide_billing_address' => true,
                    ),
                ),
            );
            
            add_option('wcfm_settings', $default_options);
        }
        
        // Create database table for custom fields
        $this->create_tables();
        
        // Set activation flag
        update_option('wcfm_activated', true);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        
        // Always try to create the table
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            field_key varchar(100) NOT NULL,
            field_type varchar(50) NOT NULL,
            field_section varchar(50) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_placeholder varchar(255) DEFAULT '',
            field_options text,
            field_enabled tinyint(1) DEFAULT 1,
            field_required tinyint(1) DEFAULT 0,
            field_priority int(11) DEFAULT 10,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_key (field_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Log table creation result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Database table creation result: ' . print_r($result, true));
            
            // Check if table was created
            $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
            error_log('[WCFM] Table exists after creation: ' . ($table_exists ? 'YES' : 'NO'));
            
            if (!$table_exists) {
                error_log('[WCFM] Failed to create table. Last error: ' . $wpdb->last_error);
            }
        }
        
        // Update plugin version
        update_option('wcfm_db_version', WCFM_VERSION);
        
        return true;
    }
    
    /**
     * Manual database repair function - can be called externally
     */
    public static function manual_database_repair() {
        $instance = self::get_instance();
        return $instance->create_tables();
    }
}

/**
 * Global function for manual database repair
 */
function wcfm_manual_database_repair() {
    return WooCommerce_Checkout_Fields_Manager::manual_database_repair();
}

// Initialize plugin
WooCommerce_Checkout_Fields_Manager::get_instance();