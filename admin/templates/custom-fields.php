<?php
/**
 * Custom Fields Template
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
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