define(function(require) {
    'use strict';

    const template = require('tpl-loader!orofilter/templates/filter/choice-filter.html');
    const $ = require('jquery');
    const _ = require('underscore');
    const tools = require('oroui/js/tools');
    const TextFilter = require('oro/filter/text-filter');

    /**
     * Choice filter: filter type as option + filter value as string
     *
     * @export  oro/filter/choice-filter
     * @class   oro.filter.ChoiceFilter
     * @extends oro.filter.TextFilter
     */
    const ChoiceFilter = TextFilter.extend({
        /**
         * Template selector for filter criteria
         *
         * @property
         */
        template: template,
        templateSelector: '#choice-filter-template',

        /**
         * Selectors for filter criteria elements
         *
         * @property {Object}
         */
        criteriaValueSelectors: {
            value: 'input[name="value"]',
            type: 'input[type="hidden"]:last'
        },

        choiceDropdownSelector: '.choice-filter .dropdown-menu',

        /**
         * @property {boolean}
         */
        wrapHintValue: true,

        /**
         * Filter events
         *
         * @property
         */
        events: {
            'keyup input': '_onReadCriteriaInputKey',
            'keydown [type="text"]': '_preventEnterProcessing',
            'click .filter-criteria .filter-criteria-hide': '_onClickCloseCriteria',
            'click .disable-filter': '_onClickDisableFilter',
            'click .choice-value': '_onClickChoiceValue'
        },

        /**
         * @inheritDoc
         */
        constructor: function ChoiceFilter(options) {
            ChoiceFilter.__super__.constructor.call(this, options);
        },

        /**
         * Initialize.
         *
         * @param {Object} options
         */
        initialize: function(options) {
            const opts = _.pick(options || {}, 'choices');
            _.extend(this, opts);

            // init filter content options if it was not initialized so far
            if (_.isUndefined(this.choices)) {
                this.choices = [];
            }
            // temp code to keep backward compatible
            if ($.isPlainObject(this.choices)) {
                this.choices = _.map(this.choices, function(option, i) {
                    return {value: i.toString(), label: option};
                });
            }

            // init empty value object if it was not initialized so far
            if (_.isUndefined(this.emptyValue)) {
                this.emptyValue = {
                    type: (_.isEmpty(this.choices) ? '' : _.first(this.choices).value),
                    value: ''
                };
            }

            ChoiceFilter.__super__.initialize.call(this, options);
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            delete this.choices;
            delete this.emptyValue;
            ChoiceFilter.__super__.dispose.call(this);
        },

        render: function() {
            // render only wrapper (a button and a dropdown container e.g.)
            this._wrap('');
            // if there's no any wrapper, means it's embedded filter
            if (this.$el.html() === '') {
                this._renderCriteria();
            }
            return this;
        },

        getType: function() {
            const value = this._readDOMValue();
            return value.type;
        },

        /**
         * @inheritDoc
         */
        getTemplateData: function() {
            const value = _.extend({}, this.emptyValue, this.value);
            let selectedChoiceLabel = '';

            if (!_.isEmpty(this.choices)) {
                let foundChoice = _.find(this.choices, function(choice) {
                    return String(choice.value) === String(value.type);
                });
                foundChoice = foundChoice || _.first(this.choices);
                selectedChoiceLabel = _.result(foundChoice, 'label') || '';
            }

            return {
                name: this.name,
                choices: this.choices,
                selectedChoice: value.type,
                selectedChoiceLabel: selectedChoiceLabel,
                value: value.value,
                renderMode: this.renderMode
            };
        },

        /**
         * @inheritDoc
         */
        _renderCriteria: function() {
            const $filter = $(this.template(this.getTemplateData()));
            this._appendFilter($filter);
            this._updateDOMValue();
            this._updateValueField();
            this._criteriaRenderd = true;
            this._isRenderingInProgress = false;
        },

        _showCriteria: function() {
            if (!this._criteriaRenderd && !this._isRenderingInProgress) {
                this._isRenderingInProgress = true;
                this._renderCriteria();
            }
            this._updateValueField();
            ChoiceFilter.__super__._showCriteria.call(this);
        },

        _onClickChoiceValue: function(e) {
            ChoiceFilter.__super__._onClickChoiceValue.call(this, e);
            this._updateValueField();
        },

        reset: function() {
            ChoiceFilter.__super__.reset.call(this);
            this._updateValueField();
        },

        _updateValueField: function() {
            const valueFrame = this.$('.value-field-frame');
            if (!valueFrame.length) {
                return;
            }
            // update class of criteria dropdown
            const type = this.$(this.criteriaValueSelectors.type).val();
            const isEmptyType = this.isEmptyType(type);
            this.$('.filter-criteria').toggleClass('empty-type', isEmptyType);
            if (!isEmptyType) {
                this.$(this.criteriaValueSelectors.value).focus();
            }
        },

        /**
         * @inheritDoc
         */
        _getCriteriaHint: function(...args) {
            const value = (args.length > 0) ? this._getDisplayValue(args[0]) : this._getDisplayValue();
            let option = null;

            if (!_.isUndefined(value.type)) {
                const type = value.type;
                option = this._getChoiceOption(type);

                if (this.isEmptyType(type)) {
                    return option ? option.label : this.placeholder;
                }
            }

            if (!value.value) {
                return this.placeholder;
            }

            const hintValue = this.wrapHintValue ? ('"' + value.value + '"') : value.value;

            return (option ? option.label + ' ' : '') + hintValue;
        },

        /**
         * Fetches option object for corresponded value type
         *
         * @param {*|string} valueType
         * @returns {{value: string, label: string}}
         * @private
         */
        _getChoiceOption: function(valueType) {
            return _.findWhere(this.choices, {value: valueType.toString()});
        },

        /**
         * @inheritDoc
         */
        _writeDOMValue: function(value) {
            this._setInputValue(this.criteriaValueSelectors.value, value.value);
            this._setInputValue(this.criteriaValueSelectors.type, value.type);
            return this;
        },

        /**
         * @inheritDoc
         */
        _readDOMValue: function() {
            return {
                value: this._getInputValue(this.criteriaValueSelectors.value),
                type: this._getInputValue(this.criteriaValueSelectors.type)
            };
        },

        /**
         * @inheritDoc
         */
        _triggerUpdate: function(newValue, oldValue) {
            if (
                !tools.isEqualsLoosely(newValue, oldValue) &&
                (
                    this.isEmptyType(newValue.type) ||
                    this.isEmptyType(oldValue.type) ||
                    !this._isEmpty(newValue.value) ||
                    (!this._isEmpty(oldValue.value) && this._isEmpty(newValue.value))
                )
            ) {
                this.trigger('update');
            }
        },

        /**
         * Is a given integer, array, string, or object empty
         * @param {Object} value
         * @return {Boolean}
         */
        _isEmpty: function(value) {
            if (_.isNumber(value)) {
                return false;
            } else {
                return _.isEmpty(value);
            }
        },

        /**
         * @inheritDoc
         */
        _onValueUpdated: function(newValue, oldValue) {
            this.$(this.choiceDropdownSelector).each(function() {
                const $menu = $(this);
                const name = $menu.data('name') || 'type';
                if (oldValue[name] === newValue[name]) {
                    return;
                }

                $menu.find('li a').each(function() {
                    const item = $(this);
                    if (item.data('value').toString() === oldValue[name] && item.parent().hasClass('active')) {
                        item.parent().removeClass('active');
                    } else if (item.data('value').toString() === newValue[name] && !item.parent().hasClass('active')) {
                        item.parent().addClass('active');
                        $menu.parent().find('button').html(item.html() + '<span class="caret"></span>');
                    }
                });
            });

            ChoiceFilter.__super__._onValueUpdated.call(this, newValue, oldValue);
        }
    });

    return ChoiceFilter;
});
