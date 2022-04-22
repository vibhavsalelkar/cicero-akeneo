'use strict';

/**
 * Family edit form add attribute select extension view
 *
 * @author    Alexandr Jeliuc <alex@jeliuc.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    [
        'jquery',
        'underscore',
        'pim/form/common/fields/simple-select-async',
    ], function (
        $,
        _,
        AddAttributeSelect,
    ) {
        return AddAttributeSelect.extend({
            resultsPerPage: 3,
            className: 'AnkFieldContainer ank-attribute',
            events:{
                'change inpute': 'updateModel'
            },
            convertBackendItem(item) {          
                return {
                    id: item,
                    text: item
                };
            },
            updateModel: function(event) {
                const model = this.getFormModel();
                const akeneoAttributeId = $(event.target).val();
                model.set('akeneoAttributeId', akeneoAttributeId);
                model.set('akeneoAttributeName', this.$("select option:selected").text());

            }
        });
    }
);