<?php
/**
 * Category discounts tab template
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if (empty($enabled_roles)) : ?>
    <div class="wrcp-notice notice-warning">
        <p>
            <?php _e('No roles are enabled for category pricing.', 'woocommerce-role-category-pricing'); ?>
            <a href="<?php echo esc_url(add_query_arg('tab', 'roles', admin_url('admin.php?page=' . $this->page_slug))); ?>">
                <?php _e('Configure roles first', 'woocommerce-role-category-pricing'); ?>
            </a>
        </p>
    </div>
<?php elseif (empty($categories)) : ?>
    <div class="wrcp-notice notice-warning">
        <p><?php _e('No product categories found. Create some product categories in WooCommerce first.', 'woocommerce-role-category-pricing'); ?></p>
    </div>
<?php else : ?>
    
    <div class="wrcp-bulk-actions">
        <h4><?php _e('Bulk Actions', 'woocommerce-role-category-pricing'); ?></h4>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select id="bulk_role" style="min-width: 150px;">
                <option value=""><?php _e('Select Role', 'woocommerce-role-category-pricing'); ?></option>
                <?php foreach ($enabled_roles as $role_key => $role_data) : ?>
                    <option value="<?php echo esc_attr($role_key); ?>">
                        <?php echo esc_html($role_data['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="number" id="bulk_discount" placeholder="<?php esc_attr_e('Discount %', 'woocommerce-role-category-pricing'); ?>" 
                   min="0" max="100" step="0.01" style="width: 100px;">
            
            <button type="button" id="apply_bulk_discount" class="button button-secondary">
                <?php _e('Apply to Selected Categories', 'woocommerce-role-category-pricing'); ?>
            </button>
            
            <button type="button" id="clear_bulk_discount" class="button button-secondary">
                <?php _e('Clear Selected Categories', 'woocommerce-role-category-pricing'); ?>
            </button>
        </div>
        <p class="description">
            <?php _e('Select categories below, choose a role and discount percentage, then apply to multiple categories at once.', 'woocommerce-role-category-pricing'); ?>
        </p>
    </div>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wrcp-category-discounts-form">
        <?php wp_nonce_field('wrcp_save_category_discounts', 'wrcp_nonce'); ?>
        <input type="hidden" name="action" value="wrcp_save_category_discounts">
        
        <div class="wrcp-category-discounts-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select_all_categories">
                        </th>
                        <th style="width: 40%;"><?php _e('Category', 'woocommerce-role-category-pricing'); ?></th>
                        <?php foreach ($enabled_roles as $role_key => $role_data) : ?>
                            <th style="text-align: center;">
                                <?php echo esc_html($role_data['name']); ?>
                                <br><small style="font-weight: normal; color: #666;">
                                    <?php printf(__('Base: %s%%', 'woocommerce-role-category-pricing'), $role_data['base_discount']); ?>
                                </small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category) : ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="category-checkbox" 
                                       value="<?php echo esc_attr($category->term_id); ?>">
                            </td>
                            <td>
                                <span class="wrcp-category-name level-<?php echo esc_attr($category->level); ?>">
                                    <?php echo esc_html($category->name); ?>
                                    <small style="color: #666;">(<?php echo esc_html($category->count); ?>)</small>
                                </span>
                            </td>
                            <?php foreach ($enabled_roles as $role_key => $role_data) : ?>
                                <?php 
                                $current_discount = $this->role_manager->get_role_category_discount($role_key, $category->term_id);
                                ?>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           name="category_discounts[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($category->term_id); ?>]"
                                           value="<?php echo $current_discount > 0 ? esc_attr($current_discount) : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           class="wrcp-category-discount"
                                           placeholder="<?php echo esc_attr($role_data['base_discount']); ?>"
                                           data-role="<?php echo esc_attr($role_key); ?>"
                                           data-category="<?php echo esc_attr($category->term_id); ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" 
                   value="<?php esc_attr_e('Save Category Discounts', 'woocommerce-role-category-pricing'); ?>">
            
            <button type="button" id="reset_all_discounts" class="button button-secondary" 
                    style="margin-left: 10px;">
                <?php _e('Reset All Discounts', 'woocommerce-role-category-pricing'); ?>
            </button>
        </p>
    </form>
    
    <div class="wrcp-category-help">
        <h4><?php _e('How Category Discounts Work', 'woocommerce-role-category-pricing'); ?></h4>
        <ul>
            <li><?php _e('Category-specific discounts override base role discounts.', 'woocommerce-role-category-pricing'); ?></li>
            <li><?php _e('The plugin walks up the category hierarchy to find the most specific discount.', 'woocommerce-role-category-pricing'); ?></li>
            <li><?php _e('If multiple roles apply to a user, the highest discount is used.', 'woocommerce-role-category-pricing'); ?></li>
            <li><?php _e('Leave fields empty to use the base discount (shown as placeholder).', 'woocommerce-role-category-pricing'); ?></li>
            <li><?php _e('Enter 0 to explicitly set no discount for a category.', 'woocommerce-role-category-pricing'); ?></li>
        </ul>
    </div>

<?php endif; ?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle select all categories
    $('#select_all_categories').on('change', function() {
        $('.category-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Handle individual category checkboxes
    $('.category-checkbox').on('change', function() {
        var allChecked = $('.category-checkbox:checked').length === $('.category-checkbox').length;
        $('#select_all_categories').prop('checked', allChecked);
    });
    
    // Handle bulk discount application
    $('#apply_bulk_discount').on('click', function() {
        var role = $('#bulk_role').val();
        var discount = $('#bulk_discount').val();
        var selectedCategories = $('.category-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!role) {
            alert('<?php _e('Please select a role.', 'woocommerce-role-category-pricing'); ?>');
            return;
        }
        
        if (!discount || discount < 0 || discount > 100) {
            alert(wrcp_admin.strings.invalid_discount);
            return;
        }
        
        if (selectedCategories.length === 0) {
            alert('<?php _e('Please select at least one category.', 'woocommerce-role-category-pricing'); ?>');
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text(wrcp_admin.strings.saving).prop('disabled', true);
        
        $.ajax({
            url: wrcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wrcp_save_bulk_discounts',
                role: role,
                discount: discount,
                category_ids: selectedCategories,
                nonce: wrcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the input fields
                    selectedCategories.forEach(function(categoryId) {
                        $('input[data-role="' + role + '"][data-category="' + categoryId + '"]').val(discount);
                    });
                    
                    // Clear selections
                    $('.category-checkbox').prop('checked', false);
                    $('#select_all_categories').prop('checked', false);
                    $('#bulk_discount').val('');
                    
                    alert(response.data.message);
                } else {
                    alert(response.data.message || wrcp_admin.strings.error);
                }
            },
            error: function() {
                alert(wrcp_admin.strings.error);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle bulk discount clearing
    $('#clear_bulk_discount').on('click', function() {
        var role = $('#bulk_role').val();
        var selectedCategories = $('.category-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!role) {
            alert('<?php _e('Please select a role.', 'woocommerce-role-category-pricing'); ?>');
            return;
        }
        
        if (selectedCategories.length === 0) {
            alert('<?php _e('Please select at least one category.', 'woocommerce-role-category-pricing'); ?>');
            return;
        }
        
        if (!confirm(wrcp_admin.strings.confirm_reset)) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text(wrcp_admin.strings.saving).prop('disabled', true);
        
        $.ajax({
            url: wrcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wrcp_save_bulk_discounts',
                role: role,
                discount: 0,
                category_ids: selectedCategories,
                nonce: wrcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Clear the input fields
                    selectedCategories.forEach(function(categoryId) {
                        $('input[data-role="' + role + '"][data-category="' + categoryId + '"]').val('');
                    });
                    
                    // Clear selections
                    $('.category-checkbox').prop('checked', false);
                    $('#select_all_categories').prop('checked', false);
                    
                    alert(response.data.message);
                } else {
                    alert(response.data.message || wrcp_admin.strings.error);
                }
            },
            error: function() {
                alert(wrcp_admin.strings.error);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle reset all discounts
    $('#reset_all_discounts').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to reset all category discounts? This cannot be undone.', 'woocommerce-role-category-pricing'); ?>')) {
            return;
        }
        
        $('.wrcp-category-discount').val('');
    });
    
    // Validate discount inputs
    $('.wrcp-category-discount').on('blur', function() {
        var value = parseFloat($(this).val());
        if ($(this).val() !== '' && (isNaN(value) || value < 0 || value > 100)) {
            alert(wrcp_admin.strings.invalid_discount);
            $(this).focus();
        }
    });
});
</script>