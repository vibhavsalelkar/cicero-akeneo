'use strict';
var currentPatch = null;
require(
    ['jquery', 'pim/patch-fetcher'],
    function ($, Fetcher) {
        $(function() {           
                var updateServerUrl = 'https://updates.akeneo.com';
                Fetcher.fetch(updateServerUrl).then(function (patch) {                 
                  currentPatch = patch;
                });
          
        });
    }
);
 
define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'backbone',
        'pim/form',
        'magento2bundleproduct/template/product/tab/bundled',
        'magento2bundleproduct/template/modal',
        'pim/fetcher-registry',
        'pim/attribute-manager',
        'pim/user-context',
        'routing',
        'oro/mediator',
        'oro/datagrid-builder',
        'oro/pageable-collection',
        'pim/datagrid/state',
        'require-context',
        'pim/form-builder',
        'pim/security-context',
        'pim/dialog',
        'oro/messenger',
        'bootstrap.bootstrapswitch'
    ],
    function (
        $,
        _,
        __,
        Backbone,
        BaseForm,
        formTemplate,
        modalTemplate,
        FetcherRegistry,
        AttributeManager,
        UserContext,
        Routing,
        mediator,
        datagridBuilder,
        PageableCollection,
        DatagridState,
        requireContext,
        FormBuilder,
        securityContext,
        Dialog,
        messenger,
    ) {
        let state = {};
        return BaseForm.extend({
            template: _.template(formTemplate),
            modalTemplate: _.template(modalTemplate),
            className: 'tab-pane active product-associations',
            events: {
                'click .add-option': 'addTable',
                'click .add-products': 'addProducts',
                'click .AknIconButton--trash.removeTable': 'confirmRemoveTable',
                'click .AknIconButton--trash.removeProducts': 'confirmRemoveProduct',
                'change select': 'updateModel',
                'change input': 'updateModel',
                'change checkbox': 'updateModel',
                'click .wk_toggler': 'toggleClass',
            },
            datagrids: {},
            config: {},
            productIdentifer: null,
            bundleFields: ['shipment_type', 'bundle_price_type', 'bundle_sku_type', 'bundle_weight_type', 'bundle_price_view'],
            defaultBundleFieldsValues: {
                "shipment_type" : "together",
                "bundle_price_view": "Price range",
                "bundle_price_type": false,
                "bundle_sku_type": false,
                "bundle_weight_type": false,
            },
            /**
             * {@inheritdoc}
             */
            initialize: function (meta) {
                this.config = meta.config;
                
                state = {
                    associationTarget: 'products'
                };

                BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.trigger('tab:register', {
                    code: (undefined === this.config.tabCode) ? this.code : this.config.tabCode,
                    isVisible: this.isVisible.bind(this),
                    label: __('pim_enrich.form.product.tab.bundled-product.title')
                });

                
                this.listenTo(this.getRoot(), 'pim_enrich:form:entity:post_update', this.postUpdate.bind(this));

                this.listenTo(this.getRoot(), 'pim_enrich:form:locale_switcher:change', function (localeEvent) {
                    if ('base_product' === localeEvent.context) {
                        this.render();
                    }
                }.bind(this));

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                
                const code = (undefined === this.config.tabCode) ? this.code : this.config.tabCode;

                if (!this.configured || code !== this.getParent().getCurrentTab()) {
                    return;
                }
                // var bundleOptions = this.getBundleOptions();
                // Promise.all([bundleOptions]).done(function() {

                    this.$el.html(
                        this.template({
                            product: this.getFormData(),
                            locale: UserContext.get('catalogLocale'),
                            bundleOptions: this.getBundleOptions(),
                            bundleFields: this.bundleFields,
                        })
                    );
        
                    this.$('.switch').bootstrapSwitch();
                    this.delegateEvents();
                // });

                return this;
            },

            /**
             * Add option table
             */
            addTable: function () {
                let values = {};
                values = this.getFormData();
                
                if(typeof values.bundleOptions === 'undefined' ||  !values.bundleOptions || typeof(values.bundleOptions) != 'object' || values.bundleOptions instanceof Array) {
                    values.bundleOptions = this.defaultBundleFieldsValues;
                }
                let tableName = 'table'+ (Object.keys(values.bundleOptions).length+1);
                values.bundleOptions[tableName] = {};

                this.setData(values);
                
                this.render();
            },

            /**
             *  Return the Available Bundle Options
             */
            getBundleOptions: function () {
                
                let options = {};
                var self = this;
                if(typeof this.getFormData().bundleOptions !== 'undefined') {
                    options = this.getFormData().bundleOptions;
                }                
                return options;
            },

            confirmRemoveTable: function(event) {
                Dialog.confirmDelete(
                    __("Are you sure you want to delete the Table"),
                    __('Confirm deletion'),
                    function () {
                        this.removeTable(event);
                    }.bind(this),
                    __('Option Table'),
                    __('Delete')
                );
            },

            /**
             * Remove the table
             */
            removeTable: function(event) {
                
                let values = {};
                values = this.getFormData();
                let name = $(event.target).attr('name');

                if(typeof values.bundleOptions[name] != 'undefined') {
                    delete values.bundleOptions[name];
                }
                this.setData(values);
                this.render();
            },

            confirmRemoveProduct: function(event) {
                Dialog.confirmDelete(
                    __("Are you sure you want to delete the product"),
                    __('Confirm deletion'),
                    function () {
                        this.removeProducts(event);
                    }.bind(this),
                    __('Option Products'),
                    __('Delete')
                );
            },

            removeProducts: function(event) {
               
                let values = {};
                values = this.getFormData();
                let name = $(event.target).attr('name');

                name = name.split("_product_");
                const tableName = name[0];
                const sku = name[1];
                var products = {};
                if(tableName != '' && sku != '') {
                    if(typeof values.bundleOptions[tableName] != 'undefined' && typeof values.bundleOptions[tableName].products != 'undefined') {
                        products = values.bundleOptions[tableName].products;
                        products = _.reject(products, function(product){
                            
                            return product.sku == sku;
                        });
                    }
                }
                values.bundleOptions[tableName].products = products;

                this.setData({'bundleOptions': values.bundleOptions}, {silent: true} );
                this.render();
            },

            updateModel: function (event) {
                let values = {};
                values = this.getFormData();
                let targetName = $(event.target).attr('name');
                var val = '';
                if($(event.target).attr('type') === "checkbox" || $(event.target).attr('type') === "radio") {
                    val = $(event.target).is(':checked');    
                } else {
                    val = $(event.target).val();
                }
                
                if(targetName === 'qty') {
                    val = parseInt(val);
                    if(val < 1) {
                        val = parseInt(1);
                    }
                } 

                if(this.bundleFields.indexOf(targetName) !== -1) {
                    if(typeof values.bundleOptions === 'undefined' ||  !values.bundleOptions || typeof(values.bundleOptions) != 'object' || values.bundleOptions instanceof Array) {
                        values.bundleOptions = this.defaultBundleFieldsValues;
                    }

                    if(typeof values.bundleOptions[targetName] === 'undefined') {
                        values.bundleOptions[targetName] = {};
                    }
                    values.bundleOptions[targetName] = val;  
                } else {
                    let tableName = $(event.target).closest('table').attr('name');
                    if(typeof values.bundleOptions[tableName] === 'undefined' || !values.bundleOptions[tableName] || typeof (values.bundleOptions[tableName] ) != 'object' || values.bundleOptions[tableName] instanceof Array) {
                        values.bundleOptions[tableName] = {};
                    }

                    if([tableName+'-is_default', 'qty', 'can_change_quantity'].indexOf(targetName) !== -1 && typeof values.bundleOptions[tableName].products !== "undefined") {
                        var skuVal = $(event.target).closest('tr').attr('name');
                        if(targetName === tableName + '-is_default') {
                            targetName = 'is_default';
                            if(!(typeof values.bundleOptions[tableName].type != 'undefined' && ['multi', 'checkbox'].indexOf(values.bundleOptions[tableName].type) != -1 )) {
                                var products = _.map(values.bundleOptions[tableName].products, function(product) { product[targetName] = false;  return product;});
                            } 
                        }
                        var product = _.find(values.bundleOptions[tableName].products, function(product) { return product.sku === skuVal })
                        if(targetName == 'qty' && (val =='' || val < 1)) {
                            messenger.notify(
                                'error',
                                __('Product quantity is mandetory field and cannot be blank')
                            );
                            this.render();
                            return;
                        }

                        if(product) {                            
                            product[targetName] = val;
                        }
                    } else {
                        values.bundleOptions[tableName][targetName] = val;                      
                    }

                    if(targetName === 'type' ) {
                        if(['multi', 'checkbox'].indexOf(val) != -1) {
                            values.bundleOptions[tableName].products = _.map(values.bundleOptions[tableName].products, function(product) {
                                return _.omit(product, 'can_change_quantity');
                            });
                        }
                        this.setData(values); 
                        this.render();
                    }
                }
                                   
                this.setData(values); 
            },

            /**
             * Refresh the associations panel after model change
             */
            postUpdate: function () {
                if (this.isVisible()) {
                    this.$('.selection-inputs input').val('');
                    state.selectedAssociations = {};
                    this.render();
                }
            },

            /**
             * Add products to option
             */
            addProducts: function (event) {
                let tableName = $(event.target).closest('table').attr('name');
                let values = this.getFormData();   
                if(typeof values.bundleOptions[tableName].title === 'undefined' || values.bundleOptions[tableName].title == "") {
                    messenger.notify(
                        'error',
                        __('Option Title is empty')
                    );
                    return;
                }
                if(typeof values.bundleOptions[tableName].type === 'undefined' || values.bundleOptions[tableName].title == "" ) {
                    messenger.notify(
                        'error',
                        __('Input Type is empty')
                    );
                    return;
                }
                
                this.launchProductPicker().then((products) => {

                    let productIds = [];
                    
                    products.forEach((item) => {
                        const matchProduct = item.match(/^product;(.*)$/);
                        if (matchProduct) {
                            productIds.push(matchProduct[1]);
                        }
                    });

                    products.forEach((item) => {
                        const matchProduct = item.match(/^product_(.*)$/);
                        if (matchProduct) {
                            productIds.push(matchProduct[1]);
                        }
                    });

                    let tableName = $(event.target).closest('table').attr('name');
                    let values = this.getFormData();    
                    if(typeof values.bundleOptions[tableName] === 'undefined' || !values.bundleOptions[tableName] || typeof (values.bundleOptions[tableName] ) != 'object' || values.bundleOptions[tableName] instanceof Array) {
                        values.bundleOptions[tableName] = {};
                    }
                    if(typeof values.bundleOptions[tableName]['products'] === "undefined") {
                        values.bundleOptions[tableName]["products"] = [];
                    }
                    
                    var previousProductIds = values.bundleOptions[tableName]["products"];
                    
                    for(let i=0; i<productIds.length; i++) {
                        if(!_.findWhere(previousProductIds, {sku: productIds[i]})) {
                            previousProductIds.push({sku: productIds[i], qty: 1});
                        }
                    }

                    this.updateFormDataOptionProducts(
                        previousProductIds,
                        tableName,
                        'products'
                    );
                    
                    this.getRoot().trigger('pim_enrich:form:update-association');
                    
                });

            },
            
            /**
             * Update the form data (product) associations
             *
             * @param {Array} currentAssociations
             * @param {string} assocType
             * @param {string} assocTarget
             */
            updateFormDataOptionProducts: function (currentAssociations, tableName, target) {
                let modelBundleOptions = this.getFormData().bundleOptions;
                modelBundleOptions[tableName][target] = currentAssociations;
                modelBundleOptions[tableName][target].sort();

                this.setData({'bundleOptions': modelBundleOptions}, {silent: true});
            },

            hideOptions: function () {
                // tbody, tfoot {
                //     display: none;
                // }
            },
            /**
             * Check if this extension is visible
             *
             * @returns {boolean}
             */
            isVisible: function () {
                return true;
            },

            isAddAssociationsVisible: function () {
                return securityContext.isGranted(this.config.aclAddAssociations);
            },

            
            /**
             * @returns {string}
             */
            getCurrentTarget: function () {
                return 'products';
            },

            toggleClass: function() {
                $('.wk_toggler').toggleClass('active');
                $('.wk_toggle').toggleClass('active');
                
            },

            /**
             * Launch the association product picker
             *
             * @return {Promise}
             */
            launchProductPicker: function () {
                const deferred = $.Deferred();
                FormBuilder.build('wk_bundle_associations-product-picker-form').then((form) => {
                    var modalObject = {
                        modalOptions: {
                            backdrop: 'static',
                            keyboard: false
                        },
                        allowCancel: true,
                        okCloses: false,
                        content: '',
                        cancelText: ' ',
                        okText: __('confirmation.title'),
                        className: 'modal modal--fullPage modal--topButton',
                    };
                    if(currentPatch >= '3.0') {
                        modalObject.innerClassName = 'AknFullPage--full';
                        modalObject.title = __('Add Products to Options In Bundle Product');
                        modalObject.template = this.modalTemplate;
                    } else {
                        form.setCustomTitle(__('Add Products to Options In Bundle Product'));
                    }
                    
                    let modal = new Backbone.BootstrapModal(modalObject);
                    modal.open();
                    form.setElement(modal.$('.modal-body')).render();
                    modal.on('cancel', deferred.reject);
                    modal.on('ok', () => {
                        const products = form.getItems().sort((a, b) => {
                            return a.code < b.code;
                        });
                        modal.close();

                        deferred.resolve(products);
                    });
                });

                return deferred.promise();
            },
        });
    }
);
