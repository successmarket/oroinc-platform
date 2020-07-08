define(function(require) {
    'use strict';

    const $ = require('jquery');
    const BaseComponent = require('oroui/js/app/components/base/component');

    const ComponentNeedsB = BaseComponent.extend({
        relatedSiblingComponents: {
            componentB: 'component-b'
        },

        /**
         * @inheritDoc
         */
        constructor: function ComponentNeedsB(options) {
            ComponentNeedsB.__super__.constructor.call(this, options);
        }
    });

    const ComponentNeedsCE = BaseComponent.extend({
        relatedSiblingComponents: {
            componentC: 'component-c',
            componentE: 'component-e'
        },

        /**
         * @inheritDoc
         */
        constructor: function ComponentNeedsCE(options) {
            ComponentNeedsCE.__super__.constructor.call(this, options);
        }
    });

    const ComponentNeedsA = BaseComponent.extend({
        relatedSiblingComponents: {
            componentA: 'component-a'
        },

        /**
         * @inheritDoc
         */
        constructor: function ComponentNeedsA(options) {
            ComponentNeedsA.__super__.constructor.call(this, options);
        }
    });

    const ComponentExtendNoNeedA = ComponentNeedsA.extend({
        relatedSiblingComponents: {
            componentA: false
        },

        /**
         * @inheritDoc
         */
        constructor: function ComponentExtendNoNeedA(options) {
            ComponentExtendNoNeedA.__super__.constructor.call(this, options);
        }
    });

    const ComponentNoNeeds = BaseComponent.extend({
        /**
         * @inheritDoc
         */
        constructor: function ComponentNoNeeds(options) {
            ComponentNoNeeds.__super__.constructor.call(this, options);
        }
    });

    const components = {
        'js/needs-b-component': ComponentNeedsB,
        'js/needs-ce-component': ComponentNeedsCE,
        'js/needs-a-component': ComponentNeedsA,
        'js/extend-no-need-a-component': ComponentExtendNoNeedA,
        'js/no-needs-component': ComponentNoNeeds
    };

    return function(moduleName) {
        const deferred = $.Deferred();
        setTimeout(function() {
            deferred.resolve(components[moduleName]);
        }, 0);
        return deferred.promise();
    };
});
