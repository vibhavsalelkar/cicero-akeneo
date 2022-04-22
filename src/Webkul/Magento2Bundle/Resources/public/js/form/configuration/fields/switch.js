'use strict';

/**
 * Switch view extension
 */
define([
    'underscore',
    'pim/job/common/edit/field/field',
    'magento2/template/configuration/field/switch',
    'bootstrap.bootstrapswitch'
], function (
    _,
    BaseField,
    fieldTemplate
) {
    return BaseField.extend({
        fieldTemplate: _.template(fieldTemplate),
        events: {
            'change input': 'updateState'
        },

        /**
         * {@inheritdoc}
         */
        render: function () {
            BaseField.prototype.render.apply(this, arguments);

            this.$('.switch').bootstrapSwitch();
        },

        /**
         * Get the field dom value
         *
         * @return {string}
         */
        getFieldValue: function () {
            return this.$('input[type="checkbox"]').prop('checked');
        }
    });
});
