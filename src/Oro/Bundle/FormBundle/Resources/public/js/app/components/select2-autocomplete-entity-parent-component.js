define(function(require) {
    'use strict';

    const _ = require('underscore');
    const Select2AutocompleteComponent = require('oro/select2-autocomplete-component');

    const Select2AutocompleteEntityParentComponent = Select2AutocompleteComponent.extend({
        /**
         * @property {Object}
         */
        options: {
            delimiter: ';'
        },

        /**
         * @inheritDoc
         */
        constructor: function Select2AutocompleteEntityParentComponent(options) {
            Select2AutocompleteEntityParentComponent.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);
            Select2AutocompleteEntityParentComponent.__super__.initialize.call(this, options);
        },

        /**
         * @inheritDoc
         */
        makeQuery: function(query) {
            return [query, this.options.configs.entityId].join(this.options.delimiter);
        }
    });

    return Select2AutocompleteEntityParentComponent;
});
