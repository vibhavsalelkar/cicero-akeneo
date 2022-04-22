"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/form/credentials/edit/channelWebsiteMapping',
        'pim/fetcher-registry',
        'oro/loading-mask',
        'routing',
    ],
    function(
        _,
        __, 
        BaseForm,
        template,
        FetcherRegistry,
        LoadingMask, 
        Routing             
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.store_mapping'),
            template: _.template(template),
            code: 'magento2_connector_store_mapping',
            events: {
                'change select': 'updateModel',
                'change .AknFormContainer-Credential input': 'updateModel',
                'change .AknFieldContainer-inputContainer.field-select': 'updateModel',
                'change .default': 'setDefaultLocale',
                'click .default': 'setDefaultLocale',
                'click .MagentoStoreMapping-remove': 'closeHint', 
            }, 

            channels: null,
            hidden: false,
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
            render: function (route) {
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();
                var controls;
                var channels;
                var id = window.location.href.split("edit/");
                var id = id[1].split("/")[0];
                
                if(this.channels) {
                    channels = this.channels;
                } else {
                    channels = FetcherRegistry.getFetcher('channel').search({options: {'page': 1, 'limit': 10000, 'activated': 1 } });
                }
                
                var self = this; 

                Promise.all([channels]).then(function(values) {
                    self.channels = values[0];
                    var data = self.getFormData();
                    $('#container .AknButton--apply.save').show();
                    
                    try {
                        if(typeof(data['storeViews'] !== 'undefined')) {
                            if(data['storeViews']!== 'undefined') {
                                var storeViews = data['storeViews'];
                            }
                            var storeMapping = typeof(data['storeMapping']) !== 'undefined' ? data['storeMapping'] : [];
                            
                        }
                    } catch(e) {
                        var storeViews = {};
                        var storeMapping = {};
                    } 
                    storeViews = _.sortBy(_.sortBy(storeViews, 'store_group_id'), 'website_id');
                    
                    self.$el.html(self.template({
                        model: self.getFormData(),
                        storeViews: _.groupBy(storeViews, function(storeView) { return storeView.store_group_id }),
                        storeMapping: storeMapping,
                        channels: self.channels,
                        hintIsHidden: self.hidden,
                    }));
                    loadingMask.hide().$el.remove();
                });

                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                // defaultLocale not found  
                if(typeof(data['defaultLocale']) === 'undefined') {
                    if(typeof(data['storeMapping'])!== 'undefined')  {
                        var storeMapping = data['storeMapping'];
                        if(typeof(storeMapping[Object.keys(storeMapping)[0]]) != 'undefined' && typeof(storeMapping[Object.keys(storeMapping)[0]['locale']]) != 'undefined') {
                            var locale = storeMapping[Object.keys(storeMapping)[0]]['locale']
                            if(locale) {
                                data['defaultLocale'] = locale;
                            } 
                        } else {
                            data['defaultLocale'] = 'en_us'; 
                        }
                    }
                }

                if($(event.target).closest('.storeViewData')) {
                    var storeViews = $(event.target).closest('.storeViewData').attr('data-storeViews');
                    if( typeof storeViews == "string" ) {
                        storeViews = storeViews.split(",");
                        _.each(storeViews, function(storeView) { 
                            //init the storemapping 
                            if(typeof( data['storeMapping']) == 'undefined' || Object.keys(data['storeMapping']).length === 0) {
                                data['storeMapping'] = {};
                            } 
                            // init store view 
                            if(typeof( data['storeMapping'][storeView]) == 'undefined') {
                                data['storeMapping'][storeView] = {};
                            } 
                            data['storeMapping'][storeView][$(event.target).attr('name')] = event.target.value;
                            if(typeof( data['storeMapping'][storeView]['id']) == 'undefined') {
                                data['storeMapping'][storeView]['id'] = $(event.target).closest('.storeViewData').attr('storeViewsId');
                            }
                            if(typeof( data['storeMapping'][storeView]['website_id']) == 'undefined') {
                                data['storeMapping'][storeView]['website_id'] = $(event.target).closest('.storeViewData').attr('websiteId');
                            }
                        });
                    }
                }
                
                if($(event.target).closest('.storeViewData').find('[type="radio"]')) {
                    var checkbox = $(event.target).closest('.storeViewData').find('[type="radio"]');
                    checkbox.val(event.target.value);
                    if($(checkbox).is(':checked')) {
                        data['defaultLocale'] = event.target.value;
                    }
                }
 
                this.setData(data);
            },
            /**
             * Set the default locale value after change
             * 
             * @param {Event} event
             */
            setDefaultLocale: function (event) {
                var resources = {};
                var data = this.getFormData();
                
                data['defaultLocale'] = event.target.value;
                this.setData(data);
            },

            closeHint: function (event) {
                this.hidden = true;
                this.render();
            }
        });
    }
);