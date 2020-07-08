define(function(require) {
    'use strict';

    const mediator = require('oroui/js/mediator');
    const BaseCollection = require('oronavigation/js/app/models/base/collection');

    const PinbarCollection = BaseCollection.extend({
        /**
         * @inheritDoc
         */
        constructor: function PinbarCollection(...args) {
            PinbarCollection.__super__.constructor.apply(this, args);
        },

        getCurrentModel: function() {
            return this.find(function(model) {
                return mediator.execute('compareNormalizedUrl', model.get('url'), {ignoreGetParameters: ['restore']});
            });
        }
    });

    return PinbarCollection;
});
