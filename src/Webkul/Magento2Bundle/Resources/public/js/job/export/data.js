'use strict';
/**
 * This extension override the product data filter for the category filter.
 * 
 */
define([
    'pim/job/product/edit/content/data',
    'magento2/template/job/export/data'
], function(BaseForm,template) {
    return BaseForm.extend({
        template: _.template(template),
        /**
         * {@inheritdoc}
         */
        render: function () {
            var checked = this.getFormData();
            
            if(checked && checked.configuration && checked.configuration.channelWiseExport && checked.configuration.channelWiseExport === true ) {
                BaseForm.prototype.render.apply(this, arguments);
            }
        }
    });
});