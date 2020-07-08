define(function(require) {
    'use strict';

    const _ = require('underscore');
    const BaseCollection = require('oroui/js/app/models/base/collection');
    const BaseCollectionView = require('oroui/js/app/views/base/collection-view');
    const PermissionReadOnlyView = require('orouser/js/datagrid/permission/permission-readonly-view');
    const ReadonlyFieldView = require('orouser/js/datagrid/action-permissions-readonly-field-view');
    const PermissionModel = require('orouser/js/models/role/permission-model');
    const BaseView = require('oroui/js/app/views/base/view');

    const ActionPermissionsReadonlyRowView = BaseView.extend({
        tagName: 'tr',

        className: 'grid-row collapsed',

        autoRender: false,

        animationDuration: 0,

        template: require('tpl-loader!orouser/templates/datagrid/action-permissions-row-view.html'),

        permissionItemView: PermissionReadOnlyView,

        fieldItemView: ReadonlyFieldView,

        /**
         * @inheritDoc
         */
        constructor: function ActionPermissionsReadonlyRowView(options) {
            ActionPermissionsReadonlyRowView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            ActionPermissionsReadonlyRowView.__super__.initialize.call(this, options);
            let fields = this.model.get('fields');
            if (typeof fields === 'string') {
                fields = JSON.parse(fields);
            }
            if (!_.isArray(fields)) {
                fields = _.values(fields);
            }
            if (fields.length) {
                _.each(fields, function(field) {
                    field.permissions = new BaseCollection(_.values(field.permissions), {
                        model: PermissionModel
                    });
                });
            }
            this.model.set('fields', fields, {silent: true});
        },

        render: function() {
            ActionPermissionsReadonlyRowView.__super__.render.call(this);
            const fields = this.model.get('fields');
            this.subview('permissions-items', new BaseCollectionView({
                el: this.$('[data-name=action-permissions-items]'),
                tagName: 'ul',
                className: 'action-permissions',
                animationDuration: 0,
                collection: this.model.get('permissions'),
                itemView: this.permissionItemView
            }));
            if (fields.length > 0) {
                this.subview('fields-list', new BaseCollectionView({
                    el: this.$('[data-name=fields-list]'),
                    collection: new BaseCollection(fields),
                    animationDuration: 0,
                    itemView: this.fieldItemView
                }));
            }
            return this;
        }
    });

    return ActionPermissionsReadonlyRowView;
});
