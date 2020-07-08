define(function(require) {
    'use strict';

    const $ = require('jquery');
    const mask = require('oroui/js/dropdown-mask');
    require('jquery-ui');
    require('oroui/js/extend/jquery.datepicker.extend');

    $(document).off('select2-open.dropdown.data-api').on('select2-open.dropdown.data-api', function() {
        if ($.datepicker._curInst && $.datepicker._datepickerShowing && !($.datepicker._inDialog && $.blockUI)) {
            $.datepicker._hideDatepicker();
        }
    });
    $(document)
        .on('datepicker:dialogShow', function(e) {
            const $input = $(e.target);
            const zIndex = $.datepicker._getInst(e.target).dpDiv.css('zIndex');
            mask.show(zIndex - 1)
                .onhide(function() {
                    $input.datepicker('hide');
                });
        })
        .on('datepicker:dialogHide', function(e) {
            mask.hide();
        });
    /* datepicker extend:end */

    /* dialog extend:start*/
    (function() {
        const oldMoveToTop = $.ui.dialog.prototype._moveToTop;
        $.widget('ui.dialog', $.ui.dialog, {
            /**
             * Replace method because some browsers return string 'auto' if property z-index not specified.
             * */
            _moveToTop: function() {
                const zIndex = this.uiDialog.css('z-index');
                const numberRegexp = /^\d+$/;
                if (typeof zIndex === 'string' && !numberRegexp.test(zIndex)) {
                    this.uiDialog.css('z-index', 910);
                }
                oldMoveToTop.call(this);
            },

            _title: function(title) {
                title.html(
                    $('<span/>', {'class': 'ui-dialog-title__inner'}).text(this.options.title)
                );
            }
        });
    }());
    /* dialog extend:end*/

    /* sortable extend:start*/
    (function() {
        let touchHandled;

        /**
         * Simulate a mouse event based on a corresponding touch event
         * @param {Object} event A touch event
         * @param {String} simulatedType The corresponding mouse event
         */
        function simulateMouseEvent(event, simulatedType) {
            // Ignore multi-touch events
            if (event.originalEvent.touches.length > 1) {
                return;
            }

            // event.preventDefault();

            const touch = event.originalEvent.changedTouches[0];

            // Initialize the simulated mouse event using the touch event's coordinates
            const simulatedEvent = new MouseEvent(simulatedType, {
                bubbles: true,
                cancelable: true,
                view: window,
                detail: 1,
                screenX: touch.screenX,
                screenY: touch.screenY,
                clientX: touch.clientX,
                clientY: touch.clientY,
                ctrlKey: false,
                altKey: false,
                shiftKey: false,
                metaKey: false,
                button: 0,
                relatedTarget: null
            });

            // Dispatch the simulated event to the target element
            event.target.dispatchEvent(simulatedEvent);
        }

        $.widget('ui.sortable', $.ui.sortable, {
            /**
             * Handle the jQuery UI widget's touchstart events
             * @param {Object} event The widget element's touchstart event
             */
            _touchStart: function(event) {
                // Ignore the event if another widget is already being handled
                if (touchHandled || !this._mouseCapture(event.originalEvent.changedTouches[0])) {
                    return;
                }

                event.stopPropagation();
                event.preventDefault();

                // Set the flag to prevent other widgets from inheriting the touch event
                touchHandled = true;

                // Simulate the mousedown event
                simulateMouseEvent(event, 'mousedown');
            },

            /**
             * Handle the jQuery UI widget's touchmove events
             * @param {Object} event The document's touchmove event
             */
            _touchMove: function(event) {
                // Ignore event if not handled
                if (!touchHandled) {
                    return;
                }

                event.preventDefault();

                // Simulate the mousemove event
                simulateMouseEvent(event, 'mousemove');
            },

            /**
             * Handle the jQuery UI widget's touchend events
             * @param {Object} event The document's touchend event
             */
            _touchEnd: function(event) {
                // Ignore event if not handled
                if (!touchHandled) {
                    return;
                }

                event.stopPropagation();
                event.preventDefault();

                // Simulate the mouseup event

                simulateMouseEvent(event, 'mouseup');
                // Unset the flag to allow other widgets to inherit the touch event
                touchHandled = false;

                return true;
            },

            /**
             * Method _mouseInit extends $.ui.mouse widget with bound touch event handlers that
             * translate touch events to mouse events and pass them to the widget's
             * original mouse event handling methods.
             */
            _mouseInit: function(...args) {
                // Delegate the touch handlers to the widget's element
                const handlers = {
                    touchstart: this._touchStart.bind(this),
                    touchmove: this._touchMove.bind(this),
                    touchend: this._touchEnd.bind(this)
                };

                Object.keys(handlers).forEach(function(eventName) {
                    handlers[eventName + '.' + this.widgetName] = handlers[eventName];
                    delete handlers[eventName];
                }.bind(this));

                this.element.on(handlers);

                this._touchMoved = false;

                this._superApply(args);
            },

            /**
             * Faster and rough handle class setting method
             */
            _setHandleClassName: function() {
                this._removeClass(this.element.find('.ui-sortable-handle'), 'ui-sortable-handle');

                this._addClass(
                    this.options.handle ? this.element.find(this.options.handle) : $($.map(this.items, function(item) {
                        return item.item.get(0);
                    })),
                    'ui-sortable-handle'
                );
            }
        });
    }());
    /* sortable extend:end*/
});
