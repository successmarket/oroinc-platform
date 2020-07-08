define(function(require) {
    'use strict';

    const Backgrid = require('backgrid');
    const CellEventList = require('./cell-event-list');

    const GridColumns = Backgrid.Columns.extend({
        comparator: 'order',

        /**
         * @inheritDoc
         */
        constructor: function GridColumns(...args) {
            GridColumns.__super__.constructor.apply(this, args);
        },

        getCellEventList: function() {
            if (!this.cellEventList) {
                this.cellEventList = new CellEventList(this);
            }
            return this.cellEventList;
        }
    });

    return GridColumns;
});
