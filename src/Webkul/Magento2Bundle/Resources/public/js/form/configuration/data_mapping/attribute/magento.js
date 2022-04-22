// /src/Webkul/OdooConnector/Resources/public/js/form/configuration/export_mapping/attribute/odoo.js

/**
 * Odoo attribute select2 to be added in a creation form
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
    'webkul/magento2/template/configuration/datamapping/attribute/magento'
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    template
    ) {

    return BaseModal.extend({
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
            const magentoAttributeId = this.$('select').select2('val');
            model.set('magentoAttributeId', magentoAttributeId);
            model.set('MagentoAttributeName', this.$("select option:selected").text());
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;

            const fetcher = FetcherRegistry.getFetcher('magento-attribute');
            const magentoAttributeId = this.getFormData().magentoAttributeId;

            fetcher.fetchAll().then(function (attributes) {
                const selectedMagentoAttributeId = magentoAttributeId || (attributes.length ? attributes[0].id : 0);
                this.$el.html(this.template({
                    label: __('magento2_connector.form.configuration.data_mapping.properties.magento_attribute'),
                    magentoAttributeId: selectedMagentoAttributeId,
                    required: __('pim_enrich.form.required'),
                    attributes: attributes,
                    error: this.parent.validationErrors['magentoAttributeId'],
                    type: this.getFormData().type
                }));

                this.getFormModel().set('magentoAttributeId', selectedMagentoAttributeId);
                this.getFormModel().set('MagentoAttributeName', this.$("select option:selected").text());

                initSelect2.init(this.$('select'))
            }.bind(this));

            this.delegateEvents();
        }
    });
});
