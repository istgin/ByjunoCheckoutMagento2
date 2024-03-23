define(
    [
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'jquery'
    ],
    function (ko, Component, url, quote, jquery) {
        'use strict';
        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'CembraPayCheckout_CembraPayCheckoutCore/payment/form_invoice',
                paymentPlan: window.checkoutConfig.payment.cembrapaycheckout_invoice.default_payment,
                deliveryPlan: window.checkoutConfig.payment.cembrapaycheckout_invoice.default_delivery,
                agreeTc: window.checkoutConfig.payment.cembrapaycheckout_invoice.default_agreetc,
                customGender: window.checkoutConfig.payment.cembrapaycheckout_invoice.default_customgender,
                value: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'paymentPlan',
                        'deliveryPlan',
                        'agreeTc',
                        'customGender',
                        'value'
                    ]);
                return this;
            },

            getLcloseText: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.closeText;
            },
            getLprevText: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.prevText;
            },
            getLnextText: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.nextText;
            },
            getLcurrentText: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.currentText;
            },
            getLmonthNames: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.monthNames;
            },
            getLmonthNamesShort: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.monthNamesShort;
            },
            getLdayNamesShort: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.dayNamesShort;
            },
            getLdayNames: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.dayNames;
            },
            getLdayNamesMin: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.calendar_config.dayNamesMin;
            },

            getAgreementLink: function () {
                for (var i = 0; i < window.checkoutConfig.payment.cembrapaycheckout_invoice.methods.length; i++) {
                    var method = window.checkoutConfig.payment.cembrapaycheckout_invoice.methods[i];
                    if (method.value === this.paymentPlan()) {
                        return method.link
                    }
                }
                return this.paymentPlan()
            },

            getAgreeTc: function () {
                return (window.checkoutConfig.payment.cembrapaycheckout_invoice.payment_mode === "authorization") ? this.agreeTc() : true;
            },

            agreeChecked: function () {
                this.agreeTc(jquery("#cembra_agree_invoice").is(":checked"))
            },

            afterPlaceOrder: function () {
                jquery('body').loader('show');
                this.selectPaymentMethod();
                jquery.mage.redirect(url.build(window.checkoutConfig.payment.cembrapaycheckout_invoice.redirectUrl));
                return false;
            },

            getCode: function () {
                return 'cembrapaycheckout_invoice';
            },

            getYearRange: function () {
                var dataYReange = new Date();
                var yRange = dataYReange.getFullYear();
                return '1900:'+yRange;
            },

            getDob: function () {
                var dob  = window.checkoutConfig.quoteData.customer_dob;
                if (dob == null)
                {
                    return ko.observable(false);
                }
                return ko.observable(new Date(dob));
            },

            getEmail: function () {
                if (window.checkoutConfig.quoteData.customer_email != null) {
                    return window.checkoutConfig.quoteData.customer_email;
                } else {
                    return quote.guestEmail;
                }
            },

            getBillingAddress: function () {
                if (quote.billingAddress() == null) {
                    return null;
                }

                if (typeof quote.billingAddress().street === 'undefined' || typeof quote.billingAddress().street[0] === 'undefined') {
                    return null;
                }

                return quote.billingAddress().street[0] + ", " + quote.billingAddress().city + ", " + quote.billingAddress().postcode;
            },

            getData: function () {
                if (this.isAllFieldsEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'invoice_payment_plan': this.paymentPlan(),
                            'invoice_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'invoice_customer_gender': this.customGender(),
                            'invoice_customer_dob': jquery("#checkout_customer_dob_invoice").val()
                        }
                    };
                } else if (this.isB2bAllFieldsEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'invoice_payment_plan': this.paymentPlan(),
                            'invoice_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'invoice_customer_gender': this.customGender(),
                            'invoice_customer_b2b_uid': jquery("#checkout_customer_b2b_uid_invoice").val()
                        }
                    };
                } else if (this.isGenderEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'invoice_payment_plan': this.paymentPlan(),
                            'invoice_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'invoice_customer_gender': this.customGender()
                        }
                    };
                } else if (this.isBirthdayEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'invoice_payment_plan': this.paymentPlan(),
                            'invoice_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'invoice_customer_dob': jquery("#checkout_customer_dob_invoice").val()
                        }
                    };
                } else if (this.isB2bUid()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'invoice_payment_plan': this.paymentPlan(),
                            'invoice_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'invoice_customer_b2b_uid': jquery("#checkout_customer_b2b_uid_invoice").val()
                        }
                    };
                } else {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'invoice_payment_plan': this.paymentPlan(),
                            'invoice_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc()
                        }
                    };
                }
            },
            getLogo: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.logo;
            },

            getPaymentPlans: function () {
                return _.map(window.checkoutConfig.payment.cembrapaycheckout_invoice.methods, function (value, key) {
                    return {
                        'value': value.value,
                        'link': value.link,
                        'label': value.name
                    }
                });
            },

            isDeliveryVisibility: function() {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.paper_invoice;
            },

            isAgreeVisibility: function () {
                return (window.checkoutConfig.payment.cembrapaycheckout_invoice.payment_mode === "authorization");
            },

            isPaymentPlanVisible: function() {
                return (window.checkoutConfig.payment.cembrapaycheckout_invoice.methods.length > 1);
            },

            getDeliveryPlans: function () {
                var list = [];
                for (var i = 0; i < window.checkoutConfig.payment.cembrapaycheckout_invoice.delivery.length; i++) {
                    var value = window.checkoutConfig.payment.cembrapaycheckout_invoice.delivery[i];
                    if (value.value === 'email') {
                        list.push(
                            {
                                'value': value.value,
                                'label': value.text + this.getEmail()
                            }
                        );
                    } else {
                        if (this.getBillingAddress() != null) {
                            list.push(
                                {
                                    'value': value.value,
                                    'label': value.text + this.getBillingAddress()
                                }
                            );
                        }
                    }
                }
                return list;
            },

            isAllFieldsEnabled: function () {
                return this.isGenderEnabled() && this.isBirthdayEnabled();
            },

            isB2bAllFieldsEnabled: function () {
                return this.isGenderEnabled() && this.isB2bUid();
            },

            isFieldsEnabled: function () {
                return this.isGenderEnabled() || this.isBirthdayEnabled();
            },

            isGenderEnabled: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.gender_enable;
            },

            isBirthdayEnabled: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.birthday_enable;
            },
            isB2bUid: function () {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.b2b_uid;
            },

            getGenders: function() {
                var list = [];
                for (var i = 0; i < window.checkoutConfig.payment.cembrapaycheckout_invoice.custom_genders.length; i++) {
                    var value = window.checkoutConfig.payment.cembrapaycheckout_invoice.custom_genders[i];
                    list.push(
                        {
                            'value': value.value,
                            'label': value.text
                        }
                    );
                }
                return list;
            },

            isSinglePaymentPlanVisible: function() {
                return (window.checkoutConfig.payment.cembrapaycheckout_invoice.methods.length === 1);
            },

            isSinglePaymentPlanVisibleTC: function() {
                return window.checkoutConfig.payment.cembrapaycheckout_invoice.methods[0].link;
            },

        });
    }
);
