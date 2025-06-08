<?php
/**
 * Advanced Settings Template
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display database status
 */
function wcfm_display_database_status() {
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
                <?php wcfm_display_database_status(); ?>
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