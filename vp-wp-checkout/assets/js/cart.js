window.VPCart = ( function($) {
    if(window.VPCart)
        return window.VPCart;

    var VPCart = {
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
                return false; //disabled by default for gift card site
            } else {
                return true;
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
                .delegate('div#payment ul.payment_methods li div, div#payment ul.payment_methods li label', 'click', function() {
                    if(!$('input#payment_method_virtual-piggy').closest('span').hasClass('checked')) {
                        view.showShippingForm();
                    }
                })
                .delegate('#vp-close', 'click', function () {
                    view.hideContentBox();
                    self.resetForm();
                    location.reload(true);
                })
                .delegate('.virtualpiggy-button-login', 'click', function () {
                    self.doLogin();
                })
                .delegate('.virtualpiggy-button-cancel', 'click', function() {
                    self.doLogout();
                    view.hideContentBox();
                    self.resetForm();
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
            if($('input#payment_method_virtual-piggy').closest('span').hasClass('checked')) { //oink is already the selected option at page load, set correct state.
                view.hideShippingForm();
            }
        },
        isLogged:function (callback) {
            if (!window.VPCart) {
                window.VPCart.init();
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

            if (!window.VPCart) {
                window.VPCart.init();
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

            this.view.showPaymentSelector(this.data.payment);
        },
        doPaymentSelection:function () {
            this.data.selectedPayment = $('#vp-select-payment').val();

            if (!this.data.selectedPayment) {
                return this.errorMessage('vp-select-payment','Select a payment method');
            }

            this.view.showLoading();
            this.routeToCheckout();
        },
        routeToCheckout:function() {
            //set logged in with session, so on next page, will show correct state
            $('input.checkout-button').trigger('click');
        },
        setOptions:function (cb) {
            if (!window.VPCart) {
                window.VPCart.init();
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
        errorMessage:function(element, message) {
            var div = document.getElementById(element);
            div.innerHTML = div.innerHTML + message;
        },
        hideLoading:function () {
            $("#vp-loader").hide();
        },
        afterLogin:function (success, message, data) {
            if (!success) {
                this.errorMessage('virtual-piggy-errors-container', message);
                this.hideLoading();
                return;
            }

            this.data = data;

            this.routeToCheckout();
        },
        isParent:function () {
            try {
                return this.data.role == 'Parent';
            } catch (e) {
                return false;
            }
        }
    };

    VPCart.view = {
        LOADING_URL:'/wp-content/plugins/vp-wp-checkout/assets/images/loading.gif',
        BUTTON_URL:'https://cdn.virtualpiggy.com/public/images/accepting-150x49.png',
        LOGO_URL:'https://cdn.virtualpiggy.com/public/images/checkout-logo-192x75.png',
        $form:null,
        $contentBox:null,
        init:function () {
            this.LOADING_URL = VPParams.baseURL + this.LOADING_URL;

            this.$form = $('form');
        },
        getContentBox:function () {
            if (this.$contentBox) {
                return this.$contentBox
            }

            this.$contentBox = $('<div/>')
                .hide()
                .addClass('virtualpiggy-loginbox');

            this.$contentBox.appendTo(document.body);
            return this.$contentBox;
        },
        showLoginBox:function () {
            this.cleanBox();

            var popup_content = '<div id="virtual-piggy-login">';
            popup_content +=        '<div id="vp-close"></div>';
            popup_content +=            '<div class="col-2">';
            popup_content +=                '<form id="virtual-piggy-login-form" method="post">';
            popup_content +=                    '<fieldset>';
            popup_content +=                        '<ul clas="form-list">';
            popup_content +=                            '<li>';
            popup_content +=                                '<div class="input-box">';
            popup_content +=                                    '<input type="text" class="input-text required-entry" id="vp-username" placeholder="Username"/>';
            popup_content +=                                '</div>';
            popup_content +=                            '</li>';
            popup_content +=                            '<li>';
            popup_content +=                                '<div class="input-box">';
            popup_content +=                                    '<input type="password" class="input-text required-entry" id="vp-password" placeholder="Password"/>';
            popup_content +=                                '</div>';
            popup_content +=                            '</li>';
            popup_content +=                        '</ul>';
            popup_content +=                    '</fieldset>';
            popup_content +=                '</form>';
            popup_content +=                '<p id="virtual-piggy-errors-container"></p>';
            popup_content +=            '</div>';
            popup_content +=            '<div class="col-2">';
            popup_content +=                '<div class="buttons-set" id="buttons-set">';
            popup_content +=                    '<button class="login-form-button virtualpiggy-button-login" type="button">Continue</button>';
            popup_content +=                    '<button href="https://www.oink.com" title="Sign Up" class="signup-form-button">Sign Up</button>';
            popup_content +=                '</div>';
            popup_content +=            '</div>';
            popup_content +=            '<div id="vp-loader" style="display:none"></div>';
            popup_content +=            '<div class="what-is-VP bold">What is Oink?</div>';
            popup_content +=            '<div class="what-is-VP-message dark-grey">Oink is the safe way for kids and teens to save, shop, and give online. <a class="blue" href="http://oink.com/">Learn more</a></div>';
            popup_content +=    '</div>';

            this.$contentBox.append(popup_content);
            $("#vp-username").focus();
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
            contentBox.show(300);
        },
        hideContentBox:function () {
            this.getContentBox().hide(200);
        },
        showLoading:function () {
            $("#vp-loader").css("display","block");
        },
        addButton:function (btn) {
            this.getButtonContainer().append(btn);
        },
        addCancelButton:function () {
            this.addButton(
                $('<button/>').addClass('virtualpiggy-button-cancel').html('Cancel')
            )
        },
        createPaymentOption:function (value, name) {
            var $container, $field, id;

            id = VPCart.id('payment-');

            $container = $('<div/>').addClass('virtualpiggy-payment-row');

            $field = $('<input/>')
                .attr('type', 'radio')
                .attr('value', value)
                .attr('id', id)
                .attr('class','virtualpiggy-radio-select')
                .attr('name', '_vp_payment_option')
                .append(name);

            $container
                .append($field)
                .append('<div class="clearfix"></div>');

            return $container;
        },
        center:function ($el) {
            $el.css("position", "fixed");
            $el.css('top', ($(window).height()- $($el).height())/4 + 'px');
            $el.css('left', ($(window).width()-$($el).width())/2 + 'px');
        },
        centerContentBox:function () {
            this.center(this.getContentBox);
        },
        showChildSelector:function (childs) {
            var contentBoxHidden = false;
            var contentBox = this.getContentBox();
            this.cleanBox();
            $(".virtualpiggy-loginbox").animate({height: "250px", width: "500px"}, 500);

            var $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-child')
                .append("<img id='OinkLogo' src='https://cdn.virtualpiggy.com/public/images/checkout-logo-192x75.png'/>");

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



            $fields.append(this.createLabel($select, 'This purchase is for '));

            this.getContentBox().append($fields)
                .append('<p id="virtual-piggy-errors-container"></p>');
            if(contentBoxHidden) {
                contentBox.show();
            }
        },
        showPaymentSelector:function (payments) {
            var $fields, $select;

            this.cleanBox();

            $fields = $('<div/>')
                .addClass('virtualpiggy-form')
                .addClass('virtualpiggy-form-payment')
                .append("<img id='OinkLogo' src='https://cdn.virtualpiggy.com/public/images/checkout-logo-192x75.png'/>");

            $select = $('<select/>')
                .attr('id', 'vp-select-payment');

            $.each(payments, function (key, value) {
                if (value == null) {
                    $select.append('<p id="errorMessage"><strong>You do not have any payment accounts</strong></p>');
                    return false;
                }
                else
                    $select.append($('<option/>').html(value).attr('value', value));
            });

            var $next = $('<button/>')
                .html('Next')
                .addClass('vp-select-payment-button');
            this.addButton($next);
            this.addCancelButton();


            $fields.append(this.createLabel($select, 'Payment method'));

            this.getContentBox().append($fields)
                .append('<p id="virtual-piggy-errors-container"></p>');
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
            var fields = VPCart.view.getWooCommerceFilledForm({});

            fields['#billing_company'] = '';
            fields['#billing_address_2'] = '';

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
            this.selectVirtualPiggyPaymentMethod();
            this.centerContentBox();
        },
        isShopp:function () {
            return $('#shopp').size() > 0;
        },
        getWooCommerceFilledForm:function (data) {
            return {
                '#billing_first_name':data.Name || VPCheckout.data.name,
                '#billing_last_name':data.LastName || '-',
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
                '#firstname':data.Name || VPCart.data.name,
                '#lastname':data.LastName || '-',
                '#billing-name':data.ParentName,
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
            var customer;
            if(!data.Name) {
                customer = (data.Name || '').split(/\s/);

                data.Name = customer.shift();
                data.LastName = customer.join(' ');
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
        resetForm:function() {
            var data='';
            var dict = this.getFilledForm(data);
            $.each(dict, function (key, value) {
                $(key).val('');
            });
            $('#payment_method_virtual-piggy').attr('checked', false);
            VPCheckout.view.showPaymentOptions();
            VPCheckout.view.showShippingForm();
        },
        selectVirtualPiggyPaymentMethod:function () {
            $('#payment_method_virtual-piggy').attr('checked', true);
            VPCart.view.hidePaymentOptions();
            VPCart.view.hideShippingForm();
        },
        reload:function () {
            window.location.reload();
        },
        isVPSelected:function () {
            var selected = $('.payment_methods input[type=radio]:checked');

            return selected.val() == 'virtual-piggy';
        }
    };

    VPCart.on = {};
    for (var prop in VPCart) {
        if (VPCart.hasOwnProperty(prop) && typeof VPCart[prop] == 'function') {
            (function (name) {
                var original = VPCart[name];

                VPCart[name] = function () {
                    var args = arguments || [];
                    if (VPCart[name].pre && typeof VPCart[name].pre == 'function') {
                        args = VPCart[name].pre.apply(VPCart, args) || arguments;
                    }

                    var value = original.apply(VPCart, args);

                    if (VPCart[name].post && typeof VPCart[name].post == 'function') {
                        args = [value, args];

                        value = VPCart[name].post.apply(VPCart, args) || arguments;
                    }

                    return value;
                };
            })(prop);
        }
    }

    $(function () {
        if (VPCart.view.isShopp()) {
            VPCart.init();
            return;
        }

        window.intervalId = setInterval(function () {
            if (VPCart.isEnabled()) {
                $('#payment_method_virtual-piggy').attr('checked', false);

                VPCart.init();
                clearInterval(window.intervalId);
            }
        }, 50);
    });

    return VPCart;
})(jQuery);