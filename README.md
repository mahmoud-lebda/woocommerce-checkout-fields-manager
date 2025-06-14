# WooCommerce Checkout Fields Manager

A comprehensive WordPress plugin for managing WooCommerce checkout fields with full support for the new Block-based Checkout system.

## Developer
**Smartify Solutions** - [https://smartifysolutions.com/](https://smartifysolutions.com/)

## Key Features

### 🎯 Complete Field Control
- Show/hide any checkout field
- Make fields required or optional
- Drag and drop field reordering
- Custom field labels and placeholders

### 📱 Full Interface Support
- **New Block Checkout**: Complete support for WooCommerce Blocks
- **Classic Checkout**: Compatible with traditional checkout
- **Responsive Design**: Optimized for mobile and tablets

### 🛍️ Product Type Rules
- Hide shipping fields for virtual products
- Hide billing addresses for downloadable products
- Custom rules based on product types

### ✨ Advanced Custom Fields
- Create new fields (text, textarea, dropdown, radio, checkbox)
- Advanced data validation
- Conditional logic for show/hide fields
- Support for tooltips and descriptions

## System Requirements

- WordPress 5.0 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin panel
3. Navigate to **WooCommerce > Checkout Fields** to start configuration

## File Structure

```
woocommerce-checkout-fields-manager/
├── woocommerce-checkout-fields-manager.php    # Main plugin file
├── README.md                                   # Documentation
├── includes/                                   # Core files
│   ├── class-wcfm-core.php                   # Core functions
│   ├── class-wcfm-fields-handler.php         # Field handler
│   └── class-wcfm-block-integration.php      # Block integration
├── admin/                                      # Admin files
│   ├── class-wcfm-admin.php                  # Admin interface
│   └── class-wcfm-settings.php               # Settings management
├── frontend/                                   # Frontend files
│   └── class-wcfm-frontend.php               # Frontend functions
├── assets/                                     # CSS & JS files
│   ├── css/
│   │   ├── admin.css                         # Admin styles
│   │   └── frontend.css                      # Frontend styles
│   ├── js/
│   │   ├── admin.js                          # Admin scripts
│   │   ├── frontend.js                       # Frontend scripts
│   │   ├── blocks-integration.js             # Block integration
│   │   ├── blocks-editor.js                  # Block editor
│   │   └── blocks-frontend.js                # Block frontend
│   └── images/
│       └── logo.png                          # Smartify Solutions logo
├── languages/                                  # Translation files
│   ├── woo-checkout-fields-manager.pot
│   ├── woo-checkout-fields-manager-ar.po
│   └── woo-checkout-fields-manager-ar.mo
└── templates/                                  # Template files
    ├── admin/
    │   ├── settings-page.php
    │   ├── field-row.php
    │   └── custom-field-modal.php
    └── frontend/
        ├── custom-field.php
        └── field-group.php
```

## User Guide

### 1. Managing Billing Fields

Navigate to **WooCommerce > Checkout Fields > Billing Fields**:

- **Enable/Disable**: Use toggle switches to show or hide fields
- **Required/Optional**: Determine if field is mandatory
- **Priority**: Reorder fields using drag and drop
- **Label**: Customize field text displayed to customers

### 2. Managing Shipping Fields

Same options as billing fields but for shipping address:
- All address fields are customizable
- Option to hide shipping fields completely for virtual products

### 3. Additional Fields

Customize additional fields such as:
- Order notes
- Any other fields added to the order

### 4. Product Type Rules

#### For Virtual Products:
- ✅ Automatically hide shipping fields
- ✅ Hide billing address (optional)

#### For Downloadable Products:
- ✅ Automatically hide shipping fields
- ✅ Hide billing address (optional)

### 5. Creating Custom Fields

1. Go to **Custom Fields** tab
2. Click **Add Custom Field**
3. Choose:
   - **Field Key**: Unique identifier for the field
   - **Label**: Display text
   - **Type**: Field type (text, email, number, date, etc.)
   - **Section**: Section (Billing, Shipping, Additional)
   - **Required**: Is the field mandatory?

#### Supported Field Types:
- **Text**: Standard text input
- **Textarea**: Multi-line text area
- **Email**: Email with validation
- **Phone**: Phone number
- **Number**: Numeric input
- **Date**: Date picker
- **Select**: Dropdown list
- **Radio**: Radio buttons
- **Checkbox**: Checkboxes

## Advanced Features

### Data Validation
- Automatic email validation
- Phone number validation
- Custom rules for min/max text length
- Custom patterns (Regular Expressions)

### Conditional Logic
Show/hide fields based on other field values:
```javascript
// Example: Show "delivery date" field only when "express delivery" is selected
if (shipping_method === 'express_delivery') {
    show_field('delivery_date');
}
```

### Export/Import Settings
- **Export**: Save all settings to JSON file
- **Import**: Restore settings from previously saved file

## Developer Customization

### Available Hooks

#### Actions:
```php
// Execute code after settings are saved
do_action('wcfm_settings_saved', $settings);

// Execute code after order meta is saved
do_action('wcfm_order_meta_saved', $order_id, $custom_fields);
```

#### Filters:
```php
// Modify field settings
$settings = apply_filters('wcfm_field_settings', $settings);

// Modify validation data
$validation = apply_filters('wcfm_field_validation', $validation, $field_key);

// Modify checkout fields
$fields = apply_filters('wcfm_checkout_fields', $fields);
```

### Adding Custom Fields Programmatically

```php
// Add a new field
function add_custom_checkout_field($fields) {
    $fields['billing']['billing_custom_field'] = array(
        'type' => 'text',
        'label' => 'Custom Field',
        'required' => true,
        'priority' => 25
    );
    return $fields;
}
add_filter('wcfm_checkout_fields', 'add_custom_checkout_field');
```

### CSS Customization

```css
/* Customize custom field appearance */
.wcfm-custom-field {
    margin-bottom: 20px;
}

.wcfm-custom-field.error input {
    border-color: #e74c3c;
}

.wcfm-custom-field.valid input {
    border-color: #27ae60;
}
```

## Performance & Security

### Security
- ✅ All inputs are sanitized
- ✅ User permission checks
- ✅ CSRF protection
- ✅ Sensitive data encryption

### Performance
- ✅ Load files only when needed
- ✅ CSS & JavaScript compression
- ✅ Settings caching
- ✅ Optimized database queries

## Technical Support

For technical support:
- 📧 Email: support@smartifysolutions.com
- 🌐 Website: [https://smartifysolutions.com/](https://smartifysolutions.com/)
- 📞 Phone: Available through official website

## Updates

### Version 1.0.0
- Initial release with all core features
- New Block Checkout support
- Comprehensive admin interface
- Advanced custom fields

## License

This plugin is licensed under GPL v2 or later.

## Contributing

We welcome contributions to improve the plugin:
1. Fork the project
2. Create a new branch
3. Submit improvements
4. Send Pull Request

## API Documentation

### Core Classes

#### `WCFM_Core`
Main plugin class handling core functionality.

```php
// Get plugin settings
$settings = WCFM_Core::get_settings();

// Check if field is enabled
$is_enabled = WCFM_Core::is_field_enabled('billing', 'billing_phone');

// Get field priority
$priority = WCFM_Core::get_field_priority('billing', 'billing_first_name');
```

#### `WCFM_Fields_Handler`
Handles field processing and validation.

```php
// Customize checkout fields
add_filter('woocommerce_checkout_fields', array($handler, 'customize_checkout_fields'), 20);
```

#### `WCFM_Admin`
Manages admin interface and settings.

```php
// Get admin page URL
$url = WCFM_Admin::get_admin_url('billing');
```

### Custom Field Creation

```php
// Create a custom field programmatically
function create_custom_field() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wcfm_custom_fields';
    
    $wpdb->insert(
        $table_name,
        array(
            'field_key' => 'custom_field_example',
            'field_type' => 'text',
            'field_section' => 'billing',
            'field_label' => 'Example Field',
            'field_enabled' => 1,
            'field_required' => 0,
            'field_priority' => 25,
        )
    );
}
```

### Validation Rules

```php
// Add custom validation rule
function add_custom_validation_rule($field_key, $rule, $value) {
    $validation_rules = get_option('wcfm_validation_rules', array());
    $validation_rules[$field_key][$rule] = $value;
    update_option('wcfm_validation_rules', $validation_rules);
}

// Example: Add minimum length validation
add_custom_validation_rule('billing_company', 'minlength', 3);
```

### Conditional Logic

```php
// Set up conditional logic
function setup_conditional_logic() {
    $rules = array(
        'billing_custom_field' => array(
            array(
                'field' => 'billing_country',
                'operator' => 'equals',
                'value' => 'US',
                'action' => 'show'
            )
        )
    );
    
    wp_localize_script('wcfm-frontend', 'wcfm_conditional_rules', $rules);
}
```

## Troubleshooting

### Common Issues

**Fields not showing:**
1. Check if field is enabled in settings
2. Clear caching plugins
3. Check for theme conflicts
4. Verify WooCommerce version compatibility

**Block checkout issues:**
1. Ensure WooCommerce Blocks is installed
2. Check if checkout page is using blocks
3. Clear block cache

**Custom fields not saving:**
1. Check database permissions
2. Verify field key uniqueness
3. Check for plugin conflicts

### Debug Mode

Enable debug mode by adding this to your `wp-config.php`:

```php
define('WCFM_DEBUG', true);
```

This will log all plugin activities to the debug log.

### Database Tables

The plugin creates these database tables:

```sql
-- Custom fields storage
CREATE TABLE wp_wcfm_custom_fields (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    field_key varchar(100) NOT NULL,
    field_type varchar(50) NOT NULL,
    field_section varchar(50) NOT NULL,
    field_label varchar(255) NOT NULL,
    field_placeholder varchar(255),
    field_options text,
    field_enabled tinyint(1) DEFAULT 1,
    field_required tinyint(1) DEFAULT 0,
    field_priority int(11) DEFAULT 10,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY field_key (field_key)
);
```

---

**Developed by [Smartify Solutions](https://smartifysolutions.com/) 🚀**