'use strict';

define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'routing',
        'pim/form/common/creation/modal',
        'oro/loading-mask',
        'pim/router',
    ],
    function (
        $,
        _,
        __,
        Routing,
        BaseModal,
        LoadingMask,
        router,
    ) {
        return BaseModal.extend({
            validationErrors: {},
            
            /**
             * {@inheritdoc}
             */
            save() {
                this.validationErrors = {};

                const loadingMask = new LoadingMask();
                this.$el.empty().append(loadingMask.render().$el.show());

                let data = this.getFormData();

                return $.ajax({
                    url: Routing.generate(this.config.postUrl),
                    type: 'POST',
                    data: JSON.stringify(data)
                }).fail(function (response) {
                    this.validationErrors = response.responseJSON;
                    this.getRoot().trigger(
                        'pim_enrich:form:entity:bad_request',
                        {'sentData': data, 'response': response.responseJSON}
                    );                    
                    this.render();
                }.bind(this))
                .always(function(response) {
                    if(response.id) {
                        window.location.hash = Routing.generate('webkul_magento2_credentials_edit', { id: response.id });
                    }
                    loadingMask.remove();
                });
            }
        });
    }
);
