"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'akeneotranslator/template/configuration/tab/credential',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('akeneotranslator.credential.tab'),
            template: _.template(template),
            code: 'akeneo_translator_credentials',
            controls: [{
                    'label' : 'akeneotranslator.form.properties.apiToken.title',
                    'name': 'apiToken',
                    'type': 'password'
                }],
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
                if(typeof(data[$(event.target).attr('name')]) === "undefined") {
                    data[$(event.target).attr('name')] = "";
                }
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
