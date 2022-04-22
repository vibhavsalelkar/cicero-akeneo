// /src/Webkul/Magento2Bundle/Resources/public/js/form/configuration/data_mapping/category/magento.js

/**
 * Odoo category to be added in a creation form
 *
 * @author    Webkul <support@webkul.com>
 *
 */

define([
    'jquery',
    'underscore',
    'webkul/magento2/form/configuration/datamapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'oro/loading-mask',
    'webkul/magento2/template/configuration/datamapping/category/magento'
], function(
    $,
    _,
    BaseForm,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    LoadingMask,
    template
    ) {

    return BaseForm.extend({
        options: {},
        template: _.template(template),
        events: {
            'change select': 'updateModel'
        },

        /**
         * Model update callback
         */
        updateModel() {
            const model = this.getFormModel();
            const magentoCategoryId = this.$('select').select2('val');
            model.set('magentoCategoryId', magentoCategoryId);
            model.set('magentoCategoryName', this.$("select option:selected").text());
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;

            var loadingMask = new LoadingMask();
            loadingMask.render().$el.appendTo(this.getRoot().$el).show();
            
            const fetcher = FetcherRegistry.getFetcher('magento-category');
            const magentoCategoryId = this.getFormData().magentoCategoryId;

            fetcher.fetchAll().then(function (categories) {
                const selectedMagentoCategoryId = magentoCategoryId || (categories.length ? categories[0].id : 0);
                this.$el.html(this.template({
                    label: __('magento.form.configuration.data_mapping.properties.magento_category'),
                    magentoCategoryId: selectedMagentoCategoryId,
                    required: __('pim_enrich.form.required'),
                    categories: categories,
                    error: this.parent.validationErrors['magentoCategoryId'],
                    type: this.getFormData().type
                }));

                this.getFormModel().set('magentoCategoryId', selectedMagentoCategoryId);
                this.getFormModel().set('magentoCategoryName', this.$("select option:selected").text());

                initSelect2.init(this.$('select'))
            }.bind(this));

            this.delegateEvents();
        }
    });
});
