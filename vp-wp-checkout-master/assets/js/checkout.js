/**
 * @author Summa Solutions (c)
 */
window.VPCheckout = (function ($) {
    // Don't conflict if this file is added more than once
    if (window.VPCheckout) {
        return window.VPCheckout;
    }

    var VPCheckout = {
        data:{},
        version:function () {
            return VPParams.version;
        },
        /**
         * Generates a "unique" id
         *
         * @return string
         */
        id:(function () {
            var seed = 1;
            return function (prefix) {
                return (prefix || '') + (seed++)
            }
        })(),
        init:function () {
            if (!this.isEnabled()) {
                return;
            }

            var self = this;


            this.doLogout(function () {
                self.view.init();
                self.initEvents();
            });
        },
        error:function (e) {
            alert(e);
        },
        isEnabled:function () {
            if (this.view.isShopp()) {
                return !!$('option[value="virtualpiggy-com"]').size();
            } else {
                return !!$('#payment_method_virtual-piggy').size();
            }
        },
        initEvents:function () {
            var self, view;

            self = this;
            view = this.view;

            $(document)
                .delegate('.virtualpiggy-button, #payment_method_virtual-piggy', 'click', function () {
                    self.isLogged(function (isLogged, data) {
                        view.showContentBox();
                        if (!isLogged) {
                            view.showLoginBox();
                        } else {
                            view.showLoading();
                            self.afterLogin(isLogged, '', data);
                        }
                    });
                })
                .delegate('.virtualpiggy-button-cancel', 'click', function () {
                    view.reload();
                    view.hideContentBox();
                })
                .delegate('.virtualpiggy-button-login', 'click', function () {
                    self.doLogin();
                })
                .delegate('.vp-select-child-button', 'click', function () {
                    self.doChildSelection();
                })
                .delegate('.vp-select-payment-button', 'click', function () {
                    self.doPaymentSelection();
                })
                .delegate('.virtualpiggy-button-place-order', 'click', function () {
                    self.placeOrder();
                })
                .delegate('#payment_method_virtual-piggy', 'change', function () {
                    VPCheckout.view.hidePaymentOptions();
                    VPCheckout.view.hideShippingForm();
                })
                .delegate('.virtualpiggy-form-login input', 'keyup', function (e) {
                    if (e.which == 13) {
                        self.doLogin();
                    }
                })
        },
        isLogged:function (callback) {
            if (!window.VPCheckout) {
                window.VPCheckout.init();
            }
            $.ajax({
                url:'?vp_action=get_data',
                complete:function (xhr) {
                    var json = $.parseJSON(xhr.responseText);

                    (callback || $.noop)(!!json.data, json.data)
                }
            });
        },
        doLogin:function () {
            var user, pass;

            if (!window.VPCheckout) {
                window.VPCheckout.init();
            }

            user = $('#vp-username').val();
            pass = $('#vp-password').val();

            if (!user || !pass) {
                return;
            }

            this.view.showLoading();

            var self = this;

            $.ajax({
                url:'?vp_action=login',
                data:{
                    username:user,
                    password:pass
                },
                complete:function (xhr) {
                    var json = $.parseJSON(xhr.responseText);

                    self.afterLogin(json.success, json.message, json.data);
                }
            });
        },
        doLogout:function (cb) {
            $.ajax({
                url:'?vp_action=logout',
                complete:function (xhr) {
                    (cb || function () {
                    })();
                }
            });
        },
        doChildSelection:function () {
            this.data.selectedChild = $('#vp-select-child').val();

            if (this.isParent()) {
                this.view.showPaymentSelector(this.data.payment);
            }
        },
        doPaymentSelection:function () {
            this.data.selectedPayment = $('.virtualpiggy-payment-row input:checked').val();

            if (!this.data.selectedPayment) {
                return this.error('Select a payement option.');
            }

            this.view.showLoading();

            this.fetchShippingAddress();
        },
        validateShippingAddress:function (data) {
            return data.Address && data.City && data.Zip && data.Country && data.State;
        },
        fetchShippingAddress:function () {
            var self = this, data, callback;

            if (!window.VPCheckout) {
                window.VPCheckout.init();
            }

            data = {};

            if (this.isParent()) {
                data.child = this.data.child || this.data.selectedChild;
            }

            callback = function (response) {
                response.data.name = self.data.selectedChild;
                self.lastData = response.data;

                self.view.hideContentBox();

                if (!self.validateShippingAddress(response.data)) {
                    alert('The address provided is invalid. Please check your VirtualPiggy configuration.');
                    return;
                }

                self.view.populateShippingForm(response.data);
                self.view.showShippingAddress(response.data);
            };


            this.setOptions(VPCheckout.shippingAddressFetcher || function () {
                if (!window.VPCheckout) {
                    window.VPCheckout.init();
                }
                $.ajax({
                    url:'?vp_action=get_shipping_details',
                    data:data,
                    complete:function (xhr) {
                        var json = $.parseJSON(xhr.responseText);

                        callback(json);
                    }
                });
            });
        },
        setOptions:function (cb) {
            if (!window.VPCheckout) {
                window.VPCheckout.init();
            }
            $.ajax({
                url:'?vp_action=set_options',
                data:{
                    child:this.data.selectedChild,
                    payment:this.data.selectedPayment
                },
                complete:cb || $.noop
            });
        },
        afterLogin:function (success, message, data) {
            if (!success) {
                this.view.hideContentBox();
                alert(message);
                return;
            }

            this.view.hideShippingForm();
            this.view.hidePaymentOptions();
            this.view.hideLauncherButton();

            this.data = data;

            if (this.isParent()) {
                this.view.showChildSelector(this.data.childs);
            } else {
                this.fetchShippingAddress();
            }
        },
        isParent:function () {
            try {
                return this.data.role == 'Parent';
            } catch (e) {
                return false;
            }
        }
    };

    VPCheckout.view = {
        LOADING_URL:'/wp-content/plugins/vp-wp-checkout/assets/images/loading.gif',
        BUTTON_URL:'/wp-content/plugins/vp-wp-checkout/assets/images/virtualpiggy/vp-button.png',
        LOGO_URL:'/wp-content/plugins/vp-wp-checkout/assets/images/virtualpiggy/logo-virtual-piggy.png',
        $form:null,
        $contentBox:null,
        init:function () {
            this.LOADING_URL = VPParams.baseURL + this.LOADING_URL;
            this.BUTTON_URL = VPParams.baseURL + this.BUTTON_URL;
            this.LOGO_URL = VPParams.baseURL + this.LOGO_URL;

            this.$form = $('form.checkout, form#checkout');
            this.addLauncherButton();
        },
        getContentBox:function () {
            if (this.$contentBox) {
                return this.$contentBox
            }

            this.$contentBox = $('<div/>')
                .hide()
                .addClass('virtualpiggy-loginbox');

            this.$contentBox.append(this.createLogo({
                width:260
            }));

            this.$contentBox.appendTo(document.body);

            return this.$contentBox;
        },
        showLoginBox:function () {
            this.cleanBox();

            var $username = $('<input/>')
                .attr('type', 'text')
                .attr('id', 'vp-username');

            var $password = $('<input/>')
                .attr('type', 'password')
                .attr('id', 'vp-password');

            var $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-login')
                .append(this.createLabel($username, 'Username'))
                .append(this.createLabel($password, 'Password'));

            var $loginButton = $('<button/>').addClass('virtualpiggy-button-login').html('Login');

            this.addButton($loginButton);
            this.addCancelButton();
            this.$contentBox.append($fields);

            $username.focus();
        },
        getButtonContainer:function () {
            var $contentBox = this.getContentBox();

            if (!$contentBox.find('.vp-button-container').size()) {
                $contentBox.append(
                    $('<div/>').addClass('vp-button-container')
                );
            }

            return $contentBox.find('.vp-button-container');
        },
        addLauncherButton:function () {
            var $button = $('<img/>')
                .attr('src', this.BUTTON_URL)
                .addClass('virtualpiggy-button');

            this.$form.prepend($button);
        },
        createLogo:function (attrs) {
            attrs = attrs || {};

            var $image = $('<img/>').attr('src', this.LOGO_URL);

            $.each(attrs, function (key, value) {
                $image.attr(key, value);
            });

            return $image;
        },
        createLabel:function ($field, label) {
            var $container, $label;

            $container = $('<div/>').addClass('virtualpiggy-field-row');
            $label = $('<label/>').attr('for', $field.attr('id')).html(label || '');

            $container
                .append($label)
                .append($field)
                .append('<div class="clearfix"></div>');

            return $container;
        },
        showContentBox:function () {
            var contentBox = this.getContentBox();

            this.center(contentBox);

            contentBox.show();
        },
        hideContentBox:function () {
            this.getContentBox().hide();
        },
        showLoading:function () {
            var $contentBox = this.getContentBox();

            this.cleanBox();

            $contentBox.append(
                $('<div/>').addClass('vp-loading-container').append(
                    $('<img/>').attr('src', this.LOADING_URL)
                )
            );
        },
        addButton:function (btn) {
            this.getButtonContainer().append(btn);
        },
        addCancelButton:function () {
            this.addButton(
                $('<button/>').addClass('virtualpiggy-button-cancel').html('Cancel')
            )
        },
        addPlaceOrderButton:function () {
            this.addButton(
                $('<button/>').addClass('virtualpiggy-button-place-order').html('Place Order')
            )
        },
        createPaymentOption:function (value, img) {
            var $container, $field, $label, id;

            id = VPCheckout.id('payment-');

            $container = $('<div/>').addClass('virtualpiggy-payment-row');

            $field = $('<input/>')
                .attr('type', 'radio')
                .attr('value', value)
                .attr('id', id)
                .attr('name', '_vp_payment_option');

            $label = $('<img/>')
                .attr('src', img);

            $container
                .append($field)
                .append($label)
                .append('<div class="clearfix"></div>');

            return $container;

        },
        center:function ($el) {
            if (!$el || !$el.css) return;
            $el.css("position", "absolute");
            $el.css("top", Math.max(0, (($(window).height() - $el.outerHeight()) / 2) +
                $(window).scrollTop()) + "px");
            $el.css("left", Math.max(0, (($(window).width() - $el.outerWidth()) / 2) +
                $(window).scrollLeft()) + "px");
        },
        centerContentBox:function () {
            this.center(this.getContentBox);
        },
        showChildSelector:function (childs) {
            this.cleanBox();

            var $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-child');

            var $select = $('<select/>')
                .attr('id', 'vp-select-child');

            $.each(childs, function (key, value) {
                $select.append($('<option/>').html(value).attr('value', value));
            });

            var $next = $('<button/>')
                .html('Next')
                .addClass('vp-select-child-button');

            this.addButton($next);
            this.addCancelButton();

            $fields.append(this.createLabel($select, 'This transaction is for'));

            this.getContentBox().append($fields);
        },
        showPaymentSelector:function (payments) {
            var $fields, $radios, self;

            this.cleanBox();
            self = this;

            $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-payment');

            $radios = $('<div/>')
                .attr('id', 'vp-select-payment');

            $.each(payments, function (key, value) {
                $radios.append(self.createPaymentOption(key, value))
            });

            var $next = $('<button/>')
                .html('Next')
                .addClass('vp-select-payment-button');

            this.addButton($next);
            this.addCancelButton();

            $fields.append(this.createLabel($radios, 'Select the payment method'));

            this.getContentBox().append($fields);
        },
        cleanBox:function () {
            this.getContentBox().children(':not(img)').remove();
        },
        hideShippingForm:function () {
            if (this.isShopp()) {
                $('#checkout ul').hide();
            } else {
                $('#customer_details').hide();
                this.hideWooCommerceShippingForm();
            }
        },
        hideWooCommerceShippingForm:function () {
            var fields = VPCheckout.view.getWooCommerceFilledForm({});

            fields['#billing_company'] = '';
            fields['#billing_address_2'] = '';
//            fields['#shiptobilling-checkbox'] = '';


            for (var f in fields) {
                $(f).hide();
                $('label[for=' + f.replace(/#/, '') + ']').hide();
            }

            $('#shiptobilling-checkbox').hide();
            $('label[for=shiptobilling-checkbox]').hide();
            $('#payment_method_virtual-piggy').attr('checked', true);
        },
        hidePaymentOptions:function () {
            if (this.isShopp()) {
                $('select[name=paymethod]').val('virtualpiggy-com');
            } else {
                $('.payment_methods').hide();
            }
        },
        hideLauncherButton:function () {
            $('img.virtualpiggy-button').hide();
        },
        showShippingAddress:function (data) {
            var address = '';

            address += data.Address;
            address += ', ';
            address += data.City;
            address += ', ';
            address += data.State;
            address += ', ';
            address += data.Country;
            address += ' (';
            address += data.Zip;
            address += ')';

            if (this.isShopp()) {
                $('#cart')
                    .before($('<h3/>').html('Shipping Address'))
                    .before($('<p/>').html(address));
            } else {
                $('#order_review_heading')
                    .before($('<h3/>').html('Shipping Address'))
                    .before($('<p/>').html(address));
            }

            this.hideLauncherButton();
            this.selectVirtualPiggyPaymentMethod();
            this.centerContentBox();
        },
        isShopp:function () {
            return $('#shopp').size() > 0;
        },
        getWooCommerceFilledForm:function (data) {
            return {
                '#billing_first_name':data.ParentName || VPCheckout.data.name,
                '#billing_last_name':data.ParentLastName || '-',
                '#billing_address_1':data.Address,
                '#billing_city':data.City,
                '#billing_postcode':data.Zip,
                '#billing_country':data.Country,
                '#billing_state':data.State,
                '#billing_email':data.ParentEmail,
                '#billing_phone':data.Phone || '0000000000'
            };
        },
        getShoppFilledForm:function (data) {
            return {
                '#firstname':data.ParentName || VPCheckout.data.name,
                '#lastname':data.ParentLastName || '-',
                '#billing-name':data.ParentName + ' ' + data.ParentLastName,
                '#billing-address':data.Address,
                '#billing-city':data.City,
                '#billing-postcode':data.Zip,
                '#billing-country':data.Country,
                '#billing-state-menu':data.State,
                '#email':data.ParentEmail,
                '#phone':data.Phone || '0000000000'
            };
        },
        getFilledForm:function (data) {
            var parent;

            if(!data.ParentLastName) {
                parent = (data.ParentName || '').split(/\s/);

                data.ParentName = parent.shift();
                data.ParentLastName = parent.join(' ');
            }

            if (this.isShopp()) {
                return this.getShoppFilledForm(data)
            } else {
                return this.getWooCommerceFilledForm(data)
            }
        },
        populateShippingForm:function (data) {
            var dict = this.getFilledForm(data);

            $.each(dict, function (key, value) {
                $(key).val(value);
            });

            try {
                $("#billing_country").trigger("liszt:updated");
            } catch (e) {
            }
        },
        selectVirtualPiggyPaymentMethod:function () {
            $('#payment_method_virtual-piggy').attr('checked', true);
            VPCheckout.view.hidePaymentOptions();
            VPCheckout.view.hideShippingForm();
        },
        reload:function () {
            window.location.reload();
        },
        isVPSelected:function () {
            var selected = $('.payment_methods input[type=radio]:checked');

            return selected.val() == 'virtual-piggy';
        }
    };

    VPCheckout.on = {};
    for (var prop in VPCheckout) {
        if (VPCheckout.hasOwnProperty(prop) && typeof VPCheckout[prop] == 'function') {
            (function (name) {
                var original = VPCheckout[name];

                VPCheckout[name] = function () {
                    var args = arguments || [];
                    if (VPCheckout[name].pre && typeof VPCheckout[name].pre == 'function') {
                        args = VPCheckout[name].pre.apply(VPCheckout, args) || arguments;
                    }

                    var value = original.apply(VPCheckout, args);

                    if (VPCheckout[name].post && typeof VPCheckout[name].post == 'function') {
                        args = [value, args];

                        value = VPCheckout[name].post.apply(VPCheckout, args) || arguments;
                    }

                    return value;
                };
            })(prop);
        }
    }

    $(function () {
        if (VPCheckout.view.isShopp()) {
            VPCheckout.init();
            return;
        }

        window.intervalId = setInterval(function () {
            if (VPCheckout.isEnabled()) {
                $('#payment_method_virtual-piggy').attr('checked', false);

                VPCheckout.init();
                clearInterval(window.intervalId);
            }
        }, 50);
    });

    return VPCheckout;
})(jQuery);