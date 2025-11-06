<?php
/**
 * WRCP Pricing Engine Class
 *
 * Handles core pricing calculation logic including category hierarchy walking,
 * discount detection, and multi-role discount comparison.
 *
 * @package WooCommerce_Role_Category_Pricing
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WRCP_Pricing_Engine {

    /**
     * Role manager instance
     *
     * @var WRCP_Role_Manager
     */
    private $role_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->role_manager = new WRCP_Role_Manager();
    }

    /**
     * Calculate discount percentage for a product based on user roles
     *
     * @param int $product_id Product ID
     * @param array $user_roles Array of user role names
     * @return float Discount percentage (0-100)
     */
    public function calculate_discount($product_id, $user_roles) {
        if (empty($user_roles) || !is_array($user_roles)) {
            return 0.0;
        }

        // Get product categories
        $product_categories = $this->get_product_categories($product_id);
        if (empty($product_categories)) {
            return $this->get_base_discount_for_roles($user_roles);
        }

        $discounts = array();

        // Calculate discount for each role
        foreach ($user_roles as $role) {
            if (!$this->role_manager->is_role_enabled($role)) {
                continue;
            }

            // Walk category hierarchy to find most specific discount
            $category_discount = $this->walk_category_hierarchy($product_categories, $role);
            
            if ($category_discount !== null) {
                $discounts[] = $category_discount;
            } else {
                // Use base discount if no category-specific discount found
                $base_discount = $this->role_manager->get_role_base_discount($role);
                if ($base_discount > 0) {
                    $discounts[] = $base_discount;
                }
            }
        }

        // Return greatest discount available
        return $this->get_greatest_discount($discounts);
    }

    /**
     * Get category-specific discount for a role
     *
     * @param int $category_id Category ID
     * @param string $role Role name
     * @return float|null Discount percentage or null if not set
     */
    public function get_category_discount($category_id, $role) {
        if (!$this->role_manager->is_role_enabled($role)) {
            return null;
        }

        $settings = get_option('wrcp_settings', array());
        
        if (!isset($settings['enabled_roles'][$role]['category_discounts'])) {
            return null;
        }

        $category_discounts = $settings['enabled_roles'][$role]['category_discounts'];
        
        // Get category term
        $category = get_term($category_id, 'product_cat');
        if (is_wp_error($category) || !$category) {
            return null;
        }

        // Check if discount exists for this category slug
        if (isset($category_discounts[$category->slug])) {
            return floatval($category_discounts[$category->slug]);
        }

        return null;
    }

    /**
     * Walk category hierarchy to find most specific discount
     *
     * @param array $product_categories Array of category IDs
     * @param string $role Role name
     * @return float|null Most specific discount percentage or null
     */
    public function walk_category_hierarchy($product_categories, $role) {
        if (empty($product_categories) || !is_array($product_categories)) {
            return null;
        }

        // Start with the most specific categories (lowest level)
        $categories_by_level = $this->organize_categories_by_level($product_categories);
        
        // Walk from most specific to least specific
        foreach ($categories_by_level as $level_categories) {
            foreach ($level_categories as $category_id) {
                $discount = $this->get_category_discount($category_id, $role);
                if ($discount !== null) {
                    return $discount;
                }
            }
        }

        return null;
    }

    /**
     * Return the greatest discount from an array of discounts
     *
     * @param array $discounts Array of discount percentages
     * @return float Greatest discount percentage
     */
    public function get_greatest_discount($discounts) {
        if (empty($discounts)) {
            return 0.0;
        }

        $discounts = array_filter($discounts, function($discount) {
            return is_numeric($discount) && $discount > 0;
        });

        if (empty($discounts)) {
            return 0.0;
        }

        return max($discounts);
    }

    /**
     * Get product categories for a given product ID
     *
     * @param int $product_id Product ID
     * @return array Array of category IDs
     */
    private function get_product_categories($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }

        return $product->get_category_ids();
    }

    /**
     * Get base discount for multiple roles (returns highest)
     *
     * @param array $user_roles Array of user role names
     * @return float Highest base discount percentage
     */
    private function get_base_discount_for_roles($user_roles) {
        $discounts = array();

        foreach ($user_roles as $role) {
            if (!$this->role_manager->is_role_enabled($role)) {
                continue;
            }

            $base_discount = $this->role_manager->get_role_base_discount($role);
            if ($base_discount > 0) {
                $discounts[] = $base_discount;
            }
        }

        return $this->get_greatest_discount($discounts);
    }

    /**
     * Organize categories by hierarchy level (most specific first)
     *
     * @param array $category_ids Array of category IDs
     * @return array Categories organized by level
     */
    private function organize_categories_by_level($category_ids) {
        $categories_by_level = array();

        foreach ($category_ids as $category_id) {
            $level = $this->get_category_level($category_id);
            if (!isset($categories_by_level[$level])) {
                $categories_by_level[$level] = array();
            }
            $categories_by_level[$level][] = $category_id;
        }

        // Sort by level (highest level = most specific)
        krsort($categories_by_level);

        return $categories_by_level;
    }

    /**
     * Get category hierarchy level (depth from root)
     *
     * @param int $category_id Category ID
     * @return int Category level (0 = root, higher = deeper)
     */
    private function get_category_level($category_id) {
        $level = 0;
        $current_id = $category_id;

        while ($current_id) {
            $category = get_term($current_id, 'product_cat');
            if (is_wp_error($category) || !$category) {
                break;
            }

            if ($category->parent == 0) {
                break;
            }

            $current_id = $category->parent;
            $level++;
        }

        return $level;
    }

    /**
     * Apply discount percentage to a price
     *
     * @param float $price Original price
     * @param float $discount_percentage Discount percentage (0-100)
     * @return float Discounted price
     */
    public function apply_discount_to_price($price, $discount_percentage) {
        if (!is_numeric($price) || !is_numeric($discount_percentage)) {
            return $price;
        }

        $price = floatval($price);
        $discount_percentage = floatval($discount_percentage);

        // Validate discount percentage
        if ($discount_percentage < 0 || $discount_percentage > 100) {
            return $price;
        }

        if ($discount_percentage == 0) {
            return $price;
        }

        $discount_amount = ($price * $discount_percentage) / 100;
        $discounted_price = $price - $discount_amount;

        // Ensure price doesn't go below zero
        return max(0, $discounted_price);
    }

    /**
     * Calculate discounted price for a product and user roles
     *
     * @param int $product_id Product ID
     * @param array $user_roles Array of user role names
     * @param float $original_price Original product price
     * @return float Discounted price
     */
    public function calculate_discounted_price($product_id, $user_roles, $original_price) {
        if (!is_numeric($original_price) || $original_price <= 0) {
            return $original_price;
        }

        $discount_percentage = $this->calculate_discount($product_id, $user_roles);
        
        // Apply WWP compatibility filter
        $discount_percentage = apply_filters('wrcp_calculate_discount', $discount_percentage, $product_id, $user_roles, $original_price);
        
        if ($discount_percentage <= 0) {
            return $original_price;
        }

        return $this->apply_discount_to_price($original_price, $discount_percentage);
    }

    /**
     * Calculate variable product price range with discounts
     *
     * @param WC_Product_Variable $product Variable product object
     * @param array $user_roles Array of user role names
     * @return array Array with 'min' and 'max' discounted prices
     */
    public function calculate_variable_product_price_range($product, $user_roles) {
        if (!$product || !is_a($product, 'WC_Product_Variable')) {
            return array('min' => 0, 'max' => 0);
        }

        $variations = $product->get_available_variations();
        if (empty($variations)) {
            return array('min' => 0, 'max' => 0);
        }

        $discounted_prices = array();

        foreach ($variations as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            $variation = wc_get_product($variation_id);
            
            if (!$variation) {
                continue;
            }

            $original_price = $variation->get_price();
            if ($original_price <= 0) {
                continue;
            }

            $discounted_price = $this->calculate_discounted_price(
                $product->get_id(), 
                $user_roles, 
                $original_price
            );

            $discounted_prices[] = $discounted_price;
        }

        if (empty($discounted_prices)) {
            return array('min' => 0, 'max' => 0);
        }

        return array(
            'min' => min($discounted_prices),
            'max' => max($discounted_prices)
        );
    }

    /**
     * Calculate discounted price for a specific variation
     *
     * @param int $variation_id Variation ID
     * @param array $user_roles Array of user role names
     * @return float Discounted variation price
     */
    public function calculate_variation_price($variation_id, $user_roles) {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return 0;
        }

        $original_price = $variation->get_price();
        if ($original_price <= 0) {
            return $original_price;
        }

        // Use parent product ID for category lookup
        $parent_id = $variation->get_parent_id();
        
        return $this->calculate_discounted_price($parent_id, $user_roles, $original_price);
    }

    /**
     * Validate price value
     *
     * @param mixed $price Price to validate
     * @return bool True if valid price
     */
    public function validate_price($price) {
        if (!is_numeric($price)) {
            return false;
        }

        $price = floatval($price);
        
        return $price >= 0;
    }

    /**
     * Validate discount percentage
     *
     * @param mixed $discount Discount percentage to validate
     * @return bool True if valid discount percentage
     */
    public function validate_discount_percentage($discount) {
        if (!is_numeric($discount)) {
            return false;
        }

        $discount = floatval($discount);
        
        return $discount >= 0 && $discount <= 100;
    }

    /**
     * Get formatted price with discount applied
     *
     * @param float $original_price Original price
     * @param float $discounted_price Discounted price
     * @param string $role_name Role name for display
     * @return string Formatted price HTML
     */
    public function get_formatted_role_price($original_price, $discounted_price, $role_name) {
        if (!$this->validate_price($original_price) || !$this->validate_price($discounted_price)) {
            return '';
        }

        // If no discount applied, return empty
        if ($original_price == $discounted_price) {
            return '';
        }

        $original_formatted = wc_price($original_price);
        $discounted_formatted = wc_price($discounted_price);
        $role_display = esc_html(ucwords(str_replace('_', ' ', $role_name)));

        return sprintf(
            '<del>%s</del> <ins>%s</ins><br><small>%s Price</small>',
            $original_formatted,
            $discounted_formatted,
            $role_display
        );
    }
}