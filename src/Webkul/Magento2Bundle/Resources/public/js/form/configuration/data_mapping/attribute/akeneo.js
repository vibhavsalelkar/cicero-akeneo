define([
    'jquery',
    'underscore',
    'webkul/magento2/form/configuration/datamapping/modal',
    'pim/user-context',
    'oro/translator',
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    ) {

    return BaseModal.extend({
        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;
            
            const akeneoAttributeId = this.getFormData().akeneoAttributeId;
            const selectedAkeneoAttributeId = akeneoAttributeId;

            this.$el.html(this.template({
                label: __('magento2.form.configuration.data_mapping.properties.akeneo_attribute'),
                akeneoAttributeId: selectedAkeneoAttributeId,
                required: __('pim_enrich.form.required'),
                error: this.parent.validationErrors['akeneoAttributeId'],
                type: this.getFormData().type,
                locale: UserContext.get('uiLocale'),
                fields: null
            }));

            this.getFormModel().set('akeneoAttributeId', selectedAkeneoAttributeId);
            this.getFormModel().set('akeneoAttributeName',this.$("select option:selected").text());
          
            this.delegateEvents();
        }
    });
});

// /src/Webkul/OdooConnector/Resources/public/js/form/configuration/export_mapping/attribute/akeneo.js

// /**
//  * Odoo attribute select2 to be added in a creation form
//  *
//  * @author    Webkul <support@webkul.com>
//  *
//  */

// define([
//     'jquery',
//     'underscore',
//     'webkul/magento2/form/configuration/datamapping/modal',
//     'pim/user-context',
//     'oro/translator',
//     'pim/fetcher-registry',
//     'pim/initselect2',
//     'webkul/magento2/template/configuration/datamapping/attribute/akeneo'
// ], function(
//     $,
//     _,
//     BaseModal,
//     UserContext,
//     __,
//     FetcherRegistry,
//     initSelect2,
//     template
//     ) {

//     return BaseModal.extend({
//         options: {},
//         template: _.template(template),
//         events: {
//             'change select': 'updateModel'
//         },
        
//         /**
//          * Model update callback
//          */
//         updateModel() {
//             const model = this.getFormModel();
//             const akeneoAttributeId = this.$('select').select2('val');
//             model.set('akeneoAttributeId', akeneoAttributeId);
//             model.set('akeneoAttributeName', this.$("select option:selected").text());
//         },

//         /**
//          * Renders the form
//          *
//          * @return {Promise}
//          */
//         render() {
//             // if (!this.configured) return this;

//             // const fetcher = FetcherRegistry.getFetcher('attribute');
//             // const akeneoAttributeId = this.getFormData().akeneoAttributeId;

//             // fetcher.search({options: {'page': 1, 'limit': 100000}, 'types': 'pim_catalog_simpleselect'}).then(function (attributes) {
//             //     const selectedAkeneoAttributeId = akeneoAttributeId || (attributes.length ? attributes[0].code : 0);

//             //     this.$el.html(this.template({
//             //         label: __('magento2.form.configuration.data_mapping.properties.akeneo_attribute'),
//             //         akeneoAttributeId: selectedAkeneoAttributeId,
//             //         required: __('pim_enrich.form.required'),
//             //         attributes: attributes,
//             //         error: this.parent.validationErrors['akeneoAttributeId'],
//             //         type: this.getFormData().type,
//             //         locale: UserContext.get('uiLocale')
//             //     }));

//             //     this.getFormModel().set('akeneoAttributeId', selectedAkeneoAttributeId);
//             //     this.getFormModel().set('akeneoAttributeName', this.$("select option:selected").text());

//             //     initSelect2.init(this.$('select'))
//             // }.bind(this));

//             // this.delegateEvents();
//         }
//     });
// });
