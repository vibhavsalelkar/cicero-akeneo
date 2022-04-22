'use strict';

define([
        'oro/translator',
        'underscore',
        'jquery',
        'routing',
        'pim/form/common/save',
        'pim/template/form/save'
    ],
    function(
        __,
        _,
        $,
        Routing,
        SaveForm,
        template
    ) {
        return SaveForm.extend({
            config: [],
            template: _.template(template),
            currentKey: 'current_form_tab',
            events: {
                'click .save': 'save'
            },

            initialize: function (config) {
                this.config = config.config;
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template({
                    label: _.__('pim_enrich.entity.save.label')
                }));
                var saveBtn = this.$('.save');
                saveBtn.addClass('magento-save-config');
                if(sessionStorage && sessionStorage.getItem(this.currentKey) && 
                    ["webkul-magento2-connector-configuration-tab-documentation", "webkul-magento2-connector-configuration-tab-credential"].indexOf(sessionStorage.getItem(this.currentKey)) !== -1) {
                    saveBtn.hide();
                } else {
                    saveBtn.show();
                }
            },

            /**
             * {@inheritdoc}
             */
            save: function () {
                this.getRoot().trigger('pim_enrich:form:entity:pre_save', this.getFormData());
                this.showLoadingMask();

                var data = this.stringify(this.getFormData());
                var self = this;
                $.ajax({
                    method: 'POST',
                    url: this.getSaveUrl(),
                    contentType: 'application/json',
                    data: data
                })
                .then(this.postSave.bind(this))
                .fail(function(data) { this.updateFailureMessage = __(data); self.fail.bind(this)}
                )
                .always(this.hideLoadingMask.bind(this));
            },

            stringify: function(formData) {
                if('undefined' != typeof(formData['mapping']) && formData['mapping'] instanceof Array) {
                    formData['mapping'] = $.extend({}, formData['mapping']);
                }
                if('undefined' != typeof(formData['storeMapping']) && formData['storeMapping'] instanceof Array) {
                    formData['storeMapping'] = $.extend({}, formData['storeMapping']);
                }                

                return JSON.stringify(formData);                
            },

            /**
             * {@inheritdoc}
             */
            getSaveUrl: function () {
                var tab = null;
                switch(sessionStorage.getItem(this.currentKey)) {
                    case 'webkul-magento2-connector-configuration-tab-credential':
                        tab = 'credential';
                        break;
                    case 'webkul-magento2-connector-configuration-tab-store-mapping':
                        tab = 'storeMapping';
                        break;
                    case 'webkul-magento2-connector-configuration-tab-mapping':
                        tab = 'mapping';
                        break;
                    case 'webkul-magento2-connector-configuration-tab-association':
                        tab = 'association';
                        break;
                    case 'webkul-magento2-connector-configuration-tab-otherSettings':
                    default:
                        tab = 'otherSettings';
                    
                }

                if(this.config && this.config.postUrl) {
                    var url = this.config.postUrl;
                    return Routing.generate(url);
                } else {
                    var url = __moduleConfig.route;
                    var route = Routing.generate(url);
                    return tab ? route + '/' + tab : route;
                }
            },

            /**
             * {@inheritdoc}
             */
            postSave: function (data) {
                this.setData(data);
                this.getRoot().trigger('pim_enrich:form:entity:post_fetch', data);

                SaveForm.prototype.postSave.apply(this, arguments);
            }     
        });
    }
);
