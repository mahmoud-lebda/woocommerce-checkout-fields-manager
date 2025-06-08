<?php
/**
 * Admin Tabs Handler - Enhanced with Font Awesome
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Admin Tabs Class
 */
class WCFM_Admin_Tabs {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Available tabs
     */
    private $tabs = array();
    
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
        $this->init_tabs();
        add_action('admin_enqueue_scripts', array($this, 'enqueue_fontawesome'));
    }
    
    /**
     * Enqueue Font Awesome
     */
    public function enqueue_fontawesome() {
        // Only load on our plugin pages
        if (isset($_GET['page']) && $_GET['page'] === 'wcfm-checkout-fields') {
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                array(),
                '6.4.0'
            );
        }
    }
    
    /**
     * Initialize tabs
     */
    private function init_tabs() {
        $this->tabs = array(
            'billing' => array(
                'title' => __('Billing Fields', WCFM_TEXT_DOMAIN),
                'icon' => 'fas fa-user',
                'callback' => array($this, 'render_billing_fields_tab'),
            ),
            'shipping' => array(
                'title' => __('Shipping Fields', WCFM_TEXT_DOMAIN),
                'icon' => 'fas fa-shipping-fast',
                'callback' => array($this, 'render_shipping_fields_tab'),
            ),
            'additional' => array(
                'title' => __('Additional Fields', WCFM_TEXT_DOMAIN),
                'icon' => 'fas fa-plus-circle',
                'callback' => array($this, 'render_additional_fields_tab'),
            ),
            'rules' => array(
                'title' => __('Product Type Rules', WCFM_TEXT_DOMAIN),
                'icon' => 'fas fa-cogs',
                'callback' => array($this, 'render_rules_tab'),
            ),
            'custom' => array(
                'title' => __('Custom Fields', WCFM_TEXT_DOMAIN),
                'icon' => 'fas fa-edit',
                'callback' => array($this, 'render_custom_fields_tab'),
            ),
            'advanced' => array(
                'title' => __('Advanced', WCFM_TEXT_DOMAIN),
                'icon' => 'fas fa-tools',
                'callback' => array($this, 'render_advanced_tab'),
            ),
        );
    }
    
    /**
     * Render navigation
     */
    public function render_navigation($active_tab) {
        ?>
        <nav class="nav-tab-wrapper wp-clearfix">
            <?php foreach ($this->tabs as $tab_key => $tab): ?>
                <a href="?page=wcfm-checkout-fields&tab=<?php echo esc_attr($tab_key); ?>" 
                   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <i class="<?php echo esc_attr($tab['icon']); ?>"></i>
                    <?php echo esc_html($tab['title']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
    
    /**
     * Render tab content
     */
    public function render_tab_content($active_tab) {
        if (isset($this->tabs[$active_tab]) && is_callable($this->tabs[$active_tab]['callback'])) {
            call_user_func($this->tabs[$active_tab]['callback']);
        } else {
            $this->render_billing_fields_tab(); // Default fallback
        }
    }
    
    /**
     * Render billing fields tab
     */
    public function render_billing_fields_tab() {
        $settings = WCFM_Core::get_settings();
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
        
        $this->render_fields_table('billing', $default_billing_fields, $billing_fields, $default_labels);
    }
    
    /**
     * Render shipping fields tab
     */
    public function render_shipping_fields_tab() {
        $settings = WCFM_Core::get_settings();
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
        
        $this->render_fields_table('shipping', $default_shipping_fields, $shipping_fields, $default_labels);
    }
    
    /**
     * Render additional fields tab
     */
    public function render_additional_fields_tab() {
        $settings = WCFM_Core::get_settings();
        $additional_fields = isset($settings['additional_fields']) ? $settings['additional_fields'] : array();
        $default_labels = WCFM_Core::get_default_field_labels();
        
        $default_additional_fields = array(
            'order_comments' => array('enabled' => true, 'required' => false, 'priority' => 10),
        );
        
        $this->render_fields_table('additional', $default_additional_fields, $additional_fields, $default_labels);
    }
    
    /**
     * Render rules tab
     */
    public function render_rules_tab() {
        $settings = WCFM_Core::get_settings();
        $template_file = WCFM_PLUGIN_PATH . 'admin/templates/product-rules.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_rules_fallback($settings);
        }
    }
    
    /**
     * Render custom fields tab
     */
    public function render_custom_fields_tab() {
        $template_file = WCFM_PLUGIN_PATH . 'admin/templates/custom-fields.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_custom_fields_fallback();
        }
    }
    
    /**
     * Render advanced tab
     */
    public function render_advanced_tab() {
        $template_file = WCFM_PLUGIN_PATH . 'admin/templates/advanced-settings.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_advanced_fallback();
        }
    }
    
    /**
     * Fallback render for rules tab
     */
    private function render_rules_fallback($settings) {
        $rules = isset($settings['product_type_rules']) ? $settings['product_type_rules'] : array();
        ?>
        <div class="wcfm-rules-manager">
            <h2><i class="fas fa-cogs"></i> <?php _e('Product Type Rules', WCFM_TEXT_DOMAIN); ?></h2>
            <p><?php _e('Configure automatic field behavior based on product types in the cart.', WCFM_TEXT_DOMAIN); ?></p>
            
            <h3><i class="fas fa-cloud"></i> <?php _e('Virtual Products', WCFM_TEXT_DOMAIN); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Hide Shipping Fields', WCFM_TEXT_DOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="product_type_rules[virtual_products][hide_shipping]" value="1" 
                                <?php checked(isset($rules['virtual_products']['hide_shipping']) ? $rules['virtual_products']['hide_shipping'] : false); ?> />
                            <?php _e('Hide all shipping fields when cart contains only virtual products', WCFM_TEXT_DOMAIN); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <h3><i class="fas fa-download"></i> <?php _e('Downloadable Products', WCFM_TEXT_DOMAIN); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Hide Shipping Fields', WCFM_TEXT_DOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="product_type_rules[downloadable_products][hide_shipping]" value="1" 
                                <?php checked(isset($rules['downloadable_products']['hide_shipping']) ? $rules['downloadable_products']['hide_shipping'] : false); ?> />
                            <?php _e('Hide all shipping fields when cart contains only downloadable products', WCFM_TEXT_DOMAIN); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Fallback render for custom fields tab
     */
    private function render_custom_fields_fallback() {
        ?>
        <div class="wcfm-custom-fields-manager">
            <h2><i class="fas fa-edit"></i> <?php _e('Custom Fields Management', WCFM_TEXT_DOMAIN); ?></h2>
            <p><?php _e('Create, edit, and manage custom checkout fields.', WCFM_TEXT_DOMAIN); ?></p>
            
            <button type="button" id="wcfm-add-custom-field" class="button button-primary">
                <i class="fas fa-plus"></i> <?php _e('Add Custom Field', WCFM_TEXT_DOMAIN); ?>
            </button>
            
            <div id="wcfm-custom-fields-list">
                <p><?php _e('Loading custom fields...', WCFM_TEXT_DOMAIN); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Fallback render for advanced tab
     */
    private function render_advanced_fallback() {
        ?>
        <div class="wcfm-advanced-settings">
            <h2><i class="fas fa-tools"></i> <?php _e('Advanced Settings', WCFM_TEXT_DOMAIN); ?></h2>
            
            <h3><?php _e('Database Tools', WCFM_TEXT_DOMAIN); ?></h3>
            <button type="button" id="wcfm-repair-database" class="button">
                <i class="fas fa-wrench"></i> <?php _e('Repair Database Tables', WCFM_TEXT_DOMAIN); ?>
            </button>
            
            <h3><?php _e('System Information', WCFM_TEXT_DOMAIN); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php _e('Plugin Version:', WCFM_TEXT_DOMAIN); ?></th>
                    <td><?php echo WCFM_VERSION; ?></td>
                </tr>
                <tr>
                    <th><?php _e('WordPress Version:', WCFM_TEXT_DOMAIN); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render fields table
     */
    private function render_fields_table($section, $default_fields, $current_fields, $default_labels) {
        ?>
        <div class="wcfm-fields-manager">
            <div class="wcfm-section-header">
                <h2>
                    <i class="<?php echo esc_attr($this->tabs[$section]['icon']); ?>"></i>
                    <?php echo esc_html($this->tabs[$section]['title']) . ' ' . __('Configuration', WCFM_TEXT_DOMAIN); ?>
                </h2>
                <p><?php printf(__('Configure which %s fields to show, hide, or make required during checkout.', WCFM_TEXT_DOMAIN), strtolower($this->tabs[$section]['title'])); ?></p>
                
                <div class="wcfm-section-actions">
                    <button type="button" class="button wcfm-bulk-enable" data-section="<?php echo esc_attr($section); ?>">
                        <i class="fas fa-eye"></i> <?php _e('Enable All', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-bulk-disable" data-section="<?php echo esc_attr($section); ?>">
                        <i class="fas fa-eye-slash"></i> <?php _e('Disable All', WCFM_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="button wcfm-reset-defaults" data-section="<?php echo esc_attr($section); ?>">
                        <i class="fas fa-undo"></i> <?php _e('Reset to Defaults', WCFM_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <?php $this->render_fields_search(); ?>
            
            <div class="wcfm-fields-table-wrapper">
                <table class="wcfm-fields-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="wcfm-field-sort" width="40">
                                <i class="fas fa-grip-vertical" title="<?php _e('Drag to reorder', WCFM_TEXT_DOMAIN); ?>"></i>
                            </th>
                            <th class="wcfm-field-name" width="200"><?php _e('Field Key', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-label"><?php _e('Display Label', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-enabled" width="100"><?php _e('Enabled', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-required" width="100"><?php _e('Required', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-priority" width="80"><?php _e('Priority', WCFM_TEXT_DOMAIN); ?></th>
                            <th class="wcfm-field-actions" width="120"><?php _e('Actions', WCFM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wcfm-<?php echo esc_attr($section); ?>-fields" class="wcfm-sortable">
                        <?php
                        foreach ($default_fields as $field_key => $default_config) {
                            $field_config = isset($current_fields[$field_key]) ? array_merge($default_config, $current_fields[$field_key]) : $default_config;
                            $field_label = isset($default_labels[$field_key]) ? $default_labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
                            
                            $this->render_field_row($section, $field_key, $field_label, $field_config);
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php $this->render_field_statistics($section, $default_fields, $current_fields); ?>
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
                <i class="fas fa-grip-vertical wcfm-drag-handle" title="<?php _e('Drag to reorder', WCFM_TEXT_DOMAIN); ?>"></i>
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
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="button button-small wcfm-field-reset" data-field="<?php echo esc_attr($field_key); ?>" title="<?php _e('Reset to Default', WCFM_TEXT_DOMAIN); ?>">
                    <i class="fas fa-undo"></i>
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
            <i class="fas fa-search wcfm-search-icon"></i>
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
}