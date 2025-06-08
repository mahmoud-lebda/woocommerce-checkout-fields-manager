<?php

/**
 * Enhanced Fields Handler Class with Block Checkout Support
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Fields Handler Class - Enhanced for Blocks
 */
class WCFM_Fields_Handler
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Is block checkout
     */
    private $is_block_checkout = false;

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
        $this->init();
    }

    /**
     * Initialize
     */
    public function init()
    {
        // Detect checkout type
        add_action('wp', array($this, 'detect_checkout_type'));

        // Hook into WooCommerce checkout fields (Classic)
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 20);

        // Hook into WooCommerce Blocks (Block Checkout)
        add_filter('__experimental_woocommerce_blocks_checkout_update_order_from_request', array($this, 'save_block_checkout_data'), 10, 2);
        add_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_block_checkout_data'), 10, 2);

        // Handle product type specific rules
        add_action('woocommerce_before_checkout_form', array($this, 'apply_product_type_rules'));

        // Handle field validation for both checkout types
        add_action('woocommerce_checkout_process', array($this, 'validate_custom_fields'));
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'validate_block_checkout_fields'));

        // Save custom field data for both checkout types
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_field_data'));

        // Display custom fields in order details
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_custom_fields_in_admin'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_custom_fields_in_order'));

        // Block-specific hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_checkout_scripts'));
    }

    /**
     * Detect checkout type
     */
    public function detect_checkout_type()
    {
        if (is_checkout()) {
            $this->is_block_checkout = has_block('woocommerce/checkout');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Checkout type detected: ' . ($this->is_block_checkout ? 'Block' : 'Classic'));
            }
        }
    }

    /**
     * Enqueue block checkout scripts
     */
    public function enqueue_block_checkout_scripts()
    {
        if ($this->is_block_checkout) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Enqueuing simplified block checkout scripts');
            }

            // Enqueue our simplified block checkout JavaScript
            wp_enqueue_script(
                'wcfm-block-checkout',
                WCFM_PLUGIN_URL . 'assets/js/blocks-integration.js',
                array('jquery'),
                WCFM_VERSION,
                true
            );

            // Get field settings for JavaScript
            $field_settings = $this->get_simplified_field_settings();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Field settings for blocks: ' . print_r($field_settings, true));
            }

            // Pass field settings to JavaScript
            wp_localize_script('wcfm-block-checkout', 'wcfmBlocksSettings', array(
                'fieldSettings' => $field_settings,
                'isBlockCheckout' => true,
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'strings' => array(
                    'required_field_error' => __('This field is required.', WCFM_TEXT_DOMAIN),
                    'invalid_email' => __('Please enter a valid email address.', WCFM_TEXT_DOMAIN),
                    'invalid_phone' => __('Please enter a valid phone number.', WCFM_TEXT_DOMAIN),
                ),
            ));

            // Add inline CSS for immediate effect
            wp_add_inline_style('wcfm-frontend-style', '
            .wcfm-field-hidden { display: none !important; }
            .wcfm-field-enabled { display: block !important; }
            .wcfm-field-required label .required { color: #e74c3c; font-weight: bold; }
            .wcfm-field-wrapper { position: relative; }
        ');
        }
    }

    /**
     * Get simplified field settings for blocks
     */
    private function get_simplified_field_settings()
    {
        $settings = WCFM_Core::get_settings();
        $simplified_settings = array();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Raw settings: ' . print_r($settings, true));
        }

        // Process billing fields
        if (isset($settings['billing_fields'])) {
            foreach ($settings['billing_fields'] as $field_key => $field_config) {
                $simplified_settings[$field_key] = array(
                    'enabled' => isset($field_config['enabled']) ? (bool)$field_config['enabled'] : true,
                    'required' => isset($field_config['required']) ? (bool)$field_config['required'] : false,
                    'label' => isset($field_config['label']) && !empty($field_config['label']) ? $field_config['label'] : '',
                    'priority' => isset($field_config['priority']) ? (int)$field_config['priority'] : 10,
                    'section' => 'billing'
                );
            }
        }

        // Process shipping fields
        if (isset($settings['shipping_fields'])) {
            foreach ($settings['shipping_fields'] as $field_key => $field_config) {
                $simplified_settings[$field_key] = array(
                    'enabled' => isset($field_config['enabled']) ? (bool)$field_config['enabled'] : true,
                    'required' => isset($field_config['required']) ? (bool)$field_config['required'] : false,
                    'label' => isset($field_config['label']) && !empty($field_config['label']) ? $field_config['label'] : '',
                    'priority' => isset($field_config['priority']) ? (int)$field_config['priority'] : 10,
                    'section' => 'shipping'
                );
            }
        }

        // Process additional fields
        if (isset($settings['additional_fields'])) {
            foreach ($settings['additional_fields'] as $field_key => $field_config) {
                $simplified_settings[$field_key] = array(
                    'enabled' => isset($field_config['enabled']) ? (bool)$field_config['enabled'] : true,
                    'required' => isset($field_config['required']) ? (bool)$field_config['required'] : false,
                    'label' => isset($field_config['label']) && !empty($field_config['label']) ? $field_config['label'] : '',
                    'priority' => isset($field_config['priority']) ? (int)$field_config['priority'] : 10,
                    'section' => 'additional'
                );
            }
        }

        return $simplified_settings;
    }

    /**
     * Get field settings formatted for blocks
     */
    private function get_field_settings_for_blocks()
    {
        $settings = WCFM_Core::get_settings();
        $formatted_settings = array();

        // Process billing fields
        if (isset($settings['billing_fields'])) {
            foreach ($settings['billing_fields'] as $field_key => $field_config) {
                $formatted_settings[$field_key] = array_merge($field_config, array(
                    'section' => 'billing'
                ));
            }
        }

        // Process shipping fields
        if (isset($settings['shipping_fields'])) {
            foreach ($settings['shipping_fields'] as $field_key => $field_config) {
                $formatted_settings[$field_key] = array_merge($field_config, array(
                    'section' => 'shipping'
                ));
            }
        }

        // Process additional fields
        if (isset($settings['additional_fields'])) {
            foreach ($settings['additional_fields'] as $field_key => $field_config) {
                $formatted_settings[$field_key] = array_merge($field_config, array(
                    'section' => 'additional'
                ));
            }
        }

        return $formatted_settings;
    }

    /**
     * Customize checkout fields based on settings (Classic Checkout)
     */
    public function customize_checkout_fields($fields)
    {
        $settings = WCFM_Core::get_settings();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Customizing checkout fields for classic checkout');
        }

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
     * Save block checkout data
     */
    public function save_block_checkout_data($order, $request)
    {
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
     * Validate block checkout fields
     */
    public function validate_block_checkout_fields($order)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Validating block checkout fields for order: ' . $order->get_id());
        }

        $settings = WCFM_Core::get_settings();
        $errors = array();

        // Validate billing fields
        if (isset($settings['billing_fields'])) {
            foreach ($settings['billing_fields'] as $field_key => $field_config) {
                if (
                    isset($field_config['enabled']) && $field_config['enabled'] &&
                    isset($field_config['required']) && $field_config['required']
                ) {

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
                if (
                    isset($field_config['enabled']) && $field_config['enabled'] &&
                    isset($field_config['required']) && $field_config['required']
                ) {

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

    /**
     * Process field section
     */
    private function process_field_section($existing_fields, $field_settings, $section)
    {
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
        uasort($processed_fields, function ($a, $b) {
            $a_priority = isset($a['priority']) ? $a['priority'] : 10;
            $b_priority = isset($b['priority']) ? $b['priority'] : 10;
            return $a_priority - $b_priority;
        });

        return $processed_fields;
    }

    /**
     * Get default field configuration
     */
    private function get_default_field_config($field_key, $section)
    {
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
    public function apply_product_type_rules()
    {
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
    private function analyze_cart_products()
    {
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
    private function apply_virtual_product_rules($rules)
    {
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
    private function apply_downloadable_product_rules($rules)
    {
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
    public function remove_billing_address_fields($fields)
    {
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
     * Validate custom fields (Classic Checkout)
     */
    public function validate_custom_fields()
    {
        if ($this->is_block_checkout) {
            return; // Block checkout has its own validation
        }

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
    private function validate_field_section($field_settings, $section)
    {
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
     * Save custom field data (Classic Checkout)
     */
    public function save_custom_field_data($order_id)
    {
        if ($this->is_block_checkout) {
            return; // Block checkout has its own saving mechanism
        }

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
    private function save_field_section_data($order_id, $field_settings, $section)
    {
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
    public function display_custom_fields_in_admin($order)
    {
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
                            $field_label = isset($field_config['label']) && !empty($field_config['label']) ?
                                $field_config['label'] : (isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key)));

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
    public function display_custom_fields_in_order($order)
    {
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
                            $field_label = isset($field_config['label']) && !empty($field_config['label']) ?
                                $field_config['label'] : (isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key)));

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

    /**
     * Get checkout type
     */
    public function get_checkout_type()
    {
        return $this->is_block_checkout ? 'block' : 'classic';
    }

    /**
     * Check if current checkout is block checkout
     */
    public function is_block_checkout()
    {
        return $this->is_block_checkout;
    }
}
