define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'pim/form/common/index/create-button',
        'webkulgallery/template/form/index/create-button',
        'pim/router',
        'pim/form-builder'
    ],function(
        $,
        _,
        __,
        BaseForm,
        template,
        router,
        FormBuilder
    ){
        return BaseForm.extend({
            template: _.template(template),
            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template({
                    title: __(this.config.title),
                    iconName: this.config.iconName,
                    url: this.config.url ? Routing.generate(this.config.url) : ''
                }));

                $('.Akeneo-gallery-redirect').on('click', function () {
                    router.redirectToRoute('webkulgallery_group_data_grid');
                }.bind(this));


                return this;

            }
        });
    });
