/**
 * Enhanced WooCommerce Blocks Integration for WCFM - No Flash Version
 * This approach applies field hiding immediately without any flash
 */

(function() {
    'use strict';

    console.log('WCFM Blocks Integration loaded - No Flash Version');

    // Check if we have the settings
    if (typeof wcfmBlocksSettings === 'undefined') {
        console.log('WCFM: Block settings not available');
        return;
    }

    console.log('WCFM: Block settings loaded', wcfmBlocksSettings);

    /**
     * Main WCFM Blocks handler
     */
    const WCFMBlocks = {
        
        initialized: false,
        appliedFields: new Set(),
        
        init: function() {
            console.log('WCFM Blocks: Initializing No Flash version...');
            
            if (this.initialized) {
                return;
            }
            
            // Apply immediate field modifications
            this.applyImmediateFieldModifications();
            
            // Watch for DOM changes
            this.watchForChanges();
            
            // Apply modifications periodically but less frequently
            this.startPeriodicCheck();
            
            this.initialized = true;
        },

        applyImmediateFieldModifications: function() {
            if (!wcfmBlocksSettings.fieldSettings) {
                return;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings;
            console.log('WCFM: Applying immediate field modifications...');

            Object.keys(fieldSettings).forEach(fieldKey => {
                const config = fieldSettings[fieldKey];
                
                if (!config.enabled) {
                    // Add CSS rules for immediate hiding
                    this.addImmediateHideCSS(fieldKey);
                }
                
                // Find and modify existing fields
                this.findAndModifyField(fieldKey, config);
            });
        },

        addImmediateHideCSS: function(fieldKey) {
            const existingStyle = document.getElementById('wcfm-dynamic-hide');
            let styleElement = existingStyle;
            
            if (!styleElement) {
                styleElement = document.createElement('style');
                styleElement.id = 'wcfm-dynamic-hide';
                styleElement.type = 'text/css';
                document.head.insertBefore(styleElement, document.head.firstChild);
            }
            
            // Build comprehensive CSS selectors for immediate hiding
            const hideCSS = `
                /* Hide field: ${fieldKey} */
                input[name="${fieldKey}"],
                textarea[name="${fieldKey}"],
                select[name="${fieldKey}"],
                input[id="${fieldKey}"],
                textarea[id="${fieldKey}"],
                select[id="${fieldKey}"],
                input[id*="${fieldKey}"],
                input[name*="${fieldKey}"],
                .wc-block-components-text-input:has(input[name="${fieldKey}"]),
                .wc-block-components-form-row:has(input[name="${fieldKey}"]),
                .wc-block-components-text-input:has(input[id="${fieldKey}"]),
                .wc-block-components-form-row:has(input[id="${fieldKey}"]),
                .wc-block-components-text-input:has(input[id*="${fieldKey}"]),
                .wc-block-components-form-row:has(input[id*="${fieldKey}"]) {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
            `;
            
            styleElement.textContent += hideCSS;
        },

        findAndModifyField: function(fieldKey, config) {
            // Multiple strategies to find the field
            const selectors = [
                `input[name="${fieldKey}"]`,
                `textarea[name="${fieldKey}"]`,
                `select[name="${fieldKey}"]`,
                `input[id="${fieldKey}"]`,
                `textarea[id="${fieldKey}"]`,
                `select[id="${fieldKey}"]`,
                `input[name*="${fieldKey}"]`,
                `#${fieldKey}`,
                `.wc-block-components-text-input input[name*="${fieldKey.replace('billing_', '').replace('shipping_', '')}"]`,
                `.wc-block-components-text-input input[id*="${fieldKey.replace('billing_', '').replace('shipping_', '')}"]`
            ];

            for (const selector of selectors) {
                const field = document.querySelector(selector);
                if (field) {
                    this.modifyField(field, fieldKey, config);
                    this.appliedFields.add(fieldKey);
                    break;
                }
            }
        },

        modifyField: function(field, fieldKey, config) {
            const wrapper = field.closest('.wc-block-components-text-input, .wc-block-components-form-row, .wp-block-woocommerce-checkout-contact-information-block, .wp-block-woocommerce-checkout-billing-address-block, .wp-block-woocommerce-checkout-shipping-address-block');
            
            if (!wrapper) {
                console.log(`WCFM: No wrapper found for ${fieldKey}`);
                return false;
            }

            let modified = false;

            // Handle field visibility immediately
            if (!config.enabled) {
                wrapper.style.display = 'none';
                wrapper.style.visibility = 'hidden';
                wrapper.style.opacity = '0';
                wrapper.style.height = '0';
                wrapper.style.overflow = 'hidden';
                wrapper.style.margin = '0';
                wrapper.style.padding = '0';
                wrapper.classList.add('wcfm-field-hidden');
                console.log(`WCFM: Immediately hidden field ${fieldKey}`);
                modified = true;
            } else {
                // Ensure enabled fields are visible
                wrapper.style.display = '';
                wrapper.style.visibility = '';
                wrapper.style.opacity = '';
                wrapper.style.height = '';
                wrapper.style.overflow = '';
                wrapper.style.margin = '';
                wrapper.style.padding = '';
                wrapper.classList.remove('wcfm-field-hidden');
                wrapper.classList.add('wcfm-field-enabled');

                // Handle required state
                if (config.required) {
                    field.setAttribute('required', 'required');
                    field.setAttribute('aria-required', 'true');
                    wrapper.classList.add('wcfm-field-required');

                    // Add required indicator to label
                    const label = wrapper.querySelector('label');
                    if (label && !label.querySelector('.required')) {
                        label.innerHTML += ' <span class="required" style="color: #e74c3c;">*</span>';
                    }
                    console.log(`WCFM: Made field ${fieldKey} required`);
                    modified = true;
                } else {
                    field.removeAttribute('required');
                    field.setAttribute('aria-required', 'false');
                    wrapper.classList.remove('wcfm-field-required');
                    
                    // Remove required indicator
                    const requiredSpan = wrapper.querySelector('label .required');
                    if (requiredSpan) {
                        requiredSpan.remove();
                    }
                }

                // Handle custom label
                if (config.label) {
                    const label = wrapper.querySelector('label');
                    if (label) {
                        const requiredSpan = label.querySelector('.required');
                        label.textContent = config.label;
                        if (requiredSpan) {
                            label.appendChild(requiredSpan);
                        }
                        console.log(`WCFM: Updated label for ${fieldKey} to: ${config.label}`);
                        modified = true;
                    }
                }

                // Add WCFM classes
                field.classList.add('wcfm-field');
                field.classList.add(`wcfm-${fieldKey.replace(/_/g, '-')}`);
                wrapper.classList.add('wcfm-field-wrapper');
            }

            return modified;
        },

        startPeriodicCheck: function() {
            // Check less frequently to avoid performance issues
            setInterval(() => {
                this.checkForNewFields();
            }, 5000); // Every 5 seconds instead of 2
        },

        checkForNewFields: function() {
            if (!wcfmBlocksSettings.fieldSettings) {
                return;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings;
            let newFieldsFound = 0;

            Object.keys(fieldSettings).forEach(fieldKey => {
                if (!this.appliedFields.has(fieldKey)) {
                    const config = fieldSettings[fieldKey];
                    this.findAndModifyField(fieldKey, config);
                    if (this.appliedFields.has(fieldKey)) {
                        newFieldsFound++;
                    }
                }
            });

            if (newFieldsFound > 0) {
                console.log(`WCFM: Found and modified ${newFieldsFound} new fields`);
            }
        },

        watchForChanges: function() {
            // Watch for new elements being added to the DOM
            const observer = new MutationObserver((mutations) => {
                let shouldUpdate = false;
                
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === 1) { // Element node
                                const hasInput = node.querySelector && (
                                    node.querySelector('input') || 
                                    node.querySelector('textarea') || 
                                    node.querySelector('select') ||
                                    node.matches('input, textarea, select')
                                );
                                
                                if (hasInput) {
                                    shouldUpdate = true;
                                }
                            }
                        });
                    }
                });

                if (shouldUpdate) {
                    console.log('WCFM: DOM changed, applying immediate modifications...');
                    // Apply changes immediately when DOM changes
                    setTimeout(() => {
                        this.applyImmediateFieldModifications();
                    }, 50); // Very short delay to ensure DOM is ready
                }
            });

            // Start observing
            const checkoutContainer = document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout, body');
            if (checkoutContainer) {
                observer.observe(checkoutContainer, {
                    childList: true,
                    subtree: true
                });
                console.log('WCFM: Started watching for DOM changes');
            }
        },

        // Utility function to ensure fields are hidden on page load
        ensureFieldsHiddenOnLoad: function() {
            if (!wcfmBlocksSettings.fieldSettings) {
                return;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings;
            
            Object.keys(fieldSettings).forEach(fieldKey => {
                const config = fieldSettings[fieldKey];
                
                if (!config.enabled) {
                    // Use multiple methods to ensure hiding
                    this.addImmediateHideCSS(fieldKey);
                    
                    // Also try to find and hide immediately
                    const possibleSelectors = [
                        `input[name="${fieldKey}"]`,
                        `input[id="${fieldKey}"]`,
                        `input[name*="${fieldKey}"]`,
                        `input[id*="${fieldKey}"]`
                    ];
                    
                    possibleSelectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(element => {
                            const wrapper = element.closest('.wc-block-components-text-input, .wc-block-components-form-row');
                            if (wrapper) {
                                wrapper.style.display = 'none';
                                wrapper.style.visibility = 'hidden';
                                wrapper.style.opacity = '0';
                                wrapper.classList.add('wcfm-field-hidden');
                            }
                        });
                    });
                }
            });
        }
    };

    /**
     * Initialize immediately and with multiple fallbacks
     */
    function initWCFM() {
        console.log('WCFM: Initializing with no-flash approach...');
        
        // Ensure fields are hidden immediately
        WCFMBlocks.ensureFieldsHiddenOnLoad();
        
        // Initialize main functionality
        WCFMBlocks.init();
    }

    // Multiple initialization methods to catch all scenarios
    
    // 1. If DOM is already ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWCFM);
    } else {
        initWCFM();
    }

    // 2. Initialize immediately (for fields that might already exist)
    WCFMBlocks.ensureFieldsHiddenOnLoad();

    // 3. Also try to initialize after short delays
    setTimeout(initWCFM, 100);
    setTimeout(initWCFM, 500);
    setTimeout(initWCFM, 1000);

    // 4. Listen for specific WooCommerce events
    document.addEventListener('wc-blocks_checkout_loaded', initWCFM);
    document.addEventListener('wc-blocks_checkout_updated', initWCFM);

    // Global access for debugging
    window.WCFMBlocks = WCFMBlocks;

})();