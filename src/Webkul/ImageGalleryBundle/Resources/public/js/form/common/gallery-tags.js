'use strict';

define([
    'underscore',
    'pim/form/common/edit-form',
    'webkulgallery/template/form/gallery-tags',
    'pim/fetcher-registry',
    'jquery.select2',
], function (
    _,
    BaseEditForm,
    fieldTemplate,
    fetcherRegistry
) {
    return BaseEditForm.extend({
        fieldTemplate: _.template(fieldTemplate),
        events: {
            'change .gallerytag': 'updateState',

        },
        empty: ['No data found'],
        data: ['Select Tags'],
        gallery: [],
        gallerytags: null,
        firstTime: null,

        /**
         * {@inheritdoc}
         */

        render: function () {
            var data = this.getFormData();
            
            var tag = 'undefined' !== typeof data['tag'] ?  data['tag'] : [];
            var gallerytags;
            if(this.gallerytags){
                gallerytags = this.gallerytags;
            }
            
            fetcherRegistry.getFetcher('gallery-tags').fetchAll()
            .then(function (gallerytags) {
                
                BaseEditForm.prototype.configure.apply(this, arguments);
                this.$el.html(this.fieldTemplate({
                    config: gallerytags,
                    events: this.delegateEvents(),
                    gallery : tag,
                }));

                var elem = this.$('#tags-select');
                elem.select2({
                    tags: gallerytags,
                    tokenSeparators: [',', ' ', ';'],
                    allowClear: true,
                    multiple: true,
                       
                });
                

                
                
            }.bind(this));
            
            

        },

        updateState: function(event) {
            
            var data = this.getFormData();
            this.gallery = [];
            this.gallery = 'undefined' !== typeof data['tag'] ?  data['tag'] : [];

            if(event.removed !== undefined){
                for (var key in this.gallery) {
                    if (this.gallery[key] == event.removed.text) {
                        this.gallery.splice(key, 1);
                    }
                }
            }
            
            $.each(event.val, function(key,val){
                
                var id = this.gallery.length + 1;
                var found = this.gallery.some(function (el) {
                   
                    return el === val;
                });
                if(!found) {
                    this.gallery.push(val);
                }
            }.bind(this))
            
            
            $.ajax({
                method: 'POST',
                url: Routing.generate('webkulgallery_tag_create'),
                contentType: 'application/json',
                data: JSON.stringify(this.gallery)
            })
            .then()
            .fail()
            if(data){
                data['tag'] = this.gallery;
            }
            this.setData(data);
            
            
            // this.render();
        },
    });
});
