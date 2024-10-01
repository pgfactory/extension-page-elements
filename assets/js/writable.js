class WritableWidget {
  constructor() {
    this.writableInputEl = null;
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
      // Handle input fields
      domForEach(wrapperEl, 'input,textarea', (inputEl) => {
        this.setupInputListeners(inputEl);
      });
    });
  } // initWritable


  // Set up input field listeners
  setupInputListeners(inputEl) {
    let saveToHost = true;
    let prevValue = '';

    // init key listener:
    inputEl.addEventListener('keyup', (ev) => {
      // handle ESC:
      if (ev.key === 'Escape') {
        saveToHost = false;
        textareaEl.value = prevValue;
        ev.target.blur();
      }
      // handle Enter, unless in textarea:
      if ((inputEl.tagName === 'INPUT') && (ev.key === 'Enter')) {
        ev.target.blur();
      }
    });

    // handler when writable field gets focus:
    inputEl.addEventListener('focus', (ev) => {
      prevValue = inputEl.value;
      this.writableInputEl = inputEl;
      setTimeout(() => {
        document.querySelector('body').addEventListener('click', this.handleOutsideWriteableClick.bind(this), { once: true, passive: true, capture: true });
      }, 100);
    });

    // handler when writable field is changed -> send to host:
    inputEl.addEventListener('change', (ev) => {
      if (!saveToHost) {
        mylog('writable-widget: not saving to host');
        return;
      }
      const value = encodeURIComponent(inputEl.value);
      const name = inputEl.name;
      const inpWrapper = inputEl.closest('.pfy-writable-widget-wrapper');
      const dataSrcInx = inpWrapper.dataset.writableGroup;
      mylog(`${name}: ${value} (${dataSrcInx})`);

      let cmd = `?ajax&writable&datasrcinx=${dataSrcInx}&name=${name}&value=${value}`;
      execAjaxPromise(cmd).then((data) => {
        if (typeof data === 'object' && data[name] !== undefined) {
          mylog(`storing writable done: "${data[name]}"`);
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
    if (this.writableInputEl === ev.target) {
      this.writableInputEl = null;
      return;
    }
    this.writableInputEl.blur();
    this.writableInputEl = null;
    document.querySelector('body').removeEventListener('click', this.handleOutsideWriteableClick.bind(this), { passive: true });
  } // handleOutsideWriteableClick


  // Initialize auto-growing text areas
  initWritableAutoGrow() {
    const growers = document.querySelectorAll('.pfy-auto-grow');
    if (growers) {
      growers.forEach((grower) => {
        const textarea = grower.querySelector('textarea');
        grower.dataset.replicatedValue = textarea.value;
        textarea.addEventListener('input', () => {
          grower.dataset.replicatedValue = textarea.value;
        });
      });
    }
  } // initWritableAutoGrow

} // WritableWidget


// Instantiate the class to initialize
new WritableWidget();
