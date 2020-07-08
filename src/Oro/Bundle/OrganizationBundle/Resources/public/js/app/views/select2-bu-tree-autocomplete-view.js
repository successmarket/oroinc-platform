define(function(require) {
    'use strict';

    const _ = require('underscore');
    const Select2AutocompleteView = require('oroform/js/app/views/select2-autocomplete-view');

    const Select2BuTreeAutocompleteView = Select2AutocompleteView.extend({
        /**
         * @inheritDoc
         */
        constructor: function Select2BuTreeAutocompleteView(options) {
            Select2BuTreeAutocompleteView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            this.$el.on('input-widget:init', _.bind(this.setPlaceholder, this));
            this.$el.on('select2-blur', _.bind(this.setPlaceholder, this));
            Select2BuTreeAutocompleteView.__super__.initialize.call(this, options);
        },

        setPlaceholder: function() {
            const select2 = this.$el.data('select2');
            const placeholder = select2.getPlaceholder();

            if (typeof placeholder !== 'undefined' &&
                !select2.opened() &&
                select2.search.val().length === 0
            ) {
                select2.search.val(placeholder).addClass('select2-default');
            }
        }
    });

    return Select2BuTreeAutocompleteView;
});
