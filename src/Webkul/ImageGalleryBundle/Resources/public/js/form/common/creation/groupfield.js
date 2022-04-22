
define([
    'jquery',
    'underscore',
    'oro/translator',
    'pim/form',
    'webkulgallery/template/form/creation/field'
], function($, _, __, BaseForm, template) {

    return BaseForm.extend({
        template: _.template(template),
        dialog: null,
        errors: [],
        events: {
            'keyup input': 'updateModel'
        },

        /**
     * {@inheritdoc}
     */
        initialize: function(config) {
            this.config = config.config;
            this.identifier = this.config.identifier || 'code';

            BaseForm.prototype.initialize.apply(this, arguments);
        },

        /**
     * Model update callback
     */
        updateModel: function(event) {
            this.getFormModel().set(this.identifier, event.target.value || '');
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

            return BaseForm.prototype.configure.apply(this, arguments);
        },

        /**
         * Sets errors
         *
         * @param {Object} errors
         */
        setValidationErrors: function (errors) {
            this.errors = ['Error in saving Group please check the code (It must be unique)'];
            
            this.render();
        },

        /**
         * Resets errors
         */
        resetValidationErrors: function () {
            this.errors = {};
            this.render();
        },



        /**
     * {@inheritdoc}
     */
        render: function() {
            if (!this.configured)
                this;
    
            // const errors = this.getRoot().validationErrors || [];

            this.$el.html(this.template({
                identifier: this.identifier,
                label: __(this.config.label),
                requiredLabel: __('pim_common.required_label'),
                errors: this.errors,
                value: this.getFormData()[this.identifier]
            }));

            this.delegateEvents();

            return this;
        }
    });
});