define(function(require) {
    'use strict';

    const $ = require('jquery');
    const EmailAttachmentView = require('oroemail/js/app/views/email-attachment-view');
    const BaseCollectionView = require('oroui/js/app/views/base/collection-view');

    /**
     * @exports EmailAttachmentCollectionView
     */
    const EmailAttachmentCollectionView = BaseCollectionView.extend({
        itemView: EmailAttachmentView,

        listen: {
            'add collection': 'collectionAdd',
            'remove collection': 'collectionRemove'
        },

        /**
         * @inheritDoc
         */
        constructor: function EmailAttachmentCollectionView(options) {
            EmailAttachmentCollectionView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            BaseCollectionView.__super__.initialize.call(this, options);
            this.itemView = this.itemView.extend({// eslint-disable-line oro/named-constructor
                inputName: options.inputName,
                fileIcons: options.fileIcons,
                collectionView: this
            });

            this.listSelector = options.listSelector;
            $(this.listSelector).html('');

            this.$el.hide();
            this.showHideAttachmentRow();
        },

        collectionAdd: function(model) {
            if (!model.get('id') && !model.get('fileName')) {
                const itemView = this.getItemView(model);
                if (typeof itemView !== 'undefined') {
                    itemView.fileSelect();
                }
            } else {
                this.showHideAttachmentRow();
            }
        },

        collectionRemove: function() {
            const self = this;
            this.collection.each(function(model) {
                if (model && !model.get('type') && !model.get('id')) {
                    self.collection.remove(model);
                }
            });
            this.showHideAttachmentRow();
        },

        showHideAttachmentRow: function() {
            if (this.collection.isEmpty()) {
                this.hide();
            } else {
                this.show();
            }
        },

        show: function() {
            this.$el.show();
        },

        hide: function() {
            this.$el.hide();
        }
    });

    return EmailAttachmentCollectionView;
});
