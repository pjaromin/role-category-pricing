<?php
/**
 * WRCP Cart Integration Class
 *
 * Handles cart and checkout pricing integration for role-based discounts.
 * Applies discounts during cart calculation and ensures price consistency
 * through the checkout process.
 *
 * @package WooCommerce_Role_Category_Pricing
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WRCP_Cart_Integration {

    /**
     * Single instance of the class
     *
     * @var WRCP_Cart_Integration
     */
    private static $instance = null;

    /**
     * Role manager instance
     *
     * @var WRCP_Role_Manager
     */
    private $role_manager;

    /**
     * Pricing engine instance
     *
     * @var WRCP_Pricing_Engine
     */
    private $pricing_engine;

    /**
     * Flag to prevent infinite loops during price calculation
     *
     * @var bool
     */
    private $calculating_prices = false;

    /**
     * Get single instance of the class
     *
     * @return WRCP_Cart_Integration
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
        $this->pricing_engine = new WRCP_Pricing_Engine();
        
        $this->init_hooks();
    }

    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks() {
        // Hook into cart calculation with priority 5 to run early
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_cart_discounts'), 5);
        
        // Ensure pricing consistency during checkout
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'apply_checkout_pricing'), 10, 4);
        
        // Handle order item price validation
        add_action('woocommerce_checkout_order_processed', array($this, 'validate_order_pricing'), 10, 3);
        
        // Ensure price consistency before order creation
        add_action('woocommerce_checkout_create_order', array($this, 'ensure_checkout_price_consistency'), 5);
        
        // Handle tax calculation on discounted prices
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price_for_tax'), 10, 2);
        
        // Add cart session compatibility
        add_action('woocommerce_cart_loaded_from_session', array($this, 'handle_cart_session_loaded'));
        
        // Clear cart pricing cache when user logs in/out
        add_action('wp_login', array($this, 'clear_cart_pricing_cache'));
        add_action('wp_logout', array($this, 'clear_cart_pricing_cache'));
        
        // Handle order status changes to maintain pricing integrity
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
    }

    /**
     * Apply discounts to cart items during cart calculation
     *
     * @param WC_Cart $cart WooCommerce cart object
     */
    public function apply_cart_discounts($cart) {
        // Prevent infinite loops
        if ($this->calculating_prices) {
            return;
        }

        // Only run in cart/checkout context
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Use WWP compatibility layer to determine if WRCP should run
        if (class_exists('WRCP_WWP_Compatibility')) {
            $compatibility = WRCP_WWP_Compatibility::get_instance();
            if (!$compatibility->should_wrcp_run()) {
                return;
            }
        }
        
        // Skip wholesale customers (fallback)
        if ($this->role_manager->is_wholesale_customer($user_id)) {
            return;
        }

        // Get user's applicable roles
        $user_roles = $this->role_manager->get_user_applicable_roles($user_id);
        if (empty($user_roles)) {
            return;
        }

        $this->calculating_prices = true;

        // Apply discounts to each cart item
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $this->modify_cart_item_price($cart_item, $user_roles);
        }

        $this->calculating_prices = false;
    }

    /**
     * Modify individual cart item price based on role discounts
     *
     * @param array $cart_item Cart item data
     * @param array $user_roles User's applicable roles
     */
    private function modify_cart_item_price(&$cart_item, $user_roles) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return;
        }

        $product = $cart_item['data'];
        $product_id = $product->get_id();
        
        // For variations, use parent product ID for category lookup
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $product_id = $parent_id;
        }

        // Get original price
        $original_price = $product->get_price();
        if (!$original_price || $original_price <= 0) {
            return;
        }

        // Calculate discounted price
        $discounted_price = $this->pricing_engine->calculate_discounted_price(
            $product_id,
            $user_roles,
            $original_price
        );

        // Only apply if there's a discount
        if ($discounted_price < $original_price) {
            // Set the new price on the product object
            $product->set_price($discounted_price);
            
            // Store original price for reference
            if (!isset($cart_item['wrcp_original_price'])) {
                $cart_item['wrcp_original_price'] = $original_price;
            }
            
            // Store discount information
            $cart_item['wrcp_discounted_price'] = $discounted_price;
            $cart_item['wrcp_discount_applied'] = true;
        }
    }

    /**
     * Apply pricing during checkout order creation
     *
     * @param WC_Order_Item_Product $item Order line item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function apply_checkout_pricing($item, $cart_item_key, $values, $order) {
        // Check if discount was applied in cart
        if (!isset($values['wrcp_discount_applied']) || !$values['wrcp_discount_applied']) {
            return;
        }

        // Ensure discounted price is maintained
        if (isset($values['wrcp_discounted_price'])) {
            $discounted_price = $values['wrcp_discounted_price'];
            $quantity = $values['quantity'];
            
            // Calculate line totals with proper tax handling
            $line_subtotal = $discounted_price * $quantity;
            $line_total = $line_subtotal;
            
            // Handle tax calculation if needed
            $product = $values['data'];
            if ($product && wc_tax_enabled()) {
                $tax_rates = WC_Tax::get_rates($product->get_tax_class());
                if (!empty($tax_rates)) {
                    $taxes = WC_Tax::calc_tax($line_subtotal, $tax_rates, wc_prices_include_tax());
                    $item->set_taxes(array('total' => $taxes, 'subtotal' => $taxes));
                    
                    if (!wc_prices_include_tax()) {
                        $line_total += array_sum($taxes);
                    }
                }
            }
            
            $item->set_subtotal($line_subtotal);
            $item->set_total($line_total);
            
            // Store original price in order item meta for reference
            if (isset($values['wrcp_original_price'])) {
                $item->add_meta_data('_wrcp_original_price', $values['wrcp_original_price'], true);
                $item->add_meta_data('_wrcp_discounted_price', $values['wrcp_discounted_price'], true);
                $item->add_meta_data('_wrcp_discount_applied', true, true);
            }
        }
    }

    /**
     * Ensure price consistency before order creation
     *
     * @param WC_Order $order Order object being created
     */
    public function ensure_checkout_price_consistency($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        // Skip wholesale customers
        if ($this->role_manager->is_wholesale_customer($user_id)) {
            return;
        }

        // Get user's applicable roles
        $user_roles = $this->role_manager->get_user_applicable_roles($user_id);
        if (empty($user_roles)) {
            return;
        }

        // Validate and correct pricing for each item
        foreach ($order->get_items() as $item_id => $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            $this->ensure_order_item_price_consistency($item, $user_roles);
        }
    }

    /**
     * Ensure individual order item price consistency
     *
     * @param WC_Order_Item_Product $item Order item
     * @param array $user_roles User's applicable roles
     */
    private function ensure_order_item_price_consistency($item, $user_roles) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        
        // Get the product
        $product = wc_get_product($variation_id ? $variation_id : $product_id);
        if (!$product) {
            return;
        }

        // Use parent product ID for category lookup if variation
        $lookup_id = $variation_id ? $product_id : $product_id;
        
        // Get original price
        $original_price = $product->get_regular_price();
        if (!$original_price || $original_price <= 0) {
            return;
        }

        // Calculate expected discounted price
        $expected_price = $this->pricing_engine->calculate_discounted_price(
            $lookup_id,
            $user_roles,
            $original_price
        );

        // Only update if there's a discount
        if ($expected_price < $original_price) {
            $quantity = $item->get_quantity();
            $line_subtotal = $expected_price * $quantity;
            
            // Handle tax calculation
            if (wc_tax_enabled()) {
                $tax_rates = WC_Tax::get_rates($product->get_tax_class());
                if (!empty($tax_rates)) {
                    $taxes = WC_Tax::calc_tax($line_subtotal, $tax_rates, wc_prices_include_tax());
                    $item->set_taxes(array('total' => $taxes, 'subtotal' => $taxes));
                }
            }
            
            $item->set_subtotal($line_subtotal);
            $item->set_total($line_subtotal);
            
            // Store pricing metadata
            $item->add_meta_data('_wrcp_original_price', $original_price, true);
            $item->add_meta_data('_wrcp_discounted_price', $expected_price, true);
            $item->add_meta_data('_wrcp_discount_applied', true, true);
        }
    }

    /**
     * Filter product price for proper tax calculation
     *
     * @param float $price Product price
     * @param WC_Product $product Product object
     * @return float Filtered price
     */
    public function filter_product_price_for_tax($price, $product) {
        // Only apply during cart/checkout context
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }

        // Only apply if we're in the middle of tax calculation
        if (!doing_action('woocommerce_before_calculate_totals') && 
            !doing_action('woocommerce_checkout_create_order')) {
            return $price;
        }

        return $price;
    }

    /**
     * Validate order pricing after checkout processing
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted checkout data
     * @param WC_Order $order Order object
     */
    public function validate_order_pricing($order_id, $posted_data, $order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        // Skip wholesale customers
        if ($this->role_manager->is_wholesale_customer($user_id)) {
            return;
        }

        // Get user's applicable roles
        $user_roles = $this->role_manager->get_user_applicable_roles($user_id);
        if (empty($user_roles)) {
            return;
        }

        $pricing_errors = array();

        // Validate each order item
        foreach ($order->get_items() as $item_id => $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            $error = $this->validate_order_item_pricing($item, $user_roles, $order);
            if ($error) {
                $pricing_errors[] = $error;
            }
        }

        // Log any pricing errors found
        if (!empty($pricing_errors)) {
            $this->log_order_pricing_errors($order, $pricing_errors);
        }

        // Recalculate order totals if any corrections were made
        $order->calculate_totals();
        $order->save();
    }

    /**
     * Validate individual order item pricing
     *
     * @param WC_Order_Item_Product $item Order item
     * @param array $user_roles User's applicable roles
     * @param WC_Order $order Order object
     * @return string|null Error message if pricing issue found, null otherwise
     */
    private function validate_order_item_pricing($item, $user_roles, $order) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        
        // Use variation ID if available, otherwise product ID
        $lookup_id = $variation_id ? $product_id : $product_id;
        
        // Get original price from meta or calculate it
        $original_price = $item->get_meta('_wrcp_original_price');
        if (!$original_price) {
            $product = wc_get_product($variation_id ? $variation_id : $product_id);
            if ($product) {
                $original_price = $product->get_regular_price();
            }
        }

        if (!$original_price || $original_price <= 0) {
            return null;
        }

        // Calculate expected discounted price
        $expected_price = $this->pricing_engine->calculate_discounted_price(
            $lookup_id,
            $user_roles,
            $original_price
        );

        // Get actual item price
        $actual_price = $item->get_subtotal() / $item->get_quantity();

        // Allow small floating point differences
        $price_difference = abs($expected_price - $actual_price);
        if ($price_difference > 0.01) {
            // Correct the pricing
            $corrected_subtotal = $expected_price * $item->get_quantity();
            $item->set_subtotal($corrected_subtotal);
            $item->set_total($corrected_subtotal);
            
            // Update metadata
            $item->update_meta_data('_wrcp_original_price', $original_price);
            $item->update_meta_data('_wrcp_discounted_price', $expected_price);
            $item->update_meta_data('_wrcp_discount_applied', true);
            
            $item->save();
            
            return sprintf(
                'Item "%s" price corrected from %s to %s',
                $item->get_name(),
                wc_price($actual_price),
                wc_price($expected_price)
            );
        }

        return null;
    }

    /**
     * Handle cart loaded from session
     */
    public function handle_cart_session_loaded() {
        // Ensure cart pricing is recalculated when loaded from session
        if (WC()->cart && !WC()->cart->is_empty()) {
            // Force recalculation on next cart total calculation
            WC()->cart->set_session();
        }
    }

    /**
     * Clear cart pricing cache when user authentication changes
     */
    public function clear_cart_pricing_cache() {
        if (WC()->cart) {
            // Clear any cached pricing data
            WC()->cart->empty_cart();
        }
        
        // Clear related transients
        $this->clear_pricing_transients();
    }

    /**
     * Get cart item discount information
     *
     * @param array $cart_item Cart item data
     * @return array Discount information
     */
    public function get_cart_item_discount_info($cart_item) {
        $info = array(
            'has_discount' => false,
            'original_price' => 0,
            'discounted_price' => 0,
            'discount_amount' => 0,
            'discount_percentage' => 0
        );

        if (!isset($cart_item['wrcp_discount_applied']) || !$cart_item['wrcp_discount_applied']) {
            return $info;
        }

        $original_price = isset($cart_item['wrcp_original_price']) ? $cart_item['wrcp_original_price'] : 0;
        $discounted_price = isset($cart_item['wrcp_discounted_price']) ? $cart_item['wrcp_discounted_price'] : 0;

        if ($original_price > 0 && $discounted_price > 0 && $discounted_price < $original_price) {
            $discount_amount = $original_price - $discounted_price;
            $discount_percentage = ($discount_amount / $original_price) * 100;

            $info = array(
                'has_discount' => true,
                'original_price' => $original_price,
                'discounted_price' => $discounted_price,
                'discount_amount' => $discount_amount,
                'discount_percentage' => round($discount_percentage, 2)
            );
        }

        return $info;
    }

    /**
     * Calculate cart total discount amount
     *
     * @return float Total discount amount
     */
    public function get_cart_total_discount() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return 0;
        }

        $total_discount = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $discount_info = $this->get_cart_item_discount_info($cart_item);
            if ($discount_info['has_discount']) {
                $total_discount += $discount_info['discount_amount'] * $cart_item['quantity'];
            }
        }

        return $total_discount;
    }

    /**
     * Check if cart has any WRCP discounts applied
     *
     * @return bool True if cart has discounts
     */
    public function cart_has_discounts() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['wrcp_discount_applied']) && $cart_item['wrcp_discount_applied']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get detailed cart discount breakdown
     *
     * @return array Discount breakdown by item
     */
    public function get_cart_discount_breakdown() {
        $breakdown = array();

        if (!WC()->cart || WC()->cart->is_empty()) {
            return $breakdown;
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $discount_info = $this->get_cart_item_discount_info($cart_item);
            
            if ($discount_info['has_discount']) {
                $product = $cart_item['data'];
                $breakdown[$cart_item_key] = array(
                    'product_name' => $product->get_name(),
                    'quantity' => $cart_item['quantity'],
                    'original_price' => $discount_info['original_price'],
                    'discounted_price' => $discount_info['discounted_price'],
                    'discount_per_item' => $discount_info['discount_amount'],
                    'total_discount' => $discount_info['discount_amount'] * $cart_item['quantity'],
                    'discount_percentage' => $discount_info['discount_percentage']
                );
            }
        }

        return $breakdown;
    }

    /**
     * Log pricing discrepancy for debugging
     *
     * @param WC_Order $order Order object
     * @param WC_Order_Item_Product $item Order item
     * @param float $expected_price Expected price
     * @param float $actual_price Actual price
     */
    private function log_pricing_discrepancy($order, $item, $expected_price, $actual_price) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => 'wrcp-cart-integration');
            
            $message = sprintf(
                'Pricing discrepancy detected. Order: %d, Item: %s, Expected: %s, Actual: %s',
                $order->get_id(),
                $item->get_name(),
                wc_price($expected_price),
                wc_price($actual_price)
            );
            
            $logger->warning($message, $context);
        }
    }

    /**
     * Log order pricing errors
     *
     * @param WC_Order $order Order object
     * @param array $errors Array of error messages
     */
    private function log_order_pricing_errors($order, $errors) {
        if (function_exists('wc_get_logger') && !empty($errors)) {
            $logger = wc_get_logger();
            $context = array('source' => 'wrcp-cart-integration');
            
            $message = sprintf(
                'Order %d pricing corrections applied: %s',
                $order->get_id(),
                implode('; ', $errors)
            );
            
            $logger->info($message, $context);
        }
    }

    /**
     * Clear pricing-related transients
     */
    private function clear_pricing_transients() {
        global $wpdb;
        
        // Clear WRCP-related transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wrcp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wrcp_%'");
    }

    /**
     * Handle tax calculation on discounted prices
     *
     * @param float $price Discounted price
     * @param WC_Product $product Product object
     * @return float Price with tax if applicable
     */
    public function calculate_tax_on_discounted_price($price, $product) {
        if (!$product || !is_numeric($price)) {
            return $price;
        }

        // Let WooCommerce handle tax calculation based on settings
        if (wc_tax_enabled() && !wc_prices_include_tax()) {
            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            if (!empty($tax_rates)) {
                $taxes = WC_Tax::calc_tax($price, $tax_rates, false);
                $price += array_sum($taxes);
            }
        }

        return $price;
    }

    /**
     * Get user roles for current cart session
     *
     * @return array User roles or empty array
     */
    private function get_current_user_roles() {
        if (!is_user_logged_in()) {
            return array();
        }

        $user_id = get_current_user_id();
        return $this->role_manager->get_user_applicable_roles($user_id);
    }

    /**
     * Check if current user qualifies for WRCP pricing
     *
     * @return bool True if user qualifies
     */
    public function user_qualifies_for_pricing() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        
        // Skip wholesale customers
        if ($this->role_manager->is_wholesale_customer($user_id)) {
            return false;
        }

        // Check if user has applicable roles
        $user_roles = $this->role_manager->get_user_applicable_roles($user_id);
        return !empty($user_roles);
    }

    /**
     * Reset cart item pricing to original values
     *
     * @param array $cart_item Cart item data
     */
    public function reset_cart_item_pricing(&$cart_item) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return;
        }

        if (isset($cart_item['wrcp_original_price'])) {
            $cart_item['data']->set_price($cart_item['wrcp_original_price']);
            unset($cart_item['wrcp_original_price']);
            unset($cart_item['wrcp_discounted_price']);
            unset($cart_item['wrcp_discount_applied']);
        }
    }

    /**
     * Force cart recalculation
     */
    public function force_cart_recalculation() {
        if (WC()->cart && !WC()->cart->is_empty()) {
            WC()->cart->calculate_totals();
        }
    }

    /**
     * Handle order status changes to maintain pricing integrity
     *
     * @param int $order_id Order ID
     * @param string $old_status Old order status
     * @param string $new_status New order status
     * @param WC_Order $order Order object
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // Only process for orders with WRCP discounts
        if (!$this->order_has_wrcp_discounts($order)) {
            return;
        }

        // Log status change for orders with discounts
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => 'wrcp-cart-integration');
            
            $message = sprintf(
                'Order %d with WRCP discounts changed status from %s to %s',
                $order_id,
                $old_status,
                $new_status
            );
            
            $logger->info($message, $context);
        }

        // Perform additional validation for critical status changes
        if (in_array($new_status, array('processing', 'completed'))) {
            $this->final_order_pricing_validation($order);
        }
    }

    /**
     * Check if order has WRCP discounts applied
     *
     * @param WC_Order $order Order object
     * @return bool True if order has WRCP discounts
     */
    private function order_has_wrcp_discounts($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_wrcp_discount_applied')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform final order pricing validation
     *
     * @param WC_Order $order Order object
     */
    private function final_order_pricing_validation($order) {
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        // Skip wholesale customers
        if ($this->role_manager->is_wholesale_customer($user_id)) {
            return;
        }

        // Get user's applicable roles
        $user_roles = $this->role_manager->get_user_applicable_roles($user_id);
        if (empty($user_roles)) {
            return;
        }

        $validation_errors = array();

        // Validate each item one final time
        foreach ($order->get_items() as $item_id => $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            // Only validate items that should have WRCP discounts
            if (!$item->get_meta('_wrcp_discount_applied')) {
                continue;
            }

            $original_price = $item->get_meta('_wrcp_original_price');
            $applied_price = $item->get_meta('_wrcp_discounted_price');
            
            if (!$original_price || !$applied_price) {
                continue;
            }

            // Recalculate expected price
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $lookup_id = $variation_id ? $product_id : $product_id;

            $expected_price = $this->pricing_engine->calculate_discounted_price(
                $lookup_id,
                $user_roles,
                $original_price
            );

            // Check if applied price matches expected price
            if (abs($applied_price - $expected_price) > 0.01) {
                $validation_errors[] = sprintf(
                    'Item "%s": Applied price %s does not match expected price %s',
                    $item->get_name(),
                    wc_price($applied_price),
                    wc_price($expected_price)
                );
            }
        }

        // Log validation results
        if (!empty($validation_errors)) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $context = array('source' => 'wrcp-cart-integration');
                
                $message = sprintf(
                    'Final validation errors for order %d: %s',
                    $order->get_id(),
                    implode('; ', $validation_errors)
                );
                
                $logger->warning($message, $context);
            }
        } else {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $context = array('source' => 'wrcp-cart-integration');
                
                $message = sprintf(
                    'Final validation passed for order %d with WRCP discounts',
                    $order->get_id()
                );
                
                $logger->info($message, $context);
            }
        }
    }
}