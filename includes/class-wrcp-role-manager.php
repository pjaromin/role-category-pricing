<?php
/**
 * Role Manager class for WooCommerce Role Category Pricing plugin
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages user roles and role-based configurations for WRCP
 */
class WRCP_Role_Manager {
    
    /**
     * Single instance of the class
     *
     * @var WRCP_Role_Manager
     */
    private static $instance = null;
    
    /**
     * Wholesale customer role names to exclude
     *
     * @var array
     */
    private $wholesale_roles = array(
        'wholesale_customer',
        'wwp_wholesale_customer'
    );
    
    /**
     * Bootstrap instance for WWP compatibility
     *
     * @var WRCP_Bootstrap
     */
    private $bootstrap;
    
    /**
     * Get single instance of the class
     *
     * @return WRCP_Role_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->bootstrap = WRCP_Bootstrap::get_instance();
        
        // Initialize hooks if needed
        add_action('init', array($this, 'init'));
        
        // Update wholesale roles list with WWP detected roles
        add_action('init', array($this, 'update_wholesale_roles_list'), 15);
        
        // Ensure hook priority compatibility with WWP
        add_action('wp_loaded', array($this, 'ensure_hook_priority_compatibility'), 20);
    }
    
    /**
     * Initialize the role manager
     */
    public function init() {
        // Hook for role management actions
        add_action('wp_ajax_wrcp_add_custom_role', array($this, 'ajax_add_custom_role'));
        add_action('wp_ajax_wrcp_remove_custom_role', array($this, 'ajax_remove_custom_role'));
    }
    
    /**
     * Update wholesale roles list with WWP detected roles
     */
    public function update_wholesale_roles_list() {
        // Check WWP compatibility first
        $wwp_compatibility = $this->bootstrap->check_wwp_compatibility();
        
        if ($wwp_compatibility['is_active'] && !empty($wwp_compatibility['wholesale_roles'])) {
            // Use detected WWP roles
            $detected_roles = $wwp_compatibility['wholesale_roles'];
            $this->wholesale_roles = array_unique(array_merge($this->wholesale_roles, $detected_roles));
            
            // Cache the detected roles for performance
            set_transient('wrcp_wwp_roles', $detected_roles, HOUR_IN_SECONDS);
        } else {
            // Try to use cached roles if WWP is temporarily unavailable
            $cached_roles = get_transient('wrcp_wwp_roles');
            if ($cached_roles && is_array($cached_roles)) {
                $this->wholesale_roles = array_unique(array_merge($this->wholesale_roles, $cached_roles));
            }
        }
        
        // Log any compatibility issues for debugging
        if (!empty($wwp_compatibility['issues'])) {
            $this->log_wwp_compatibility_issues($wwp_compatibility['issues']);
        }
    }
    
    /**
     * Log WWP compatibility issues for debugging
     *
     * @param array $issues Array of compatibility issues
     */
    private function log_wwp_compatibility_issues($issues) {
        if (function_exists('wc_get_logger') && defined('WP_DEBUG') && WP_DEBUG) {
            $logger = wc_get_logger();
            foreach ($issues as $issue) {
                $logger->debug(
                    sprintf('WWP Compatibility Issue: %s', $issue),
                    array('source' => 'wrcp-wwp-compatibility')
                );
            }
        }
    }
    
    /**
     * Get all configurable roles excluding wholesale customers
     *
     * @return array Array of role objects with key, name, and capabilities
     */
    public function get_configurable_roles() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $all_roles = $wp_roles->get_names();
        $configurable_roles = array();
        
        foreach ($all_roles as $role_key => $role_name) {
            // Skip wholesale customer roles
            if (in_array($role_key, $this->wholesale_roles)) {
                continue;
            }
            
            // Skip administrator role for security
            if ($role_key === 'administrator') {
                continue;
            }
            
            $role_obj = get_role($role_key);
            if ($role_obj) {
                $configurable_roles[$role_key] = array(
                    'key' => $role_key,
                    'name' => translate_user_role($role_name),
                    'capabilities' => $role_obj->capabilities
                );
            }
        }
        
