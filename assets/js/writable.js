class WritableWidget {
  constructor() {
    this.writableInputEl = null;
    this.boundHandleOutsideClick = this.handleOutsideWriteableClick.bind(this);
    this.init();
  } // constructor


  // Initialize writable widgets
  init() {
    document.addEventListener('DOMContentLoaded', () => {
      this.initWritable();
      this.initWritableAutoGrow();
    });
  } // init


  // Initialize writable input fields and text areas
  initWritable() {
    domForEach('.pfy-writable-widget-wrapper', (wrapperEl) => {
      domForEach(wrapperEl, 'input,textarea', (inputEl) => {
        this.setupInputListeners(inputEl);
      });
    });
  } // initWritable


  // Set up input field listeners
  setupInputListeners(inputEl) {
    let saveToHost = true;
    let prevValue = '';

    inputEl.addEventListener('keyup', (ev) => {
      if (ev.key === 'Escape') {
        saveToHost = false;
        inputEl.value = prevValue;
        ev.target.blur();
      }
      if ((inputEl.tagName === 'INPUT') && (ev.key === 'Enter')) {
        ev.target.blur();
      }
    });

    inputEl.addEventListener('focus', () => {
      prevValue = inputEl.value;
      saveToHost = true;
      this.writableInputEl = inputEl;
      setTimeout(() => {
        document.body.addEventListener('click', this.boundHandleOutsideClick, { once: true, passive: true, capture: true });
      }, 100);
    });

    inputEl.addEventListener('change', () => {
      if (!saveToHost) {
        return;
      }
      const value = encodeURIComponent(inputEl.value);
      const name = inputEl.name;
      const inpWrapper = inputEl.closest('.pfy-writable-widget-wrapper');
      const dataSrcInx = inpWrapper.dataset.writableGroup;

      const cmd = `?ajax&writable&datasrcinx=${dataSrcInx}&name=${name}&value=${value}`;
      execAjaxPromise(cmd).then((data) => {
        if (typeof data === 'object' && data[name] !== undefined) {
          inputEl.value = data[name];
        }
      });
    });
  } // setupInputListeners


  // Handle clicks outside writable inputs
  handleOutsideWriteableClick(ev) {
    if (!this.writableInputEl) {
      return;
    }
    if (this.writableInputEl !== ev.target) {
      this.writableInputEl.blur();
    }
    this.writableInputEl = null;
  } // handleOutsideWriteableClick


  // Initialize auto-growing text areas
  initWritableAutoGrow() {
    document.querySelectorAll('.pfy-auto-grow').forEach((grower) => {
      domForOne(grower,'textarea', textareaEl => {
        grower.dataset.replicatedValue = textareaEl.value;
        textareaEl.addEventListener('input', () => {
          grower.dataset.replicatedValue = textareaEl.value;
        });
      })
    });
  } // initWritableAutoGrow

} // WritableWidget


// Instantiate the class to initialize
new WritableWidget();
