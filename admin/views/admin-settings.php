<?php
/**
 * Admin settings page template
 *
 * @package WRCP
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
$this->display_admin_notices();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wrcp-admin-header">
        <p class="description">
            <?php _e('Configure role-based category pricing for your WooCommerce store. This plugin works alongside WooCommerce Wholesale Prices to provide flexible discount structures.', 'woocommerce-role-category-pricing'); ?>
        </p>
    </div>
    
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_data) : ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, admin_url('admin.php?page=' . $this->page_slug))); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_data['title']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="tab-content">
        <?php if (isset($tabs[$current_tab])) : ?>
            <div class="tab-description">
                <p><?php echo esc_html($tabs[$current_tab]['description']); ?></p>
            </div>
        <?php endif; ?>
        
        <?php
        switch ($current_tab) {
            case 'roles':
                $this->render_role_configuration_tab();
                break;
                
            case 'categories':
                $this->render_category_discounts_tab();
                break;
                
            case 'import-export':
                $this->render_import_export_tab();
                break;
                
            default:
                $this->render_role_configuration_tab();
                break;
        }
        ?>
    </div>
</div>

<style>
.wrcp-admin-header {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.tab-content {
    margin-top: 20px;
}

.tab-description {
    margin-bottom: 20px;
    padding: 10px 15px;
    background: #fff;
    border-left: 4px solid #0073aa;
}

.wrcp-form-table {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
}

.wrcp-form-table th {
    background: #f9f9f9;
    border-bottom: 1px solid #ddd;
    padding: 15px;
    font-weight: 600;
}

.wrcp-form-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.wrcp-form-table tr:last-child td {
    border-bottom: none;
}

.wrcp-role-config {
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}

.wrcp-role-header {
    background: #f9f9f9;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    font-weight: 600;
}

.wrcp-role-content {
    padding: 15px;
}

.wrcp-field-group {
    margin-bottom: 15px;
}

.wrcp-field-group:last-child {
    margin-bottom: 0;
}

.wrcp-field-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.wrcp-field-group input[type="number"],
.wrcp-field-group input[type="text"],
.wrcp-field-group select {
    width: 100%;
    max-width: 300px;
}

.wrcp-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}

.wrcp-checkbox-group label {
    display: flex;
    align-items: center;
    margin-bottom: 0;
    font-weight: normal;
    white-space: nowrap;
}

.wrcp-checkbox-group input[type="checkbox"] {
    margin-right: 5px;
}

.wrcp-category-tree {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    background: #fff;
}

.wrcp-category-item {
    display: flex;
    align-items: center;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.wrcp-category-item:last-child {
    border-bottom: none;
}

.wrcp-category-name {
    flex: 1;
    padding-left: 0;
}

.wrcp-category-name.level-1 { padding-left: 20px; }
.wrcp-category-name.level-2 { padding-left: 40px; }
.wrcp-category-name.level-3 { padding-left: 60px; }
.wrcp-category-name.level-4 { padding-left: 80px; }

.wrcp-category-discount {
    width: 80px;
    margin-left: 10px;
}

.wrcp-bulk-actions {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.wrcp-bulk-actions .button {
    margin-right: 10px;
}

.wrcp-import-export-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.wrcp-import-export-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

.wrcp-file-input {
    margin-bottom: 10px;
}

.wrcp-notice {
    padding: 10px 15px;
    margin-bottom: 15px;
    border-radius: 4px;
}

.wrcp-notice.notice-info {
    background: #e7f3ff;
    border-left: 4px solid #0073aa;
}

.wrcp-notice.notice-warning {
    background: #fff8e5;
    border-left: 4px solid #ffb900;
}

.wrcp-loading {
    opacity: 0.6;
    pointer-events: none;
}

.wrcp-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-left: 5px;
    background: url('<?php echo admin_url('images/spinner.gif'); ?>') no-repeat;
    background-size: 16px 16px;
}

@media (max-width: 782px) {
    .wrcp-checkbox-group {
        flex-direction: column;
    }
    
    .wrcp-field-group input[type="number"],
    .wrcp-field-group input[type="text"],
    .wrcp-field-group select {
        max-width: 100%;
    }
    
    .wrcp-category-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .wrcp-category-discount {
        margin-left: 0;
        margin-top: 5px;
        width: 100px;
    }
}
</style>