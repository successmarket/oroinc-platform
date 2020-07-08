define(function(require) {
    'use strict';

    const BaseComponent = require('oroui/js/app/components/base/component');
    const $ = require('jquery');
    const _ = require('underscore');

    const PasswordGenerateComponent = BaseComponent.extend({
        /**
         * @inheritDoc
         */
        constructor: function PasswordGenerateComponent(options) {
            PasswordGenerateComponent.__super__.constructor.call(this, options);
        },

        initialize: function(options) {
            this.$el = $(options.checkbox);
            this.passwordInput = $(options.passwordInput);

            this.togglePassword();

            this.$el.click(_.bind(this.togglePassword, this));
        },

        togglePassword: function() {
            if (this.$el.is(':checked')) {
                this.passwordInput.attr('disabled', true);
            } else {
                this.passwordInput.attr('disabled', false);
            }
        }
    });

    return PasswordGenerateComponent;
});
