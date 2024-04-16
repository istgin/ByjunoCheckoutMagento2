define(
    [
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'mage/translate'
    ],
    function (ko, Component, url, quote, jquery, $t) {
        'use strict';
        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Byjuno_ByjunoCore/payment/form_installment',
                deliveryPlan: window.checkoutConfig.payment.byjuno_installment.default_delivery,
                agreeTc: window.checkoutConfig.payment.byjuno_installment.default_agreetc,
                customGender: window.checkoutConfig.payment.byjuno_installment.default_customgender,
                value: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'deliveryPlan',
                        'agreeTc',
                        'customGender',
                        'value'
                    ]);
                return this;
            },

            getLcloseText: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.closeText;
            },
            getLprevText: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.prevText;
            },
            getLnextText: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.nextText;
            },
            getLcurrentText: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.currentText;
            },
            getLmonthNames: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.monthNames;
            },
            getLmonthNamesShort: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.monthNamesShort;
            },
            getLdayNamesShort: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.dayNamesShort;
            },
            getLdayNames: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.dayNames;
            },
            getLdayNamesMin: function () {
                return window.checkoutConfig.payment.byjuno_installment.calendar_config.dayNamesMin;
            },

            getAgreementLink: function () {
                return window.checkoutConfig.payment.byjuno_installment.tc_link;
            },

            getAgreementText: function () {
                var agreement_link = this.getAgreementLink();
                var text = $t("Agreement");
                var agreement = text.replace("%%agreement%%", agreement_link)
                return agreement;
            },

            getAgreeTc: function () {
                return (window.checkoutConfig.payment.byjuno_installment.payment_mode === "authorization") ? this.agreeTc() : true;
            },

            agreeChecked: function () {
                this.agreeTc(jquery("#cembra_agree_installment").is(":checked"))
            },

            afterPlaceOrder: function () {
                jquery('body').loader('show');
                this.selectPaymentMethod();
                jquery.mage.redirect(url.build(window.checkoutConfig.payment.byjuno_installment.redirectUrl));
                return false;
            },


            getCode: function () {
                return 'byjuno_installment';
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

            isCompany: function () {
                if (quote.billingAddress() == null) {
                    return false;
                }

                if (typeof quote.billingAddress().company === 'undefined' || typeof quote.billingAddress().street[0] === 'undefined' || quote.billingAddress().company === '' || quote.billingAddress().company === null) {
                    return false;
                }
                return true;
            },

            getData: function () {
                if (this.isAllFieldsEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'installment_payment_plan': jquery("#installment_payment_plan:checked").val(),
                            'installment_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'installment_customer_gender': this.customGender(),
                            'installment_customer_dob': jquery("#installment_payment_plan").val()
                        }
                    };
                } else if (this.isB2bAllFieldsEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'installment_payment_plan': jquery("#installment_payment_plan:checked").val(),
                            'installment_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'installment_customer_gender': this.customGender(),
                            'installment_customer_b2b_uid': jquery("#checkout_customer_b2b_uid_installment").val()
                        }
                    };
                }  else if (this.isGenderEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'installment_payment_plan': jquery("#installment_payment_plan:checked").val(),
                            'installment_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'installment_customer_gender': this.customGender()
                        }
                    };
                } else if (this.isBirthdayEnabled()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'installment_payment_plan': jquery("#installment_payment_plan:checked").val(),
                            'installment_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'installment_customer_dob': jquery("#checkout_customer_dob_installment").val()
                        }
                    };
                } else if (this.isB2bUid()) {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'installment_payment_plan': jquery("#installment_payment_plan:checked").val(),
                            'installment_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc(),
                            'installment_customer_b2b_uid': jquery("#checkout_customer_b2b_uid_installment").val()
                        }
                    };
                }  else {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'installment_payment_plan': jquery("#installment_payment_plan:checked").val(),
                            'installment_send': this.deliveryPlan(),
                            'agree_tc': this.agreeTc()
                        }
                    };
                }
            },
            getLogo: function () {
                return window.checkoutConfig.payment.byjuno_installment.logo;
            },

            getPlans: function  () {
                var methods = [];
                var isCompany = this.isCompany();
                window.checkoutConfig.payment.byjuno_installment.methods.forEach(function(element) {
                    if (element.allow === "0" || (element.allow === "1" && !isCompany) || (element.allow === "2" && isCompany)) {
                        methods.push(element);
                    }
                });
                if (methods.length > 0) {
                    methods[0].checked = true;
                }
                return methods;
            },

            getPaymentPlans: function () {
                return _.map(this.getPlans(), function (value, key) {
                    return {
                        'value': value.value,
                        'label': value.name,
                        'checked': value.checked
                    }
                });
            },

            isDeliveryVisibility: function() {
                return window.checkoutConfig.payment.byjuno_installment.paper_invoice;
            },

            isAgreeVisibility: function () {
                return (window.checkoutConfig.payment.byjuno_installment.payment_mode === "authorization");
            },

            isPaymentPlanVisible: function() {
                return true;
                //return (this.getPlans().length > 1);
            },

            getDeliveryPlans: function () {
                var list = [];
                for (var i = 0; i < window.checkoutConfig.payment.byjuno_installment.delivery.length; i++) {
                    var value = window.checkoutConfig.payment.byjuno_installment.delivery[i];
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
                return this.isGenderEnabled() || this.isBirthdayEnabled() || this.isB2bUid();;
            },

            isGenderEnabled: function () {
                return window.checkoutConfig.payment.byjuno_installment.gender_enable;
            },

            isBirthdayEnabled: function () {
                return !this.isCompany() && window.checkoutConfig.payment.byjuno_installment.birthday_enable;
            },
            isB2bUid: function () {
                return this.isCompany() && window.checkoutConfig.payment.byjuno_installment.b2b_uid;
            },

            getGenders: function() {
                var list = [];
                for (var i = 0; i < window.checkoutConfig.payment.byjuno_installment.custom_genders.length; i++) {
                    var value = window.checkoutConfig.payment.byjuno_installment.custom_genders[i];
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
                return (this.getPlans().length === 1);
            },
        });
    }
);
