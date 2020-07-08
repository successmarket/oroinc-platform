define([
    'underscore',
    './header-cell/header-cell',
    'chaplin',
    '../app/components/column-renderer-component',
    './util'
], function(_, HeaderCell, Chaplin, ColumnRendererComponent, util) {
    'use strict';

    const HeaderRow = Chaplin.CollectionView.extend({
        tagName: 'tr',

        className: '',

        animationDuration: 0,

        /* Required fby current realization of grid.js, see header initialization code */
        autoRender: true,

        themeOptions: {
            optionPrefix: 'headerRow',
            className: 'grid-header-row'
        },

        /**
         * @inheritDoc
         */
        constructor: function HeaderRow(options) {
            HeaderRow.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            this.columns = options.columns;
            this.dataCollection = options.dataCollection;

            // itemView function is called as new this.itemView
            // it is placed here to pass THIS within closure
            const _this = this;
            _.extend(this, _.pick(options, ['themeOptions', 'template']));
            // let descendants override itemView
            if (!this.itemView) {
                this.itemView = function(options) {
                    const column = options.model;
                    const CurrentHeaderCell = column.get('headerCell') || options.headerCell || HeaderCell;
                    const cellOptions = {
                        column: column,
                        collection: _this.dataCollection,
                        themeOptions: {
                            className: 'grid-cell grid-header-cell'
                        }
                    };
                    if (column.get('name')) {
                        cellOptions.themeOptions.className += ' grid-header-cell-' + column.get('name');
                    }
                    _this.columns.trigger('configureInitializeOptions', CurrentHeaderCell, cellOptions);
                    return new CurrentHeaderCell(cellOptions);
                };
            }

            this.columnRenderer = new ColumnRendererComponent(options);

            HeaderRow.__super__.initialize.call(this, options);
            this.cells = this.subviews;
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            delete this.cells;
            delete this.columns;
            delete this.dataCollection;
            HeaderRow.__super__.dispose.call(this);
        },

        render: function() {
            this._deferredRender();
            if (this.template) {
                this.renderCustomTemplate();
            } else {
                HeaderRow.__super__.render.call(this);
            }
            this._resolveDeferredRender();

            return this;
        },

        renderCustomTemplate: function() {
            const self = this;
            this.$el.html(this.template({
                themeOptions: this.themeOptions ? this.themeOptions : {},
                render: function(columnName) {
                    const columnModel = _.find(self.columns.models, function(model) {
                        return model.get('name') === columnName;
                    });
                    if (columnModel) {
                        return self.columnRenderer.getHtml(self.renderItem(columnModel).$el);
                    }
                    return '';
                },
                attributes: function(columnName, additionalAttributes) {
                    const attributes = additionalAttributes || {};
                    const columnModel = _.find(self.columns.models, function(model) {
                        return model.get('name') === columnName;
                    });
                    if (columnModel) {
                        attributes.id = columnModel.get('name');
                        return self.columnRenderer.getRawAttributes(self.renderItem(columnModel).$el, attributes);
                    }
                    return '';
                }
            }));

            _.each(this.getItemViews(), function(view) {
                view.setElement(this.$('#' + view.column.get('name')));
                view.$el.attr('id', null);
            }, this);

            return this;
        }
    });

    return HeaderRow;
});
