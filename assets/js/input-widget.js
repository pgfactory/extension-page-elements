// === input widget

domForEach('.pfy-input-widget', function(wrapperEl) {
  domForOne(wrapperEl, 'input', function(el) {
    let saveToHost = true;
    let prevValue = '';
    el.addEventListener('keyup', function(ev) {
      if (ev.key === 'Escape') {
        saveToHost = false;
        el.value = prevValue;
        ev.target.blur();
      } else if (ev.key === 'Enter') {
        ev.target.blur();
      }
    });
    el.addEventListener('focus', function(ev) {
      prevValue = el.value;
    });
    el.addEventListener('change', function(ev) {
      if (!saveToHost) {
        mylog('input-widget: not saving to host');
        return;
      }
      const el = ev.target;
      const value = el.value;
      const name = el.name;
      const inpWrapper = el.closest('.pfy-input-widget-wrapper');
      const dataSrcInx = inpWrapper.dataset.inputGroup;
      mylog(`${name}: ${value} (${dataSrcInx})`);
      let cmd = '?ajax&input&datasrcinx=' + dataSrcInx;
      cmd += '&name=' + name + '&value=' + value;
      execAjaxPromise(cmd)
        .then(function (data) {
          mylog('store input done: ' + data);
        });
    });
  });
});
