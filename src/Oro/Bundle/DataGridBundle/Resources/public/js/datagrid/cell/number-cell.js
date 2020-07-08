define([
    'underscore',
    'backgrid',
    'orodatagrid/js/datagrid/formatter/number-formatter'
], function(_, Backgrid, NumberFormatter) {
    'use strict';

    /**
     * Number column cell.
     *
     * @export  oro/datagrid/cell/number-cell
     * @class   oro.datagrid.cell.NumberCell
     * @extends Backgrid.NumberCell
     */
    const NumberCell = Backgrid.NumberCell.extend({
        /** @property {orodatagrid.datagrid.formatter.NumberFormatter} */
        formatterPrototype: NumberFormatter,

        /** @property {String} */
        style: 'decimal',

        /**
         * @inheritDoc
         */
        constructor: function NumberCell(options) {
            NumberCell.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            _.extend(this, options);
            NumberCell.__super__.initialize.call(this, options);
            this.formatter = this.createFormatter();
        },

        /**
         * Creates number cell formatter
         *
         * @return {orodatagrid.datagrid.formatter.NumberFormatter}
         */
        createFormatter: function() {
            return new this.formatterPrototype({style: this.style});
        },

        /**
         * @inheritDoc
         */
        render: function() {
            const render = NumberCell.__super__.render.call(this);

            this.enterEditMode();

            return render;
        },

        /**
         * @inheritDoc
         */
        enterEditMode: function() {
            if (this.isEditableColumn()) {
                NumberCell.__super__.enterEditMode.call(this);
            }
        },

        /**
         * @inheritDoc
         */
        exitEditMode: function() {
            if (!this.isEditableColumn()) {
                NumberCell.__super__.exitEditMode.call(this);
            }
        }
    });

    return NumberCell;
});
