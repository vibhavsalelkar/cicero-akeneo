'use strict';

define([
    'underscore',
    'pim/form/common/edit-form',
    'webkulgallery/template/form/gallery-group',
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
            'change .gallerygroup': 'updateState',

        },
        empty: ['No data found'],
        data: ['Select Group'],
        gallery: [],
        gallerygroup: null,
        firstTime: null,

        /**
         * {@inheritdoc}
         */

        render: function () {
            var data = this.getFormData();
            
            var group = 'undefined' !== typeof data['galleryGroup'] ?  data['galleryGroup'] : [];
            var gallerygroup;
            if(this.gallerygroup){
                gallerygroup = this.gallerygroup;
            }
            
            fetcherRegistry.getFetcher('gallery-groups').fetchAll()
            .then(function (gallerygroups) {
                
                BaseEditForm.prototype.configure.apply(this, arguments);
                this.$el.html(this.fieldTemplate({
                    config: gallerygroups,
                    events: this.delegateEvents(),
                    gallery : group,
                }));       
        
            }.bind(this));
            
            

        },

        updateState: function(event) {
            
            var data = this.getFormData();      
            if(data){
                data['galleryGroup'] = $(event.target).val();
            }
            this.setData(data);
            
            
            // this.render();
        },
    });
});
