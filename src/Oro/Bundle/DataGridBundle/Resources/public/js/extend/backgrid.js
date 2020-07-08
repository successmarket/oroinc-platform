define(function(require) {
    'use strict';

    const _ = require('underscore');
    const Backgrid = require('npmassets/backgrid/lib/backgrid');

    /**
     * Cells should be removed durung dispose cycle
     */
    Backgrid.Cell.prototype.keepElement = false;

    /**
     * Copied from backgrid. Removed unused in our project code which slow downs rendering
     */
    Backgrid.Cell.prototype.initialize = function(options) {
        this.column = options.column;
        /*
        // Columns are always prepared in Oro.Datagrid
        if (!(this.column instanceof Column)) {
            this.column = new Column(this.column);
        }
        */

        const column = this.column;
        const model = this.model;
        const $el = this.$el;

        let Formatter = Backgrid.resolveNameToClass(column.get('formatter') ||
            this.formatter, 'Formatter');

        if (!_.isFunction(Formatter.fromRaw) && !_.isFunction(Formatter.toRaw)) {
            Formatter = new Formatter();
        }

        this.formatter = Formatter;

        this.editor = Backgrid.resolveNameToClass(this.editor, 'CellEditor');

        this.listenTo(model, 'change:' + column.get('name'), function() {
            if (!$el.hasClass('editor')) {
                this.render();
            }
        });

        this.listenTo(model, 'backgrid:error', this.renderError);

        this.listenTo(column, 'change:editable change:sortable change:renderable',
            function(column) {
                const changed = column.changedAttributes();
                for (const key in changed) {
                    if (changed.hasOwnProperty(key)) {
                        $el.toggleClass(key, changed[key]);
                    }
                }
            });
        /*
        // These three lines give performance slow down
        if (Backgrid.callByNeed(column.editable(), column, model)) $el.addClass('editable');
        if (Backgrid.callByNeed(column.sortable(), column, model)) $el.addClass('sortable');
        if (Backgrid.callByNeed(column.renderable(), column, model)) $el.addClass('renderable');
        */
    };

    /**
     Render a text string in a table cell. The text is converted from the
     model's raw value for this cell's column.
     */
    Backgrid.Cell.prototype.render = function() {
        const $el = this.$el;
        $el.empty();
        const model = this.model;
        const columnName = this.column.get('name');
        $el.text(this.formatter.fromRaw(model.get(columnName), model));
        // $el.addClass(columnName);
        // this.updateStateClassesMaybe();
        this.delegateEvents();
        return this;
    };

    /**
     * Event binding on each cell gives perfomance slow down
     *
     * Please find support code in ../datagrid/row.js
     */
    Backgrid.Cell.prototype.delegatedEventBinding = true;
    const oldDelegateEvents = Backgrid.Cell.prototype.delegateEvents;
    Backgrid.Cell.prototype.delegateEvents = function() {
        if (_.isFunction(this.events)) {
            oldDelegateEvents.call(this);
        }
    };
    const oldUndelegateEvents = Backgrid.Cell.prototype.undelegateEvents;
    Backgrid.Cell.prototype.undelegateEvents = function() {
        if (_.isFunction(this.events)) {
            oldUndelegateEvents.call(this);
        }
    };

    /**
     * Shortcut method for the check if the cell is editable
     *
     * @return {boolean}
     */
    Backgrid.Cell.prototype.isEditableColumn = function() {
        return Backgrid.callByNeed(this.column.editable(), this.column, this.model);
    };

    return Backgrid;
});
