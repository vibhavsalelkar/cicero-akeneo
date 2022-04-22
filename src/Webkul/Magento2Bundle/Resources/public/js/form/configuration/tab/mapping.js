"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/mapping',
        'pim/router',
        'oro/loading-mask',
        'pim/fetcher-registry',
        'pim/user-context',
        'pim/initselect2',
        
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        router,
        LoadingMask,
        FetcherRegistry,
        UserContext,
        initSelect2
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.attribute_mapping'),
            template: _.template(template),
            code: 'magento2_connector_mapping',
            events: {
                'change .AknFormContainer-Mappings input:not(".view-only")': 'updateModel',
                'change .AknFormContainer-Mappings select:not(".view-only")': 'updateModel',
                'click .field-add': 'addField', 
                'click .AknIconButton--remove.delete-row': 'removeField',
                'click .select-all': 'selectAll',            
                'click .remove-all': 'deselectAll',            
                'click .AknButton.AknButton--mapping': 'redirectToMappingPage',
                'click .video-row-add': 'addVideoRow',
                'click .delete-video-row': 'removeVideoRow',
            },
            fields: null,
            attributes: null,
            imageAlts: null,
            variantAttributes: null,
            videoRows: [],
            defaultImage: '',
            imageRoles : {
                'base_image' : 'base_image',
                'small_image' : 'small_image',
                'thumbnail_image' : 'thumbnail_image',
                
            },
            childImageRoles : {
                'child_base_image' : 'child_base_image',
                'child_small_image' : 'child_small_image',
                'child_thumbnail_image' : 'child_thumbnail_image',
                
            },
            
            imageAttrs: [],

            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.trigger('tab:register', {
                    code: this.code,
                    label: this.label
                });

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                $('.magento-save-config').show();
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();

                var fields;
                var attributes;
                var videoRows;

                if(this.fields && this.attributes) {
                    fields = this.fields;
                    attributes = this.attributes;
                } else {
                    fields = FetcherRegistry.getFetcher('magento-fields').fetchAll();
                    attributes = FetcherRegistry.getFetcher('attribute').search({options: {'page': 1, 'limit': 10000 } });
                }

                var self = this; 

                var formData = self.getFormData();  
                if(_.isEmpty(self.videoRows) && typeof formData['otherMappings'] !== 'undefined' && typeof formData['otherMappings']['videoAttrsMapping'] !== 'undefined') {
                    self.videoRows = formData['otherMappings']['videoAttrsMapping']
                }

                
                Promise.all([fields, attributes, formData]).then(function(values) {
                    $('#container .AknButton--apply.save').show();
                    self.fields = values[0];
                    var formData = values[2];  
                    
                    self.addSelectedAttributes(self.getArrayValues(formData['mapping']));
                    
                    if(formData) {
                        var extraFields = self.generateExtraFields(self.fields, formData);
                        $.each(extraFields, function(key,field) {
                            self.fields.push(field); 
                        });
                    }
                    self.attributes = self.sortByLabel(values[1]);
                    self.imageAttrs = self.sortImages(values[1], formData, 'images');
                    self.imageAlts = self.sortImages(values[1], formData, 'images_alts');
                    
                    var selectedImages = formData && typeof(formData['otherMappings']) !== 'undefined' && typeof(formData['otherMappings']['images']) !== 'undefined' ?  formData['otherMappings']['images'] : [];
                    var videoImagesAttrs = _.map(self.imageAttrs, function(imageAttr) {
                        if(selectedImages.indexOf(imageAttr.code) === -1) {
                            return imageAttr;
                        }
                    });
                    
                    videoImagesAttrs = _.filter(videoImagesAttrs);

                    if(_.isEmpty(self.videoRows) && typeof formData['otherMappings'] !== 'undefined' && typeof formData['otherMappings']['videoAttrsMapping'] !== 'undefined') {
                        self.videoRows = formData['otherMappings']['videoAttrsMapping']
                    }
                    
                    self.$el.html(self.template({
                        fields: self.fields,
                        attributes: self.attributes,
                        imageAttrs: self.imageAttrs,
                        videoRows: self.videoRows,
                        videoImagesAttrs: videoImagesAttrs,
                        imageAlts: self.imageAlts,
                        videoRows: self.videoRows,
                        videoImagesAttrs: videoImagesAttrs,
                        defaultImage: self.defaultImage,
                        imageRoles: self.imageRoles,
                        childImageRoles: self.childImageRoles,
                        model: formData,
                        mapping: formData['mapping'],
                        childmapping: formData['childmapping'],
                        currentLocale: UserContext.get('uiLocale'),
                        mappedAttributesCode: _.union(_.values(formData['mapping']) , _.values(formData['childmapping']))
                    }));
                    
                    $('.select2').select2();
                    
                    self.$('*[data-toggle="tooltip"]').tooltip();
                    loadingMask.hide().$el.remove();
                });

                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

            generateExtraFields: function(fields, formData) {
                var extraFields = [];
                var fieldCodes = [];
                $.each(fields, function(key, value) {
                    fieldCodes.push(value.name);
                });
                
                if('undefined' !== typeof(formData['mapping']) && formData['mapping'] && 'undefined' !== typeof(formData['draftMapping']) && formData['draftMapping']) {
                    var mapping = _.extend(formData['draftMapping'], formData['mapping']);
                    
                    $.each(mapping, function(key, value) {
                        if(-1 === fieldCodes.indexOf(key)) {
                            extraFields.push({
                                    'name': key,
                                    'types': null,
                                    'dynamic': true
                            });
                        }
                    });
                }

                return extraFields;
                
            },
  
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var val;                
                var data = JSON.parse(JSON.stringify(this.getFormData()));            
                if($(event.target).hasClass('select2') && ($(event.target).hasClass('select2-container-multi') || $(event.target).attr('name') == 'images' || $(event.target).attr('name') == 'images_alts')) {
                    val = $(event.target).select2('data');

                    val = val.map(function(obj) { return obj.id });
                    
                    if($(event.target).attr('name') == 'images' && typeof data.otherMappings !== 'undefined' && typeof data.otherMappings.images !== 'undefined') {
                        var diffData = _.difference(data.otherMappings.images, val);
                        if(diffData.length > 0) {
                            var diffIndex = _.indexOf(data.otherMappings.images, diffData[0]);
                            if(typeof(data.otherMappings.images_alts) != 'undefined') {
                                data.otherMappings.images_alts[diffIndex] = null
                                data.otherMappings.images_alts = _.without(data.otherMappings.images_alts, null);                           
                            }                            

                        }
                     }

                } else {
                    val = $(event.target).val();
                }
                var wrapper = $(event.target).attr('data-wrapper') ? $(event.target).attr('data-wrapper') : 'mapping' ;
                if(typeof(data[wrapper]) === 'undefined' || !data[wrapper] || typeof(data[wrapper]) !== 'object' || data[wrapper] instanceof Array) {
                    
                    data[wrapper] = {};
                }
               
                if(typeof $(event.target).attr('data-name') !== 'undefined') {
                    if(
                        typeof data[wrapper][$(event.target).attr('data-name')] === 'undefiend' 
                        || !data[wrapper][$(event.target).attr('data-name')]
                        || typeof data[wrapper][$(event.target).attr('data-name')] !== 'object'
                        || (data[wrapper][$(event.target).attr('data-name')] instanceof Array
                        && ['videoAttrsMapping'].indexOf(data[wrapper][$(event.target).attr('data-name')]) !== -1)
                    ) {
                        data[wrapper][$(event.target).attr('data-name')] = {};
                    }
                    
                    if(
                        typeof data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')] === 'undefiend' 
                        || !data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')]
                        || typeof data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')] !== 'object'
                        || data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')] instanceof Array
                    ) {
                        
                        data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')] = {};
                    }

                    if(
                        
                        typeof data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')][$(event.target).attr('name')] === 'undefiend' 
                        || !data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')][$(event.target).attr('name')]
                        || typeof data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')][$(event.target).attr('name')] !== 'object'
                        || data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')][$(event.target).attr('name')] instanceof Array
                    ) {
                        
                        data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')][$(event.target).attr('name')] = {};
                    }
                    
                    data[wrapper][$(event.target).attr('data-name')][$(event.target).attr('data-item-id')][$(event.target).attr('name')] = val;
                    
                } else {
                    data[wrapper][$(event.target).attr('name')] = val;
                }
                
                // Set image to null if image role attribue is not present in images
                if($(event.target).attr('name') == 'images' && typeof data.otherMappings !== 'undefined' && typeof data.otherMappings.images !== 'undefined') {
                    var otherMappingImages = data.otherMappings.images;
                    
                    _.each(this.imageRoles, function(imageRole, imageRoleKey) {
                        if(typeof data.otherMappings[imageRole] != 'undefined' && otherMappingImages.indexOf(data.otherMappings[imageRole]) == -1) {
                            data.otherMappings[imageRole] = '';
                            if(otherMappingImages.length == 1) {
                                data.otherMappings[imageRole] = otherMappingImages[0];
                            }
                        }
                    });
                    _.each(this.childImageRoles, function(imageRole, imageRoleKey) {
                        if(typeof data.otherMappings[imageRole] != 'undefined' && otherMappingImages.indexOf(data.otherMappings[imageRole]) == -1) {
                            data.otherMappings[imageRole] = '';
                            if(otherMappingImages.length == 1) {
                                data.otherMappings[imageRole] = otherMappingImages[0];
                            }
                        }
                    });
                }

                if($(event.target).attr('name') == 'images_alts') {
                    if( !(data.otherMappings.images_alts.length > data.otherMappings.images.length)) {
                        this.setData(data); 
                    }
                } else {
                    this.setData(data);                    
                }
                this.render();  
            },
            addVideoRow: function(e) {
                
                this.videoRows.push({});
                this.render();
            },
            removeVideoRow: function(e) {
                var fieldId = $(e.target).attr('data-item-id');
                var fieldName = $(e.target).attr('data-name');
                this.videoRows.splice(fieldId, 1);
                this.render();
            },
            addVideoRow: function(e) {
                
                this.videoRows.push({});
                this.render();
            },
            removeVideoRow: function(e) {
                var fieldId = $(e.target).attr('data-item-id');
                var fieldName = $(e.target).attr('data-name');
                this.videoRows.splice(fieldId, 1);
                this.render();
            },
            addField: function(e) {
                this.resetDynamicErrors(e);

                var field = $('#dynamic-filed-input');
                var val = field.val();
                var findField = _.findWhere(this.fields, {'name' : val});
                    
                if(val && this.reservedAttributes.indexOf(val.toLowerCase()) === -1 && typeof findField === 'undefined') {
                    field.val('');
                    
                    var newField = {
                                'name': val,
                                'types': null,
                                'dynamic': true
                            };
                    
                    this.fields.push(newField);
                    var data = this.getFormData();
                    var wrapper = 'draftMapping';
                    if(typeof(data[wrapper]) === 'undefined' || !data[wrapper] || typeof(data[wrapper]) !== 'object' || data[wrapper] instanceof Array) {
                        data[wrapper] = {};
                    }
                    data[wrapper][val] = field.val();
                    this.setData(data);
                    this.render();
                } else {
                    this.addDynamicErrors(e);
                }
            },
            removeField: function(e) {
                var fieldId = $(e.target).attr('data-id');
                var fieldName = $(e.target).attr('data-name');
                this.fields.splice(fieldId, 1);

                var data = this.getFormData();
                if('undefined' !== typeof(data['mapping'])) {
                    delete data['mapping'][fieldName];
                }
                if('undefined' !== typeof(data['childmapping'])) {
                    delete data['childmapping'][fieldName];
                }
                if('undefined' !== typeof(data['draftMapping'])) {
                    delete data['draftMapping'][fieldName];
                }
                this.setData(data);

                this.render();                
            },
            deselectAll: function(e) {
                var target = $('#' + $(e.target).attr('data-for'));
                if(target) {
                    target.val([]);
                    target.trigger('change');
                }
            },
            selectAll: function(e) {
                var target = $('#' + $(e.target).attr('data-for'));
                if(target) {
                    var mappedFields = $('#mapped-fields select.attributeValue');
                    var mappedValues = [];
                    $.each(mappedFields, function(key, option) {
                        if(option.value) {
                            mappedValues.push(option.value);
                        }
                    });
                    var values = [];
                    $.each(target.find('option'), function(key, option) {
                        if(option.value && mappedValues.indexOf(option.value) === -1) {
                            values.push(option.value);
                        }
                    });

                    target.val(values);
                    target.trigger('change');
                }
            },
            getArrayValues: function(data) {
                var values = [];
                if(data) {
                    $.each(data, function(key, value) {
                        if(value) {
                            values.push(value);
                        }
                    });
                }

                return values;
            },

            selectedAttributes: [],
            
            /**
             * add the selected attributes
             *
             * @param array values
             */
            addSelectedAttributes: function(values) {
                this.selectedAttributes = values;
            },

            /**
             * Reset the Field Errors
             *
             * @param {Event} e
             */
            resetDynamicErrors: function(e) {
                $(e.target).closest('.field-group').find('.AknFieldContainer-validationError').remove();
            },

            /**
             * Add the Field Errors
             *
             * @param {Event} e
             */
            addDynamicErrors: function(e) {
                $(e.target).closest('.field-group').append('<span class="AknFieldContainer-validationError"><i class="icon-warning-sign"></i><span class="error-message">This value is not valid.</span></span>');
            }, 

            /**
             * sort the Labels 
             *
             * @param array data
             */
            sortByLabel: function(data) {
                data.sort(function(a, b) {
                    var textA = typeof(a.labels[UserContext.get('uiLocale')]) !== 'undefined' && a.labels[UserContext.get('uiLocale')] ? a.labels[UserContext.get('uiLocale')].toUpperCase() : a.code.toUpperCase();
                    var textB = typeof(b.labels[UserContext.get('uiLocale')]) !== 'undefined' && b.labels[UserContext.get('uiLocale')] ? b.labels[UserContext.get('uiLocale')].toUpperCase() : b.code.toUpperCase();
                    return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
                });
                return data;
            },

            /**
             * sort the Images according to selection
             *
             * @param array data
             * @param array fields
             */
            sortImages: function(data,  fields, mappingData) {
                var imageData = [];

                switch (mappingData) {
                    case 'images' :
                        var mappedData = (typeof(fields.otherMappings) !== 'undefined' && typeof(fields.otherMappings.images) !== 'undefined') && fields.otherMappings.images ? fields.otherMappings.images : [];
                        var catelogType = 'pim_catalog_image'
                        if(mappedData.length == 1) {
                            this.defaultImage = mappedData[0];
                        } else if(mappedData.length == 0) {
                            this.defaultImage = '';
                        }
                    break;
                    case 'images_alts' :
                        var mappedData = (typeof(fields.otherMappings) !== 'undefined' && typeof(fields.otherMappings.images_alts) !== 'undefined') && fields.otherMappings.images_alts ? fields.otherMappings.images_alts : [];
                        var catelogType = 'pim_catalog_text'
                    break;
                };
                
                for(var i=0; i < data.length; i++) {
                    if(data[i].type === catelogType) {
                        imageData.push(data[i]);
                    }
                }
                imageData.sort(function(a, b) {
                    var textA = mappedData.indexOf(a.code);
                    var textB = mappedData.indexOf(b.code);
                    return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
                });
                return imageData;                
            },
            /**
             * Reserved Attributes
             *
             * @var array
             */
            reservedAttributes: ['sku', 'name', 'weight', 'price', 'description', 'short_description', 'quantity', 'meta_title', 'meta_keyword', 'meta_description', 'url_key', 'id', 'type_id', 'created_at', 'updated_at', 'attribute_set_id', 'category_ids',  'bundle_price_type', 'bundle_sku_type', 'bundle_price_view', 'bundle_weight_type', 'bundle_values', 'bundle_shipment_type'],

            /**
             * Redirect to mapping page
             *
             * @param {Event} event
             */
            redirectToMappingPage: function(event) {
                event.preventDefault();

                router.redirectToRoute('webkul_magento2_configuration_mapping_index');
            },
        });
    }
);
