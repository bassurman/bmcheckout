window.method = null;
window.address_selected = null;
window.latestScroll = null;
var BillmateIframe = new function() {
    var self = this;
    var childWindow = null;
    this.init = function(options) {
        var settings = jQuery.extend({
            loadingBlockId: '#checkout-loader',
            shippingContainer: '#shipping-container',
            estimateMethodSelector: 'input[name="estimate_method"]',
            productQtySelector: '.qty',
            updateButtonSelector: '.btn-update',
            checkoutFrameSelector: '#checkoutdiv',
            discountCouponFormId: '#discount-coupon-form',
            discountCouponCode: '#coupon_code',
            eventOrigin: 'https://checkout.billmate.se',
        }, options );
        self.config = settings;
        self.initListeners();
        self.initQtyChangeListener();
        self.initCouponeFormListener();
        self.initUpdateShippingListener();
    };

    this.initListeners = function () {
        document.observe('dom:loaded',function () {
            window.addEventListener("message", self.handleEvent);
        });
    };

    this.initQtyChangeListener = function() {
        jQuery(self.config.productQtySelector).on('change', function(e) {
            jQuery(self.config.productQtySelector)
                .closest('form')
                .append('<input name="return_url" type="hidden" value="' + self.config.checkout_url + '"/>');
            jQuery(self.config.updateButtonSelector).click();
            self.lock();
        });
    };

    this.initCouponeFormListener = function() {
        discountForm.submit = self.couponFormSubmit;
    };

    this.couponFormSubmit = function(isRemove) {

        var couponValue = jQuery(self.config.discountCouponCode);
        if (isRemove) {
            couponValue.removeClass('required-entry');
        } else {
            couponValue.addClass('required-entry');
        }

        if (discountForm.validator.validate()) {
             jQuery('.coupon-message').html('');
             var requestData = {
                 coupon_code: couponValue.val(),
                 remove:isRemove ? 1 : 0,
             };
             self.sendRequest(
                 self.config.discount_url,
                 requestData,
                 self.afterCouponApply
             );
         } else {
             return false;
         }
    };

    this.initUpdateShippingListener = function() {
        jQuery(self.config.estimateMethodSelector).on('change', function() {
            var method_code = jQuery(this).val();
            var requestData = {
                estimate_method: method_code
            };
            self.sendRequest(self.config.shipping_url, requestData)
        });
        self.setFirstDefaultShipping();
    };

    this.sendRequest = function(url, requestData, afterResponseEvent) {
        self.lock();
        jQuery.ajax({
            url : url,
            data: requestData,
            type: 'POST',
            success: function(response) {
                if (typeof(afterResponseEvent) == 'function') {
                    afterResponseEvent(response);
                }
            },
            complete: function () {
                self.update();
                self.unlock();
            }
        });
    };

    this.unlock = function() {
        setTimeout(
            function() {
                jQuery(self.config.loadingBlockId).removeClass('loading');
                self.checkoutPostMessage('unlock')
            }, 1000);
    };

    this.lock = function() {
        jQuery(self.config.loadingBlockId).addClass('loading');
        this.checkoutPostMessage('lock');
    };

    this.update = function() {
        this.checkoutPostMessage('update_checkout');
    };

    this.checkoutPostMessage = function(message) {
        var checkout = document.getElementById('checkout');
        if (checkout != null) {
            var win = checkout.contentWindow;
            win.postMessage(message,'*');
        }
    };

    this.afterAddressUpdate = function(response) {
        jQuery(self.config.shippingContainer).html(response);
        self.setFirstDefaultShipping();
        window.address_selected = true;
    };

    this.afterOrderCreate = function(response) {
        var result = response.evalJSON();
        location.href=result.url;
    };

    this.afterCouponApply = function(response) {
        var result = response.evalJSON();
        if (result.success) {
            jQuery('.coupon-message').html('<span class="success">' + result.message + '</span>');
            if (jQuery('#remove-coupone').val() == '0') {
                jQuery('#discount-cancel-button').show();
            } else {
                jQuery('#discount-cancel-button').hide();
            }
        } else {
            jQuery('.coupon-message').html('<span class="error">' + result.message + '</span>');
        }
    };

    this.updateTotals = function() {
        self.sendRequest(self.config.totals_url,{});
    };

    this.setFirstDefaultShipping = function() {
        if (jQuery(self.config.estimateMethodSelector + ':checked').length != 1) {
            jQuery(self.config.estimateMethodSelector + ':first').click();
        }
    };

    this.handleEvent = function(event){
        if(event.origin == self.config.eventOrigin) {
            try {
                var json = JSON.parse(event.data);
            } catch (e) {
                return;
            }
            self.childWindow = json.source;
            switch (json.event) {
                case 'address_selected':
                    self.sendRequest(
                        self.config.address_url,
                        json.data,
                        self.afterAddressUpdate
                    );
                    break;
                case 'checkout_success':
                    self.sendRequest(
                        self.config.order_url,
                        json.data,
                        self.afterOrderCreate
                    );
                    break;
                case 'content_height':
                    $('checkout').height = json.data;
                    break;
                case 'content_scroll_position':
                    window.latestScroll = jQuery(document).find( "#checkout" ).offset().top + json.data;
                    jQuery('html, body').animate({scrollTop: jQuery(document).find( "#checkout" ).offset().top + json.data}, 400);
                    break;
                case 'checkout_loaded':
                    jQuery(self.config.checkoutFrameSelector).removeClass('loading');
                    break;
                default:
                    console.log(event);
                    console.log('not implemented');
                    break;

            }
        };
    };
};