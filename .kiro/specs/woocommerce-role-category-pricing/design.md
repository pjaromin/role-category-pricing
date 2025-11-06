# Design Document

## Overview

The WooCommerce Role Category Pricing (WRCP) plugin is designed as a WordPress/WooCommerce extension that provides role-based category-specific pricing discounts. The plugin integrates seamlessly with the existing WooCommerce Wholesale Prices plugin by respecting its wholesale customer role and pricing mechanisms while extending functionality to other user roles with category-specific discounts.

## Architecture

### Plugin Structure
```
woocommerce-role-category-pricing/
├── woocommerce-role-category-pricing.php (main plugin file)
├── includes/
│   ├── class-wrcp-bootstrap.php
│   ├── class-wrcp-admin-settings.php
│   ├── class-wrcp-role-manager.php
│   ├── class-wrcp-pricing-engine.php
│   ├── class-wrcp-frontend-display.php
│   └── class-wrcp-cart-integration.php
├── admin/
│   ├── views/
│   │   ├── admin-settings.php
│   │   ├── role-configuration.php
│   │   └── category-discounts.php
│   └── css/
│       └── admin-styles.css
├── assets/
│   ├── css/
│   │   └── frontend-styles.css
│   └── js/
│       └── frontend-scripts.js
└── languages/
    └── woocommerce-role-category-pricing.pot
```

### Integration Strategy with WooCommerce Wholesale Prices

Based on the analysis of the WWP plugin, our integration strategy focuses on:

1. **Hook Priority Management**: WWP uses priority 10 for `woocommerce_get_price_html`. WRCP will use priority 15 to ensure WWP processes first.

2. **Role Detection**: WWP uses `getUserWholesaleRole()` method to detect wholesale customers. WRCP will check for wholesale roles before applying its own logic.

3. **Price Filter Compatibility**: WWP applies filters through `wholesale_price_html_filter()`. WRCP will respect existing wholesale pricing and only modify non-wholesale user pricing.

## Components and Interfaces

### 1. WRCP_Bootstrap Class
**Purpose**: Plugin initialization and dependency management
**Key Methods**:
- `init()`: Initialize plugin hooks and dependencies
- `check_dependencies()`: Verify WooCommerce and WWP compatibility
- `load_textdomain()`: Load translation files

### 2. WRCP_Admin_Settings Class
**Purpose**: WordPress admin interface for plugin configuration
**Key Methods**:
- `add_admin_menu()`: Add settings page to WP admin
- `render_settings_page()`: Display main settings interface
- `save_settings()`: Process and validate form submissions
- `enqueue_admin_assets()`: Load admin CSS/JS

### 3. WRCP_Role_Manager Class
**Purpose**: Manage user roles and role-based configurations
**Key Methods**:
- `get_configurable_roles()`: Return roles available for WRCP configuration
- `is_role_enabled($role)`: Check if role is enabled for WRCP
- `get_role_base_discount($role)`: Get base discount for role
- `add_custom_role($role_name, $capabilities)`: Add new role via plugin
- `remove_custom_role($role_key)`: Remove plugin-created roles only
- `get_user_applicable_roles($user_id)`: Get user's WRCP-enabled roles

### 4. WRCP_Pricing_Engine Class
**Purpose**: Core pricing calculation logic
**Key Methods**:
- `calculate_discount($product_id, $user_roles)`: Calculate final discount percentage
- `get_category_discount($category_id, $role)`: Get category-specific discount
- `walk_category_hierarchy($product_categories, $role)`: Find most specific category discount
- `get_greatest_discount($discounts)`: Return highest discount from multiple roles
- `apply_discount_to_price($price, $discount_percentage)`: Calculate discounted price

### 5. WRCP_Frontend_Display Class
**Purpose**: Handle frontend price display modifications
**Key Methods**:
- `modify_price_html($price_html, $product)`: Main price display filter
- `should_modify_pricing($user_roles)`: Check if user qualifies for WRCP pricing
- `format_role_price_html($original_price, $discounted_price, $role_name)`: Format price display
- `handle_variable_product_pricing($product)`: Special handling for variable products

