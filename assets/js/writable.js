// === writable widget

var writableInputEl = null;
function initWritable() {
  domForEach('.pfy-writable-widget-wrapper', function (wrapperEl) {

    // case input field:
    domForEach(wrapperEl, 'input', function (inputEl) {
      let saveToHost = true;
      let prevValue = '';
      inputEl.addEventListener('keyup', function (ev) {
        if (ev.key === 'Escape') {
          saveToHost = false;
          inputEl.value = prevValue;
          ev.target.blur();
        } else if (ev.key === 'Enter') {
          ev.target.blur();
        }
      });

      inputEl.addEventListener('focus', function (ev) {
        prevValue = inputEl.value;

        // set up outside click to terminate writable input:
        writableInputEl = inputEl;
        setTimeout(function () {
          //mylog('setting up click trigger');
          document.querySelector('body').addEventListener('click', handleOutsideWriteableClick);
        }, 100, {once: true, passive: true, capture: true});
      });

      inputEl.addEventListener('change', function (ev) {
        if (!saveToHost) {
          mylog('writable-widget: not saving to host');
          return;
        }
        let value = inputEl.value;
        value = encodeURIComponent(value);
        const name = inputEl.name;
        const inpWrapper = inputEl.closest('.pfy-writable-widget-wrapper');
        const dataSrcInx = inpWrapper.dataset.writableGroup;
        mylog(`${name}: ${value} (${dataSrcInx})`);
        let cmd = '?ajax&writable&datasrcinx=' + dataSrcInx;
        cmd += '&name=' + name + '&value=' + value;
        execAjaxPromise(cmd)
          .then(function (data) {
            if (typeof data === 'object' && typeof data[name] !== 'undefined') {
              mylog('storing writable done: "' + data[name] + '"');
              inputEl.value = data[name];
            }
          });
      });
    });

    // case textarea:
    domForEach(wrapperEl, 'textarea', function (textareaEl) {
      let saveToHost = true;
      let prevValue = '';

      textareaEl.addEventListener('keyup', function (ev) {
        if (ev.key === 'Escape') {
          saveToHost = false;
          textareaEl.value = prevValue;
          ev.target.blur();
        }
      });

      textareaEl.addEventListener('focus', function (ev) {
        prevValue = textareaEl.value;

        // set up outside click to terminate writable input:
        writableInputEl = textareaEl;
        setTimeout(function () {
          //mylog('setting up click trigger');
          document.querySelector('body').addEventListener('click', handleOutsideWriteableClick);
        }, 100, {once: true, passive: true, capture: true});
      });

      textareaEl.addEventListener('change', function (ev) {
        if (!saveToHost) {
          mylog('writable-widget: not saving to host');
          return;
        }
        const textareaEl = ev.target;
        let value = textareaEl.value;
        value = encodeURIComponent(value);
        const name = textareaEl.name;
        const inpWrapper = textareaEl.closest('.pfy-writable-widget-wrapper');
        const dataSrcInx = inpWrapper.dataset.writableGroup;
        mylog(`${name}: ${value} (${dataSrcInx})`);
        let cmd = '?ajax&writable&datasrcinx=' + dataSrcInx;
        cmd += '&name=' + name + '&value=' + value;
        execAjaxPromise(cmd)
          .then(function (data) {
            if (typeof data === 'object') {
              mylog('storing writable done: "' + data[name] + '"');
              textareaEl.value = data[name];
            }
          });
      });
    });
  });
} // initWritable


function handleOutsideWriteableClick(ev) {
  //mylog('handleOutsideWriteableClick');
  if (!writableInputEl) {
    return;
  }
  if (writableInputEl === ev.target) {
    writableInputEl = null;
    return;
  }
  writableInputEl.blur();
  writableInputEl = null;
  document.querySelector('body').removeEventListener('click', handleOutsideWriteableClick, { passive: true });
} // handleOutsideWriteableClick



function initWritableAutoGrow() {
  // source: https://css-tricks.com/the-cleanest-trick-for-autogrowing-textareas/
  const growers = document.querySelectorAll(".pfy-auto-grow");
  if (growers) {
    growers.forEach((grower) => {
      const textarea = grower.querySelector("textarea");
      grower.dataset.replicatedValue = textarea.value; // preset grower
      textarea.addEventListener("input", () => {
        grower.dataset.replicatedValue = textarea.value;
      });
    });
  }
} // initWritableAutoGrow


// init:
document.addEventListener('DOMContentLoaded', function () {
  initWritable();
  initWritableAutoGrow();
})
