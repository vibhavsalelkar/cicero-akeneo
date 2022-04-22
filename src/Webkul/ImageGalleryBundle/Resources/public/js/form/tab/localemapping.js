/**
 * Locale mapping tab module
 *
 * @author    Webkul
 * @copyright  Webkul Software Pvt Ltd (http://www.webkul.com)
 */

"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'webkul/akeneotranslator/template/configuration/tab/localemapping',
        'pim/fetcher-registry',
        'oro/loading-mask',
        'pim/initselect2'
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        FetcherRegistry,
        LoadingMask,
        initSelect2
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('webkul_translator_connector.form.configuration.group.locale_mapping.title'),
            template: _.template(template),
            code: 'webkul_translate_locale_mapping',
            events: {
                'change .AknFormContainer-Locale-mappings input.locale-mapping-field': 'updateModel',
                'click .AknButton.field-locale-mapping': 'addLocale',
                'click .AknIconButton--remove.delete-row': 'removeLocale'
            },
            locales: null,
            isFirstTime: true,

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
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();
                var locales;
                
                // if(typeof(this.locales) === 'undefined') {
                    locales = FetcherRegistry.getFetcher('ui-map-locale').fetchAll();
                // } else {
                //     locales = this.locales;
                // }
                self = this;
                Promise.all([locales]).then(function (values) {
                    self.locales = values[0];
                    self.$el.html(self.template({
                        locales : self.locales,
                        localeMappings :self.getFormData()['localeMappings']
                    }));
                    initSelect2.init(self.$('select'));
                    loadingMask.hide().$el.remove();
                });
                this.delegateEvents();
                
                return BaseForm.prototype.render.apply(this, arguments);
            },

            /**
             * Renders template
             *
             */
            renderTemp: function() {
                this.$el.html(this.template({
                    locales: this.locales,
                    localeMappings: this.getFormData()['localeMappings'],
                }));

                initSelect2.init(this.$('select'));

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
               
                if(!data['localeMappings']) {
                    data['localeMappings'] = {};
                }
                
                data['localeMappings'][$(event.target).attr('name')] = event.target.value;

                this.setData(data);
            },

            /**
             * Add locale mapping
             *
             * @param {Event} event
             */
            addLocale: function(event) {
                this.isFirstTime = false;

                var data = this.getFormData();
              
                if(!data['localeMappings'])
                    data['localeMappings'] = {};

                if(data['localeMappings'][$('#locale-mapping-input').val()] != undefined)
                    return;

                data['localeMappings'][$('#locale-mapping-input').val()] = '';
                this.setData(data);
                this.render()
            },

            /**
             * Remove locale mapping
             *
             * @param {Event} event
             */
            removeLocale: function(event) {
                $('.AknFieldContainer.' + $(event.target).attr('data-name')).remove()

                var data = this.getFormData();
              
                if(data['localeMappings']) {
                    delete data['localeMappings'][$(event.target).attr('data-name')]
                }

                this.setData(data);

                this.renderTemp()
            }
        });
    }
);
