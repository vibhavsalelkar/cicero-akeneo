'use strict';

define(['pim/form/common/delete', 'webkulgallery/remover/asset'], function (DeleteForm, AssetRemover) {
    return DeleteForm.extend({
        remover: AssetRemover
    });
});
