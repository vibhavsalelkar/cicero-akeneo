'use strict';
define([
        'jquery',
        'backbone',
        'pim/form',
        'underscore',
        'routing',
        'pim/common/property',
        'webkulgallery/template/select/image-gallery-slider',
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
            className: 'AknComparableFields field-container',
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

            /**
             * Initialize this field
             *
             * @param {Object} attribute
             *
             * @returns {Object}
             */
            initialize: function (meta) {
                return;                

                this.config = meta.config;
                return;
                if (undefined === this.config.fieldName) {
                    throw new Error('This view must be configured with a field name.');
                }                
                this.fieldName = this.config.fieldName;
                this.readOnly = this.config.readOnly || false;
                this.errors = [];
                
                this.elements  = {};
                this.context   = {};
                this.attribute = {};
                this.attribute.field_type = this.attribute.type = 'pim_catalog_image';

                BaseForm.prototype.initialize.apply(this, arguments);
            },

            events: {
                'click  .open-media': 'previewImage'
            },

            uploadContext: {},

            renderInput: function (context) {
                
                return this.fieldTemplate(context);
            },
            getTemplateContext: function () {

                return Field.prototype.getTemplateContext.apply(this, arguments)
                    .then(function (templateContext) {
                        templateContext.inUpload          = !this.isReady();
                        templateContext.mediaUrlGenerator = MediaUrlGenerator;
                        templateContext.Routing = Routing;
                                                
                        if(this.getCurrentValue()) {
                            
                            templateContext.value = this.getCurrentValue();
                        }

                        return templateContext;
                    }.bind(this));
            },

            /**
             * Render this field
             *
             * @returns {Object}
             */
            render: function () {                
                this.setValid(true);
                this.elements = {};
                var promises  = [];
                
                $.when.apply($, promises)
                    .then(this.getTemplateContext.bind(this))
                    .then(function (templateContext) {
                        this.$el.html(this.fieldTemplate(templateContext));
                       
                        this.renderElements();                        
                        this.delegateEvents();
                    }.bind(this));

                return this;
            },          
            /**
             * Render elements of this field in different available positions
             */
            renderElements: function () {
                _.each(this.elements, function (elements, position) {
                    var $container = 'field-input' === position ?
                        this.$('.original-field .field-input') :
                        this.$('.' + position + '-elements-container');

                    $container.empty();

                    _.each(elements, function (element) {
                        if (typeof element.render === 'function') {
                            $container.append(element.render().$el);
                        } else {
                            $container.append(element);
                        }
                    }.bind(this));

                }.bind(this));
            },                   
            

            /**
             * Return whether this field is ready
             *
             * @returns {boolean}
             */
            isReady: function () {
                return this.ready;
            },

            /**
             * Set as valid
             *
             * @param {boolean} valid
             */
            setValid: function (valid) {
                this.valid = valid;
            },


            getCurrentValue: function () {
                const value = propertyAccessor.accessProperty(
                    this.getFormData(),
                    this.fieldName
                );
                if(value && typeof(value.data) === 'undefined' && (value.length !== 0) ) {
                    var dataValue = value;
                    if(Array.isArray(dataValue) === false) {
                        dataValue = [value];
                    }
                    return { 'data': dataValue };
                }
                
                return null === value || (typeof(value.data) === 'object' && value.data === 0) ? undefined : value;
            },            
            

            previewImage: function (e) {                
                var index = $(e.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();               
                var mediaUrl = MediaUrlGenerator.getMediaShowUrl(this.getCurrentValue().data[index].filePath, 'preview');              
                if (mediaUrl) {
                    $.slimbox(mediaUrl, '', {overlayOpacity: 0.3});
                }
            },
                 
        });
    }
);
