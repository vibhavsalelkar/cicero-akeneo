'use strict';

define([
    'underscore',
    'pim/controller/front',
    'pim/form-builder',
    'pim/fetcher-registry',
    'pim/user-context',
    'pim/page-title',
    'pim/i18n'
],
function (
    _,
    BaseController,
    FormBuilder,
    fetcherRegistry,
    UserContext,
    PageTitle,
    i18n
) {
    return BaseController.extend({
        /**
         * {@inheritdoc}
         */
        renderForm: function (route) {

            if (!this.active) {
                return;
            }
            return fetcherRegistry.getFetcher('gallery').fetch(route.params.code, {cached: false})
                .then((asset) => {
                    
                    PageTitle.set({'gallery.label': route.params.code});
                    
                    return FormBuilder.getFormMeta('webkul-gallery-edit-form')
                        .then(FormBuilder.buildForm)
                        .then((form) => {

                        return form.configure().then(() => {
                            return form;
                        });
                    })
                    .then((form) => {
                        this.on('pim:controller:can-leave', function (event) {
                            form.trigger('pim_enrich:form:can-leave', event);
                        });
                        form.setData(asset);
                        form.trigger('pim_enrich:form:entity:post_fetch', asset);
                        form.setElement(this.$el).render();

                        return form;
                    });
                });
        }
    });
});
