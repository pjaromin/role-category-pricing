<?php
/**
 * Admin Settings class for WooCommerce Role Category Pricing plugin
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin settings interface and form processing for WRCP
 */
class WRCP_Admin_Settings {
    
    /**
     * Single instance of the class
     *
     * @var WRCP_Admin_Settings
     */
    private static $instance = null;
    
    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'wrcp-settings';
    
    /**
     * Settings page hook suffix
     *
     * @var string
     */
    private $page_hook = '';
    
    /**
     * Role manager instance
     *
     * @var WRCP_Role_Manager
     */
    private $role_manager;
    
    /**
     * Get single instance of the class
     *
     * @return WRCP_Admin_Settings
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
        $this->role_manager = WRCP_Role_Manager::get_instance();
        $this->init();
    }
    
    /**
     * Initialize admin settings
     */
    private function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Handle form submissions
        add_action('admin_post_wrcp_save_settings', array($this, 'handle_settings_save'));
        add_action('admin_post_wrcp_save_role_config', array($this, 'handle_role_config_save'));
        add_action('admin_post_wrcp_save_category_discounts', array($this, 'handle_category_discounts_save'));
        add_action('admin_post_wrcp_export_settings', array($this, 'handle_export'));
        add_action('admin_post_wrcp_import_settings', array($this, 'handle_import'));
        add_action('admin_post_wrcp_reset_settings', array($this, 'handle_reset'));
        add_action('admin_post_wrcp_restore_backup', array($this, 'handle_restore_backup'));
        
        // AJAX handlers
        add_action('wp_ajax_wrcp_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_wrcp_save_bulk_discounts', array($this, 'ajax_save_bulk_discounts'));
        
