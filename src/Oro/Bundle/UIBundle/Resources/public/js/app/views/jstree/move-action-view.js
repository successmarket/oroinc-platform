define(function(require) {
    'use strict';

    var MoveActionView;
    var AbstractActionView = require('oroui/js/app/views/jstree/abstract-action-view');
    var _ = require('underscore');
    var DialogWidget = require('oro/dialog-widget');
    var routing = require('routing');
    var messenger = require('oroui/js/messenger');

    MoveActionView = AbstractActionView.extend({
        options: _.extend({}, AbstractActionView.prototype.options, {
            icon: 'random',
            label: _.__('oro.ui.jstree.actions.move'),
            routeName: null,
            routeParams: {}
        }),

        onClick: function() {
            var $tree = this.options.$tree;
            var selectedIds = $tree.jstree('get_checked');

            if (!selectedIds.length) {
                messenger.notificationFlashMessage('warning', _.__('oro.ui.jstree.no_node_selected_error'));
                return;
            }

            var url = false;
            if (this.options.routeName) {
                var routeParams = this.options.routeParams;
                routeParams['selected'] = selectedIds;
                url = routing.generate(this.options.routeName, routeParams);
            }

            this.dialogWidget = new DialogWidget({
                title: _.__('oro.ui.jstree.actions.move'),
                url: url,
                stateEnabled: false,
                incrementalPosition: true,
                dialogOptions: {
                    modal: true,
                    allowMaximize: true,
                    width: 650,
                    minHeight: 100
                }
            });

            this.dialogWidget.once('formSave', _.bind(function(changed) {
                _.each(changed, function(data) {
                    $tree.jstree('move_node', data.id, data.parent, data.position);
                    $tree.jstree('uncheck_node', '#' + data.id);
                });
            }, this));

            this.dialogWidget.render();
        }
    });

    return MoveActionView;
});
