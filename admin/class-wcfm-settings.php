<?php
/**
 * Settings management class
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Settings Class
 */
class WCFM_Settings {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Settings sections
     */
    private $sections = array();
    
    /**
     * Settings fields
     */
    private $fields = array();
    
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
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_wcfm_get_custom_fields', array($this, 'get_custom_fields_ajax'));
        add_action('wp_ajax_wcfm_save_custom_field', array($this, 'save_custom_field_ajax'));
        add_action('wp_ajax_wcfm_delete_custom_field', array($this, 'delete_custom_field_ajax'));
        add_action('wp_ajax_wcfm_repair_database', array($this, 'repair_database_ajax'));
    }
    
    /**
     * Repair database via AJAX
     */
    public function repair_database_ajax() {
        check_ajax_referer('wcfm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        $result = $this->repair_database_tables();
        
        if ($result) {
            wp_send_json_success(__('Database tables repaired successfully!', WCFM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to repair database tables.', WCFM_TEXT_DOMAIN));
        }
    }
    
    /**
     * Repair database tables
     */
    public function repair_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
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
            
            return ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
        }
        
        return true; // Table already exists
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        $this->set_sections();
        $this->set_fields();
        $this->register_settings();
    }
    
    /**
     * Set settings sections
     */
    private function set_sections() {
        $this->sections = array(
            'general' => array(
                'id' => 'wcfm_general',
                'title' => __('General Settings', WCFM_TEXT_DOMAIN),
                'description' => __('General configuration options for the checkout fields manager.', WCFM_TEXT_DOMAIN),
            ),
            'advanced' => array(
                'id' => 'wcfm_advanced',
                'title' => __('Advanced Settings', WCFM_TEXT_DOMAIN),
                'description' => __('Advanced configuration options for power users.', WCFM_TEXT_DOMAIN),
            ),
        );
    }
    
    /**
     * Set settings fields
     */
    private function set_fields() {
        $this->fields = array(
            'general' => array(
                'enable_logging' => array(
                    'id' => 'enable_logging',
                    'title' => __('Enable Logging', WCFM_TEXT_DOMAIN),
                    'description' => __('Enable debug logging for troubleshooting.', WCFM_TEXT_DOMAIN),
                    'type' => 'checkbox',
                    'default' => false,
                ),
                'enable_css_classes' => array(
                    'id' => 'enable_css_classes',
                    'title' => __('Enable Custom CSS Classes', WCFM_TEXT_DOMAIN),
                    'description' => __('Allow adding custom CSS classes to fields.', WCFM_TEXT_DOMAIN),
                    'type' => 'checkbox',
                    'default' => true,
                ),
                'enable_conditional_logic' => array(
                    'id' => 'enable_conditional_logic',
                    'title' => __('Enable Conditional Logic', WCFM_TEXT_DOMAIN),
                    'description' => __('Enable conditional field display based on other field values.', WCFM_TEXT_DOMAIN),
                    'type' => 'checkbox',
                    'default' => false,
                ),
            ),
            'advanced' => array(
                'cache_settings' => array(
                    'id' => 'cache_settings',
                    'title' => __('Cache Field Settings', WCFM_TEXT_DOMAIN),
                    'description' => __('Cache field settings for better performance.', WCFM_TEXT_DOMAIN),
                    'type' => 'checkbox',
                    'default' => true,
                ),
                'load_assets_conditionally' => array(
                    'id' => 'load_assets_conditionally',
                    'title' => __('Load Assets Conditionally', WCFM_TEXT_DOMAIN),
                    'description' => __('Only load CSS/JS assets on checkout pages.', WCFM_TEXT_DOMAIN),
                    'type' => 'checkbox',
                    'default' => true,
                ),
                'backup_settings' => array(
                    'id' => 'backup_settings',
                    'title' => __('Auto Backup Settings', WCFM_TEXT_DOMAIN),
                    'description' => __('Automatically backup settings before major changes.', WCFM_TEXT_DOMAIN),
                    'type' => 'checkbox',
                    'default' => true,
                ),
            ),
        );
    }
    
    /**
     * Register settings
     */
    private function register_settings() {
        foreach ($this->sections as $section_key => $section) {
            add_settings_section(
                $section['id'],
                $section['title'],
                array($this, 'section_callback'),
                'wcfm_' . $section_key . '_settings'
            );
            
            if (isset($this->fields[$section_key])) {
                foreach ($this->fields[$section_key] as $field_key => $field) {
                    add_settings_field(
                        $field['id'],
                        $field['title'],
                        array($this, 'field_callback'),
                        'wcfm_' . $section_key . '_settings',
                        $section['id'],
                        array(
                            'id' => $field['id'],
                            'type' => $field['type'],
                            'description' => $field['description'],
                            'default' => $field['default'],
                            'section' => $section_key,
                        )
                    );
                }
            }
        }
    }
    
    /**
     * Section callback
     */
    public function section_callback($args) {
        $section_id = $args['id'];
        foreach ($this->sections as $section) {
            if ($section['id'] === $section_id) {
                echo '<p>' . esc_html($section['description']) . '</p>';
                break;
            }
        }
    }
    
    /**
     * Field callback
     */
    public function field_callback($args) {
        $field_id = $args['id'];
        $field_type = $args['type'];
        $field_description = $args['description'];
        $field_default = $args['default'];
        $section = $args['section'];
        
        $option_name = 'wcfm_' . $section . '_settings';
        $options = get_option($option_name, array());
        $value = isset($options[$field_id]) ? $options[$field_id] : $field_default;
        
        switch ($field_type) {
            case 'text':
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
                
            case 'textarea':
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_id) . ']" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;
                
            case 'checkbox':
                echo '<label><input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_id) . ']" value="1" ' . checked(1, $value, false) . ' /> ' . esc_html($field_description) . '</label>';
                return; // Don't show description again
                
            case 'select':
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_id) . ']">';
                if (isset($args['options'])) {
                    foreach ($args['options'] as $option_value => $option_label) {
                        echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                    }
                }
                echo '</select>';
                break;
                
            case 'number':
                echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="small-text" />';
                break;
                
            default:
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        }
        
        if ($field_type !== 'checkbox' && !empty($field_description)) {
            echo '<p class="description">' . esc_html($field_description) . '</p>';
        }
    }
    
    /**
     * Get custom fields via AJAX
     */
    public function get_custom_fields_ajax() {
        check_ajax_referer('wcfm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $custom_fields = $wpdb->get_results("SELECT * FROM $table_name ORDER BY field_priority ASC");
        
        $fields_html = '';
        
        if (!empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                $fields_html .= $this->render_custom_field_row($field);
            }
        } else {
            $fields_html = '<tr><td colspan="7">' . __('No custom fields found. Click "Add Custom Field" to create one.', WCFM_TEXT_DOMAIN) . '</td></tr>';
        }
        
        wp_send_json_success($fields_html);
    }
    
    /**
     * Save custom field via AJAX
     */
    public function save_custom_field_ajax() {
        check_ajax_referer('wcfm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $field_data = array(
            'field_key' => sanitize_text_field($_POST['field_key']),
            'field_type' => sanitize_text_field($_POST['field_type']),
            'field_section' => sanitize_text_field($_POST['field_section']),
            'field_label' => sanitize_text_field($_POST['field_label']),
            'field_placeholder' => sanitize_text_field($_POST['field_placeholder']),
            'field_options' => sanitize_textarea_field($_POST['field_options']),
            'field_enabled' => isset($_POST['field_enabled']) ? 1 : 0,
            'field_required' => isset($_POST['field_required']) ? 1 : 0,
            'field_priority' => absint($_POST['field_priority']),
        );
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $field_id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;
        
        if ($field_id > 0) {
            // Update existing field
            $result = $wpdb->update(
                $table_name,
                $field_data,
                array('id' => $field_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d'),
                array('%d')
            );
        } else {
            // Insert new field
            $result = $wpdb->insert(
                $table_name,
                $field_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success(__('Custom field saved successfully!', WCFM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to save custom field.', WCFM_TEXT_DOMAIN));
        }
    }
    
    /**
     * Delete custom field via AJAX
     */
    public function delete_custom_field_ajax() {
        check_ajax_referer('wcfm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $field_id = absint($_POST['field_id']);
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $field_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Custom field deleted successfully!', WCFM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to delete custom field.', WCFM_TEXT_DOMAIN));
        }
    }
    
    /**
     * Render custom field row
     */
    private function render_custom_field_row($field) {
        $field_types = array(
            'text' => __('Text', WCFM_TEXT_DOMAIN),
            'textarea' => __('Textarea', WCFM_TEXT_DOMAIN),
            'select' => __('Select', WCFM_TEXT_DOMAIN),
            'checkbox' => __('Checkbox', WCFM_TEXT_DOMAIN),
            'radio' => __('Radio', WCFM_TEXT_DOMAIN),
            'email' => __('Email', WCFM_TEXT_DOMAIN),
            'tel' => __('Phone', WCFM_TEXT_DOMAIN),
            'number' => __('Number', WCFM_TEXT_DOMAIN),
            'date' => __('Date', WCFM_TEXT_DOMAIN),
        );
        
        $field_sections = array(
            'billing' => __('Billing', WCFM_TEXT_DOMAIN),
            'shipping' => __('Shipping', WCFM_TEXT_DOMAIN),
            'additional' => __('Additional', WCFM_TEXT_DOMAIN),
        );
        
        ob_start();
        ?>
        <tr class="wcfm-custom-field-row" data-field-id="<?php echo esc_attr($field->id); ?>">
            <td class="wcfm-field-sort">
                <span class="dashicons dashicons-menu"></span>
            </td>
            <td class="wcfm-field-key">
                <strong><?php echo esc_html($field->field_key); ?></strong>
            </td>
            <td class="wcfm-field-label">
                <?php echo esc_html($field->field_label); ?>
            </td>
            <td class="wcfm-field-type">
                <?php echo isset($field_types[$field->field_type]) ? esc_html($field_types[$field->field_type]) : esc_html($field->field_type); ?>
            </td>
            <td class="wcfm-field-section">
                <?php echo isset($field_sections[$field->field_section]) ? esc_html($field_sections[$field->field_section]) : esc_html($field->field_section); ?>
            </td>
            <td class="wcfm-field-status">
                <span class="wcfm-status <?php echo $field->field_enabled ? 'enabled' : 'disabled'; ?>">
                    <?php echo $field->field_enabled ? __('Enabled', WCFM_TEXT_DOMAIN) : __('Disabled', WCFM_TEXT_DOMAIN); ?>
                </span>
                <?php if ($field->field_required): ?>
                    <span class="wcfm-required"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></span>
                <?php endif; ?>
            </td>
            <td class="wcfm-field-actions">
                <button type="button" class="button wcfm-edit-custom-field" data-field-id="<?php echo esc_attr($field->id); ?>">
                    <?php _e('Edit', WCFM_TEXT_DOMAIN); ?>
                </button>
                <button type="button" class="button wcfm-delete-custom-field" data-field-id="<?php echo esc_attr($field->id); ?>">
                    <?php _e('Delete', WCFM_TEXT_DOMAIN); ?>
                </button>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get field validation rules
     */
    public static function get_field_validation_rules() {
        return array(
            'text' => array(
                'minlength' => __('Minimum Length', WCFM_TEXT_DOMAIN),
                'maxlength' => __('Maximum Length', WCFM_TEXT_DOMAIN),
                'pattern' => __('Pattern (Regex)', WCFM_TEXT_DOMAIN),
            ),
            'textarea' => array(
                'minlength' => __('Minimum Length', WCFM_TEXT_DOMAIN),
                'maxlength' => __('Maximum Length', WCFM_TEXT_DOMAIN),
            ),
            'number' => array(
                'min' => __('Minimum Value', WCFM_TEXT_DOMAIN),
                'max' => __('Maximum Value', WCFM_TEXT_DOMAIN),
                'step' => __('Step', WCFM_TEXT_DOMAIN),
            ),
            'email' => array(
                'domain_restriction' => __('Allowed Domains', WCFM_TEXT_DOMAIN),
            ),
            'tel' => array(
                'format' => __('Phone Format', WCFM_TEXT_DOMAIN),
            ),
            'date' => array(
                'min_date' => __('Minimum Date', WCFM_TEXT_DOMAIN),
                'max_date' => __('Maximum Date', WCFM_TEXT_DOMAIN),
            ),
        );
    }
    
    /**
     * Get field display options
     */
    public static function get_field_display_options() {
        return array(
            'class' => __('CSS Classes', WCFM_TEXT_DOMAIN),
            'wrapper_class' => __('Wrapper CSS Classes', WCFM_TEXT_DOMAIN),
            'before' => __('Content Before Field', WCFM_TEXT_DOMAIN),
            'after' => __('Content After Field', WCFM_TEXT_DOMAIN),
            'tooltip' => __('Tooltip Text', WCFM_TEXT_DOMAIN),
            'conditional_logic' => __('Conditional Logic', WCFM_TEXT_DOMAIN),
        );
    }
    
    /**
     * Export custom fields
     */
    public static function export_custom_fields() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $custom_fields = $wpdb->get_results("SELECT * FROM $table_name ORDER BY field_priority ASC", ARRAY_A);
        
        return $custom_fields;
    }
    
    /**
     * Import custom fields
     */
    public static function import_custom_fields($fields_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        
        // Clear existing custom fields
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // Insert imported fields
        foreach ($fields_data as $field) {
            unset($field['id']); // Remove ID to allow auto-increment
            
            $wpdb->insert(
                $table_name,
                $field,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
            );
        }
        
        return true;
    }
    
    /**
     * Get setting value
     */
    public static function get_setting($section, $key, $default = null) {
        $option_name = 'wcfm_' . $section . '_settings';
        $options = get_option($option_name, array());
        
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Update setting value
     */
    public static function update_setting($section, $key, $value) {
        $option_name = 'wcfm_' . $section . '_settings';
        $options = get_option($option_name, array());
        
        $options[$key] = $value;
        
        return update_option($option_name, $options);
    }
    
    /**
     * Validate field configuration
     */
    public static function validate_field_config($field_config) {
        $errors = array();
        
        // Required fields
        if (empty($field_config['field_key'])) {
            $errors[] = __('Field key is required.', WCFM_TEXT_DOMAIN);
        }
        
        if (empty($field_config['field_label'])) {
            $errors[] = __('Field label is required.', WCFM_TEXT_DOMAIN);
        }
        
        if (empty($field_config['field_type'])) {
            $errors[] = __('Field type is required.', WCFM_TEXT_DOMAIN);
        }
        
        // Validate field key format
        if (!empty($field_config['field_key']) && !preg_match('/^[a-z0-9_]+$/', $field_config['field_key'])) {
            $errors[] = __('Field key can only contain lowercase letters, numbers, and underscores.', WCFM_TEXT_DOMAIN);
        }
        
        // Check for duplicate field keys
        if (!empty($field_config['field_key'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wcfm_custom_fields';
            
            $existing_field = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE field_key = %s" . 
                (isset($field_config['id']) ? " AND id != %d" : ""),
                $field_config['field_key'],
                isset($field_config['id']) ? $field_config['id'] : 0
            ));
            
            if ($existing_field > 0) {
                $errors[] = __('A field with this key already exists.', WCFM_TEXT_DOMAIN);
            }
        }
        
        return $errors;
    }
}