define(['oro/datagrid/string-cell', 'oro/translator'],
    function(StringCell, __) {
        'use strict';

        /**
         * Complete variant product column cell
         *
         * @extends oro.datagrid.StringCell
         */
        return StringCell.extend({
            /**
             * Render the completeness.
             */
            render: function () {
                if (this.model.get('complete_bundle_products') === null) {
                    this.$el.empty().html(__('not_available'));

                    return this;
                }

                const data = this.formatter.fromRaw(this.model.get(this.column.get('name')));
                let completeness = '-';
                
                if (null !== data && '' !== data) {
                    let cssClass = '';
                    if (data < 0 ) {
                        cssClass += 'warning';
                    } else if (0 === data) {
                        cssClass += 'important';
                    } else {
                        cssClass += 'success';
                    }

                    completeness = '<span class="AknBadge AknBadge--medium AknBadge--'+cssClass+'">'+ data +'</span>';
                }

                this.$el.empty().html(completeness);

                return this;
            }
        });
    }
);
