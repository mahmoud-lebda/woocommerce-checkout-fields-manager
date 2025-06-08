<?php
/**
 * Admin AJAX Handler - Enhanced with proper database repair
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Admin AJAX Class
 */
class WCFM_Admin_Ajax {
    
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
        // AJAX handlers
        add_action('wp_ajax_wcfm_save_settings', array($this, 'save_settings_ajax'));
        add_action('wp_ajax_wcfm_reset_settings', array($this, 'reset_settings_ajax'));
        add_action('wp_ajax_wcfm_export_settings', array($this, 'export_settings_ajax'));
        add_action('wp_ajax_wcfm_import_settings', array($this, 'import_settings_ajax'));
        add_action('wp_ajax_wcfm_save_field_order', array($this, 'save_field_order_ajax'));
        add_action('wp_ajax_wcfm_repair_database', array($this, 'repair_database_ajax'));
        add_action('wp_ajax_wcfm_check_database', array($this, 'check_database_ajax'));
    }
    
    /**
     * Repair database via AJAX - Enhanced version
     */
    public function repair_database_ajax() {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Repair database AJAX called');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Nonce verification failed in repair_database_ajax');
            }
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] User does not have manage_woocommerce capability');
            }
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $charset_collate = $wpdb->get_charset_collate();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Attempting to create table: ' . $table_name);
        }
        
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] dbDelta result: ' . print_r($result, true));
        }
        
        // Update version
        update_option('wcfm_db_version', WCFM_VERSION);
        
        // Check if table was created successfully
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Table exists after creation attempt: ' . ($table_exists ? 'YES' : 'NO'));
            if (!$table_exists && $wpdb->last_error) {
                error_log('[WCFM] MySQL error: ' . $wpdb->last_error);
            }
        }
        
        if ($table_exists) {
            // Try to insert a test record to verify table is working
            $test_insert = $wpdb->insert(
                $table_name,
                array(
                    'field_key' => 'test_field_' . time(),
                    'field_type' => 'text',
                    'field_section' => 'billing',
                    'field_label' => 'Test Field',
                    'field_enabled' => 0
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
            
            if ($test_insert) {
                // Remove test record
                $wpdb->delete($table_name, array('field_key' => 'test_field_' . time()), array('%s'));
                wp_send_json_success(__('Database tables repaired successfully! Table is working properly.', WCFM_TEXT_DOMAIN));
            } else {
                wp_send_json_error(__('Database table created but insert test failed. Error: ', WCFM_TEXT_DOMAIN) . $wpdb->last_error);
            }
        } else {
            wp_send_json_error(__('Failed to create database table. Error: ', WCFM_TEXT_DOMAIN) . $wpdb->last_error);
        }
    }
    
    /**
     * Save settings via AJAX
     */
    public function save_settings_ajax() {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCFM] Save settings AJAX called');
            error_log('[WCFM] POST data: ' . print_r($_POST, true));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Nonce verification failed');
            }
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] User does not have manage_woocommerce capability');
            }
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        try {
            // Parse form data
            $form_data = array();
            if (isset($_POST['form_data']) && !empty($_POST['form_data'])) {
                parse_str($_POST['form_data'], $form_data);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Form data received: ' . $_POST['form_data']);
                error_log('[WCFM] Parsed form data: ' . print_r($form_data, true));
            }
            
            // If no form data, but this might be a table issue, try to create tables first
            if (empty($form_data)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] No form data received, checking database...');
                }
                
                // Check if database table exists
                global $wpdb;
                $table_name = $wpdb->prefix . 'wcfm_custom_fields';
                $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
                
                if (!$table_exists) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[WCFM] Database table missing, creating it...');
                    }
                    $this->repair_database_ajax();
                    return;
                }
                
                wp_send_json_error(__('No data received. Please try again.', WCFM_TEXT_DOMAIN));
            }
            
            $settings = array();
            
            // Process billing fields
            if (isset($form_data['billing_fields'])) {
                $settings['billing_fields'] = $this->sanitize_fields_data($form_data['billing_fields']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Processed billing fields: ' . print_r($settings['billing_fields'], true));
                }
            }
            
            // Process shipping fields
            if (isset($form_data['shipping_fields'])) {
                $settings['shipping_fields'] = $this->sanitize_fields_data($form_data['shipping_fields']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Processed shipping fields: ' . print_r($settings['shipping_fields'], true));
                }
            }
            
            // Process additional fields
            if (isset($form_data['additional_fields'])) {
                $settings['additional_fields'] = $this->sanitize_fields_data($form_data['additional_fields']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Processed additional fields: ' . print_r($settings['additional_fields'], true));
                }
            }
            
            // Process product type rules
            if (isset($form_data['product_type_rules'])) {
                $settings['product_type_rules'] = $this->sanitize_rules_data($form_data['product_type_rules']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Processed product type rules: ' . print_r($settings['product_type_rules'], true));
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Final settings to save: ' . print_r($settings, true));
            }
            
            // If no settings processed, create empty array to prevent data loss
            if (empty($settings)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] No settings data to save, creating empty array');
                }
                $settings = array();
            }
            
            // Create backup before saving
            $this->create_settings_backup();
            
            // Save settings
            $save_result = WCFM_Core::update_settings($settings);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Save result: ' . ($save_result ? 'success' : 'failed'));
            }
            
            if ($save_result !== false) {
                // Clear any caches
                $this->clear_field_cache();
                
                do_action('wcfm_settings_saved', $settings);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Settings saved successfully');
                }
                
                wp_send_json_success(__('Settings saved successfully!', WCFM_TEXT_DOMAIN));
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WCFM] Failed to update settings in database');
                }
                wp_send_json_error(__('Failed to save settings. Database update failed.', WCFM_TEXT_DOMAIN));
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WCFM] Exception in save_settings_ajax: ' . $e->getMessage());
                error_log('[WCFM] Exception trace: ' . $e->getTraceAsString());
            }
            wp_send_json_error(__('An error occurred while saving settings: ', WCFM_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * Reset settings via AJAX
     */
    public function reset_settings_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        // Create backup before reset
        $this->create_settings_backup();
        
        // Delete current settings
        delete_option('wcfm_settings');
        
        // Clear caches
        $this->clear_field_cache();
        
        wp_send_json_success(__('Settings reset to defaults successfully!', WCFM_TEXT_DOMAIN));
    }
    
    /**
     * Export settings via AJAX
     */
    public function export_settings_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        $settings = WCFM_Core::get_settings();
        $custom_fields = WCFM_Settings::export_custom_fields();
        
        $export_data = array(
            'plugin' => 'WooCommerce Checkout Fields Manager',
            'version' => WCFM_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'settings' => $settings,
            'custom_fields' => $custom_fields,
            'meta' => array(
                'wordpress_version' => get_bloginfo('version'),
                'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
                'php_version' => PHP_VERSION,
            ),
        );
        
        wp_send_json_success($export_data);
    }
    
    /**
     * Import settings via AJAX
     */
    public function import_settings_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        $import_data = json_decode(stripslashes($_POST['import_data'] ?? ''), true);
        
        if (!$import_data) {
            wp_send_json_error(__('Invalid import data format.', WCFM_TEXT_DOMAIN));
        }
        
        // Validate import data
        if (!isset($import_data['plugin']) || $import_data['plugin'] !== 'WooCommerce Checkout Fields Manager') {
            wp_send_json_error(__('This file is not a valid WCFM export.', WCFM_TEXT_DOMAIN));
        }
        
        // Create backup before import
        $this->create_settings_backup();
        
        try {
            // Import settings
            if (isset($import_data['settings'])) {
                WCFM_Core::update_settings($import_data['settings']);
            }
            
            // Import custom fields
            if (isset($import_data['custom_fields'])) {
                WCFM_Settings::import_custom_fields($import_data['custom_fields']);
            }
            
            // Clear caches
            $this->clear_field_cache();
            
            wp_send_json_success(__('Settings imported successfully!', WCFM_TEXT_DOMAIN));
            
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to import settings: ', WCFM_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * Save field order via AJAX
     */
    public function save_field_order_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        $section = sanitize_text_field($_POST['section'] ?? '');
        $field_order = array_map('sanitize_text_field', $_POST['field_order'] ?? array());
        
        if (empty($section) || empty($field_order)) {
            wp_send_json_error(__('Invalid data provided.', WCFM_TEXT_DOMAIN));
        }
        
        $settings = WCFM_Core::get_settings();
        $section_key = $section . '_fields';
        
        if (!isset($settings[$section_key])) {
            $settings[$section_key] = array();
        }
        
        // Update priorities based on order
        foreach ($field_order as $index => $field_key) {
            if (isset($settings[$section_key][$field_key])) {
                $settings[$section_key][$field_key]['priority'] = ($index + 1) * 10;
            }
        }
        
        // Save updated settings
        if (WCFM_Core::update_settings($settings)) {
            wp_send_json_success(__('Field order saved successfully!', WCFM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to save field order.', WCFM_TEXT_DOMAIN));
        }
    }
    
    /**
     * Check database status via AJAX
     */
    public function check_database_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
        $db_version = get_option('wcfm_db_version', '0');
        
        $status = array(
            'table_exists' => $table_exists,
            'db_version' => $db_version,
            'plugin_version' => WCFM_VERSION,
            'needs_repair' => !$table_exists || version_compare($db_version, WCFM_VERSION, '<'),
        );
        
        if ($table_exists) {
            $status['custom_fields_count'] = absint($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
            
            // Test table functionality
            $test_query = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
            $status['table_columns'] = count($test_query);
            $status['table_functional'] = !empty($test_query);
        } else {
            $status['custom_fields_count'] = 0;
            $status['table_columns'] = 0;
            $status['table_functional'] = false;
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * Sanitize fields data
     */
    private function sanitize_fields_data($fields_data) {
        $sanitized = array();
        
        foreach ($fields_data as $field_key => $field_config) {
            $sanitized_key = sanitize_key($field_key);
            
            $sanitized[$sanitized_key] = array(
                'enabled' => isset($field_config['enabled']) ? true : false,
                'required' => isset($field_config['required']) ? true : false,
                'priority' => isset($field_config['priority']) ? absint($field_config['priority']) : 10,
                'label' => isset($field_config['label']) ? sanitize_text_field($field_config['label']) : '',
            );
            
            // Add any additional field-specific settings
            if (isset($field_config['placeholder'])) {
                $sanitized[$sanitized_key]['placeholder'] = sanitize_text_field($field_config['placeholder']);
            }
            
            if (isset($field_config['class'])) {
                $sanitized[$sanitized_key]['class'] = array_map('sanitize_html_class', (array) $field_config['class']);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize rules data
     */
    private function sanitize_rules_data($rules_data) {
        $sanitized = array();
        
        if (isset($rules_data['virtual_products'])) {
            $sanitized['virtual_products'] = array(
                'hide_shipping' => isset($rules_data['virtual_products']['hide_shipping']) ? true : false,
                'hide_billing_address' => isset($rules_data['virtual_products']['hide_billing_address']) ? true : false,
            );
        }
        
        if (isset($rules_data['downloadable_products'])) {
            $sanitized['downloadable_products'] = array(
                'hide_shipping' => isset($rules_data['downloadable_products']['hide_shipping']) ? true : false,
                'hide_billing_address' => isset($rules_data['downloadable_products']['hide_billing_address']) ? true : false,
            );
        }
        
        return $sanitized;
    }
    
    /**
     * Create settings backup
     */
    private function create_settings_backup() {
        $settings = WCFM_Core::get_settings();
        $custom_fields = WCFM_Settings::export_custom_fields();
        
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'settings' => $settings,
            'custom_fields' => $custom_fields,
        );
        
        // Store up to 5 backups
        $backups = get_option('wcfm_settings_backups', array());
        
        // Add new backup to the beginning
        array_unshift($backups, $backup_data);
        
        // Keep only the last 5 backups
        $backups = array_slice($backups, 0, 5);
        
        update_option('wcfm_settings_backups', $backups);
    }
    
    /**
     * Clear field cache
     */
    private function clear_field_cache() {
        // Clear any WordPress caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear object cache
        wp_cache_delete('wcfm_field_settings', 'wcfm');
        wp_cache_delete('wcfm_custom_fields', 'wcfm');
        
        // Clear transients
        delete_transient('wcfm_checkout_fields');
        delete_transient('wcfm_field_configuration');
        
        do_action('wcfm_cache_cleared');
    }
}