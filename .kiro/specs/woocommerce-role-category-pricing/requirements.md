# Requirements Document

## Introduction

The WooCommerce Role Category Pricing (WRCP) plugin is a WordPress/WooCommerce extension that provides role-based category-specific pricing discounts. The plugin works alongside the WooCommerce Wholesale Prices plugin and native WooCommerce pricing to offer flexible discount structures based on user roles and product categories.

## Glossary

- **WRCP**: WooCommerce Role Category Pricing plugin
- **WP-Admin**: WordPress administration dashboard
- **Role**: WordPress user role (e.g., customer, subscriber, custom roles)
- **Category Hierarchy**: WooCommerce product category tree structure from specific to general
- **Wholesale Customer**: User role managed by WooCommerce Wholesale Prices plugin
- **Variable Product**: WooCommerce product with multiple variations (size, color, etc.)
- **Product Archive**: WooCommerce shop page and category listing pages
- **Single Product Page**: Individual product detail page
- **Base Discount**: Default discount percentage applied to a role across all categories
- **Category Discount**: Specific discount percentage applied to a role for a particular category

## Requirements

### Requirement 1

**User Story:** As a store administrator, I want to configure which user roles can receive category-based pricing discounts, so that I can control which customer segments get special pricing.

#### Acceptance Criteria

1. THE WRCP SHALL provide an admin settings page for role configuration
2. WHEN an administrator accesses the WRCP settings, THE WRCP SHALL display all available WordPress user roles
3. THE WRCP SHALL allow administrators to enable or disable each role for use with the plugin
4. THE WRCP SHALL allow administrators to add new custom roles through the plugin interface
5. THE WRCP SHALL allow administrators to remove only roles that were created by the plugin

### Requirement 2

**User Story:** As a store administrator, I want to assign base discount percentages and shipping methods to each enabled role, so that I can provide role-specific pricing and shipping options.

#### Acceptance Criteria

1. WHERE a role is enabled for WRCP, THE WRCP SHALL allow assignment of an optional base discount percentage
2. WHERE a role is enabled for WRCP, THE WRCP SHALL allow assignment of optional shipping methods
3. THE WRCP SHALL validate that discount percentages are numeric values between 0 and 100
4. THE WRCP SHALL save role configuration settings in the WordPress database
5. THE WRCP SHALL display current settings for each enabled role in the admin interface

### Requirement 3

**User Story:** As a store administrator, I want to assign category-specific discount percentages to each enabled role, so that I can provide targeted pricing for different product categories.

#### Acceptance Criteria

1. WHERE a role is enabled for WRCP, THE WRCP SHALL allow assignment of discount percentages for each WooCommerce product category
2. THE WRCP SHALL display all available product categories in the admin interface
3. THE WRCP SHALL validate that category discount percentages are numeric values between 0 and 100
4. THE WRCP SHALL save category discount settings for each role in the WordPress database
5. THE WRCP SHALL allow administrators to clear category-specific discounts

### Requirement 4

**User Story:** As a customer with a configured role, I want to see my special pricing on product pages, so that I know what price I will pay.

#### Acceptance Criteria

1. WHEN a user with a configured role views a product, THE WRCP SHALL display the regular price crossed out
2. WHEN a user with a configured role views a product, THE WRCP SHALL display the discounted price labeled as "&lt;Role Name&gt; Price:"
3. WHERE a user has the "Wholesale Customer" role, THE WRCP SHALL not modify the product display
4. WHERE a user has a role not configured in WRCP, THE WRCP SHALL not modify the product display
5. THE WRCP SHALL apply this pricing display on both Product Archive pages and Single Product pages

### Requirement 5

**User Story:** As a customer with a configured role, I want to be charged the discounted price at checkout, so that I receive the pricing benefit associated with my role.

#### Acceptance Criteria

1. WHEN a user with a configured role adds a product to cart, THE WRCP SHALL apply the calculated discount to the product price
2. THE WRCP SHALL ensure the discounted price is maintained through the checkout process
3. THE WRCP SHALL apply discounts to all applicable products in the shopping cart
4. THE WRCP SHALL not interfere with WooCommerce Wholesale Prices plugin pricing
5. THE WRCP SHALL calculate the final price after applying the most specific discount available

### Requirement 6

**User Story:** As a customer with a configured role, I want discount calculations to use the most specific category setting available, so that I receive the most relevant pricing for each product.

#### Acceptance Criteria

1. THE WRCP SHALL first check for discount settings in the product's lowest-level category
2. IF no discount is found in the lowest-level category, THE WRCP SHALL walk up the category hierarchy until a discount setting is found
3. IF no category-specific discount is found, THE WRCP SHALL apply the role's base discount percentage
4. IF no base discount is configured, THE WRCP SHALL display the regular price as the role price
5. WHERE a user belongs to multiple configured roles, THE WRCP SHALL apply the greatest discount available

### Requirement 7

**User Story:** As a customer viewing variable products, I want to see discounted price ranges and variation-specific pricing, so that I understand the pricing for different product options.

#### Acceptance Criteria

1. WHEN a user with a configured role views a variable product, THE WRCP SHALL override the price range with discounted values
2. WHEN a user selects a specific variation, THE WRCP SHALL display the discounted price for that variation
3. THE WRCP SHALL maintain the same pricing display format for variable products as simple products
4. THE WRCP SHALL calculate discounts for each variation independently based on the product's categories
5. THE WRCP SHALL ensure variation pricing updates dynamically when selections change

### Requirement 8

**User Story:** As a store administrator, I want the plugin to be compatible with WooCommerce Wholesale Prices, so that both plugins can work together without conflicts.

#### Acceptance Criteria

1. THE WRCP SHALL not modify pricing for users with "Wholesale Customer" role
2. THE WRCP SHALL not interfere with WooCommerce Wholesale Prices plugin functionality
3. THE WRCP SHALL load after WooCommerce Wholesale Prices to avoid hook conflicts
4. THE WRCP SHALL check for WooCommerce Wholesale Prices plugin presence before applying modifications
5. THE WRCP SHALL maintain compatibility with standard WooCommerce pricing hooks and filters