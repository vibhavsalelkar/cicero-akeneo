"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/credential',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.credentials.tab'),
            code: 'magento2_connector_credential',
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
                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

        });
    }
);
