/**
 * @file
 * Obiba Agate Module AngularJs App Service.
 */
(function ($) {
'use strict';

      mica.agateRegister.factory('UserResourceJoin', ['$resource',
          function ($resource) {
          return $resource(Drupal.settings.basePath + Drupal.settings.pathPrefix + 'agate/agate_user_join/ws',
            {},
            {
              'post': {
                method: 'POST',
                errorHandler: true,
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                transformRequest: function (data){
                  return $.param(data);
                }
              }
            })
          }])
        .factory('AgateJoinFormResource', ['$resource',
          function ($resource) {
            return $resource(Drupal.settings.basePath + Drupal.settings.pathPrefix + 'agate/agate-form/ws' + (Drupal.settings.confCleanPath?'?':'&') + 'locale=' + Drupal.settings.angularjsApp.locale, {}, {
              'get': {
                method: 'GET', errorHandler: true, params: {locale: Drupal.settings.angularjsApp.locale}
              }
            });
          }])
        .factory('OidcProvidersResource', ['$resource',
          function ($resource) {
            return $resource(Drupal.settings.basePath + Drupal.settings.pathPrefix + 'agate/auth/providers/ws', {locale: '@locale'}, {
              'get': { method: 'GET', errorHandler: true, isArray: true }
            });
          }]);

}(jQuery));