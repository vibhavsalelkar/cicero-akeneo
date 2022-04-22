define([
    'jquery', 
    'oro/datagrid/product-row', 
    'pim/media-url-generator',
    'webkulgallery/template/datagrid/row/asset-thumbnail'
], 
function(
    $, 
    ProductRow, 
    MediaUrlGenerator,
    thumbnailTemplate
) {
    return ProductRow.extend({
        thumbnailTemplate: _.template(thumbnailTemplate),

        /**
         * {@inheritdoc}
         */
        getTemplateOptions() {
            const label = this.model.get('code');
            const thumbnail = this.model.get('thumbnail');
            const imagePath = MediaUrlGenerator.getMediaShowUrl(thumbnail, 'thumbnail');
          
            return {
                useLayerStyle: false,
                identifier: '',
                label,
                imagePath
            };
        },

        /**
         * {@inheritdoc}
         */
        getRenderableColumns() {
            return ['massAction', 'rowActions'];
        }
    });
});
