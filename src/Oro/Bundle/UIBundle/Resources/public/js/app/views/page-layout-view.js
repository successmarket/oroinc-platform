define(function(require) {
    'use strict';

    const $ = require('jquery');
    const tools = require('oroui/js/tools');
    const Chaplin = require('chaplin');
    const mediator = require('oroui/js/mediator');
    const formToAjaxOptions = require('oroui/js/tools/form-to-ajax-options');
    const utils = Chaplin.utils;

    const PageLayoutView = Chaplin.Layout.extend({
        events: {
            'submit form': 'onSubmit',
            'click.action.data-api [data-action=page-refresh]': 'onRefreshClick'
        },

        listen: {
            'page:beforeChange mediator': 'removeErrorClass',
            'page:error mediator': 'addErrorClass'
        },

        /**
         * @inheritDoc
         */
        constructor: function PageLayoutView(options) {
            PageLayoutView.__super__.constructor.call(this, options);

            if (!this.settings.routeLinks) {
                // in case route links is turned of -- prevent navigation on empty hash
                this.delegate('click', 'a[href="#"]', function(event) {
                    event.preventDefault();
                });
            }
        },

        /**
         * @inheritDoc
         */
        render: function() {
            this.$el.attr({'data-layout': 'separate'});
            this.initLayout();
        },

        /**
         * Added raw argument. Removed Internet Explorer < 9 workaround
         *
         * @param {string} subtitle
         * @param {boolean=} raw
         * @returns {string}
         * @override
         */
        adjustTitle: function(subtitle, raw) {
            let title;
            if (!raw) {
                if (!subtitle) {
                    subtitle = '';
                }
                title = this.settings.titleTemplate({
                    title: this.title,
                    subtitle: subtitle
                });
            } else {
                title = subtitle;
            }
            // removed Internet Explorer < 9 workaround
            document.title = title;
            return title;
        },

        /**
         * Fixes issue when correspondent over options regions are not taken into account
         * @override
         */
        registerGlobalRegions: function(instance) {
            let name;
            let selector;
            let version;
            let _i;
            let _len;
            const _ref = utils.getAllPropertyVersions(instance, 'regions');

            if (instance.hasOwnProperty('regions')) {
                _ref.push(instance.regions);
            }

            for (_i = 0, _len = _ref.length; _i < _len; _i++) {
                version = _ref[_i];
                for (name in version) {
                    if (!version.hasOwnProperty(name)) {
                        continue;
                    }
                    selector = version[name];
                    this.registerGlobalRegion(instance, name, selector);
                }
            }
        },

        /**
         * Fixes issues
         *  - empty hashes (like '#')
         *  - routing full url (containing protocol and host)
         *  - stops application's navigation if it's an error page
         *  - process links with redirect options
         * @override
         */
        openLink: function(event) {
            let href;
            const el = event.currentTarget;
            const $el = $(el);

            if (
                utils.modifierKeyPressed(event) ||
                event.isDefaultPrevented() ||
                $el.parents('.sf-toolbar').length ||
                tools.isErrorPage()
            ) {
                return;
            }

            if (el.nodeName === 'A' && el.getAttribute('href')) {
                href = el.getAttribute('href');
                // prevent click by empty hashes
                if (href === '#') {
                    event.preventDefault();
                    return;
                }
                // fixes issue of routing full url, makes url relative
                if (href.indexOf(':\/\/') !== -1 && el.host === location.host) {
                    el.setAttribute('href', el.pathname + el.search + el.hash);
                }
            }

            // not link to same page and not javascript code link
            if (
                href &&
                !Chaplin.mediator.execute('compareUrl', href) &&
                href.substr(0, 11) !== 'javascript:'
            ) {
                const payload = {prevented: false, target: el};
                Chaplin.mediator.publish('openLink:before', payload);
                if (payload.prevented !== false) {
                    event.preventDefault();
                    return;
                }
            }

            href = el.getAttribute('href') || el.getAttribute('data-href') || null;
            if (!(href !== null && href !== void 0) || href === '' || href.charAt(0) === '#') {
                return;
            }
            const skipRouting = this.settings.skipRouting;
            switch (typeof skipRouting) {
                case 'function':
                    if (!skipRouting(href, el)) {
                        return;
                    }
                    break;
                case 'string':
                    if (utils.matchesSelector(el, skipRouting)) {
                        return;
                    }
            }
            if (this.isExternalLink(el)) {
                if (this.settings.openExternalToBlank) {
                    event.preventDefault();
                    this.openWindow(href);
                }
                return;
            }

            // now it's possible to pass redirect options over elements data-options attribute
            const options = $el.data('options') || {};
            utils.redirectTo({url: href}, options);
            event.preventDefault();
        },

        removeErrorClass: function() {
            this.$el.removeClass('error-page');
        },

        addErrorClass: function() {
            this.$el.addClass('error-page');
        },

        onSubmit: function(event) {
            let data;
            let options;

            if (event.isDefaultPrevented()) {
                return;
            }

            const $form = $(event.target);
            if ($form.data('nohash') && !$form.data('sent')) {
                $form.data('sent', true);
                return;
            }
            event.preventDefault();
            if ($form.data('sent')) {
                return;
            }

            $form.data('sent', true);

            let url = $form.attr('action');
            const method = $form.attr('method') || 'GET';

            if (url && url.indexOf('#') === 0) {
                return;
            }

            if (url && method.toUpperCase() === 'GET') {
                data = $form.serialize();
                if (data) {
                    url += (url.indexOf('?') === -1 ? '?' : '&') + data;
                }
                mediator.execute('redirectTo', {url: url});
                $form.removeData('sent');
            } else {
                options = formToAjaxOptions($form, {
                    complete: function() {
                        $form.removeData('sent');
                    }
                });
                mediator.execute('submitPage', options);
            }
        },

        onRefreshClick: function() {
            mediator.execute('refreshPage');
        }
    });

    return PageLayoutView;
});
