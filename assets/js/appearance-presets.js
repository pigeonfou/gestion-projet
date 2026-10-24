/* Instant, non-persistent preview for the account appearance selector. */
(function () {
  'use strict';
  var selector = document.querySelector('[data-preset-selector]');
  if (!selector) return;

  selector.addEventListener('change', function (event) {
    var input = event.target;
    if (!input || input.name !== 'ui_preset' || !input.checked) return;
    var preset = input.value;
    document.documentElement.setAttribute('data-preset', preset);
    if (document.body) document.body.setAttribute('data-preset', preset);
  });
}());