        return $configurable_roles;
    }
    
    /**
     * Check if a role is enabled for WRCP
     *
     * @param string $role Role key to check
     * @return bool True if role is enabled
     */
    public function is_role_enabled($role) {
        if (empty($role)) {
            return false;
        }
        
        $settings = get_option('wrcp_settings', array());
        
        $is_enabled = isset($settings['enabled_roles'][$role]['enabled']) && 
                     $settings['enabled_roles'][$role]['enabled'] === true;
        
        // Allow filtering of role enabled status
        return apply_filters('wrcp_is_role_enabled', $is_enabled, $role);
    }
    
    /**
     * Get base discount percentage for a role
     *
     * @param string $role Role key
     * @return float Base discount percentage (0-100)
     */
    public function get_role_base_discount($role) {
        if (empty($role) || !$this->is_role_enabled($role)) {
            return 0.0;
        }
        
        $settings = get_option('wrcp_settings', array());
        
        if (isset($settings['enabled_roles'][$role]['base_discount'])) {
            return floatval($settings['enabled_roles'][$role]['base_discount']);
        }
        
        return 0.0;
    }
    
    /**
     * Add a new custom role through the plugin
     *
     * @param string $role_name Display name for the role
     * @param array $capabilities Role capabilities (optional)
     * @return string|WP_Error Role key on success, WP_Error on failure
     */
    public function add_custom_role($role_name, $capabilities = array()) {
        if (empty($role_name)) {
            return new WP_Error('empty_role_name', __('Role name cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        // Sanitize role name to create role key
        $role_key = sanitize_key(strtolower(str_replace(' ', '_', $role_name)));
        
        // Check if role already exists
        if (get_role($role_key)) {
            return new WP_Error('role_exists', __('A role with this name already exists.', 'woocommerce-role-category-pricing'));
        }
        
        // Default capabilities for custom roles
        if (empty($capabilities)) {
            $capabilities = array('read' => true);
        }
        
        // Add the role
        $result = add_role($role_key, $role_name, $capabilities);
        
        if (!$result) {
            return new WP_Error('role_creation_failed', __('Failed to create the role.', 'woocommerce-role-category-pricing'));
        }
        
        // Store in plugin settings as custom role
        $settings = get_option('wrcp_settings', array());
        if (!isset($settings['custom_roles'])) {
            $settings['custom_roles'] = array();
        }
        
        $settings['custom_roles'][$role_key] = array(
            'display_name' => $role_name,
            'capabilities' => $capabilities,
            'created_by_plugin' => true
        );
        
        update_option('wrcp_settings', $settings);
        
        return $role_key;
    }
    
    /**
     * Remove a custom role that was created by the plugin
     *
     * @param string $role_key Role key to remove
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function remove_custom_role($role_key) {
        if (empty($role_key)) {
            return new WP_Error('empty_role_key', __('Role key cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        $settings = get_option('wrcp_settings', array());
        
        // Check if this role was created by the plugin
        if (!isset($settings['custom_roles'][$role_key]) || 
            !isset($settings['custom_roles'][$role_key]['created_by_plugin']) ||
            !$settings['custom_roles'][$role_key]['created_by_plugin']) {
            return new WP_Error('not_plugin_role', __('This role was not created by the plugin and cannot be removed.', 'woocommerce-role-category-pricing'));
        }
        
        // Remove the role from WordPress
        remove_role($role_key);
        
        // Remove from plugin settings
        unset($settings['custom_roles'][$role_key]);
        
        // Also remove from enabled roles if it exists
        if (isset($settings['enabled_roles'][$role_key])) {
            unset($settings['enabled_roles'][$role_key]);
        }
        
        update_option('wrcp_settings', $settings);
        
        return true;
    }
    
    /**
     * Get user's applicable roles for WRCP (enabled roles only)
     *
     * @param int $user_id User ID
     * @return array Array of role keys that are enabled for WRCP
     */
    public function get_user_applicable_roles($user_id) {
        if (empty($user_id)) {
            return array();
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return array();
        }
        
        $user_roles = $user->roles;
        $applicable_roles = array();
        
        foreach ($user_roles as $role) {
            // Skip wholesale customer roles
            if (in_array($role, $this->wholesale_roles)) {
                continue;
            }
            
            // Only include if role is enabled for WRCP
            if ($this->is_role_enabled($role)) {
                $applicable_roles[] = $role;
            }
        }
        
        return $applicable_roles;
    }
    
    /**
     * Check if user has wholesale customer role
     *
     * @param int $user_id User ID
     * @return bool True if user has wholesale role
     */
    public function is_wholesale_customer($user_id) {
        if (empty($user_id)) {
            return false;
        }
        
        // Check cache first for performance
        $cache_key = 'wrcp_is_wholesale_' . $user_id;
        $cached_result = wp_cache_get($cache_key, 'wrcp');
        if ($cached_result !== false) {
            return $cached_result === 'yes';
        }
        
        $is_wholesale = false;
        
        // First try WWP's own detection method if available
        if ($this->bootstrap->is_wwp_active()) {
            $wwp_wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($user_id);
            if ($wwp_wholesale_role) {
                // Only consider true wholesale roles, not custom roles like "Educator"
                $true_wholesale_roles = array(
                    'wholesale_customer',
                    'wwp_wholesale_customer',
                    'dealer', // Only if this is truly a wholesale role
                );
                
                // Allow filtering of which roles should be considered wholesale
                $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', $true_wholesale_roles);
                
                if (in_array($wwp_wholesale_role, $true_wholesale_roles)) {
                    $is_wholesale = true;
                }
            }
        }
        
        // Fallback to role-based detection if WWP method didn't find a role
        if (!$is_wholesale) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $user_roles = $user->roles;
                
                // Use the same true wholesale roles list for consistency
                $true_wholesale_roles = array(
                    'wholesale_customer',
                    'wwp_wholesale_customer',
                    'dealer', // Only if this is truly a wholesale role
                );
                
                // Allow filtering of which roles should be considered wholesale
                $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', $true_wholesale_roles);
                
                foreach ($true_wholesale_roles as $wholesale_role) {
                    if (in_array($wholesale_role, $user_roles)) {
                        $is_wholesale = true;
                        break;
                    }
                }
            }
        }
        
        // Cache the result for 5 minutes
        wp_cache_set($cache_key, $is_wholesale ? 'yes' : 'no', 'wrcp', 300);
        
        return $is_wholesale;
    }
    
    /**
     * Clear wholesale customer cache for a user
     *
     * @param int $user_id User ID
     */
    public function clear_wholesale_cache($user_id = null) {
        if ($user_id) {
            $cache_key = 'wrcp_is_wholesale_' . $user_id;
            wp_cache_delete($cache_key, 'wrcp');
        } else {
            // Clear all wholesale caches
            wp_cache_flush_group('wrcp');
        }
    }
    
    /**
     * Get user's wholesale role using WWP methods
     *
     * @param int $user_id User ID
     * @return string|false Wholesale role key or false if not wholesale customer
     */
    public function get_user_wholesale_role($user_id) {
        if (empty($user_id)) {
            return false;
        }
        
        // Check cache first
        $cache_key = 'wrcp_wholesale_role_' . $user_id;
        $cached_role = wp_cache_get($cache_key, 'wrcp');
        if ($cached_role !== false) {
            return $cached_role === 'none' ? false : $cached_role;
        }
        
        $wholesale_role = false;
        
        // Use WWP's detection method if available
        if ($this->bootstrap->is_wwp_active()) {
            $wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($user_id);
        }
        
        // Fallback to role-based detection if WWP method didn't find a role
        if (!$wholesale_role) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $user_roles = $user->roles;
                
                foreach ($this->wholesale_roles as $wholesale_role_key) {
                    if (in_array($wholesale_role_key, $user_roles)) {
                        $wholesale_role = $wholesale_role_key;
                        break;
                    }
                }
            }
        }
        
        // Cache the result
        wp_cache_set($cache_key, $wholesale_role ?: 'none', 'wrcp', 300);
        
        return $wholesale_role;
    }
    
    /**
     * Ensure WRCP hooks run after WWP hooks with proper priority management
     */
    public function ensure_hook_priority_compatibility() {
        // Only run this if WWP is active
        if (!$this->bootstrap->is_wwp_active()) {
            return;
        }
        
        // Get current WWP hook priority
        $wwp_priority = $this->bootstrap->detect_wwp_hook_priority();
        if ($wwp_priority === false) {
            return; // Can't detect WWP priority, use defaults
        }
        
        // Calculate appropriate WRCP priority
        $wrcp_priority = $wwp_priority + 5;
        
        // Re-register WRCP hooks with correct priority if needed
        if (class_exists('WRCP_Frontend_Display')) {
            $frontend_display = WRCP_Frontend_Display::get_instance();
            
            // Remove existing hooks
            remove_filter('woocommerce_get_price_html', array($frontend_display, 'modify_price_html'));
            remove_filter('woocommerce_variable_price_html', array($frontend_display, 'modify_variable_price_html'));
            remove_filter('woocommerce_variation_price_html', array($frontend_display, 'modify_variation_price_html'));
            
            // Re-add with correct priority
            add_filter('woocommerce_get_price_html', array($frontend_display, 'modify_price_html'), $wrcp_priority, 2);
            add_filter('woocommerce_variable_price_html', array($frontend_display, 'modify_variable_price_html'), $wrcp_priority, 2);
            add_filter('woocommerce_variation_price_html', array($frontend_display, 'modify_variation_price_html'), $wrcp_priority, 2);
        }
    }
    
    /**
     * Get all wholesale role keys (including WWP detected ones)
     *
     * @return array Array of wholesale role keys
     */
    public function get_wholesale_role_keys() {
        return $this->wholesale_roles;
    }
    
    /**
     * Get all custom roles created by the plugin
     *
     * @return array Array of custom roles
     */
    public function get_custom_roles() {
        $settings = get_option('wrcp_settings', array());
        
        if (!isset($settings['custom_roles'])) {
            return array();
        }
        
        return $settings['custom_roles'];
    }
    
    /**
     * Validate role key format
     *
     * @param string $role_key Role key to validate
     * @return bool True if valid
     */
    public function is_valid_role_key($role_key) {
        if (empty($role_key)) {
            return false;
        }
        
        // Role key should only contain lowercase letters, numbers, and underscores
        return preg_match('/^[a-z0-9_]+$/', $role_key);
    }
    
    /**
     * AJAX handler for adding custom role
     */
    public function ajax_add_custom_role() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wrcp_admin_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Additional security: Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(__('Invalid request method.', 'woocommerce-role-category-pricing'));
        }
        
        // Sanitize and validate role name
        $role_name = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        
        if (empty($role_name)) {
            wp_send_json_error(__('Role name cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        // Additional validation
        if (strlen($role_name) > 50) {
            wp_send_json_error(__('Role name cannot exceed 50 characters.', 'woocommerce-role-category-pricing'));
        }
        
        if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $role_name)) {
            wp_send_json_error(__('Role name can only contain letters, numbers, spaces, hyphens, and underscores.', 'woocommerce-role-category-pricing'));
        }
        
        $result = $this->add_custom_role($role_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'role_key' => $result,
                'message' => __('Role created successfully.', 'woocommerce-role-category-pricing')
            ));
        }
    }
    
    /**
     * AJAX handler for removing custom role
     */
    public function ajax_remove_custom_role() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wrcp_admin_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Additional security: Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(__('Invalid request method.', 'woocommerce-role-category-pricing'));
        }
        
        // Sanitize and validate role key
        $role_key = isset($_POST['role_key']) ? sanitize_key($_POST['role_key']) : '';
        
        if (empty($role_key)) {
            wp_send_json_error(__('Role key cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        if (!$this->is_valid_role_key($role_key)) {
            wp_send_json_error(__('Invalid role key format.', 'woocommerce-role-category-pricing'));
        }
        
        $result = $this->remove_custom_role($role_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Role removed successfully.', 'woocommerce-role-category-pricing')
            ));
        }
    }
    
    /**
     * Save role configuration settings
     *
     * @param string $role Role key
     * @param array $config Configuration array
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function save_role_configuration($role, $config) {
        if (empty($role)) {
            return new WP_Error('empty_role', __('Role key cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate configuration data
        $validation_result = $this->validate_role_configuration($config);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        $settings = get_option('wrcp_settings', array());
        
        if (!isset($settings['enabled_roles'])) {
            $settings['enabled_roles'] = array();
        }
        
        // Merge with existing configuration
        if (!isset($settings['enabled_roles'][$role])) {
            $settings['enabled_roles'][$role] = array();
        }
        
        $settings['enabled_roles'][$role] = array_merge($settings['enabled_roles'][$role], $config);
        
        return update_option('wrcp_settings', $settings);
    }
    
    /**
     * Get role configuration settings
     *
     * @param string $role Role key
     * @return array Role configuration
     */
    public function get_role_configuration($role) {
        if (empty($role)) {
            return array();
        }
        
        $settings = get_option('wrcp_settings', array());
        
        if (!isset($settings['enabled_roles'][$role])) {
            return array(
                'enabled' => false,
                'base_discount' => 0.0,
                'shipping_methods' => array(),
                'category_discounts' => array()
            );
        }
        
        // Ensure all required keys exist with defaults
        $defaults = array(
            'enabled' => false,
            'base_discount' => 0.0,
            'shipping_methods' => array(),
            'category_discounts' => array()
        );
        
        return array_merge($defaults, $settings['enabled_roles'][$role]);
    }
    
    /**
     * Enable or disable a role for WRCP
     *
     * @param string $role Role key
     * @param bool $enabled Whether to enable the role
     * @return bool Success status
     */
    public function set_role_enabled($role, $enabled) {
        if (empty($role)) {
            return false;
        }
        
        return $this->save_role_configuration($role, array('enabled' => (bool) $enabled));
    }
    
    /**
     * Set base discount percentage for a role
     *
     * @param string $role Role key
     * @param float $discount Discount percentage (0-100)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function set_role_base_discount($role, $discount) {
        if (empty($role)) {
            return new WP_Error('empty_role', __('Role key cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        $discount = floatval($discount);
        
        if ($discount < 0 || $discount > 100) {
            return new WP_Error('invalid_discount', __('Discount percentage must be between 0 and 100.', 'woocommerce-role-category-pricing'));
        }
        
        return $this->save_role_configuration($role, array('base_discount' => $discount));
    }
    
    /**
     * Set shipping methods for a role
     *
     * @param string $role Role key
     * @param array $shipping_methods Array of shipping method IDs
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function set_role_shipping_methods($role, $shipping_methods) {
        if (empty($role)) {
            return new WP_Error('empty_role', __('Role key cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        if (!is_array($shipping_methods)) {
            $shipping_methods = array();
        }
        
        // Validate shipping methods exist
        $validation_result = $this->validate_shipping_methods($shipping_methods);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        return $this->save_role_configuration($role, array('shipping_methods' => $shipping_methods));
    }
    
    /**
     * Set category discount for a role
     *
     * @param string $role Role key
     * @param int $category_id Category ID
     * @param float $discount Discount percentage (0-100)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function set_role_category_discount($role, $category_id, $discount) {
        if (empty($role)) {
            return new WP_Error('empty_role', __('Role key cannot be empty.', 'woocommerce-role-category-pricing'));
        }
        
        $category_id = intval($category_id);
        $discount = floatval($discount);
        
        if ($category_id <= 0) {
            return new WP_Error('invalid_category', __('Invalid category ID.', 'woocommerce-role-category-pricing'));
        }
        
        if ($discount < 0 || $discount > 100) {
            return new WP_Error('invalid_discount', __('Discount percentage must be between 0 and 100.', 'woocommerce-role-category-pricing'));
        }
        
        // Verify category exists
        $category = get_term($category_id, 'product_cat');
        if (!$category || is_wp_error($category)) {
            return new WP_Error('category_not_found', __('Category not found.', 'woocommerce-role-category-pricing'));
        }
        
        $config = $this->get_role_configuration($role);
        $config['category_discounts'][$category_id] = $discount;
        
        return $this->save_role_configuration($role, $config);
    }
    
    /**
     * Get category discount for a role
     *
     * @param string $role Role key
     * @param int $category_id Category ID
     * @return float Discount percentage
     */
    public function get_role_category_discount($role, $category_id) {
        if (empty($role) || empty($category_id)) {
            return 0.0;
        }
        
        $config = $this->get_role_configuration($role);
        
        if (isset($config['category_discounts'][$category_id])) {
            return floatval($config['category_discounts'][$category_id]);
        }
        
        return 0.0;
    }
    
    /**
     * Remove category discount for a role
     *
     * @param string $role Role key
     * @param int $category_id Category ID
     * @return bool Success status
     */
    public function remove_role_category_discount($role, $category_id) {
        if (empty($role) || empty($category_id)) {
            return false;
        }
        
        $config = $this->get_role_configuration($role);
        
        if (isset($config['category_discounts'][$category_id])) {
            unset($config['category_discounts'][$category_id]);
            return $this->save_role_configuration($role, $config);
        }
        
        return true;
    }
    
    /**
     * Get all role configurations
     *
     * @return array All role configurations
     */
    public function get_all_role_configurations() {
        $settings = get_option('wrcp_settings', array());
        
        if (!isset($settings['enabled_roles'])) {
            return array();
        }
        
        return $settings['enabled_roles'];
    }
    
    /**
     * Validate role configuration data
     *
     * @param array $config Configuration array
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_role_configuration($config) {
        if (!is_array($config)) {
            return new WP_Error('invalid_config', __('Configuration must be an array.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate enabled flag
        if (isset($config['enabled']) && !is_bool($config['enabled'])) {
            return new WP_Error('invalid_enabled', __('Enabled flag must be boolean.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate base discount
        if (isset($config['base_discount'])) {
            $discount = floatval($config['base_discount']);
            if ($discount < 0 || $discount > 100) {
                return new WP_Error('invalid_base_discount', __('Base discount must be between 0 and 100.', 'woocommerce-role-category-pricing'));
            }
        }
        
        // Validate shipping methods
        if (isset($config['shipping_methods'])) {
            if (!is_array($config['shipping_methods'])) {
                return new WP_Error('invalid_shipping_methods', __('Shipping methods must be an array.', 'woocommerce-role-category-pricing'));
            }
            
            $validation_result = $this->validate_shipping_methods($config['shipping_methods']);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
        }
        
        // Validate category discounts
        if (isset($config['category_discounts'])) {
            if (!is_array($config['category_discounts'])) {
                return new WP_Error('invalid_category_discounts', __('Category discounts must be an array.', 'woocommerce-role-category-pricing'));
            }
            
            foreach ($config['category_discounts'] as $category_id => $discount) {
                $category_id = intval($category_id);
                $discount = floatval($discount);
                
                if ($category_id <= 0) {
                    return new WP_Error('invalid_category_id', __('Invalid category ID in category discounts.', 'woocommerce-role-category-pricing'));
                }
                
                if ($discount < 0 || $discount > 100) {
                    return new WP_Error('invalid_category_discount', __('Category discount must be between 0 and 100.', 'woocommerce-role-category-pricing'));
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate shipping methods
     *
     * @param array $shipping_methods Array of shipping method IDs
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_shipping_methods($shipping_methods) {
        if (!is_array($shipping_methods)) {
            return new WP_Error('invalid_shipping_methods', __('Shipping methods must be an array.', 'woocommerce-role-category-pricing'));
        }
        
        // If WooCommerce is not available, skip validation
        if (!function_exists('WC')) {
            return true;
        }
        
        $available_methods = array();
        $shipping_zones = WC_Shipping_Zones::get_zones();
        
        // Get methods from all zones
        foreach ($shipping_zones as $zone) {
            if (isset($zone['shipping_methods'])) {
                foreach ($zone['shipping_methods'] as $method) {
                    $available_methods[] = $method->id;
                }
            }
        }
        
        // Also get methods from zone 0 (rest of the world)
        $zone_0 = new WC_Shipping_Zone(0);
        $zone_0_methods = $zone_0->get_shipping_methods();
        foreach ($zone_0_methods as $method) {
            $available_methods[] = $method->id;
        }
        
        // Validate each shipping method
        foreach ($shipping_methods as $method_id) {
            if (!in_array($method_id, $available_methods)) {
                // Allow common shipping method types even if not configured
                $common_methods = array('flat_rate', 'free_shipping', 'local_pickup', 'local_delivery');
                if (!in_array($method_id, $common_methods)) {
                    return new WP_Error('invalid_shipping_method', 
                        sprintf(__('Shipping method "%s" is not available.', 'woocommerce-role-category-pricing'), $method_id));
                }
            }
        }
        
        return true;
    }
    
    /**
     * Reset role configuration to defaults
     *
     * @param string $role Role key
     * @return bool Success status
     */
    public function reset_role_configuration($role) {
        if (empty($role)) {
            return false;
        }
        
        $default_config = array(
            'enabled' => false,
            'base_discount' => 0.0,
            'shipping_methods' => array(),
            'category_discounts' => array()
        );
        
        return $this->save_role_configuration($role, $default_config);
    }
    
    /**
     * Export role configurations
     *
     * @return array All role configurations for export
     */
    public function export_configurations() {
        $settings = get_option('wrcp_settings', array());
        
        // Get additional metadata for the export
        $export_data = array(
            'enabled_roles' => isset($settings['enabled_roles']) ? $settings['enabled_roles'] : array(),
            'custom_roles' => isset($settings['custom_roles']) ? $settings['custom_roles'] : array(),
            'export_date' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'plugin_version' => WRCP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => class_exists('WooCommerce') ? WC()->version : 'not_installed',
            'export_metadata' => array(
                'total_enabled_roles' => count(isset($settings['enabled_roles']) ? $settings['enabled_roles'] : array()),
                'total_custom_roles' => count(isset($settings['custom_roles']) ? $settings['custom_roles'] : array()),
                'site_url' => get_site_url(),
                'export_user' => get_current_user_id()
            )
        );
        
        return $export_data;
    }
    
    /**
     * Import role configurations
     *
     * @param array $import_data Configuration data to import
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function import_configurations($import_data) {
        if (!is_array($import_data)) {
            return new WP_Error('invalid_import_data', __('Import data must be an array.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate import data structure
        $validation_result = $this->validate_import_data($import_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // Create backup before import
        $backup_result = $this->create_settings_backup();
        if (is_wp_error($backup_result)) {
            return new WP_Error('backup_failed', __('Failed to create backup before import.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate each role configuration
        foreach ($import_data['enabled_roles'] as $role => $config) {
            $validation_result = $this->validate_role_configuration($config);
            if (is_wp_error($validation_result)) {
                return new WP_Error('invalid_role_config', 
                    sprintf(__('Invalid configuration for role "%s": %s', 'woocommerce-role-category-pricing'), 
                    $role, $validation_result->get_error_message()));
            }
        }
        
        // Import the configurations
        $settings = get_option('wrcp_settings', array());
        $settings['enabled_roles'] = $import_data['enabled_roles'];
        
        // Import custom roles if present
        if (isset($import_data['custom_roles']) && is_array($import_data['custom_roles'])) {
            $settings['custom_roles'] = $import_data['custom_roles'];
            
            // Create the custom roles in WordPress
            foreach ($import_data['custom_roles'] as $role_key => $role_data) {
                if (isset($role_data['created_by_plugin']) && $role_data['created_by_plugin']) {
                    if (!get_role($role_key)) {
                        add_role($role_key, $role_data['display_name'], $role_data['capabilities']);
                    }
                }
            }
        }
        
        // Update import metadata
        $settings['last_import_date'] = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
        $settings['import_source_version'] = isset($import_data['plugin_version']) ? $import_data['plugin_version'] : 'unknown';
        
        $result = update_option('wrcp_settings', $settings);
        
        if ($result) {
            // Clear any cached data after successful import
            $this->clear_role_cache();
            
            // Log successful import
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->info('WRCP settings imported successfully', array('source' => 'wrcp-import'));
            }
        }
        
        return $result;
    }
    
    /**
     * Validate import data structure
     *
     * @param array $import_data Data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_import_data($import_data) {
        // Check required fields
        if (!isset($import_data['enabled_roles']) || !is_array($import_data['enabled_roles'])) {
            return new WP_Error('invalid_import_structure', __('Import data is missing enabled_roles array.', 'woocommerce-role-category-pricing'));
        }
        
        // Check plugin version compatibility
        if (isset($import_data['plugin_version'])) {
            $import_version = $import_data['plugin_version'];
            $current_version = WRCP_VERSION;
            
            // For now, we'll accept any version, but log warnings for major version differences
            if (version_compare($import_version, $current_version, '>')) {
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->warning(
                        sprintf('Importing from newer plugin version %s to %s', $import_version, $current_version),
                        array('source' => 'wrcp-import')
                    );
                }
            }
        }
        
        // Validate custom roles structure if present
        if (isset($import_data['custom_roles']) && !is_array($import_data['custom_roles'])) {
            return new WP_Error('invalid_custom_roles', __('Custom roles data must be an array.', 'woocommerce-role-category-pricing'));
        }
        
        return true;
    }
    
    /**
     * Create backup of current settings
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function create_settings_backup() {
        $current_settings = get_option('wrcp_settings', array());
        
        if (empty($current_settings)) {
            return true; // No settings to backup
        }
        
        // Add backup metadata
        $backup_data = array(
            'settings' => $current_settings,
            'backup_date' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'plugin_version' => WRCP_VERSION,
            'backup_user' => get_current_user_id()
        );
        
        $result = update_option('wrcp_settings_backup', $backup_data);
        
        if (!$result) {
            return new WP_Error('backup_failed', __('Failed to create settings backup.', 'woocommerce-role-category-pricing'));
        }
        
        return true;
    }
    
    /**
     * Restore settings from backup
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function restore_from_backup() {
        $backup_data = get_option('wrcp_settings_backup', false);
        
        if (!$backup_data || !isset($backup_data['settings'])) {
            return new WP_Error('no_backup', __('No backup found to restore from.', 'woocommerce-role-category-pricing'));
        }
        
        $result = update_option('wrcp_settings', $backup_data['settings']);
        
        if ($result) {
            // Clear cache after restore
            $this->clear_role_cache();
            
            // Log restore
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->info('WRCP settings restored from backup', array('source' => 'wrcp-restore'));
            }
        }
        
        return $result;
    }
    
    /**
     * Get backup information
     *
     * @return array|false Backup info or false if no backup exists
     */
    public function get_backup_info() {
        $backup_data = get_option('wrcp_settings_backup', false);
        
        if (!$backup_data) {
            return false;
        }
        
        return array(
            'backup_date' => isset($backup_data['backup_date']) ? $backup_data['backup_date'] : 'unknown',
            'plugin_version' => isset($backup_data['plugin_version']) ? $backup_data['plugin_version'] : 'unknown',
            'backup_user' => isset($backup_data['backup_user']) ? $backup_data['backup_user'] : 0,
            'has_settings' => !empty($backup_data['settings'])
        );
    }
    
    /**
     * Reset settings to defaults
     *
     * @return bool True on success
     */
    public function reset_to_defaults() {
        // Create backup before reset
        $this->create_settings_backup();
        
        // Default settings
        $default_settings = array(
            'enabled_roles' => array(),
            'custom_roles' => array(),
            'plugin_version' => WRCP_VERSION,
            'reset_date' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'reset_user' => get_current_user_id()
        );
        
        $result = update_option('wrcp_settings', $default_settings);
        
        if ($result) {
            // Clear cache after reset
            $this->clear_role_cache();
            
            // Log reset
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->info('WRCP settings reset to defaults', array('source' => 'wrcp-reset'));
            }
        }
        
        return $result;
    }
    
    /**
     * Clear role-related cache
     */
    private function clear_role_cache() {
        // Clear WordPress object cache
        wp_cache_delete('wrcp_enabled_roles', 'wrcp');
        wp_cache_delete('wrcp_custom_roles', 'wrcp');
        wp_cache_delete('wrcp_all_roles', 'wrcp');
        
        // Clear transients
        delete_transient('wrcp_role_cache');
        delete_transient('wrcp_category_cache');
    }
}