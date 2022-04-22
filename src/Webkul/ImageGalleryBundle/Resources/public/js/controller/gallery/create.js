'use strict';

/**
 * Asset create controller
 *
 */
define(
    [
        'underscore',
        'pim/controller/front',
        'pim/form-builder'
    ],
    function (_, BaseController, FormBuilder) {
        return BaseController.extend({
            /**
             * {@inheritdoc}
             */
            renderForm: function () {
                if (!this.active) {
                    return;
                }
                return FormBuilder.build('webkul-gallery-create-form')
                    .then((form) => {
                        form.setElement(this.$el).render();

                        return form;
                    });
            }
        });
    }
);
