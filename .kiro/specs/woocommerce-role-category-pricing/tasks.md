# Implementation Plan

- [x] 1. Set up plugin structure and core bootstrap
  - Create main plugin file with proper WordPress headers and activation/deactivation hooks
  - Implement WRCP_Bootstrap class with dependency checking and initialization
  - Set up autoloading for plugin classes
  - _Requirements: 8.3, 8.4_

- [x] 2. Implement role management system
  - [x] 2.1 Create WRCP_Role_Manager class with role detection and validation
    - Implement methods to get configurable roles excluding wholesale customers
    - Add role enablement checking and custom role management
    - Create user role retrieval with WRCP filtering
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 2.2 Implement role configuration data structure
    - Design and implement role settings storage in WordPress options
    - Create methods for saving/retrieving role-based discount settings
    - Add validation for discount percentages and shipping method assignments
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [ ]* 2.3 Write unit tests for role management
    - Test role detection logic and custom role creation/deletion
    - Verify role validation and settings persistence
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 3. Create admin settings interface
  - [x] 3.1 Implement WRCP_Admin_Settings class with menu integration
    - Add admin menu item and settings page registration
    - Create settings page rendering with role configuration forms
    - Implement form submission handling and validation
    - _Requirements: 1.1, 2.1, 3.1, 3.2_

  - [x] 3.2 Build role configuration interface
    - Create HTML forms for enabling/disabling roles
    - Add base discount percentage input fields with validation
    - Implement shipping method selection interface
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2_

  - [x] 3.3 Implement category discount configuration
    - Build category selection interface with discount input fields
    - Add category hierarchy display for better user experience
    - Create bulk discount assignment functionality
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 3.4 Add admin interface styling and JavaScript
    - Create CSS for admin forms and layout
    - Add JavaScript for dynamic form interactions
    - _Requirements: 1.1, 2.1, 3.1_

- [x] 4. Develop core pricing calculation engine
  - [x] 4.1 Create WRCP_Pricing_Engine class with discount calculation logic
    - Implement category hierarchy walking algorithm
    - Add most-specific discount detection logic
    - Create multi-role discount comparison (greatest discount wins)
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [x] 4.2 Implement price calculation methods
    - Add discount percentage application to product prices
    - Create variable product price range calculation
    - Implement price validation and error handling
    - _Requirements: 5.1, 5.2, 5.3, 5.5, 7.1, 7.2, 7.4_

  - [ ]* 4.3 Write unit tests for pricing calculations
    - Test category hierarchy walking with various category structures
    - Verify multi-role discount calculations
    - Test edge cases and error conditions
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 5. Implement frontend price display modifications
  - [x] 5.1 Create WRCP_Frontend_Display class with price HTML filtering
    - Hook into woocommerce_get_price_html with proper priority (15)
    - Implement wholesale customer detection and bypass logic
    - Add role-based pricing eligibility checking
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 8.1, 8.2_

  - [x] 5.2 Implement price display formatting
    - Create crossed-out regular price with role-specific discounted price
    - Add proper HTML structure with role name labeling
    - Implement consistent formatting for simple and variable products
    - _Requirements: 4.1, 4.2, 4.5, 7.3_

  - [x] 5.3 Handle variable product price display
    - Override price ranges with discounted values
    - Implement dynamic price updates on variation selection
    - Ensure proper price display for each variation
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ]* 5.4 Add frontend styling for price display
    - Create CSS for crossed-out prices and role labels
    - Ensure responsive design for various themes
    - _Requirements: 4.1, 4.2, 4.5_

- [x] 6. Integrate cart and checkout pricing
  - [x] 6.1 Create WRCP_Cart_Integration class with cart hooks
    - Hook into woocommerce_before_calculate_totals for price application
    - Implement cart item price modification logic
    - Add compatibility with WooCommerce cart sessions
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 6.2 Implement checkout price consistency
    - Ensure discounted prices persist through checkout process
    - Add order item price validation and application
    - Implement proper tax calculation on discounted prices
    - _Requirements: 5.1, 5.2, 5.3, 5.5_

  - [ ]* 6.3 Write integration tests for cart functionality
    - Test cart price calculations with various product types
    - Verify checkout process maintains correct pricing
    - Test compatibility with WooCommerce cart/checkout flows
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 7. Implement WooCommerce Wholesale Prices compatibility
  - [x] 7.1 Add WWP detection and integration logic
    - Check for WWP plugin presence and version compatibility
    - Implement wholesale customer role detection using WWP methods
    - Add hook priority management to avoid conflicts
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [x] 7.2 Create compatibility layer for pricing hooks
    - Ensure WRCP runs after WWP price modifications
    - Add fallback logic for WWP hook changes
    - Implement graceful degradation if WWP is deactivated
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 7.3 Write compatibility tests with WWP
    - Test wholesale customer pricing remains unchanged
    - Verify non-wholesale users get WRCP pricing
    - Test plugin activation/deactivation scenarios
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 8. Add plugin activation and data management
  - [x] 8.1 Implement plugin activation and deactivation hooks
    - Create database table setup and default option initialization
    - Add plugin version checking and migration logic
    - Implement clean uninstall with data removal
    - _Requirements: 1.1, 2.4, 3.4_

  - [x] 8.2 Create settings import/export functionality
    - Add settings backup and restore capabilities
    - Implement configuration validation on import
    - Create settings reset to defaults option
    - _Requirements: 2.4, 3.4_

  - [ ]* 8.3 Add error logging and debugging features
    - Implement WooCommerce logger integration
    - Add debug mode for pricing calculations
    - Create admin notices for configuration issues
    - _Requirements: 1.1, 2.1, 3.1_

- [x] 9. Finalize plugin integration and testing
  - [x] 9.1 Implement security measures and input validation
    - Add nonce verification for all admin forms
    - Sanitize and validate all user inputs
    - Implement proper capability checking for admin functions
    - _Requirements: 1.1, 2.1, 3.1_

  - [x] 9.2 Add internationalization support
    - Prepare all strings for translation using WordPress i18n functions
    - Create translation template file (.pot)
    - Implement text domain loading and RTL support
    - _Requirements: 1.1, 2.1, 3.1_

  - [ ]* 9.3 Create comprehensive integration tests
    - Test complete user workflows from admin configuration to frontend pricing
    - Verify multi-role scenarios and edge cases
    - Test theme and plugin compatibility
    - _Requirements: All requirements_

  - [ ]* 9.4 Add performance optimization
    - Implement caching for category hierarchy lookups
    - Add transient storage for expensive calculations
    - Optimize database queries and reduce redundant operations
    - _Requirements: 4.1, 5.1, 6.1_