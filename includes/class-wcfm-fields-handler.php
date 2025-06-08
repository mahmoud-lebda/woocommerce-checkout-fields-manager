<?php
/**
 * Fields Handler Class
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Fields Handler Class
 */
class WCFM_Fields_Handler {
    
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
        // Hook into WooCommerce checkout fields
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 20);
        
        // Handle product type specific rules
        add_action('woocommerce_before_checkout_form', array($this, 'apply_product_type_rules'));
        
        // Handle field validation
        add_action('woocommerce_checkout_process', array($this, 'validate_custom_fields'));
        
        // Save custom field data
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_field_data'));
        
        // Display custom fields in order details
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_custom_fields_in_admin'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_custom_fields_in_order'));
    }
    
    /**
     * Customize checkout fields based on settings
     */
    public function customize_checkout_fields($fields) {
        $settings = WCFM_Core::get_settings();
        
        // Handle billing fields
        if (isset($settings['billing_fields'])) {
            $fields['billing'] = $this->process_field_section($fields['billing'], $settings['billing_fields'], 'billing');
        }
        
        // Handle shipping fields
        if (isset($settings['shipping_fields'])) {
            $fields['shipping'] = $this->process_field_section($fields['shipping'], $settings['shipping_fields'], 'shipping');
        }
        
        // Handle additional fields
        if (isset($settings['additional_fields'])) {
            $fields['order'] = $this->process_field_section(
                isset($fields['order']) ? $fields['order'] : array(),
                $settings['additional_fields'],
                'order'
            );
        }
        
        return $fields;
    }
    
    /**
     * Process field section
     */
    private function process_field_section($existing_fields, $field_settings, $section) {
        $processed_fields = array();
        
        foreach ($field_settings as $field_key => $field_config) {
            // Skip disabled fields
            if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                continue;
            }
            
            // Get existing field or create new one
            if (isset($existing_fields[$field_key])) {
                $field = $existing_fields[$field_key];
            } else {
                $field = $this->get_default_field_config($field_key, $section);
            }
            
            // Apply custom configuration
            if (isset($field_config['required'])) {
                $field['required'] = $field_config['required'];
            }
            
            if (isset($field_config['priority'])) {
                $field['priority'] = $field_config['priority'];
            }
            
            if (isset($field_config['label'])) {
                $field['label'] = $field_config['label'];
            }
            
            if (isset($field_config['placeholder'])) {
                $field['placeholder'] = $field_config['placeholder'];
            }
            
            if (isset($field_config['class'])) {
                $field['class'] = $field_config['class'];
            }
            
            $processed_fields[$field_key] = $field;
        }
        
        // Sort by priority
        uasort($processed_fields, function($a, $b) {
            $a_priority = isset($a['priority']) ? $a['priority'] : 10;
            $b_priority = isset($b['priority']) ? $b['priority'] : 10;
            return $a_priority - $b_priority;
        });
        
        return $processed_fields;
    }
    
    /**
     * Get default field configuration
     */
    private function get_default_field_config($field_key, $section) {
        $labels = WCFM_Core::get_default_field_labels();
        
        $default_config = array(
            'type' => 'text',
            'label' => isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key)),
            'required' => false,
            'class' => array('form-row-wide'),
            'priority' => 10,
        );
        
        // Special field configurations
        switch ($field_key) {
            case 'billing_country':
            case 'shipping_country':
                $default_config['type'] = 'country';
                break;
                
            case 'billing_state':
            case 'shipping_state':
                $default_config['type'] = 'state';
                break;
                
            case 'billing_email':
                $default_config['type'] = 'email';
                break;
                
            case 'billing_phone':
                $default_config['type'] = 'tel';
                break;
                
            case 'order_comments':
                $default_config['type'] = 'textarea';
                $default_config['class'] = array('form-row-wide', 'notes');
                break;
                
            case 'billing_first_name':
            case 'shipping_first_name':
            case 'billing_last_name':
            case 'shipping_last_name':
                $default_config['class'] = array('form-row-first');
                if (strpos($field_key, 'last_name') !== false) {
                    $default_config['class'] = array('form-row-last');
                }
                break;
        }
        
        return $default_config;
    }
    
    /**
     * Apply product type specific rules
     */
    public function apply_product_type_rules() {
        $settings = WCFM_Core::get_settings();
        
        if (!isset($settings['product_type_rules'])) {
            return;
        }
        
        $cart_analysis = $this->analyze_cart_products();
        
        // Apply rules for virtual products
        if ($cart_analysis['has_virtual'] && isset($settings['product_type_rules']['virtual_products'])) {
            $this->apply_virtual_product_rules($settings['product_type_rules']['virtual_products']);
        }
        
        // Apply rules for downloadable products
        if ($cart_analysis['has_downloadable'] && isset($settings['product_type_rules']['downloadable_products'])) {
            $this->apply_downloadable_product_rules($settings['product_type_rules']['downloadable_products']);
        }
    }
    
    /**
     * Analyze cart products
     */
    private function analyze_cart_products() {
        $analysis = array(
            'has_virtual' => false,
            'has_downloadable' => false,
            'has_physical' => false,
        );
        
        if (!WC()->cart) {
            return $analysis;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            
            if ($product->is_virtual()) {
                $analysis['has_virtual'] = true;
            }
            
            if ($product->is_downloadable()) {
                $analysis['has_downloadable'] = true;
            }
            
            if (!$product->is_virtual() && !$product->is_downloadable()) {
                $analysis['has_physical'] = true;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Apply virtual product rules
     */
    private function apply_virtual_product_rules($rules) {
        if (isset($rules['hide_shipping']) && $rules['hide_shipping']) {
            add_filter('woocommerce_cart_needs_shipping', '__return_false');
        }
        
        if (isset($rules['hide_billing_address']) && $rules['hide_billing_address']) {
            add_filter('woocommerce_checkout_fields', array($this, 'remove_billing_address_fields'));
        }
    }
    
    /**
     * Apply downloadable product rules
     */
    private function apply_downloadable_product_rules($rules) {
        if (isset($rules['hide_shipping']) && $rules['hide_shipping']) {
            add_filter('woocommerce_cart_needs_shipping', '__return_false');
        }
        
        if (isset($rules['hide_billing_address']) && $rules['hide_billing_address']) {
            add_filter('woocommerce_checkout_fields', array($this, 'remove_billing_address_fields'));
        }
    }
    
    /**
     * Remove billing address fields
     */
    public function remove_billing_address_fields($fields) {
        $address_fields = array(
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_postcode',
            'billing_country',
            'billing_state'
        );
        
        foreach ($address_fields as $field) {
            unset($fields['billing'][$field]);
        }
        
        return $fields;
    }
    
    /**
     * Validate custom fields
     */
    public function validate_custom_fields() {
        $settings = WCFM_Core::get_settings();
        
        // Validate billing fields
        if (isset($settings['billing_fields'])) {
            $this->validate_field_section($settings['billing_fields'], 'billing');
        }
        
        // Validate shipping fields
        if (isset($settings['shipping_fields'])) {
            $this->validate_field_section($settings['shipping_fields'], 'shipping');
        }
        
        // Validate additional fields
        if (isset($settings['additional_fields'])) {
            $this->validate_field_section($settings['additional_fields'], 'order');
        }
    }
    
    /**
     * Validate field section
     */
    private function validate_field_section($field_settings, $section) {
        foreach ($field_settings as $field_key => $field_config) {
            if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                continue;
            }
            
            if (isset($field_config['required']) && $field_config['required']) {
                $field_value = isset($_POST[$field_key]) ? sanitize_text_field($_POST[$field_key]) : '';
                
                if (empty($field_value)) {
                    $labels = WCFM_Core::get_default_field_labels();
                    $field_label = isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                    
                    wc_add_notice(
                        sprintf(__('%s is a required field.', WCFM_TEXT_DOMAIN), $field_label),
                        'error'
                    );
                }
            }
        }
    }
    
    /**
     * Save custom field data
     */
    public function save_custom_field_data($order_id) {
        $settings = WCFM_Core::get_settings();
        
        // Save billing field data
        if (isset($settings['billing_fields'])) {
            $this->save_field_section_data($order_id, $settings['billing_fields'], 'billing');
        }
        
        // Save shipping field data
        if (isset($settings['shipping_fields'])) {
            $this->save_field_section_data($order_id, $settings['shipping_fields'], 'shipping');
        }
        
        // Save additional field data
        if (isset($settings['additional_fields'])) {
            $this->save_field_section_data($order_id, $settings['additional_fields'], 'order');
        }
    }
    
    /**
     * Save field section data
     */
    private function save_field_section_data($order_id, $field_settings, $section) {
        foreach ($field_settings as $field_key => $field_config) {
            if (!isset($field_config['enabled']) || !$field_config['enabled']) {
                continue;
            }
            
            $field_value = isset($_POST[$field_key]) ? sanitize_text_field($_POST[$field_key]) : '';
            
            if (!empty($field_value)) {
                update_post_meta($order_id, $field_key, $field_value);
            }
        }
    }
    
    /**
     * Display custom fields in admin order details
     */
    public function display_custom_fields_in_admin($order) {
        $settings = WCFM_Core::get_settings();
        $custom_fields = array();
        
        // Collect all custom fields
        $all_sections = array('billing_fields', 'shipping_fields', 'additional_fields');
        
        foreach ($all_sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    if (isset($field_config['enabled']) && $field_config['enabled']) {
                        $field_value = get_post_meta($order->get_id(), $field_key, true);
                        
                        if (!empty($field_value)) {
                            $labels = WCFM_Core::get_default_field_labels();
                            $field_label = isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                            
                            $custom_fields[$field_key] = array(
                                'label' => $field_label,
                                'value' => $field_value
                            );
                        }
                    }
                }
            }
        }
        
        if (!empty($custom_fields)) {
            echo '<h3>' . __('Custom Checkout Fields', WCFM_TEXT_DOMAIN) . '</h3>';
            echo '<div class="wcfm-custom-fields">';
            
            foreach ($custom_fields as $field_key => $field_data) {
                echo '<p><strong>' . esc_html($field_data['label']) . ':</strong> ' . esc_html($field_data['value']) . '</p>';
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Display custom fields in order details for customers
     */
    public function display_custom_fields_in_order($order) {
        $settings = WCFM_Core::get_settings();
        $custom_fields = array();
        
        // Collect all custom fields
        $all_sections = array('billing_fields', 'shipping_fields', 'additional_fields');
        
        foreach ($all_sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    if (isset($field_config['enabled']) && $field_config['enabled']) {
                        $field_value = get_post_meta($order->get_id(), $field_key, true);
                        
                        if (!empty($field_value)) {
                            $labels = WCFM_Core::get_default_field_labels();
                            $field_label = isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                            
                            $custom_fields[$field_key] = array(
                                'label' => $field_label,
                                'value' => $field_value
                            );
                        }
                    }
                }
            }
        }
        
        if (!empty($custom_fields)) {
            echo '<section class="woocommerce-customer-details">';
            echo '<h2>' . __('Additional Information', WCFM_TEXT_DOMAIN) . '</h2>';
            echo '<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">';
            
            foreach ($custom_fields as $field_key => $field_data) {
                echo '<tr>';
                echo '<th>' . esc_html($field_data['label']) . ':</th>';
                echo '<td>' . esc_html($field_data['value']) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '</section>';
        }
    }
}