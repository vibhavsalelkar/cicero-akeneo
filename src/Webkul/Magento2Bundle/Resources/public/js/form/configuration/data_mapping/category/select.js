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
        AddCategorySelect,
    ) {
        return AddCategorySelect.extend({
            resultsPerPage: 3,
            className: 'AnkFieldContainer ank-category',
            events:{
                'change inpute': 'updateModel'
            },
            updateModel: function(event) {
                const model = this.getFormModel();
                const akeneoCategoryId = $(event.target).val();
                model.set('akeneoCategoryId', akeneoCategoryId);
                model.set('akeneoCategoryName', this.$("select option:selected").text());

            }
        });
    }
);