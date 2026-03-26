/**
 * @file
 * Show/hide date sub-inputs based on the selected accuracy level.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Maps accuracy values to their wrapper CSS classes.
   */
  var accuracyWrapperMap = {
    exact: 'date-ish-exact-wrapper',
    month: 'date-ish-month-wrapper',
    quarter: 'date-ish-quarter-wrapper',
    year_half: 'date-ish-year-half-wrapper',
    year: 'date-ish-year-wrapper'
  };

  /**
   * All wrapper classes managed by this widget.
   */
  var allWrapperClasses = Object.values(accuracyWrapperMap);

  /**
   * Toggles wrapper visibility within a fieldset based on the selected accuracy.
   *
   * @param {HTMLSelectElement} select - The accuracy select element.
   */
  function toggleWrappers(select) {
    var fieldset = select.closest('fieldset') || select.parentNode;
    var activeClass = accuracyWrapperMap[select.value];

    allWrapperClasses.forEach(function (cls) {
      var wrapper = fieldset.querySelector('.' + cls);
      if (wrapper) {
        wrapper.style.display = (cls === activeClass) ? '' : 'none';
      }
    });
  }

  Drupal.behaviors.dateIshWidget = {
    attach: function (context) {
      var selects = once('date-ish-widget', '.date-ish-accuracy', context);

      selects.forEach(function (select) {
        // Set initial visibility.
        toggleWrappers(select);

        // Update on change.
        select.addEventListener('change', function () {
          toggleWrappers(select);
        });
      });
    }
  };

})(Drupal, once);
