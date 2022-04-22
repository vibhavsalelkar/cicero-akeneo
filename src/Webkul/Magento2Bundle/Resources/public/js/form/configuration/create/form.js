"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/credential',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.credentials.tab'),
            template: _.template(template),
            code: 'magento2_connector_credential',
            controls: [{
                    'label' : 'magento2.form.properties.host_name.title',
                    'name': 'hostName',
                    'type': 'text'
                }, {
                    'label' : 'magento2.form.properties.auth_token.title',
                    'name': 'authToken',
                    'type': 'password'
                }
            ],
            errors: [],
            events: {
                'change .AknFormContainer-Credential input': 'updateModel'
            },

            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:bad_request',
                    this.setValidationErrors.bind(this)
                );

                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:pre_save',
                    this.resetValidationErrors.bind(this)
                );

                this.trigger('tab:register', {
                    code: this.code,
                    label: this.label
                });

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {                
                $('.magento-save-config').hide();
                this.$el.html(this.template({
                    controls: this.controls,
                    model: this.getFormData(),
                    errors: this.errors
                }));
 
                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                data[$(event.target).attr('name')] = event.target.value;
                this.setData(data);
            },

            /**
             * Sets errors
             *
             * @param {Object} errors
             */
            setValidationErrors: function (errors) {
                this.errors = errors.response;
                this.render();
            },

            /**
             * Resets errors
             */
            resetValidationErrors: function () {
                this.errors = {};
                this.render();
            }
        });
    }
);
