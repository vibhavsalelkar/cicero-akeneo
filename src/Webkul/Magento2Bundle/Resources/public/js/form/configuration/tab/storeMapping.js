"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/storeMapping',
        'pim/fetcher-registry',
        'oro/loading-mask',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        FetcherRegistry,
        LoadingMask,              
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.store_mapping'),
            template: _.template(template),
            code: 'magento2_connector_store_mapping',
            events: {
                'change select': 'updateModel',
                'change input': 'updateData',
                'change .default': 'setDefaultLocale',
                'click .default': 'setDefaultLocale',
                'click .MagentoStoreMapping-remove': 'closeHint',
            },
            controls: [{
                'label' : 'magento2.form.properties.host_name.title',
                'name': 'hostName',
                'type': 'text'
            }, {
                'label' : 'magento2.form.properties.auth_token.title',
                'name': 'authToken',
                'type': 'password'
            }],
            currencies: null,
            locales: null,
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
            render: function () {
                $('.magento-save-config').show();

                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();

                var currencies, locales, channels;
                if(this.currencies) {
                    currencies = this.currencies;
                    locales = this.locales;
                    channels = this.channels;
                } else {
                    currencies = FetcherRegistry.getFetcher('currency').search({options: {'page': 1, 'limit': 10000, 'activated': 1 } });
                    locales = FetcherRegistry.getFetcher('locale').fetchActivated();
                    channels = FetcherRegistry.getFetcher('channel').search({options: {'page':1, 'limit': 10000, 'activated':1}});
                }
                var self = this; 
                Promise.all([currencies, locales, channels]).then(function(values) {
                    self.currencies = values[0];
                    self.locales = values[1];
                    self.channels = values[2];
                    //console.log(self.locales); 
                    var data = self.getFormData();
                    $('#container .AknButton--apply.save').show();
                    var storeViews = data['storeViews'];
                    try {
                        if(typeof(storeViews) !== 'object') {
                            var storeViews = JSON.parse(storeViews);
                        }
                    } catch(e) {
                        var storeViews = {};
                    }
                    var defaultLocale = typeof(data['defaultLocale']) !== 'undefined' ? data['defaultLocale'] : 'en_us' ;

                    self.$el.html(self.template({
                        model: data,
                        controls: self.controls,
                        locales: self.locales,
                        channels: self.channels,
                        storeViews: _.sortBy(storeViews, 'website_id'),
                        storeMapping: typeof(data['storeMapping']) !== 'undefined' && data['storeMapping'] ? data['storeMapping'] : {},
                        defaultLocale: defaultLocale,
                        currencies: self.currencies,
                        check: false,
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
            updateData: function (event) {
                var data = this.getFormData();
                if($(event.target).attr('name')) {
                    data[$(event.target).attr('name')] = $(event.target).val();                    
                }
                this.setData(data);
            },
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                if ('undefined' === typeof(data['storeMapping']) 
                    || 'object' !== typeof(data['storeMapping']) 
                    || ! data['storeMapping'] instanceof Array )  {

                    data['storeMapping'] = {"allStoreView": {}};  
                }

                var storeViewWebsiteId = $(event.target).closest('.storeViewData').attr('storeViewWebsiteId')
                var storeViewCode = $(event.target).closest('.storeViewData').data('storeview');             
                var storeId = $(event.target).closest('.storeViewData').attr('storeviewsid');

                if ('undefined' === typeof(data['storeMapping'][storeViewCode]) 
                    || 'object' !== typeof(data['storeMapping'][storeViewCode]) 
                    || ! data['storeMapping'][storeViewCode] instanceof Array ) {

                    data['storeMapping'][storeViewCode] =  {
                        "id": "",
                        "website_id": "",
                        "channel": "",
                        "locale": "",
                        "currency": "",                
                    };
                }

                if('allStoreView' === storeViewCode) {
                    storeId = 0;
                    storeViewWebsiteId = 0;
                }

                data['storeMapping'][storeViewCode]['id'] = storeId;
                data['storeMapping'][storeViewCode]['website_id'] = storeViewWebsiteId;

                data['storeMapping'][storeViewCode][$(event.target).attr('name')] = event.target.value;
                // data['storeMapping'] = _.sortBy(data['storeMapping'], 'website_id');
                //console.log(data);
                this.setData(data);
                this.render();
            },
            /**
             * Set the default locale value after change
             * 
             * @param {Event} event
             */
            setDefaultLocale: function (event) {
                // var data = this.getFormData();
                // if(!data['defaultLocale']) {
                //     data['defaultLocale'] = {};
                // }
                // data['defaultLocale'] = event.target.value;
                
                // data['defaultChannel'] = $(event.target).closest('.selectChannelData').attr('channelCode');
                // data['defaultChannelStoreId'] = $(event.target).closest('.storeViewData').attr('storeViewWebsiteId');
                // this.setData(data);
                // this.render();
            },

            closeHint: function (event) {
                this.hidden = true;
                this.render();
            },
            data: null,
        });
    }
);
