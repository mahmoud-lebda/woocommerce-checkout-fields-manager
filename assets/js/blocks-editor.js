/**
 * WooCommerce Blocks Editor Integration for WCFM
 * This file handles the integration with WooCommerce Block Editor
 */

(function() {
    'use strict';

    // Check if we're in the editor environment
    if (typeof wp === 'undefined' || !wp.hooks || !wp.data) {
        return;
    }

    const { addFilter } = wp.hooks;
    const { __ } = wp.i18n;

    /**
     * Modify checkout fields in the editor
     */
    addFilter(
        'woocommerce_blocks_checkout_fields_editor',
        'wcfm/modify-checkout-fields-editor',
        function(fields) {
            // Get field settings if available
            const fieldSettings = (typeof wcfmBlocksSettings !== 'undefined') ? 
                wcfmBlocksSettings.fieldSettings : {};

            if (!Object.keys(fieldSettings).length) {
                return fields;
            }

            // Process each field setting for editor preview
            Object.keys(fieldSettings).forEach(fieldKey => {
                const fieldConfig = fieldSettings[fieldKey];
                const section = fieldConfig.section || 'billing';
                
                // Skip if field is disabled
                if (!fieldConfig.enabled) {
                    if (fields[section] && fields[section][fieldKey]) {
                        // Mark as disabled rather than removing completely in editor
                        fields[section][fieldKey].disabled = true;
                        fields[section][fieldKey].className = (fields[section][fieldKey].className || '') + ' wcfm-field-disabled';
                    }
                    return;
                }

                // Ensure section exists
                if (!fields[section]) {
                    fields[section] = {};
                }

                // Create or modify the field for editor
                if (!fields[section][fieldKey]) {
                    fields[section][fieldKey] = {
                        type: 'text',
                        label: fieldKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
                    };
                }

                // Apply field configuration for editor preview
                if (fieldConfig.required !== undefined) {
                    fields[section][fieldKey].required = fieldConfig.required;
                }

                if (fieldConfig.label) {
                    fields[section][fieldKey].label = fieldConfig.label;
                }

                if (fieldConfig.priority !== undefined) {
                    fields[section][fieldKey].priority = fieldConfig.priority;
                }

                // Add editor-specific classes
                fields[section][fieldKey].className = (fields[section][fieldKey].className || '') + ' wcfm-field wcfm-editor-field';

                if (fieldConfig.required) {
                    fields[section][fieldKey].className += ' wcfm-field-required';
                }
            });

            return fields;
        }
    );

    /**
     * Add WCFM notice in the editor
     */
    addFilter(
        'woocommerce_blocks_checkout_editor_notices',
        'wcfm/add-editor-notice',
        function(notices) {
            // Add notice about WCFM customization
            notices.push({
                id: 'wcfm-customization-notice',
                type: 'info',
                content: __('Checkout fields are being managed by WCFM Checkout Fields Manager. Changes made here may be overridden by plugin settings.', 'woo-checkout-fields-manager')
            });

            return notices;
        }
    );

    /**
     * Modify field properties in editor sidebar
     */
    addFilter(
        'woocommerce_blocks_checkout_field_properties',
        'wcfm/modify-field-properties-editor',
        function(properties, fieldKey) {
            // Get field settings if available
            const fieldSettings = (typeof wcfmBlocksSettings !== 'undefined') ? 
                wcfmBlocksSettings.fieldSettings : {};

            if (fieldSettings[fieldKey]) {
                const fieldConfig = fieldSettings[fieldKey];

                // Add WCFM-specific properties
                properties.push({
                    label: __('WCFM Managed', 'woo-checkout-fields-manager'),
                    value: fieldConfig.enabled ? __('Yes', 'woo-checkout-fields-manager') : __('No (Disabled)', 'woo-checkout-fields-manager'),
                    readonly: true
                });

                if (fieldConfig.enabled) {
                    properties.push({
                        label: __('WCFM Required', 'woo-checkout-fields-manager'),
                        value: fieldConfig.required ? __('Yes', 'woo-checkout-fields-manager') : __('No', 'woo-checkout-fields-manager'),
                        readonly: true
                    });

                    properties.push({
                        label: __('WCFM Priority', 'woo-checkout-fields-manager'),
                        value: fieldConfig.priority || 10,
                        readonly: true
                    });
                }
            }

            return properties;
        }
    );

    /**
     * Add WCFM settings link to editor sidebar
     */
    addFilter(
        'woocommerce_blocks_checkout_editor_sidebar',
        'wcfm/add-settings-link',
        function(sidebar) {
            // Add link to WCFM settings
            sidebar.push({
                title: __('WCFM Settings', 'woo-checkout-fields-manager'),
                content: wp.element.createElement('div', {
                    style: { padding: '16px' }
                }, [
                    wp.element.createElement('p', {
                        key: 'description'
                    }, __('Manage checkout fields with WCFM Checkout Fields Manager', 'woo-checkout-fields-manager')),
                    wp.element.createElement('a', {
                        key: 'settings-link',
                        href: '/wp-admin/admin.php?page=wcfm-checkout-fields',
                        target: '_blank',
                        className: 'button button-primary',
                        style: { marginTop: '8px' }
                    }, __('Open WCFM Settings', 'woo-checkout-fields-manager'))
                ])
            });

            return sidebar;
        }
    );

})();