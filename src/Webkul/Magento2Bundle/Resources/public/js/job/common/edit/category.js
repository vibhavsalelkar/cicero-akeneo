'use strict';

define([
    'jquery',
    'underscore',
    'oro/translator',
    'backbone',
    'pim/filter/filter',
    'routing',
    'magento2/job/common/edit/category-selector',
    'pim/fetcher-registry',
    'pim/template/filter/product/category',
    'jquery.select2'
], function ($, _, __, Backbone, BaseFilter, Routing, CategoryTree, fetcherRegistry, template) {
    var TreeModal = Backbone.BootstrapModal.extend({});

    return BaseFilter.extend({
        shortname: 'category',
        categoryIds: [],
        categoryData: [],
        channelCategory: [],
        template: _.template(template),
        className: 'AknFieldContainer control-group filter-item category-filter',
        events: {
            'click button': 'openSelector'
        },

        /**
         * {@inherit}
         */
        configure: function () {
            this.listenTo(this.getRoot(), 'channel:update:after', this.channelUpdated.bind(this));
            // this.listenTo(this, 'channel:update:later', this.channelUpdated.bind(this));
            this.listenTo(this.getRoot(), 'pim_enrich:form:entity:pre_update', function (data) {
                _.defaults(data, {field: this.getCode(), operator: 'IN CHILDREN', value: []});
            }.bind(this));

            return BaseFilter.prototype.configure.apply(this, arguments);
        },

        /**
         * Returns rendered input.
         *
         * @return {String}
         */
        renderInput: function () {
            if('IN CHILDREN' !== this.getOperator()) {
                    fetcherRegistry.getFetcher('custom-category').search(this.getValue()).then((res) => {                       
                        this.categoryIds = res.ids;
                        this.categoryData = res.categoryData;
                })
            }
            this.channelCategory = [];
            this.getCurrentChannel().then( (channels) => {
                _.each(channels, (channel) => {
                    var index = $.inArray( channel.code, this.getParentForm().getFilters().structure.scope);
                    if(index != -1) {
                        this.channelCategory.push(channel.category_tree);
                    }
                })
                
            })

            var categoryCount = 'IN CHILDREN' === this.getOperator() ? 0 : this.getValue().length;        

            return this.template({
                isEditable: this.isEditable(),
                titleEdit: __('pim_connector.export.categories.selector.title'),
                labelEdit: __('pim_common.edit'),
                labelInfo: __(
                    'pim_connector.export.categories.selector.label',
                    {count: categoryCount},
                    categoryCount
                ),
                value: this.getValue()
            });
        },

        /**
         * Resets selection after channel has been modified then re-renders the view.
         */
        channelUpdated: function () {            
            this.channelCategory = [];
            this.getCurrentChannel().then( (channels) => {
                _.each(channels, (channel) => {
                    
                    var index = $.inArray( channel.code, this.getParentForm().getFilters().structure.scope);
                    if(index != -1) {
                        this.channelCategory.push(channel.category_tree);
                    }
                })
                
            })
                
            this.getCurrentChannel().then(function (channel) {
                this.setDefaultValues(channel);
                this.render();
            }.bind(this));
        },

        /**
         * {@inherit}
         */
        getTemplateContext: function () {
            return $.when(
                BaseFilter.prototype.getTemplateContext.apply(this, arguments),
                this.getCurrentChannel()
            ).then(function (templateContext, channel) {
                if ('IN CHILDREN' === this.getOperator()) {
                    this.setDefaultValues(channel);
                }

                return templateContext;
            }.bind(this));
        },

        /**
         * Open the selector popin
         */
        openSelector: function () {            
            var modal = new TreeModal({
                title: __('pim_connector.export.categories.selector.modal.title'),
                cancelText: __('pim_common.cancel'),
                okText: __('pim_common.confirm'),
                content: '',
                illustrationClass: 'categories',
            });

            modal.render();

            var tree = new CategoryTree({
                el: modal.$el.find('.modal-body'),
                attributes: {
                    channel: this.getParentForm().getFilters().structure.scope,
                    categories: this.categoryIds,
                    categoryData: this.categoryData,
                    channelCategory: this.channelCategory
                }
            });

            tree.render();
            modal.open();

            modal.on('cancel', function () {
                modal.remove();
                tree.remove();
            });

            modal.on('ok', function () {
                if (_.isEmpty(tree.attributes.categories)) {                        
                    this.getCurrentChannel().then(function (channel) {
                        this.setDefaultValues(channel);
                    }.bind(this));
                } else {
                    this.setData({
                        field: this.getField(),
                        operator: 'IN',
                        value: tree.attributes.categories
                    });
                }

                modal.close();
                modal.remove();
                tree.remove();
                this.render();
            }.bind(this));

        },

        /**
         * {@inheritdoc}
         */
        isEmpty: function () {
            return false;
        },

        /**
         * Get the current selected channel
         *
         * @return {Promise}
         */
        getCurrentChannel: function () {
            
            return fetcherRegistry.getFetcher('channel').fetchAll();
        },

        /**
         * Set the default values for the filter
         *
         * @param {object} channel
         */
        setDefaultValues: function (channel) {
            if (this.getOperator() === 'IN CHILDREN' && _.isEqual(this.getValue(), [channel.category_tree])) {
                return;
            }   
            var value = [];        
            channel.forEach(element => {                
                value.push(element.category_tree);
            });
            this.setData({
                field: this.getField(),
                operator: 'IN CHILDREN',
                value: value
            });
        }
    });
});