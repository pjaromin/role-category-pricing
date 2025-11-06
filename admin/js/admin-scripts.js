/**
 * Admin JavaScript for WooCommerce Role Category Pricing plugin
 *
 * @package WRCP
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        WRCP_Admin.init();
    });
    
    /**
     * Main admin object
     */
    var WRCP_Admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initValidation();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Role configuration events
            this.bindRoleEvents();
            
            // Category discount events
            this.bindCategoryEvents();
            
            // Import/Export events
            this.bindImportExportEvents();
            
            // General form events
            this.bindFormEvents();
        },
        
        /**
         * Bind role configuration events
         */
        bindRoleEvents: function() {
            var self = this;
            
            // Handle role enable/disable
            $(document).on('change', '.role-enable-checkbox', function() {
                var $content = $(this).closest('.wrcp-role-config').find('.wrcp-role-content');
                if ($(this).is(':checked')) {
                    $content.slideDown(300);
                } else {
                    $content.slideUp(300);
                }
            });
            
            // Handle add custom role
            $(document).on('click', '#add_custom_role', function() {
                self.handleAddCustomRole($(this));
            });
            
            // Handle remove custom role
            $(document).on('click', '.remove-custom-role', function() {
                self.handleRemoveCustomRole($(this));
            });
            
            // Enter key support for add role
            $(document).on('keypress', '#new_role_name', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#add_custom_role').click();
                }
            });
            
            // Validate discount inputs in real-time
            $(document).on('input', 'input[name*="base_discount"]', function() {
                self.validateDiscountInput($(this));
            });
        },
        
        /**
         * Bind category discount events
         */
        bindCategoryEvents: function() {
            var self = this;
            
            // Handle select all categories
            $(document).on('change', '#select_all_categories', function() {
                $('.category-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            // Handle individual category checkboxes
            $(document).on('change', '.category-checkbox', function() {
                var allChecked = $('.category-checkbox:checked').length === $('.category-checkbox').length;
                $('#select_all_categories').prop('checked', allChecked);
            });
            
            // Handle bulk discount application
            $(document).on('click', '#apply_bulk_discount', function() {
                self.handleBulkDiscount($(this), false);
            });
            
            // Handle bulk discount clearing
            $(document).on('click', '#clear_bulk_discount', function() {
                self.handleBulkDiscount($(this), true);
            });
            
            // Handle reset all discounts
            $(document).on('click', '#reset_all_discounts', function() {
                self.handleResetAllDiscounts();
            });
            
            // Validate category discount inputs
            $(document).on('blur', '.wrcp-category-discount', function() {
                self.validateDiscountInput($(this));
            });
            
            // Auto-save category discounts on change (debounced)
            var saveTimeout;
            $(document).on('input', '.wrcp-category-discount', function() {
                var $input = $(this);
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    self.autoSaveCategoryDiscount($input);
                }, 1000);
            });
        },
        
        /**
         * Bind import/export events
         */
        bindImportExportEvents: function() {
            var self = this;
            
            // Validate import file
            $(document).on('change', '#import_file', function() {
                self.validateImportFile($(this));
            });
            
            // Handle export with loading state
            $(document).on('submit', 'form[action*="wrcp_export_settings"]', function() {
                var $button = $(this).find('input[type="submit"]');
                $button.val(wrcp_admin.strings.saving).prop('disabled', true);
            });
        },
        
        /**
         * Bind general form events
         */
        bindFormEvents: function() {
            var self = this;
            
            // Prevent double submission
            $(document).on('submit', 'form', function() {
                var $form = $(this);
                var $submitButton = $form.find('input[type="submit"], button[type="submit"]');
                
                if ($form.data('submitted')) {
                    return false;
                }
                
                $form.data('submitted', true);
                $submitButton.prop('disabled', true);
                
                // Re-enable after 5 seconds as fallback
                setTimeout(function() {
                    $form.data('submitted', false);
                    $submitButton.prop('disabled', false);
                }, 5000);
            });
            
            // Form validation before submit
            $(document).on('submit', '.wrcp-role-config-form, .wrcp-category-discounts-form', function() {
                return self.validateForm($(this));
            });
        },
        
        /**
         * Handle adding custom role
         */
        handleAddCustomRole: function($button) {
            var roleName = $('#new_role_name').val().trim();
            if (!roleName) {
                this.showAlert(wrcp_admin.strings.role_name_required, 'error');
                $('#new_role_name').focus();
                return;
            }
            
            var originalText = $button.text();
            this.setButtonLoading($button, true);
            
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
                        this.showAlert(response.data || wrcp_admin.strings.error, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showAlert(wrcp_admin.strings.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setButtonLoading($button, false, originalText);
                }.bind(this)
            });
        },
        
        /**
         * Handle removing custom role
         */
        handleRemoveCustomRole: function($button) {
            if (!confirm(wrcp_admin.strings.confirm_delete)) {
                return;
            }
            
            var roleKey = $button.data('role');
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
                        this.showAlert(response.data || wrcp_admin.strings.error, 'error');
                        $button.prop('disabled', false);
                    }
                }.bind(this),
                error: function() {
                    this.showAlert(wrcp_admin.strings.error, 'error');
                    $button.prop('disabled', false);
                }.bind(this)
            });
        },
        
        /**
         * Handle bulk discount operations
         */
        handleBulkDiscount: function($button, isClear) {
            var role = $('#bulk_role').val();
            var discount = isClear ? 0 : $('#bulk_discount').val();
            var selectedCategories = $('.category-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            // Validation
            if (!role) {
                this.showAlert('Please select a role.', 'error');
                return;
            }
            
            if (!isClear && (!discount || discount < 0 || discount > 100)) {
                this.showAlert(wrcp_admin.strings.invalid_discount, 'error');
                return;
            }
            
            if (selectedCategories.length === 0) {
                this.showAlert('Please select at least one category.', 'error');
                return;
            }
            
            if (isClear && !confirm(wrcp_admin.strings.confirm_reset)) {
                return;
            }
            
            var originalText = $button.text();
            this.setButtonLoading($button, true);
            
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
                            var $input = $('input[data-role="' + role + '"][data-category="' + categoryId + '"]');
                            $input.val(isClear ? '' : discount);
                        });
                        
                        // Clear selections
                        $('.category-checkbox').prop('checked', false);
                        $('#select_all_categories').prop('checked', false);
                        if (!isClear) {
                            $('#bulk_discount').val('');
                        }
                        
                        this.showAlert(response.data.message, 'success');
                    } else {
                        this.showAlert(response.data.message || wrcp_admin.strings.error, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showAlert(wrcp_admin.strings.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setButtonLoading($button, false, originalText);
                }.bind(this)
            });
        },
        
        /**
         * Handle reset all discounts
         */
        handleResetAllDiscounts: function() {
            if (!confirm('Are you sure you want to reset all category discounts? This cannot be undone.')) {
                return;
            }
            
            $('.wrcp-category-discount').val('');
            this.showAlert('All category discounts have been cleared. Remember to save the form.', 'info');
        },
        
        /**
         * Auto-save category discount
         */
        autoSaveCategoryDiscount: function($input) {
            var role = $input.data('role');
            var categoryId = $input.data('category');
            var discount = $input.val();
            
            // Skip if invalid
            if (!this.validateDiscountInput($input, true)) {
                return;
            }
            
            // Visual feedback
            $input.addClass('wrcp-auto-saving');
            
            $.ajax({
                url: wrcp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrcp_save_bulk_discounts',
                    role: role,
                    discount: discount || 0,
                    category_ids: [categoryId],
                    nonce: wrcp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $input.addClass('wrcp-auto-saved');
                        setTimeout(function() {
                            $input.removeClass('wrcp-auto-saved');
                        }, 2000);
                    }
                },
                complete: function() {
                    $input.removeClass('wrcp-auto-saving');
                }
            });
        },
        
        /**
         * Validate discount input
         */
        validateDiscountInput: function($input, silent) {
            var value = $input.val();
            var isValid = true;
            
            if (value !== '') {
                var numValue = parseFloat(value);
                if (isNaN(numValue) || numValue < 0 || numValue > 100) {
                    isValid = false;
                }
            }
            
            // Visual feedback
            $input.toggleClass('wrcp-invalid', !isValid);
            
            if (!isValid && !silent) {
                this.showAlert(wrcp_admin.strings.invalid_discount, 'error');
                $input.focus();
            }
            
            return isValid;
        },
        
        /**
         * Validate import file
         */
        validateImportFile: function($input) {
            var file = $input[0].files[0];
            if (!file) return;
            
            // Check file type
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                this.showAlert('Please select a valid JSON file.', 'error');
                $input.val('');
                return;
            }
            
            // Check file size (max 1MB)
            if (file.size > 1024 * 1024) {
                this.showAlert('File is too large. Maximum size is 1MB.', 'error');
                $input.val('');
                return;
            }
        },
        
        /**
         * Validate form before submission
         */
        validateForm: function($form) {
            var isValid = true;
            
            // Validate all discount inputs
            $form.find('input[type="number"]').each(function() {
                if (!WRCP_Admin.validateDiscountInput($(this), true)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                this.showAlert('Please fix the validation errors before saving.', 'error');
                $form.find('.wrcp-invalid').first().focus();
            }
            
            return isValid;
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to help icons if any
            $('[data-tooltip]').each(function() {
                var $this = $(this);
                $this.attr('title', $this.data('tooltip'));
            });
        },
        
        /**
         * Initialize validation
         */
        initValidation: function() {
            // Add validation classes
            $('input[type="number"]').addClass('wrcp-validate-discount');
            
            // Add required field indicators
            $('input[required], select[required]').each(function() {
                var $label = $('label[for="' + $(this).attr('id') + '"]');
                if ($label.length && $label.find('.required').length === 0) {
                    $label.append(' <span class="required" style="color: #d63638;">*</span>');
                }
            });
        },
        
        /**
         * Set button loading state
         */
        setButtonLoading: function($button, loading, originalText) {
            if (loading) {
                $button.data('original-text', $button.text());
                $button.text(wrcp_admin.strings.saving).prop('disabled', true);
                if ($button.next('.wrcp-spinner').length === 0) {
                    $button.after('<span class="wrcp-spinner"></span>');
                }
            } else {
                var text = originalText || $button.data('original-text') || $button.text();
                $button.text(text).prop('disabled', false);
                $button.next('.wrcp-spinner').remove();
            }
        },
        
        /**
         * Show alert message
         */
        showAlert: function(message, type) {
            type = type || 'info';
            
            // Remove existing alerts
            $('.wrcp-alert').remove();
            
            // Create alert
            var $alert = $('<div class="wrcp-alert notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after page title
            $('.wrap h1').after($alert);
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $alert.fadeOut();
                }, 5000);
            }
            
            // Scroll to alert
            $('html, body').animate({
                scrollTop: $alert.offset().top - 50
            }, 300);
        },
        
        /**
         * Utility: Debounce function
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };
    
    // Make WRCP_Admin globally available
    window.WRCP_Admin = WRCP_Admin;
    
})(jQuery);