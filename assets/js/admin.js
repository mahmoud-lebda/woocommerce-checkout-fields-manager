/**
 * WooCommerce Checkout Fields Manager - Admin JavaScript
 */

(function($) {
    'use strict';

    // Main admin object
    var WCFM_Admin = {
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.loadCustomFields();
            this.initFieldTypeHandlers();
        },

        bindEvents: function() {
            // Save settings
            $('#wcfm-save-settings').on('click', this.saveSettings);
            
            // Reset settings
            $('#wcfm-reset-settings').on('click', this.resetSettings);
            
            // Export settings
            $('#wcfm-export-settings').on('click', this.exportSettings);
            
            // Import settings
            $('#wcfm-import-settings').on('click', function() {
                $('#wcfm-import-file').click();
            });
            $('#wcfm-import-file').on('change', this.importSettings);
            
            // Custom field modal
            $('#wcfm-add-custom-field').on('click', this.openCustomFieldModal);
            $('.wcfm-modal-close').on('click', this.closeCustomFieldModal);
            $('#wcfm-save-custom-field').on('click', this.saveCustomField);
            
            // Edit custom field
            $(document).on('click', '.wcfm-edit-custom-field', this.editCustomField);
            
            // Delete custom field
            $(document).on('click', '.wcfm-delete-custom-field', this.deleteCustomField);
            
            // Field type change
            $('#wcfm-custom-field-form select[name="field_type"]').on('change', this.handleFieldTypeChange);
            
            // Modal click outside
            $('.wcfm-modal').on('click', function(e) {
                if (e.target === this) {
                    WCFM_Admin.closeCustomFieldModal();
                }
            });
            
            // Form field changes
            $('.wcfm-fields-table').on('change', 'input, select', this.markFormDirty);
            
            // Prevent form submission on enter
            $(document).on('keypress', 'form input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        initSortable: function() {
            $('.wcfm-fields-table tbody').sortable({
                handle: '.wcfm-field-sort',
                placeholder: 'ui-sortable-placeholder',
                helper: 'clone',
                axis: 'y',
                update: function(event, ui) {
                    WCFM_Admin.updateFieldPriorities();
                    WCFM_Admin.markFormDirty();
                }
            });
        },

        updateFieldPriorities: function() {
            $('.wcfm-fields-table tbody tr').each(function(index) {
                $(this).find('.wcfm-field-priority input').val((index + 1) * 10);
            });
        },

        markFormDirty: function() {
            $('.wcfm-save-actions .button-primary').addClass('button-primary-dirty');
            window.onbeforeunload = function() {
                return wcfm_admin.strings.unsaved_changes;
            };
        },

        clearFormDirty: function() {
            $('.wcfm-save-actions .button-primary').removeClass('button-primary-dirty');
            window.onbeforeunload = null;
        },

        saveSettings: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $('.wcfm-save-actions .spinner');
            
            // Get form data
            var formData = WCFM_Admin.getFormData();
            
            // Show loading
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            // AJAX request
            $.post(wcfm_admin.ajax_url, {
                action: 'wcfm_save_settings',
                nonce: wcfm_admin.nonce,
                form_data: $.param(formData)
            })
            .done(function(response) {
                if (response.success) {
                    WCFM_Admin.showNotice(response.data, 'success');
                    WCFM_Admin.clearFormDirty();
                } else {
                    WCFM_Admin.showNotice(response.data || wcfm_admin.strings.save_error, 'error');
                }
            })
            .fail(function() {
                WCFM_Admin.showNotice(wcfm_admin.strings.save_error, 'error');
            })
            .always(function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },

        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(wcfm_admin.strings.confirm_reset || 'Are you sure you want to reset all settings to defaults?')) {
                return;
            }
            
            var $button = $(this);
            var $spinner = $('.wcfm-save-actions .spinner');
            
            // Show loading
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            // AJAX request
            $.post(wcfm_admin.ajax_url, {
                action: 'wcfm_reset_settings',
                nonce: wcfm_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    WCFM_Admin.showNotice(response.data, 'success');
                    location.reload();
                } else {
                    WCFM_Admin.showNotice(response.data || 'Failed to reset settings.', 'error');
                }
            })
            .fail(function() {
                WCFM_Admin.showNotice('Failed to reset settings.', 'error');
            })
            .always(function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },

        exportSettings: function(e) {
            e.preventDefault();
            
            $.post(wcfm_admin.ajax_url, {
                action: 'wcfm_export_settings',
                nonce: wcfm_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var dataStr = JSON.stringify(response.data, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = 'wcfm-settings-' + new Date().toISOString().slice(0, 10) + '.json';
                    link.click();
                    
                    WCFM_Admin.showNotice('Settings exported successfully!', 'success');
                } else {
                    WCFM_Admin.showNotice('Failed to export settings.', 'error');
                }
            })
            .fail(function() {
                WCFM_Admin.showNotice('Failed to export settings.', 'error');
            });
        },

        importSettings: function(e) {
            var file = e.target.files[0];
            if (!file) return;
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var importData = e.target.result;
                    
                    $.post(wcfm_admin.ajax_url, {
                        action: 'wcfm_import_settings',
                        nonce: wcfm_admin.nonce,
                        import_data: importData
                    })
                    .done(function(response) {
                        if (response.success) {
                            WCFM_Admin.showNotice(response.data, 'success');
                            location.reload();
                        } else {
                            WCFM_Admin.showNotice(response.data || 'Failed to import settings.', 'error');
                        }
                    })
                    .fail(function() {
                        WCFM_Admin.showNotice('Failed to import settings.', 'error');
                    });
                } catch (error) {
                    WCFM_Admin.showNotice('Invalid file format.', 'error');
                }
            };
            reader.readAsText(file);
        },

        getFormData: function() {
            var formData = {};
            
            // Get all form inputs
            $('.wcfm-fields-table input, .wcfm-fields-table select, form input, form select, form textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if (!name) return;
                
                if ($field.attr('type') === 'checkbox') {
                    if ($field.is(':checked')) {
                        WCFM_Admin.setNestedValue(formData, name, value || '1');
                    }
                } else if ($field.attr('type') === 'radio') {
                    if ($field.is(':checked')) {
                        WCFM_Admin.setNestedValue(formData, name, value);
                    }
                } else {
                    WCFM_Admin.setNestedValue(formData, name, value);
                }
            });
            
            return formData;
        },

        setNestedValue: function(obj, path, value) {
            var keys = path.replace(/\]/g, '').split(/\[/);
            var current = obj;
            
            for (var i = 0; i < keys.length - 1; i++) {
                var key = keys[i];
                if (!(key in current)) {
                    current[key] = {};
                }
                current = current[key];
            }
            
            current[keys[keys.length - 1]] = value;
        },

        loadCustomFields: function() {
            $.post(wcfm_admin.ajax_url, {
                action: 'wcfm_get_custom_fields',
                nonce: wcfm_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $('#wcfm-custom-fields-list').html(
                        '<table class="wcfm-fields-table">' +
                        '<thead><tr>' +
                        '<th>Order</th><th>Field Key</th><th>Label</th><th>Type</th><th>Section</th><th>Status</th><th>Actions</th>' +
                        '</tr></thead>' +
                        '<tbody>' + response.data + '</tbody>' +
                        '</table>'
                    );
                }
            });
        },

        openCustomFieldModal: function(e) {
            e.preventDefault();
            $('#wcfm-custom-field-modal').show();
            $('#wcfm-custom-field-form')[0].reset();
            $('#wcfm-custom-field-form input[name="field_id"]').remove();
            WCFM_Admin.handleFieldTypeChange();
        },

        closeCustomFieldModal: function() {
            $('#wcfm-custom-field-modal').hide();
        },

        editCustomField: function(e) {
            e.preventDefault();
            var fieldId = $(this).data('field-id');
            
            // Here you would load the field data and populate the form
            // For brevity, this is simplified
            $('#wcfm-custom-field-modal').show();
        },

        saveCustomField: function(e) {
            e.preventDefault();
            
            var formData = {};
            $('#wcfm-custom-field-form').serializeArray().forEach(function(field) {
                formData[field.name] = field.value;
            });
            
            // Add checkboxes
            $('#wcfm-custom-field-form input[type="checkbox"]').each(function() {
                formData[$(this).attr('name')] = $(this).is(':checked') ? 1 : 0;
            });
            
            $.post(wcfm_admin.ajax_url, {
                action: 'wcfm_save_custom_field',
                nonce: wcfm_admin.nonce,
                ...formData
            })
            .done(function(response) {
                if (response.success) {
                    WCFM_Admin.showNotice(response.data, 'success');
                    WCFM_Admin.closeCustomFieldModal();
                    WCFM_Admin.loadCustomFields();
                } else {
                    WCFM_Admin.showNotice(response.data || 'Failed to save custom field.', 'error');
                }
            })
            .fail(function() {
                WCFM_Admin.showNotice('Failed to save custom field.', 'error');
            });
        },

        deleteCustomField: function(e) {
            e.preventDefault();
            
            if (!confirm(wcfm_admin.strings.confirm_delete)) {
                return;
            }
            
            var fieldId = $(this).data('field-id');
            
            $.post(wcfm_admin.ajax_url, {
                action: 'wcfm_delete_custom_field',
                nonce: wcfm_admin.nonce,
                field_id: fieldId
            })
            .done(function(response) {
                if (response.success) {
                    WCFM_Admin.showNotice(response.data, 'success');
                    WCFM_Admin.loadCustomFields();
                } else {
                    WCFM_Admin.showNotice(response.data || 'Failed to delete custom field.', 'error');
                }
            })
            .fail(function() {
                WCFM_Admin.showNotice('Failed to delete custom field.', 'error');
            });
        },

        handleFieldTypeChange: function() {
            var fieldType = $('#wcfm-custom-field-form select[name="field_type"]').val();
            var $optionsRow = $('.field-options-row');
            
            if (['select', 'radio', 'checkbox'].includes(fieldType)) {
                $optionsRow.addClass('show');
            } else {
                $optionsRow.removeClass('show');
            }
        },

        initFieldTypeHandlers: function() {
            // Initialize field type specific handlers
            this.handleFieldTypeChange();
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
            
            // Scroll to top
            $('html, body').animate({scrollTop: 0}, 500);
        },

        // Field validation
        validateField: function(field) {
            var $field = $(field);
            var value = $field.val();
            var required = $field.attr('required') || $field.data('required');
            var type = $field.attr('type') || $field.data('type');
            
            // Remove previous error states
            $field.removeClass('error');
            $field.next('.field-error').remove();
            
            // Required validation
            if (required && !value.trim()) {
                this.showFieldError($field, 'This field is required.');
                return false;
            }
            
            // Type validation
            switch (type) {
                case 'email':
                    if (value && !this.isValidEmail(value)) {
                        this.showFieldError($field, 'Please enter a valid email address.');
                        return false;
                    }
                    break;
                case 'url':
                    if (value && !this.isValidUrl(value)) {
                        this.showFieldError($field, 'Please enter a valid URL.');
                        return false;
                    }
                    break;
                case 'number':
                    if (value && isNaN(value)) {
                        this.showFieldError($field, 'Please enter a valid number.');
                        return false;
                    }
                    break;
            }
            
            return true;
        },

        showFieldError: function($field, message) {
            $field.addClass('error');
            $field.after('<span class="field-error" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">' + message + '</span>');
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        // Bulk actions
        bulkAction: function(action) {
            var selectedFields = $('.wcfm-fields-table input[type="checkbox"]:checked');
            
            if (selectedFields.length === 0) {
                alert('Please select at least one field.');
                return;
            }
            
            switch (action) {
                case 'enable':
                    selectedFields.each(function() {
                        $(this).closest('tr').find('.wcfm-field-enabled input').prop('checked', true);
                    });
                    break;
                case 'disable':
                    selectedFields.each(function() {
                        $(this).closest('tr').find('.wcfm-field-enabled input').prop('checked', false);
                    });
                    break;
                case 'require':
                    selectedFields.each(function() {
                        $(this).closest('tr').find('.wcfm-field-required input').prop('checked', true);
                    });
                    break;
                case 'unrequire':
                    selectedFields.each(function() {
                        $(this).closest('tr').find('.wcfm-field-required input').prop('checked', false);
                    });
                    break;
            }
            
            this.markFormDirty();
        },

        // Search and filter
        initSearch: function() {
            var $searchInput = $('<input type="text" placeholder="Search fields..." class="wcfm-search-fields" style="margin-bottom: 10px; padding: 8px; width: 300px;">');
            $('.wcfm-fields-table-wrapper').prepend($searchInput);
            
            $searchInput.on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                
                $('.wcfm-fields-table tbody tr').each(function() {
                    var $row = $(this);
                    var fieldKey = $row.find('.wcfm-field-name').text().toLowerCase();
                    var fieldLabel = $row.find('.wcfm-field-label input').val().toLowerCase();
                    
                    if (fieldKey.includes(searchTerm) || fieldLabel.includes(searchTerm)) {
                        $row.show();
                    } else {
                        $row.hide();
                    }
                });
            });
        },

        // Field preview
        initFieldPreview: function() {
            $(document).on('change', '#wcfm-custom-field-form input, #wcfm-custom-field-form select, #wcfm-custom-field-form textarea', function() {
                WCFM_Admin.updateFieldPreview();
            });
        },

        updateFieldPreview: function() {
            var $form = $('#wcfm-custom-field-form');
            var fieldType = $form.find('select[name="field_type"]').val();
            var fieldLabel = $form.find('input[name="field_label"]').val();
            var fieldPlaceholder = $form.find('input[name="field_placeholder"]').val();
            var fieldRequired = $form.find('input[name="field_required"]').is(':checked');
            var fieldOptions = $form.find('textarea[name="field_options"]').val();
            
            var previewHtml = '<div class="wcfm-field-preview">';
            previewHtml += '<label>' + (fieldLabel || 'Field Label');
            if (fieldRequired) {
                previewHtml += ' <span class="required">*</span>';
            }
            previewHtml += '</label>';
            
            switch (fieldType) {
                case 'text':
                case 'email':
                case 'tel':
                case 'number':
                case 'date':
                    previewHtml += '<input type="' + fieldType + '" placeholder="' + (fieldPlaceholder || '') + '" disabled>';
                    break;
                case 'textarea':
                    previewHtml += '<textarea placeholder="' + (fieldPlaceholder || '') + '" disabled></textarea>';
                    break;
                case 'select':
                    previewHtml += '<select disabled><option>Select an option</option>';
                    if (fieldOptions) {
                        var options = fieldOptions.split('\n');
                        options.forEach(function(option) {
                            if (option.trim()) {
                                var parts = option.split('|');
                                var value = parts[0].trim();
                                var label = parts[1] ? parts[1].trim() : value;
                                previewHtml += '<option value="' + value + '">' + label + '</option>';
                            }
                        });
                    }
                    previewHtml += '</select>';
                    break;
                case 'checkbox':
                    previewHtml += '<label><input type="checkbox" disabled> ' + (fieldLabel || 'Checkbox Option') + '</label>';
                    break;
                case 'radio':
                    if (fieldOptions) {
                        var options = fieldOptions.split('\n');
                        options.forEach(function(option) {
                            if (option.trim()) {
                                var parts = option.split('|');
                                var value = parts[0].trim();
                                var label = parts[1] ? parts[1].trim() : value;
                                previewHtml += '<label><input type="radio" name="preview_radio" disabled> ' + label + '</label><br>';
                            }
                        });
                    }
                    break;
            }
            
            previewHtml += '</div>';
            
            var $existingPreview = $('.wcfm-field-preview');
            if ($existingPreview.length) {
                $existingPreview.replaceWith(previewHtml);
            } else {
                $('#wcfm-custom-field-form').append(previewHtml);
            }
        },

        // Conditional logic
        initConditionalLogic: function() {
            var $conditionalSection = $('<div class="wcfm-conditional-logic"><h4>Conditional Logic</h4><button type="button" class="button wcfm-add-condition">Add Condition</button><div class="wcfm-conditions"></div></div>');
            $('#wcfm-custom-field-form .form-table').append('<tr><td colspan="2"></td></tr>').find('td:last').append($conditionalSection);
            
            $(document).on('click', '.wcfm-add-condition', this.addConditionalRule);
            $(document).on('click', '.wcfm-remove-rule', this.removeConditionalRule);
        },

        addConditionalRule: function() {
            var ruleHtml = '<div class="wcfm-conditional-rule">';
            ruleHtml += '<select name="condition_field"><option value="">Select Field</option></select>';
            ruleHtml += '<select name="condition_operator">';
            ruleHtml += '<option value="equals">Equals</option>';
            ruleHtml += '<option value="not_equals">Not Equals</option>';
            ruleHtml += '<option value="contains">Contains</option>';
            ruleHtml += '<option value="not_contains">Does Not Contain</option>';
            ruleHtml += '<option value="is_empty">Is Empty</option>';
            ruleHtml += '<option value="is_not_empty">Is Not Empty</option>';
            ruleHtml += '</select>';
            ruleHtml += '<input type="text" name="condition_value" placeholder="Value">';
            ruleHtml += '<select name="condition_action">';
            ruleHtml += '<option value="show">Show</option>';
            ruleHtml += '<option value="hide">Hide</option>';
            ruleHtml += '<option value="require">Require</option>';
            ruleHtml += '<option value="unrequire">Make Optional</option>';
            ruleHtml += '</select>';
            ruleHtml += '<span class="wcfm-remove-rule dashicons dashicons-no-alt"></span>';
            ruleHtml += '</div>';
            
            $('.wcfm-conditions').append(ruleHtml);
        },

        removeConditionalRule: function() {
            $(this).closest('.wcfm-conditional-rule').remove();
        },

        // Keyboard shortcuts
        initKeyboardShortcuts: function() {
            $(document).on('keydown', function(e) {
                // Ctrl+S or Cmd+S to save
                if ((e.ctrlKey || e.metaKey) && e.which === 83) {
                    e.preventDefault();
                    $('#wcfm-save-settings').click();
                }
                
                // Escape to close modal
                if (e.which === 27) {
                    $('.wcfm-modal:visible').hide();
                }
            });
        },

        // Auto-save draft
        initAutoSave: function() {
            var autoSaveTimer;
            
            $('.wcfm-fields-table').on('change', 'input, select', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    WCFM_Admin.saveAsDraft();
                }, 30000); // Auto-save after 30 seconds of inactivity
            });
        },

        saveAsDraft: function() {
            var formData = this.getFormData();
            
            localStorage.setItem('wcfm_draft_settings', JSON.stringify({
                data: formData,
                timestamp: Date.now()
            }));
            
            this.showNotice('Settings auto-saved as draft.', 'info');
        },

        loadDraft: function() {
            var draft = localStorage.getItem('wcfm_draft_settings');
            if (!draft) return;
            
            try {
                var draftData = JSON.parse(draft);
                var age = Date.now() - draftData.timestamp;
                
                // Only load draft if it's less than 1 hour old
                if (age < 3600000) {
                    if (confirm('A draft was found. Would you like to restore it?')) {
                        this.populateFormWithData(draftData.data);
                    }
                }
            } catch (e) {
                // Invalid draft data, remove it
                localStorage.removeItem('wcfm_draft_settings');
            }
        },

        populateFormWithData: function(data) {
            // This would populate the form with the draft data
            // Implementation depends on the specific form structure
        },

        // Export/Import specific field configurations
        exportFieldConfig: function(fieldKey) {
            var fieldData = this.getFieldData(fieldKey);
            
            var dataStr = JSON.stringify(fieldData, null, 2);
            var dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            var link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = 'wcfm-field-' + fieldKey + '.json';
            link.click();
        },

        importFieldConfig: function(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var fieldData = JSON.parse(e.target.result);
                    WCFM_Admin.applyFieldData(fieldData);
                    WCFM_Admin.showNotice('Field configuration imported successfully!', 'success');
                } catch (error) {
                    WCFM_Admin.showNotice('Invalid field configuration file.', 'error');
                }
            };
            reader.readAsText(file);
        },

        // Field data helpers
        getFieldData: function(fieldKey) {
            var $row = $('.wcfm-field-row[data-field="' + fieldKey + '"]');
            var data = {};
            
            $row.find('input, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if ($field.attr('type') === 'checkbox') {
                    value = $field.is(':checked');
                }
                
                data[name] = value;
            });
            
            return data;
        },

        applyFieldData: function(fieldData) {
            // Apply field data to the form
            for (var name in fieldData) {
                var $field = $('[name="' + name + '"]');
                var value = fieldData[name];
                
                if ($field.attr('type') === 'checkbox') {
                    $field.prop('checked', value);
                } else {
                    $field.val(value);
                }
            }
            
            this.markFormDirty();
        },

        // Field validation rules
        addValidationRule: function(fieldKey, rule, value) {
            // Add validation rule to field configuration
            var $row = $('.wcfm-field-row[data-field="' + fieldKey + '"]');
            var validationData = $row.data('validation') || {};
            
            validationData[rule] = value;
            $row.data('validation', validationData);
            
            this.markFormDirty();
        },

        removeValidationRule: function(fieldKey, rule) {
            // Remove validation rule from field configuration
            var $row = $('.wcfm-field-row[data-field="' + fieldKey + '"]');
            var validationData = $row.data('validation') || {};
            
            delete validationData[rule];
            $row.data('validation', validationData);
            
            this.markFormDirty();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCFM_Admin.init();
        
        // Load any existing draft
        WCFM_Admin.loadDraft();
        
        // Initialize additional features
        WCFM_Admin.initSearch();
        WCFM_Admin.initFieldPreview();
        WCFM_Admin.initConditionalLogic();
        WCFM_Admin.initKeyboardShortcuts();
        WCFM_Admin.initAutoSave();
        
        // Clear draft on successful save
        $(document).on('wcfm_settings_saved', function() {
            localStorage.removeItem('wcfm_draft_settings');
        });
    });

    // Make WCFM_Admin available globally
    window.WCFM_Admin = WCFM_Admin;

})(jQuery);