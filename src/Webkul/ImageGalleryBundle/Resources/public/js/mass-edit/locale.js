'use strict';

define(
    [
        'underscore',
        'oro/translator',
        'pim/mass-edit-form/product/operation',
        'pim/common/select2/family',
        'wk-translator/template/mass-edit/change-locale',
        'pim/initselect2',
        'pim/fetcher-registry',
    ],
    function (
        _,
        __,
        BaseOperation,
        Select2Configurator,
        template,
        initSelect2,
        FetcherRegistry
    ) {
        return BaseOperation.extend({
            template: _.template(template),
            events: {
                'change .change-locale': 'updateModel'
            },

            /**
             * {@inheritdoc}
             */
            reset: function () {
                this.resetValue();
                this.setValue('need webkul translation', 'webkultranslation');
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                var locales;
                var scopes;
                locales = FetcherRegistry.getFetcher('akeneo-translator-quicklocales').fetchAll();
                scopes = FetcherRegistry.getFetcher('akeneo-translator-quickscope').fetchAll();
                Promise.all([locales, scopes]).then(function (values) {
                    this.locales = values[0];
                    this.scopes = values[1];
                    this.$el.html(this.template({
                        readOnly: this.readOnly,
                        model: this.locales,
                        modelscope: this.scopes,
                        changetolocalevalue: this.getChangeToLocaleValue(),
                        changefromlocalevalue: this.getChangeFromLocaleValue(),
                        changetoscopevalue: this.getChangeToScopeValue(),
                        changefromscopevalue: this.getChangeFromScopeValue(),
                    }));
                    
                    
                    $('.select2').each(function(key, select) {
                        if($(select).attr('readonly')) {
                            $(select).select2().select2('readonly', true);
                        } else {
                            $(select).select2();
                        }
                    });
                    self.$('.switch').bootstrapSwitch();
                    
                    $('.AknFieldContainer *[data-toggle="tooltip"]').tooltip();
                }.bind(this));

                return this;
            },

            /**
             * Update the form model from a dom event
             *
             * @param {event} event
             */
            updateModel: function (event) {
                
                this.setValue($(event.target).val() , event.target.name);
            },

            /**
             * update the form model
             *
             * @param {string} family
             */
            setValue: function (valueLocale,valuefield) {
                var data = this.getFormData();
                var count = 0;
                _.each(data.actions, function(value, key){
                    if (value.field === valuefield) {
                        count++;
                        data.actions[key].value = valueLocale;
                    }
                });

                if(count <= 0){
                    var tempData = {
                                field: valuefield,
                                value: valueLocale
                            };

                    data.actions.push(tempData);  
                }

                this.setData(data);
            },

            resetValue: function() {
                var data = this.getFormData();
                data.actions = [];
                this.setData(data);
            },

            /**
             * Get the current model value
             *
             * @return {string}
             */
            getChangeToLocaleValue: function () {
                var action = _.findWhere(this.getFormData().actions, {field: 'changetolocale'})

                return action ? action.value : null;
            },

            /**
             * Get the current model value
             *
             * @return {string}
             */
            getChangeFromLocaleValue: function () {
                var action = _.findWhere(this.getFormData().actions, {field: 'changefromlocale'})

                return action ? action.value : null;
            },
            /**
             * Get the current model value
             *
             * @return {string}
             */
            getChangeFromScopeValue: function () {
                var action = _.findWhere(this.getFormData().actions, {field: 'changefromscope'})

                return action ? action.value : null;
            },
            /**
             * Get the current model value
             *
             * @return {string}
             */
            getChangeToScopeValue: function () {
                var action = _.findWhere(this.getFormData().actions, {field: 'changetoscope'})

                return action ? action.value : null;
            }
        });
    }
);
