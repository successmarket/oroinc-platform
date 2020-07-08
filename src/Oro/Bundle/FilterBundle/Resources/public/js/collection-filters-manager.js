define([
    'underscore',
    'oroui/js/tools',
    'orofilter/js/filters-manager',
    'oroui/js/mediator'
], function(_, tools, FiltersManager, mediator) {
    'use strict';

    /**
     * View that represents all grid filters
     *
     * @export  orofilter/js/collection-filters-manager
     * @class   orofilter.CollectionFiltersManager
     * @extends orofilter.FiltersManager
     */
    const CollectionFiltersManager = FiltersManager.extend({
        /**
         * @inheritDoc
         */
        constructor: function CollectionFiltersManager(options) {
            CollectionFiltersManager.__super__.constructor.call(this, options);
        },

        /**
         * Initialize filter list options
         *
         * @param {Object} options
         * @param {oro.PageableCollection} [options.collection]
         * @param {Object} [options.filters]
         * @param {String} [options.addButtonHint]
         */
        initialize: function(options) {
            this.collection = options.collection;

            this.listenTo(this.collection, {
                beforeFetch: this._beforeCollectionFetch,
                updateState: this._onUpdateCollectionState,
                reset: this._onCollectionReset
            });

            this.isVisible = true;

            CollectionFiltersManager.__super__.initialize.call(this, options);
        },

        render: function() {
            CollectionFiltersManager.__super__.render.call(this);
            this._onUpdateCollectionState(this.collection);
            this._onCollectionReset(this.collection);
            return this;
        },

        /**
         * Triggers when filter is updated
         *
         * @param {oro.filter.AbstractFilter} filter
         * @protected
         */
        _onFilterUpdated: function(filter) {
            if (this.ignoreFiltersUpdateEvents) {
                return;
            }
            this._updateView();

            CollectionFiltersManager.__super__._onFilterUpdated.call(this, filter);
        },

        /**
         * Triggers before collection fetch it's data
         *
         * @protected
         */
        _beforeCollectionFetch: function(collection) {
            collection.state.filters = this._createState();
        },

        /**
         * Triggers when collection state is updated
         *
         * @param {oro.PageableCollection} collection
         */
        _onUpdateCollectionState: function(collection) {
            this.ignoreFiltersUpdateEvents = true;
            this._applyState(collection.state.filters || {});
            this._resetHintContainer();
            this.ignoreFiltersUpdateEvents = false;
        },

        /**
         * Triggers when filter select is changed
         *
         * @protected
         */
        _onChangeFilterSelect: function(filters) {
            CollectionFiltersManager.__super__._onChangeFilterSelect.call(this, filters);
            this._updateView();
        },

        /**
         * Triggers update filter state
         *
         * @protected
         */
        _updateView: function() {
            this.collection.state.currentPage = 1;
            this.collection.fetch({reset: true});
        },

        /**
         * Triggers after collection resets it's data
         *
         * @protected
         */
        _onCollectionReset: function(collection) {
            const hasRecords = collection.length > 0;
            const hasFiltersState = !_.isEmpty(collection.state.filters);
            if (hasRecords || hasFiltersState) {
                if (!this.isVisible) {
                    this.$el.show();
                    this.isVisible = true;
                }
            } else {
                if (this.isVisible) {
                    this.$el.hide();
                    this.isVisible = false;
                }
            }
        },

        /**
         * Create state according to filters parameters
         *
         * @return {Object}
         * @protected
         */
        _createState: function() {
            const state = {};
            _.each(this.filters, function(filter, name) {
                const shortName = '__' + name;
                if (_.has(this.collection.initialState.filters, name) && !filter.isEmptyValue()) {
                    state[name] = filter.getValue();
                } else if (filter.enabled) {
                    if (!filter.isEmptyValue()) {
                        state[name] = filter.getValue();
                    } else if (!filter.defaultEnabled) {
                        state[shortName] = '1';
                    }
                } else if (filter.defaultEnabled) {
                    state[shortName] = '0';
                }
            }, this);

            return state;
        },

        /**
         * Apply filter values from state
         *
         * @param {Object} state
         * @protected
         * @return {*}
         */
        _applyState: function(state) {
            const toEnable = [];
            const toDisable = [];
            const valuesToApply = {};

            _.each(this.filters, function(filter, name) {
                const shortName = '__' + name;
                let filterState;

                // Reset to initial state,
                // todo: should be removed after complete story about filter states
                if (filter.defaultEnabled === false && filter.enabled === true) {
                    this.disableFilter(filter);
                }

                if (filter.defaultEnabled === true && filter.enabled === false) {
                    this.enableFilter(filter);
                }

                if (_.has(state, name) && !tools.isEqualsLoosely(state[name], filter.emptyValue)) {
                    filterState = state[name];
                    if (!_.isObject(filterState)) {
                        filterState = {
                            value: filterState
                        };
                    }
                    valuesToApply[name] = filterState;
                    toEnable.push(filter);
                } else if (_.has(state, shortName)) {
                    filter.reset();
                    if (Number(state[shortName])) {
                        toEnable.push(filter);
                    } else {
                        toDisable.push(filter);
                    }
                } else {
                    filter.reset();
                }
            }, this);

            this.enableFilters(toEnable);
            this.disableFilters(toDisable);

            _.each(valuesToApply, function(filterState, name) {
                this.filters[name].setValue(filterState);
            }, this);

            mediator.trigger('filters-manager:after-applying-state');
            this.checkFiltersVisibility();

            return this;
        }
    });

    return CollectionFiltersManager;
});
