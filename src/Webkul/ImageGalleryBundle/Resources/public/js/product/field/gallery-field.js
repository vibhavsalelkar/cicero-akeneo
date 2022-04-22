'use strict';

/**
 * Asset group
 *
 */
define(
    [
        'underscore',
        'pim/field',
        'webkulgallery/select/gallery-group'
    ], (
        _,
        Field,
        AssetSelectGroup
    ) => {
        return Field.extend({
            /**
             * {@inheritdoc}
             */
            initialize() {
                this.assetSelect = new AssetSelectGroup();                
                this.assetSelect.on('collection:change', function (assets) {
                    this.setCurrentValue(assets);
                }.bind(this));

                Field.prototype.initialize.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            setValues() {
                Field.prototype.setValues.apply(this, arguments);

                this.assetSelect.setData(this.getCurrentValue().data);
            },

            /**
             * {@inheritdoc}
             */
            renderInput(templateContext) {
                const entityType = templateContext.context.entity.meta.model_type;                
                const entityIdentifier = 'product_model' === entityType
                    ? templateContext.context.entity.code
                    : templateContext.context.entity.identifier;
                
                const context = _.extend(
                    {},
                    this.context,
                    {editMode: templateContext.editMode},
                    {attributeCode: templateContext.attribute.code},
                    {entityIdentifier: entityIdentifier},
                    {entityType: entityType}
                );
                
                this.assetSelect.setContext(context);

                return this.assetSelect.render().$el;
            },

            /**
             * {@inheritdoc}
             */
            setFocus() {
                this.el.scrollIntoView(false);
            }
        });
    }
);
