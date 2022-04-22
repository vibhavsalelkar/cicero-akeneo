'use strict';
define([
        'jquery',
        'backbone',
        'pim/form',
        'underscore',
        'routing',
        'pim/common/property',
        'webkulgallery/template/product/field/media',
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
            fileExtension: ["jpg", "jpeg", "bmp", "gif", "png", "svg"],


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
                'click .edit-media' : 'editimage',
                'click .show-url': 'showS3Url',
                'blur .ak-input-copy-link': 'removeS3Link',
                'click .change-visibility': 'changeFileVisibility',
            },

            uploadContext: {},

            showS3Url: function(e) {
                var index = $(e.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();
                var slug = encodeURIComponent(this.getCurrentValue().data[index].filePath);
                var elem = $(e.target).closest('.show-url');

                if(elem.next() && !elem.next().hasClass('ak-input-copy-link')) {   
                    var self = this;
                    $.get(Routing.generate('webkul_awsintegration_connector_media_get_url', { name: slug }))
                    .done(function(response) {
                        if(typeof(response.url) !== 'undefined' && response.url) {
                            self.fileUrl = response.url;
                            elem.after(self.showUrlTemplate({'url': self.fileUrl}));
                            elem.next().focus().select();                                 
                        }
                    });
                }
            },
            changeFileVisibility: function(e) {
                var index = $(e.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();
                var slug = encodeURIComponent(this.getCurrentValue().data[index].filePath);
                $.get(Routing.generate('webkul_awsintegration_connector_change_visibility', { name: slug }))
                .then(this.postSave.bind(this))
                .fail(this.fail.bind(this));
                
            },  

            removeS3Link: function(e) {
                $('.ak-input-copy-link').remove();
            },

            showUrlTemplate: _.template('<input type="text" class="ak-input-copy-link AknTextField" value="<%- url %>">'),

            /**
             * What to do after a save
             */
            postSave: function (response) { 
                messenger.notify(
                    'success',
                    'Visibility changed to ' + response.visibility + ' successfully.'
                );
                this.render();
            },

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
             * On save fail
             *
             * @param {Object} response
             */
            fail: function (response, responseJSON) {
                var msg = "Can't change visibility make sure PutObjectAcl permission is given to bucket.";
                if(typeof(response.responseJSON.visibility) !== 'undefined' && response.responseJSON.visibility) {
                    msg += " Current visibility is " + response.responseJSON.visibility;
                }
                messenger.notify(
                    'error',
                     msg
                );
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

                var fileExtension =  input.files[0].name.split("."); 
                var validFileExtension = _.find(this.fileExtension, function(extension) { 
                    return fileExtension[1] == extension;
                })

                if (typeof validFileExtension === 'undefined') {
                    Dialog.alert(_.__(
                        'pim_enrich.entity.file_extension.not_supported',
                        {'fileExtension': fileExtension, 'supportedFileExtension': this.fileExtension}
                    ));
                    return;
                } 


                var allFiles = input.files;

                $.each(allFiles, function( index, imageFiles ) {
                    
                    var invalidType = ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
                    if(invalidType.includes(imageFiles.type)){
                        messenger.notify(
                            'error',
                            imageFiles.type + 'type of file are not allowed to upload'
                        );
                        return true;
                    }

                    var formData = new FormData();
                    formData.append('file', imageFiles);

                    this.setReady(false);
                    this.uploadContext = {
                        'locale': this.context.locale,
                    };

             
                    $.ajax({
                        url: Routing.generate('webkul_gallery_media_rest_post'),
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
                }.bind(this));
            },
            clearField: function (e) {
                
                var index = $(e.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();
                
                this.clearCurrentValue(index);

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
                        // return;
                if(value && typeof(value.data) === 'undefined' && (value.length !== 0) ) {
                    var dataValue = value;
                    if(Array.isArray(dataValue) === false) {
                        dataValue = [value];
                    }
                    return { 'data': dataValue };
                }
                
                return null === value || (typeof(value.data) === 'object' && value.data === 0) ? undefined : value;
            },
            /**
             * Set current model value for this field
             *
             * @param {*} value
             */
            setCurrentValue: function (value) {
                const dataModel = this.getFormData();

                if(value) {
                    var imagesValue = {};
                    if(typeof(dataModel.medias) === 'undefined') {
                        dataModel.medias = []
                    }
                    
                    if(typeof(dataModel.medias.data) === 'undefined') {
                        dataModel.medias.data = dataModel.medias;
                    }
                    imagesValue = dataModel.medias;

                    if(Array.isArray(imagesValue.data) === false) {
                        imagesValue.data = [imagesValue.data];
                    }
                    
                    imagesValue.data.push(value);
                }

                propertyAccessor.updateProperty(dataModel, this.fieldName, imagesValue);
                
                this.setData(dataModel);
            },

            clearCurrentValue: function(index) {
                const dataModel = this.getFormData();

                if(typeof(dataModel.medias.data) === 'undefined') {
                    dataModel.medias.data = dataModel.medias;
                }

                var imagesValue = dataModel.medias;

                if (typeof(imagesValue.data) !== 'undefined') {
                    imagesValue.data.splice(index, 1);
                }

                propertyAccessor.updateProperty(dataModel, this.fieldName, imagesValue);

                this.setData(dataModel);
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
                
                this.$('.progress').css({opacity: 1});  
                this.$('.progress .bar').css({  
                    width: ((e.loaded / e.total) * 100) + '%'   
                });
            },
            previewImage: function (e) {
                
                var index = $(e.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();               
                var mediaUrl = MediaUrlGenerator.getMediaShowUrl(this.getCurrentValue().data[index].filePath, 'preview');
                if (mediaUrl) {
                    $.slimbox(mediaUrl, '', {overlayOpacity: 0.3});
                }
            },
            editimage: function (event) {
                var index = $(event.target).closest("div.AknMediaField-info").find("input[name='asset_index']").val();
                var mediaUrl = MediaUrlGenerator.getMediaShowUrl(this.getCurrentValue().data[index].filePath, 'preview');
                var id = this.getFormData().id
                var code  = this.getFormData().code;
                

                router.redirectToRoute('webkul_gallery_media_edit',{'code':code,'id':id, 'filePath': this.getCurrentValue().data[index].filePath});
    
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
