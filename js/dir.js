
"use strict";

document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener('click', function (ev) {
    const target = ev.target.closest('[data-url]');
    if (!target) {
      return;
    }
    ev.stopImmediatePropagation();
    ev.preventDefault();

    const url = target.dataset.url;
    const options = {
      text: `{{ pfy-download-popup-text }}`,
    };

    pfyConfirm(options).then(
      function () {
        window.location.href = url;
      },
      function () {
        // user cancelled download
      }
    );
  });
});
