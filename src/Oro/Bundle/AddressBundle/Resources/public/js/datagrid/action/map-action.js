define(function(require) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const GoogleMaps = require('oroaddress/js/mapservice/googlemaps');
    const ModelAction = require('oro/datagrid/action/model-action');

    const MapAction = ModelAction.extend({
        /**
         * @property {Object}
         */
        options: {
            mapView: GoogleMaps
        },

        /**
         * @property {Boolean}
         */
        dispatched: true,

        /**
         * @inheritDoc
         */
        constructor: function MapAction(options) {
            MapAction.__super__.constructor.call(this, options);
        },

        /**
        * @param {Object} options
        */
        initialize: function(options) {
            MapAction.__super__.initialize.call(this, options);

            this.$mapContainerFrame = $('<div class="map-popover__frame"/>');
            this.mapView = new this.options.mapView({
                el: this.$mapContainerFrame
            });

            this.datagrid.on('rendered', this.onGridRendered, this);
            this.datagrid.on('content:update', this.onGridRendered, this);
        },

        dispose: function() {
            if (this.disposed) {
                return;
            }
            delete this.$mapContainerFrame;
            this.datagrid.off(null, null, this);
            this.subviews[0].$el.off('click');
            MapAction.__super__.dispose.call(this);
        },

        onGridRendered: function() {
            if (!this.subviews.length) {
                return;
            }
            this.subviews[0].$el.on('click', _.bind(this.onActionClick, this));
        },

        /**
         * @param {jQuery.Event} e
         */
        onActionClick: function(e) {
            e.preventDefault();
            this.handlePopover(this.getPopoverConfig());
        },

        getPopoverConfig: function() {
            return _.extend({
                'placement': 'left',
                'container': 'body',
                'animation': false,
                'html': true,
                'closeButton': true,
                'class': 'map-popover',
                'content': this.$mapContainerFrame
            }, this.popoverTpl ? {template: this.popoverTpl} : {});
        },

        /**
         * @param {Object} config
         */
        handlePopover: function(config) {
            const $popoverTrigger = this.subviews[0].$el;

            $popoverTrigger.popover(config).on('shown.bs.popover', _.bind(function() {
                this.mapView.updateMap(this.getAddress(), this.model.get('label'));

                $(document).on('mouseup', _.bind(function(e) {
                    const $map = this.mapView.$el;
                    if (!$map.is(e.target) && !$map.has(e.target).length) {
                        $popoverTrigger.popover('dispose');
                    }
                }, this));
            }, this)).on('hidden.bs.popover', _.bind(function() {
                $(document).off('mouseup', null, this);
            }, this));

            $popoverTrigger.popover('show');
        },

        getAddress: function() {
            return this.model.get('countryName') + ', ' +
                this.model.get('city') + ', ' +
                this.model.get('street') + ' ' + (this.model.get('street2') || '');
        }
    });

    return MapAction;
});
