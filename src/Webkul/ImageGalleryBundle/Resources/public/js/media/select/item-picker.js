'use strict';
define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'pim/common/item-picker',
        'webkulgallery/template/select/item-picker-basket',
        'pim/media-url-generator',
        'oro/datagrid-builder',
        'routing',
        'require-context'
    ],
    function (
        $,
        _,
        __,
        parentAssetPicker,
        basketTemplate,
        MediaUrlGenerator,
        datagridBuilder,
        Routing,
        requireContext

    ) {                         
        return parentAssetPicker.extend({
            basketTemplate: _.template(basketTemplate),
            /**
             * Renders the basket to update its content
             * @param items
             */
            renderBasket: function (items) {   
                             
                this.$('.basket').html(this.basketTemplate({ 
                    items: items,
                    title: __('pim_enrich.entity.product.module.basket.title'),
                    emptyLabel: __('pim_enrich.entity.product.module.basket.empty_basket'),
                    imagePathMethod: this.imagePathMethod.bind(this),
                    columnName: this.config.columnName,
                    identifierName: this.config.columnName,
                    labelMethod: this.labelMethod.bind(this),
                    itemCodeMethod: this.itemCodeMethod.bind(this),
                    MediaUrlGenerator: MediaUrlGenerator
                }));
            },

            /**
             * Render the item grid
             */
            renderGrid: function () {
                const urlParams = {
                    alias: this.datagrid.name,
                    params: {
                        dataLocale: this.getLocale(),
                        _filter: {
                            category: { value: { categoryId: -2 }}, // -2 = all categories
                            scope: { value: this.getScope() }
                        }
                    }
                };
                
                /* jshint nonew: false */
                // new CategoryFilter(
                //     urlParams,
                //     this.config.datagridName,
                //     this.config.categoryTreeRoute,
                //     '#item-picker-tree'
                // );
                
                $.get(Routing.generate('pim_datagrid_load', urlParams)).done(function (response) {
                    this.$('#grid-' + this.datagrid.name).data(
                        { 'metadata': response.metadata, 'data': JSON.parse(response.data) }
                    );

                    let resolvedModules = [];
                    response.metadata.requireJSModules.concat(['oro/datagrid/pagination-input'])
                        .forEach(function(module) {
                            resolvedModules.push(requireContext(module))
                        });

                    datagridBuilder(resolvedModules);
                }.bind(this));
            },

            
            addItem: function (code) {
                let items = [];
                items.push(code);
                items = _.uniq(items);

                this.setItems(items);

                return this;
            },
       

        });        
    }
);
