<?php
/**
 * Admin functionality class
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Admin Class
 */
class WCFM_Admin {
    
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
        add_action('admin_init', array($this, 'admin_init'));
        
        // AJAX handlers
        add_action('wp_ajax_wcfm_save_settings', array($this, 'save_settings_ajax'));
        add_action('wp_ajax_wcfm_reset_settings', array($this, 'reset_settings_ajax'));
        add_action('wp_ajax_wcfm_export_settings', array($this, 'export_settings_ajax'));
        add_action('wp_ajax_wcfm_import_settings', array($this, 'import_settings_ajax'));
        add_action('wp_ajax_wcfm_save_field_order', array($this, 'save_field_order_ajax'));
        add_action('wp_ajax_wcfm_repair_database', array($this, 'repair_database_ajax'));
        add_action('wp_ajax_wcfm_check_database', array($this, 'check_database_ajax'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(WCFM_PLUGIN_PATH . 'woocommerce-checkout-fields-manager.php'), array($this, 'add_settings_link'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
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
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Register settings
        register_setting('wcfm_settings_group', 'wcfm_settings');
        
        // Check for actions
        if (isset($_GET['wcfm_action'])) {
            $this->handle_admin_actions();
        }
    }
    
    /**
     * Handle admin actions
     */
    private function handle_admin_actions() {
        $action = sanitize_text_field($_GET['wcfm_action']);
        
        switch ($action) {
            case 'backup_settings':
                $this->create_settings_backup();
                break;
            case 'restore_backup':
                $this->restore_settings_backup();
                break;
        }
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wcfm-checkout-fields') . '">' . __('Settings', WCFM_TEXT_DOMAIN) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'billing';
        $settings = WCFM_Core::get_settings();
        ?>
        <div class="wrap wcfm-admin-wrap">
            <?php $this->render_admin_header(); ?>
            
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="?page=wcfm-checkout-fields&tab=billing" class="nav-tab <?php echo $active_tab === 'billing' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-id-alt"></span>
                    <?php _e('Billing Fields', WCFM_TEXT_DOMAIN); ?>
                </a>
                <a href="?page=wcfm-checkout-fields&tab=shipping" class="nav-tab <?php echo $active_tab === 'shipping' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php _e('Shipping Fields', WCFM_TEXT_DOMAIN); ?>
                </a>
                <a href="?page=wcfm-checkout-fields&tab=additional" class="nav-tab <?php echo $active_tab === 'additional' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Additional Fields', WCFM_TEXT_DOMAIN); ?>
                </a>
                <a href="?page=wcfm-checkout-fields&tab=rules" class="nav-tab <?php echo $active_tab === 'rules' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Product Type Rules', WCFM_TEXT_DOMAIN); ?>
                </a>
                <a href="?page=wcfm-checkout-fields&tab=custom" class="nav-tab <?php echo $active_tab === 'custom' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-customizer"></span>
                    <?php _e('Custom Fields', WCFM_TEXT_DOMAIN); ?>
                </a>
                <a href="?page=wcfm-checkout-fields&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Advanced', WCFM_TEXT_DOMAIN); ?>
                </a>
            </nav>
            
            <div class="wcfm-tab-content">
                <?php
                switch ($active_tab) {
                    case 'billing':
                        $this->render_billing_fields_tab($settings);
                        break;
                    case 'shipping':
                        $this->render_shipping_fields_tab($settings);
                        break;
                    case 'additional':
                        $this->render_additional_fields_tab($settings);
                        break;
                    case 'rules':
                        $this->render_rules_tab($settings);
                        break;
                    case 'custom':
                        $this->render_custom_fields_tab($settings);
                        break;
                    case 'advanced':
                        $this->render_advanced_tab($settings);
                        break;
                    default:
                        $this->render_billing_fields_tab($settings);
                }
                ?>
            </div>
            
            <?php $this->render_save_actions(); ?>
        </div>
        
        <?php $this->render_modals(); ?>
        <?php
    }
    
    /**
     * Render admin header
     */
    private function render_admin_header() {
        ?>
        <div class="wcfm-admin-header">
            <div class="wcfm-header-left">
                <h1 class="wp-heading-inline">
                    <?php echo esc_html(get_admin_page_title()); ?>
                    <span class="wcfm-version">v<?php echo WCFM_VERSION; ?></span>
                </h1>
                <p class="wcfm-subtitle">
                    <?php _e('Complete control over WooCommerce checkout fields with Block Checkout support', WCFM_TEXT_DOMAIN); ?>
                </p>
            </div>
            
            <div class="wcfm-header-right">
                <div class="wcfm-logo">
                    <img src="<?php echo WCFM_PLUGIN_URL; ?>assets/images/logo.png" alt="Smartify Solutions" />
                    <div class="wcfm-credits">
                        <span class="wcfm-by"><?php _e('by', WCFM_TEXT_DOMAIN); ?></span>
                        <strong>Smartify Solutions</strong>
                        <a href="https://smartifysolutions.com/" target="_blank" class="wcfm-website">
                            <?php _e('Visit Website', WCFM_TEXT_DOMAIN); ?>
                        </a>
                    </div>
                </div>
                
                <div class="wcfm-quick-actions">
                    <button type="button" id="wcfm-export-settings" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" id="wcfm-import-settings" class="button">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Import', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <input type="file" id="wcfm-import-file" accept=".json" style="display: none;" />
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render billing fields tab
     */
    private function render_billing_fields_tab($settings) {
        $billing_fields = isset($settings['billing_fields']) ? $settings['billing_fields'] : array();
        $default_labels = WCFM_Core::get_default_field_labels();
        
        $default_billing_fields = array(
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
        );
        
        ?>
        <div class="wcfm-fields-manager">
            <div class="wcfm-section-header">
                <h2>
                    <span class="dashicons dashicons-id-alt"></span>
                    <?php _e('Billing Fields Configuration', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php _e('Configure which billing fields to show, hide, or make required during checkout.', WCFM_TEXT_DOMAIN); ?></p>
                
                <div class="wcfm-section-actions">
                    <button type="button" class="button wcfm-bulk-enable" data-section="billing">
                        <?php _e('Enable All', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-bulk-disable" data-section="billing">
                        <?php _e('Disable All', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-reset-defaults" data-section="billing">
                        <?php _e('Reset to Defaults', WCFM_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <?php $this->render_fields_search(); ?>
            
            <div class="wcfm-fields-table-wrapper">
                <table class="wcfm-fields-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="wcfm-field-sort" width="40">
                                <span class="dashicons dashicons-menu" title="<?php _e('Drag to reorder', WCFM_TEXT_DOMAIN); ?>"></span>
                            </th>
                            <th class="wcfm-field-name" width="200"><?php _e('Field Key', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-label"><?php _e('Display Label', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-enabled" width="100"><?php _e('Enabled', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-required" width="100"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-priority" width="80"><?php _e('Priority', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-actions" width="120"><?php _e('Actions', WCFM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wcfm-billing-fields" class="wcfm-sortable">
                        <?php
                        foreach ($default_billing_fields as $field_key => $default_config) {
                            $field_config = isset($billing_fields[$field_key]) ? array_merge($default_config, $billing_fields[$field_key]) : $default_config;
                            $field_label = isset($default_labels[$field_key]) ? $default_labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                            
                            $this->render_field_row('billing', $field_key, $field_label, $field_config);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php $this->render_field_statistics('billing', $default_billing_fields, $billing_fields); ?>
        </div>
        <?php
    }
    
    /**
     * Render shipping fields tab
     */
    private function render_shipping_fields_tab($settings) {
        $shipping_fields = isset($settings['shipping_fields']) ? $settings['shipping_fields'] : array();
        $default_labels = WCFM_Core::get_default_field_labels();
        
        $default_shipping_fields = array(
            'shipping_first_name' => array('enabled' => true, 'required' => true, 'priority' => 10),
            'shipping_last_name' => array('enabled' => true, 'required' => true, 'priority' => 20),
            'shipping_company' => array('enabled' => true, 'required' => false, 'priority' => 30),
            'shipping_country' => array('enabled' => true, 'required' => true, 'priority' => 40),
            'shipping_address_1' => array('enabled' => true, 'required' => true, 'priority' => 50),
            'shipping_address_2' => array('enabled' => true, 'required' => false, 'priority' => 60),
            'shipping_city' => array('enabled' => true, 'required' => true, 'priority' => 70),
            'shipping_state' => array('enabled' => true, 'required' => true, 'priority' => 80),
            'shipping_postcode' => array('enabled' => true, 'required' => true, 'priority' => 90),
        );
        
        ?>
        <div class="wcfm-fields-manager">
            <div class="wcfm-section-header">
                <h2>
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php _e('Shipping Fields Configuration', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php _e('Configure which shipping fields to show, hide, or make required during checkout.', WCFM_TEXT_DOMAIN); ?></p>
                
                <div class="wcfm-section-actions">
                    <button type="button" class="button wcfm-bulk-enable" data-section="shipping">
                        <?php _e('Enable All', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-bulk-disable" data-section="shipping">
                        <?php _e('Disable All', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-reset-defaults" data-section="shipping">
                        <?php _e('Reset to Defaults', WCFM_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <?php $this->render_fields_search(); ?>
            
            <div class="wcfm-fields-table-wrapper">
                <table class="wcfm-fields-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="wcfm-field-sort" width="40">
                                <span class="dashicons dashicons-menu" title="<?php _e('Drag to reorder', WCFM_TEXT_DOMAIN); ?>"></span>
                            </th>
                            <th class="wcfm-field-name" width="200"><?php _e('Field Key', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-label"><?php _e('Display Label', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-enabled" width="100"><?php _e('Enabled', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-required" width="100"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-priority" width="80"><?php _e('Priority', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-actions" width="120"><?php _e('Actions', WCFM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wcfm-shipping-fields" class="wcfm-sortable">
                        <?php
                        foreach ($default_shipping_fields as $field_key => $default_config) {
                            $field_config = isset($shipping_fields[$field_key]) ? array_merge($default_config, $shipping_fields[$field_key]) : $default_config;
                            $field_label = isset($default_labels[$field_key]) ? $default_labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                            
                            $this->render_field_row('shipping', $field_key, $field_label, $field_config);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php $this->render_field_statistics('shipping', $default_shipping_fields, $shipping_fields); ?>
        </div>
        <?php
    }
    
    /**
     * Render additional fields tab
     */
    private function render_additional_fields_tab($settings) {
        $additional_fields = isset($settings['additional_fields']) ? $settings['additional_fields'] : array();
        $default_labels = WCFM_Core::get_default_field_labels();
        
        $default_additional_fields = array(
            'order_comments' => array('enabled' => true, 'required' => false, 'priority' => 10),
        );
        
        ?>
        <div class="wcfm-fields-manager">
            <div class="wcfm-section-header">
                <h2>
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Additional Fields Configuration', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php _e('Configure additional checkout fields like order notes and other custom elements.', WCFM_TEXT_DOMAIN); ?></p>
                
                <div class="wcfm-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <p><?php _e('Additional fields appear after billing and shipping sections. Use the Custom Fields tab to create new fields.', WCFM_TEXT_DOMAIN); ?></p>
                </div>
            </div>
            
            <div class="wcfm-fields-table-wrapper">
                <table class="wcfm-fields-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="wcfm-field-sort" width="40">
                                <span class="dashicons dashicons-menu" title="<?php _e('Drag to reorder', WCFM_TEXT_DOMAIN); ?>"></span>
                            </th>
                            <th class="wcfm-field-name" width="200"><?php _e('Field Key', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-label"><?php _e('Display Label', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-enabled" width="100"><?php _e('Enabled', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-required" width="100"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-priority" width="80"><?php _e('Priority', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-actions" width="120"><?php _e('Actions', WCFM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wcfm-additional-fields" class="wcfm-sortable">
                        <?php
                        foreach ($default_additional_fields as $field_key => $default_config) {
                            $field_config = isset($additional_fields[$field_key]) ? array_merge($default_config, $additional_fields[$field_key]) : $default_config;
                            $field_label = isset($default_labels[$field_key]) ? $default_labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                            
                            $this->render_field_row('additional', $field_key, $field_label, $field_config);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render rules tab
     */
    private function render_rules_tab($settings) {
        $rules = isset($settings['product_type_rules']) ? $settings['product_type_rules'] : array();
        ?>
        <div class="wcfm-rules-manager">
            <div class="wcfm-section-header">
                <h2>
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Product Type Rules', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php _e('Configure automatic field behavior based on product types in the cart.', WCFM_TEXT_DOMAIN); ?></p>
            </div>
            
            <div class="wcfm-rules-grid">
                <div class="wcfm-rule-card">
                    <div class="wcfm-rule-header">
                        <h3>
                            <span class="dashicons dashicons-cloud"></span>
                            <?php _e('Virtual Products', WCFM_TEXT_DOMAIN); ?>
                        </h3>
                        <p><?php _e('Rules applied when cart contains only virtual products', WCFM_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="wcfm-rule-options">
                        <label class="wcfm-checkbox-label">
                            <input type="checkbox" name="product_type_rules[virtual_products][hide_shipping]" value="1" 
                                <?php checked(isset($rules['virtual_products']['hide_shipping']) ? $rules['virtual_products']['hide_shipping'] : false); ?> />
                            <span class="wcfm-checkbox-custom"></span>
                            <span class="wcfm-checkbox-text">
                                <strong><?php _e('Hide Shipping Fields', WCFM_TEXT_DOMAIN); ?></strong>
                                <span><?php _e('Hide all shipping-related fields for virtual products', WCFM_TEXT_DOMAIN); ?></span>
                            </span>
                        </label>
                        
                        <label class="wcfm-checkbox-label">
                            <input type="checkbox" name="product_type_rules[virtual_products][hide_billing_address]" value="1" 
                                <?php checked(isset($rules['virtual_products']['hide_billing_address']) ? $rules['virtual_products']['hide_billing_address'] : false); ?> />
                            <span class="wcfm-checkbox-custom"></span>
                            <span class="wcfm-checkbox-text">
                                <strong><?php _e('Hide Billing Address', WCFM_TEXT_DOMAIN); ?></strong>
                                <span><?php _e('Hide billing address fields (keep only name, email, phone)', WCFM_TEXT_DOMAIN); ?></span>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="wcfm-rule-card">
                    <div class="wcfm-rule-header">
                        <h3>
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Downloadable Products', WCFM_TEXT_DOMAIN); ?>
                        </h3>
                        <p><?php _e('Rules applied when cart contains only downloadable products', WCFM_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="wcfm-rule-options">
                        <label class="wcfm-checkbox-label">
                            <input type="checkbox" name="product_type_rules[downloadable_products][hide_shipping]" value="1" 
                                <?php checked(isset($rules['downloadable_products']['hide_shipping']) ? $rules['downloadable_products']['hide_shipping'] : false); ?> />
                            <span class="wcfm-checkbox-custom"></span>
                            <span class="wcfm-checkbox-text">
                                <strong><?php _e('Hide Shipping Fields', WCFM_TEXT_DOMAIN); ?></strong>
                                <span><?php _e('Hide all shipping-related fields for downloadable products', WCFM_TEXT_DOMAIN); ?></span>
                            </span>
                        </label>
                        
                        <label class="wcfm-checkbox-label">
                            <input type="checkbox" name="product_type_rules[downloadable_products][hide_billing_address]" value="1" 
                                <?php checked(isset($rules['downloadable_products']['hide_billing_address']) ? $rules['downloadable_products']['hide_billing_address'] : false); ?> />
                            <span class="wcfm-checkbox-custom"></span>
                            <span class="wcfm-checkbox-text">
                                <strong><?php _e('Hide Billing Address', WCFM_TEXT_DOMAIN); ?></strong>
                                <span><?php _e('Hide billing address fields (keep only name, email, phone)', WCFM_TEXT_DOMAIN); ?></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="wcfm-info-box wcfm-warning">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php _e('Important Note:', WCFM_TEXT_DOMAIN); ?></strong>
                    <p><?php _e('These rules are applied automatically based on cart contents. If the cart contains mixed product types, no automatic rules will apply.', WCFM_TEXT_DOMAIN); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render custom fields tab
     */
    private function render_custom_fields_tab($settings) {
        ?>
        <div class="wcfm-custom-fields-manager">
            <div class="wcfm-section-header">
                <h2>
                    <span class="dashicons dashicons-admin-customizer"></span>
                    <?php _e('Custom Fields Management', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php _e('Create, edit, and manage custom checkout fields with advanced options.', WCFM_TEXT_DOMAIN); ?></p>
                
                <div class="wcfm-section-actions">
                    <button type="button" id="wcfm-add-custom-field" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Add Custom Field', WCFM_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <div class="wcfm-custom-fields-stats">
                <div class="wcfm-stat-box">
                    <span class="wcfm-stat-number" id="wcfm-total-custom-fields">0</span>
                    <span class="wcfm-stat-label"><?php _e('Total Fields', WCFM_TEXT_DOMAIN); ?></span>
                </div>
                <div class="wcfm-stat-box">
                    <span class="wcfm-stat-number" id="wcfm-enabled-custom-fields">0</span>
                    <span class="wcfm-stat-label"><?php _e('Enabled', WCFM_TEXT_DOMAIN); ?></span>
                </div>
                <div class="wcfm-stat-box">
                    <span class="wcfm-stat-number" id="wcfm-required-custom-fields">0</span>
                    <span class="wcfm-stat-label"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></span>
                </div>
            </div>
            
            <div id="wcfm-custom-fields-list">
                <div class="wcfm-loading-placeholder">
                    <span class="spinner is-active"></span>
                    <p><?php _e('Loading custom fields...', WCFM_TEXT_DOMAIN); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render advanced tab
     */
    private function render_advanced_tab($settings) {
        ?>
        <div class="wcfm-advanced-settings">
            <div class="wcfm-section-header">
                <h2>
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Advanced Settings', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php _e('Advanced configuration options and tools for power users.', WCFM_TEXT_DOMAIN); ?></p>
            </div>
            
            <div class="wcfm-advanced-grid">
                <div class="wcfm-advanced-card">
                    <h3><?php _e('Import/Export Settings', WCFM_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Backup and restore your field configurations.', WCFM_TEXT_DOMAIN); ?></p>
                    
                    <div class="wcfm-advanced-actions">
                        <button type="button" id="wcfm-advanced-export" class="button">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export All Settings', WCFM_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" id="wcfm-advanced-import" class="button">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Import Settings', WCFM_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
                
                <div class="wcfm-advanced-card">
                    <h3><?php _e('Database Tools', WCFM_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Repair and maintain plugin database tables.', WCFM_TEXT_DOMAIN); ?></p>
                    
                    <div class="wcfm-advanced-actions">
                        <button type="button" id="wcfm-repair-database" class="button">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Repair Database Tables', WCFM_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" id="wcfm-check-tables" class="button">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Check Table Status', WCFM_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                    
                    <div class="wcfm-database-status">
                        <?php $this->display_database_status(); ?>
                    </div>
                </div>
                
                <div class="wcfm-advanced-card">
                    <h3><?php _e('Reset Options', WCFM_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Reset specific sections or all settings to defaults.', WCFM_TEXT_DOMAIN); ?></p>
                    
                    <div class="wcfm-advanced-actions">
                        <button type="button" class="button wcfm-reset-section" data-section="billing">
                            <?php _e('Reset Billing Fields', WCFM_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="button wcfm-reset-section" data-section="shipping">
                            <?php _e('Reset Shipping Fields', WCFM_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="button wcfm-reset-all" style="color: #d63638;">
                            <?php _e('Reset All Settings', WCFM_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
                
                <div class="wcfm-advanced-card">
                    <h3><?php _e('System Information', WCFM_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Plugin and system status information.', WCFM_TEXT_DOMAIN); ?></p>
                    
                    <div class="wcfm-system-info">
                        <table class="wcfm-system-table">
                            <tr>
                                <td><?php _e('Plugin Version:', WCFM_TEXT_DOMAIN); ?></td>
                                <td><?php echo WCFM_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('WordPress Version:', WCFM_TEXT_DOMAIN); ?></td>
                                <td><?php echo get_bloginfo('version'); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('WooCommerce Version:', WCFM_TEXT_DOMAIN); ?></td>
                                <td><?php echo defined('WC_VERSION') ? WC_VERSION : __('Not Detected', WCFM_TEXT_DOMAIN); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Block Checkout:', WCFM_TEXT_DOMAIN); ?></td>
                                <td><?php echo class_exists('Automattic\WooCommerce\Blocks\Package') ? __('Supported', WCFM_TEXT_DOMAIN) : __('Not Available', WCFM_TEXT_DOMAIN); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render field row
     */
    private function render_field_row($section, $field_key, $field_label, $field_config) {
        $enabled = isset($field_config['enabled']) ? $field_config['enabled'] : true;
        $required = isset($field_config['required']) ? $field_config['required'] : false;
        $priority = isset($field_config['priority']) ? $field_config['priority'] : 10;
        $custom_label = isset($field_config['label']) ? $field_config['label'] : $field_label;
        ?>
        <tr class="wcfm-field-row" data-field="<?php echo esc_attr($field_key); ?>" data-section="<?php echo esc_attr($section); ?>">
            <td class="wcfm-field-sort">
                <span class="dashicons dashicons-menu wcfm-drag-handle" title="<?php _e('Drag to reorder', WCFM_TEXT_DOMAIN); ?>"></span>
            </td>
            <td class="wcfm-field-name">
                <strong><?php echo esc_html($field_key); ?></strong>
                <div class="wcfm-field-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', explode('_', $field_key)[1] ?? 'field'))); ?></div>
            </td>
            <td class="wcfm-field-label">
                <input type="text" 
                       name="<?php echo esc_attr($section); ?>_fields[<?php echo esc_attr($field_key); ?>][label]" 
                       value="<?php echo esc_attr($custom_label); ?>" 
                       class="regular-text wcfm-field-label-input" 
                       placeholder="<?php echo esc_attr($field_label); ?>" />
            </td>
            <td class="wcfm-field-enabled">
                <label class="wcfm-switch">
                    <input type="checkbox" 
                           name="<?php echo esc_attr($section); ?>_fields[<?php echo esc_attr($field_key); ?>][enabled]" 
                           value="1" 
                           <?php checked($enabled); ?> 
                           class="wcfm-enabled-toggle" />
                    <span class="wcfm-slider"></span>
                </label>
            </td>
            <td class="wcfm-field-required">
                <label class="wcfm-switch">
                    <input type="checkbox" 
                           name="<?php echo esc_attr($section); ?>_fields[<?php echo esc_attr($field_key); ?>][required]" 
                           value="1" 
                           <?php checked($required); ?> 
                           class="wcfm-required-toggle" />
                    <span class="wcfm-slider"></span>
                </label>
            </td>
            <td class="wcfm-field-priority">
                <input type="number" 
                       name="<?php echo esc_attr($section); ?>_fields[<?php echo esc_attr($field_key); ?>][priority]" 
                       value="<?php echo esc_attr($priority); ?>" 
                       min="1" 
                       max="999" 
                       class="small-text wcfm-priority-input" />
            </td>
            <td class="wcfm-field-actions">
                <button type="button" class="button button-small wcfm-field-edit" data-field="<?php echo esc_attr($field_key); ?>" title="<?php _e('Edit Field', WCFM_TEXT_DOMAIN); ?>">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="button button-small wcfm-field-reset" data-field="<?php echo esc_attr($field_key); ?>" title="<?php _e('Reset to Default', WCFM_TEXT_DOMAIN); ?>">
                    <span class="dashicons dashicons-undo"></span>
                </button>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render fields search
     */
    private function render_fields_search() {
        ?>
        <div class="wcfm-search-wrapper">
            <input type="text" 
                   id="wcfm-fields-search" 
                   class="wcfm-search-input" 
                   placeholder="<?php _e('Search fields...', WCFM_TEXT_DOMAIN); ?>" />
            <span class="dashicons dashicons-search wcfm-search-icon"></span>
        </div>
        <?php
    }
    
    /**
     * Render field statistics
     */
    private function render_field_statistics($section, $default_fields, $current_fields) {
        $total = count($default_fields);
        $enabled = 0;
        $required = 0;
        
        foreach ($default_fields as $field_key => $default_config) {
            $field_config = isset($current_fields[$field_key]) ? array_merge($default_config, $current_fields[$field_key]) : $default_config;
            
            if (isset($field_config['enabled']) && $field_config['enabled']) {
                $enabled++;
            }
            
            if (isset($field_config['required']) && $field_config['required']) {
                $required++;
            }
        }
        ?>
        <div class="wcfm-field-stats">
            <div class="wcfm-stat-item">
                <span class="wcfm-stat-number"><?php echo $total; ?></span>
                <span class="wcfm-stat-label"><?php _e('Total Fields', WCFM_TEXT_DOMAIN); ?></span>
            </div>
            <div class="wcfm-stat-item">
                <span class="wcfm-stat-number"><?php echo $enabled; ?></span>
                <span class="wcfm-stat-label"><?php _e('Enabled', WCFM_TEXT_DOMAIN); ?></span>
            </div>
            <div class="wcfm-stat-item">
                <span class="wcfm-stat-number"><?php echo $required; ?></span>
                <span class="wcfm-stat-label"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></span>
            </div>
            <div class="wcfm-stat-item">
                <span class="wcfm-stat-number"><?php echo ($total - $enabled); ?></span>
                <span class="wcfm-stat-label"><?php _e('Disabled', WCFM_TEXT_DOMAIN); ?></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render save actions
     */
    private function render_save_actions() {
        ?>
        <div class="wcfm-save-actions">
            <div class="wcfm-save-left">
                <span class="wcfm-save-status" id="wcfm-save-status"></span>
            </div>
            <div class="wcfm-save-right">
                <button type="button" id="wcfm-reset-settings" class="button">
                    <span class="dashicons dashicons-undo"></span>
                    <?php _e('Reset to Defaults', WCFM_TEXT_DOMAIN); ?>
                </button>
                <button type="button" id="wcfm-save-settings" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Save All Changes', WCFM_TEXT_DOMAIN); ?>
                </button>
                <span class="spinner" id="wcfm-save-spinner"></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render modals
     */
    private function render_modals() {
        ?>
        <!-- Custom Field Modal -->
        <div id="wcfm-custom-field-modal" class="wcfm-modal" style="display: none;">
            <div class="wcfm-modal-content">
                <div class="wcfm-modal-header">
                    <h3><?php _e('Add/Edit Custom Field', WCFM_TEXT_DOMAIN); ?></h3>
                    <button type="button" class="wcfm-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wcfm-modal-body">
                    <form id="wcfm-custom-field-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="field_key"><?php _e('Field Key', WCFM_TEXT_DOMAIN); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="field_key" name="field_key" class="regular-text" required />
                                    <p class="description"><?php _e('Unique identifier for the field (e.g., custom_field_1). Only lowercase letters, numbers, and underscores allowed.', WCFM_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="field_label"><?php _e('Field Label', WCFM_TEXT_DOMAIN); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="field_label" name="field_label" class="regular-text" required />
                                    <p class="description"><?php _e('The label shown to customers during checkout.', WCFM_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="field_type"><?php _e('Field Type', WCFM_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="field_type" name="field_type" class="regular-text">
                                        <option value="text"><?php _e('Text', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="textarea"><?php _e('Textarea', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="select"><?php _e('Select Dropdown', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="checkbox"><?php _e('Checkbox', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="radio"><?php _e('Radio Buttons', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="email"><?php _e('Email', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="tel"><?php _e('Phone Number', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="number"><?php _e('Number', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="date"><?php _e('Date', WCFM_TEXT_DOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="field_section"><?php _e('Field Section', WCFM_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <select id="field_section" name="field_section" class="regular-text">
                                        <option value="billing"><?php _e('Billing', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="shipping"><?php _e('Shipping', WCFM_TEXT_DOMAIN); ?></option>
                                        <option value="additional"><?php _e('Additional', WCFM_TEXT_DOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="field_placeholder"><?php _e('Placeholder', WCFM_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="field_placeholder" name="field_placeholder" class="regular-text" />
                                    <p class="description"><?php _e('Placeholder text shown inside the field.', WCFM_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr class="field-options-row" style="display: none;">
                                <th scope="row">
                                    <label for="field_options"><?php _e('Options', WCFM_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <textarea id="field_options" name="field_options" rows="4" class="large-text"></textarea>
                                    <p class="description"><?php _e('One option per line. Format: value|label (e.g., "us|United States"). For simple options, just enter the value.', WCFM_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Field Settings', WCFM_TEXT_DOMAIN); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" id="field_required" name="field_required" value="1" />
                                            <?php _e('Make this field required', WCFM_TEXT_DOMAIN); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" id="field_enabled" name="field_enabled" value="1" checked />
                                            <?php _e('Enable this field', WCFM_TEXT_DOMAIN); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="field_priority"><?php _e('Priority', WCFM_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="field_priority" name="field_priority" value="10" min="1" max="999" class="small-text" />
                                    <p class="description"><?php _e('Lower numbers appear first. Default is 10.', WCFM_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div id="wcfm-field-preview" class="wcfm-field-preview" style="display: none;">
                            <h4><?php _e('Field Preview', WCFM_TEXT_DOMAIN); ?></h4>
                            <div id="wcfm-preview-content"></div>
                        </div>
                    </form>
                </div>
                <div class="wcfm-modal-footer">
                    <button type="button" id="wcfm-save-custom-field" class="button button-primary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Save Field', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-modal-close">
                        <?php _e('Cancel', WCFM_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings via AJAX
     */
    public function save_settings_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcfm_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', WCFM_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions.', WCFM_TEXT_DOMAIN));
        }
        
        // Parse form data
        $form_data = array();
        if (isset($_POST['form_data'])) {
            parse_str($_POST['form_data'], $form_data);
        }
        
        $settings = array();
        
        // Process billing fields
        if (isset($form_data['billing_fields'])) {
            $settings['billing_fields'] = $this->sanitize_fields_data($form_data['billing_fields']);
        }
        
        // Process shipping fields
        if (isset($form_data['shipping_fields'])) {
            $settings['shipping_fields'] = $this->sanitize_fields_data($form_data['shipping_fields']);
        }
        
        // Process additional fields
        if (isset($form_data['additional_fields'])) {
            $settings['additional_fields'] = $this->sanitize_fields_data($form_data['additional_fields']);
        }
        
        // Process product type rules
        if (isset($form_data['product_type_rules'])) {
            $settings['product_type_rules'] = $this->sanitize_rules_data($form_data['product_type_rules']);
        }
        
        // Create backup before saving
        $this->create_settings_backup();
        
        // Save settings
        if (WCFM_Core::update_settings($settings)) {
            // Clear any caches
            $this->clear_field_cache();
            
            do_action('wcfm_settings_saved', $settings);
            
            wp_send_json_success(__('Settings saved successfully!', WCFM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to save settings. Please try again.', WCFM_TEXT_DOMAIN));
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
     * Restore settings backup
     */
    private function restore_settings_backup() {
        if (!isset($_GET['backup_index'])) {
            return;
        }
        
        $backup_index = absint($_GET['backup_index']);
        $backups = get_option('wcfm_settings_backups', array());
        
        if (!isset($backups[$backup_index])) {
            wp_die(__('Invalid backup selected.', WCFM_TEXT_DOMAIN));
        }
        
        $backup = $backups[$backup_index];
        
        // Restore settings
        if (isset($backup['settings'])) {
            WCFM_Core::update_settings($backup['settings']);
        }
        
        // Restore custom fields
        if (isset($backup['custom_fields'])) {
            WCFM_Settings::import_custom_fields($backup['custom_fields']);
        }
        
        // Clear caches
        $this->clear_field_cache();
        
        // Redirect with success message
        wp_redirect(add_query_arg(array(
            'page' => 'wcfm-checkout-fields',
            'wcfm_message' => __('Backup restored successfully!', WCFM_TEXT_DOMAIN),
            'wcfm_type' => 'success'
        ), admin_url('admin.php')));
        exit;
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
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check for messages in URL
        if (isset($_GET['wcfm_message'])) {
            $message = sanitize_text_field($_GET['wcfm_message']);
            $type = isset($_GET['wcfm_type']) ? sanitize_text_field($_GET['wcfm_type']) : 'success';
            
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }
        
        // Check for WooCommerce compatibility
        if (current_user_can('manage_woocommerce') && isset($_GET['page']) && $_GET['page'] === 'wcfm-checkout-fields') {
            $this->check_compatibility_notices();
        }
    }
    
    /**
     * Repair database via AJAX
     */
    public function repair_database_ajax() {
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
        
        // Check if table was created successfully
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            wp_send_json_success(__('Database tables repaired successfully!', WCFM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to create database table.', WCFM_TEXT_DOMAIN));
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
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * Display database status
     */
    private function display_database_status() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_custom_fields';
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
        $db_version = get_option('wcfm_db_version', '0');
        
        ?>
        <table class="wcfm-system-table">
            <tr>
                <td><?php _e('Custom Fields Table:', WCFM_TEXT_DOMAIN); ?></td>
                <td>
                    <?php if ($table_exists): ?>
                        <span style="color: #46b450;">✓ <?php _e('Exists', WCFM_TEXT_DOMAIN); ?></span>
                    <?php else: ?>
                        <span style="color: #dc3232;">✗ <?php _e('Missing', WCFM_TEXT_DOMAIN); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><?php _e('Database Version:', WCFM_TEXT_DOMAIN); ?></td>
                <td><?php echo esc_html($db_version); ?></td>
            </tr>
            <tr>
                <td><?php _e('Plugin Version:', WCFM_TEXT_DOMAIN); ?></td>
                <td><?php echo WCFM_VERSION; ?></td>
            </tr>
            <?php if ($table_exists): ?>
            <tr>
                <td><?php _e('Custom Fields Count:', WCFM_TEXT_DOMAIN); ?></td>
                <td><?php echo absint($wpdb->get_var("SELECT COUNT(*) FROM $table_name")); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Check compatibility notices
     */
    private function check_compatibility_notices() {
        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '7.0', '<')) {
            echo '<div class="notice notice-warning">';
            echo '<p>' . sprintf(
                __('WooCommerce Checkout Fields Manager recommends WooCommerce 7.0 or higher. You are currently running version %s.', WCFM_TEXT_DOMAIN),
                WC_VERSION
            ) . '</p>';
            echo '</div>';
        }
        
        // Check for Block Checkout
        if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
            echo '<div class="notice notice-info">';
            echo '<p>' . __('For full Block Checkout support, please ensure WooCommerce Blocks plugin is installed and active.', WCFM_TEXT_DOMAIN) . '</p>';
            echo '</div>';
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            echo '<div class="notice notice-error">';
            echo '<p>' . sprintf(
                __('WooCommerce Checkout Fields Manager requires PHP 7.4 or higher. You are currently running version %s. Please contact your hosting provider to upgrade.', WCFM_TEXT_DOMAIN),
                PHP_VERSION
            ) . '</p>';
            echo '</div>';
        }
        
        // Check for conflicts with other checkout field plugins
        $this->check_plugin_conflicts();
    }
    
    /**
     * Check for plugin conflicts
     */
    private function check_plugin_conflicts() {
        $conflicting_plugins = array(
            'woocommerce-checkout-field-editor/checkout-field-editor.php' => 'WooCommerce Checkout Field Editor',
            'wc-checkout-field-editor/wc-checkout-field-editor.php' => 'Checkout Field Editor for WooCommerce',
            'flexible-checkout-fields/flexible-checkout-fields.php' => 'Flexible Checkout Fields',
        );
        
        $active_conflicts = array();
        
        foreach ($conflicting_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $active_conflicts[] = $plugin_name;
            }
        }
        
        if (!empty($active_conflicts)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . __('Plugin Conflict Warning:', WCFM_TEXT_DOMAIN) . '</strong></p>';
            echo '<p>' . sprintf(
                __('The following plugins may conflict with WooCommerce Checkout Fields Manager: %s. Consider deactivating them to avoid conflicts.', WCFM_TEXT_DOMAIN),
                implode(', ', $active_conflicts)
            ) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Get admin page URL
     */
    public static function get_admin_url($tab = '', $args = array()) {
        $url_args = array('page' => 'wcfm-checkout-fields');
        
        if (!empty($tab)) {
            $url_args['tab'] = $tab;
        }
        
        if (!empty($args)) {
            $url_args = array_merge($url_args, $args);
        }
        
        return add_query_arg($url_args, admin_url('admin.php'));
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
            'content' => '
                <h3>' . __('WooCommerce Checkout Fields Manager', WCFM_TEXT_DOMAIN) . '</h3>
                <p>' . __('This plugin allows you to customize WooCommerce checkout fields with complete control over visibility, requirements, and ordering.', WCFM_TEXT_DOMAIN) . '</p>
                <ul>
                    <li>' . __('Show/hide any checkout field', WCFM_TEXT_DOMAIN) . '</li>
                    <li>' . __('Make fields required or optional', WCFM_TEXT_DOMAIN) . '</li>
                    <li>' . __('Reorder fields by drag and drop', WCFM_TEXT_DOMAIN) . '</li>
                    <li>' . __('Create custom fields with validation', WCFM_TEXT_DOMAIN) . '</li>
                    <li>' . __('Set rules based on product types', WCFM_TEXT_DOMAIN) . '</li>
                </ul>
            '
        ));
        
        // Field Management tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-fields',
            'title' => __('Field Management', WCFM_TEXT_DOMAIN),
            'content' => '
                <h3>' . __('Managing Checkout Fields', WCFM_TEXT_DOMAIN) . '</h3>
                <p>' . __('Use the toggle switches to enable/disable fields and make them required/optional.', WCFM_TEXT_DOMAIN) . '</p>
                <p><strong>' . __('Priority Numbers:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Lower numbers appear first. Default is 10.', WCFM_TEXT_DOMAIN) . '</p>
                <p><strong>' . __('Drag and Drop:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Drag the menu icon to reorder fields visually.', WCFM_TEXT_DOMAIN) . '</p>
                <p><strong>' . __('Search:', WCFM_TEXT_DOMAIN) . '</strong> ' . __('Use the search box to quickly find specific fields.', WCFM_TEXT_DOMAIN) . '</p>
            '
        ));
        
        // Custom Fields tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-custom',
            'title' => __('Custom Fields', WCFM_TEXT_DOMAIN),
            'content' => '
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
            '
        ));
        
        // Troubleshooting tab
        $screen->add_help_tab(array(
            'id' => 'wcfm-troubleshooting',
            'title' => __('Troubleshooting', WCFM_TEXT_DOMAIN),
            'content' => '
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
            '
        ));
        
        // Sidebar
        $screen->set_help_sidebar('
            <p><strong>' . __('For More Information:', WCFM_TEXT_DOMAIN) . '</strong></p>
            <p><a href="https://smartifysolutions.com/" target="_blank">' . __('Visit Smartify Solutions', WCFM_TEXT_DOMAIN) . '</a></p>
            <p><a href="mailto:support@smartifysolutions.com">' . __('Contact Support', WCFM_TEXT_DOMAIN) . '</a></p>
        ');
    }
    
    /**
     * Initialize admin
     */
    public static function init_admin() {
        $admin = self::get_instance();
        
        // Add help tabs
        add_action('load-woocommerce_page_wcfm-checkout-fields', array($admin, 'add_help_tabs'));
        
        // Add body classes
        add_filter('admin_body_class', array($admin, 'admin_body_class'));
        
        return $admin;
    }
}