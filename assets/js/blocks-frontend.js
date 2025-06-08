/**
 * WooCommerce Blocks Frontend Integration for WCFM
 * This file handles the frontend behavior of WooCommerce Block Checkout
 */

(function() {
    'use strict';

    // Check if we're in a block checkout environment
    if (typeof wp === 'undefined' || !wp.hooks) {
        return;
    }

    const { addFilter, addAction, doAction } = wp.hooks;
    const { __ } = wp.i18n;

    /**
     * WCFM Frontend Manager for Blocks
     */
    const WCFMBlocksFrontend = {

        /**
         * Initialize the frontend manager
         */
        init: function() {
            this.bindEvents();
            this.setupFieldValidation();
            this.handleFieldVisibility();
            this.applyProductTypeRules();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Listen for checkout updates
            document.addEventListener('wc-blocks_checkout_updated', this.onCheckoutUpdated.bind(this));
            
            // Listen for field changes
            document.addEventListener('input', this.onFieldChange.bind(this));
            document.addEventListener('change', this.onFieldChange.bind(this));
            
            // Listen for validation events
            document.addEventListener('wc-blocks_checkout_validation', this.onValidation.bind(this));
        },

        /**
         * Handle checkout updates
         */
        onCheckoutUpdated: function(event) {
            // Re-apply field enhancements after checkout update
            setTimeout(() => {
                this.handleFieldVisibility();
                this.applyFieldEnhancements();
            }, 100);
        },

        /**
         * Handle field changes
         */
        onFieldChange: function(event) {
            const field = event.target;
            
            if (!field.name || !this.isWCFMField(field.name)) {
                return;
            }

            // Real-time validation
            this.validateField(field);
            
            // Handle conditional logic
            this.handleConditionalLogic(field);
            
            // Trigger custom event
            doAction('wcfm_blocks_field_changed', field, event);
        },

        /**
         * Handle validation
         */
        onValidation: function(event) {
            this.validateAllFields();
        },

        /**
         * Setup field validation
         */
        setupFieldValidation: function() {
            // Add validation for WCFM fields
            addFilter(
                'woocommerce_blocks_checkout_field_error_message',
                'wcfm/field-validation',
                this.validateFieldMessage.bind(this)
            );
        },

        /**
         * Validate field message
         */
        validateFieldMessage: function(message, fieldName, fieldValue, fieldConfig) {
            if (!this.getFieldSettings()) {
                return message;
            }

            const fieldSettings = this.getFieldSettings()[fieldName];
            
            if (!fieldSettings) {
                return message;
            }

            // Required field validation
            if (fieldSettings.required && (!fieldValue || fieldValue.trim() === '')) {
                return this.getStrings().required_field_error;
            }

            // Email validation
            if (fieldName.includes('email') && fieldValue && !this.isValidEmail(fieldValue)) {
                return this.getStrings().invalid_email;
            }

            // Phone validation
            if (fieldName.includes('phone') && fieldValue && !this.isValidPhone(fieldValue)) {
                return this.getStrings().invalid_phone;
            }

            return message;
        },

        /**
         * Validate individual field
         */
        validateField: function(field) {
            const fieldName = field.name;
            const fieldValue = field.value;
            const fieldSettings = this.getFieldSettings();

            if (!fieldSettings || !fieldSettings[fieldName]) {
                return true;
            }

            const config = fieldSettings[fieldName];
            const wrapper = field.closest('.wc-block-components-text-input, .wc-block-components-form-row');

            // Clear previous errors
            this.clearFieldError(wrapper);

            // Required validation
            if (config.required && (!fieldValue || fieldValue.trim() === '')) {
                this.showFieldError(wrapper, this.getStrings().required_field_error);
                return false;
            }

            // Skip further validation if field is empty and not required
            if (!fieldValue || fieldValue.trim() === '') {
                this.markFieldAsValid(wrapper);
                return true;
            }

            // Type-specific validation
            let isValid = true;
            let errorMessage = '';

            if (fieldName.includes('email') && !this.isValidEmail(fieldValue)) {
                isValid = false;
                errorMessage = this.getStrings().invalid_email;
            } else if (fieldName.includes('phone') && !this.isValidPhone(fieldValue)) {
                isValid = false;
                errorMessage = this.getStrings().invalid_phone;
            }

            if (!isValid) {
                this.showFieldError(wrapper, errorMessage);
                return false;
            }

            this.markFieldAsValid(wrapper);
            return true;
        },

        /**
         * Validate all fields
         */
        validateAllFields: function() {
            const fieldSettings = this.getFieldSettings();
            let isValid = true;

            if (!fieldSettings) {
                return true;
            }

            Object.keys(fieldSettings).forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field && field.offsetParent !== null) { // Check if field is visible
                    if (!this.validateField(field)) {
                        isValid = false;
                    }
                }
            });

            return isValid;
        },

        /**
         * Handle field visibility
         */
        handleFieldVisibility: function() {
            const fieldSettings = this.getFieldSettings();

            if (!fieldSettings) {
                return;
            }

            Object.keys(fieldSettings).forEach(fieldName => {
                const config = fieldSettings[fieldName];
                const field = document.querySelector(`[name="${fieldName}"]`);
                
                if (field) {
                    const wrapper = field.closest('.wc-block-components-text-input, .wc-block-components-form-row');
                    
                    if (wrapper) {
                        if (!config.enabled) {
                            wrapper.style.display = 'none';
                            wrapper.classList.add('wcfm-field-hidden');
                        } else {
                            wrapper.style.display = '';
                            wrapper.classList.remove('wcfm-field-hidden');
                            wrapper.classList.add('wcfm-field-enabled');
                        }

                        // Add required indicator
                        if (config.required && config.enabled) {
                            wrapper.classList.add('wcfm-field-required');
                            field.setAttribute('required', 'required');
                            field.setAttribute('aria-required', 'true');
                            
                            const label = wrapper.querySelector('label');
                            if (label && !label.querySelector('.required')) {
                                label.innerHTML += ' <span class="required">*</span>';
                            }
                        }
                    }
                }
            });
        },

        /**
         * Apply field enhancements
         */
        applyFieldEnhancements: function() {
            const fieldSettings = this.getFieldSettings();

            if (!fieldSettings) {
                return;
            }

            Object.keys(fieldSettings).forEach(fieldName => {
                const config = fieldSettings[fieldName];
                const field = document.querySelector(`[name="${fieldName}"]`);
                
                if (field && config.enabled) {
                    // Add WCFM classes
                    field.classList.add('wcfm-field');
                    field.classList.add(`wcfm-${fieldName.replace(/_/g, '-')}`);

                    // Apply custom label
                    if (config.label) {
                        const label = document.querySelector(`label[for="${fieldName}"]`);
                        if (label) {
                            const requiredSpan = label.querySelector('.required');
                            label.textContent = config.label;
                            if (requiredSpan) {
                                label.appendChild(requiredSpan);
                            }
                        }
                    }

                    // Set field type attributes
                    if (fieldName.includes('email')) {
                        field.setAttribute('type', 'email');
                    } else if (fieldName.includes('phone')) {
                        field.setAttribute('type', 'tel');
                    }
                }
            });
        },

        /**
         * Apply product type rules
         */
        applyProductTypeRules: function() {
            // This would be called when cart contents change
            // For now, we'll handle it server-side
        },

        /**
         * Handle conditional logic
         */
        handleConditionalLogic: function(triggerField) {
            // Implement conditional logic based on field values
            // This is a placeholder for future conditional logic implementation
        },

        /**
         * Show field error
         */
        showFieldError: function(wrapper, message) {
            if (!wrapper) return;

            // Remove existing error
            this.clearFieldError(wrapper);

            // Add error class
            wrapper.classList.add('wcfm-field-invalid');

            // Add error message
            const errorElement = document.createElement('div');
            errorElement.className = 'wcfm-field-error wc-block-components-validation-error';
            errorElement.textContent = message;
            
            wrapper.appendChild(errorElement);
        },

        /**
         * Clear field error
         */
        clearFieldError: function(wrapper) {
            if (!wrapper) return;

            wrapper.classList.remove('wcfm-field-invalid');
            const errorElement = wrapper.querySelector('.wcfm-field-error');
            if (errorElement) {
                errorElement.remove();
            }
        },

        /**
         * Mark field as valid
         */
        markFieldAsValid: function(wrapper) {
            if (!wrapper) return;

            this.clearFieldError(wrapper);
            wrapper.classList.add('wcfm-field-valid');
        },

        /**
         * Check if field is WCFM managed
         */
        isWCFMField: function(fieldName) {
            const fieldSettings = this.getFieldSettings();
            return fieldSettings && fieldSettings.hasOwnProperty(fieldName);
        },

        /**
         * Email validation
         */
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Phone validation
         */
        isValidPhone: function(phone) {
            const cleanedPhone = phone.replace(/[^0-9+\-\(\)\s]/g, '');
            return cleanedPhone.length >= 10;
        },

        /**
         * Get field settings
         */
        getFieldSettings: function() {
            return (typeof wcfmBlocksSettings !== 'undefined') ? 
                wcfmBlocksSettings.fieldSettings : null;
        },

        /**
         * Get localized strings
         */
        getStrings: function() {
            return (typeof wcfmBlocksSettings !== 'undefined') ? 
                wcfmBlocksSettings.strings : {
                    required_field_error: 'This field is required.',
                    invalid_email: 'Please enter a valid email address.',
                    invalid_phone: 'Please enter a valid phone number.'
                };
        }
    };

    /**
     * Initialize when DOM is ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                WCFMBlocksFrontend.init();
            });
        } else {
            WCFMBlocksFrontend.init();
        }

        // Also initialize when blocks are loaded
        addAction('woocommerce_blocks_checkout_loaded', 'wcfm/init-frontend', function() {
            WCFMBlocksFrontend.init();
        });
    }

    // Start initialization
    init();

    // Make available globally for debugging
    window.WCFMBlocksFrontend = WCFMBlocksFrontend;

})();