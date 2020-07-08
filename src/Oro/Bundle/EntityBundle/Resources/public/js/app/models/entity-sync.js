define(function(require) {
    'use strict';

    const _ = require('underscore');
    const Backbone = require('backbone');

    // Map from CRUD to HTTP for our default `Backbone.sync` implementation.
    const methodMap = {
        'create': 'POST',
        'update': 'PATCH',
        'patch': 'PATCH',
        'delete': 'DELETE',
        'read': 'GET'
    };

    const sync = function(method, model, options) {
        options.type = methodMap[method];
        options.contentType = 'application/vnd.api+json';

        const urlParams = _.clone(options.urlParams) || {};

        if (options.include && _.isArray(options.include)) {
            urlParams.include = options.include.join();
        }

        // @todo add filter by fields

        if (!options.url) {
            try {
                options.url = model.url(method, urlParams);
            } catch (e) {
                throw new Error('A "url" function must be specified');
            }
        }

        options.beforeSend = function(xhr) {
            // do not request HATEOAS links
            xhr.setRequestHeader('X-Include', 'noHateoas');
        };

        return Backbone.sync.call(this, method, model, options);
    };

    return sync;
});
