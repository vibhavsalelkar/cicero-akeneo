'use strict';
define(
    [
        'oro/datagrid/enabled-cell',
        'webkulgallery/template/datagrid/star-cell',
    ],
    function (
        parentEnabledCell,
        template
    ) {
        return parentEnabledCell.extend({
            template: _.template(template),
        });
    }
);
