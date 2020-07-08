define(function(require) {
    'use strict';

    const mediator = require('oroui/js/mediator');
    const BaseNavigationModel = require('oronavigation/js/app/models/base/model');
    const BaseCollection = require('oroui/js/app/models/base/collection');

    const BaseNavigationItemCollection = BaseCollection.extend({
        model: BaseNavigationModel,

        /**
         * @inheritDoc
         */
        constructor: function BaseNavigationItemCollection(...args) {
            BaseNavigationItemCollection.__super__.constructor.apply(this, args);
        },

        getCurrentModel: function() {
            return this.find(function(model) {
                return mediator.execute('compareUrl', model.get('url'));
            });
        }
    });

    return BaseNavigationItemCollection;
});
