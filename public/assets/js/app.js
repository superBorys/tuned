'use strict';

// Define the `playerApp` module
angular.module('playerApp', [
        //'ngRoute',
        'playerApp.player'
    ])

    .config(['$httpProvider', '$locationProvider', '$sceProvider',
        function config($httpProvider, $locationProvider, $sceProvider) {
            $locationProvider.html5Mode({
                enabled: true,
                requireBase: false,
                rewriteLinks: false
            });
            $sceProvider.enabled(false);
        }
    ])

    .run(['$rootScope', '$location', function($rootScope, $location){

    }]);