define(function(require, exports, module) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const __ = require('orotranslation/js/translator');
    const Backgrid = require('backgrid');
    let config = require('module-config').default(module.id);

    config = _.extend({
        showCloseButton: false,
        allowDefaultAriaLabel: true
    }, config);

    /**
     * Cell for grid, contains actions
     *
     * @export  oro/datagrid/cell/action-cell
     * @class   oro.datagrid.cell.ActionCell
     * @extends Backgrid.Cell
     */
    const ActionCell = Backgrid.Cell.extend({

        /** @property */
        className: 'action-cell',

        /** @property {Array} */
        actions: undefined,

        /** @property {boolean} */
        isDropdownActions: null,

        /** @property Integer */
        actionsHideCount: 3,

        /** @property {Array} */
        launchers: undefined,

        /** @property boolean */
        showCloseButton: config.showCloseButton,

        /** @property {string}: 'icon-text' | 'icon-only' | 'text-only' */
        launcherMode: '',

        /**
         * Allow launcher to use / set default aria-label attribute if it not defined
         *
         * @property boolean
         * */
        allowDefaultAriaLabel: config.allowDefaultAriaLabel,

        /** @property {string}: 'show' | 'hide' */
        actionsState: '',

        /** @property */
        baseMarkup: _.template(
            '<div class="more-bar-holder">' +
                '<div class="dropleft">' +
                    '<a class="dropdown-toggle" href="#" role="button" id="<%- togglerId %>" data-toggle="dropdown" ' +
                        'aria-haspopup="true" aria-expanded="false" aria-label="<%- label %>">' +
                        '<span class="icon fa-ellipsis-h" aria-hidden="true"></span>' +
                    '</a>' +
                    '<ul class="dropdown-menu dropdown-menu__action-cell launchers-dropdown-menu" ' +
                        'aria-labelledby="<%- togglerId %>"></ul>' +
                '</div>' +
            '</div>'
        ),

        /** @property */
        simpleBaseMarkup: _.template('<div class="more-bar-holder action-row"></div>'),

        /** @property */
        closeButtonTemplate: _.template(
            '<li class="dropdown-close"><i class="fa-close hide-text">' + __('Close') + '</i></li>'
        ),

        /** @property */
        launchersContainerSelector: '.launchers-dropdown-menu',

        /** @property */
        launchersListTemplate: _.template(
            '<% if (withIcons) { %>' +
                '<li><ul class="launchers-list"></ul></li>' +
            '<% } else { %>' +
                '<li class="well-small"><ul class="unstyled launchers-list"></ul></li>' +
            '<% } %>'
        ),

        /** @property */
        simpleLaunchersListTemplate: _.template(
            '<% if (withIcons) { %>' +
                '<ul class="nav nav--block nav-pills icons-holder launchers-list"></ul>' +
            '<% } else { %>' +
                '<ul class="unstyled launchers-list"></ul>' +
            '<% } %>'
        ),

        /** @property */
        launcherItemTemplate: _.template(
            '<li class="launcher-item<% if (className) { %> <%= \'mode-\' + className %><% } %>"></li>'
        ),

        /** @property */
        events: {
            'click': '_showDropdown',
            'keydown .dropdown-menu': 'onKeydown',
            'mouseover .dropdown-toggle': '_showDropdown',
            'mouseleave .dropleft.show': '_hideDropdown',
            'click .dropdown-close .fa-close': '_hideDropdown'
        },

        /**
         * @inheritDoc
         */
        constructor: function ActionCell(options) {
            ActionCell.__super__.constructor.call(this, options);
        },

        /**
         * Initialize cell actions and launchers
         */
        initialize: function(options) {
            const opts = options || {};
            this.subviews = [];

            if (opts.actionsHideCount !== void 0) {
                this.actionsHideCount = opts.actionsHideCount;
            }

            if (opts.themeOptions.actionsHideCount !== void 0) {
                this.actionsHideCount = opts.themeOptions.actionsHideCount;
            }

            if (opts.allowDefaultAriaLabel !== void 0) {
                this.allowDefaultAriaLabel = opts.allowDefaultAriaLabel;
            }

            if (_.isObject(opts.themeOptions.launcherOptions)) {
                this.launcherMode = opts.themeOptions.launcherOptions.launcherMode || this.launcherMode;
                this.actionsState = opts.themeOptions.launcherOptions.actionsState || this.actionsState;
            }

            ActionCell.__super__.initialize.call(this, options);
            this.actions = this.createActions();
            _.each(this.actions, function(action) {
                this.listenTo(action, 'preExecute', this.onActionRun);
            }, this);

            this.subviews.push(...this.actions);
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            delete this.actions;
            delete this.column;
            ActionCell.__super__.dispose.call(this);
        },

        /**
         * Handle action run
         *
         * @param {oro.datagrid.action.AbstractAction} action
         */
        onActionRun: function(action) {
            this.$('.show > [data-toggle="dropdown"]').trigger('tohide.bs.dropdown');
        },

        /**
         * Creates actions
         *
         * @return {Array}
         */
        createActions: function() {
            const result = [];
            const actions = this.column.get('actions');
            const config = this.model.get('action_configuration') || {};

            _.each(actions, function(action, name) {
                // filter available actions for current row
                if (!config || config[name] !== false) {
                    result.push(this.createAction(action, config[name] || {}));
                }
            }, this);

            return _.sortBy(result, 'order');
        },

        /**
         * Creates action
         *
         * @param {Function} Action
         * @param {Object} configuration
         * @protected
         */
        createAction: function(Action, configuration) {
            return new Action({
                model: this.model,
                datagrid: this.column.get('datagrid'),
                configuration: configuration
            });
        },

        /**
         * Creates actions launchers
         *
         * @protected
         */
        createLaunchers: function() {
            return _.map(this.actions, function(action) {
                return action.createLauncher({
                    launcherMode: this.launcherMode,
                    allowDefaultAriaLabel: this.allowDefaultAriaLabel
                });
            }, this);
        },

        /**
         * Render cell with actions
         */
        render: function() {
            let isSimplifiedMarkupApplied = false;
            // don't render anything if list of launchers is empty
            if (_.isEmpty(this.actions)) {
                this.$el.empty();

                return this;
            }

            this.isDropdownActions = this.actions.length >= this.actionsHideCount;

            if (this.actionsState === 'show') {
                this.isDropdownActions = false;
            } else if (this.actionsState === 'hide') {
                this.isDropdownActions = true;
            }

            if (!this.isDropdownActions) {
                isSimplifiedMarkupApplied = true;
                this.baseMarkup = this.simpleBaseMarkup;
                this.launchersListTemplate = this.simpleLaunchersListTemplate;
                this.launchersContainerSelector = '.more-bar-holder';
            }

            this.$el.html(this.baseMarkup(this.getTemplateData()));
            this.isLauncherListFilled = false;

            if (isSimplifiedMarkupApplied) {
                this.fillLauncherList();
            }

            return this;
        },

        getTemplateData: function() {
            return {
                togglerId: 'actions-cell-dropdown-' + this.cid,
                label: __('oro.datagrid.row_actions.label')
            };
        },

        fillLauncherList: function() {
            if (!this.isLauncherListFilled) {
                this.isLauncherListFilled = true;

                const launcherList = this.createLaunchers();

                const launchers = this.getLaunchersByIcons(launcherList);
                const $listsContainer = this.$(this.launchersContainerSelector);

                if (this.showCloseButton && launcherList.length >= this.actionsHideCount) {
                    $listsContainer.append(this.closeButtonTemplate());
                }

                if (launchers.withIcons.length) {
                    this.renderLaunchersList(launchers.withIcons, {withIcons: true})
                        .appendTo($listsContainer);
                }

                if (launchers.withIcons.length && launchers.withoutIcons.length) {
                    const divider = document.createElement($listsContainer[0].tagName === 'UL' ? 'li' : 'span');

                    divider.classList.add('divider');
                    $listsContainer.append(divider);
                }

                if (launchers.withoutIcons.length) {
                    this.renderLaunchersList(launchers.withoutIcons, {withIcons: false})
                        .appendTo($listsContainer);
                }
            }
        },

        /**
         * Render launchers list
         *
         * @param {Array} launchers
         * @param {Object=} params
         * @return {jQuery} Rendered element wrapped with jQuery
         */
        renderLaunchersList: function(launchers, params) {
            params = params || {};
            const result = $(this.launchersListTemplate(params));
            const $launchersList = result.filter('.launchers-list').length ? result : $('.launchers-list', result);
            _.each(launchers, function(launcher) {
                $launchersList.append(this.renderLauncherItem(launcher));
            }, this);

            return result;
        },

        /**
         * Render launcher
         *
         * @param {orodatagrid.datagrid.ActionLauncher} launcher
         * @param {Object=} params
         * @return {jQuery} Rendered element wrapped with jQuery
         */
        renderLauncherItem: function(launcher, params) {
            params = _.extend(params || {}, {className: launcher.launcherMode});
            const result = $(this.launcherItemTemplate(params));
            const $launcherItem = result.filter('.launcher-item').length ? result : $('.launcher-item', result);
            $launcherItem.append(launcher.render().$el);
            const className = 'mode-' + launcher.launcherMode;
            $launcherItem.addClass(className);
            if (this.isDropdownActions) {
                launcher.$el.addClass('dropdown-item');
            }
            return result;
        },

        /**
         * Get separate object of launchers arrays: with icons (key `withIcons`) and without icons (key `withoutIcons`).
         *
         * @return {Object}
         * @protected
         */
        getLaunchersByIcons: function(launcherList) {
            const launchers = {
                withIcons: [],
                withoutIcons: []
            };

            _.each(launcherList, function(launcher) {
                if (launcher.icon) {
                    launchers.withIcons.push(launcher);
                } else {
                    launchers.withoutIcons.push(launcher);
                }
            }, this);

            return launchers;
        },

        /**
         * Show dropdown
         *
         * @param {Event} e
         * @protected
         */
        _showDropdown: function(e) {
            this.fillLauncherList();
            if (!this.$('[data-toggle="dropdown"]').parent().hasClass('show')) {
                this.$('[data-toggle="dropdown"]').dropdown('toggle');
            }
            this.model.set('isDropdownActions', this.isDropdownActions);
            e.stopPropagation();
        },

        /**
         * Hide dropdown
         *
         * @param {Event} e
         * @protected
         */
        _hideDropdown: function(e) {
            if (this.$('[data-toggle="dropdown"]').parent().hasClass('show')) {
                this.$('[data-toggle="dropdown"]').dropdown('toggle');
            }
            e.stopPropagation();
        },

        onKeydown: function(event) {
            // close dropdown on ESC key
            if (event.which === 27) {
                this._hideDropdown(event);
            }
        }
    });

    return ActionCell;
});
