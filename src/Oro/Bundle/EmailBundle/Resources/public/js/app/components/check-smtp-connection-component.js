define(function(require) {
    'use strict';

    var $ = require('jquery');
    var _ = require('underscore');
    var BaseComponent = require('oroui/js/app/components/base/component');
    var CheckSmtpConnectionView = require('../views/check-smtp-connection-view');
    var CheckSavedSmtpConnectionView = require('../views/check-saved-smtp-connection-view');
    var CheckSmtpConnectionModel = require('../models/check-smtp-connection-model');
    var CheckSmtpConnectionComponent;

    CheckSmtpConnectionComponent = BaseComponent.extend({
        /**
         * @inheritDoc
         */
        constructor: function CheckSmtpConnectionComponent() {
            CheckSmtpConnectionComponent.__super__.constructor.apply(this, arguments);
        },

        /**
         * Initialize component
         *
         * @param {Object} options
         * @param {string} options.elementNamePrototype
         */
        initialize: function(options) {
            if (options.elementNamePrototype) {
                var viewOptions = _.extend({
                    el: $(options._sourceElement).closest(options.parentElementSelector),
                    entity: options.forEntity || 'user',
                    entityId: options.id,
                    organization: options.organization || ''
                }, options.viewOptions || {});

                if (options.view !== 'saved') {
                    viewOptions.model = new CheckSmtpConnectionModel({});
                    this.view = new CheckSmtpConnectionView(viewOptions);
                } else {
                    this.view = new CheckSavedSmtpConnectionView(viewOptions);
                }
            } else {
                // unable to initialize
                $(options._sourceElement).remove();
            }
        }
    });
    return CheckSmtpConnectionComponent;
});
