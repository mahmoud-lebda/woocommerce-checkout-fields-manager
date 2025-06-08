<?php
/**
 * Custom Field Modal Template
 * 
 * @package WooCommerce_Checkout_Fields_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Custom Field Modal -->
<div id="wcfm-custom-field-modal" class="wcfm-modal" style="display: none;">
    <div class="wcfm-modal-content">
        <div class="wcfm-modal-header">
            <h3><?php _e('Add/Edit Custom Field', WCFM_TEXT_DOMAIN); ?></h3>
            <button type="button" class="wcfm-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="wcfm-modal-body">
            <form id="wcfm-custom-field-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="field_key"><?php _e('Field Key', WCFM_TEXT_DOMAIN); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="field_key" name="field_key" class="regular-text" required />
                            <p class="description"><?php _e('Unique identifier for the field (e.g., custom_field_1). Only lowercase letters, numbers, and underscores allowed.', WCFM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_label"><?php _e('Field Label', WCFM_TEXT_DOMAIN); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="field_label" name="field_label" class="regular-text" required />
                            <p class="description"><?php _e('The label shown to customers during checkout.', WCFM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_type"><?php _e('Field Type', WCFM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="field_type" name="field_type" class="regular-text">
                                <option value="text"><?php _e('Text', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="textarea"><?php _e('Textarea', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="select"><?php _e('Select Dropdown', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="checkbox"><?php _e('Checkbox', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="radio"><?php _e('Radio Buttons', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="email"><?php _e('Email', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="tel"><?php _e('Phone Number', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="number"><?php _e('Number', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="date"><?php _e('Date', WCFM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_section"><?php _e('Field Section', WCFM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="field_section" name="field_section" class="regular-text">
                                <option value="billing"><?php _e('Billing', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="shipping"><?php _e('Shipping', WCFM_TEXT_DOMAIN); ?></option>
                                <option value="additional"><?php _e('Additional', WCFM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_placeholder"><?php _e('Placeholder', WCFM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="field_placeholder" name="field_placeholder" class="regular-text" />
                            <p class="description"><?php _e('Placeholder text shown inside the field.', WCFM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr class="field-options-row" style="display: none;">
                        <th scope="row">
                            <label for="field_options"><?php _e('Options', WCFM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <textarea id="field_options" name="field_options" rows="4" class="large-text"></textarea>
                            <p class="description"><?php _e('One option per line. Format: value|label (e.g., "us|United States"). For simple options, just enter the value.', WCFM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Field Settings', WCFM_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" id="field_required" name="field_required" value="1" />
                                    <?php _e('Make this field required', WCFM_TEXT_DOMAIN); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" id="field_enabled" name="field_enabled" value="1" checked />
                                    <?php _e('Enable this field', WCFM_TEXT_DOMAIN); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="field_priority"><?php _e('Priority', WCFM_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="number" id="field_priority" name="field_priority" value="10" min="1" max="999" class="small-text" />
                            <p class="description"><?php _e('Lower numbers appear first. Default is 10.', WCFM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div id="wcfm-field-preview" class="wcfm-field-preview" style="display: none;">
                    <h4><?php _e('Field Preview', WCFM_TEXT_DOMAIN); ?></h4>
                    <div id="wcfm-preview-content"></div>
                </div>
            </form>
        </div>
        <div class="wcfm-modal-footer">
            <button type="button" id="wcfm-save-custom-field" class="button button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Save Field', WCFM_TEXT_DOMAIN); ?>
            </button>
            <button type="button" class="button wcfm-modal-close">
                <?php _e('Cancel', WCFM_TEXT_DOMAIN); ?>
            </button>
        </div>
    </div>
</div>