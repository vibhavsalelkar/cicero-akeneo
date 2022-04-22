'use strict'

define([
	'jquery',
    'underscore',
    'pim/form/common/fields/date',
    'datepicker',
    'pim/formatter/date',
    'pim/date-context'

],
function (
	$,
    _,
    parentDateField,
    Datepicker,
    DateFormatter,
    DateContext
){
    return parentDateField.extend({
    	/**
         * {@inheritdoc}
         */
        postRender: function () {
            var today = new Date();
            Datepicker
                .init(
                    this.$('.date-wrapper'),
                    {
                        format: DateContext.get('date').format,
                        defaultFormat: DateContext.get('date').defaultFormat,
                        language: DateContext.get('language'),
                        startDate: today
                    }
                )
                .on('changeDate', function () {
                    this.errors = [];
                    this.updateModel(this.getFieldValue(this.$('input')[0]));
                    this.$('.date-wrapper').datetimepicker('destroy');
                    this.getRoot().render();
                }.bind(this));
        }
    });

});