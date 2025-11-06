<?php
/**
 * WWP Compatibility Layer for WooCommerce Role Category Pricing plugin
 *
 * Handles integration with WooCommerce Wholesale Prices plugin including
 * hook priority management, fallback logic, and graceful degradation.
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles WWP compatibility and integration
 */
class WRCP_WWP_Compatibility {
    
    /**
     * Single instance of the class
     *
     * @var WRCP_WWP_Compatibility
     */
    private static $instance = null;
    
    /**
     * Bootstrap instance
     *
     * @var WRCP_Bootstrap
     */
    private $bootstrap;
    
    /**
     * Frontend display instance
     *
     * @var WRCP_Frontend_Display
     */
    private $frontend_display;
    
    /**
     * Cart integration instance
     *
     * @var WRCP_Cart_Integration
     */
    private $cart_integration;
    
    /**
     * WWP hook priorities cache
     *
     * @var array
     */
    private $wwp_priorities = array();
    
    /**
     * WRCP hook priorities cache
     *
     * @var array
     */
    private $wrcp_priorities = array();
    
    /**
     * WWP compatibility status
     *
     * @var array
     */
    private $compatibility_status = null;
    
    /**
     * Get single instance of the class
     *
     * @return WRCP_WWP_Compatibility
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
        
        $this->init();
    }
    
    /**
     * Initialize the compatibility layer
     */
    private function init() {
        // Hook into WordPress initialization to set up compatibility
        add_action('init', array($this, 'setup_compatibility'), 20);
        
        // Hook into plugins_loaded to detect WWP changes
        add_action('plugins_loaded', array($this, 'detect_wwp_changes'), 15);
        
        // Monitor WWP activation/deactivation
        add_action('activated_plugin', array($this, 'handle_plugin_activation'), 10, 2);
        add_action('deactivated_plugin', array($this, 'handle_plugin_deactivation'), 10, 2);
        
        // Hook into wp_loaded to ensure proper hook ordering
        add_action('wp_loaded', array($this, 'ensure_hook_ordering'), 25);
        
        // Add admin notices for compatibility issues
        add_action('admin_notices', array($this, 'display_compatibility_notices'));
    }
    
    /**
     * Set up WWP compatibility
     */
    public function setup_compatibility() {
        // Get compatibility status
        $this->compatibility_status = $this->bootstrap->check_wwp_compatibility();
        
        if ($this->compatibility_status['is_active']) {
            $this->setup_wwp_integration();
        } else {
            $this->setup_fallback_mode();
        }
        
        // Cache compatibility status
        set_transient('wrcp_wwp_compatibility_status', $this->compatibility_status, HOUR_IN_SECONDS);
    }
    
    /**
     * Set up WWP integration when WWP is active
     */
    private function setup_wwp_integration() {
        // Detect and cache WWP hook priorities
        $this->detect_and_cache_wwp_priorities();
        
        // Set up WRCP hooks with appropriate priorities
        $this->setup_wrcp_hooks_with_priority();
        
        // Set up compatibility filters
        $this->setup_compatibility_filters();
        
        // Monitor WWP hook changes
        add_action('wp_loaded', array($this, 'monitor_wwp_hook_changes'), 30);
    }
    
    /**
     * Set up fallback mode when WWP is not active
     */
    private function setup_fallback_mode() {
        // Use default priorities for WRCP hooks
        $this->setup_wrcp_hooks_default();
        
        // Set up fallback wholesale role detection
        $this->setup_fallback_wholesale_detection();
    }
    
    /**
     * Detect and cache WWP hook priorities
     */
    private function detect_and_cache_wwp_priorities() {
        $hooks_to_check = array(
            'woocommerce_get_price_html',
            'woocommerce_variable_price_html',
            'woocommerce_variation_price_html',
            'woocommerce_before_calculate_totals'
        );
        
        foreach ($hooks_to_check as $hook) {
            $priority = $this->detect_wwp_priority_for_hook($hook);
            if ($priority !== false) {
                $this->wwp_priorities[$hook] = $priority;
            }
        }
        
        // Cache the detected priorities
        set_transient('wrcp_wwp_priorities', $this->wwp_priorities, HOUR_IN_SECONDS);
    }
    
