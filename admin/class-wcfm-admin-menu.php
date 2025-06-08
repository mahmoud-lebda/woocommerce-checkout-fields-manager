<?php
/**
 * Admin Menu Handler
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Admin Menu Class
 */
class WCFM_Admin_Menu {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('load-woocommerce_page_wcfm-checkout-fields', array($this, 'add_help_tabs'));
        add_filter('admin_body_class', array($this, 'admin_body_class'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Checkout Fields Manager', WCFM_TEXT_DOMAIN),
            __('Checkout Fields', WCFM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wcfm-checkout-fields',
            array($this, 'admin_page_callback')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page_callback() {
        $admin = WCFM_Admin::get_instance();
        $admin->admin_page();
    }
    
    /**
     * Add admin body classes
     */
    public function admin_body_class($classes) {
        if (isset($_GET['page']) && $_GET['page'] === 'wcfm-checkout-fields') {
            $classes .= ' wcfm-admin-page';
            
            // Add tab-specific class
            if (isset($_GET['tab'])) {
                $classes .= ' wcfm-tab-' . sanitize_html_class($_GET['tab']);
            }
            
            // Add WooCommerce version class
            if (defined('WC_VERSION')) {
                $wc_version = str_replace('.', '-', WC_VERSION);
                $classes .= ' wcfm-wc-' . sanitize_html_class($wc_version);
            }
            
            // Add Block Checkout support class
            if (class_exists('Automattic\WooCommerce\Blocks\Package')) {
                $classes .= ' wcfm-blocks-supported';
            }
        }
        
        return $classes;
    }
    
    /**
     * Display help tabs
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'wcfm-checkout-fields') === false) {
            return;
        }
        
        // Overview tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-overview',
            'title' => __('Overview', WCFM_TEXT_DOMAIN),
            'content' => $this->get_overview_help_content()
        ));
        
        // Field Management tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-fields',
            'title' => __('Field Management', WCFM_TEXT_DOMAIN),
            'content' => $this->get_fields_help_content()
        ));
        
        // Custom Fields tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-custom',
            'title' => __('Custom Fields', WCFM_TEXT_DOMAIN),
            'content' => $this->get_custom_fields_help_content()
        ));
        
        // Troubleshooting tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-troubleshooting',
            'title' => __('Troubleshooting', WCFM_TEXT_DOMAIN),
            'content' => $this->get_troubleshooting_help_content()
        ));
        
        // Sidebar
        $screen->set_help_sidebar($this->get_help_sidebar());
    }
    
    /**
     * Get overview help content
     */
    private function get_overview_help_content() {
        return '
            <h3>' . __('WooCommerce Checkout Fields Manager', WCFM_TEXT_DOMAIN) . '</h3>
            <p>' . __('This plugin allows you to customize WooCommerce checkout fields with complete control over visibility, requirements, and ordering.', WCFM_TEXT_DOMAIN) . '</p>
            <ul>
                <li>' . __('Show/hide any checkout field', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Make fields required or optional', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Reorder fields by drag and drop', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Create custom fields with validation', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Set rules based on product types', WCFM_TEXT_DOMAIN) . '</li>
            </ul>
        ';
    }
    
    /**
     * Get fields help content
     */
    private function get_fields_help_content() {
        return '
            <h3>' . __('Managing Checkout Fields', WCFM_TEXT_DOMAIN) . '</h3>
            <p>' . __('Use the toggle switches to enable/disable fields and make them required/optional.', WCFM_TEXT_DOMAIN) . '</p>
            <p><strong>' . __('Priority Numbers:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Lower numbers appear first. Default is 10.', WCFM_TEXT_DOMAIN) . '</p>
            <p><strong>' . __('Drag and Drop:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Drag the menu icon to reorder fields visually.', WCFM_TEXT_DOMAIN) . '</p>
            <p><strong>' . __('Search:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Use the search box to quickly find specific fields.', WCFM_TEXT_DOMAIN) . '</p>
        ';
    }
    
    /**
     * Get custom fields help content
     */
    private function get_custom_fields_help_content() {
        return '
            <h3>' . __('Creating Custom Fields', WCFM_TEXT_DOMAIN) . '</h3>
            <p>' . __('Add new fields to collect additional information during checkout.', WCFM_TEXT_DOMAIN) . '</p>
            <p><strong>' . __('Field Types:', WCFM_TEXT_DOMAIN) . '</strong></p>
            <ul>
                <li><strong>' . __('Text:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Single line text input', WCFM_TEXT_DOMAIN) . '</li>
                <li><strong>' . __('Textarea:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Multi-line text input', WCFM_TEXT_DOMAIN) . '</li>
                <li><strong>' . __('Select:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Dropdown with predefined options', WCFM_TEXT_DOMAIN) . '</li>
                <li><strong>' . __('Email:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Email validation included', WCFM_TEXT_DOMAIN) . '</li>
                <li><strong>' . __('Phone:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Phone number with validation', WCFM_TEXT_DOMAIN) . '</li>
            </ul>
        ';
    }
    
    /**
     * Get troubleshooting help content
     */
    private function get_troubleshooting_help_content() {
        return '
            <h3>' . __('Common Issues', WCFM_TEXT_DOMAIN) . '</h3>
            <p><strong>' . __('Fields not appearing:', WCFM_TEXT_DOMAIN) . '</strong></p>
            <ul>
                <li>' . __('Check if the field is enabled', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Clear any caching plugins', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Check for theme conflicts', WCFM_TEXT_DOMAIN) . '</li>
            </ul>
            <p><strong>' . __('Block Checkout issues:', WCFM_TEXT_DOMAIN) . '</strong></p>
            <ul>
                <li>' . __('Ensure WooCommerce Blocks is installed', WCFM_TEXT_DOMAIN) . '</li>
                <li>' . __('Check checkout page is using blocks', WCFM_TEXT_DOMAIN) . '</li>
            </ul>
        ';
    }
    
    /**
     * Get help sidebar
     */
    private function get_help_sidebar() {
        return '
            <p><strong>' . __('For More Information:', WCFM_TEXT_DOMAIN) . '</strong></p>
            <p><a href="https://smartifysolutions.com/" target="_blank">' . __('Visit Smartify Solutions', WCFM_TEXT_DOMAIN) . '</a></p>
            <p><a href="mailto:support@smartifysolutions.com">' . __('Contact Support', WCFM_TEXT_DOMAIN) . '</a></p>
        ';
    }
}