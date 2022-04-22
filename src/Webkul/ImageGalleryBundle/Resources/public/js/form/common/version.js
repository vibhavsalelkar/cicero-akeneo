"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'webkulgallery/template/form/common/index/version',
        'routing',
        'pim/fetcher-registry',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        Routing,
        fetcherRegistry
    ) {
        return BaseForm.extend({        

            template: _.template(template),

            /**
             * {@inheritdoc}
             */
            
                render: function () {
                    fetcherRegistry.getFetcher('image-gallery-version').fetchAll()
                    .then(function (version) {
                    this.$el.html(this.template({
                        moduleVersion: version['moduleVersion'],
                    }));
                    this.delegateEvents();

                    return BaseForm.prototype.render.apply(this, arguments);
                }.bind(this));
            },

        });
    }
);
