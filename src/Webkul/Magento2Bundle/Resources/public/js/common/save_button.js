'use strict';

define(
    [
        'pim/form',
        'underscore',
        'oro/translator',
        'magento2/template/common/savebutton',
        'routing',
        'magento2/template/common/storeMapping',
        'pim/fetcher-registry',
        'oro/loading-mask'
    ],
    function (
        BaseForm,
        _,
        __,
        template,
        Routing,
        storeViewTemplate,
        FetcherRegistry,
        LoadingMask       
    ) {
        return BaseForm.extend({
            template: _.template(template),
            storeviewtemplate: _.template(storeViewTemplate),
            config: null,
            storeView: null,
            isGroup: true,
            label: __('magento2.store_mapping'),
            code: 'magento2_connector_store_mapping',
            events: { "click .action" :  "fetch" ,
                      'change select': 'updateModel',
            },
            currencies: null,
            locales: null,
            button: "Fetch Store View Mapping",
            storeViews: null,
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

            initialize: function (config) {
                this.config = config.config;
                BaseForm.prototype.initialize.apply(this, arguments);
            },

            
            /**
             * {@inheritdoc}
             */
            render: function () {
                
                if(!(
                this.getFormData()['configuration']['hostName'] &&
                this.getFormData()['configuration']['authToken']
                ) && this.getFormData()['configuration']['storeMapping']
            ){
                    delete this.getFormData()['configuration']['storeMapping'];
                    
            }

            
                if(this.getFormData()['configuration']['storeMapping'] && this.getFormData()['configuration']['storeViews']){
                    this.storeviewrender();
                }else{
                    this.$el.html(this.template({
                        value: this.button,
                        __: __,
                        error: ''

                    }));
                }
            

                this.delegateEvents();

                return this;
            },

            fetch: function(){ 
                var data = JSON.stringify(this.getFormData());
                self = this;
                $.ajax({
                        method: 'POST',
                        url: Routing.generate('webkul_magento2_connector_configuration_getstoreview'),
                        contentType: 'application/json',
                        data: data,
                        success: function(fetch){
                            self.storeView = fetch;
                            self.updateStoreView(JSON.stringify(fetch));
                            self.storeviewrender();
                        },
                        error: function(xhr, textStatus, errorThrown) { 
                            self.$el.html(self.template({
                                value: self.button,
                                __: __  ,
                                error: xhr.responseText
                            }));
                            self.delegateEvents();

                            return self;
                        }  
                    });
                    
            },
            
            /**
             * {@inheritdoc}
             */
            storeviewrender: function () {
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();

                var currencies;
                if(this.currencies) {
                    currencies = this.currencies;
                } else {
                    currencies = FetcherRegistry.getFetcher('currency').search({options: {'page': 1, 'limit': 10000, 'activated': 1 } });
                }
                var locales;
                if(this.locales) {
                    locales = this.locales;
                } else {
                    locales = FetcherRegistry.getFetcher('locale').fetchActivated()
                    
                }

                var self = this; 
                Promise.all([currencies, locales]).then(function(values) {
                    self.currencies = values[0];
                    self.locales = values[1];
                    $('#container .AknButton--apply.save').show();
                    
                    var storeViews='';
                    
                    if(self.storeView){
                        storeViews = self.storeView; 
                    }else{
                        storeViews = self.getFormData()['configuration']['storeViews']
                    }
                    try {
                        if(typeof(storeViews) !== 'object') {
                            var storeViews = JSON.parse(storeViews);
                        }
                    } catch(e) {
                        var storeViews = {};
                    }
                    self.$el.html(self.template({
                        value: self.button,
                        __: __,
                        error: ''

                    }));
                    self.$el.append(self.storeviewtemplate({
                        locales: self.locales,
                        storeViews: storeViews,
                        storeMapping: self.getFormData()['configuration']['storeMapping'] ? self.getFormData()['configuration']['storeMapping'] : [],
                        currencies: self.currencies,
                        error: self.errors,
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
                if(!data['configuration']['storeMapping'])
                    data['configuration']['storeMapping'] = {};

                if($(event.target).closest('.storeViewData')) {
                    var storeView = $(event.target).closest('.storeViewData').attr('data-storeView');
                    if(typeof(data['configuration']['storeMapping'][storeView]) == 'undefined') {
                        data['configuration']['storeMapping'][storeView] = {};
                    }
                    data['configuration']['storeMapping'][storeView][$(event.target).attr('name')] = event.target.value;
                }

                this.setData(data);
            },

            updateStoreView: function(storeViews) {
                var data = this.getFormData();
                if(!data['configuration']['storeViews'])
                    data['configuration']['storeViews'] = {};

                if(storeViews) {
                    
                    if(typeof(data['configuration']['storeViews']) == 'undefined') {
                        data['configuration']['storeViews'] = {};
                    }
                    data['configuration']['storeViews'] = storeViews;
                }

                this.setData(data);
            }

            
        });
    }
);
