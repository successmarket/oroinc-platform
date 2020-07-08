define(function(require) {
    'use strict';

    const $ = require('jquery');
    const BaseView = require('oroui/js/app/views/base/view');

    const AbstractActionView = BaseView.extend({
        /**
         * @property {Object}
         */
        options: {
            $tree: '',
            action: '',
            template: require('tpl-loader!oroui/templates/jstree-action.html'),
            icon: '',
            label: ''
        },

        /**
         * @inheritDoc
         */
        events: {
            'click [data-role="jstree-action"]': 'onClick'
        },

        /**
         * @inheritDoc
         */
        constructor: function AbstractActionView(options) {
            AbstractActionView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            this.options = $.extend(true, {}, this.options, options);
            AbstractActionView.__super__.initialize.call(this, options);
        },

        /**
         * @inheritDoc
         */
        render: function() {
            const $el = $(this.options.template(this.options));
            if (this.$el) {
                this.$el.replaceWith($el);
            }
            this.setElement($el);
            return this;
        },

        onClick: function() {},

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }

            delete this.options;
            AbstractActionView.__super__.dispose.call(this);
        }
    });

    return AbstractActionView;
});
