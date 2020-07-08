define([
    'oro/filter/choice-filter'
], function(ChoiceFilter) {
    'use strict';

    const CommandWithArgsFilter = ChoiceFilter.extend({
        /**
         * @inheritDoc
         */
        constructor: function CommandWithArgsFilter(options) {
            CommandWithArgsFilter.__super__.constructor.call(this, options);
        }
    });

    return CommandWithArgsFilter;
});
