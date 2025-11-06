/**
 * Frontend JavaScript for WooCommerce Role Category Pricing plugin
 *
 * Handles dynamic price updates for variable products when variations are selected.
 *
 * @package WRCP
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WRCP Frontend Handler
     */
    var WRCP_Frontend = {
        
        /**
         * Initialize the frontend functionality
         */
        init: function() {
            this.bindEvents();
            this.initVariationPricing();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle variation selection changes
            $(document).on('found_variation', 'form.variations_form', this.handleVariationFound);
            $(document).on('reset_data', 'form.variations_form', this.handleVariationReset);
            
            // Handle variation form initialization
            $(document).on('wc_variation_form', this.initVariationForm);
        },
        
        /**
         * Initialize variation pricing on page load
         */
        initVariationPricing: function() {
            var self = this;
            
            // Process existing variation forms
            $('form.variations_form').each(function() {
                self.initVariationForm.call(this);
            });
        },
        
        /**
         * Initialize individual variation form
         */
        initVariationForm: function() {
            var $form = $(this);
            var $priceContainer = $form.closest('.product').find('.price');
            
            // Store original price HTML for reset functionality
            if (!$priceContainer.data('wrcp-original-price')) {
                $priceContainer.data('wrcp-original-price', $priceContainer.html());
            }
        },
        
        /**
         * Handle when a variation is found/selected
         *
         * @param {Event} event jQuery event object
         * @param {Object} variation Variation data from WooCommerce
         */
        handleVariationFound: function(event, variation) {
            var $form = $(event.target);
            var $product = $form.closest('.product');
            var $priceContainer = $product.find('.price');
            
            // Skip if no WRCP pricing data
            if (!variation.wrcp_price_html) {
                return;
            }
            
            // Update price display with WRCP pricing
            $priceContainer.html(variation.wrcp_price_html);
            
            // Add variation-specific class
            $priceContainer.addClass('wrcp-variation-price');
            
            // Trigger custom event for other plugins/themes
            $form.trigger('wrcp_variation_price_updated', [variation]);
        },
        
        /**
         * Handle variation reset (when "Clear" is clicked)
         *
         * @param {Event} event jQuery event object
         */
        handleVariationReset: function(event) {
            var $form = $(event.target);
            var $product = $form.closest('.product');
            var $priceContainer = $product.find('.price');
            
            // Restore original price HTML
            var originalPrice = $priceContainer.data('wrcp-original-price');
            if (originalPrice) {
                $priceContainer.html(originalPrice);
            }
            
            // Remove variation-specific class
            $priceContainer.removeClass('wrcp-variation-price');
            
            // Trigger custom event
            $form.trigger('wrcp_variation_price_reset');
        },
        
        /**
         * Get WRCP price data for a specific variation
         *
         * @param {number} variationId Variation ID
         * @param {Function} callback Callback function to handle the response
         */
        getVariationPriceData: function(variationId, callback) {
            if (!variationId || !wrcp_frontend_params.ajax_url) {
                return;
            }
            
            $.ajax({
                url: wrcp_frontend_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wrcp_get_variation_price',
                    variation_id: variationId,
                    nonce: wrcp_frontend_params.nonce
                },
                success: function(response) {
                    if (response.success && callback) {
                        callback(response.data);
                    }
                },
                error: function() {
                    console.log('WRCP: Error fetching variation price data');
                }
            });
        },
        
        /**
         * Update price display with animation
         *
         * @param {jQuery} $container Price container element
         * @param {string} newPriceHtml New price HTML
         */
        updatePriceWithAnimation: function($container, newPriceHtml) {
            $container.fadeOut(200, function() {
                $(this).html(newPriceHtml).fadeIn(200);
            });
        },
        
        /**
         * Format price for display
         *
         * @param {Object} priceData Price data object
         * @return {string} Formatted price HTML
         */
        formatPriceDisplay: function(priceData) {
            if (!priceData.has_discount) {
                return priceData.original_price_html;
            }
            
            var template = '<span class="wrcp-price-container" data-role="' + priceData.role + '">' +
                          '<del class="wrcp-original-price">' + priceData.original_price_html + '</del> ' +
                          '<ins class="wrcp-discounted-price">' + priceData.discounted_price_html + '</ins>' +
                          '<br><small class="wrcp-role-label">' + priceData.role_display + ' Price</small>';
            
            if (priceData.savings_html) {
                template += '<small class="wrcp-savings-info">' + priceData.savings_html + '</small>';
            }
            
            template += '</span>';
            
            return template;
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WRCP_Frontend.init();
    });
    
    // Make WRCP_Frontend globally available
    window.WRCP_Frontend = WRCP_Frontend;

})(jQuery);