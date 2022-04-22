// /src/Webkul/Magento2/Resources/public/js/form/configuration/data_mapping/type.js

/**
 * Mapping type select2 to be added in a creation form
 *
 * @author    Webkul <support@webkul.com>
 *
 */

define([
    'jquery',
    'underscore',
    'pim/form',
    'pim/user-context',
    'oro/translator',
    'pim/initselect2',
    'webkul/magento2/template/configuration/datamapping/type'
], function(
    $,
    _,
    BaseForm,
    UserContext,
    __,
    initSelect2,
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
            const type = this.$('select').select2('val');
            model.set('type', type);

            this.toggleModal(type)
        },

        /**
         * Toggle category/attribute fields
         */
        toggleModal(type) {
            if(type == 'category') {
                $('.AknFieldContainer.akn-category').show();
                $('.AknFieldContainer.magento-category').show();
                $('.AknFieldContainer.akn-attribute').hide();
                $('.AknFieldContainer.magento-attribute').hide();
            } else {
                $('.AknFieldContainer.magento-attribute').show();
                $('.AknFieldContainer.akn-attribute').show();
                $('.AknFieldContainer.magento-category').hide();
                $('.AknFieldContainer.akn-category').hide();
            }
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;

            const type = this.getFormData().type;
            const selectedType = type || 'category';
            this.getFormModel().set('type', selectedType);

            this.toggleModal(selectedType)

            this.$el.html(this.template({
                label: __('magento2.form.configuration.data_mapping.properties.type'),
                type: selectedType,
                required: __('pim_enrich.form.required'),
                types: [{
                        'code': 'category',
                        'label': __('magento2.form.configuration.data_mapping.properties.type.category')
                    }, {
                        'code': 'attribute',
                        'label': __('magento2.form.configuration.data_mapping.properties.type.attribute')
                    }]
            }));
            this.getFormModel().set('type', selectedType);
            
            initSelect2.init(this.$('select'))

            this.delegateEvents();
        }
    });
});
