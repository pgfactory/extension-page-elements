
window.onload = function() {
  document.addEventListener('click', function (ev) {
    const target = ev.target.closest('[data-url]');
    if (!target) {
      return;
    }
    ev.stopImmediatePropagation();
    ev.preventDefault();
    let url = target.dataset.url;
    url = encodeURI(url);
    console.log(url);
    const options = {
      header: `{{ pfy-download-popup-header }}`,
      text: `{{ pfy-download-popup-text }}`,
    };

    pfyConfirm(options).then(
      () => {
        console.log('Confirmed, continue downloading');
        document.location.href = url;
        }
    );
  });
}
