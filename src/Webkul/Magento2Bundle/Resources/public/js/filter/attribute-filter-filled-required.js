'use strict';
/**
 * Filter returning only values missing required values.
 *
 * @author    Yohan Blain <yohan.blain@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'pim/form',
        'pim/provider/to-fill-field-provider',
        'pim/user-context'
    ],
    function (
        $,
        _,
        __,
        BaseForm,
        toFillFieldProvider,
        UserContext
    ) {
        return BaseForm.extend({
            needToFill: {},
            /**
             * @returns {String}
             */
            getCode() {
                return 'filled_required';
            },

            /**
             * @returns {String}
             */
            getLabel() {
                return __('pim_enrich.entity.product.module.attribute_filled');
            },

            /**
             * @returns {Boolean}
             */
            isVisible() {
                return true;
            },

            /**
             * @param {Object} values
             *
             * @returns {Promise}
             */
            filterValues(values) {
                const scope = UserContext.get('catalogScope');
                const locale = UserContext.get('catalogLocale');

                // const fieldsToFill = toFillFieldProvider.getMissingRequiredFields(this.getFormData(), scope, locale);
                this.needToFill = {};
                $.each(values, function (key,attrVal) {
                    var i;
                    for (i = 0; i < attrVal.length; i++) {
                        if (attrVal[i]['scope'] && attrVal[i]['locale'] ) {
                            if (attrVal[i]['scope'] == scope && attrVal[i]['locale'] == locale) {
                                var data = attrVal[i]['data'] && typeof(attrVal[i]['data']['filePath']) !== 'undefined' 
                                                ? ( attrVal[i]['data']['filePath'] ? attrVal : null )
                                                : (attrVal[i]['data'] ? attrVal : null ) ;
                                if (data) {
                                    this.needToFill[key] = attrVal;
                                }
                            }
                        } else if(attrVal[i]['scope']){
                            if (attrVal[i]['scope'] == scope) {
                                var data = attrVal[i]['data'] && typeof(attrVal[i]['data']['filePath']) !== 'undefined' 
                                                ? ( attrVal[i]['data']['filePath'] ? attrVal : null )
                                                : (attrVal[i]['data'] ? attrVal : null ) ;
                                if (data) {
                                    this.needToFill[key] = attrVal;
                                }
                            }
                        } else if(attrVal[i]['locale']){
                            if (attrVal[i]['locale'] == locale) {
                                var data = attrVal[i]['data'] && typeof(attrVal[i]['data']['filePath']) !== 'undefined' 
                                                ? ( attrVal[i]['data']['filePath'] ? attrVal : null )
                                                : (attrVal[i]['data'] ? attrVal : null ) ;
                                if (data) {
                                    this.needToFill[key] = attrVal;
                                }
                            }
                        } else {
                            var data = attrVal[i]['data'] && typeof(attrVal[i]['data']['filePath']) !== 'undefined' 
                                            ? ( attrVal[i]['data']['filePath'] ? attrVal : null )
                                            : (attrVal[i]['data'] ? attrVal : null ) ;
                                                                   
                            if (data) {
                                this.needToFill[key] = attrVal;
                            }
                        }
                        
                    }

                }.bind(this));
                
                
                // const valuesToFill = _.pick(values, fieldsToFill);
                
                return $.Deferred().resolve(this.needToFill).promise();
            }
        });
    }
);
