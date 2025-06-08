<?php
/**
 * Product Rules Template
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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