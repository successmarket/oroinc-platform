define(['underscore', 'backgrid', 'oroui/js/tools/text-util'
], function(_, Backgrid, textUtil) {
    'use strict';

    /**
     * Cell formatter with fixed fromRaw method
     *
     * @export  orodatagrid/js/datagrid/formatter/cell-formatter
     * @class   orodatagrid.datagrid.formatter.CellFormatter
     * @extends Backgrid.CellFormatter
     */
    const CellFormatter = function() {};

    CellFormatter.prototype = new Backgrid.CellFormatter();

    _.extend(CellFormatter.prototype, {
        /**
         * @inheritDoc
         */
        fromRaw: function(rawData) {
            if (rawData === null) {
                return '';
            }
            const result = Backgrid.CellFormatter.prototype.fromRaw.call(this, rawData);
            return textUtil.prepareText(result);
        }
    });

    return CellFormatter;
});
