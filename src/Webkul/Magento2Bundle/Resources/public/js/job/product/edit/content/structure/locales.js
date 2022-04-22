'use strict';
/**
 * Multi Locale with multi structure filter
 */
define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'pim/template/export/product/edit/content/structure/locales',
        'pim/form',
        'pim/fetcher-registry',
        'jquery.select2'
    ],
    function (
        $,
        _,
        __,
        template,
        BaseForm,
        fetcherRegistry
    ) {
        return BaseForm.extend({
            className: 'AknFieldContainer',
            template: _.template(template),

            /**
             * Initializes configuration.
             *
             * @param {Object} config
             */
            initialize: function (config) {
                this.config = config.config;

                return BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * Configures this extension.
             *
             * @return {Promise}
             */
            configure: function () {
                this.listenTo(this.getRoot(), 'channel:update:later', this.localeUpdated.bind(this));

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * Renders locales dropdown.
             *
             * @returns {Object}
             */
            render: function () {
                if (!this.configured) {
                    return this;
                } 
                var scopes  = this.getFilters().structure.scope;
                scopes = scopes ? scopes : [];
                fetcherRegistry.getFetcher('channel')
                    .fetchByIdentifiers(scopes)
                    .always(function (scopes) {
                        var locales = [];
                        if(scopes) {
                            _.each(scopes, function(scope) {
                                _.each(scope.locales, function(locale) {
                                    if( typeof (_.findWhere(locales, {code: locale.code}) !== 'undefined')) {
                                        locales.push(locale);
                                    }
                                });
                                
                            });  
                        }
                        
                        
                        this.$el.html(
                            this.template({
                                isEditable: this.isEditable(),
                                __: __,
                                locales: this.getLocales(),
                                availableLocales: locales,
                                errors: [this.getParent().getValidationErrorsForField('locales')]
                            })
                        );

                        this.$('.select2').select2().on('change', this.updateState.bind(this));
                        this.$('[data-toggle="tooltip"]').tooltip();

                        this.renderExtensions();
                    }.bind(this));

                return this;
            },

            /**
             * Returns whether this filter is editable.
             *
             * @returns {boolean}
             */
            isEditable: function () {
                return undefined !== this.config.readOnly ?
                    !this.config.readOnly :
                    true;
            },

            /**
             * Sets new locales on field change.
             *
             * @param {Object} event
             */
            updateState: function (event) {
                this.setLocales(event.val);
            },

            /**
             * Sets specified locales into root model.
             *
             * @param {Array} codes
             */
            setLocales: function (codes) {
                
                var data = this.getFilters();
                var before = data.structure.locales;

                data.structure.locales = codes;
                this.setData(data);

                if (before !== codes) {
                    // this.getRoot().trigger('locales:update:after', codes);
                }
            },

            /**
             * Gets locales from root model.
             *
             * @returns {Array}
             */
            getLocales: function () {
                var structure = this.getFilters().structure;

                if (_.isUndefined(structure)) {
                    return [];
                }

                return _.isUndefined(structure.locales) ? [] : structure.locales;
            },

            /**
             * Resets locales after channel has been modified then re-renders the view.
             */
            localeUpdated: function () {
                this.initializeDefaultLocales()
                    .then(function () {                                                
                        this.render();
                    }.bind(this));
            },

            /**
             * Sets locales corresponding to the current scope (default state).
             *
             * @return {Promise}
             */
            initializeDefaultLocales: function () {
                return fetcherRegistry.getFetcher('channel')
                    .fetchByIdentifiers(this.getCurrentScope())
                    .then(function (scopes) {
                        var locales = [];
                        _.each(scopes, function(scope) {
                            locales.push(_.pluck(scope.locales, 'code'));
                        });
                        locales = _.uniq(_.flatten(locales));
                        
                        this.setLocales(locales);
                    }.bind(this));
            },

            /**
             * Gets current scope from root model.
             *
             * @return {String}
             */
            getCurrentScope: function () {
                return this.getFilters().structure.scope;
            },

            /**
             * Get filters
             *
             * @return {object}
             */
            getFilters: function () {
                return this.getFormData().configuration.filters;
            }
        });
    }
);
