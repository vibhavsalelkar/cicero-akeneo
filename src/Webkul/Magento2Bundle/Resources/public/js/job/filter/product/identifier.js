'use strict';

define([
    'jquery',
    'underscore',
    'oro/translator',
    'pim/filter/filter',
    'pim/fetcher-registry',
    'pim/user-context',
    'magento2/template/job/identifier'
], function (
    $,
    _,
    __,
    BaseFilter,
    FetcherRegistry,
    UserContext,
    template
) {
    return BaseFilter.extend({
        shortname: 'identifier',
        template: _.template(template),
        events: {
            'change [name="filter-value"]': 'updateState'
        },

        /**
         * {@inheritdoc}
         */
        isEmpty: function () {
            return _.isEmpty(this.getValue());
        },

        /**
         * {@inheritdoc}
         */
        renderInput: function () {
            return this.template({
                __: __,
                value: _.isArray(this.getValue()) ? this.getValue().join(', ') : '',
                field: this.getField(),
                isEditable: this.isEditable()
            });
        },

        /**
         * {@inheritdoc}
         */
        getTemplateContext: function () {
            return BaseFilter.prototype.getTemplateContext.apply(this, arguments)
                .then(function (templateContext) {
                    return _.extend({}, templateContext, {
                        removable: false
                    });
                }.bind(this));
        },

        /**
         * {@inheritdoc}
         */
        getValue: function() {
            var data = this.getFormData();

            if(typeof data.configuration === 'undefined') {
                data.configuration = {};
            }
            
            if(typeof data.configuration.filterIdentifiers === 'undefined') {
                data.configuration.filterIdentifiers = [];
            }

            return data.configuration.filterIdentifiers;
        },

        /**
         * {@inheritdoc}
         */
        updateState: function () {
            var value = this.$('[name="filter-value"]').val().split(/[\n,]+/);
            
            var cleanedValues = _.reject(value, function (val) {
                return '' === val;
            });
            
            var data = this.getFormData();

            if(typeof data.configuration === 'undefined') {
                data.configuration = {};
            }
            
            data.configuration.filterIdentifiers = cleanedValues;

            this.setData(data); 
        }
    });
});
