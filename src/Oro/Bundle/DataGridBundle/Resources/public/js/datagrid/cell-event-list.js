define(['underscore', 'backbone'], function(_, Backbone) {
    'use strict';

    function CellEventList(columns) {
        this.columns = columns;

        // listener will be removed with columns instance
        // no need to dispose this class
        columns.on('change:renderable add remove reset change:columnEventList', function() {
            delete this.cachedEventList;
            this.trigger('change');
        }, this);
    }

    CellEventList.prototype = {
        getEventsMap: function() {
            if (!this.cachedEventList) {
                const cellEventsList = {};
                this.columns.each(function(column) {
                    if (!column.get('renderable')) {
                        return;
                    }
                    const Cell = column.get('cell');
                    if (Cell.prototype.delegatedEventBinding && !_.isFunction(Cell.prototype.events)) {
                        const events = Cell.prototype.events;
                        // prevent CS error 'cause we must completely repeat Backbone behaviour
                        // eslint-disable-next-line guard-for-in
                        for (const eventName in events) {
                            if (!cellEventsList.hasOwnProperty(eventName)) {
                                cellEventsList[eventName] = true;
                            }
                        }
                    }
                });
                this.cachedEventList = cellEventsList;
            }
            return this.cachedEventList;
        }
    };

    _.extend(CellEventList.prototype, Backbone.Events);

    return CellEventList;
});
