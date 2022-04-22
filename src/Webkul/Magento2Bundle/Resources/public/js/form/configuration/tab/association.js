"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/association',
        'oro/loading-mask',
        'pim/fetcher-registry',
        'pim/user-context',        
        'pim/initselect2'
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        LoadingMask,
        FetcherRegistry,
        UserContext,     
        initSelect2,    
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.association.tab'),
            template: _.template(template),
            code: 'magento2_connector_association',
            errors: [],
            events: {
                'change .AknFormContainer-associationMappings select': 'updateModel',
            },

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
            associations: null,
            /**
             * {@inheritdoc}
             */
            render: function () {
                $('.magento-save-config').show();
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();
                var self = this;
                
                var fields = [
                    {
                        "code" : 'related',
                        "label" : 'association.related'
                    },
                    {
                        "code" : 'crosssell',
                        "label" : 'association.crosssell'
                    },
                    {
                        "code" : 'upsell',
                        "label" : 'association.upsell'
                    },
                ];
                var associations;    
                if(self.associations) {
                    associations = self.associations;
                } else {
                    associations = FetcherRegistry.getFetcher('association-type').search({options: {'page': 1, 'limit': 10000 } });
                }
                
                Promise.all([associations, fields]).then(function(values) {
                    self.associations = values[0];
                    self.fields = values[1]
                    self.$el.html(self.template({
                        associations: self.associations,
                        fields: self.fields,
                        model: self.getFormData(),
                        currentLocale: UserContext.get('uiLocale'), 
                    }));
                    $('.select2').select2();
                    loadingMask.hide().$el.remove();                    
                });
                self.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            
            updateModel: function (event) {
                var data = this.getFormData();
                var val;
                if(typeof(data['association']) === 'undefined') {
                    data['association'] = {};
                }
                if($(event.target).hasClass('select2')) {
                    val = $(event.target).select2('data')
                    val = val.id;
                }
                data['association'][$(event.target).attr('name')] = val;
                this.setData(data);
            },

            /**
             * Sets errors
             *
             * @param {Object} errors
             */
            setValidationErrors: function (errors) {
                this.errors = errors.response;
                this.render();
            },

            /**
             * Resets errors
             */
            resetValidationErrors: function () {
                this.errors = {};
                this.render();
            }
        });
    }
);