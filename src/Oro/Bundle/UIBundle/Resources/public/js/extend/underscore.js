define(['underscore', 'asap'], function(_, asap) {
    'use strict';

    _.mixin({
        nl2br: function(str) {
            const breakTag = '<br />';
            return String(str).replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
        },

        trunc: function(str, maxLength, useWordBoundary, hellip) {
            hellip = hellip || '&hellip;';
            const toLong = str.length > maxLength;
            str = toLong ? str.substr(0, maxLength - 1) : str;
            const lastSpace = str.lastIndexOf(' ');
            str = useWordBoundary && toLong && lastSpace > 0 ? str.substr(0, lastSpace) : str;
            return toLong ? str + hellip : str;
        },

        isMobile: function() {
            const elem = document.getElementsByTagName('body')[0];
            return elem && (' ' + elem.className + ' ')
                .replace(/[\t\r\n\f]/g, ' ')
                .indexOf(' mobile-version ') !== -1;
        },

        isTouchDevice() {
            return (('ontouchstart' in window) || (navigator.MaxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0));
        },

        isDesktop: function() {
            return !this.isMobile();
        },

        isRTL: function() {
            return document.getElementsByTagName('html')[0].getAttribute('dir') === 'rtl';
        },

        trim: function(text) {
            return text.replace(/^\s*/, '').replace(/\s*$/, '');
        },

        capitalize: function(text) {
            return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
        },

        /**
         * Compares two arrays if they have the same set of elements
         *
         * @param firstArray {Array}
         * @param secondArray {Array}
         * @returns {boolean}
         */
        haveEqualSet: function(firstArray, secondArray) {
            return firstArray.length === secondArray.length && _.difference(firstArray, secondArray).length === 0;
        },

        /**
         * The Number.isSafeInteger() method determines whether the provided value is a number that is a safe integer.
         * @param number {testValue}
         * @return {boolean}
         */
        isSafeInteger: function(number) {
            // 0. Try use native API
            if (Number.isSafeInteger) {
                return Number.isSafeInteger(number);
            }

            // 1. If Type(number) is not Number, return false.
            if (typeof(number) !== 'number') {
                return false;
            }
            // 2. If number is NaN, +∞, or -∞, return false.
            if (this.isNaN(number) || number === Infinity || number === -Infinity) {
                return false;
            }
            // 3. Let integer.
            const integer = parseInt(number);
            // 4. If integer is not equal to number, return false.
            if (integer !== number) {
                return false;
            }
            // 5. If abs(integer) ≤ 2^53-1, return true.
            if (Math.abs(integer) <= (Math.pow(2, 53) - 1)) {
                return true;
            }
            // 6. Otherwise, return false.
            return false;
        }
    });

    _.templateSettings.innerTempStart = '<%#';
    _.templateSettings.innerTempEnd = '#%>';

    _.template = _.wrap(_.template, function(original, text, settings, oldSettings) {
        const args = [text, settings, oldSettings];
        if (!settings && oldSettings) {
            settings = oldSettings;
        }
        settings = _.defaults({}, settings, _.templateSettings);

        const regexStart = new RegExp('^' + settings.innerTempStart);
        const regexEnd = new RegExp(settings.innerTempEnd + '$');
        const evaluateStart = '(' + _.templateSettings.innerTempStart + ')';
        const evaluateEnd = '|(' + _.templateSettings.innerTempEnd + ')';

        const innerTempEvaluate = new RegExp(evaluateStart + evaluateEnd, 'g');

        text = _.trim(text).replace(regexStart, '').replace(regexEnd, '');

        let escapedText = text;

        const levelOffsets = {};
        let level = 0;
        let offsetDelta = 0;

        const escapeText = function(text) {
            return text.replace(/&lt;%/g, '&amp;lt;%').replace(/<%/g, '&lt;%').replace(/%>/g, '%&gt;');
        };

        text.replace(innerTempEvaluate, function(match, open, close, offset) {
            offset += offsetDelta;
            if (open) {
                level++;
                levelOffsets[level] = offset;
            }
            if (close && level) {
                const start = escapedText.slice(0, levelOffsets[level]);
                const end = escapedText.slice(offset + close.length);
                const escape = escapedText.slice(levelOffsets[level] + settings.innerTempStart.length, offset);
                const newEscape = escapeText(escape);

                offsetDelta += newEscape.length - escape.length - (settings.innerTempEnd.length * 2);
                escapedText = start + newEscape + end;
                level--;
            }

            // Adobe VMs need the match returned to produce the correct offset.
            return match;
        });
        args[0] = _.trim(escapedText);
        const func = original.apply(this, args);
        func._source = args[0];
        return func;
    });

    _.defer = asap;

    return _;
});
