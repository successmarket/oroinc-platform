define(function(require) {
    'use strict';

    const _ = require('underscore');
    const BaseView = require('oroui/js/app/views/base/view');
    const optionTemplate = require('tpl-loader!oroquerydesigner/templates/function-choice.html');

    const FunctionChoiceView = BaseView.extend({
        optionNames: BaseView.prototype.optionNames.concat([
            'converters', 'aggregates'
        ]),

        optionTemplate: optionTemplate,

        activeFunctionGroupKey: null,

        /**
         * @inheritDoc
         */
        constructor: function FunctionChoiceView(options) {
            FunctionChoiceView.__super__.constructor.call(this, options);
        },

        render: function() {
            this.$el.inputWidget('create');
            this._disable(true);
            return this;
        },

        /**
         * Sets functions conform the given criteria as active
         *
         * @param {Object}  criteria
         * @param {boolean} [convertersOnly=false]
         */
        setActiveFunctions: function(criteria, convertersOnly) {
            const foundGroups = [];
            let foundGroupKey = null;
            let content = '';
            const functions = [];

            _.each(this.converters, function(item, name) {
                if (this._matchApplicable(item.applicable, criteria)) {
                    foundGroups.push({group_name: name, group_type: 'converters'});
                }
            }, this);

            if (!convertersOnly && criteria) {
                _.each(this.aggregates, function(item, name) {
                    if (this._matchApplicable(item.applicable, criteria)) {
                        foundGroups.push({group_name: name, group_type: 'aggregates'});
                    }
                }, this);
            }

            if (!_.isEmpty(foundGroups)) {
                foundGroupKey = '';
                _.each(foundGroups, function(group) {
                    foundGroupKey += group.group_type + ':' + group.group_name + ';';
                });
            }

            if (foundGroupKey && (foundGroupKey !== this.activeFunctionGroupKey)) {
                this._clearSelect();

                _.each(foundGroups, function(foundGroup) {
                    _.each(this[foundGroup.group_type][foundGroup.group_name].functions, function(func) {
                        let existingFuncIndex = -1;

                        _.any(functions, function(val, index) {
                            if (val.name === func.name) {
                                existingFuncIndex = index;
                                return true;
                            }
                            return false;
                        });

                        if (existingFuncIndex !== -1) {
                            // override existing function and use its labels if needed
                            const existingLabel = functions[existingFuncIndex].label;
                            const existingTitle = functions[existingFuncIndex].title;
                            functions[existingFuncIndex] = _.extend({}, foundGroup, func);
                            if (_.isNull(functions[existingFuncIndex].label)) {
                                functions[existingFuncIndex].label = existingLabel;
                            }
                            if (_.isNull(functions[existingFuncIndex].title)) {
                                functions[existingFuncIndex].title = existingTitle;
                            }
                        } else {
                            functions.push(_.extend({}, foundGroup, func));
                        }
                    });
                }, this);

                _.each(functions, function(func) {
                    content += this.optionTemplate({data: func});
                }, this);

                if (content !== '') {
                    this.$el.append(content);
                }

                this.activeFunctionGroupKey = foundGroupKey;
            }
            this._disable(!foundGroupKey);
            if (this.$el.val() !== '') {
                this.$el.val('').trigger('change');
            }
        },

        _matchApplicable: function(applicable, criteria) {
            return _.find(applicable, function(item) {
                return _.every(item, function(value, key) {
                    return criteria[key] === value;
                });
            });
        },

        _clearSelect: function() {
            this.$el.find('option').not('[value=""]').remove();
        },

        _disable: function(flag) {
            this.$el.prop('disabled', flag).inputWidget('refresh');
            const $widgetContainer = this.$el.inputWidget('getContainer');
            if ($widgetContainer) {
                $widgetContainer.toggleClass('disabled', flag);
            }
        }
    });

    return FunctionChoiceView;
});
