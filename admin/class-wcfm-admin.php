<?php
/**
 * Main Admin functionality class
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WCFM Admin Class - Main Admin Controller
 */
class WCFM_Admin {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Admin components
     */
    private $menu_handler;
    private $ajax_handler;
    private $tabs_handler;
    
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
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Load admin components
        $this->load_admin_components();
        
        // Initialize components
        $this->init_admin_components();
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(WCFM_PLUGIN_PATH . 'woocommerce-checkout-fields-manager.php'), array($this, 'add_settings_link'));
    }
    
    /**
     * Load admin components
     */
    private function load_admin_components() {
        require_once WCFM_PLUGIN_PATH . 'admin/class-wcfm-admin-menu.php';
        require_once WCFM_PLUGIN_PATH . 'admin/class-wcfm-admin-ajax.php';
        require_once WCFM_PLUGIN_PATH . 'admin/class-wcfm-admin-tabs.php';
        require_once WCFM_PLUGIN_PATH . 'admin/class-wcfm-admin-notices.php';
    }
    
    /**
     * Initialize admin components
     */
    private function init_admin_components() {
        $this->menu_handler = WCFM_Admin_Menu::get_instance();
        $this->ajax_handler = WCFM_Admin_Ajax::get_instance();
        $this->tabs_handler = WCFM_Admin_Tabs::get_instance();
        
        // Initialize notices
        WCFM_Admin_Notices::get_instance();
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
        ?>
        <div class="wrap wcfm-admin-wrap">
            <?php $this->render_admin_header(); ?>
            
            <?php $this->tabs_handler->render_navigation($active_tab); ?>
            
            <div class="wcfm-tab-content">
                <?php $this->tabs_handler->render_tab_content($active_tab); ?>
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
        // Include modal templates
        $modal_file = WCFM_PLUGIN_PATH . 'admin/templates/custom-field-modal.php';
        
        if (file_exists($modal_file)) {
            include $modal_file;
        } else {
            // Fallback modal rendering
            ?>
            <div id="wcfm-custom-field-modal" class="wcfm-modal" style="display: none;">
                <div class="wcfm-modal-content">
                    <div class="wcfm-modal-header">
                        <h3><?php _e('Add/Edit Custom Field', WCFM_TEXT_DOMAIN); ?></h3>
                        <button type="button" class="wcfm-modal-close">×</button>
                    </div>
                    <div class="wcfm-modal-body">
                        <form id="wcfm-custom-field-form">
                            <p><?php _e('Custom field form will be loaded here.', WCFM_TEXT_DOMAIN); ?></p>
                        </form>
                    </div>
                    <div class="wcfm-modal-footer">
                        <button type="button" id="wcfm-save-custom-field" class="button button-primary">
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
     * Initialize admin
     */
    public static function init_admin() {
        return self::get_instance();
    }
}