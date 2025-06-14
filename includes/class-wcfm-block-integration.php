<?php
/**
 * WooCommerce Blocks Integration Class - Enhanced with Immediate Field Hiding
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
        
        // Hook into block checkout for field modification
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_scripts'));
        
        // Hook into Store API for field processing
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_block_checkout_fields'), 10, 2);
        
        // Extend checkout schema for our custom fields
        add_filter('woocommerce_store_api_checkout_schema', array($this, 'extend_checkout_schema'));
        
        // Update order meta for blocks
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'update_order_meta_blocks'), 10, 2);
        
        // Modify checkout fields for blocks with priority support
        add_filter('woocommerce_blocks_checkout_fields', array($this, 'modify_block_checkout_fields'), 10, 1);
        
        // Add custom validation for block checkout
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'validate_block_checkout_fields'));
        
        // Add CSS for immediate field hiding - CRITICAL FOR PREVENTING FLASHING
        add_action('wp_head', array($this, 'add_immediate_field_hiding_css'), 1);
        
        // Add inline CSS for field ordering
        add_action('wp_head', array($this, 'add_field_ordering_css'), 5);
    }
    
    /**
     * Add immediate field hiding CSS - This prevents fields from showing at all
     */
    public function add_immediate_field_hiding_css() {
        if (has_block('woocommerce/checkout')) {
            $settings = WCFM_Core::get_settings();
            
            echo '<style id="wcfm-immediate-hide" type="text/css">';
            echo '/* WCFM Immediate Field Hiding - Prevents Flash */';
            
            // Hide disabled fields immediately using CSS
            $sections = array('billing_fields', 'shipping_fields', 'additional_fields');
            
            foreach ($sections as $section) {
                if (isset($settings[$section])) {
                    foreach ($settings[$section] as $field_key => $field_config) {
                        // Hide disabled fields immediately
                        if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                            // Multiple selectors to ensure we catch the field
                            echo "
                                input[name=\"{$field_key}\"],
                                textarea[name=\"{$field_key}\"],
                                select[name=\"{$field_key}\"],
                                input[id=\"{$field_key}\"],
                                textarea[id=\"{$field_key}\"],
                                select[id=\"{$field_key}\"],
                                input[id*=\"{$field_key}\"],
                                input[name*=\"{$field_key}\"],
                                .wc-block-components-text-input:has(input[name=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[name=\"{$field_key}\"]),
                                .wc-block-components-text-input:has(input[id=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[id=\"{$field_key}\"]),
                                .wc-block-components-text-input:has(input[id*=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[id*=\"{$field_key}\"]) {
                                    display: none !important;
                                    visibility: hidden !important;
                                    opacity: 0 !important;
                                    height: 0 !important;
                                    overflow: hidden !important;
                                    margin: 0 !important;
                                    padding: 0 !important;
                                }
                            ";
                            
                            // Also hide based on common patterns
                            $field_base = str_replace(['billing_', 'shipping_'], '', $field_key);
                            echo "
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
                                }
                            ";
                        }
                    }
                }
            }
            
            // Add general classes for JavaScript to use
            echo '
                .wcfm-field-hidden {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                
                .wcfm-field-enabled {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }
                
                /* Ensure required indicators show properly */
                .wcfm-field-required label .required {
                    color: #e74c3c !important;
                    font-weight: bold !important;
                }
            ';
            
            echo '</style>';
        }
    }
    
    /**
     * Add CSS for field ordering
     */
    public function add_field_ordering_css() {
        if (has_block('woocommerce/checkout')) {
            $settings = WCFM_Core::get_settings();
            $css = '<style id="wcfm-field-ordering">';
            
            // Add ordering CSS for each field based on priority
            $sections = array('billing_fields', 'shipping_fields', 'additional_fields');
            
            foreach ($sections as $section) {
                if (isset($settings[$section])) {
                    foreach ($settings[$section] as $field_key => $field_config) {
                        if (isset($field_config['priority'])) {
                            $priority = intval($field_config['priority']);
                            
                            // Add CSS for field ordering
                            $css .= "
                                .wc-block-components-text-input:has(input[name=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[name=\"{$field_key}\"]),
                                .wc-block-components-text-input:has(input[id=\"{$field_key}\"]),
                                .wc-block-components-form-row:has(input[id=\"{$field_key}\"]) {
                                    order: {$priority} !important;
                                    -webkit-order: {$priority} !important;
                                    -ms-flex-order: {$priority} !important;
                                }
                            ";
                        }
                    }
                }
            }
            
            // Make parent containers use flexbox for ordering
            $css .= "
                .wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form,
                .wp-block-woocommerce-checkout-shipping-address-block .wc-block-components-address-form,
                .wp-block-woocommerce-checkout-contact-information-block,
                .wc-block-checkout__billing-fields,
                .wc-block-checkout__shipping-fields {
                    display: flex !important;
                    flex-direction: column !important;
                }
            ";
            
            $css .= '</style>';
            echo $css;
        }
    }
    
    /**
     * Register integration with WooCommerce Blocks
     */
    public function register_integration() {
        if (!class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            return;
        }
        
        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function($integration_registry) {
                if (method_exists($integration_registry, 'register')) {
                    $integration_registry->register(new WCFM_Blocks_Integration());
                }
            }
        );
    }
    
    /**
     * Enqueue scripts for block checkout
     */
    public function enqueue_block_scripts() {
        if (has_block('woocommerce/checkout') || has_block('woocommerce/cart')) {
            
            // Register and enqueue the block integration script
            wp_register_script(
                'wcfm-blocks-integration',
                WCFM_PLUGIN_URL . 'assets/js/blocks-integration.js',
                array(
                    'wp-element', 
                    'wp-components', 
                    'wp-blocks', 
                    'wp-hooks',
                    'wp-data',
                    'wc-blocks-checkout',
                    'wc-blocks-data-store'
                ),
                WCFM_VERSION,
                true
            );
            
            wp_enqueue_script('wcfm-blocks-integration');
            
            // Get settings for JavaScript
            $settings = WCFM_Core::get_settings();
            $field_settings = $this->prepare_field_settings_for_blocks();
            
            // Pass settings to JavaScript
            wp_localize_script('wcfm-blocks-integration', 'wcfmBlocksSettings', array(
                'fieldSettings' => $field_settings,
                'labels' => WCFM_Core::get_default_field_labels(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfm_blocks_nonce'),
                'isBlockCheckout' => true,
                'strings' => array(
                    'required_field_error' => __('This field is required.', WCFM_TEXT_DOMAIN),
                    'invalid_email' => __('Please enter a valid email address.', WCFM_TEXT_DOMAIN),
                    'invalid_phone' => __('Please enter a valid phone number.', WCFM_TEXT_DOMAIN),
                ),
            ));
        }
    }
    
    /**
     * Prepare field settings for block checkout
     */
    private function prepare_field_settings_for_blocks() {
        $settings = WCFM_Core::get_settings();
        $prepared_settings = array();
        
        // Process each section
        $sections = array('billing_fields', 'shipping_fields', 'additional_fields');
        
        foreach ($sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    $prepared_settings[$field_key] = array(
                        'enabled' => isset($field_config['enabled']) ? $field_config['enabled'] : true,
                        'required' => isset($field_config['required']) ? $field_config['required'] : false,
                        'priority' => isset($field_config['priority']) ? $field_config['priority'] : 10,
                        'label' => isset($field_config['label']) ? $field_config['label'] : '',
                        'section' => str_replace('_fields', '', $section),
                    );
                }
            }
        }
        
        return $prepared_settings;
    }
    
    /**
     * Modify checkout fields for blocks with enhanced priority support
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
        
        // Process additional fields
        if (isset($settings['additional_fields'])) {
            $fields = $this->process_block_fields($fields, $settings['additional_fields'], 'order');
        }
        
        // Sort fields by priority within each section
        foreach ($fields as $section_key => $section_fields) {
            if (is_array($section_fields)) {
                uasort($fields[$section_key], function($a, $b) {
                    $a_priority = isset($a['priority']) ? $a['priority'] : 10;
                    $b_priority = isset($b['priority']) ? $b['priority'] : 10;
                    return $a_priority - $b_priority;
                });
            }
        }
        
        return $fields;
    }
    
    /**
     * Process block fields with enhanced priority handling
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
            
            // Add custom attributes for CSS targeting
            if (!isset($fields[$section][$field_key]['custom_attributes'])) {
                $fields[$section][$field_key]['custom_attributes'] = array();
            }
            
            $fields[$section][$field_key]['custom_attributes']['data-wcfm-priority'] = isset($field_config['priority']) ? $field_config['priority'] : 10;
            $fields[$section][$field_key]['custom_attributes']['data-wcfm-field'] = $field_key;
            
            // Add classes for styling
            if (!isset($fields[$section][$field_key]['class'])) {
                $fields[$section][$field_key]['class'] = array();
            }
            
            $fields[$section][$field_key]['class'][] = 'wcfm-field';
            $fields[$section][$field_key]['class'][] = 'wcfm-field-' . str_replace('_', '-', $field_key);
            $fields[$section][$field_key]['class'][] = 'wcfm-priority-' . (isset($field_config['priority']) ? $field_config['priority'] : 10);
        }
        
        return $fields;
    }
    
    /**
     * Save custom fields from block checkout
     */
    public function save_block_checkout_fields($order, $request) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Saving block checkout data for order: ' . $order->get_id());
        }
        
        $settings = WCFM_Core::get_settings();
        
        // Get data from different sources in the request
        $billing_data = $request->get_param('billing_address') ?: array();
        $shipping_data = $request->get_param('shipping_address') ?: array();
        $extensions_data = $request->get_param('extensions') ?: array();
        
        // Save billing fields
        if (isset($settings['billing_fields'])) {
            foreach ($settings['billing_fields'] as $field_key => $field_config) {
                if (isset($field_config['enabled']) && $field_config['enabled']) {
                    // Try to get value from billing data
                    $field_value = null;
                    $billing_key = str_replace('billing_', '', $field_key);
                    
                    if (isset($billing_data[$billing_key])) {
                        $field_value = sanitize_text_field($billing_data[$billing_key]);
                    }
                    
                    // Also check direct field key
                    if (empty($field_value) && isset($billing_data[$field_key])) {
                        $field_value = sanitize_text_field($billing_data[$field_key]);
                    }
                    
                    if (!empty($field_value)) {
                        $order->update_meta_data($field_key, $field_value);
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("[WCFM] Saved billing field {$field_key}: {$field_value}");
                        }
                    }
                }
            }
        }
        
        // Save shipping fields
        if (isset($settings['shipping_fields'])) {
            foreach ($settings['shipping_fields'] as $field_key => $field_config) {
                if (isset($field_config['enabled']) && $field_config['enabled']) {
                    // Try to get value from shipping data
                    $field_value = null;
                    $shipping_key = str_replace('shipping_', '', $field_key);
                    
                    if (isset($shipping_data[$shipping_key])) {
                        $field_value = sanitize_text_field($shipping_data[$shipping_key]);
                    }
                    
                    // Also check direct field key
                    if (empty($field_value) && isset($shipping_data[$field_key])) {
                        $field_value = sanitize_text_field($shipping_data[$field_key]);
                    }
                    
                    if (!empty($field_value)) {
                        $order->update_meta_data($field_key, $field_value);
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("[WCFM] Saved shipping field {$field_key}: {$field_value}");
                        }
                    }
                }
            }
        }
        
        // Save additional fields from extensions
        if (isset($extensions_data['wcfm']) && is_array($extensions_data['wcfm'])) {
            foreach ($extensions_data['wcfm'] as $field_key => $field_value) {
                if (!empty($field_value)) {
                    $order->update_meta_data($field_key, sanitize_text_field($field_value));
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[WCFM] Saved additional field {$field_key}: {$field_value}");
                    }
                }
            }
        }
        
        return $order;
    }
    
    /**
     * Extend checkout schema for our custom fields
     */
    public function extend_checkout_schema($schema) {
        $settings = WCFM_Core::get_settings();
        
        // Add extensions for additional fields
        if (!isset($schema['properties']['extensions'])) {
            $schema['properties']['extensions'] = array(
                'type' => 'object',
                'properties' => array(),
            );
        }
        
        // Add our custom fields namespace
        $schema['properties']['extensions']['properties']['wcfm'] = array(
            'type' => 'object',
            'properties' => array(),
        );
        
        // Add additional fields to the schema
        if (isset($settings['additional_fields'])) {
            foreach ($settings['additional_fields'] as $field_key => $field_config) {
                if (isset($field_config['enabled']) && $field_config['enabled']) {
                    $schema['properties']['extensions']['properties']['wcfm']['properties'][$field_key] = array(
                        'type' => 'string',
                        'description' => isset($field_config['label']) ? $field_config['label'] : $field_key,
                    );
                }
            }
        }
        
        return $schema;
    }
    
    /**
     * Update order meta for blocks
     */
    public function update_order_meta_blocks($order, $request = null) {
        // If $request is null, try to get it from global context
        if ($request === null) {
            global $wp;
            if (isset($wp->query_vars['rest_route'])) {
                // We're in a REST request context, but don't have the request object
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] update_order_meta_blocks called without request object');
                }
                return $order;
            }
        }
        
        // If we still don't have a request, we can't process
        if ($request === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] update_order_meta_blocks: No request object available');
            }
            return $order;
        }
        
        // Call our main save method
        return $this->save_block_checkout_fields($order, $request);
    }
    
    /**
     * Validate block checkout fields
     */
    public function validate_block_checkout_fields($order) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Validating block checkout fields for order: ' . $order->get_id());
        }
        
        $settings = WCFM_Core::get_settings();
        $errors = array();
        
        // Validate billing fields
        if (isset($settings['billing_fields'])) {
            foreach ($settings['billing_fields'] as $field_key => $field_config) {
                if (isset($field_config['enabled']) && $field_config['enabled'] && 
                    isset($field_config['required']) && $field_config['required']) {
                    
                    $field_value = $order->get_meta($field_key);
                    
                    if (empty($field_value)) {
                        $labels = WCFM_Core::get_default_field_labels();
                        $field_label = isset($labels[$field_key]) ? $labels[$field_key] : $field_key;
                        $errors[] = sprintf(__('%s is a required field.', WCFM_TEXT_DOMAIN), $field_label);
                    }
                }
            }
        }
        
        // Validate shipping fields
        if (isset($settings['shipping_fields'])) {
            foreach ($settings['shipping_fields'] as $field_key => $field_config) {
                if (isset($field_config['enabled']) && $field_config['enabled'] && 
                    isset($field_config['required']) && $field_config['required']) {
                    
                    $field_value = $order->get_meta($field_key);
                    
                    if (empty($field_value)) {
                        $labels = WCFM_Core::get_default_field_labels();
                        $field_label = isset($labels[$field_key]) ? $labels[$field_key] : $field_key;
                        $errors[] = sprintf(__('%s is a required field.', WCFM_TEXT_DOMAIN), $field_label);
                    }
                }
            }
        }
        
        // Add errors to WooCommerce notices if any
        if (!empty($errors)) {
            foreach ($errors as $error) {
                wc_add_notice($error, 'error');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Validation error: ' . $error);
                }
            }
        }
    }
}

/**
 * WooCommerce Blocks Integration Implementation
 */
if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
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
                'fieldSettings' => $settings,
                'labels' => WCFM_Core::get_default_field_labels(),
                'enabled' => true,
                'pluginUrl' => WCFM_PLUGIN_URL,
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
                    'wp-hooks',
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
                    'wp-hooks',
                    'wp-data',
                    'wc-blocks-checkout',
                ),
                WCFM_VERSION,
                true
            );
        }
    }
}