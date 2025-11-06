<?php
/**
 * Import/Export tab template
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrcp-import-export-sections">
    
    <div class="wrcp-import-export-section">
        <h3><?php _e('Export Settings', 'woocommerce-role-category-pricing'); ?></h3>
        <p><?php _e('Export your current role and category discount configurations to a JSON file for backup or transfer to another site.', 'woocommerce-role-category-pricing'); ?></p>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wrcp_export_settings', 'wrcp_nonce'); ?>
            <input type="hidden" name="action" value="wrcp_export_settings">
            
            <p class="submit">
                <input type="submit" name="export" class="button button-secondary" 
                       value="<?php esc_attr_e('Export Settings', 'woocommerce-role-category-pricing'); ?>">
            </p>
        </form>
    </div>
    
    <div class="wrcp-import-export-section">
        <h3><?php _e('Import Settings', 'woocommerce-role-category-pricing'); ?></h3>
        <p><?php _e('Import role and category discount configurations from a previously exported JSON file.', 'woocommerce-role-category-pricing'); ?></p>
        
        <div class="wrcp-notice notice-warning">
            <p><strong><?php _e('Warning:', 'woocommerce-role-category-pricing'); ?></strong> 
               <?php _e('Importing will overwrite your current settings. Make sure to export your current settings first if you want to keep them.', 'woocommerce-role-category-pricing'); ?></p>
        </div>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('wrcp_import_settings', 'wrcp_nonce'); ?>
            <input type="hidden" name="action" value="wrcp_import_settings">
            
            <div class="wrcp-field-group">
                <label for="import_file"><?php _e('Select JSON File', 'woocommerce-role-category-pricing'); ?></label>
                <input type="file" id="import_file" name="import_file" accept=".json" required class="wrcp-file-input">
                <p class="description">
                    <?php _e('Select a JSON file that was previously exported from this plugin.', 'woocommerce-role-category-pricing'); ?>
                </p>
            </div>
            
            <p class="submit">
                <input type="submit" name="import" class="button button-primary" 
                       value="<?php esc_attr_e('Import Settings', 'woocommerce-role-category-pricing'); ?>"
                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to import these settings? This will overwrite your current configuration.', 'woocommerce-role-category-pricing'); ?>');">
            </p>
        </form>
    </div>
    
    <div class="wrcp-import-export-section">
        <h3><?php _e('Backup & Restore', 'woocommerce-role-category-pricing'); ?></h3>
        
        <?php
        $backup_info = $this->role_manager->get_backup_info();
        ?>
        
        <?php if ($backup_info) : ?>
            <div class="wrcp-backup-info">
                <h4><?php _e('Available Backup', 'woocommerce-role-category-pricing'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th style="width: 30%;"><?php _e('Backup Date', 'woocommerce-role-category-pricing'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($backup_info['backup_date']))); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Plugin Version', 'woocommerce-role-category-pricing'); ?></th>
                            <td><?php echo esc_html($backup_info['plugin_version']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Has Settings', 'woocommerce-role-category-pricing'); ?></th>
                            <td><?php echo $backup_info['has_settings'] ? __('Yes', 'woocommerce-role-category-pricing') : __('No', 'woocommerce-role-category-pricing'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;">
                    <?php wp_nonce_field('wrcp_restore_backup', 'wrcp_nonce'); ?>
                    <input type="hidden" name="action" value="wrcp_restore_backup">
                    
                    <p class="submit">
                        <input type="submit" name="restore" class="button button-secondary" 
                               value="<?php esc_attr_e('Restore from Backup', 'woocommerce-role-category-pricing'); ?>"
                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to restore from backup? This will overwrite your current settings.', 'woocommerce-role-category-pricing'); ?>');">
                    </p>
                </form>
            </div>
        <?php else : ?>
            <p><?php _e('No backup available. A backup will be automatically created before importing settings or resetting.', 'woocommerce-role-category-pricing'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="wrcp-import-export-section">
        <h3><?php _e('Reset Settings', 'woocommerce-role-category-pricing'); ?></h3>
        <p><?php _e('Reset all plugin settings to their default values. This will remove all role configurations and category discounts.', 'woocommerce-role-category-pricing'); ?></p>
        
        <div class="wrcp-notice notice-warning">
            <p><strong><?php _e('Warning:', 'woocommerce-role-category-pricing'); ?></strong> 
               <?php _e('A backup will be created automatically before resetting. You can restore from the backup if needed.', 'woocommerce-role-category-pricing'); ?></p>
        </div>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wrcp_reset_settings', 'wrcp_nonce'); ?>
            <input type="hidden" name="action" value="wrcp_reset_settings">
            
            <p class="submit">
                <input type="submit" name="reset" class="button button-secondary" 
                       value="<?php esc_attr_e('Reset All Settings', 'woocommerce-role-category-pricing'); ?>"
                       onclick="return confirm('<?php esc_attr_e('Are you absolutely sure you want to reset all settings? A backup will be created automatically.', 'woocommerce-role-category-pricing'); ?>');">
            </p>
        </form>
    </div>
    
    <div class="wrcp-import-export-section">
        <h3><?php _e('Current Configuration Summary', 'woocommerce-role-category-pricing'); ?></h3>
        
        <?php
        $enabled_roles = $this->get_enabled_roles_for_display();
        $all_configs = $this->role_manager->get_all_role_configurations();
        $custom_roles = $this->role_manager->get_custom_roles();
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr>
                    <th style="width: 30%;"><?php _e('Enabled Roles', 'woocommerce-role-category-pricing'); ?></th>
                    <td><?php echo count($enabled_roles); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Total Role Configurations', 'woocommerce-role-category-pricing'); ?></th>
                    <td><?php echo count($all_configs); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Custom Roles Created', 'woocommerce-role-category-pricing'); ?></th>
                    <td><?php echo count(array_filter($custom_roles, function($role) { return isset($role['created_by_plugin']) && $role['created_by_plugin']; })); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Total Category Discounts', 'woocommerce-role-category-pricing'); ?></th>
                    <td>
                        <?php
                        $total_discounts = 0;
                        foreach ($all_configs as $role_config) {
                            if (isset($role_config['category_discounts'])) {
                                $total_discounts += count($role_config['category_discounts']);
                            }
                        }
                        echo $total_discounts;
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Plugin Version', 'woocommerce-role-category-pricing'); ?></th>
                    <td><?php echo esc_html(WRCP_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Last Modified', 'woocommerce-role-category-pricing'); ?></th>
                    <td>
                        <?php
                        $settings = get_option('wrcp_settings', array());
                        if (isset($settings['last_modified'])) {
                            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($settings['last_modified'])));
                        } else {
                            _e('Unknown', 'woocommerce-role-category-pricing');
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if (!empty($enabled_roles)) : ?>
            <h4><?php _e('Enabled Roles Summary', 'woocommerce-role-category-pricing'); ?></h4>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Role', 'woocommerce-role-category-pricing'); ?></th>
                        <th><?php _e('Base Discount', 'woocommerce-role-category-pricing'); ?></th>
                        <th><?php _e('Category Discounts', 'woocommerce-role-category-pricing'); ?></th>
                        <th><?php _e('Shipping Methods', 'woocommerce-role-category-pricing'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enabled_roles as $role_key => $role_data) : ?>
                        <tr>
                            <td><?php echo esc_html($role_data['name']); ?></td>
                            <td><?php echo esc_html($role_data['base_discount']); ?>%</td>
                            <td><?php echo count($role_data['category_discounts']); ?></td>
                            <td><?php echo count($role_data['shipping_methods']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<?php
// Handle additional messages for import/export
if (isset($_GET['message'])) {
    $message = sanitize_key($_GET['message']);
    
    switch ($message) {
        case 'import_success':
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Settings imported successfully.', 'woocommerce-role-category-pricing') . 
                 '</p></div>';
            break;
            
        case 'import_error':
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 __('Failed to import settings. Please check the file and try again.', 'woocommerce-role-category-pricing') . 
                 '</p></div>';
            break;
            
        case 'invalid_json':
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 __('Invalid JSON file. Please select a valid export file.', 'woocommerce-role-category-pricing') . 
                 '</p></div>';
            break;
            
        case 'reset_success':
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('All settings have been reset to defaults. A backup was created automatically.', 'woocommerce-role-category-pricing') . 
                 '</p></div>';
            break;
            
        case 'reset_error':
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 __('Failed to reset settings. Please try again.', 'woocommerce-role-category-pricing') . 
                 '</p></div>';
            break;
            
        case 'restore_success':
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Settings restored from backup successfully.', 'woocommerce-role-category-pricing') . 
                 '</p></div>';
            break;
    }
}
?>