        // Additional AJAX handlers for enhanced functionality
        add_action('wp_ajax_wrcp_validate_discount', array($this, 'ajax_validate_discount'));
        add_action('wp_ajax_wrcp_validate_role_name', array($this, 'ajax_validate_role_name'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . WRCP_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        $this->page_hook = add_submenu_page(
            'woocommerce',
            __('Role Category Pricing', 'woocommerce-role-category-pricing'),
            __('Role Category Pricing', 'woocommerce-role-category-pricing'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== $this->page_hook) {
            return;
        }
        
        // Enqueue WordPress media scripts for potential future use
        wp_enqueue_media();
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'wrcp-admin-styles',
            WRCP_PLUGIN_URL . 'admin/css/admin-styles.css',
            array(),
            WRCP_VERSION
        );
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'wrcp-admin-scripts',
            WRCP_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery', 'wp-util'),
            WRCP_VERSION,
            true
        );
        
        // Localize script with AJAX data and internationalized strings
        wp_localize_script('wrcp-admin-scripts', 'wrcp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wrcp_admin_nonce'),
            'is_rtl' => is_rtl(),
            'locale' => get_locale(),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this role?', 'woocommerce-role-category-pricing'),
                'confirm_reset' => __('Are you sure you want to clear the selected discounts?', 'woocommerce-role-category-pricing'),
                'saving' => __('Saving...', 'woocommerce-role-category-pricing'),
                'saved' => __('Saved!', 'woocommerce-role-category-pricing'),
                'error' => __('Error occurred while saving.', 'woocommerce-role-category-pricing'),
                'invalid_discount' => __('Please enter a valid discount percentage (0-100).', 'woocommerce-role-category-pricing'),
                'role_name_required' => __('Role name is required.', 'woocommerce-role-category-pricing'),
                'please_select_role' => __('Please select a role.', 'woocommerce-role-category-pricing'),
                'please_select_categories' => __('Please select at least one category.', 'woocommerce-role-category-pricing'),
                'confirm_reset_all' => __('Are you sure you want to reset all category discounts? This cannot be undone.', 'woocommerce-role-category-pricing'),
                'invalid_file_type' => __('Invalid file type. Only JSON files are allowed.', 'woocommerce-role-category-pricing'),
                'file_too_large' => __('File is too large. Maximum size is 5MB.', 'woocommerce-role-category-pricing'),
                'loading' => __('Loading...', 'woocommerce-role-category-pricing'),
                'success' => __('Success!', 'woocommerce-role-category-pricing'),
                'warning' => __('Warning', 'woocommerce-role-category-pricing'),
                'info' => __('Information', 'woocommerce-role-category-pricing')
            ),
            'number_format' => array(
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals()
            )
        ));
    }
    
    /**
     * Render main settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woocommerce-role-category-pricing'));
        }
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'roles';
        
        // Get available tabs
        $tabs = $this->get_settings_tabs();
        
        // Include the main settings template
        include WRCP_PLUGIN_PATH . 'admin/views/admin-settings.php';
    }
    
    /**
     * Get settings tabs
     *
     * @return array Settings tabs
     */
    private function get_settings_tabs() {
        return array(
            'roles' => array(
                'title' => __('Role Configuration', 'woocommerce-role-category-pricing'),
                'description' => __('Configure which roles can receive discounts and set base discount percentages.', 'woocommerce-role-category-pricing')
            ),
            'categories' => array(
                'title' => __('Category Discounts', 'woocommerce-role-category-pricing'),
                'description' => __('Set category-specific discount percentages for each enabled role.', 'woocommerce-role-category-pricing')
            ),
            'import-export' => array(
                'title' => __('Import/Export', 'woocommerce-role-category-pricing'),
                'description' => __('Backup and restore your role and discount configurations.', 'woocommerce-role-category-pricing')
            )
        );
    }
    
    /**
     * Handle general settings save
     */
    public function handle_settings_save() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_save_settings') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Process general settings if any
        $redirect_url = add_query_arg(array(
            'page' => $this->page_slug,
            'tab' => 'roles',
            'message' => 'settings_saved'
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle role configuration save
     */
    public function handle_role_config_save() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_save_role_config') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        $errors = array();
        $success_count = 0;
        
        // Additional security: Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method.', 'woocommerce-role-category-pricing'));
        }
        
        // Process role configurations
        if (isset($_POST['roles']) && is_array($_POST['roles'])) {
            foreach ($_POST['roles'] as $role_key => $role_data) {
                // Sanitize and validate role key
                $role_key = sanitize_key($role_key);
                
                if (empty($role_key)) {
                    $errors[] = __('Invalid role key provided.', 'woocommerce-role-category-pricing');
                    continue;
                }
                
                // Verify role exists and is configurable
                if (!$this->role_manager->is_valid_role_key($role_key)) {
                    $errors[] = sprintf(
                        __('Invalid role key format: "%s".', 'woocommerce-role-category-pricing'),
                        $role_key
                    );
                    continue;
                }
                
                // Validate and sanitize role data
                $config = array(
                    'enabled' => isset($role_data['enabled']) && $role_data['enabled'] === '1',
                    'base_discount' => 0.0,
                    'shipping_methods' => array()
                );
                
                // Process base discount with enhanced validation
                if (isset($role_data['base_discount']) && !empty($role_data['base_discount'])) {
                    $discount_input = sanitize_text_field($role_data['base_discount']);
                    
                    if (!is_numeric($discount_input)) {
                        $errors[] = sprintf(
                            __('Base discount for role "%s" must be a valid number.', 'woocommerce-role-category-pricing'),
                            $role_key
                        );
                        continue;
                    }
                    
                    $discount = floatval($discount_input);
                    if ($discount >= 0 && $discount <= 100) {
                        $config['base_discount'] = round($discount, 2); // Round to 2 decimal places
                    } else {
                        $errors[] = sprintf(
                            __('Invalid base discount for role "%s". Must be between 0 and 100.', 'woocommerce-role-category-pricing'),
                            $role_key
                        );
                        continue;
                    }
                }
                
                // Process shipping methods with validation
                if (isset($role_data['shipping_methods']) && is_array($role_data['shipping_methods'])) {
                    $sanitized_methods = array();
                    foreach ($role_data['shipping_methods'] as $method) {
                        $method = sanitize_text_field($method);
                        if (!empty($method) && preg_match('/^[a-zA-Z0-9_-]+$/', $method)) {
                            $sanitized_methods[] = $method;
                        }
                    }
                    $config['shipping_methods'] = $sanitized_methods;
                }
                
                // Save role configuration
                $result = $this->role_manager->save_role_configuration($role_key, $config);
                if (is_wp_error($result)) {
                    $errors[] = sprintf(
                        __('Failed to save configuration for role "%s": %s', 'woocommerce-role-category-pricing'),
                        $role_key,
                        $result->get_error_message()
                    );
                } else {
                    $success_count++;
                }
            }
        }
        
        // Prepare redirect with messages
        $redirect_args = array(
            'page' => $this->page_slug,
            'tab' => 'roles'
        );
        
        if ($success_count > 0) {
            $redirect_args['message'] = 'role_config_saved';
            $redirect_args['count'] = $success_count;
        }
        
        if (!empty($errors)) {
            // Store errors in transient for display
            set_transient('wrcp_admin_errors', $errors, 30);
            $redirect_args['errors'] = '1';
        }
        
        $redirect_url = add_query_arg($redirect_args, admin_url('admin.php'));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle category discounts save
     */
    public function handle_category_discounts_save() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_save_category_discounts') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        $errors = array();
        $success_count = 0;
        
        // Additional security: Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method.', 'woocommerce-role-category-pricing'));
        }
        
        // Process category discounts
        if (isset($_POST['category_discounts']) && is_array($_POST['category_discounts'])) {
            foreach ($_POST['category_discounts'] as $role_key => $categories) {
                // Sanitize and validate role key
                $role_key = sanitize_key($role_key);
                
                if (empty($role_key) || !$this->role_manager->is_valid_role_key($role_key)) {
                    $errors[] = sprintf(
                        __('Invalid role key: "%s".', 'woocommerce-role-category-pricing'),
                        $role_key
                    );
                    continue;
                }
                
                if (!is_array($categories)) {
                    continue;
                }
                
                foreach ($categories as $category_id => $discount) {
                    // Sanitize and validate category ID
                    $category_id = intval($category_id);
                    
                    if ($category_id <= 0) {
                        continue;
                    }
                    
                    // Verify category exists
                    $category = get_term($category_id, 'product_cat');
                    if (!$category || is_wp_error($category)) {
                        $errors[] = sprintf(
                            __('Category with ID %d does not exist.', 'woocommerce-role-category-pricing'),
                            $category_id
                        );
                        continue;
                    }
                    
                    // Sanitize and validate discount
                    $discount_input = sanitize_text_field($discount);
                    
                    if (!empty($discount_input) && !is_numeric($discount_input)) {
                        $errors[] = sprintf(
                            __('Invalid discount value for category %d in role "%s". Must be a valid number.', 'woocommerce-role-category-pricing'),
                            $category_id,
                            $role_key
                        );
                        continue;
                    }
                    
                    $discount = floatval($discount_input);
                    
                    if ($discount < 0 || $discount > 100) {
                        $errors[] = sprintf(
                            __('Invalid discount for category %d in role "%s". Must be between 0 and 100.', 'woocommerce-role-category-pricing'),
                            $category_id,
                            $role_key
                        );
                        continue;
                    }
                    
                    // Round to 2 decimal places
                    $discount = round($discount, 2);
                    
                    // Save or remove category discount
                    if ($discount > 0) {
                        $result = $this->role_manager->set_role_category_discount($role_key, $category_id, $discount);
                    } else {
                        $result = $this->role_manager->remove_role_category_discount($role_key, $category_id);
                    }
                    
                    if (is_wp_error($result)) {
                        $errors[] = sprintf(
                            __('Failed to save discount for category %d in role "%s": %s', 'woocommerce-role-category-pricing'),
                            $category_id,
                            $role_key,
                            $result->get_error_message()
                        );
                    } else {
                        $success_count++;
                    }
                }
            }
        }
        
        // Prepare redirect with messages
        $redirect_args = array(
            'page' => $this->page_slug,
            'tab' => 'categories'
        );
        
        if ($success_count > 0) {
            $redirect_args['message'] = 'category_discounts_saved';
            $redirect_args['count'] = $success_count;
        }
        
        if (!empty($errors)) {
            // Store errors in transient for display
            set_transient('wrcp_admin_errors', $errors, 30);
            $redirect_args['errors'] = '1';
        }
        
        $redirect_url = add_query_arg($redirect_args, admin_url('admin.php'));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * AJAX handler to get categories
     */
    public function ajax_get_categories() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wrcp_admin_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        $categories = $this->get_product_categories_hierarchical();
        wp_send_json_success($categories);
    }
    
    /**
     * AJAX handler to save bulk discounts
     */
    public function ajax_save_bulk_discounts() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wrcp_admin_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Additional security: Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(__('Invalid request method.', 'woocommerce-role-category-pricing'));
        }
        
        // Sanitize and validate inputs
        $role = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';
        $discount_input = isset($_POST['discount']) ? sanitize_text_field($_POST['discount']) : '';
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? 
                       array_map('intval', $_POST['category_ids']) : array();
        
        // Validate role
        if (empty($role) || !$this->role_manager->is_valid_role_key($role)) {
            wp_send_json_error(__('Invalid role specified.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate discount - allow empty for clearing
        if (!empty($discount_input) && !is_numeric($discount_input)) {
            wp_send_json_error(__('Discount must be a valid number.', 'woocommerce-role-category-pricing'));
        }
        
        $discount = floatval($discount_input);
        if ($discount < 0 || $discount > 100) {
            wp_send_json_error(__('Discount must be between 0 and 100.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate category IDs
        if (empty($category_ids)) {
            wp_send_json_error(__('No categories specified.', 'woocommerce-role-category-pricing'));
        }
        
        // Limit the number of categories that can be processed at once
        if (count($category_ids) > 100) {
            wp_send_json_error(__('Too many categories selected. Maximum 100 categories allowed per request.', 'woocommerce-role-category-pricing'));
        }
        
        $success_count = 0;
        $errors = array();
        
        foreach ($category_ids as $category_id) {
            if ($category_id <= 0) {
                continue;
            }
            
            // Verify category exists
            $category = get_term($category_id, 'product_cat');
            if (!$category || is_wp_error($category)) {
                $errors[] = sprintf(__('Category with ID %d does not exist.', 'woocommerce-role-category-pricing'), $category_id);
                continue;
            }
            
            // Round discount to 2 decimal places
            $discount = round($discount, 2);
            
            if ($discount > 0) {
                $result = $this->role_manager->set_role_category_discount($role, $category_id, $discount);
            } else {
                $result = $this->role_manager->remove_role_category_discount($role, $category_id);
            }
            
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $success_count++;
            }
        }
        
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Some discounts could not be saved.', 'woocommerce-role-category-pricing'),
                'errors' => $errors,
                'success_count' => $success_count
            ));
        } else {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Successfully updated %d category discounts.', 'woocommerce-role-category-pricing'),
                    $success_count
                ),
                'success_count' => $success_count
            ));
        }
    }
    
    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=' . $this->page_slug),
            __('Settings', 'woocommerce-role-category-pricing')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Get all available shipping methods
     *
     * @return array Shipping methods
     */
    public function get_available_shipping_methods() {
        if (!function_exists('WC')) {
            return array();
        }
        
        $methods = array();
        $shipping_zones = WC_Shipping_Zones::get_zones();
        
        // Get methods from all zones
        foreach ($shipping_zones as $zone_data) {
            if (isset($zone_data['shipping_methods'])) {
                foreach ($zone_data['shipping_methods'] as $method) {
                    $methods[$method->id] = $method->get_title();
                }
            }
        }
        
        // Also get methods from zone 0 (rest of the world)
        $zone_0 = new WC_Shipping_Zone(0);
        $zone_0_methods = $zone_0->get_shipping_methods();
        foreach ($zone_0_methods as $method) {
            $methods[$method->id] = $method->get_title();
        }
        
        // Add common shipping methods if not present
        $common_methods = array(
            'flat_rate' => __('Flat Rate', 'woocommerce-role-category-pricing'),
            'free_shipping' => __('Free Shipping', 'woocommerce-role-category-pricing'),
            'local_pickup' => __('Local Pickup', 'woocommerce-role-category-pricing'),
            'local_delivery' => __('Local Delivery', 'woocommerce-role-category-pricing')
        );
        
        foreach ($common_methods as $method_id => $method_title) {
            if (!isset($methods[$method_id])) {
                $methods[$method_id] = $method_title;
            }
        }
        
        return $methods;
    }
    
    /**
     * Get product categories in hierarchical format
     *
     * @return array Hierarchical categories
     */
    public function get_product_categories_hierarchical() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (is_wp_error($categories)) {
            return array();
        }
        
        return $this->build_category_hierarchy($categories);
    }
    
    /**
     * Build category hierarchy from flat array
     *
     * @param array $categories Flat array of categories
     * @param int $parent_id Parent category ID
     * @param int $level Current hierarchy level
     * @return array Hierarchical categories
     */
    private function build_category_hierarchy($categories, $parent_id = 0, $level = 0) {
        $hierarchy = array();
        
        foreach ($categories as $category) {
            if ($category->parent == $parent_id) {
                $category->level = $level;
                $hierarchy[] = $category;
                
                // Get children
                $children = $this->build_category_hierarchy($categories, $category->term_id, $level + 1);
                $hierarchy = array_merge($hierarchy, $children);
            }
        }
        
        return $hierarchy;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Display success messages
        if (isset($_GET['message'])) {
            $message = sanitize_key($_GET['message']);
            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
            
            switch ($message) {
                case 'settings_saved':
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Settings saved successfully.', 'woocommerce-role-category-pricing') . 
                         '</p></div>';
                    break;
                    
                case 'role_config_saved':
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         sprintf(__('Successfully saved configuration for %d roles.', 'woocommerce-role-category-pricing'), $count) . 
                         '</p></div>';
                    break;
                    
                case 'category_discounts_saved':
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         sprintf(__('Successfully saved %d category discounts.', 'woocommerce-role-category-pricing'), $count) . 
                         '</p></div>';
                    break;
            }
        }
        
        // Display error messages
        if (isset($_GET['errors']) && $_GET['errors'] === '1') {
            $errors = get_transient('wrcp_admin_errors');
            if ($errors && is_array($errors)) {
                foreach ($errors as $error) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
                }
                delete_transient('wrcp_admin_errors');
            }
        }
    }
    
    /**
     * Get enabled roles for display
     *
     * @return array Enabled roles with configurations
     */
    public function get_enabled_roles_for_display() {
        $configurable_roles = $this->role_manager->get_configurable_roles();
        $enabled_roles = array();
        
        foreach ($configurable_roles as $role_key => $role_data) {
            if ($this->role_manager->is_role_enabled($role_key)) {
                $config = $this->role_manager->get_role_configuration($role_key);
                $enabled_roles[$role_key] = array_merge($role_data, $config);
            }
        }
        
        return $enabled_roles;
    }
    
    /**
     * Render role configuration tab content
     */
    public function render_role_configuration_tab() {
        $configurable_roles = $this->role_manager->get_configurable_roles();
        $custom_roles = $this->role_manager->get_custom_roles();
        $shipping_methods = $this->get_available_shipping_methods();
        
        include WRCP_PLUGIN_PATH . 'admin/views/role-configuration.php';
    }
    
    /**
     * Render category discounts tab content
     */
    public function render_category_discounts_tab() {
        $enabled_roles = $this->get_enabled_roles_for_display();
        $categories = $this->get_product_categories_hierarchical();
        
        include WRCP_PLUGIN_PATH . 'admin/views/category-discounts.php';
    }
    
    /**
     * Render import/export tab content
     */
    public function render_import_export_tab() {
        include WRCP_PLUGIN_PATH . 'admin/views/import-export.php';
    }
    
    /**
     * Handle configuration export
     */
    public function handle_export() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_export_settings') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        $export_data = $this->role_manager->export_configurations();
        
        // Set headers for file download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="wrcp-settings-' . date('Y-m-d-H-i-s') . '.json"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Handle configuration import
     */
    public function handle_import() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_import_settings') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Additional security: Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method.', 'woocommerce-role-category-pricing'));
        }
        
        // Validate file upload
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'message' => 'import_error'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Security: Validate file type and size
        $file_info = $_FILES['import_file'];
        $allowed_mime_types = array('application/json', 'text/plain');
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        if ($file_info['size'] > $max_file_size) {
            set_transient('wrcp_admin_errors', array(__('Import file is too large. Maximum size is 5MB.', 'woocommerce-role-category-pricing')), 30);
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'errors' => '1'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'json') {
            set_transient('wrcp_admin_errors', array(__('Invalid file type. Only JSON files are allowed.', 'woocommerce-role-category-pricing')), 30);
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'errors' => '1'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Read and validate file content
        $file_content = file_get_contents($file_info['tmp_name']);
        
        if ($file_content === false) {
            set_transient('wrcp_admin_errors', array(__('Failed to read import file.', 'woocommerce-role-category-pricing')), 30);
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'errors' => '1'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Sanitize file content (remove potential harmful characters)
        $file_content = wp_kses($file_content, array());
        
        $import_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'message' => 'invalid_json'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Additional validation: Check if import data is reasonable
        if (!is_array($import_data)) {
            set_transient('wrcp_admin_errors', array(__('Invalid import data format.', 'woocommerce-role-category-pricing')), 30);
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'errors' => '1'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
            exit;
        }
        
        $result = $this->role_manager->import_configurations($import_data);
        
        if (is_wp_error($result)) {
            set_transient('wrcp_admin_errors', array($result->get_error_message()), 30);
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'errors' => '1'
            ), admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'message' => 'import_success'
            ), admin_url('admin.php'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle settings reset
     */
    public function handle_reset() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_reset_settings') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Use role manager's reset method
        $result = $this->role_manager->reset_to_defaults();
        
        if ($result) {
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'message' => 'reset_success'
            ), admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'message' => 'reset_error'
            ), admin_url('admin.php'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle restore from backup
     */
    public function handle_restore_backup() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['wrcp_nonce'], 'wrcp_restore_backup') || 
            !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        // Use role manager's restore method
        $result = $this->role_manager->restore_from_backup();
        
        if (is_wp_error($result)) {
            set_transient('wrcp_admin_errors', array($result->get_error_message()), 30);
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'errors' => '1'
            ), admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg(array(
                'page' => $this->page_slug,
                'tab' => 'import-export',
                'message' => 'restore_success'
            ), admin_url('admin.php'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * AJAX handler to validate discount percentage
     */
    public function ajax_validate_discount() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wrcp_admin_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        $discount = isset($_POST['discount']) ? sanitize_text_field($_POST['discount']) : '';
        
        if (empty($discount)) {
            wp_send_json_success(array('valid' => true, 'message' => ''));
        }
        
        $discount_value = floatval($discount);
        
        if (!is_numeric($discount) || $discount_value < 0 || $discount_value > 100) {
            wp_send_json_success(array(
                'valid' => false, 
                'message' => __('Discount must be a number between 0 and 100.', 'woocommerce-role-category-pricing')
            ));
        }
        
        wp_send_json_success(array('valid' => true, 'message' => ''));
    }
    
    /**
     * AJAX handler to validate role name
     */
    public function ajax_validate_role_name() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wrcp_admin_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Security check failed.', 'woocommerce-role-category-pricing'));
        }
        
        $role_name = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        
        if (empty($role_name)) {
            wp_send_json_success(array(
                'valid' => false, 
                'message' => __('Role name cannot be empty.', 'woocommerce-role-category-pricing')
            ));
        }
        
        // Check length
        if (strlen($role_name) > 50) {
            wp_send_json_success(array(
                'valid' => false, 
                'message' => __('Role name cannot exceed 50 characters.', 'woocommerce-role-category-pricing')
            ));
        }
        
        // Check for valid characters
        if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $role_name)) {
            wp_send_json_success(array(
                'valid' => false, 
                'message' => __('Role name can only contain letters, numbers, spaces, hyphens, and underscores.', 'woocommerce-role-category-pricing')
            ));
        }
        
        // Generate role key and check if it already exists
        $role_key = sanitize_key(strtolower(str_replace(' ', '_', $role_name)));
        if (get_role($role_key)) {
            wp_send_json_success(array(
                'valid' => false, 
                'message' => __('A role with this name already exists.', 'woocommerce-role-category-pricing')
            ));
        }
        
        wp_send_json_success(array('valid' => true, 'message' => ''));
    }
}