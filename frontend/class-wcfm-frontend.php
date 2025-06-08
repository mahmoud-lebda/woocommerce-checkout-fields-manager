<?php

/**
 * Frontend functionality class
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Frontend Class
 */
class WCFM_Frontend
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_fields'));
        add_filter('woocommerce_checkout_fields', array($this, 'add_custom_checkout_fields'), 25);

        // Add custom fields to order emails
        add_action('woocommerce_email_order_meta', array($this, 'add_custom_fields_to_emails'), 10, 3);

        // Display custom fields in order confirmation
        add_action('woocommerce_thankyou', array($this, 'display_custom_fields_order_confirmation'));

        // Add custom fields to REST API
        add_action('rest_api_init', array($this, 'register_rest_fields'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts()
    {
        if (is_checkout() || is_wc_endpoint_url('order-pay')) {

            // Enqueue styles
            wp_enqueue_style(
                'wcfm-frontend',
                WCFM_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WCFM_VERSION
            );

            // Enqueue scripts
            wp_enqueue_script(
                'wcfm-frontend',
                WCFM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery', 'wc-checkout'),
                WCFM_VERSION,
                true
            );

            // Get field settings for frontend
            $field_settings = WCFM_Core::get_frontend_settings();

            // Basic localization data
            $localize_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfm_frontend_nonce'),
                'is_block_checkout' => WCFM_Core::is_block_checkout(),
                'field_settings' => $field_settings,
                'strings' => array(
                    'required_field_error' => __('This field is required.', WCFM_TEXT_DOMAIN),
                    'invalid_email' => __('Please enter a valid email address.', WCFM_TEXT_DOMAIN),
                    'invalid_phone' => __('Please enter a valid phone number.', WCFM_TEXT_DOMAIN),
                    'field_too_short' => __('This field is too short (minimum %d characters).', WCFM_TEXT_DOMAIN),
                    'field_too_long' => __('This field is too long (maximum %d characters).', WCFM_TEXT_DOMAIN),
                ),
            );

            // Localize script with data
            wp_localize_script('wcfm-frontend', 'wcfm_frontend', $localize_data);

            // If it's block checkout, load additional block assets
            if (WCFM_Core::is_block_checkout()) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM Frontend] Loading block checkout assets');
                }

                // Enqueue block-specific styles
                wp_enqueue_style(
                    'wcfm-blocks',
                    WCFM_PLUGIN_URL . 'assets/css/blocks.css',
                    array('wcfm-frontend'),
                    WCFM_VERSION
                );

                // Enqueue block integration script
                wp_enqueue_script(
                    'wcfm-blocks-integration',
                    WCFM_PLUGIN_URL . 'assets/js/blocks-integration.js',
                    array('wp-hooks', 'wp-data', 'wp-element', 'wcfm-frontend'),
                    WCFM_VERSION,
                    true
                );

                // Localize block-specific data
                wp_localize_script('wcfm-blocks-integration', 'wcfmBlocksSettings', array(
                    'fieldSettings' => $field_settings,
                    'isBlockCheckout' => true,
                    'strings' => $localize_data['strings'],
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wcfm_blocks_nonce'),
                ));

                // Add body class for block checkout
                add_filter('body_class', function ($classes) {
                    $classes[] = 'wcfm-block-checkout';
                    return $classes;
                });
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM Frontend] Loading classic checkout assets');
                }

                // Add body class for classic checkout
                add_filter('body_class', function ($classes) {
                    $classes[] = 'wcfm-classic-checkout';
                    return $classes;
                });
            }
        }
    }

    /**
     * Get frontend field settings
     */
    private function get_frontend_field_settings()
    {
        $settings = WCFM_Core::get_settings();
        $frontend_settings = array();

        // Process all field sections
        $sections = array('billing_fields', 'shipping_fields', 'additional_fields');

        foreach ($sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    if (isset($field_config['enabled']) && $field_config['enabled']) {
                        $frontend_settings[$field_key] = array(
                            'required' => isset($field_config['required']) ? $field_config['required'] : false,
                            'validation' => isset($field_config['validation']) ? $field_config['validation'] : array(),
                            'conditional_logic' => isset($field_config['conditional_logic']) ? $field_config['conditional_logic'] : array(),
                        );
                    }
                }
            }
        }

        // Add custom fields
        $custom_fields = $this->get_custom_fields();
        foreach ($custom_fields as $field) {
            if ($field->field_enabled) {
                $frontend_settings[$field->field_key] = array(
                    'required' => $field->field_required,
                    'type' => $field->field_type,
                    'validation' => !empty($field->field_options) ? json_decode($field->field_options, true) : array(),
                );
            }
        }

        return $frontend_settings;
    }

    /**
     * Add custom checkout fields
     */
    public function add_custom_checkout_fields($fields)
    {
        $custom_fields = $this->get_custom_fields();

        foreach ($custom_fields as $field) {
            if (!$field->field_enabled) {
                continue;
            }

            $field_config = array(
                'type' => $field->field_type,
                'label' => $field->field_label,
                'required' => $field->field_required,
                'priority' => $field->field_priority,
                'class' => array('form-row-wide'),
            );

            // Add placeholder if set
            if (!empty($field->field_placeholder)) {
                $field_config['placeholder'] = $field->field_placeholder;
            }

            // Handle field options for select, radio, checkbox
            if (in_array($field->field_type, array('select', 'radio', 'checkbox')) && !empty($field->field_options)) {
                $options = $this->parse_field_options($field->field_options);
                $field_config['options'] = $options;
            }

            // Add validation attributes
            $field_config = $this->add_field_validation($field_config, $field);

            // Add to appropriate section
            $section = $field->field_section . '_fields';
            if ($field->field_section === 'additional') {
                $section = 'order';
            }

            if (!isset($fields[$section])) {
                $fields[$section] = array();
            }

            $fields[$section][$field->field_key] = $field_config;
        }

        return $fields;
    }

    /**
     * Parse field options
     */
    private function parse_field_options($options_text)
    {
        $options = array();
        $lines = explode("\n", $options_text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, '|') !== false) {
                list($value, $label) = explode('|', $line, 2);
                $options[trim($value)] = trim($label);
            } else {
                $options[trim($line)] = trim($line);
            }
        }

        return $options;
    }

    /**
     * Add field validation
     */
    private function add_field_validation($field_config, $field)
    {
        $custom_attributes = array();

        // Parse validation rules from field options
        if (!empty($field->field_options)) {
            $options = json_decode($field->field_options, true);

            if (is_array($options)) {
                foreach ($options as $rule => $value) {
                    switch ($rule) {
                        case 'minlength':
                            $custom_attributes['minlength'] = absint($value);
                            break;
                        case 'maxlength':
                            $custom_attributes['maxlength'] = absint($value);
                            break;
                        case 'pattern':
                            $custom_attributes['pattern'] = esc_attr($value);
                            break;
                        case 'min':
                            $custom_attributes['min'] = esc_attr($value);
                            break;
                        case 'max':
                            $custom_attributes['max'] = esc_attr($value);
                            break;
                        case 'step':
                            $custom_attributes['step'] = esc_attr($value);
                            break;
                    }
                }
            }
        }

        // Add data attributes for frontend validation
        $custom_attributes['data-wcfm-field'] = $field->field_key;
        $custom_attributes['data-wcfm-type'] = $field->field_type;

        if (!empty($custom_attributes)) {
            $field_config['custom_attributes'] = $custom_attributes;
        }

        return $field_config;
    }

    /**
     * Validate checkout fields
     */
    public function validate_checkout_fields()
    {
        $custom_fields = $this->get_custom_fields();

        foreach ($custom_fields as $field) {
            if (!$field->field_enabled) {
                continue;
            }

            $field_value = isset($_POST[$field->field_key]) ? sanitize_text_field($_POST[$field->field_key]) : '';

            // Required field validation
            if ($field->field_required && empty($field_value)) {
                wc_add_notice(
                    sprintf(__('%s is a required field.', WCFM_TEXT_DOMAIN), $field->field_label),
                    'error'
                );
                continue;
            }

            // Skip further validation if field is empty and not required
            if (empty($field_value)) {
                continue;
            }

            // Type-specific validation
            switch ($field->field_type) {
                case 'email':
                    if (!is_email($field_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be a valid email address.', WCFM_TEXT_DOMAIN), $field->field_label),
                            'error'
                        );
                    }
                    break;

                case 'tel':
                    if (!$this->validate_phone_number($field_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be a valid phone number.', WCFM_TEXT_DOMAIN), $field->field_label),
                            'error'
                        );
                    }
                    break;

                case 'number':
                    if (!is_numeric($field_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be a valid number.', WCFM_TEXT_DOMAIN), $field->field_label),
                            'error'
                        );
                    }
                    break;

                case 'date':
                    if (!$this->validate_date($field_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be a valid date.', WCFM_TEXT_DOMAIN), $field->field_label),
                            'error'
                        );
                    }
                    break;
            }

            // Custom validation rules
            $this->validate_custom_rules($field, $field_value);
        }
    }

    /**
     * Validate phone number
     */
    private function validate_phone_number($phone)
    {
        // Basic phone number validation
        $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
        return strlen($phone) >= 10;
    }

    /**
     * Validate date
     */
    private function validate_date($date)
    {
        $parsed_date = date_parse($date);
        return checkdate($parsed_date['month'], $parsed_date['day'], $parsed_date['year']);
    }

    /**
     * Validate custom rules
     */
    private function validate_custom_rules($field, $value)
    {
        if (empty($field->field_options)) {
            return;
        }

        $options = json_decode($field->field_options, true);
        if (!is_array($options)) {
            return;
        }

        foreach ($options as $rule => $rule_value) {
            switch ($rule) {
                case 'minlength':
                    if (strlen($value) < absint($rule_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be at least %d characters long.', WCFM_TEXT_DOMAIN), $field->field_label, $rule_value),
                            'error'
                        );
                    }
                    break;

                case 'maxlength':
                    if (strlen($value) > absint($rule_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be no more than %d characters long.', WCFM_TEXT_DOMAIN), $field->field_label, $rule_value),
                            'error'
                        );
                    }
                    break;

                case 'pattern':
                    if (!preg_match('/' . $rule_value . '/', $value)) {
                        wc_add_notice(
                            sprintf(__('%s format is not valid.', WCFM_TEXT_DOMAIN), $field->field_label),
                            'error'
                        );
                    }
                    break;

                case 'min':
                    if (is_numeric($value) && floatval($value) < floatval($rule_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be at least %s.', WCFM_TEXT_DOMAIN), $field->field_label, $rule_value),
                            'error'
                        );
                    }
                    break;

                case 'max':
                    if (is_numeric($value) && floatval($value) > floatval($rule_value)) {
                        wc_add_notice(
                            sprintf(__('%s must be no more than %s.', WCFM_TEXT_DOMAIN), $field->field_label, $rule_value),
                            'error'
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Get custom fields from database
     */
    private function get_custom_fields()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wcfm_custom_fields';

        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            // Table doesn't exist, create it
            $this->create_custom_fields_table();
            return array(); // Return empty array for now
        }

        return $wpdb->get_results("SELECT * FROM $table_name WHERE field_enabled = 1 ORDER BY field_priority ASC");
    }

    /**
     * Create custom fields table if it doesn't exist
     */
    private function create_custom_fields_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
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
        dbDelta($sql);

        // Update version
        update_option('wcfm_db_version', WCFM_VERSION);

        // Log table creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Custom fields table created: ' . $table_name);
        }
    }

    /**
     * Add custom fields to order emails
     */
    public function add_custom_fields_to_emails($order, $sent_to_admin, $plain_text)
    {
        $custom_fields = $this->get_custom_fields();
        $custom_data = array();

        foreach ($custom_fields as $field) {
            $field_value = get_post_meta($order->get_id(), $field->field_key, true);

            if (!empty($field_value)) {
                $custom_data[$field->field_label] = $field_value;
            }
        }

        if (!empty($custom_data)) {
            if ($plain_text) {
                echo "\n" . __('Additional Information:', WCFM_TEXT_DOMAIN) . "\n";
                foreach ($custom_data as $label => $value) {
                    echo $label . ': ' . $value . "\n";
                }
            } else {
                echo '<h2>' . __('Additional Information', WCFM_TEXT_DOMAIN) . '</h2>';
                echo '<table cellspacing="0" cellpadding="6" border="1" style="width: 100%; border: 1px solid #eee;">';
                foreach ($custom_data as $label => $value) {
                    echo '<tr>';
                    echo '<th scope="row" style="text-align: left; border: 1px solid #eee; padding: 12px;">' . esc_html($label) . '</th>';
                    echo '<td style="text-align: left; border: 1px solid #eee; padding: 12px;">' . esc_html($value) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        }
    }

    /**
     * Display custom fields in order confirmation
     */
    public function display_custom_fields_order_confirmation($order_id)
    {
        if (!$order_id) {
            return;
        }

        $custom_fields = $this->get_custom_fields();
        $custom_data = array();

        foreach ($custom_fields as $field) {
            $field_value = get_post_meta($order_id, $field->field_key, true);

            if (!empty($field_value)) {
                $custom_data[$field->field_label] = $field_value;
            }
        }

        if (!empty($custom_data)) {
            echo '<section class="woocommerce-customer-details wcfm-custom-fields">';
            echo '<h2 class="woocommerce-column__title">' . __('Additional Information', WCFM_TEXT_DOMAIN) . '</h2>';
            echo '<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">';

            foreach ($custom_data as $label => $value) {
                echo '<tr>';
                echo '<th>' . esc_html($label) . ':</th>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '</section>';
        }
    }

    /**
     * Register REST API fields
     */
    public function register_rest_fields()
    {
        $custom_fields = $this->get_custom_fields();

        foreach ($custom_fields as $field) {
            register_rest_field(
                'shop_order',
                $field->field_key,
                array(
                    'get_callback' => function ($order) use ($field) {
                        return get_post_meta($order['id'], $field->field_key, true);
                    },
                    'update_callback' => function ($value, $order) use ($field) {
                        return update_post_meta($order->ID, $field->field_key, sanitize_text_field($value));
                    },
                    'schema' => array(
                        'description' => $field->field_label,
                        'type' => $this->get_rest_field_type($field->field_type),
                    ),
                )
            );
        }
    }

    /**
     * Get REST field type
     */
    private function get_rest_field_type($field_type)
    {
        switch ($field_type) {
            case 'number':
                return 'number';
            case 'checkbox':
                return 'boolean';
            case 'date':
                return 'string'; // Date as string in ISO format
            default:
                return 'string';
        }
    }

    /**
     * Handle conditional field logic
     */
    public function handle_conditional_logic()
    {
        $settings = WCFM_Core::get_settings();

        if (!WCFM_Settings::get_setting('general', 'enable_conditional_logic', false)) {
            return;
        }

        $conditional_rules = array();

        // Process all field sections for conditional logic
        $sections = array('billing_fields', 'shipping_fields', 'additional_fields');

        foreach ($sections as $section) {
            if (isset($settings[$section])) {
                foreach ($settings[$section] as $field_key => $field_config) {
                    if (isset($field_config['conditional_logic']) && !empty($field_config['conditional_logic'])) {
                        $conditional_rules[$field_key] = $field_config['conditional_logic'];
                    }
                }
            }
        }

        // Add custom fields conditional logic
        $custom_fields = $this->get_custom_fields();
        foreach ($custom_fields as $field) {
            if (!empty($field->field_options)) {
                $options = json_decode($field->field_options, true);
                if (isset($options['conditional_logic'])) {
                    $conditional_rules[$field->field_key] = $options['conditional_logic'];
                }
            }
        }

        if (!empty($conditional_rules)) {
            wp_localize_script('wcfm-frontend', 'wcfm_conditional_rules', $conditional_rules);
        }
    }

    /**
     * Add field tooltips
     */
    public function add_field_tooltips($field, $key, $args, $value)
    {
        if (isset($args['tooltip']) && !empty($args['tooltip'])) {
            echo '<span class="wcfm-tooltip" data-tooltip="' . esc_attr($args['tooltip']) . '">';
            echo '<span class="dashicons dashicons-info"></span>';
            echo '</span>';
        }
    }

    /**
     * Handle field dependencies
     */
    public function handle_field_dependencies()
    {
        // This method can be extended to handle complex field dependencies
        // For example, showing/hiding fields based on product types, shipping methods, etc.

        $dependencies = array();

        // Add product type dependencies
        $cart_analysis = $this->analyze_cart_for_dependencies();

        if ($cart_analysis['has_virtual_only']) {
            $dependencies['hide_shipping'] = true;
        }

        if ($cart_analysis['has_downloadable_only']) {
            $dependencies['hide_physical_address'] = true;
        }

        wp_localize_script('wcfm-frontend', 'wcfm_dependencies', $dependencies);
    }

    /**
     * Analyze cart for dependencies
     */
    private function analyze_cart_for_dependencies()
    {
        $analysis = array(
            'has_virtual_only' => false,
            'has_downloadable_only' => false,
            'has_physical' => false,
            'total_items' => 0,
        );

        if (!WC()->cart || WC()->cart->is_empty()) {
            return $analysis;
        }

        $virtual_count = 0;
        $downloadable_count = 0;
        $physical_count = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $analysis['total_items']++;

            if ($product->is_virtual()) {
                $virtual_count++;
            } elseif ($product->is_downloadable()) {
                $downloadable_count++;
            } else {
                $physical_count++;
            }
        }

        $analysis['has_virtual_only'] = ($virtual_count > 0 && $physical_count === 0 && $downloadable_count === 0);
        $analysis['has_downloadable_only'] = ($downloadable_count > 0 && $physical_count === 0 && $virtual_count === 0);
        $analysis['has_physical'] = ($physical_count > 0);

        return $analysis;
    }

    /**
     * Add custom CSS classes to checkout form
     */
    public function add_custom_css_classes()
    {
        if (!WCFM_Settings::get_setting('general', 'enable_css_classes', true)) {
            return;
        }

        $cart_analysis = $this->analyze_cart_for_dependencies();
        $css_classes = array('wcfm-checkout');

        if ($cart_analysis['has_virtual_only']) {
            $css_classes[] = 'wcfm-virtual-only';
        }

        if ($cart_analysis['has_downloadable_only']) {
            $css_classes[] = 'wcfm-downloadable-only';
        }

        if ($cart_analysis['has_physical']) {
            $css_classes[] = 'wcfm-has-physical';
        }

        if (has_block('woocommerce/checkout')) {
            $css_classes[] = 'wcfm-block-checkout';
        } else {
            $css_classes[] = 'wcfm-classic-checkout';
        }

        // Add classes to body
        add_filter('body_class', function ($classes) use ($css_classes) {
            return array_merge($classes, $css_classes);
        });
    }

    /**
     * AJAX handler for dynamic field updates
     */
    public function ajax_update_checkout_fields()
    {
        check_ajax_referer('wcfm_frontend_nonce', 'nonce');

        $trigger_field = sanitize_text_field($_POST['trigger_field']);
        $trigger_value = sanitize_text_field($_POST['trigger_value']);

        // Get conditional rules
        $conditional_rules = $this->get_conditional_rules_for_field($trigger_field);

        $response = array(
            'fields_to_show' => array(),
            'fields_to_hide' => array(),
            'fields_to_require' => array(),
            'fields_to_unrequire' => array(),
        );

        foreach ($conditional_rules as $target_field => $rules) {
            foreach ($rules as $rule) {
                if ($this->evaluate_conditional_rule($rule, $trigger_field, $trigger_value)) {
                    switch ($rule['action']) {
                        case 'show':
                            $response['fields_to_show'][] = $target_field;
                            break;
                        case 'hide':
                            $response['fields_to_hide'][] = $target_field;
                            break;
                        case 'require':
                            $response['fields_to_require'][] = $target_field;
                            break;
                        case 'unrequire':
                            $response['fields_to_unrequire'][] = $target_field;
                            break;
                    }
                }
            }
        }

        wp_send_json_success($response);
    }

    /**
     * Get conditional rules for field
     */
    private function get_conditional_rules_for_field($field)
    {
        // This method would retrieve conditional rules from the database
        // Implementation depends on how conditional rules are stored
        return array();
    }

    /**
     * Evaluate conditional rule
     */
    private function evaluate_conditional_rule($rule, $field, $value)
    {
        if ($rule['field'] !== $field) {
            return false;
        }

        switch ($rule['operator']) {
            case 'equals':
                return $value === $rule['value'];
            case 'not_equals':
                return $value !== $rule['value'];
            case 'contains':
                return strpos($value, $rule['value']) !== false;
            case 'not_contains':
                return strpos($value, $rule['value']) === false;
            case 'greater_than':
                return is_numeric($value) && floatval($value) > floatval($rule['value']);
            case 'less_than':
                return is_numeric($value) && floatval($value) < floatval($rule['value']);
            case 'is_empty':
                return empty($value);
            case 'is_not_empty':
                return !empty($value);
            default:
                return false;
        }
    }

    /**
     * Handle field visibility based on user role
     */
    public function handle_role_based_visibility()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        $user_roles = $user->roles;

        // Get role-based field settings
        $settings = WCFM_Core::get_settings();
        $role_settings = isset($settings['role_based']) ? $settings['role_based'] : array();

        if (empty($role_settings)) {
            return;
        }

        $fields_to_hide = array();
        $fields_to_show = array();

        foreach ($role_settings as $field_key => $field_roles) {
            $show_field = false;

            foreach ($user_roles as $role) {
                if (in_array($role, $field_roles)) {
                    $show_field = true;
                    break;
                }
            }

            if ($show_field) {
                $fields_to_show[] = $field_key;
            } else {
                $fields_to_hide[] = $field_key;
            }
        }

        if (!empty($fields_to_hide) || !empty($fields_to_show)) {
            wp_localize_script('wcfm-frontend', 'wcfm_role_fields', array(
                'hide' => $fields_to_hide,
                'show' => $fields_to_show,
            ));
        }
    }
}
