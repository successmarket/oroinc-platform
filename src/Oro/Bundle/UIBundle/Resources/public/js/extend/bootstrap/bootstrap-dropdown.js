define(function(require, exports, module) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    let config = require('module-config').default(module.id);

    const Popper = require('popper');
    const manageFocus = require('oroui/js/tools/manage-focus').default;
    const Dropdown = require('bootstrap-dropdown');
    const original = _.clone(Dropdown.prototype);
    const _clearMenus = Dropdown._clearMenus;

    const DATA_KEY = 'bs.dropdown';
    const EVENT_KEY = '.' + DATA_KEY;
    const DATA_API_KEY = '.data-api';
    const HIDE_EVENT = 'hide' + EVENT_KEY;
    const TO_HIDE_EVENT = 'tohide' + EVENT_KEY;
    const HIDING_EVENT = 'hiding' + EVENT_KEY;
    const HIDDEN_EVENT = 'hidden' + EVENT_KEY;
    const GRID_SCROLLABLE_CONTAINER = '.grid-scrollable-container';
    const DIALOG_SCROLLABLE_CONTAINER = '.ui-dialog-content';
    const SCROLLABLE_CONTAINER = [
        DIALOG_SCROLLABLE_CONTAINER,
        GRID_SCROLLABLE_CONTAINER
    ].join(',');
    const ESC_KEY_CODE = 27;
    const ClassName = {
        DISABLED: 'disabled',
        SHOW: 'show',
        DROPUP: 'dropup',
        DROPRIGHT: 'dropright',
        DROPLEFT: 'dropleft',
        MENURIGHT: 'dropdown-menu-right',
        MENULEFT: 'dropdown-menu-left',
        POSITION_STATIC: 'position-static'
    };

    config = _.extend({
        displayArrow: true,
        keepSeparately: true
    }, config);

    _.extend(Dropdown.prototype, {
        toggle: function() {
            Dropdown._togglingElement = this._element;
            Dropdown._isShowing = !$(this._menu).hasClass('show');

            original.toggle.call(this);
            this.syncAriaExpanded();

            if (Dropdown._isShowing) {
                manageFocus.focusTabbable($(this._menu));
                this.bindKeepFocusInside();
            } else {
                this.unbindKeepFocusInside();
            }

            if (this._displayArrow()) {
                $(this._menu).attr('x-displayed-arrow', Dropdown._isShowing ? '' : null);
            }

            delete Dropdown._togglingElement;
            delete Dropdown._isShowing;
        },

        show: function() {
            original.show.call(this);
            this.syncAriaExpanded();
            this.bindKeepFocusInside();
        },

        hide: function() {
            original.hide.call(this);
            this.syncAriaExpanded();
            this.unbindKeepFocusInside();
        },

        bindKeepFocusInside: function() {
            $(this._menu).on(_events(['keydown']), e => {
                if (e.keyCode === ESC_KEY_CODE) {
                    this.hide();
                    this._element.focus();
                } else {
                    manageFocus.preventTabOutOfContainer(e, e.currentTarget);
                }
            });
        },

        unbindKeepFocusInside: function() {
            $(this._menu).off(_events(['keydown']));
        },

        syncAriaExpanded: function() {
            this._element.setAttribute('aria-expanded', $(this._menu).hasClass(ClassName.SHOW));
        },

        dispose: function() {
            const parent = Dropdown._getParentFromElement(this._element);
            $(parent).off(EVENT_KEY);

            if (this._dialog) {
                $(this._dialog).off(EVENT_KEY);
                delete this._dialog;
            }
            original.dispose.call(this);
        },

        _getConfig: function() {
            const config = original._getConfig.call(this);
            let placement = config.placement;

            if (
                placement && _.isRTL() &&
                (placement = placement.split('-')).length === 2 &&
                ['auto', 'top', 'bottom'].indexOf(placement[0]) !== -1 &&
                ['start', 'end'].indexOf(placement[1]) !== -1
            ) {
                placement[1] = {start: 'end', end: 'start'}[placement[1]];
                config.placement = placement.join('-');
            }

            if ('adjustHeight' in config) {
                // empty attribute `data-adjust-height` considered as turn ON option
                config.adjustHeight = config.adjustHeight === '' || config.adjustHeight;
            }

            return config;
        },

        _getMenuElement: function() {
            original._getMenuElement.call(this);

            if (!this._menu) {
                // if the menu element wasn't found by selector `.dropdown-menu`,
                // the element next to toggler button is considered as menu
                this._menu = $(this._element).next();
            }

            return this._menu;
        },

        _addEventListeners: function() {
            this._popperUpdate = this._popperUpdate.bind(this);

            original._addEventListeners.call(this);

            const parent = Dropdown._getParentFromElement(this._element);
            const dialogContent = $(this._element).closest(DIALOG_SCROLLABLE_CONTAINER);

            this._dialog = dialogContent.length && dialogContent.parent() || null;

            $(this._element).add(parent).on(TO_HIDE_EVENT, function(event) {
                event.stopImmediatePropagation();
                if ($(this._menu).hasClass('show')) {
                    this.toggle();
                }
            }.bind(this));

            $(parent).on(HIDE_EVENT, this._onHide.bind(this));
            $(parent).on(HIDDEN_EVENT, this._onHidden.bind(this));

            if (this._dialog) {
                $(this._dialog).on(
                    _events(['dialogresize', 'dialogdrag', 'dialogreposition']),
                    this._popperUpdate
                );
            }
        },

        _popperUpdate: function(e) {
            if (this._popper) {
                // When scrolling leads to hidden dropdown appears again, single call of scroll handler
                // shows dropdown menu in wrong position. But since single scroll event happens very
                // rarely in real life the next scroll event sets dropdown menu correctly.
                // To emulate similar effect for custom scroll just call `scheduleUpdate` twice
                this._popper.scheduleUpdate();
                this._popper.scheduleUpdate();
            }
        },

        /**
         * Handles 'hide' event triggered from _clearMenus
         *
         * @param event
         * @protected
         */
        _onHide: function(event) {
            if (this._element !== event.relatedTarget) {
                return;
            }

            if (Dropdown._isShowing && $.contains(this._menu, Dropdown._togglingElement)) {
                // prevent parent menu close on opening nested dropdown
                event.preventDefault();
            }

            let $clickTarget;

            if (
                Dropdown._clickEvent &&
                this._config.preventCloseOnMenuClick === true &&
                ($clickTarget = $(Dropdown._clickEvent.target)) &&
                $clickTarget.closest('.dropdown-menu').is(this._menu) &&
                !$clickTarget.is('[data-role="close"]')
            ) {
                // prevent parent menu close on click inside
                event.preventDefault();
            }

            if (!event.isDefaultPrevented()) {
                $(this._menu).trigger(HIDING_EVENT);
            }
        },

        _onHidden: function(event) {
            //  removing popper scroll listeners when dropdown is hidden.
            this._popperDestroy();
        },

        _popperDestroy: function() {
            if (this._popper !== null) {
                // the fix deletes previews instance to prevent memory leaks
                this._popper.destroy();
                this._popper = null;
            }
        },

        _getPopperConfig: function() {
            const config = original._getPopperConfig.call(this);

            if (!config.positionFixed && $(this._element).closest(SCROLLABLE_CONTAINER).length) {
                // dropdowns are shown with position fixed inside scrollable container, to fix overflow
                config.positionFixed = true;
            }

            if (this._config.inheritParentWidth) {
                const inheritParentWidth = this._config.inheritParentWidth;
                config.positionFixed = true;
                config.modifiers.offset = {
                    fn: function(data, options) {
                        const popper = data.instance.popper;
                        const offset = data.offsets.popper;

                        if (
                            offset.width &&
                            (inheritParentWidth === 'strictly' || offset.width < popper.parentElement.clientWidth)
                        ) {
                            popper.style.width = popper.parentElement.clientWidth + 'px';
                            _.extend(offset, _.pick(
                                popper.parentElement.getBoundingClientRect(),
                                'left',
                                'right',
                                'width')
                            );
                        }

                        Popper.Defaults.modifiers.offset.fn(data, options);

                        return data;
                    }
                };
            }

            // https://popper.js.org/popper-documentation.html#Popper.Defaults
            _.extend(config, _.pick(this._config, 'placement', 'positionFixed', 'eventsEnabled'));
            _.extend(config.modifiers, _.pick(this._config.modifiers, 'shift', 'offset', 'preventOverflow',
                'keepTogether', 'arrow', 'flip', 'inner', 'hide', 'computeStyle', 'applyStyle'));

            if (this._config.adjustHeight && config.placement.substring(0, 6) === 'bottom') {
                config.modifiers.adjustHeight = {enabled: true};
                config.modifiers.flip = {enabled: false};
            }

            if (this._displayArrow()) {
                const menu = this._getMenuElement();
                let arrow = $(menu).children('.arrow')[0];

                if (!arrow) {
                    arrow = document.createElement(menu.tagName.toLowerCase() === 'ul' ? 'li' : 'span');
                    arrow.classList.add('arrow');
                    arrow.setAttribute('data-helper-element', '');
                    menu.insertBefore(arrow, menu.firstChild);
                }

                config.modifiers.arrow = _.extend(config.modifiers.arrow || {}, {
                    element: arrow,
                    fn: function(data, options) {
                        if (this._checkKeepSeparately()) {
                            data.arrowStyles = _.extend({}, data.arrowStyles || {}, {
                                visibility: 'hidden'
                            });
                        }

                        return Popper.Defaults.modifiers.arrow.fn(data, options);
                    }.bind(this)
                });
            }

            if (_.result(config.modifiers, 'preventOverflow')) {
                const boundariesElement = config.modifiers.preventOverflow.boundariesElement;

                if (boundariesElement && ['scrollParent', 'window', 'viewport'].indexOf(boundariesElement) === -1) {
                    config.modifiers.preventOverflow.boundariesElement = $(this._element).closest(boundariesElement)[0];
                }

                config.modifiers.preventOverflow.escapeWithReference = true;
            }

            return config;
        },

        /**
         * Defined property `_inNavbar` is used only for
         *
         * @return {boolean}
         * @protected
         */
        _detectNavbar: function() {
            return original._detectNavbar.call(this) ||
                this._config.popper === false || // popper plugin is turned off intentionally
                $(this._element).closest('.app-header').length > 0; // app-header is considered as navbar as well
        },

        _displayArrow: function() {
            return _.isBoolean(this._config.displayArrow) ? this._config.displayArrow : config.displayArrow;
        },

        _checkKeepSeparately: function() {
            return _.isBoolean(this._config.keepSeparately) ? this._config.keepSeparately : config.keepSeparately;
        }
    });

    Dropdown._clearMenus = function(event) {
        if (event && event.type === 'click') {
            const $target = $(event.target);
            if ($target.closest('[data-toggle]').length && $target.closest('.dropdown-menu.show').length) {
                // click on toggle element inside active dropdown-menu
                return;
            }

            if ($target.closest('.dropdown-menu.show').length) {
                // Dropdown._clickEvent is defined only if the click occurred within some opened dropdown menu
                // original click event is used in the hide event handler
                Dropdown._clickEvent = event;
            }
        }

        _clearMenus(event);

        delete Dropdown._clickEvent;
    };

    function _events(names) {
        return names.map(function(name) {
            return name + EVENT_KEY + DATA_API_KEY;
        }).join(' ');
    }

    $(document)
        // replaced _clearMenus handler with custom one
        .off(_events(['click', 'keyup']), _clearMenus)
        .on(_events(['click', 'keyup', 'clearMenus']), Dropdown._clearMenus)

        // nested form click events are processed in _clearMenus method extend
        .off(_events(['click']), '.dropdown form')
        .on(_events(['disposeLayout']), function(event) {
            $('[data-toggle="dropdown"]', event.target).each(function() {
                const $toogler = $(this);
                if ($toogler.data('bs.dropdown')) {
                    $toogler.dropdown('dispose');
                }
            });
        });

    return Dropdown;
});
