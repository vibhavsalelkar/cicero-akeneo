'use strict';
define([
        'jquery',
        'backbone',
        'pim/form',
        'underscore',
        'routing',
        'pim/common/property',
        'webkulgallery/template/product/field/image-media-field',
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
            fieldTemplate: _.template(fieldTemplate),

            /**
             * Initialize this field
             *
             * @param {Object} attribute
             *
             * @returns {Object}
             */
            initialize: function (meta) {
                this.config = meta.config;

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
                'change input[type="file"]': 'updateModel',
                'click  .clear-field': 'clearField',
                'click  .open-media': 'previewImage',
                'click .edit-media' : 'editimage'
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

            renderCopyInput: function (value) {
                return this.getTemplateContext()
                    .then(function (context) {
                        var copyContext = $.extend(true, {}, context);
                        copyContext.value = value;
                        copyContext.context.locale    = value.locale;
                        copyContext.context.scope     = value.scope;
                        copyContext.editMode          = 'view';
                        copyContext.mediaUrlGenerator = MediaUrlGenerator;
                        copyContext.Routing = Routing;

                        return this.renderInput(copyContext);
                    }.bind(this));
            },
            /**
             * Render this field
             *
             * @returns {Object}
             */
            render: function () {
                this.setEditable(!this.locked);
                this.setValid(true);
                this.elements = {};
                var promises  = [];
                
                $.when.apply($, promises)
                    .then(this.getTemplateContext.bind(this))
                    .then(function (templateContext) {
                        this.$el.html(this.fieldTemplate(templateContext));
                        // this.$el.append(this.renderInput(templateContext));

                        this.renderElements();
                        this.postRender();
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
             * Is called after rendering the input
             */
            postRender: function () {},
                   
            updateModel: function () {
                if (!this.isReady()) {
                    Dialog.alert(_.__(
                        'pim_enrich.entity.product.info.already_in_upload',
                        {'locale': this.context.locale, 'scope': this.context.scope}
                    ));
                }

                var input = this.$('input[type="file"]').get(0);
                
                if (!input || 0 === input.files.length) {
                    return;
                }

                var formData = new FormData();
                formData.append('file', input.files[0]);

                this.setReady(false);
                this.uploadContext = {
                    'locale': this.context.locale,
                };


                $.ajax({
                    url: Routing.generate('pim_enrich_media_rest_post'),
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    cache: false,
                    processData: false,
                    xhr: function () {
                        var myXhr = $.ajaxSettings.xhr();
                        if (myXhr.upload) {
                            myXhr.upload.addEventListener('progress', this.handleProcess.bind(this), false);
                        }

                        return myXhr;
                    }.bind(this)
                })
                .done(function (data) {
                    if(data) {
                        this.setUploadContextValue(data);
                        this.render();
                    }
                }.bind(this))
                .fail(function (xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message ?
                        xhr.responseJSON.message :
                        _.__('pim_enrich.entity.product.error.upload');
                    messenger.enqueueMessage('error', message);
                })
                .always(function () {
                    this.$('> .akeneo-media-uploader-field .progress').css({opacity: 0});
                    this.setReady(true);
                    this.uploadContext = {};
                }.bind(this));
            },
            clearField: function () {
                this.setCurrentValue({
                    filePath: null,
                    originalFilename: null
                });

                this.render();
            },
            /**
             * Set as valid
             *
             * @param {boolean} valid
             */
            setValid: function (valid) {
                this.valid = valid;
            },

            /**
             * Return whether is valid
             *
             * @returns {boolean}
             */
            isValid: function () {
                return this.valid;
            },

            /**
             * Set the focus on the input of this field
             */
            setFocus: function () {
                this.$('input:first').focus();
            },

            /**
             * Set this field as editable
             *
             * @param {boolean} editable
             */
            setEditable: function (editable) {
                this.editable = editable;
            },

            /**
             * Set this field as locked
             *
             * @param {boolean} locked
             */
            setLocked: function (locked) {
                this.locked = locked;
            },

            /**
             * Return whether this field is editable
             *
             * @returns {boolean}
             */
            isEditable: function () {
                return this.editable;
            },

            /**
             * Set this field as ready
             *
             * @param {boolean} ready
             */
            setReady: function (ready) {
                this.ready = ready;
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
             * Get the current edit mode (can be 'edit' or 'view')
             *
             * @returns {string}
             */
            getEditMode: function () {
                if (this.editable) {
                    return 'edit';
                } else {
                    return 'view';
                }
            },

            /**
             * Return whether this field can be seen
             *
             * @returns {boolean}
             */
            canBeSeen: function () {
                return true;
            },

            getCurrentValue: function () {
                const value = propertyAccessor.accessProperty(
                    this.getFormData(),
                    this.fieldName
                );
                

                if(value && typeof(value.data) === 'undefined' && (value.length !== 0) ) {
                    return { 'data': value };
                }

                return null === value || (typeof(value.data) === 'object' && value.data === 0) ? undefined : value;
            },
            /**
             * Set current model value for this field
             *
             * @param {*} value
             */
            setCurrentValue: function (value) {
                const data = this.getFormData();
                if(value) {
                    value = { 'data' : value };
                }
                propertyAccessor.updateProperty(data, this.fieldName, value);

                this.setData(data);
            },

            /**
             * Get the label of this field (default is code surrounded by brackets)
             *
             * @returns {string}
             */
            getLabel: function () {
                return _.__('File');
            },

            handleProcess: function (e) {
                if (this.uploadContext.locale === this.context.locale &&
                    this.uploadContext.scope === this.context.scope
                ) {
                    this.$('> .akeneo-media-uploader-field .progress').css({opacity: 1});
                    this.$('> .akeneo-media-uploader-field .progress .bar').css({
                        width: ((e.loaded / e.total) * 100) + '%'
                    });
                }
            },
            previewImage: function () {
                var mediaUrl = MediaUrlGenerator.getMediaShowUrl(this.getCurrentValue().data.filePath, 'preview');
                if (mediaUrl) {
                    $.slimbox(mediaUrl, '', {overlayOpacity: 0.3});
                }
            },
            editimage: function (event) {
                var mediaUrl = MediaUrlGenerator.getMediaShowUrl(this.getCurrentValue().data.filePath, 'preview');
               
                var code  = this.getFormData().code;                
                event.preventDefault();
                // return false;
                router.redirectToRoute('webkul_edit_media',{'code':code, 'url': mediaUrl, 'filepath': this.getCurrentValue().data.filePath });
    
            },

            setUploadContextValue: function (value) {
                this.setCurrentValue(value);
            },
            /**
             * Recursively search for the first tab ancestor if any, and returns its code. Sorry.
             *
             * @returns {String}
             */
            getTabCode() {
                let parent = this.getParent();
                while (!(parent instanceof Tab)) {
                    parent = parent.getParent();
                    if (null === parent) {
                        return null;
                    }
                }

                return parent.code;
            }       
        });
    }
);
