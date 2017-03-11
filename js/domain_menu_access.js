/**
 * @file
 * A JavaScript file for the module.
 *
 */

(function (Drupal, $, debounce) {
  'use strict';
  Drupal.behaviors.domain_menu_access_add_all_affiliates_field = {
    attach: function (context, settings) {
      var form = $('#menu-link-content-menu-link-content-form', context);
      if (form.length > 0) {
        var domain_wrapper = $('div.field--name-domain-access', form),
          html_checkbox = '<div class="js-form-item form-item js-form-type-checkbox form-type-checkbox">'
            + '<input id="domain-access-all-affiliates" name="_domain_access_all_affiliates" value="" class="form-checkbox" type="checkbox" />'
            + '<label for="domain-access-all-affiliates" class="option">&nbsp;' + Drupal.t('Publish to all affiliates.') + '</label>'
            + '</div>';
        if (domain_wrapper.length > 0) {
          var fieldset = $('fieldset', domain_wrapper),
              checkboxes = fieldset.find('input[name^="domain_access"]');
          fieldset.prepend(html_checkbox);
          var checkbox = $('#domain-access-all-affiliates');
          checkbox.change(function () {
            if ($(this).prop('checked')) {
              checkboxes.prop('checked', false).parent().addClass('visually-hidden');
            }
            else {
              checkboxes.parent().removeClass('visually-hidden');
            }
          });
        }
      }
    }
  };
})(Drupal, jQuery, Drupal.debounce);
