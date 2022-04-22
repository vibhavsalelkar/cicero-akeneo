'use strict';

/**
 * Switch view extension
 */
define([
    'underscore',
    'pim/job/common/edit/field/field',
    'pim/template/export/common/edit/field/switch',
    'pim/common/property',
    'bootstrap.bootstrapswitch'
], function (
    _,
    BaseField,
    fieldTemplate,
    propertyAccessors
) {
    return BaseField.extend({
        fieldTemplate: _.template(fieldTemplate),
        events: {
            // 'change input': 'updateState',
            'change input': 'customUpdateState',
        },

        /**
         * {@inheritdoc}
         */
        render: function () {
            var checked = this.$('#jobInstance_configuration_disableCsvUpload').is(':checked');
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
        },

        customUpdateState: function () {
            var data = propertyAccessors.updateProperty(this.getFormData(), this.getFieldCode(), this.getFieldValue());
            this.setData(data);
            if(this.getFormData()['configuration']['disableCsvUpload']) {
                var data = propertyAccessors.updateProperty(this.getFormData(), 'configuration.downloadCsvFile', true);
                this.setData(data);                
                $('.switch-off').addClass('switch-on');
            }
        },

    });
});