### 6. WRCP_Cart_Integration Class
**Purpose**: Apply discounts during cart and checkout process
**Key Methods**:
- `apply_cart_discounts($cart)`: Apply discounts to cart items
- `modify_cart_item_price($cart_item)`: Calculate and apply item-level discounts
- `ensure_checkout_pricing($order)`: Maintain pricing through checkout

## Data Models

### Plugin Options Structure
```php
// Main plugin settings
$wrcp_settings = array(
    'enabled_roles' => array(
        'customer' => array(
            'enabled' => true,
            'base_discount' => 10.0,
            'shipping_methods' => array('flat_rate', 'free_shipping'),
            'category_discounts' => array(
                'electronics' => 15.0,
                'clothing' => 20.0,
                'books' => 5.0
            )
        ),
        'subscriber' => array(
            'enabled' => true,
            'base_discount' => 5.0,
            'shipping_methods' => array('flat_rate'),
            'category_discounts' => array(
                'electronics' => 8.0
            )
        )
    ),
    'custom_roles' => array(
        'vip_customer' => array(
            'display_name' => 'VIP Customer',
            'capabilities' => array('read'),
            'created_by_plugin' => true
        )
    ),
    'plugin_version' => '1.0.0'
);
```

### Database Schema
The plugin will use WordPress options table for configuration storage:
- `wrcp_settings`: Main plugin configuration (JSON encoded)
- `wrcp_version`: Plugin version for migration handling
- `wrcp_custom_roles`: Plugin-created roles for cleanup on uninstall

## Error Handling

### Validation Rules
1. **Discount Percentages**: Must be numeric, 0-100 range
2. **Role Names**: Must be valid WordPress role keys (alphanumeric, underscores, hyphens)
3. **Category IDs**: Must exist in WooCommerce product categories
4. **Shipping Methods**: Must be valid WooCommerce shipping method IDs

### Error Recovery
1. **Invalid Discounts**: Default to 0% discount, log warning
2. **Missing Categories**: Skip category-specific discount, use base discount
3. **Role Conflicts**: Use highest available discount
4. **WWP Conflicts**: Defer to WWP pricing for wholesale customers

### Logging Strategy
- Use WooCommerce logger for debugging information
- Log pricing calculations in debug mode
- Track role assignment changes
- Monitor compatibility issues with WWP

## Testing Strategy

### Unit Tests
1. **WRCP_Pricing_Engine**: Test discount calculations, category hierarchy walking
2. **WRCP_Role_Manager**: Test role validation, custom role management
3. **WRCP_Frontend_Display**: Test price HTML formatting, role detection

### Integration Tests
1. **WWP Compatibility**: Verify wholesale customers are unaffected
2. **WooCommerce Integration**: Test with various product types (simple, variable, grouped)
3. **Multi-role Scenarios**: Test users with multiple configured roles
4. **Category Hierarchy**: Test discount inheritance through category trees

### Frontend Tests
1. **Price Display**: Verify correct pricing on shop and product pages
2. **Variable Products**: Test price range updates and variation selection
3. **Cart Integration**: Confirm discounts apply correctly in cart/checkout
4. **Role Switching**: Test pricing updates when user roles change

### Compatibility Tests
1. **Theme Compatibility**: Test with popular WooCommerce themes
2. **Plugin Conflicts**: Test with common WooCommerce extensions
3. **Performance**: Ensure minimal impact on page load times
4. **Caching**: Verify compatibility with caching plugins

## Implementation Considerations

### Hook Priority Strategy
- Use priority 15+ for `woocommerce_get_price_html` to run after WWP (priority 10)
- Use priority 5 for cart hooks to ensure early processing
- Monitor WWP hook changes in future versions

### Performance Optimization
- Cache category hierarchy lookups
- Minimize database queries through role/discount caching
- Use transients for expensive calculations
- Implement lazy loading for admin interfaces

### Security Measures
- Sanitize all user inputs in admin forms
- Validate role permissions before modifications
- Use WordPress nonces for form submissions
- Escape all output data

### Internationalization
- Prepare all user-facing strings for translation
- Support RTL languages in admin interface
- Use WordPress translation functions throughout
- Provide translation template file

### Future Extensibility
- Implement action/filter hooks for third-party extensions
- Design modular architecture for additional discount types
- Support for custom pricing rules via filters
- API endpoints for headless commerce integration