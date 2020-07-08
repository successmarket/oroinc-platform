define(function(require) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const tools = require('oroui/js/tools');
    const scrollspy = {};

    scrollspy.init = function($container) {
        if (tools.isMobile()) {
            this._replaceWithCollapse($container);
            return;
        }

        if (!$container.is('body')) {
            // if it's not main scroll-spy, make its target unique
            this.makeUnique($container);
        }

        $container.find('[data-spy="scroll"]').each(function() {
            const $spy = $(this);

            if (tools.isDesktop()) {
                $spy.find('.responsive-section:last').each(function() {
                    const $section = $(this);
                    const titleHeight = $section.find('.scrollspy-title:visible').outerHeight();
                    $section.css('min-height', 'calc(100% + ' + (titleHeight || 0) + 'px)');
                });
            }

            $spy.scrollspy($spy.data());
        });
    };

    /**
     * Makes links and targets' ids of scroll-spy container unique
     *  - modifies scroll-spy's container target
     *  - adds ns-suffix for all links to not mix them with general scroll-spy
     *
     * @param {jQuery} container
     */
    scrollspy.makeUnique = function(container) {
        const $scrollSpy = container.find('[data-spy="scroll"]');
        if (!$scrollSpy.length) {
            // there's no scroll-spy elements
            return;
        }

        let containerId = container.attr('id');
        if (!containerId) {
            // make sure container has id
            containerId = _.uniqueId('scrollspy');
            container.attr('id', containerId);
        }

        $scrollSpy.each(function() {
            const suffix = _.uniqueId('-');
            const $spy = $(this);
            const href = $spy.attr('href');
            const menuSelector = $spy.data('target') || href || '';
            // make target to be container related
            $spy.data('target', '#' + containerId + ' ' + menuSelector);

            container.find(menuSelector + ' .nav > a').each(function() {
                let $target;
                const $link = $(this);
                let target = $link.data('target') || $link.attr('href');
                if (/^#\w/.test(target)) {
                    $target = container.find(target);
                }
                // make menu item and its target unique
                target += suffix;
                $link.attr('href', target);
                $target.attr('id', target.substr(1));
            });
        });
    };

    scrollspy._replaceWithCollapse = function(container) {
        container.find('[data-spy="scroll"]').each(function() {
            const $spy = $(this);
            $spy.removeAttr('data-spy').addClass('accordion');

            $spy.find('.scrollspy-title').each(function(i) {
                // first is opened, rest are closed
                const collapsed = i > 0;
                const $header = $(this);
                const $target = $header.next().next();
                const targetId = _.uniqueId('collapse-');
                const headerId = targetId + '-trigger';

                $header
                    .removeClass('scrollspy-title')
                    .addClass('accordion-toggle')
                    .toggleClass('collapsed', collapsed)
                    .attr({
                        'id': headerId,
                        'role': 'button',
                        'data-toggle': 'collapse',
                        'data-target': '#' + targetId,
                        'aria-controls': targetId,
                        'aria-expanded': !collapsed
                    })
                    .parent().addClass('accordion-group');
                $header.wrap('<div class="accordion-heading"/>');

                $target.addClass('accordion-body collapse')
                    .toggleClass('show', !collapsed)
                    .attr({
                        'id': targetId,
                        'role': 'region',
                        'aria-labelledby': headerId
                    });

                if (!collapsed) {
                    $target.data('toggle', false);
                }
                $target.on('focusin', function() {
                    $target.collapse('show');
                });
            });
        });
    };

    scrollspy.adjust = function() {
        if (tools.isMobile()) {
            return;
        }

        $('[data-spy="scroll"]').each(function() {
            const $spy = $(this);

            if ($spy.data('bs.scrollspy')) {
                $spy.scrollspy('refresh').scrollspy('_process');
            }
        });
    };

    return scrollspy;
});
