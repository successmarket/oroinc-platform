define(function(require) {
    'use strict';

    require('npmassets/Base64/base64');

    return {
        encode: function(stringToEncode) {
            return window.btoa(stringToEncode);
        },
        decode: function(encodedData) {
            return window.atob(encodedData);
        }
    };
});
