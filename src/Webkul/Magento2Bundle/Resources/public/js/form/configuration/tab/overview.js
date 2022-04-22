"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/overview',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.overview'),
            template: _.template(template),
            code: 'magento2_connector_overview',

            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.trigger('tab:register', {
                    code: this.code,
                    label: this.label
                });

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                $('.magento-save-config').hide();
                
                this.$el.html(this.template());

                return BaseForm.prototype.render.apply(this, arguments);
            },
        });
    }
);
