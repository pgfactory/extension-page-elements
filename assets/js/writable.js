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
          mylog('triggering click');
          document.querySelector('body').addEventListener('click', handleOutsideWriteableClick);
        }, 100);
      });

      inputEl.addEventListener('change', function (ev) {
        if (!saveToHost) {
          mylog('writable-widget: not saving to host');
          return;
        }
        const value = inputEl.value;
        const name = inputEl.name;
        const inpWrapper = inputEl.closest('.pfy-writable-widget-wrapper');
        const dataSrcInx = inpWrapper.dataset.writableGroup;
        mylog(`${name}: ${value} (${dataSrcInx})`);
        let cmd = '?ajax&writable&datasrcinx=' + dataSrcInx;
        cmd += '&name=' + name + '&value=' + value;
        execAjaxPromise(cmd)
          .then(function (data) {
            mylog('storing writable done: ' + data[name]);
            inputEl.value = data[name];
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
          mylog('triggering click');
          document.querySelector('body').addEventListener('click', handleOutsideWriteableClick);
        }, 100);
      });

      textareaEl.addEventListener('change', function (ev) {
        if (!saveToHost) {
          mylog('writable-widget: not saving to host');
          return;
        }
        const textareaEl = ev.target;
        let value = textareaEl.value;
        value = encodeURI(value);
        const name = textareaEl.name;
        const inpWrapper = textareaEl.closest('.pfy-writable-widget-wrapper');
        const dataSrcInx = inpWrapper.dataset.writableGroup;
        mylog(`${name}: ${value} (${dataSrcInx})`);
        let cmd = '?ajax&writable&datasrcinx=' + dataSrcInx;
        cmd += '&name=' + name + '&value=' + value;
        execAjaxPromise(cmd)
          .then(function (data) {
            mylog('storing writable done: ' + data[name]);
            textareaEl.value = data[name];
          });
      });
    });
  });
} // initWritable


function handleOutsideWriteableClick(ev) {
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
