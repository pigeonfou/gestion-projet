/* Run synchronously in <head>, before styles and before the first paint. */
(function () {
  'use strict';
  var root = document.documentElement;
  var preference = root.getAttribute('data-theme');
  var system = window.matchMedia('(prefers-color-scheme: dark)');
  function apply() {
    var dark = preference === 'dark' || (preference === 'system' && system.matches);
    root.setAttribute('data-color-scheme', dark ? 'dark' : 'light');
  }
  apply();
  if (preference === 'system') {
    if (system.addEventListener) system.addEventListener('change', apply);
    else if (system.addListener) system.addListener(apply);
  }
}());
