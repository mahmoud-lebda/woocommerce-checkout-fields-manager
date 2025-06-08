<?php
/**
 * WooCommerce Blocks Integration Class
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Block Integration Class
 */
class WCFM_Block_Integration {
    
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
        $this->init();
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Check if WooCommerce Blocks is available
        if (class_exists('Automattic\WooCommerce\Blocks\Package')) {
            add_action('woocommerce_blocks_loaded', array($this, 'register_integration'));
        }
        
        // Hook into block checkout
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_scripts'));
        add_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_block_checkout_fields'), 10, 2);
    }
    
    /**
     * Register integration with WooCommerce Blocks
     */
    public function register_integration() {
        if (!class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            return;
        }
        
        require_once WCFM_PLUGIN_PATH . 'includes/class-wcfm-blocks-integration.php';
        
        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function($integration_registry) {
                $integration_registry->register(new WCFM_Blocks_Integration());
            }
        );
    }
    
    /**
     * Enqueue scripts for block checkout
     */
    public function enqueue_block_scripts() {
        if (has_block('woocommerce/checkout') || has_block('woocommerce/cart')) {
            wp_enqueue_script(
                'wcfm-blocks-integration',
                WCFM_PLUGIN_URL . 'assets/js/blocks-integration.js',
                array('wp-element', 'wp-components', 'wp-blocks', 'wc-blocks-checkout'),
                WCFM_VERSION,
                true
            );
            
            // Pass settings to JavaScript
            $settings = WCFM_Core::get_settings();
            wp_localize_script('wcfm-blocks-integration', 'wcfmBlocksSettings', array(
                'fields' => $settings,
                'labels' => WCFM_Core::get_default_field_labels(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfm_blocks_nonce'),
            ));
        }
    }
    
    /**
     * Save custom fields from block checkout
     */
    public function save_block_checkout_fields($order, $request) {
        $settings = WCFM_Core::get_settings();
        
        // Get custom field data from request
        $custom_fields = $request->get_param('extensions');
        
        if (isset($custom_fields['wcfm']) && is_array($custom_fields['wcfm'])) {
            foreach ($custom_fields['wcfm'] as $field_key => $field_value) {
                if (!empty($field_value)) {
                    $order->update_meta_data($field_key, sanitize_text_field($field_value));
                }
            }
        }
        
        return $order;
    }
    
    /**
     * Modify checkout fields for blocks
     */
    public function modify_block_checkout_fields($fields) {
        $settings = WCFM_Core::get_settings();
        
        // Process billing fields
        if (isset($settings['billing_fields'])) {
            $fields = $this->process_block_fields($fields, $settings['billing_fields'], 'billing');
        }
        
        // Process shipping fields
        if (isset($settings['shipping_fields'])) {
            $fields = $this->process_block_fields($fields, $settings['shipping_fields'], 'shipping');
        }
        
        return $fields;
    }
    
    /**
     * Process block fields
     */
    private function process_block_fields($fields, $field_settings, $section) {
        foreach ($field_settings as $field_key => $field_config) {
            // Skip disabled fields
            if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                // Remove field if it exists
                if (isset($fields[$section]) && isset($fields[$section][$field_key])) {
                    unset($fields[$section][$field_key]);
                }
                continue;
            }
            
            // Ensure field exists in the section
            if (!isset($fields[$section])) {
                $fields[$section] = array();
            }
            
            if (!isset($fields[$section][$field_key])) {
                $fields[$section][$field_key] = array();
            }
            
            // Apply configuration
            if (isset($field_config['required'])) {
                $fields[$section][$field_key]['required'] = $field_config['required'];
            }
            
            if (isset($field_config['label'])) {
                $fields[$section][$field_key]['label'] = $field_config['label'];
            }
            
            if (isset($field_config['priority'])) {
                $fields[$section][$field_key]['priority'] = $field_config['priority'];
            }
        }
        
        return $fields;
    }
    
    /**
     * Add custom fields to block checkout schema
     */
    public function extend_checkout_schema($schema) {
        $settings = WCFM_Core::get_settings();
        $custom_fields = array();
        
        // Collect all enabled custom fields
        $all_sections = array('billing_fields', 'shipping_fields', 'additional_fields');
        
        foreach ($all_sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    if (isset($field_config['enabled']) && $field_config['enabled']) {
                        $custom_fields[$field_key] = array(
                            'description' => sprintf(__('Custom field: %s', WCFM_TEXT_DOMAIN), $field_key),
                            'type' => 'string',
                            'required' => isset($field_config['required']) ? $field_config['required'] : false,
                        );
                    }
                }
            }
        }
        
        if (!empty($custom_fields)) {
            $schema['properties']['extensions']['properties']['wcfm'] = array(
                'type' => 'object',
                'properties' => $custom_fields,
            );
        }
        
        return $schema;
    }
}

/**
 * WooCommerce Blocks Integration Implementation
 */
class WCFM_Blocks_Integration implements Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
    
    /**
     * The name of the integration
     */
    public function get_name() {
        return 'wcfm-checkout-fields-manager';
    }
    
    /**
     * When called invokes any initialization/setup for the integration
     */
    public function initialize() {
        $this->register_block_editor_script();
        $this->register_block_frontend_script();
    }
    
    /**
     * Returns an array of script handles to enqueue in the frontend context
     */
    public function get_script_handles() {
        return array('wcfm-blocks-frontend');
    }
    
    /**
     * Returns an array of script handles to enqueue in the editor context
     */
    public function get_editor_script_handles() {
        return array('wcfm-blocks-editor');
    }
    
    /**
     * An array of key, value pairs of data made available to the block on the client side
     */
    public function get_script_data() {
        $settings = WCFM_Core::get_settings();
        
        return array(
            'fields' => $settings,
            'labels' => WCFM_Core::get_default_field_labels(),
            'enabled' => true,
        );
    }
    
    /**
     * Register block editor script
     */
    private function register_block_editor_script() {
        wp_register_script(
            'wcfm-blocks-editor',
            WCFM_PLUGIN_URL . 'assets/js/blocks-editor.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-i18n',
                'wc-blocks-checkout',
            ),
            WCFM_VERSION,
            true
        );
    }
    
    /**
     * Register block frontend script
     */
    private function register_block_frontend_script() {
        wp_register_script(
            'wcfm-blocks-frontend',
            WCFM_PLUGIN_URL . 'assets/js/blocks-frontend.js',
            array(
                'wp-element',
                'wp-components',
                'wc-blocks-checkout',
            ),
            WCFM_VERSION,
            true
        );
    }
}