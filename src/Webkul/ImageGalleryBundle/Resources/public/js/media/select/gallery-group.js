'use strict';

define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'backbone',        
        'webkulgallery/template/select/asset-group',      
        'pim/fetcher-registry',
        'pim/form-builder',
        'pim/security-context',
        'pim/media-url-generator',
        'bootstrap-modal',
        'pim/common/property',
        'pim/form',
        'jquery.slimbox'
    ], (
        $,
        _,
        __,
        Backbone,
        template,
        FetcherRegistry,
        FormBuilder,
        SecurityContext,
        MediaUrlGenerator,
        propertyAccessor,
        BaseForm
    ) => {
        return Backbone.View.extend({
            className: 'AknAssetGroupField',
            data: [],
            context: {},
            template: _.template(template),
            events: {
                'click .add-asset': 'updateAssets',
                'click .upload-assets': 'uploadAssets',
                'click  .open-image': 'previewImageGallery',                
                'click  .open-image1': 'previewImageGallery1',
                'click  .open-imageSlider': 'previewImage',
                'click  .asset-thumbnail': 'assetThumbnail'
            },

            /**
             * {@inheritdoc}
             *
             * In the case where asset codes are integers, even if their order iscorrectly managed by the backend, the
             * fetcher will reorganize them, sorting them by code ascending. As "this.data" contains the codes in the
             * correct order, we reorder the assets according to this list of code.
             */
            render() {
            
                if(!this.data) {
                    this.data = [];
                }
                                   
                FetcherRegistry.getFetcher('gallery').fetchByIdentifiers(this.data).then(assets => {                                    
                    let orderedAssets = [];
                    this.data.forEach(assetCode => {
                        orderedAssets = orderedAssets.concat(assets.filter(asset => asset.code === assetCode));
                    });
                
                    this.$el.html(this.template({
                        assets: orderedAssets,
                        locale: this.context.locale,
                        scope: this.context.scope,
                        thumbnailFilter: 'thumbnail',
                        editMode: this.context.editMode,
                        MediaUrlGenerator: MediaUrlGenerator
                    }));
                
                    if ('view' !== this.context.editMode) {                       
                        // this.$('.AknAssetCollectionField-list').sortable({
                        //     update: this.updateDataFromDom.bind(this)
                        // });
                    }
                    this.delegateEvents();
                });

                return this;
            },

            
            previewImageGallery1() {                                        
                // $("button").click(function(){
                    // $.get("webkul_gallery_media_rest_get", function(data, status){
                    //   alert("Data: " + data + "\nStatus: " + status);
                    // });
                //   });  
                
                $.ajax({
                    url: Routing.generate('webkul_gallery_media_rest_get'),
                    type: 'GET',
                    dataType: 'json', // added data type
                    success: function(data) {
                      
                        // alert(data);
                    }                    
                  });
            },

            /**
             *
             */
            updateDataFromDom() {
                const assets = this.$('.AknAssetCollectionField-listItem')
                    .map((index, listItem) => listItem.dataset.asset)
                    .get();
                
                this.data = assets;
                this.trigger('collection:change', assets);
            },

            /**
             * Set data into the view
             *
             * @param {Array} data
             */
            setData(data) {                                                       
                this.data = data;
            },

            /**
             * Set context into the view
             *
             * @param {Object} context
             */
            setContext(context) {                     
                this.context = context;
            },

            /**
             * Launch the asset picker and set the assets after update
             */
            updateAssets() {                        
                
                this.manageAssets().then(assets => {                                                  
                    this.data = assets;
                    this.trigger('collection:change', assets);
                    this.render();
                });
                
            },

            /**
             * Launch the Image Gallery with Slider
             */
            previewImageGallery() {                        
                
                this.manageImageGallery().then(assets => {                                                  
                    this.data = assets;
                    this.trigger('collection:change', assets);
                    this.render();
                });
                
            },

            /**
             * Open the modal to mass upload assets.
             */
            uploadAssets() {                
                FormBuilder.build('pimee-asset-mass-upload').then(form => {
                    const routes = {
                        cancelRedirectionRoute: '',
                        importRoute: 'webkulgallery_mass_upload_into_asset_collection_rest_import'
                    };

                    const entity = {
                        attributeCode: this.context.attributeCode,
                        identifier: this.context.entityIdentifier,
                        type: this.context.entityType
                    };

                    form.setRoutes(routes)
                        .setEntity(entity)
                        .setElement(this.$('.asset-mass-uploader'))
                        .render();
                });
            },

            // /**
            //  * Set current model value for this field
            //  *
            //  * @param {*} value
            //  */
            // setCurrentData: function (value) {
            //     const dataModel = this.getFormData();

            //     if(value) {
            //         var imagesValue = {};
            //         if(typeof(dataModel.medias) === 'undefined') {
            //             dataModel.medias = []
            //         }
                    
            //         if(typeof(dataModel.medias.data) === 'undefined') {
            //             dataModel.medias.data = dataModel.medias;
            //         }
            //         imagesValue = dataModel.medias;

            //         if(Array.isArray(imagesValue.data) === false) {
            //             imagesValue.data = [imagesValue.data];
            //         }
                    
            //         imagesValue.data.push(value);
            //     }

            //     propertyAccessor.updateProperty(dataModel, this.fieldName, imagesValue);
                
            //     this.setData(dataModel);
            // },

            // clearCurrentData: function(index) {
            //     const dataModel = this.getFormData();

            //     if(typeof(dataModel.medias.data) === 'undefined') {
            //         dataModel.medias.data = dataModel.medias;
            //     }

            //     var imagesValue = dataModel.medias

            //     if (typeof(imagesValue.data) !== 'undefined') {
            //         imagesValue.data.splice(index, 1);
            //     }

            //     propertyAccessor.updateProperty(dataModel, this.fieldName, imagesValue);

            //     this.setData(dataModel);
            // },

            
            getCurrentValue: function () {
                // return;                
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
            assetThumbnail: function(e) {                
                // var index = $(e.currentTarget).closest("div.AknAssetCollectionField-assetThumbnail.asset-thumbnail").val();
                var startingImage = 0;
                var parser  = document.createElement('a');
                var word    = "thumbnail";
                var newWord = "preview";                                
                // var src = $(this).attr('style');
                var src =  $(e.currentTarget).css('background-image').replace('url', '');                
                var src2 = src.slice(2,-2);
                parser.href = src2;
                var path = parser.pathname.substring(1); 
                var mediaUrl = [];                          
                var gotUrl = path.replace(new RegExp(word+'$'), newWord);  
                mediaUrl.push(gotUrl);
                
                $(".openModal").each(function(){
                    var src =  $(this).css('background-image').replace('url', '');
                    var src2 = src.slice(2,-2);
                    parser.href = src2;
                    var path = parser.pathname.substring(1); 
                                           
                    var newUrl = path.replace(new RegExp(word+'$'), newWord); 
                    var checkUrlPushed = mediaUrl.includes(newUrl);
                    if (!checkUrlPushed) {
                        mediaUrl.push(newUrl);
                    }
                    
                });
                if (mediaUrl) { 
                    var medias = [];
                    _.each(mediaUrl, function(media, index) {
                        medias.push([media]);
                    });             
                    jQuery.slimbox(medias, startingImage, {loop: true});                             
                }                       
            },

            previewImage: function (e) {                
                var index = $(e.target).closest("div.AknAssetCollectionField-header").find("input[name='asset_indexes']").val();
                var mediaUrl = '/media/show/2%252Fe%252Fe%252F5%252F2ee501eb834237e1b7f23fa64de0c0d879f855df_jaishreekrishna.jpg/preview';                
                if (mediaUrl) {
                    $.slimbox(mediaUrl, '', {overlayOpacity: 0.3});
                }
            },

            /**
             * Launch the asset picker
             *
             * @return {Promise}
             */
            manageAssets() {                     
                const deferred = $.Deferred();                
                
                FormBuilder.build('webkulgallery-product-media-select-form').then(form => {                                        
                    let modal = new Backbone.BootstrapModal({
                        className: 'modal modal--fullPage AknGalleryAssets modal--topButton',
                        modalOptions: {
                            backdrop: 'static',
                            keyboard: false
                        },
                        allowCancel: true,
                        okCloses: false,
                        title: '',
                        content: '',
                        cancelText: 'Cancel',
                        okText: __('confirmation.title')
                    });
                    
                    modal.open();            
                    
                    form.setImagePathMethod(function (item) {                    
                        
                        if(item && typeof(item.filePath) !== 'undefined' && item.filePath) {
                            return MediaUrlGenerator.getMediaShowUrl(item.filePath, 'thumbnail');
                        } else {
                            return null;
                        }
                    });

                    form.setLabelMethod(item => item.description);

                    form.setElement(modal.$('.modal-body'))
                        .render()
                        .setItems(this.data);

                    modal.on('cancel', deferred.reject);

                    modal.on('ok', () => {

                        const assets = _.sortBy(form.getItems(), 'code');                                                
                        modal.close();                                        
                        deferred.resolve(assets);
                    });
                });                

                return deferred.promise();
            },


            /**
             * Launch the Image Gallery Slider
             *
             * @return {Promise}
             */
            manageImageGallery() {                     
                const deferred = $.Deferred();
                FormBuilder.build('webkulgallery-product-media-image-slider-form').then(form => {                                        
                    let modal = new Backbone.BootstrapModal({
                        className: 'modal modal--fullPage AknGalleryAssets modal--topButton',
                        modalOptions: {
                            backdrop: 'static',
                            keyboard: false
                        },
                        title: 'This form is for image',
                        content: '',
                    });
                    modal.open();
                    return;                    
                });                

                return deferred.promise();
            },

        });
    }
);
