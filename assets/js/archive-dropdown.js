/* ChronoArchive: progressive enhancement for archive selectors. No jQuery. */
(function () {
  'use strict';
  document.addEventListener('change', function (event) {
    var select = event.target;
    if (!select || !select.matches || !select.matches('select.chronoarchive-select')) return;
    var value = select.value;
    if (!value) return;
    try {
      var url = new URL(value, window.location.href);
      if ((url.protocol === 'https:' || url.protocol === 'http:') && url.origin === window.location.origin) {
        window.location.assign(url.href);
      }
    } catch (error) {
      // Invalid values are ignored, never navigated to.
    }
  });
}());

