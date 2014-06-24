window.VPCheckout = (function ($) {
    if (window.VPCheckout) {
        return window.VPCheckout;
    }
    var VPCheckout = {
        data: {},
        version: function () {
            return VPParams.version;
        },
        /**
         * Generates a "unique" id
         * @return string
         */
        id: (function () {
            var seed = 1;
            return function (prefix) {
                return (prefix || '') + (seed++)
            }
        })(),
        init: function () {
            if (!this.isEnabled()) {
                return;
            }

            var self = this;
            self.resetForm();
            self.isLogged(function (isLogged, info) {
                if (!isLogged) {
                    self.view.init();
                    self.initEvents();
                    self.view.showLoginBox();
                } else {
                    self.view.init();
                    self.initEvents();
                    self.afterLogin(isLogged, '', info);
                }
            });
        },
        error: function (e) {
            alert(e);
        },
        isEnabled: function () {
            if (this.view.isShopp())
                return !!$('option[value="virtualpiggy-com"]').size();
            else
                return !!$('#payment_method_virtual-piggy').size();
        },
        initEvents: function () {
            var self = this;
            var view = this.view;

            $(document)
                .delegate('.virtualpiggy-button, #payment_method_virtual-piggy', 'click', function () {
                    self.isLogged(function (isLogged, data) {
                        view.showContentBox();
                        if (!isLogged)
                            view.showLoginBox();
                        else {
                            view.showLoading();
                            self.afterLogin(isLogged, '', data);
                        }
                    });
                })

                .delegate('div#payment ul.payment_methods li div, div#payment ul.payment_methods li label', 'click', function () {
                    if (!$('input#payment_method_virtual-piggy').closest('span').hasClass('checked')) {
                        view.showShippingForm();
                    }
                })
                .delegate('#vp-close', 'click', function () {
                    self.resetForm();
                    view.hideContentBox();
                    window.location.reload(true);
                })
                .delegate('.virtualpiggy-button-login', 'click', function () {
                    self.doLogin();
                })
                .delegate('.virtualpiggy-button-cancel', 'click', function () {
                    self.doLogout();
                    self.resetForm();
                    view.hideContentBox();
                    view.$form.unblock();
                    location.reload(true);
                })
                .delegate('.vp-select-child-button', 'click', function () {
                    self.doChildSelection();
                })
                .delegate('.vp-select-payment-button', 'click', function () {
                    self.doPaymentSelection();
                })
                .delegate('#payment_method_virtual-piggy', 'change', function () {
                    VPCheckout.view.hidePaymentOptions();
                    VPCheckout.view.hideShippingForm();
                })
                .delegate('#vp-password', 'keyup', function (e) {
                    if (e.which == 13) {
                        self.doLogin();
                    }
                })
                .delegate('#vp-username', 'keyup', function (e) {
                    if (e.which == 13) {
                        self.doLogin();
                    }
                });

            /*if Oink is the selected payment method, set the correct state*/
            if ($('input#payment_method_virtual-piggy').closest('span').hasClass('checked')) {
                view.hideShippingForm();
            }
        },
        resetForm: function () {
            var $form = $('.checkout')
            $form.find('input:text, input:password, input:file, select, textarea').val('');
            $form.find('input:radio, input:checkbox')
                .removeAttr('checked')
                .removeAttr('selected');
            $('#payment_method_virtual-piggy').attr('checked', false);
            VPCheckout.view.showPaymentOptions();
            VPCheckout.view.showShippingForm();
        },
        isLogged: function (callback) {
            if (!window.VPCheckout)
                window.VPCheckout.init();
            $.ajax({
                url: '?vp_action=get_data',
                complete: function (xhr) {
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
                this.errorMessage('#virtual-piggy-errors-container', 'Both username and password are required fields');
                return;
            }
            this.view.showLoading();
            var self = this;
            $.ajax({
                url: '?vp_action=login',
                data: {
                    username: user,
                    password: pass
                },
                complete: function (xhr) {
                    var json = $.parseJSON(xhr.responseText);

                    self.afterLogin(json.success, json.message, json.data);
                }
            });
        },
        doLogout: function (cb) {
            $.ajax({
                url: '?vp_action=logout',
                complete: function (xhr) {
                    (cb || function () {
                    })();
                }
            });
        },
        doChildSelection: function () {
            this.data.selectedChild = $('#vp-select-child').val();
            this.view.showPaymentSelector(this.data.payment);
        },
        doPaymentSelection: function (selectedPayment) {
            this.data.selectedPayment = selectedPayment || $('#vp-select-payment').val();
            this.view.showLoading();
            this.view.hideContentBox();
            this.fetchShippingAddress();
        },
        fetchShippingAddress: function () { var self = this, data, callback;
            if (!window.VPCheckout)
                window.VPCheckout.init();
            data = {};
            if (this.isParent())
                data.child = this.data.child || this.data.selectedChild;

            callback = function (response) {
                response.data.name = self.data.selectedChild;
                self.lastData = response.data;
                if (response.success) {
                    self.view.hideShippingForm();
                    self.view.hidePaymentOptions();
                    self.view.populateShippingForm(response.data);
                    self.view.showShippingAddress(response.data);
                    self.view.$form.unblock();
                }
                else {
                    self.view.$form.unblock();
                    self.doLogout();
                    $('#payment_method_virtual-piggy').attr('checked', false);
                    window.location.reload();
                }
            };
            this.setOptions(VPCheckout.shippingAddressFetcher || function () {
                if (!window.VPCheckout)
                    window.VPCheckout.init();
                $.ajax({
                    url: '?vp_action=get_shipping_details',
                    data: data,
                    complete: function (xhr) {
                        var json = $.parseJSON(xhr.responseText);
                        callback(json);
                    }
                });
            });
        },
        setOptions: function (cb) {
            if (!window.VPCheckout)
                window.VPCheckout.init();
            $.ajax({
                url: '?vp_action=set_options',
                data: {
                    child: this.data.selectedChild,
                    payment: this.data.selectedPayment
                },
                complete: cb || $.noop
            });
        },hideLoading: function () {
            $("#vp-loader").hide();
        },
        errorMessage: function (element, message) {
            $(element).append(message);
        },
        clearErrors: function(element) {
           $(element).empty();
        },
        afterLogin: function (success, message, data) {
            this.clearErrors('#virtual-piggy-errors-container');
            if (!success) {
                this.errorMessage('#virtual-piggy-errors-container', message);
                this.hideLoading();
                this.doLogout();
                return;
            }

            if (typeof this.data.role === "undefined")
                this.data = data;

            $('#payment_method_virtual-piggy').attr('checked', 'checked');
            this.view.hideLauncherButton();
            if (this.isParent() && (typeof this.data.selectedChild === "undefined"))
                this.view.showChildSelector(this.data.childs);
            else {
                this.view.hideContentBox();
                this.fetchShippingAddress();
            }
        },
        isParent: function () {
            try {
                return this.data.role == 'Parent';
            } catch (e) {
                return false;
            }
        }
    };

    VPCheckout.view = {
        LOADING_URL: '/wp-content/plugins/vp-wp-checkout/assets/images/loading.gif',
        BUTTON_URL: '//cdn.virtualpiggy.com/public/images/checkout-145x42.png',
        LOGO_URL: '//cdn.virtualpiggy.com/public/images/checkout-logo-192x75.png',
        $form: null,
        $contentBox: null,
        init: function () {
            this.LOADING_URL = VPParams.baseURL + this.LOADING_URL;
            this.$form = $('form.checkout, form#checkout');
            this.addLauncherButton();
        },
        getContentBox: function () {
            if (this.$contentBox)
                return this.$contentBox;
            this.$contentBox = $('<div/>')
                .hide()
                .addClass('virtualpiggy-loginbox');
            this.$contentBox.appendTo(document.body);
            return this.$contentBox;
        },
        showLoginBox: function () {
            this.cleanBox();
            var popup_content = '<div id="virtual-piggy-login">';
            popup_content +=        '<div id="vp-close"></div>';
            popup_content +=            '<div class="col-2">';
            popup_content +=                '<form id="virtual-piggy-login-form" method="post">';
            popup_content +=                    '<fieldset>';
            popup_content +=                        '<ul class="form-list">';
            popup_content +=                            '<li>';
            popup_content +=                                '<div class="input-box">';
            popup_content +=                                    '<input type="text" class="input-text required-entry" id="vp-username" placeholder="Username"/>';
            popup_content +=                                '</div>';
            popup_content +=                            '</li>';
            popup_content +=                            '<li>';
            popup_content +=                                '<div class="input-box">';
            popup_content +=                                    '<input type="password" class="input-text required-entry" id="vp-password" placeholder="Password"/>';
            popup_content +=                                '</div>';
            popup_content +=                                '<p id="virtual-piggy-errors-container"></p>';
            popup_content +=                            '</li>';
            popup_content +=                            '<li>';
            popup_content +=                                '<div class="buttons-set" id="buttons-set">';
            popup_content +=                                    '<button class="login-form-button virtualpiggy-button-login" type="button">Continue</button>';
            popup_content +=                                '</div>';
            popup_content +=                            '</li>';
            popup_content +=                        '</ul>';
            popup_content +=                    '</fieldset>';
            popup_content +=                '</form>';
            popup_content +=            '</div>';
            popup_content +=            '<div id="vp-loader" style="display:none"></div>';
            popup_content +=            '<div id ="VP-info">';
            popup_content +=                '<div class="what-is-VP bold">What is Oink?</div>';
            popup_content +=                '<div class="what-is-VP-message dark-grey">Oink is the safe way for kids and teens to <br/>save, shop, and give online. <a class="blue" href="//oink.com/">Learn more</a></div>';
            popup_content +=                '<a id="sign-up-button" target="_blank" href="//users.virtualpiggy.com/registration">Sign Up</a></div>';
            popup_content +=            '</div>';
            popup_content +=        '</div>';

            this.$contentBox.append(popup_content);
            $("#vp-username").focus();
        },
        getButtonContainer: function () {
            var $contentBox = this.getContentBox();
            if (!$contentBox.find('.vp-button-container').size()) {
                $contentBox.append(
                    $('<div/>').addClass('vp-button-container')
                );
            }
            return $contentBox.find('.vp-button-container');
        },
        addLauncherButton: function () {
            var $button = $('<img/>')
                .attr('src', this.BUTTON_URL)
                .addClass('virtualpiggy-button');
            this.$form.prepend($button);
        },

        createLabel: function ($field, label) {
            var $container, $label;
            $container = $('<div/>').addClass('virtualpiggy-field-row');
            $label = $('<label/>').attr('for', $field.attr('id')).html(label || '');

            $container
                .append($label)
                .append($field)
                .append('<div class="clearfix"></div>');

            return $container;
        },
        showContentBox: function () {
            var contentBox = this.getContentBox();
            this.center(contentBox);
            contentBox.show(300);
        },
        hideContentBox: function () {
            this.getContentBox().hide(200);
        },
        showLoading: function () {
            $("#vp-loader").css("display", "block");
        },
        addButton: function (btn) {
            this.getButtonContainer().append(btn);
        },
        addCancelButton: function () {
            this.addButton(
                $('<button/>').addClass('virtualpiggy-button-cancel').html('Cancel')
            )
        },
        center: function ($el) {
            $el.css("position", "fixed");
            $el.css('top', ($(window).height() - $($el).outerHeight())/4 + 'px');
            $el.css('left', ($(window).width() - $($el).outerWidth())/2 + 'px');
        },
        showChildSelector: function (childs) {
            var contentBoxHidden = false;
            var contentBox = this.getContentBox();
            this.cleanBox();
            this.showContentBox()
            $(".virtualpiggy-loginbox").animate({height: "300px"}, 500);
            var $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-child')
                .append("<img id='OinkLogo' src='//cdn.virtualpiggy.com/public/images/checkout-logo-192x75.png'/>");
            var popup_content = '<div id="vp-close"></div>';
            contentBox.append(popup_content);
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
            $fields.append(this.createLabel($select, 'This purchase is for'));
            this.getContentBox().append($fields)
                .append('<p id="virtual-piggy-errors-container"></p>');
            if (contentBoxHidden) {
                contentBox.show();
            }
        },
        showPaymentSelector: function (payments) {
            var $fields, $select, currentAccount, paymentAccounts = 0;
            this.cleanBox();
            $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-payment')
                .append("<img id='OinkLogo' src='//cdn.virtualpiggy.com/public/images/checkout-logo-192x75.png'/>");
            $select = $('<select/>')
                .attr('id', 'vp-select-payment');
            $.each(payments, function (key, value) {
                if (value == null) {
                    $select = '<p id="errorMessage"><strong>You do not have any payment accounts</strong></p>';
                    return false;
                }
                else {
                    $select.append($('<option/>').html(value).attr('value', value));
                    paymentAccounts++;
                    currentAccount = value;
                }
            });
            var $next = $('<button/>')
                .html('Next')
                .addClass('vp-select-payment-button');
            if (!paymentAccounts) {
                $fields.append($select);
            }
            else if (paymentAccounts == 1) {
                window.VPCheckout.doPaymentSelection(currentAccount);
            }
            else {
                $fields.append(this.createLabel($select, 'Payment method'));
                this.addButton($next);
            }
            this.addCancelButton();
            this.getContentBox().append($fields);
        },
        cleanBox: function () {
            this.getContentBox().children(':not(img)').remove();
        },
        hideShippingForm: function () {
            if (this.isShopp()) {
                $('#checkout ul').hide();
            } else {
                $('#customer_details').hide();
                this.hideWooCommerceShippingForm();
            }
        },
        showShippingForm: function () {
            if (this.isShopp()) {
                $('#checkout ul').show();
            } else {
                this.showWooCommerceShippingForm();
            }
        },
        hideWooCommerceShippingForm: function () {
            var fields = VPCheckout.view.getWooCommerceFilledForm({});
            fields['#billing_company'] = '';
            fields['#billing_address_2'] = '';

            for (var f in fields) {
                $(f).hide();
                $('label[for=' + f.replace(/#/, '') + ']').hide();
            }

            $('#shiptobilling-checkbox').hide();
            $('label[for=shiptobilling-checkbox]').hide();
            $('#payment_method_virtual-piggy').attr('checked', 'checked');
        },
        showWooCommerceShippingForm: function () {
            var fields = VPCheckout.view.getWooCommerceFilledForm({});

            for (var f in fields) {
                $(f).show();
                $('label[for=' + f.replace(/#/, '') + ']').show();
            }
            $('#shiptobilling-checkbox').show();
            $('label[for=shiptobilling-checkbox]').show();
            $('#payment_method_virtual-piggy').attr('checked', '');
        },
        hidePaymentOptions: function () {
            if (this.isShopp())
                $('select[name=paymethod]').val('virtualpiggy-com');
            else
                $('.payment_methods').hide();
        },
        showPaymentOptions: function () {
            if (this.isShopp())
                $('select[name=paymethod]').val('virtualpiggy-com');
            else
                $('.payment_methods').show();
        },
        hideLauncherButton: function () {
            $('img.virtualpiggy-button').hide();
        },
        showShippingAddress: function (data) {
            if ($('div#oink_shipping_address_view').length > 0)
                return;
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
                    .before($('<p/>').html('<div id="oink_shipping_address_view">' + address + '</div>'));
            } else {
                $('#order_review_heading')
                    .before($('<h3/>').html('Shipping Address'))
                    .before($('<p/>').html('<div id="oink_shipping_address_view">' + address + '</div>'));
            }

            this.hideLauncherButton();
            this.selectVirtualPiggyPaymentMethod();
        },
        isShopp: function () {
            return $('#shopp').size() > 0;
        },
        getWooCommerceFilledForm: function (data) {
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
        getShoppFilledForm: function (data) {
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
        getFilledForm: function (data) {
            var customer;
            if (!data.ParentName) {
                customer = (data.ParentName || '').split(/\s/);

                data.Name = customer.shift();
                data.LastName = customer.join(' ');
            }

            if (this.isShopp()) {
                return this.getShoppFilledForm(data)
            } else {
                return this.getWooCommerceFilledForm(data)
            }
        },
        populateShippingForm: function (data) {
            var dict = this.getFilledForm(data);

            $.each(dict, function (key, value) {
                $(key).val(value);
            });

            try {
                $("#billing_country").trigger("liszt:updated");
            } catch (e) {

            }
        },
        selectVirtualPiggyPaymentMethod: function () {
            $('#payment_method_virtual-piggy').attr('checked', true);
            VPCheckout.view.hidePaymentOptions();
            VPCheckout.view.hideShippingForm();
        },
        reload: function () {
            window.location.reload(true);
        },
        isVPSelected: function () {
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