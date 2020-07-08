define(['underscore', 'backbone'
], function(_, Backbone) {
    'use strict';

    /**
     * @export  oroentity/js/entity-field-view
     * @class   oroentity.EntityFieldView
     * @extends Backbone.View
     */
    const EntityFeildView = Backbone.View.extend({
        /** @property {Object} */
        options: {
            fieldsLabel: null,
            relatedLabel: null,
            findEntity: null,
            exclude: undefined // function (criteria) { return true/false }
        },

        /** @property {Object} */
        util: null,

        /**
         * @inheritDoc
         */
        constructor: function EntityFeildView(options) {
            EntityFeildView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);
            this.util = this.$el.data('entity-field-util');
            this.util.fieldsLabel = this.options.fieldsLabel;
            this.util.relatedLabel = this.options.relatedLabel;
            if (!_.isNull(this.options.findEntity)) {
                this.util.findEntity = this.options.findEntity;
            }
            if (!_.isUndefined(this.options.exclude)) {
                this.util.exclude = this.options.exclude;
                this.util.filterData();
            }
        },

        getEntityName: function() {
            return this.util.getEntityName();
        },

        changeEntity: function(entityName, fields) {
            this.util.changeEntity(entityName, fields);
        },

        splitFieldId: function(fieldId) {
            return this.util.splitFieldId(fieldId);
        },

        getFieldData: function(fieldId) {
            return this.util.getFieldData(fieldId);
        },

        getFieldApplicableConditions: function(fieldId) {
            if (_.isNull(fieldId) || fieldId === '') {
                return {};
            }

            const result = {
                parent_entity: null,
                entity: this.getEntityName(),
                field: fieldId
            };
            const chain = result.field.split('+');
            if (_.size(chain) > 1) {
                const pair = _.last(chain).split('::');
                result.parent_entity = result.entity;
                result.entity = pair[_.size(pair) - 2];
                result.field = _.last(pair);
                if (_.size(chain) > 2) {
                    const parentField = chain[_.size(chain) - 2].split('::');
                    result.parent_entity = parentField[_.size(parentField) - 2];
                }
            }
            _.extend(result, _.pick(this.getFieldData(fieldId), ['type', 'identifier']));
            return result;
        }
    });

    return EntityFeildView;
});
