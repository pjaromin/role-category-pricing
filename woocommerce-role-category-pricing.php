<?php
/**
 * Plugin Name: WooCommerce Role Category Pricing
 * Plugin URI: https://github.com/your-username/woocommerce-role-category-pricing
 * Description: Provides role-based category-specific pricing discounts for WooCommerce, compatible with WooCommerce Wholesale Prices plugin.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: woocommerce-role-category-pricing
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WRCP_VERSION', '1.0.0');
define('WRCP_PLUGIN_FILE', __FILE__);
define('WRCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WRCP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WRCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WRCP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'WRCP_') === 0) {
        $class_file = str_replace('_', '-', strtolower($class_name));
        $file_path = WRCP_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

// Initialize the plugin
function wrcp_init() {
    // Check if WooCommerce is active before initializing
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WooCommerce Role Category Pricing requires WooCommerce to be installed and active.', 'woocommerce-role-category-pricing');
            echo '</p></div>';
        });
        return;
    }
    
    // Load text domain early for proper internationalization
    load_plugin_textdomain(
        'woocommerce-role-category-pricing',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    
    if (class_exists('WRCP_Bootstrap')) {
        WRCP_Bootstrap::get_instance();
    }
}

// Plugin activation hook
register_activation_hook(__FILE__, 'wrcp_activate');
function wrcp_activate() {
    // Check dependencies before activation
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('WooCommerce Role Category Pricing requires WooCommerce to be installed and active.', 'woocommerce-role-category-pricing'),
            __('Plugin Activation Error', 'woocommerce-role-category-pricing'),
            array('back_link' => true)
        );
    }
    
    // Initialize plugin data
    if (class_exists('WRCP_Bootstrap')) {
        WRCP_Bootstrap::activate();
    }
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'wrcp_deactivate');
function wrcp_deactivate() {
    if (class_exists('WRCP_Bootstrap')) {
        WRCP_Bootstrap::deactivate();
    }
}

// Plugin uninstall hook
register_uninstall_hook(__FILE__, 'wrcp_uninstall');
function wrcp_uninstall() {
    if (class_exists('WRCP_Bootstrap')) {
        WRCP_Bootstrap::uninstall();
    }
}

// Declare WooCommerce compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility with High-Performance Order Storage (HPOS)
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        
        // Declare compatibility with Cart and Checkout Blocks
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        
        // Declare compatibility with other WooCommerce features
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, true);
    }
});

// Initialize plugin after WordPress and WooCommerce are loaded
add_action('plugins_loaded', 'wrcp_init', 20);

// Add a hook to check for WooCommerce after all plugins are loaded
add_action('init', function() {
    if (!class_exists('WooCommerce')) {
        // Deactivate the plugin if WooCommerce is not available
        add_action('admin_init', function() {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('WooCommerce Role Category Pricing has been deactivated because WooCommerce is not active.', 'woocommerce-role-category-pricing');
                echo '</p></div>';
            });
        });
    }
}, 0);