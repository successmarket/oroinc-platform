define(function(require, exports, module) {
    'use strict';

    const template = require('tpl-loader!orofilter/templates/filter/select-filter.html');
    const $ = require('jquery');
    const _ = require('underscore');
    const AbstractFilter = require('oro/filter/abstract-filter');
    const MultiselectDecorator = require('orofilter/js/multiselect-decorator');
    const LoadingMaskView = require('oroui/js/app/views/loading-mask-view');
    let config = require('module-config').default(module.id);

    config = _.extend({
        populateDefault: true
    }, config);

    /**
     * Select filter: filter value as select option
     *
     * @export  oro/filter/select-filter
     * @class   oro.filter.SelectFilter
     * @extends oro.filter.AbstractFilter
     */
    const SelectFilter = AbstractFilter.extend({
        /**
         * @property
         */

        MultiselectDecorator: MultiselectDecorator,

        /**
         * Filter selector template
         *
         * @property
         */
        template: template,
        templateSelector: '#select-filter-template',

        /**
         * Should default value be added to options list
         *
         * @property
         */
        populateDefault: config.populateDefault,

        /**
         * Selector for filter area
         *
         * @property
         */
        containerSelector: '.filter-select',

        /**
         * Selector for close button
         *
         * @property
         */
        disableSelector: '.disable-filter',

        /**
         * Selector for widget button
         *
         * @property
         */
        buttonSelector: '.filter-criteria-selector',

        /**
         * Selector for select input element
         *
         * @property
         */
        inputSelector: 'select',

        /**
         * Select widget object
         *
         * @property
         */
        selectWidget: null,

        /**
         * Minimum widget menu width, calculated depends on filter options
         *
         * @property
         */
        cachedMinimumWidth: null,

        /**
         * Select widget options
         *
         * @property
         */
        widgetOptions: {
            multiple: false,
            classes: 'select-filter-widget'
        },

        /**
         * Selector, jQuery object or HTML element that will be target for append multiselect dropdown menu
         *
         * @property
         */
        dropdownContainer: null,

        /**
         * Select widget menu opened flag
         *
         * @property
         */
        selectDropdownOpened: false,

        /**
         * @property {Boolean}
         */
        contextSearch: true,

        /**
         * @property {Boolean}
         */
        closeAfterChose: true,

        /**
         * @property {Boolean}
         */
        loadedMetadata: true,

        /**
         * Filter events
         *
         * @property
         */
        events: {
            'keydown select': '_preventEnterProcessing',
            'click .filter-select': '_onClickFilterArea',
            'click .disable-filter': '_onClickDisableFilter',
            'change select': '_onSelectChange'
        },

        /**
         * @inheritDoc
         */
        constructor: function SelectFilter(options) {
            SelectFilter.__super__.constructor.call(this, options);
        },

        /**
         * Initialize.
         *
         * @param {Object} options
         */
        initialize: function(options) {
            const opts = _.pick(options, 'choices', 'dropdownContainer', 'widgetOptions');
            $.extend(true, this, opts);

            this._setChoices(this.choices);

            // init empty value object if it was not initialized so far
            if (_.isUndefined(this.emptyValue)) {
                this.emptyValue = {
                    value: ''
                };
            }

            SelectFilter.__super__.initialize.call(this, options);

            if (this.lazy) {
                this.loadedMetadata = false;
                this.loader(
                    _.bind(function(metadata) {
                        this._setChoices(metadata.choices);
                        this.render();
                        if (this.subview('loading')) {
                            this.subview('loading').hide();
                        }
                    }, this)
                );
            }
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            delete this.choices;
            if (this.selectWidget) {
                this.selectWidget.dispose();
                delete this.selectWidget;
            }
            SelectFilter.__super__.dispose.call(this);
        },

        /**
         * @inheritDoc
         */
        getTemplateData: function() {
            const options = this.choices.slice(0);
            if (this.populateDefault) {
                options.unshift({value: '', label: this.placeholder || this.populateDefault});
            }

            return {
                label: this.labelPrefix + this.label,
                showLabel: this.showLabel,
                options: options,
                canDisable: this.canDisable,
                selected: _.extend({}, this.emptyValue, this.value),
                isEmpty: this.isEmpty(),
                renderMode: this.renderMode
            };
        },

        /**
         * Render filter template
         *
         * @return {*}
         */
        render: function() {
            const html = this.template(this.getTemplateData());

            if (!this.selectWidget) {
                this.setElement(html);
                this._initializeSelectWidget();
            } else {
                const selectOptions = $(html).find('select').html();
                this.$('select').html(selectOptions);
                this.selectWidget.multiselect('refresh');
            }

            if (!this.loadedMetadata && !this.subview('loading')) {
                this.subview('loading', new LoadingMaskView({
                    container: this.$el
                }));
                this.subview('loading').show();
            }

            return this;
        },

        /**
         * Set dropdownContainer for dropdown element
         *
         * @param {(jQuery|Element|String)} container
         * @protected
         */
        setDropdownContainer: function(container) {
            this.dropdownContainer = $(container);
        },

        /**
         * @inheritDoc
         */
        hide: function() {
            // when the filter has been opened and becomes invisible - close multiselect too
            if (this.selectWidget) {
                this.selectWidget.multiselect('close');
            }

            return SelectFilter.__super__.hide.call(this);
        },

        /**
         * Initialize multiselect widget
         *
         * @protected
         */
        _initializeSelectWidget: function() {
            const position = this._getSelectWidgetPosition();

            this.selectWidget = new this.MultiselectDecorator({
                element: this.$(this.inputSelector),
                parameters: _.extend({
                    noneSelectedText: this.placeholder,
                    showCheckAll: false,
                    showUncheckAll: false,
                    outerTrigger: this.$(this.buttonSelector),
                    selectedText: _.bind(function(numChecked, numTotal, checkedItems) {
                        return this._getSelectedText(checkedItems);
                    }, this),
                    position: position,
                    beforeopen: _.bind(function() {
                        this.selectWidget.onBeforeOpenDropdown();
                    }, this),
                    open: _.bind(function() {
                        this.selectWidget.onOpenDropdown();
                        this.trigger('showCriteria', this);
                        this._setDropdownWidth();
                        this._setButtonPressed(this.$(this.containerSelector), true);
                        this._clearChoicesStyle();
                        this.selectDropdownOpened = true;

                        this.selectWidget.updateDropdownPosition($.extend({}, position, {
                            within: this._findDropdownFitContainer(this.dropdownContainer) || this.dropdownContainer
                        }));
                    }, this),
                    refresh: _.bind(function() {
                        this.selectWidget.onRefresh();
                    }, this),
                    beforeclose: _.bind(function() {
                        return this.closeAfterChose;
                    }, this),
                    close: _.bind(function() {
                        this._setButtonPressed(this.$(this.containerSelector), false);
                        if (!this.disposed) {
                            this.selectDropdownOpened = false;
                        }
                    }, this),
                    appendTo: this._appendToContainer(),
                    refreshNotOpened: this.templateTheme !== ''
                }, this.widgetOptions),
                contextSearch: this.contextSearch
            });

            this.selectWidget.setViewDesign(this);
            this.selectWidget.getWidget().on('keyup', _.bind(function(e) {
                if (e.keyCode === 27) {
                    this._onClickFilterArea(e);
                }
            }, this));
        },

        /**
         * Get position to multiselect widget
         *
         * @returns {{my: string, at: string, of: *, collision: string, within: (*|null)}}
         * @private
         */
        _getSelectWidgetPosition: function() {
            return {
                my: 'left top+8',
                at: 'left bottom',
                of: this.$el,
                collision: _.isMobile() ? 'none' : 'fit none',
                within: this._findDropdownFitContainer(this.dropdownContainer) || this.dropdownContainer
            };
        },

        /**
         * Append multiselect widget to container
         * @return {jQuery}
         */
        _appendToContainer: function() {
            return this.$el;
        },

        /**
         * Remove styles from choices list
         *
         * @protected
         */
        _clearChoicesStyle: function() {
            const labels = this.selectWidget.getWidget().find('label');
            labels.removeClass('ui-state-hover');
            if (_.isEmpty(this.value.value)) {
                labels.removeClass('ui-state-active');
            }
        },

        /**
         * Get text for filter hint
         *
         * @param {Array} checkedItems
         * @protected
         */
        _getSelectedText: function(checkedItems) {
            if (_.isEmpty(checkedItems)) {
                return this.placeholder;
            }

            const elements = [];
            _.each(checkedItems, function(element) {
                const title = element.getAttribute('title');
                if (title) {
                    elements.push(title);
                }
            });
            return elements.join(', ');
        },

        /**
         * Get criteria hint value
         *
         * @return {String}
         */
        _getCriteriaHint: function(...args) {
            const value = (args.length > 0) ? this._getDisplayValue(args[0]) : this._getDisplayValue();
            const choice = _.find(this.choices, function(c) {
                return (c.value === value.value);
            });
            return !_.isUndefined(choice) ? choice.label : this.placeholder;
        },

        /**
         * Set design for select dropdown
         *
         * @protected
         */
        _setDropdownWidth: function() {
            if (!this.cachedMinimumWidth) {
                this.cachedMinimumWidth = this.selectWidget.getMinimumDropdownWidth() + 24;
            }
            const widget = this.selectWidget.getWidget();
            const filterWidth = this.$(this.containerSelector).width();
            const requiredWidth = Math.max(filterWidth + 24, this.cachedMinimumWidth);
            widget.width(requiredWidth).css('min-width', requiredWidth + 'px');
        },

        /**
         * Open/close select dropdown
         *
         * @param {Event} e
         * @protected
         */
        _onClickFilterArea: function(e) {
            if (!this.selectDropdownOpened) {
                this.selectWidget.multiselect('open');
            } else {
                this.selectWidget.multiselect('close');
            }

            e.stopPropagation();
        },

        /**
         * Triggers change data event
         *
         * @protected
         */
        _onSelectChange: function() {
            // set value
            this.applyValue();
            // update dropdown
            this.selectWidget.updateDropdownPosition();
        },

        /**
         * Handle click on filter disabler
         *
         * @param {Event} e
         */
        _onClickDisableFilter: function(e) {
            e.preventDefault();
            this.disable();
        },

        /**
         * @inheritDoc
         */
        _onValueUpdated: function(newValue, oldValue) {
            SelectFilter.__super__._onValueUpdated.call(this, newValue, oldValue);
            if (this.selectWidget) {
                this.selectWidget.multiselect('refresh');
            }
        },

        /**
         * @inheritDoc
         */
        _writeDOMValue: function(value) {
            this._setInputValue(this.inputSelector, value.value);
            return this;
        },

        /**
         * @inheritDoc
         */
        _readDOMValue: function() {
            return {
                value: this._getInputValue(this.inputSelector)
            };
        },

        _setChoices: function(choices) {
            choices = choices || [];

            // temp code to keep backward compatible
            this.choices = _.map(choices, function(option, i) {
                return _.isString(option) ? {value: i, label: option} : option;
            });
        },

        /**
         * @inheritDoc
         */
        _isDOMValueChanged: function() {
            const thisDOMValue = this._readDOMValue();
            return (
                !_.isUndefined(thisDOMValue.value) &&
                !_.isNull(thisDOMValue.value) &&
                !_.isEqual(this.value, thisDOMValue)
            );
        }
    });

    return SelectFilter;
});
