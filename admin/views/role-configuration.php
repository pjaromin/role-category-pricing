<?php
/**
 * Role configuration tab template
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wrcp-role-config-form">
    <?php wp_nonce_field('wrcp_save_role_config', 'wrcp_nonce'); ?>
    <input type="hidden" name="action" value="wrcp_save_role_config">
    
    <div class="wrcp-custom-roles-section">
        <h3><?php _e('Custom Roles', 'woocommerce-role-category-pricing'); ?></h3>
        
        <div class="wrcp-notice notice-info">
            <p><?php _e('You can create custom roles for specific customer segments. Custom roles created through this plugin can be removed here.', 'woocommerce-role-category-pricing'); ?></p>
        </div>
        
        <div class="wrcp-add-role-form">
            <div class="wrcp-field-group">
                <label for="new_role_name"><?php _e('New Role Name', 'woocommerce-role-category-pricing'); ?></label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="new_role_name" name="new_role_name" placeholder="<?php esc_attr_e('Enter role name', 'woocommerce-role-category-pricing'); ?>" style="max-width: 250px;">
                    <button type="button" id="add_custom_role" class="button button-secondary">
                        <?php _e('Add Role', 'woocommerce-role-category-pricing'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (!empty($custom_roles)) : ?>
            <div class="wrcp-custom-roles-list">
                <h4><?php _e('Custom Roles Created by Plugin', 'woocommerce-role-category-pricing'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Role Name', 'woocommerce-role-category-pricing'); ?></th>
                            <th><?php _e('Role Key', 'woocommerce-role-category-pricing'); ?></th>
                            <th><?php _e('Actions', 'woocommerce-role-category-pricing'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($custom_roles as $role_key => $role_data) : ?>
                            <?php if (isset($role_data['created_by_plugin']) && $role_data['created_by_plugin']) : ?>
                                <tr>
                                    <td><?php echo esc_html($role_data['display_name']); ?></td>
                                    <td><code><?php echo esc_html($role_key); ?></code></td>
                                    <td>
                                        <button type="button" class="button button-small button-link-delete remove-custom-role" 
                                                data-role="<?php echo esc_attr($role_key); ?>">
                                            <?php _e('Remove', 'woocommerce-role-category-pricing'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="wrcp-roles-configuration">
        <h3><?php _e('Role Configuration', 'woocommerce-role-category-pricing'); ?></h3>
        
        <div class="wrcp-notice notice-info">
            <p><?php _e('Enable roles for category-based pricing and configure base discount percentages. Wholesale customer roles are automatically excluded.', 'woocommerce-role-category-pricing'); ?></p>
        </div>
        
        <?php if (empty($configurable_roles)) : ?>
            <div class="wrcp-notice notice-warning">
                <p><?php _e('No configurable roles found. Make sure you have user roles other than Administrator and Wholesale Customer.', 'woocommerce-role-category-pricing'); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ($configurable_roles as $role_key => $role_data) : ?>
                <?php 
                $role_config = $this->role_manager->get_role_configuration($role_key);
                $is_enabled = $role_config['enabled'];
                $base_discount = $role_config['base_discount'];
                $role_shipping_methods = $role_config['shipping_methods'];
                ?>
                
                <div class="wrcp-role-config" data-role="<?php echo esc_attr($role_key); ?>">
                    <div class="wrcp-role-header">
                        <label>
                            <input type="checkbox" name="roles[<?php echo esc_attr($role_key); ?>][enabled]" 
                                   value="1" <?php checked($is_enabled); ?> class="role-enable-checkbox">
                            <?php echo esc_html($role_data['name']); ?>
                            <small style="font-weight: normal; color: #666;">(<?php echo esc_html($role_key); ?>)</small>
                        </label>
                    </div>
                    
                    <div class="wrcp-role-content" style="<?php echo $is_enabled ? '' : 'display: none;'; ?>">
                        <div class="wrcp-field-group">
                            <label for="base_discount_<?php echo esc_attr($role_key); ?>">
                                <?php _e('Base Discount Percentage', 'woocommerce-role-category-pricing'); ?>
                            </label>
                            <input type="number" 
                                   id="base_discount_<?php echo esc_attr($role_key); ?>"
                                   name="roles[<?php echo esc_attr($role_key); ?>][base_discount]" 
                                   value="<?php echo esc_attr($base_discount); ?>"
                                   min="0" max="100" step="0.01"
                                   placeholder="0.00">
                            <p class="description">
                                <?php _e('Default discount percentage applied when no category-specific discount is found. Leave empty for no base discount.', 'woocommerce-role-category-pricing'); ?>
                            </p>
                        </div>
                        
                        <div class="wrcp-field-group">
                            <label><?php _e('Available Shipping Methods', 'woocommerce-role-category-pricing'); ?></label>
                            <?php if (!empty($shipping_methods)) : ?>
                                <div class="wrcp-checkbox-group">
                                    <?php foreach ($shipping_methods as $method_id => $method_title) : ?>
                                        <label>
                                            <input type="checkbox" 
                                                   name="roles[<?php echo esc_attr($role_key); ?>][shipping_methods][]" 
                                                   value="<?php echo esc_attr($method_id); ?>"
                                                   <?php checked(in_array($method_id, $role_shipping_methods)); ?>>
                                            <?php echo esc_html($method_title); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">
                                    <?php _e('Select which shipping methods are available for this role. Leave unchecked to allow all methods.', 'woocommerce-role-category-pricing'); ?>
                                </p>
                            <?php else : ?>
                                <p class="description">
                                    <?php _e('No shipping methods configured. Set up shipping methods in WooCommerce settings first.', 'woocommerce-role-category-pricing'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($configurable_roles)) : ?>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" 
                   value="<?php esc_attr_e('Save Role Configuration', 'woocommerce-role-category-pricing'); ?>">
        </p>
    <?php endif; ?>
</form>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle role enable/disable
    $('.role-enable-checkbox').on('change', function() {
        var $content = $(this).closest('.wrcp-role-config').find('.wrcp-role-content');
        if ($(this).is(':checked')) {
            $content.slideDown();
        } else {
            $content.slideUp();
        }
    });
    
    // Handle add custom role
    $('#add_custom_role').on('click', function() {
        var roleName = $('#new_role_name').val().trim();
        if (!roleName) {
            alert(wrcp_admin.strings.role_name_required);
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text(wrcp_admin.strings.saving).prop('disabled', true);
        
        $.ajax({
            url: wrcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wrcp_add_custom_role',
                role_name: roleName,
                nonce: wrcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || wrcp_admin.strings.error);
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
    
    // Handle remove custom role
    $('.remove-custom-role').on('click', function() {
        if (!confirm(wrcp_admin.strings.confirm_delete)) {
            return;
        }
        
        var roleKey = $(this).data('role');
        var $button = $(this);
        var $row = $button.closest('tr');
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: wrcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wrcp_remove_custom_role',
                role_key: roleKey,
                nonce: wrcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $row.remove();
                        // Reload page if no more custom roles
                        if ($('.wrcp-custom-roles-list tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data || wrcp_admin.strings.error);
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert(wrcp_admin.strings.error);
                $button.prop('disabled', false);
            }
        });
    });
    
    // Enter key support for add role
    $('#new_role_name').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#add_custom_role').click();
        }
    });
});
</script>