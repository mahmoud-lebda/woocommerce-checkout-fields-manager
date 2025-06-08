/**
 * Simplified WooCommerce Blocks Integration for WCFM
 * This approach directly manipulates the DOM which is more reliable for Block Checkout
 */

(function() {
    'use strict';

    console.log('WCFM Blocks Integration loaded');

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
        
        init: function() {
            console.log('WCFM Blocks: Initializing...');
            
            // Apply field modifications immediately and periodically
            this.applyFieldModifications();
            
            // Watch for DOM changes (block checkout loads dynamically)
            this.watchForChanges();
            
            // Apply modifications every 2 seconds for dynamic loading
            setInterval(() => {
                this.applyFieldModifications();
            }, 2000);
        },

        applyFieldModifications: function() {
            if (!wcfmBlocksSettings.fieldSettings) {
                return;
            }

            const fieldSettings = wcfmBlocksSettings.fieldSettings;
            let modificationsApplied = 0;

            console.log('WCFM: Applying field modifications...');

            Object.keys(fieldSettings).forEach(fieldKey => {
                const config = fieldSettings[fieldKey];
                
                // Find the field in various possible ways
                const field = this.findField(fieldKey);
                
                if (field) {
                    console.log(`WCFM: Found field ${fieldKey}`, config);
                    
                    // Apply modifications
                    if (this.modifyField(field, fieldKey, config)) {
                        modificationsApplied++;
                    }
                } else {
                    console.log(`WCFM: Field ${fieldKey} not found in DOM`);
                }
            });

            if (modificationsApplied > 0) {
                console.log(`WCFM: Applied modifications to ${modificationsApplied} fields`);
            }
        },

        findField: function(fieldKey) {
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
                    return field;
                }
            }

            return null;
        },

        modifyField: function(field, fieldKey, config) {
            const wrapper = field.closest('.wc-block-components-text-input, .wc-block-components-form-row, .wp-block-woocommerce-checkout-contact-information-block, .wp-block-woocommerce-checkout-billing-address-block, .wp-block-woocommerce-checkout-shipping-address-block');
            
            if (!wrapper) {
                console.log(`WCFM: No wrapper found for ${fieldKey}`);
                return false;
            }

            let modified = false;

            // Handle field visibility
            if (!config.enabled) {
                wrapper.style.display = 'none';
                wrapper.classList.add('wcfm-field-hidden');
                console.log(`WCFM: Hidden field ${fieldKey}`);
                modified = true;
            } else {
                wrapper.style.display = '';
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
                    console.log('WCFM: DOM changed, reapplying modifications...');
                    setTimeout(() => {
                        this.applyFieldModifications();
                    }, 500);
                }
            });

            // Start observing
            const checkoutContainer = document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout');
            if (checkoutContainer) {
                observer.observe(checkoutContainer, {
                    childList: true,
                    subtree: true
                });
                console.log('WCFM: Started watching for DOM changes');
            } else {
                console.log('WCFM: Checkout container not found for observation');
            }
        }
    };

    // Initialize when DOM is ready
    function initWCFM() {
        console.log('WCFM: DOM ready, initializing...');
        WCFMBlocks.init();
    }

    // Multiple initialization methods
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWCFM);
    } else {
        initWCFM();
    }

    // Also try to initialize after a delay (for dynamic loading)
    setTimeout(initWCFM, 1000);
    setTimeout(initWCFM, 3000);

    // Global access for debugging
    window.WCFMBlocks = WCFMBlocks;

})();