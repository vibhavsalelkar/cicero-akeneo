'use strict';
define([
        'jquery',
        'backbone',
        'pim/form',
        'underscore',
        'routing',
        'pim/common/property',
        // 'webkulgallery/template/product/field/media',
        'webkulgallery/template/select/asset-group',      
        'pim/dialog',
        'oro/messenger',
        'pim/media-url-generator',
        'pim/i18n',
        'pim/field',
        'pim/router',
        'jquery.slimbox'
        
    ],
    function ($, Backbone, BaseForm, _, Routing, propertyAccessor, fieldTemplate, Dialog, messenger, MediaUrlGenerator, i18n,Field, router) {
        var FieldModel = Backbone.Model.extend({
            values: []
        });

        return BaseForm.extend({
            tagName: 'div',
            className: 'AknAssetGroupField',
            fieldName: null,
            options: {},
            attributes: function () {
                return {
                    'data-attribute': this.options ? this.options.code : null
                };
            },
            attribute: null,
            context: {},
            model: FieldModel,
            elements: {},
            editable: true,
            ready: true,
            valid: true,
            locked: false,            
            imagesValue: {},
            fieldTemplate: _.template(fieldTemplate),            
            events: {                
                'click  .open-image': 'previewImage',        
            },

            previewImage: function (e) {
                var index = $(e.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();
            
                var mediaUrl = MediaUrlGenerator.getMediaShowUrl(this.getCurrentValue().data[index].filePath, 'preview');
                // var mediaUrl = '/media/show/2%252Fe%252Fe%252F5%252F2ee501eb834237e1b7f23fa64de0c0d879f855df_jaishreekrishna.jpg/preview';
                if (mediaUrl) {
                    $.slimbox(mediaUrl, '', {overlayOpacity: 0.3});
                }
            },
        });
    }
);