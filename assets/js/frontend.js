/**
 * WooCommerce Checkout Fields Manager - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Main frontend object
    var WCFM_Frontend = {
        init: function() {
            this.bindEvents();
            this.initValidation();
            this.handleConditionalLogic();
            this.handleProductTypeRules();
            this.initTooltips();
            this.handleFieldDependencies();
        },

        bindEvents: function() {
            // Field change events
            $(document).on('change blur', '.wcfm-custom-field input, .wcfm-custom-field textarea, .wcfm-custom-field select', this.validateField);
            
            // Form submission
            $('form.checkout').on('checkout_place_order', this.validateAllFields);
            
            // Real-time validation
            $(document).on('input', '.wcfm-custom-field input[type="text"], .wcfm-custom-field input[type="email"], .wcfm-custom-field textarea', 
                this.debounce(this.validateField, 500));
            
            // Product type changes (for conditional logic)
            $(document).on('change', 'input[name="shipping_method[0]"]', this.handleShippingMethodChange);
            
            // Country/state changes
            $(document).on('change', '#billing_country, #shipping_country', this.handleCountryChange);
            
            // Payment method changes
            $(document).on('change', 'input[name="payment_method"]', this.handlePaymentMethodChange);
            
            // Block checkout compatibility
            if (wcfm_frontend.is_block_checkout) {
                this.initBlockCheckoutCompatibility();
            }
        },

        initValidation: function() {
            // Initialize field validation based on settings
            var fieldSettings = wcfm_frontend.field_settings;
            
            for (var fieldKey in fieldSettings) {
                var $field = $('[name="' + fieldKey + '"]');
                var settings = fieldSettings[fieldKey];
                
                if (settings.required) {
                    $field.attr('required', true);
                    this.markFieldAsRequired($field);
                }
                
                // Add validation attributes
                if (settings.validation) {
                    this.applyValidationRules($field, settings.validation);
                }
            }
        },

        validateField: function(event) {
            var $field = $(event.target);
            var fieldKey = $field.attr('name');
            var fieldValue = $field.val();
            var fieldSettings = wcfm_frontend.field_settings[fieldKey];
            
            if (!fieldSettings) return true;
            
            // Clear previous errors
            WCFM_Frontend.clearFieldError($field);
            
            // Required validation
            if (fieldSettings.required && (!fieldValue || fieldValue.trim() === '')) {
                WCFM_Frontend.showFieldError($field, wcfm_frontend.strings.required_field_error);
                return false;
            }
            
            // Skip further validation if field is empty and not required
            if (!fieldValue || fieldValue.trim() === '') {
                WCFM_Frontend.markFieldAsValid($field);
                return true;
            }
            
            // Type-specific validation
            var fieldType = fieldSettings.type || $field.attr('type') || 'text';
            var isValid = true;
            
            switch (fieldType) {
                case 'email':
                    isValid = WCFM_Frontend.validateEmail(fieldValue);
                    if (!isValid) {
                        WCFM_Frontend.showFieldError($field, wcfm_frontend.strings.invalid_email);
                    }
                    break;
                    
                case 'tel':
                    isValid = WCFM_Frontend.validatePhone(fieldValue);
                    if (!isValid) {
                        WCFM_Frontend.showFieldError($field, wcfm_frontend.strings.invalid_phone);
                    }
                    break;
                    
                case 'number':
                    isValid = !isNaN(fieldValue) && isFinite(fieldValue);
                    if (!isValid) {
                        WCFM_Frontend.showFieldError($field, 'Please enter a valid number.');
                    }
                    break;
                    
                case 'date':
                    isValid = WCFM_Frontend.validateDate(fieldValue);
                    if (!isValid) {
                        WCFM_Frontend.showFieldError($field, 'Please enter a valid date.');
                    }
                    break;
            }
            
            // Custom validation rules
            if (isValid && fieldSettings.validation) {
                isValid = WCFM_Frontend.validateCustomRules($field, fieldValue, fieldSettings.validation);
            }
            
            if (isValid) {
                WCFM_Frontend.markFieldAsValid($field);
            }
            
            return isValid;
        },

        validateAllFields: function(event) {
            var isValid = true;
            var fieldSettings = wcfm_frontend.field_settings;
            
            for (var fieldKey in fieldSettings) {
                var $field = $('[name="' + fieldKey + '"]');
                if ($field.length && $field.is(':visible')) {
                    var fieldValid = WCFM_Frontend.validateField({target: $field[0]});
                    if (!fieldValid) {
                        isValid = false;
                    }
                }
            }
            
            if (!isValid) {
                // Scroll to first error
                var $firstError = $('.wcfm-field-invalid').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 500);
                }
                
                // Focus first error field
                $firstError.find('input, textarea, select').first().focus();
            }
            
            return isValid;
        },

        validateEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        validatePhone: function(phone) {
            // Basic phone validation - can be enhanced based on requirements
            var cleaned = phone.replace(/[^0-9+\-\(\)\s]/g, '');
            return cleaned.length >= 10;
        },

        validateDate: function(date) {
            if (!date) return false;
            var parsedDate = new Date(date);
            return !isNaN(parsedDate.getTime());
        },

        validateCustomRules: function($field, value, rules) {
            for (var rule in rules) {
                var ruleValue = rules[rule];
                
                switch (rule) {
                    case 'minlength':
                        if (value.length < parseInt(ruleValue)) {
                            this.showFieldError($field, wcfm_frontend.strings.field_too_short.replace('%d', ruleValue));
                            return false;
                        }
                        break;
                        
                    case 'maxlength':
                        if (value.length > parseInt(ruleValue)) {
                            this.showFieldError($field, wcfm_frontend.strings.field_too_long.replace('%d', ruleValue));
                            return false;
                        }
                        break;
                        
                    case 'pattern':
                        var regex = new RegExp(ruleValue);
                        if (!regex.test(value)) {
                            this.showFieldError($field, 'Field format is not valid.');
                            return false;
                        }
                        break;
                        
                    case 'min':
                        if (parseFloat(value) < parseFloat(ruleValue)) {
                            this.showFieldError($field, 'Value must be at least ' + ruleValue + '.');
                            return false;
                        }
                        break;
                        
                    case 'max':
                        if (parseFloat(value) > parseFloat(ruleValue)) {
                            this.showFieldError($field, 'Value must be no more than ' + ruleValue + '.');
                            return false;
                        }
                        break;
                }
            }
            
            return true;
        },

        applyValidationRules: function($field, rules) {
            for (var rule in rules) {
                var value = rules[rule];
                
                switch (rule) {
                    case 'minlength':
                        $field.attr('minlength', value);
                        break;
                    case 'maxlength':
                        $field.attr('maxlength', value);
                        break;
                    case 'pattern':
                        $field.attr('pattern', value);
                        break;
                    case 'min':
                        $field.attr('min', value);
                        break;
                    case 'max':
                        $field.attr('max', value);
                        break;
                    case 'step':
                        $field.attr('step', value);
                        break;
                }
            }
        },

        showFieldError: function($field, message) {
            var $wrapper = $field.closest('.wcfm-custom-field, .form-row');
            
            // Remove existing error
            $wrapper.find('.wcfm-field-error').remove();
            $wrapper.removeClass('wcfm-field-valid').addClass('wcfm-field-invalid');
            
            // Add new error
            $field.after('<span class="wcfm-field-error">' + message + '</span>');
            
            // Add error styling
            $field.addClass('error');
        },

        clearFieldError: function($field) {
            var $wrapper = $field.closest('.wcfm-custom-field, .form-row');
            
            $wrapper.find('.wcfm-field-error').remove();
            $wrapper.removeClass('wcfm-field-invalid');
            $field.removeClass('error');
        },

        markFieldAsValid: function($field) {
            var $wrapper = $field.closest('.wcfm-custom-field, .form-row');
            
            this.clearFieldError($field);
            $wrapper.addClass('wcfm-field-valid');
        },

        markFieldAsRequired: function($field) {
            var $label = $field.closest('.wcfm-custom-field, .form-row').find('label');
            
            if ($label.find('.required').length === 0) {
                $label.append(' <abbr class="required" title="required">*</abbr>');
            }
        },

        handleConditionalLogic: function() {
            if (typeof wcfm_conditional_rules === 'undefined') {
                return;
            }
            
            var conditionalRules = wcfm_conditional_rules;
            
            // Bind change events to trigger fields
            for (var targetField in conditionalRules) {
                var rules = conditionalRules[targetField];
                
                rules.forEach(function(rule) {
                    $(document).on('change', '[name="' + rule.field + '"]', function() {
                        WCFM_Frontend.evaluateConditionalRules();
                    });
                });
            }
            
            // Initial evaluation
            this.evaluateConditionalRules();
        },

        evaluateConditionalRules: function() {
            if (typeof wcfm_conditional_rules === 'undefined') {
                return;
            }
            
            var conditionalRules = wcfm_conditional_rules;
            
            for (var targetField in conditionalRules) {
                var $targetField = $('[name="' + targetField + '"]');
                var $targetWrapper = $targetField.closest('.wcfm-custom-field, .form-row');
                var rules = conditionalRules[targetField];
                var shouldShow = false;
                var shouldRequire = false;
                
                rules.forEach(function(rule) {
                    var $triggerField = $('[name="' + rule.field + '"]');
                    var triggerValue = $triggerField.val();
                    
                    var conditionMet = WCFM_Frontend.evaluateCondition(rule, triggerValue);
                    
                    if (conditionMet) {
                        switch (rule.action) {
                            case 'show':
                                shouldShow = true;
                                break;
                            case 'hide':
                                shouldShow = false;
                                break;
                            case 'require':
                                shouldRequire = true;
                                break;
                            case 'unrequire':
                                shouldRequire = false;
                                break;
                        }
                    }
                });
                
                // Apply visibility
                if (shouldShow) {
                    $targetWrapper.removeClass('wcfm-field-hidden').show();
                } else {
                    $targetWrapper.addClass('wcfm-field-hidden').hide();
                }
                
                // Apply required state
                if (shouldRequire) {
                    $targetField.attr('required', true);
                    this.markFieldAsRequired($targetField);
                } else {
                    $targetField.removeAttr('required');
                    $targetWrapper.find('.required').remove();
                }
            }
        },

        evaluateCondition: function(rule, value) {
            switch (rule.operator) {
                case 'equals':
                    return value === rule.value;
                case 'not_equals':
                    return value !== rule.value;
                case 'contains':
                    return value.indexOf(rule.value) !== -1;
                case 'not_contains':
                    return value.indexOf(rule.value) === -1;
                case 'greater_than':
                    return parseFloat(value) > parseFloat(rule.value);
                case 'less_than':
                    return parseFloat(value) < parseFloat(rule.value);
                case 'is_empty':
                    return !value || value.trim() === '';
                case 'is_not_empty':
                    return value && value.trim() !== '';
                default:
                    return false;
            }
        },

        handleProductTypeRules: function() {
            // This function handles field visibility based on cart contents
            // It would typically be triggered by AJAX updates to cart contents
            
            if (typeof wcfm_dependencies === 'undefined') {
                return;
            }
            
            var dependencies = wcfm_dependencies;
            
            if (dependencies.hide_shipping) {
                $('.shipping_address').hide();
                $('#ship-to-different-address-checkbox').prop('checked', false).closest('.form-row').hide();
            }
            
            if (dependencies.hide_physical_address) {
                $('.billing_address_1, .billing_address_2, .billing_city, .billing_postcode, .billing_country, .billing_state').closest('.form-row').hide();
            }
        },

        handleShippingMethodChange: function() {
            // Handle shipping method changes
            var selectedShippingMethod = $('input[name="shipping_method[0]"]:checked').val();
            
            // You can add logic here to show/hide fields based on shipping method
            // For example, show pickup location field for local pickup
            
            if (selectedShippingMethod && selectedShippingMethod.includes('local_pickup')) {
                $('.wcfm-pickup-location').show();
            } else {
                $('.wcfm-pickup-location').hide();
            }
        },

        handleCountryChange: function() {
            var $country = $(this);
            var country = $country.val();
            var fieldPrefix = $country.attr('id').replace('_country', '');
            
            // You can add logic here to show/hide fields based on country
            // For example, show/hide state field, postal code format validation, etc.
            
            if (country === 'US') {
                $('[name="' + fieldPrefix + '_state"]').closest('.form-row').show();
            } else if (country === 'GB') {
                $('[name="' + fieldPrefix + '_postcode"]').attr('placeholder', 'Postcode');
            }
        },

        handlePaymentMethodChange: function() {
            var selectedPaymentMethod = $('input[name="payment_method"]:checked').val();
            
            // You can add logic here to show/hide fields based on payment method
            // For example, show additional fields for specific payment gateways
        },

        handleFieldDependencies: function() {
            // Handle role-based field visibility
            if (typeof wcfm_role_fields !== 'undefined') {
                var roleFields = wcfm_role_fields;
                
                // Hide fields not allowed for current user role
                if (roleFields.hide && roleFields.hide.length > 0) {
                    roleFields.hide.forEach(function(fieldKey) {
                        $('[name="' + fieldKey + '"]').closest('.wcfm-custom-field, .form-row').hide();
                    });
                }
                
                // Show fields allowed for current user role
                if (roleFields.show && roleFields.show.length > 0) {
                    roleFields.show.forEach(function(fieldKey) {
                        $('[name="' + fieldKey + '"]').closest('.wcfm-custom-field, .form-row').show();
                    });
                }
            }
        },

        initTooltips: function() {
            // Initialize tooltips for fields with help text
            $('.wcfm-tooltip').each(function() {
                var $tooltip = $(this);
                var tooltipText = $tooltip.data('tooltip');
                
                if (tooltipText) {
                    $tooltip.append('<span class="tooltip-content">' + tooltipText + '</span>');
                }
            });
        },

        initBlockCheckoutCompatibility: function() {
            // Handle compatibility with WooCommerce Blocks checkout
            
            // Listen for block checkout updates
            $(document).on('wc-blocks_checkout_updated', function(event) {
                // Re-initialize validation after checkout update
                setTimeout(function() {
                    WCFM_Frontend.initValidation();
                    WCFM_Frontend.evaluateConditionalRules();
                }, 100);
            });
            
            // Handle block-specific field rendering
            if (typeof wp !== 'undefined' && wp.hooks) {
                wp.hooks.addFilter(
                    'woocommerce_blocks_checkout_fields',
                    'wcfm/checkout-fields',
                    function(fields) {
                        // Add custom fields to block checkout
                        return WCFM_Frontend.addCustomFieldsToBlocks(fields);
                    }
                );
            }
        },

        addCustomFieldsToBlocks: function(fields) {
            // Add custom fields to block checkout fields
            var fieldSettings = wcfm_frontend.field_settings;
            
            for (var fieldKey in fieldSettings) {
                var settings = fieldSettings[fieldKey];
                
                // Determine field section
                var section = 'billing';
                if (fieldKey.startsWith('shipping_')) {
                    section = 'shipping';
                } else if (fieldKey.startsWith('order_') || fieldKey === 'order_comments') {
                    section = 'order';
                }
                
                // Add field to appropriate section
                if (!fields[section]) {
                    fields[section] = {};
                }
                
                fields[section][fieldKey] = {
                    label: settings.label || fieldKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                    required: settings.required || false,
                    type: settings.type || 'text',
                    validate: function(value) {
                        return WCFM_Frontend.validateFieldValue(fieldKey, value);
                    }
                };
            }
            
            return fields;
        },

        validateFieldValue: function(fieldKey, value) {
            var fieldSettings = wcfm_frontend.field_settings[fieldKey];
            if (!fieldSettings) return true;
            
            // Required validation
            if (fieldSettings.required && (!value || value.trim() === '')) {
                return false;
            }
            
            // Type-specific validation
            switch (fieldSettings.type) {
                case 'email':
                    return !value || this.validateEmail(value);
                case 'tel':
                    return !value || this.validatePhone(value);
                case 'number':
                    return !value || (!isNaN(value) && isFinite(value));
                case 'date':
                    return !value || this.validateDate(value);
            }
            
            return true;
        },

        // AJAX field updates
        updateFieldsAjax: function(triggerField, triggerValue) {
            $.post(wcfm_frontend.ajax_url, {
                action: 'wcfm_update_checkout_fields',
                nonce: wcfm_frontend.nonce,
                trigger_field: triggerField,
                trigger_value: triggerValue
            })
            .done(function(response) {
                if (response.success) {
                    WCFM_Frontend.applyFieldUpdates(response.data);
                }
            });
        },

        applyFieldUpdates: function(updates) {
            // Show fields
            if (updates.fields_to_show) {
                updates.fields_to_show.forEach(function(fieldKey) {
                    var $field = $('[name="' + fieldKey + '"]');
                    $field.closest('.wcfm-custom-field, .form-row').removeClass('wcfm-field-hidden').show();
                });
            }
            
            // Hide fields
            if (updates.fields_to_hide) {
                updates.fields_to_hide.forEach(function(fieldKey) {
                    var $field = $('[name="' + fieldKey + '"]');
                    $field.closest('.wcfm-custom-field, .form-row').addClass('wcfm-field-hidden').hide();
                });
            }
            
            // Require fields
            if (updates.fields_to_require) {
                updates.fields_to_require.forEach(function(fieldKey) {
                    var $field = $('[name="' + fieldKey + '"]');
                    $field.attr('required', true);
                    WCFM_Frontend.markFieldAsRequired($field);
                });
            }
            
            // Make fields optional
            if (updates.fields_to_unrequire) {
                updates.fields_to_unrequire.forEach(function(fieldKey) {
                    var $field = $('[name="' + fieldKey + '"]');
                    $field.removeAttr('required');
                    $field.closest('.wcfm-custom-field, .form-row').find('.required').remove();
                });
            }
        },

        // Utility functions
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Field formatting
        formatField: function($field) {
            var fieldType = $field.attr('type') || $field.data('type');
            
            switch (fieldType) {
                case 'tel':
                    // Format phone number as user types
                    $field.on('input', function() {
                        var value = $(this).val().replace(/\D/g, '');
                        var formattedValue = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                        $(this).val(formattedValue);
                    });
                    break;
                    
                case 'number':
                    // Add number formatting
                    $field.on('blur', function() {
                        var value = parseFloat($(this).val());
                        if (!isNaN(value)) {
                            $(this).val(value.toFixed(2));
                        }
                    });
                    break;
            }
        },

        // Field masking
        applyFieldMask: function($field, mask) {
            // Simple field masking implementation
            $field.on('input', function() {
                var value = $(this).val();
                var maskedValue = '';
                var valueIndex = 0;
                
                for (var i = 0; i < mask.length && valueIndex < value.length; i++) {
                    if (mask[i] === '9') {
                        if (/\d/.test(value[valueIndex])) {
                            maskedValue += value[valueIndex];
                            valueIndex++;
                        } else {
                            break;
                        }
                    } else if (mask[i] === 'A') {
                        if (/[A-Za-z]/.test(value[valueIndex])) {
                            maskedValue += value[valueIndex].toUpperCase();
                            valueIndex++;
                        } else {
                            break;
                        }
                    } else {
                        maskedValue += mask[i];
                    }
                }
                
                $(this).val(maskedValue);
            });
        },

        // Character counter
        addCharacterCounter: function($field, maxLength) {
            var $counter = $('<span class="wcfm-char-counter">0 / ' + maxLength + '</span>');
            $field.after($counter);
            
            $field.on('input', function() {
                var currentLength = $(this).val().length;
                $counter.text(currentLength + ' / ' + maxLength);
                
                if (currentLength > maxLength * 0.9) {
                    $counter.addClass('wcfm-char-warning');
                } else {
                    $counter.removeClass('wcfm-char-warning');
                }
                
                if (currentLength >= maxLength) {
                    $counter.addClass('wcfm-char-limit');
                } else {
                    $counter.removeClass('wcfm-char-limit');
                }
            });
        },

        // Auto-resize textarea
        autoResizeTextarea: function($textarea) {
            $textarea.on('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        },

        // Field focus enhancement
        enhanceFieldFocus: function() {
            $('.wcfm-custom-field input, .wcfm-custom-field textarea, .wcfm-custom-field select').on('focus', function() {
                $(this).closest('.wcfm-custom-field').addClass('wcfm-field-focused');
            }).on('blur', function() {
                $(this).closest('.wcfm-custom-field').removeClass('wcfm-field-focused');
            });
        },

        // Accessibility enhancements
        enhanceAccessibility: function() {
            // Add ARIA attributes
            $('.wcfm-custom-field input, .wcfm-custom-field textarea, .wcfm-custom-field select').each(function() {
                var $field = $(this);
                var $label = $field.closest('.wcfm-custom-field').find('label');
                var $error = $field.siblings('.wcfm-field-error');
                
                // Connect label to field
                if ($label.length && !$field.attr('aria-labelledby')) {
                    var labelId = 'label-' + Math.random().toString(36).substr(2, 9);
                    $label.attr('id', labelId);
                    $field.attr('aria-labelledby', labelId);
                }
                
                // Connect error to field
                if ($error.length) {
                    var errorId = 'error-' + Math.random().toString(36).substr(2, 9);
                    $error.attr('id', errorId);
                    $field.attr('aria-describedby', errorId);
                    $field.attr('aria-invalid', 'true');
                }
            });
            
            // Keyboard navigation enhancements
            $(document).on('keydown', '.wcfm-custom-field input, .wcfm-custom-field textarea, .wcfm-custom-field select', function(e) {
                // Handle Enter key to move to next field
                if (e.key === 'Enter' && !$(this).is('textarea')) {
                    e.preventDefault();
                    var $nextField = $(this).closest('.wcfm-custom-field').next().find('input, textarea, select').first();
                    if ($nextField.length) {
                        $nextField.focus();
                    }
                }
            });
        },

        // Performance optimization
        optimizePerformance: function() {
            // Use requestAnimationFrame for smooth animations
            var rafId;
            
            $(window).on('scroll resize', function() {
                if (rafId) {
                    cancelAnimationFrame(rafId);
                }
                
                rafId = requestAnimationFrame(function() {
                    WCFM_Frontend.handleViewportChanges();
                });
            });
        },

        handleViewportChanges: function() {
            // Handle responsive changes
            var isMobile = window.innerWidth < 768;
            
            if (isMobile) {
                $('.wcfm-custom-field').addClass('wcfm-mobile');
            } else {
                $('.wcfm-custom-field').removeClass('wcfm-mobile');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCFM_Frontend.init();
        
        // Additional enhancements
        WCFM_Frontend.enhanceFieldFocus();
        WCFM_Frontend.enhanceAccessibility();
        WCFM_Frontend.optimizePerformance();
        
        // Apply field formatting
        $('.wcfm-custom-field input, .wcfm-custom-field textarea').each(function() {
            WCFM_Frontend.formatField($(this));
            
            // Add character counter if maxlength is set
            var maxLength = $(this).attr('maxlength');
            if (maxLength) {
                WCFM_Frontend.addCharacterCounter($(this), maxLength);
            }
            
            // Auto-resize textareas
            if ($(this).is('textarea')) {
                WCFM_Frontend.autoResizeTextarea($(this));
            }
        });
        
        // Handle initial viewport
        WCFM_Frontend.handleViewportChanges();
    });

    // Make WCFM_Frontend available globally
    window.WCFM_Frontend = WCFM_Frontend;

})(jQuery);