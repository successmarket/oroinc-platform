define(function(require, exports, module) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const Chaplin = require('chaplin');
    const __ = require('orotranslation/js/translator');
    const BaseController = require('oroui/js/app/controllers/base/controller');
    const PageModel = require('oroui/js/app/models/page-model');
    const errorHandler = require('oroui/js/error');
    let config = require('module-config').default(module.id);

    const document = window.document;
    const location = window.location;
    const history = window.history;
    const utils = Chaplin.utils;
    const mediator = Chaplin.mediator;

    config = _.extend({
        fullRedirect: false
    }, config);

    const PageController = BaseController.extend({});
    _.extend(PageController.prototype, {
        fullRedirect: config.fullRedirect,

        /**
         * Creates page model
         * @override
         */
        initialize: function(...args) {
            PageController.__super__.initialize.apply(this, args);

            const page = new PageModel();
            this.listenTo(page, 'request', this.onPageRequest);
            this.listenTo(page, 'sync', this.onPageLoaded);
            this.listenTo(page, 'invalid', this.onPageInvalid);
            this.listenTo(page, 'error', this.onPageError);
            this.model = page;

            // application is in action till first 'page:afterChange' event
            let isInAction = true;

            this.subscribeEvent('page:beforeChange', function() {
                isInAction = true;
            });
            this.subscribeEvent('page:afterChange', function() {
                isInAction = false;
            });
            mediator.setHandler('isInAction', function() {
                return isInAction;
            });
            this._setNavigationHandlers();
        },

        /**
         * Disposes page components
         * @override
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            PageController.__super__.dispose.call(this);
        },

        /**
         * Main action point
         *  - does background work before page loading
         *  - fetches page data from server
         *
         * @param {Object} params
         * @param {Object} route
         * @param {Object} options
         */
        index: function(params, route, options) {
            const url = this._combineRouteUrl(route);
            const args = {// collect arguments to reuse in events of page_fetch state change
                params: params,
                route: route,
                options: options
            };

            if (!route.previous) {
                // page just loaded from server, does not require extra request
                this.onPageLoaded(this.model, {}, {actionArgs: args});
                return;
            }

            if (!this._beforePageLoad(route, params, options)) {
                // prevent page loading, if there's redirect found
                return;
            }
            delete options.redirection;

            const cacheItem = this.cache.get(route.path);

            if (cacheItem && cacheItem.page && route.query === cacheItem.query && options.force !== true) {
                options.fromCache = true;
                this.onPageRequest(this.model, null, {actionArgs: args});
                this.model.set(cacheItem.page, {actionArgs: args});
                // manually call onPageLoaded() since there is no 'sync' event triggered
                this.onPageLoaded(this.model, {}, {actionArgs: args});
            } else {
                this.model.fetch({
                    url: url,
                    validate: true,
                    actionArgs: args
                });
            }
        },

        /**
         * Does preparation for page loading
         *  - adds event listeners on page model
         *  - triggers 'page:beforeChange' event with two parameters oldRoute and newRoute
         *  - handle route change, if it has place to be
         *
         * @param {Object} route
         * @param {Object} params
         * @param {Object} options
         * @returns {boolean}
         * @private
         */
        _beforePageLoad: function(route, params, options) {
            let url;
            let opts;
            const oldRoute = route.previous;
            const newRoute = _.extend(_.omit(route, ['previous']), {params: params});
            this.publishEvent('page:beforeChange', oldRoute, newRoute, options);

            // if route has been changed during 'page:beforeChange' event,
            // redirect to a new URL and stop processing current
            if (route.path !== newRoute.path || route.query !== newRoute.query) {
                url = this._combineRouteUrl(newRoute);
                opts = _.pick(options, ['forceStartup', 'changeURL', 'force', 'silent']);
                opts.replace = true;
                _.defer(function() {
                    mediator.execute('redirectTo', {url: url}, opts);
                });
                return false;
            }
            return true;
        },

        /**
         * Handles page request
         *  - triggers 'page:request' event
         *
         * @param {Chaplin.Model} model
         * @param {XMLHttpRequest} jqXHR
         * @param {Object} options
         */
        onPageRequest: function(model, jqXHR, options) {
            this.publishEvent('page:request', options.actionArgs);
        },

        /**
         * Handles page request done
         *  - triggers 'page:update' event with
         *  - updates page title
         *
         * @param {Chaplin.Model} model
         * @param {Object} result
         * @param {Object} options
         */
        onPageLoaded: function(model, result, options) {
            const self = this;
            const updatePromises = [];
            const pageData = model.getAttributes();
            const actionArgs = options.actionArgs;
            const jqXHR = options.xhr;

            if (pageData.title) {
                this.adjustTitle(pageData.title);
            }

            this.publishEvent('page:update', pageData, actionArgs, jqXHR, updatePromises);

            // execute callback function when all promises are either resolved or rejected
            function waitPromises(promises, callback) {
                $.when(...promises).always(function() {
                    const pendingPromises = _.filter(promises, function(promise) {
                        return promise.state() === 'pending';
                    });
                    if (pendingPromises.length) {
                        waitPromises(pendingPromises, callback);
                    } else {
                        callback();
                    }
                }).catch(function(error) {
                    if (error) {
                        errorHandler.showError(error);
                    }
                });
            }

            // once all views has been updated, trigger page:afterChange
            waitPromises(updatePromises, function() {
                self.publishEvent('page:afterChange');
            });
        },

        /**
         * Handles invalid page load
         *  - process redirect of it was found
         *
         * @param {Chaplin.Model} model
         * @param {*} error
         * @param {Object} options
         */
        onPageInvalid: function(model, error, options) {
            let pathDesc;
            let redirectOptions;
            if (error.redirect) {
                pathDesc = {url: error.location};
                redirectOptions = _.extend(
                    {replace: true},
                    options.actionArgs.options,
                    _.pick(error, ['redirect', 'fullRedirect'])
                );

                this._processRedirect(pathDesc, redirectOptions);
            }
        },

        /**
         * Handles page loading error
         *  - tries to parse raw data and keep updating page
         *
         * @param {Chaplin.Model} model
         * @param {XMLHttpRequest} jqXHR
         * @param {Object} options
         */
        onPageError: function(model, jqXHR, options) {
            const rawData = jqXHR.responseText;
            let data = {};

            if (jqXHR.status === 200 && rawData.indexOf('http') === 0) {
                data = {redirect: true, fullRedirect: true, location: rawData};
                model.set(data, options);
                return;
            }

            const payload = {stopPageProcessing: false};
            this.publishEvent('page:beforeError', jqXHR, payload);
            if (payload.stopPageProcessing) {
                history.back();
                return;
            }

            data = this._parseRawData(rawData);
            if (_.isObject(data)) {
                model.set(data, options);
            } else {
                errorHandler.showError(new Error(__('oro.ui.error.unexpected')));
            }

            this.publishEvent('page:error', model.getAttributes(), options.actionArgs, jqXHR);
            this.publishEvent('page:afterChange');
        },

        /**
         * Process redirect response
         *  see documentation for Chaplin.utils.redirectTo() method
         *
         * @param {Object|string} pathDesc
         * @param {Object=} params
         * @param {Object=} options
         * @private
         */
        _processRedirect: function(pathDesc, params, options) {
            let parser;
            let pathname;
            let query;
            const getUrl = function(pathname, query) {
                query = utils.queryParams.parse(query);
                query._rand = Math.random();
                query = utils.queryParams.stringify(query);
                return pathname + (query && ('?' + query));
            };
            options = options || {};
            if (typeof pathDesc === 'object' && pathDesc.url !== null && pathDesc.url !== void 0) {
                options = params || {};
                // fetch from URL only pathname and query
                parser = document.createElement('a');
                parser.href = pathDesc.url;
                // IE removes starting slash
                pathname = (parser.pathname[0] === '/' ? '' : '/') + parser.pathname;
                query = parser.search.substr(1);
                pathDesc.url = pathname + (query && ('?' + query));
            }
            options = _.defaults(options, {
                fullRedirect: this.fullRedirect
            });
            if (options.target === '_blank') {
                window.open(getUrl(pathname, query), '_blank');
                return;
            }
            if (options.fullRedirect) {
                location[options.replace ? 'replace' : 'assign'](getUrl(pathname, query));
                return;
            }
            if (options.redirect) {
                _.extend(options, {forceStartup: true, force: true, redirection: true});
                this.publishEvent('page:redirect', pathDesc, options);
                utils.redirectTo(pathDesc, options);
                return;
            }
            utils.redirectTo(pathDesc, params, options);
        },

        /**
         * Register handler for page reload and redirect
         *
         * @private
         */
        _setNavigationHandlers: function() {
            mediator.setHandler('redirectTo', function(pathDesc, params, options) {
                const queue = [];
                mediator.trigger('page:beforeRedirectTo', queue, pathDesc, params, options);
                $.when(...queue).done(_.bind(function() {
                    this._processRedirect(pathDesc, params, options);
                }, this));
            }, this);

            mediator.setHandler('refreshPage', function(options) {
                const queue = [];
                mediator.trigger('page:beforeRefresh', queue);
                options = options || {};
                _.defaults(options, {forceStartup: true, force: true});
                $.when(...queue).done(_.bind(function(customOptions) {
                    _.extend(options, customOptions || {});
                    this._processRedirect({url: location.href}, options);
                    mediator.trigger('page:afterRefresh');
                }, this));
            }, this);

            mediator.setHandler('submitPage', this._submitPage, this);
        },

        /**
         * Make data more bulletproof.
         *
         * @param {string} rawData
         * @param {number=} prevPos
         * @returns {Object}
         */
        _parseRawData: function(rawData, prevPos) {
            if (_.isUndefined(prevPos)) {
                prevPos = -1;
            }
            rawData = rawData.trim();
            const jsonStartPos = rawData.indexOf('{', prevPos + 1);
            let additionalData = '';
            let dataObj = null;
            let data;
            if (jsonStartPos > 0) {
                additionalData = rawData.substr(0, jsonStartPos);
                data = rawData.substr(jsonStartPos);
                try {
                    dataObj = JSON.parse(data);
                } catch (err) {
                    return this._parseRawData(rawData, jsonStartPos);
                }
            } else if (jsonStartPos === 0) {
                dataObj = JSON.parse(rawData);
            } else {
                // there's nothing to do
                dataObj = rawData;
            }

            if (additionalData) {
                additionalData = '<div class="alert alert-info fade in top-messages alert-dismissible " role="alert">' +
                    '<button type="button" class="close" data-dismiss="alert" aria-label="' + __('Close') + '">' +
                    '<span aria-hidden="true">&times;</span>' +
                    '</button>' +
                    '<div class="message">' + additionalData + '</div>' +
                    '</div>';
            }

            if (dataObj.content !== undefined) {
                dataObj.content = additionalData + dataObj.content;
            }

            return dataObj;
        },

        /**
         * Performs save call for a model
         * (is used for saving forms, all data should be already packet into options)
         *
         * @param options
         * @private
         */
        _submitPage: function(options) {
            this.publishEvent('page:beforeChange');
            options.actionArgs = options.actionArgs || {};
            _.defaults(options.actionArgs, {params: {}, route: {}, options: {}});
            this.model.save(null, options);
        }
    });

    return PageController;
});
