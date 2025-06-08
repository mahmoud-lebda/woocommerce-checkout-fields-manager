/**
 * WooCommerce Blocks Integration for WCFM
 * This file handles the integration with WooCommerce Block Checkout
 */

(function() {
    'use strict';

    // Check if we're in a block checkout environment
    if (typeof wp === 'undefined' || !wp.hooks || typeof wcfmBlocksSettings === 'undefined') {
        return;
    }

    const { addFilter, addAction } = wp.hooks;
    const { __ } = wp.i18n;

    /**
     * Modify checkout fields for blocks
     */
    addFilter(
        'woocommerce_blocks_checkout_fields',
        'wcfm/modify-checkout-fields',
        function(fields) {
            if (!wcfmBlocksSettings.fieldSettings) {
                return fields;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings;

            // Process each field setting
            Object.keys(fieldSettings).forEach(fieldKey => {
                const fieldConfig = fieldSettings[fieldKey];
                const section = fieldConfig.section || 'billing';
                
                // Skip if field is disabled
                if (!fieldConfig.enabled) {
                    if (fields[section] && fields[section][fieldKey]) {
                        delete fields[section][fieldKey];
                    }
                    return;
                }

                // Ensure section exists
                if (!fields[section]) {
                    fields[section] = {};
                }

                // Create or modify the field
                if (!fields[section][fieldKey]) {
                    fields[section][fieldKey] = {};
                }

                // Apply field configuration
                if (fieldConfig.required !== undefined) {
                    fields[section][fieldKey].required = fieldConfig.required;
                }

                if (fieldConfig.label) {
                    fields[section][fieldKey].label = fieldConfig.label;
                }

                if (fieldConfig.priority !== undefined) {
                    fields[section][fieldKey].priority = fieldConfig.priority;
                }

                // Add WCFM specific classes
                if (!fields[section][fieldKey].class) {
                    fields[section][fieldKey].class = [];
                }
                fields[section][fieldKey].class.push('wcfm-field');
                fields[section][fieldKey].class.push('wcfm-' + section + '-field');
            });

            return fields;
        }
    );

    /**
     * Add field validation for block checkout
     */
    addFilter(
        'woocommerce_blocks_checkout_field_validation',
        'wcfm/validate-checkout-fields',
        function(result, fieldKey, fieldValue, fieldConfig) {
            if (!wcfmBlocksSettings.fieldSettings) {
                return result;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings[fieldKey];
            
            if (!fieldSettings) {
                return result;
            }

            // Required field validation
            if (fieldSettings.required && (!fieldValue || fieldValue.trim() === '')) {
                return {
                    isValid: false,
                    message: wcfmBlocksSettings.strings.required_field_error
                };
            }

            // Email validation
            if (fieldKey.includes('email') && fieldValue && !isValidEmail(fieldValue)) {
                return {
                    isValid: false,
                    message: wcfmBlocksSettings.strings.invalid_email
                };
            }

            // Phone validation
            if (fieldKey.includes('phone') && fieldValue && !isValidPhone(fieldValue)) {
                return {
                    isValid: false,
                    message: wcfmBlocksSettings.strings.invalid_phone
                };
            }

            return result;
        }
    );

    /**
     * Handle field visibility based on settings
     */
    addAction(
        'woocommerce_blocks_checkout_form_rendered',
        'wcfm/handle-field-visibility',
        function() {
            if (!wcfmBlocksSettings.fieldSettings) {
                return;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings;

            // Apply field visibility settings
            Object.keys(fieldSettings).forEach(fieldKey => {
                const fieldConfig = fieldSettings[fieldKey];
                const fieldElement = document.querySelector(`[name="${fieldKey}"]`);
                
                if (fieldElement) {
                    const fieldWrapper = fieldElement.closest('.wc-block-components-text-input') || 
                                        fieldElement.closest('.wc-block-components-form-row');
                    
                    if (fieldWrapper) {
                        if (!fieldConfig.enabled) {
                            fieldWrapper.style.display = 'none';
                            fieldWrapper.classList.add('wcfm-field-hidden');
                        } else {
                            fieldWrapper.style.display = '';
                            fieldWrapper.classList.remove('wcfm-field-hidden');
                            fieldWrapper.classList.add('wcfm-field-enabled');
                        }

                        // Add required indicator
                        if (fieldConfig.required) {
                            fieldWrapper.classList.add('wcfm-field-required');
                            const label = fieldWrapper.querySelector('label');
                            if (label && !label.querySelector('.required')) {
                                label.innerHTML += ' <span class="required">*</span>';
                            }
                        }
                    }
                }
            });
        }
    );

    /**
     * Handle product type rules for block checkout
     */
    addAction(
        'woocommerce_blocks_checkout_cart_changed',
        'wcfm/handle-product-type-rules',
        function(cartData) {
            // This would handle cart changes and apply product type rules
            // For now, we'll keep it simple and rely on server-side handling
        }
    );

    /**
     * Extend the checkout data with our custom fields
     */
    addFilter(
        'woocommerce_blocks_checkout_submit_data',
        'wcfm/extend-checkout-data',
        function(checkoutData) {
            if (!wcfmBlocksSettings.fieldSettings) {
                return checkoutData;
            }

            // Initialize extensions if not exists
            if (!checkoutData.extensions) {
                checkoutData.extensions = {};
            }

            if (!checkoutData.extensions.wcfm) {
                checkoutData.extensions.wcfm = {};
            }

            // Collect additional field values
            const fieldSettings = wcfmBlocksSettings.fieldSettings;
            
            Object.keys(fieldSettings).forEach(fieldKey => {
                const fieldConfig = fieldSettings[fieldKey];
                
                if (fieldConfig.enabled && fieldConfig.section === 'additional') {
                    const fieldElement = document.querySelector(`[name="${fieldKey}"]`);
                    
                    if (fieldElement && fieldElement.value) {
                        checkoutData.extensions.wcfm[fieldKey] = fieldElement.value;
                    }
                }
            });

            return checkoutData;
        }
    );

    /**
     * Initialize field enhancements after blocks are loaded
     */
    addAction(
        'woocommerce_blocks_checkout_loaded',
        'wcfm/initialize-field-enhancements',
        function() {
            // Add any additional field enhancements here
            initFieldEnhancements();
        }
    );

    /**
     * Initialize field enhancements
     */
    function initFieldEnhancements() {
        // Add custom CSS classes to the checkout form
        const checkoutForm = document.querySelector('.wc-block-checkout__form');
        if (checkoutForm) {
            checkoutForm.classList.add('wcfm-block-checkout');
        }

        // Apply field-specific enhancements
        setTimeout(() => {
            applyFieldEnhancements();
        }, 500);
    }

    /**
     * Apply field enhancements
     */
    function applyFieldEnhancements() {
        if (!wcfmBlocksSettings.fieldSettings) {
            return;
        }

        const fieldSettings = wcfmBlocksSettings.fieldSettings;

        Object.keys(fieldSettings).forEach(fieldKey => {
            const fieldConfig = fieldSettings[fieldKey];
            const fieldElement = document.querySelector(`[name="${fieldKey}"]`);
            
            if (fieldElement && fieldConfig.enabled) {
                // Add field-specific classes
                fieldElement.classList.add('wcfm-field');
                fieldElement.classList.add(`wcfm-${fieldKey}`);

                // Apply required state
                if (fieldConfig.required) {
                    fieldElement.setAttribute('required', 'required');
                    fieldElement.setAttribute('aria-required', 'true');
                }

                // Add validation attributes
                if (fieldKey.includes('email')) {
                    fieldElement.setAttribute('type', 'email');
                }

                if (fieldKey.includes('phone')) {
                    fieldElement.setAttribute('type', 'tel');
                }

                // Apply custom label if provided
                if (fieldConfig.label) {
                    const label = document.querySelector(`label[for="${fieldKey}"]`);
                    if (label) {
                        label.textContent = fieldConfig.label;
                        if (fieldConfig.required) {
                            label.innerHTML += ' <span class="required">*</span>';
                        }
                    }
                }
            }
        });
    }

    /**
     * Email validation helper
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Phone validation helper
     */
    function isValidPhone(phone) {
        const cleanedPhone = phone.replace(/[^0-9+\-\(\)\s]/g, '');
        return cleanedPhone.length >= 10;
    }

    /**
     * Observe DOM changes for dynamic field updates
     */
    function observeFieldChanges() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Re-apply enhancements to new fields
                    applyFieldEnhancements();
                }
            });
        });

        const checkoutContainer = document.querySelector('.wc-block-checkout');
        if (checkoutContainer) {
            observer.observe(checkoutContainer, {
                childList: true,
                subtree: true
            });
        }
    }

    /**
     * Initialize when DOM is ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initFieldEnhancements();
                observeFieldChanges();
            });
        } else {
            initFieldEnhancements();
            observeFieldChanges();
        }
    }

    // Initialize the integration
    init();

})();