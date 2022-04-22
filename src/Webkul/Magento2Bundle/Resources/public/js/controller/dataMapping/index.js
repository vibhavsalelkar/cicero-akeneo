/**
 * Data Mapping controller module
 *
 * @author    Navneet Kumar <navneetkumar.symfony813@webkul.com>
 * @copyright 2017 Webkul Software Pvt Ltd (http://www.webkul.com)
 */

'use strict';

define(['pim/controller/front', 'pim/form-builder'],
    function (BaseController, FormBuilder) {
        return BaseController.extend({
            renderForm: function (route) {
                return FormBuilder.build('webkul-magento2-connector-data-mapping-index').then((form) => {
                    form.setElement(this.$el).render();
                });
            }
        });
    }
);