"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'akeneo-translator/template/configuration/tab/settings',
        'jquery',
        'routing',
        'pim/fetcher-registry',
        'pim/user-context',
        'oro/loading-mask',
        'pim/initselect2',
        'bootstrap.bootstrapswitch'
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        $,
        Routing,
        FetcherRegistry,
        UserContext,
        LoadingMask,
        initSelect2        
    ) {
        return BaseForm.extend({
            
            isGroup: true,
            label: __('translator.settings.tab'),
            template: _.template(template),
            code: 'translator_connector_settings',
            errors: [],
            events: {
                'change .woocommerce-settings select.label-field': 'updateModel',
                'click .select-all':  'selectAll',
                'click .remove-all': 'deselectAll',
                // 'click .woocommerce-settings .ak-view-all': 'showAllMappings',                
            },
            fields: null,
            attributes: null,
            fieldsUrl: 'webkul_woocommerce_connector_configuration_action',
            currencies: [],
            
            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:bad_request',
                    this.setValidationErrors.bind(this)
                );

                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:pre_save',
                    this.resetValidationErrors.bind(this)
                );

                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:post_fetch',
                    this.render.bind(this)
                );                

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
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();
                var attributes;
                //store attributes mapping values
                var data = this.getFormData();

                if(this.attributes) {
                    attributes = this.attributes;
                } else {
                    attributes = FetcherRegistry.getFetcher('attribute').search({options: {'page': 1, 'limit': 10000 } });
                }
               
                Promise.all([attributes]).then(function (values) {
                    $('#container .AknButtonList[data-drop-zone="buttons"] div:nth-of-type(1)').show();
                    
                    this.attributes = values[0];
                    this.$el.html(this.template({
                        model: this.getFormData(),
                        errors: this.errors,
                        attributes: this.attributes,
                        currentLocale: UserContext.get('uiLocale'),
                    }));

                    $('.woocommerce-settings .select2').each(function(key, select) {
                        if($(select).attr('readonly')) {
                            $(select).select2().select2('readonly', true);
                        } else {
                            $(select).select2();
                        }
                    });
                    self.$('.switch').bootstrapSwitch();
                    loadingMask.hide().$el.remove();
                    $('.AknFieldContainer *[data-toggle="tooltip"]').tooltip();
                }.bind(this));
                
                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

            indexes: [ 'defaults', 'settings', 'quicksettings', 'other_mappings'],           
            /**
             * Update model after value change
             *
             * @param {Event} event
             */            
            updateModel: function (event) {

                var index = {};
                var data = this.getFormData();

                if ($(event.target).hasClass('other_mappings')) {
                    index = 'other_mappings';
                } 
                
                data[index] = {};
                if($(event.target).val() === null){
                    data[index][$(event.target).attr('name')] = {};
                }else{
                    data[index][$(event.target).attr('name')] = $(event.target).val();
                }

                this.setData(data);                
            },



            deselectAll: function(e) {

                var target = $('#' + $(e.target).attr('data-for'));
                
                if(target) {
                    target.val([]);
                    target.trigger('change');
                }
            },
            selectAll: function(e) {
                var target = $('#' + $(e.target).attr('data-for'));
                
                if(target) {
                    var mappedFields = $('#mapped-fields select.attributeValue');
                    var mappedValues = [];
                    $.each(mappedFields, function(key, option) {
                        if(option.value) {
                            mappedValues.push(option.value);
                        }
                    });
                    var values = [];
                    $.each(target.find('option'), function(key, option) {
                        if(option.value && mappedValues.indexOf(option.value) === -1) {
                            values.push(option.value);
                        }
                    });

                    target.val(values);
                    target.trigger('change');
                }
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