    /**
     * Detect WWP priority for a specific hook
     *
     * @param string $hook Hook name
     * @return int|false Detected priority or false if not found
     */
    private function detect_wwp_priority_for_hook($hook) {
        global $wp_filter;
        
        if (!isset($wp_filter[$hook])) {
            return false;
        }
        
        $wwp_classes = array(
            'WWP_Wholesale_Price_Wholesale_Page',
            'WWP_Wholesale_Prices',
            'WooCommerceWholeSalePrices',
            'WWP_Wholesale_Price_Product_Page',
            'WWP_Wholesale_Price_Cart'
        );
        
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0])) {
                    $class_name = get_class($callback['function'][0]);
                    if (in_array($class_name, $wwp_classes) || strpos($class_name, 'WWP') !== false) {
                        return intval($priority);
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Set up WRCP hooks with appropriate priorities to run after WWP
     */
    private function setup_wrcp_hooks_with_priority() {
        // Get WRCP component instances
        $this->frontend_display = WRCP_Frontend_Display::get_instance();
        $this->cart_integration = WRCP_Cart_Integration::get_instance();
        
        // Calculate WRCP priorities based on WWP priorities
        $this->calculate_wrcp_priorities();
        
        // Remove existing WRCP hooks
        $this->remove_existing_wrcp_hooks();
        
        // Add WRCP hooks with calculated priorities
        $this->add_wrcp_hooks_with_priority();
    }
    
    /**
     * Calculate appropriate WRCP priorities based on WWP priorities
     */
    private function calculate_wrcp_priorities() {
        $default_buffer = 5;
        
        // Calculate priorities for each hook
        $hooks = array(
            'woocommerce_get_price_html' => 15,
            'woocommerce_variable_price_html' => 15,
            'woocommerce_variation_price_html' => 15,
            'woocommerce_before_calculate_totals' => 15
        );
        
        foreach ($hooks as $hook => $default_priority) {
            if (isset($this->wwp_priorities[$hook])) {
                $this->wrcp_priorities[$hook] = $this->wwp_priorities[$hook] + $default_buffer;
            } else {
                $this->wrcp_priorities[$hook] = $default_priority;
            }
        }
        
        // Cache the calculated priorities
        set_transient('wrcp_calculated_priorities', $this->wrcp_priorities, HOUR_IN_SECONDS);
    }
    
    /**
     * Remove existing WRCP hooks to avoid conflicts
     */
    private function remove_existing_wrcp_hooks() {
        if ($this->frontend_display) {
            remove_filter('woocommerce_get_price_html', array($this->frontend_display, 'modify_price_html'));
            remove_filter('woocommerce_variable_price_html', array($this->frontend_display, 'modify_variable_price_html'));
            remove_filter('woocommerce_variation_price_html', array($this->frontend_display, 'modify_variation_price_html'));
        }
        
        if ($this->cart_integration) {
            remove_action('woocommerce_before_calculate_totals', array($this->cart_integration, 'apply_cart_discounts'));
        }
    }
    
    /**
     * Add WRCP hooks with calculated priorities
     */
    private function add_wrcp_hooks_with_priority() {
        if ($this->frontend_display) {
            add_filter('woocommerce_get_price_html', 
                array($this->frontend_display, 'modify_price_html'), 
                $this->wrcp_priorities['woocommerce_get_price_html'], 2);
                
            add_filter('woocommerce_variable_price_html', 
                array($this->frontend_display, 'modify_variable_price_html'), 
                $this->wrcp_priorities['woocommerce_variable_price_html'], 2);
                
            add_filter('woocommerce_variation_price_html', 
                array($this->frontend_display, 'modify_variation_price_html'), 
                $this->wrcp_priorities['woocommerce_variation_price_html'], 2);
        }
        
        if ($this->cart_integration) {
            add_action('woocommerce_before_calculate_totals', 
                array($this->cart_integration, 'apply_cart_discounts'), 
                $this->wrcp_priorities['woocommerce_before_calculate_totals']);
        }
    }
    
    /**
     * Set up WRCP hooks with default priorities when WWP is not active
     */
    private function setup_wrcp_hooks_default() {
        $this->frontend_display = WRCP_Frontend_Display::get_instance();
        $this->cart_integration = WRCP_Cart_Integration::get_instance();
        
        // Use default priorities
        $this->wrcp_priorities = array(
            'woocommerce_get_price_html' => 10,
            'woocommerce_variable_price_html' => 10,
            'woocommerce_variation_price_html' => 10,
            'woocommerce_before_calculate_totals' => 10
        );
        
        $this->add_wrcp_hooks_with_priority();
    }
    
    /**
     * Set up compatibility filters to prevent conflicts
     */
    private function setup_compatibility_filters() {
        // Filter to bypass WRCP for wholesale customers
        add_filter('wrcp_should_modify_pricing', array($this, 'bypass_for_wholesale_customers'), 10, 2);
        
        // Filter to ensure WWP pricing takes precedence
        add_filter('wrcp_calculate_discount', array($this, 'respect_wwp_pricing'), 10, 4);
        
        // Filter to handle WWP price modifications
        add_filter('woocommerce_get_price_html', array($this, 'ensure_wwp_precedence'), 5, 2);
    }
    
    /**
     * Bypass WRCP pricing for wholesale customers
     *
     * @param bool $should_modify Whether WRCP should modify pricing
     * @param int $user_id User ID
     * @return bool Modified decision
     */
    public function bypass_for_wholesale_customers($should_modify, $user_id) {
        if (!$should_modify) {
            return false;
        }
        
        // Check if user is wholesale customer using WWP methods
        if ($this->bootstrap->is_wwp_active()) {
            $wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($user_id);
            if ($wholesale_role) {
                // Only bypass WRCP for actual wholesale roles, not custom roles like "Educator"
                $true_wholesale_roles = array(
                    'wholesale_customer',
                    'wwp_wholesale_customer',
                    'dealer', // Only if this is truly a wholesale role
                );
                
                // Allow filtering of which roles should bypass WRCP
                $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', $true_wholesale_roles);
                
                if (in_array($wholesale_role, $true_wholesale_roles)) {
                    return false; // Bypass WRCP for true wholesale customers only
                }
            }
        }
        
        return $should_modify;
    }
    
    /**
     * Respect WWP pricing when calculating WRCP discounts
     *
     * @param float $discount Calculated discount
     * @param int $product_id Product ID
     * @param array $user_roles User roles
     * @param float $original_price Original price
     * @return float Modified discount
     */
    public function respect_wwp_pricing($discount, $product_id, $user_roles, $original_price) {
        if (!$this->bootstrap->is_wwp_active()) {
            return $discount;
        }
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return $discount;
        }
        
        // If user has true wholesale role, don't apply WRCP discount
        $wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($current_user_id);
        if ($wholesale_role) {
            // Only bypass WRCP for actual wholesale roles, not custom roles like "Educator"
            $true_wholesale_roles = array(
                'wholesale_customer',
                'wwp_wholesale_customer',
                'dealer', // Only if this is truly a wholesale role
            );
            
            // Allow filtering of which roles should bypass WRCP
            $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', $true_wholesale_roles);
            
            if (in_array($wholesale_role, $true_wholesale_roles)) {
                return 0; // No WRCP discount for true wholesale customers only
            }
        }
        
        return $discount;
    }
    
    /**
     * Ensure WWP pricing takes precedence over WRCP
     *
     * @param string $price_html Price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    public function ensure_wwp_precedence($price_html, $product) {
        if (!$this->bootstrap->is_wwp_active() || !is_user_logged_in()) {
            return $price_html;
        }
        
        $current_user_id = get_current_user_id();
        $wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($current_user_id);
        
        if ($wholesale_role) {
            // Only let WWP handle pricing for true wholesale customers
            $true_wholesale_roles = array(
                'wholesale_customer',
                'wwp_wholesale_customer',
                'dealer', // Only if this is truly a wholesale role
            );
            
            // Allow filtering of which roles should bypass WRCP
            $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', $true_wholesale_roles);
            
            if (in_array($wholesale_role, $true_wholesale_roles)) {
                // Let WWP handle the pricing for true wholesale customers
                // Remove any WRCP modifications that might have been applied
                remove_filter('woocommerce_get_price_html', array($this->frontend_display, 'modify_price_html'));
            }
        }
        
        return $price_html;
    }
    
    /**
     * Set up fallback wholesale role detection when WWP is not active
     */
    private function setup_fallback_wholesale_detection() {
        // Use common wholesale role names as fallback
        $fallback_roles = array('wholesale_customer', 'wwp_wholesale_customer');
        
        // Store fallback roles for role manager
        set_transient('wrcp_fallback_wholesale_roles', $fallback_roles, DAY_IN_SECONDS);
    }
    
    /**
     * Monitor WWP hook changes and adjust WRCP accordingly
     */
    public function monitor_wwp_hook_changes() {
        if (!$this->bootstrap->is_wwp_active()) {
            return;
        }
        
        // Check if WWP priorities have changed
        $current_priorities = array();
        $hooks_to_check = array_keys($this->wwp_priorities);
        
        foreach ($hooks_to_check as $hook) {
            $priority = $this->detect_wwp_priority_for_hook($hook);
            if ($priority !== false) {
                $current_priorities[$hook] = $priority;
            }
        }
        
        // Compare with cached priorities
        if ($current_priorities !== $this->wwp_priorities) {
            // WWP priorities have changed, update WRCP hooks
            $this->wwp_priorities = $current_priorities;
            $this->calculate_wrcp_priorities();
            $this->remove_existing_wrcp_hooks();
            $this->add_wrcp_hooks_with_priority();
            
            // Update cache
            set_transient('wrcp_wwp_priorities', $this->wwp_priorities, HOUR_IN_SECONDS);
            set_transient('wrcp_calculated_priorities', $this->wrcp_priorities, HOUR_IN_SECONDS);
            
            // Log the change
            $this->log_priority_change('WWP hook priorities changed, WRCP hooks updated');
        }
    }
    
    /**
     * Detect WWP plugin changes (activation, deactivation, updates)
     */
    public function detect_wwp_changes() {
        $current_wwp_status = $this->bootstrap->is_wwp_active();
        $previous_wwp_status = get_transient('wrcp_wwp_previous_status');
        
        if ($previous_wwp_status === false) {
            // First time checking, store current status
            set_transient('wrcp_wwp_previous_status', $current_wwp_status, DAY_IN_SECONDS);
            return;
        }
        
        if ($current_wwp_status !== $previous_wwp_status) {
            // WWP status changed
            if ($current_wwp_status) {
                $this->handle_wwp_activation();
            } else {
                $this->handle_wwp_deactivation();
            }
            
            // Update stored status
            set_transient('wrcp_wwp_previous_status', $current_wwp_status, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Handle WWP plugin activation
     */
    private function handle_wwp_activation() {
        // Clear compatibility caches
        $this->clear_compatibility_caches();
        
        // Re-setup compatibility
        $this->setup_compatibility();
        
        // Log the activation
        $this->log_priority_change('WWP activated, WRCP compatibility re-initialized');
        
        // Set admin notice
        set_transient('wrcp_wwp_activated_notice', true, HOUR_IN_SECONDS);
    }
    
    /**
     * Handle WWP plugin deactivation
     */
    private function handle_wwp_deactivation() {
        // Switch to fallback mode
        $this->setup_fallback_mode();
        
        // Clear WWP-related caches
        $this->clear_compatibility_caches();
        
        // Log the deactivation
        $this->log_priority_change('WWP deactivated, WRCP switched to fallback mode');
        
        // Set admin notice
        set_transient('wrcp_wwp_deactivated_notice', true, HOUR_IN_SECONDS);
    }
    
    /**
     * Handle plugin activation events
     *
     * @param string $plugin Plugin file
     * @param bool $network_wide Network activation
     */
    public function handle_plugin_activation($plugin, $network_wide) {
        if (strpos($plugin, 'woocommerce-wholesale-prices') !== false) {
            // WWP was activated
            add_action('wp_loaded', array($this, 'handle_wwp_activation'), 5);
        }
    }
    
    /**
     * Handle plugin deactivation events
     *
     * @param string $plugin Plugin file
     */
    public function handle_plugin_deactivation($plugin) {
        if (strpos($plugin, 'woocommerce-wholesale-prices') !== false) {
            // WWP was deactivated
            add_action('wp_loaded', array($this, 'handle_wwp_deactivation'), 5);
        }
    }
    
    /**
     * Ensure proper hook ordering between WWP and WRCP
     */
    public function ensure_hook_ordering() {
        if (!$this->bootstrap->is_wwp_active()) {
            return;
        }
        
        // Get current hook priorities
        $cached_priorities = get_transient('wrcp_calculated_priorities');
        if ($cached_priorities) {
            $this->wrcp_priorities = $cached_priorities;
        }
        
        // Verify hook ordering is correct
        $this->verify_hook_ordering();
    }
    
    /**
     * Verify that WRCP hooks are running after WWP hooks
     */
    private function verify_hook_ordering() {
        global $wp_filter;
        
        $hooks_to_verify = array('woocommerce_get_price_html');
        
        foreach ($hooks_to_verify as $hook) {
            if (!isset($wp_filter[$hook])) {
                continue;
            }
            
            $wwp_priority = $this->detect_wwp_priority_for_hook($hook);
            $wrcp_priority = isset($this->wrcp_priorities[$hook]) ? $this->wrcp_priorities[$hook] : false;
            
            if ($wwp_priority !== false && $wrcp_priority !== false && $wrcp_priority <= $wwp_priority) {
                // WRCP is running before or at the same priority as WWP, fix this
                $this->fix_hook_ordering($hook, $wwp_priority);
            }
        }
    }
    
    /**
     * Fix hook ordering for a specific hook
     *
     * @param string $hook Hook name
     * @param int $wwp_priority WWP priority
     */
    private function fix_hook_ordering($hook, $wwp_priority) {
        $new_priority = $wwp_priority + 5;
        
        // Update WRCP priority
        $this->wrcp_priorities[$hook] = $new_priority;
        
        // Re-register WRCP hook with new priority
        if ($hook === 'woocommerce_get_price_html' && $this->frontend_display) {
            remove_filter($hook, array($this->frontend_display, 'modify_price_html'));
            add_filter($hook, array($this->frontend_display, 'modify_price_html'), $new_priority, 2);
        }
        
        // Log the fix
        $this->log_priority_change(sprintf('Fixed hook ordering for %s: WWP=%d, WRCP=%d', $hook, $wwp_priority, $new_priority));
    }
    
    /**
     * Clear compatibility-related caches
     */
    private function clear_compatibility_caches() {
        delete_transient('wrcp_wwp_compatibility_status');
        delete_transient('wrcp_wwp_priorities');
        delete_transient('wrcp_calculated_priorities');
        delete_transient('wrcp_fallback_wholesale_roles');
        
        // Clear object caches
        wp_cache_delete_group('wrcp');
    }
    
    /**
     * Display compatibility notices in admin
     */
    public function display_compatibility_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // WWP activation notice
        if (get_transient('wrcp_wwp_activated_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('WooCommerce Wholesale Prices detected. WRCP compatibility mode activated.', 'woocommerce-role-category-pricing') . '</p>';
            echo '</div>';
            delete_transient('wrcp_wwp_activated_notice');
        }
        
        // WWP deactivation notice
        if (get_transient('wrcp_wwp_deactivated_notice')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . esc_html__('WooCommerce Wholesale Prices deactivated. WRCP switched to fallback mode.', 'woocommerce-role-category-pricing') . '</p>';
            echo '</div>';
            delete_transient('wrcp_wwp_deactivated_notice');
        }
        
        // Compatibility issues notice
        if ($this->compatibility_status && !empty($this->compatibility_status['issues'])) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('WRCP WWP Compatibility Issues:', 'woocommerce-role-category-pricing') . '</strong></p>';
            echo '<ul>';
            foreach ($this->compatibility_status['issues'] as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        // Debug notice for development (only show if WP_DEBUG is enabled)
        if (defined('WP_DEBUG') && WP_DEBUG && is_user_logged_in()) {
            $debug_info = $this->get_user_debug_info();
            if (isset($_GET['wrcp_debug']) && $_GET['wrcp_debug'] === '1') {
                echo '<div class="notice notice-info">';
                echo '<p><strong>WRCP Debug Info:</strong></p>';
                echo '<pre>' . esc_html(print_r($debug_info, true)) . '</pre>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Log priority changes and compatibility events
     *
     * @param string $message Log message
     */
    private function log_priority_change($message) {
        if (function_exists('wc_get_logger') && defined('WP_DEBUG') && WP_DEBUG) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'wrcp-wwp-compatibility'));
        }
    }
    
    /**
     * Get current compatibility status
     *
     * @return array Compatibility status
     */
    public function get_compatibility_status() {
        if ($this->compatibility_status === null) {
            $this->compatibility_status = $this->bootstrap->check_wwp_compatibility();
        }
        
        return $this->compatibility_status;
    }
    
    /**
     * Get current WWP priorities
     *
     * @return array WWP priorities
     */
    public function get_wwp_priorities() {
        return $this->wwp_priorities;
    }
    
    /**
     * Get current WRCP priorities
     *
     * @return array WRCP priorities
     */
    public function get_wrcp_priorities() {
        return $this->wrcp_priorities;
    }
    
    /**
     * Force refresh of compatibility status and priorities
     */
    public function refresh_compatibility() {
        $this->clear_compatibility_caches();
        $this->compatibility_status = null;
        $this->wwp_priorities = array();
        $this->wrcp_priorities = array();
        
        // Clear role manager wholesale caches
        if (class_exists('WRCP_Role_Manager')) {
            $role_manager = WRCP_Role_Manager::get_instance();
            $role_manager->clear_wholesale_cache();
        }
        
        $this->setup_compatibility();
    }
    
    /**
     * Check if WRCP should run for current user context
     *
     * @return bool True if WRCP should run
     */
    public function should_wrcp_run() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WRCP Compatibility: Checking should_wrcp_run for user ' . $current_user_id);
        }
        
        // Check if user is true wholesale customer
        if ($this->bootstrap->is_wwp_active()) {
            $wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($current_user_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WRCP Compatibility: WWP detected role: ' . ($wholesale_role ? $wholesale_role : 'none'));
            }
            
            if ($wholesale_role) {
                // Only bypass WRCP for actual wholesale roles, not custom roles like "Educator"
                $true_wholesale_roles = array(
                    'wholesale_customer',
                    'wwp_wholesale_customer',
                    'dealer', // Only if this is truly a wholesale role
                );
                
                // Allow filtering of which roles should bypass WRCP
                $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', $true_wholesale_roles);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WRCP Compatibility: True wholesale roles: ' . implode(', ', $true_wholesale_roles));
                    error_log('WRCP Compatibility: Is ' . $wholesale_role . ' in true wholesale roles? ' . (in_array($wholesale_role, $true_wholesale_roles) ? 'yes' : 'no'));
                }
                
                if (in_array($wholesale_role, $true_wholesale_roles)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('WRCP Compatibility: Bypassing WRCP for true wholesale role: ' . $wholesale_role);
                    }
                    return false; // Don't run WRCP for true wholesale customers only
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WRCP Compatibility: WRCP should run - returning true');
        }
        
        return true;
    }
    
    /**
     * Get debug information about current user's wholesale status
     *
     * @return array Debug information
     */
    public function get_user_debug_info() {
        if (!is_user_logged_in()) {
            return array('error' => 'User not logged in');
        }
        
        $current_user_id = get_current_user_id();
        $user = get_user_by('id', $current_user_id);
        
        $debug_info = array(
            'user_id' => $current_user_id,
            'user_roles' => $user ? $user->roles : array(),
            'wwp_active' => $this->bootstrap->is_wwp_active(),
            'wwp_detected_role' => false,
            'is_true_wholesale' => false,
            'should_wrcp_run' => $this->should_wrcp_run(),
        );
        
        if ($this->bootstrap->is_wwp_active()) {
            $wholesale_role = $this->bootstrap->get_user_wwp_wholesale_role($current_user_id);
            $debug_info['wwp_detected_role'] = $wholesale_role;
            
            if ($wholesale_role) {
                $true_wholesale_roles = apply_filters('wrcp_true_wholesale_roles', array(
                    'wholesale_customer',
                    'wwp_wholesale_customer',
                    'dealer',
                ));
                
                $debug_info['true_wholesale_roles'] = $true_wholesale_roles;
                $debug_info['is_true_wholesale'] = in_array($wholesale_role, $true_wholesale_roles);
            }
        }
        
        return $debug_info;
    }
}