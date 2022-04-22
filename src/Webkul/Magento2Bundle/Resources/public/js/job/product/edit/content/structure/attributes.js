'use strict';
/**
 * Scopes structure filter
 */
define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'magento2/template/export/product/edit/content/structure/attributes',
        'pim/form',
        'pim/fetcher-registry',
        'pim/user-context',
        'jquery.select2',
        'pim/i18n'
    ],
    function (
        $,
        _,
        __,
        template,
        BaseForm,
        fetcherRegistry,
        UserContext,
        select2,
        i18n
    ) {
        return BaseForm.extend({
            config: {},
            className: 'AknFieldContainer',
            template: _.template(template),

            /**
             * Initializes configuration.
             *
             * @param {Object} config
             */
            initialize: function (config) {
                this.config = config.config;

                return BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * Renders scopes dropdown.
             *
             * @return {Object}
             */
            render: function () {
                if (!this.configured) {
                    return this;
                }
                
                //console.log(this.getFilters());
                var attributes  = this.getFilters().attributeExport;
                
                attributes = attributes ? attributes : [];
                fetcherRegistry.getFetcher('mapping-attributes').fetchAll().then((values) => {
                    
                    if(_.isEmpty(attributes)) {
                        attributes = values;
                        this.setAttributes(values);
                    }
                    this.$el.html(
                        this.template({
                            isEditable: this.isEditable(),
                            __: __,
                            attributes: attributes,
                            availableAttributes: values,
                            errors: [this.getParent().getValidationErrorsForField('locales')]
                        })
                    );

                    this.$('.select2').select2().on('change', this.updateState.bind(this));
                    this.$('[data-toggle="tooltip"]').tooltip();

                    this.renderExtensions();

                });
               
                return this;
            },


            /**
             * Returns whether this filter is editable.
             *
             * @returns {boolean}
             */
            isEditable: function () {
                return undefined !== this.config.readOnly ?
                    !this.config.readOnly :
                    true;
            },

            /**
             * Sets new scope on field change.
             *
             * @param {Object} event
             */
            updateState: function (event) {
                this.setAttributes($(event.target).val());
                this.render();
            },

            /**
             * Sets specified scope into root model.
             *
             * @param {String} code
             */
            setAttributes: function (code) {
                let data = this.getFormData().configuration.filters;
                if(typeof data.attributeExport == 'undefined'){
                    data.attributeExport = []; 
                }

                data.attributeExport = code;

                this.setData(data);
            },

            /**
             * Get filters
             *
             * @return {object}
             */
            getFilters: function () {
                
                return this.getFormData().configuration.filters;
            }
        });
    }
);
