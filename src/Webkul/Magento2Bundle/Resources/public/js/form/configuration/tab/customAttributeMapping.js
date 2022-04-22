'use strict';

/**
 * Module used to display the attrribute datagrid in a custom attirbute mapping *
 */
define([
        'oro/translator',
        'pim/form',
        'pim/fetcher-registry',
        'pim/user-context',
        'pim/common/grid'
    ],
    function (
        __,
        BaseForm,
        FetcherRegistry,
        UserContext,
        Grid
    ) {
        return BaseForm.extend({
            className: 'products',

            /**
             * {@inheritdoc}
             */
            initialize: function (config) {
                this.config = config.config;

                BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.trigger('tab:register', {
                    code: this.code,
                    label: __(this.config.label)
                });

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                if (!this.CustomAttriubtesMappingGrid) {
                    let formData = this.getFormData();
                    
                    if(undefined === formData.otherMappings.custom_fields || !(formData.otherMappings.custom_fields instanceof Array) ) {
                        formData.otherMappings.custom_fields = [];
                        console.log(formData);
                    }
                    var selection =  formData.otherMappings !== undefined && formData.otherMappings.custom_fields !== undefined ? formData.otherMappings.custom_fields : [];

                    this.CustomAttriubtesMappingGrid = new Grid(
                        this.config.gridId,
                        {
                            locale: UserContext.get('catalogLocale'),
                            selection: selection,
                            selectionIdentifier: 'code'
                        }
                    );

                    this.CustomAttriubtesMappingGrid.on('grid:selection:updated', function (selection) {
                        
                        formData.otherMappings.custom_fields = selection;
                        console.log('selection', formData.otherMappings);
                        this.setData(formData);
                    }.bind(this));

                    this.getRoot().on('pim_enrich:form:entity:post_fetch', () => {
                        const shouldRefresh = this.code === this.getParent().getCurrentTab()
                        if (shouldRefresh) this.CustomAttriubtesMappingGrid.refresh();
                    });
                }

                this.$el.empty().append(this.CustomAttriubtesMappingGrid.render().$el);

                this.renderExtensions();
            }
        });
    }
);