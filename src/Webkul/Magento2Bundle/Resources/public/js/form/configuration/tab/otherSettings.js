"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'magento2/template/configuration/tab/otherSettings',
        'oro/loading-mask',
        'pim/fetcher-registry',
        'pim/user-context',
        'pim/initselect2',
        'bootstrap.bootstrapswitch'
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        LoadingMask,
        FetcherRegistry,
        UserContext,
        initSelect2
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('magento2.otherSettings.tab'),
            template: _.template(template),
            code: 'magento2_connector_otherSettings',
            errors: [],
            events: {
                'change .AknFormContainer-otherSettings input': 'updateModel',
                'change .AknFormContainer-otherSettings select': 'updateModel',
                'click .AknFormContainer-otherSettings input': 'updateModel',
            },

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

                this.trigger('tab:register', {
                    code: this.code,
                    label: this.label
                });

                return BaseForm.prototype.configure.apply(this, arguments);
            },
            attributes: null,

            /**
             * {@inheritdoc}
             */
            render: function () {
                $('.magento-save-config').show();
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();

                var attributes;
                if(this.attributes) {
                    attributes = this.attributes;
                } else {
                    attributes = FetcherRegistry.getFetcher('attribute').search({options: {'page': 1, 'limit': 10000 } });
                }
                var self = this;

                Promise.all([attributes]).then(function(values) {
                    self.attributes = values[0];
                    var otherSettings = typeof(self.getFormData()['otherSettings']) !== 'undefined' ? self.getFormData()['otherSettings'] : {};


                    self.$el.html(self.template({
                        settings: otherSettings,
                        attributes: self.attributes,
                        currentLocale: UserContext.get('uiLocale'),
                        supportMagento2ProductAttachment: self.getFormData()['support_magento2_productAttachment'],
                        supportMagento2AmastyProductAttachment: self.getFormData()['support_amasty_productAttachment'],
                        supportAmastyProductParts: self.getFormData()['support_amasty_product_parts']
                    }));

                    self.$('*[data-toggle="tooltip"]').tooltip();
                    self.$('.switch').bootstrapSwitch();
                    self.$('.select2').select2();
                    
                    loadingMask.hide().$el.remove();                    
                });

                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            postRender: function () {
                this.$('.switch').bootstrapSwitch();
            },
            
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                
                var index = 'otherSettings';

                if($(event.target).hasClass('select2') && ($(event.target).hasClass('select2-container-multi') || $(event.target).attr('name') == 'magento2_attachment_fields')) {
                    val = $(event.target).select2('data')
                    val = val.map(function(obj) { return obj.id });                    
                } else if( $(event.target).is('input[type="checkbox"]')) {
                    var val = $(event.target).is(':checked');
                    if(val == true) {
                        val = "true";
                    } else{
                        val = "false";
                    }    
                } else {
                    val = $(event.target).val();
                }

                if(typeof(data[index]) === 'undefined' || !data[index] || typeof(data[index]) !== 'object' || data[index] instanceof Array) {
                    data[index] = {};
                }
                data[index][$(event.target).attr('name')] = val;

                if($(event.target).attr('name') === 'metric_is_active') {
                    data[index]['metric_selection'] = "true";
                }

                this.setData(data);
                this.dependentFieldAction(event);
                
                if($(event.target).attr('name') === 'metric_is_active') {
                    this.render();
                }
            },

            dependentFieldAction: function(event) {
                if(event.target.name == 'enable_attachment_export') {
                    var data = this.getFormData();
                    if(typeof(data.otherSettings) !== 'undefined' && typeof(data.otherSettings.enable_attachment_export) !== 'undefined' && ["false", false].indexOf(data.otherSettings.enable_attachment_export) === -1 ) {
                        $('.magento2_attachment_fields_wrapper').show();
                    } else {
                        $('.magento2_attachment_fields_wrapper').hide();
                        $('#pim_enrich_entity_form_attachment_fields').select2("val", "");
                        if(typeof(data.otherSettings) !== 'undefined' && typeof(data.otherSettings.enable_attachment_export) !== 'undefined') {                        
                            delete(data.otherSettings.magento2_attachment_fields);
                        }
                    }
                } else if(event.target.name == 'enable_amasty_attachment_export') {
                    var data = this.getFormData();
                    if(typeof(data.otherSettings) !== 'undefined' && typeof(data.otherSettings.enable_amasty_attachment_export) !== 'undefined' && ["false", false].indexOf(data.otherSettings.enable_amasty_attachment_export) === -1 ) {
                        $('.magento2_amasty_attachment_fields_wrapper').show();
                    } else {
                        $('.magento2_amasty_attachment_fields_wrapper').hide();
                        $('#pim_enrich_entity_form_amasty_attachment_fields').select2("val", "");
                        if(typeof(data.otherSettings) !== 'undefined' && typeof(data.otherSettings.enable_amasty_attachment_export) !== 'undefined') {
                            delete(data.otherSettings.magento2_attachment_fields);
                        }
                    }
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