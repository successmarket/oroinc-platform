define(function(require) {
    'use strict';

    const _ = require('underscore');
    const Cell = require('./jpm-cell');

    function Matrix(options) {
        const that = this;
        const steps = options.workflow.get('steps');
        const orders = _.uniq(steps.pluck('order'));

        this.workflow = options.workflow;
        this.width = 8;
        this.cells = _.range(orders.length).map(
            function() {
                return _.range(that.width).map(function() {
                    return [];
                });
            }
        );
        this.cellMap = {};
        this.transitions = {};
        this.workflow.get('transitions').each(function(transition) {
            that.transitions[transition.get('name')] = transition;
        });
        this.connections = [];
        steps.each(function(step) {
            _.each(step.get('allowed_transitions'), function(transitionName) {
                const transition = that.transitions[transitionName];
                let target;
                if (transition) {
                    target = steps.find(function(item) {
                        return item.get('name') === transition.get('step_to');
                    });
                    if (target) {
                        that.connections.push({
                            from: step.get('name'),
                            to: target.get('name')
                        });
                    }
                }
            });
        });

        this._fill(steps);
    }

    _.extend(Matrix.prototype, {
        move: function(cell, x, y) {
            if (typeof y === 'undefined' || y === false) {
                y = cell.y;
            }
            if (!_.isArray(this.cells[y][x]) || cell.x === x && cell.y === y) {
                return false;
            }
            const place = this.cells[cell.y][cell.x];
            this.cells[y][x].push(cell);
            place.splice(place.indexOf(cell), 1);
            cell.x = x;
            cell.y = y;
            return true;
        },
        remove: function(cell) {
            const place = this.cells[cell.y][cell.x];
            place.splice(place.indexOf(cell), 1);
            cell.step.set('position', [0, -1000]);
        },
        swap: function(c1, c2) {
            const x = c1.x;
            this.move(c1, c2.x);
            this.move(c2, x);
        },
        align: function() {
            let minX = this.width;
            let minY = this.cells.length;
            this.forEachCell(function(cell) {
                minX = Math.min(minX, cell.x);
                minY = Math.min(minY, cell.y);
            });
            if (minY > 0 || minX > 0) {
                this.forEachCell(_.bind(function(cell) {
                    this.move(cell, cell.x - minX, cell.y - minY);
                }, this));
            }
            return this;
        },
        findCell: function(step) {
            const name = typeof step === 'string' ? step : step.get('name');
            return name in this.cellMap ? this.cellMap[name] : null;
        },
        forEachCell: function(callback) {
            _.each(this.cellMap, callback);
            return this;
        },
        _fill: function(steps) {
            let row;
            let col;
            let key;
            let cell;
            let stepName;
            const groupedSteps = steps.groupBy(function(step) {
                return step.get('order');
            });
            const sortedKeys = _.each(_.keys(groupedSteps), parseInt).sort();
            // fill cells
            for (row = 0; row < sortedKeys.length; row++) {
                key = sortedKeys[row];
                for (col = 0; col < groupedSteps[key].length; col++) {
                    stepName = groupedSteps[key][col].get('name');
                    cell = new Cell({
                        x: col + 2,
                        y: row,
                        step: groupedSteps[key][col]
                    });
                    this.cells[row][col + 2] = [cell];
                    this.cellMap[stepName] = cell;
                }
            }
            // set children
            for (row = 0; row < this.cells.length; row++) {
                for (col = 0; col < this.cells[row].length; col++) {
                    if (this.cells[row][col].length) {
                        _.each(this.cells[row][col], _.bind(function(item) {
                            item.setChildren(this.findChildren(item));
                        }, this));
                    }
                }
            }
        },

        findChildren: function(parent) {
            const children = [];
            parent.step.getAllowedTransitions(this.workflow).each(_.bind(function(transition) {
                const cell = this.findCell(transition.get('step_to'));
                if (cell && children.indexOf(cell) < 0) {
                    children.push(cell);
                }
            }, this));
            return children;
        }
    });

    return Matrix;
});
