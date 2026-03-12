/*
* pfyPopup
*
* Usage:
  pfyConfirm('Continue...?').then(
    function(value) {
      console.log('Ok');
    },
    function(error) {
      console.log('Canceled');
    }
  );
*/

"use strict";


class PfyPopup {
  static instanceCounter = 0;
  static globalTriggersInitialized = false;

  constructor(options) {
    this.popupInstance = PfyPopup.instanceCounter++;
    this.inhibitClosing = false;
    this.triggerInitialized = false;
    this.triggerElem = null;
    this.popup = null;
    this.id = '';
    this.buttonHtml = '';

    this.parseArgs(options);
    this.setupGlobalTriggers();
    this.setupOpenTrigger();
  }


  parseArgs(options) {
    if (typeof options === 'string') {
      options = { text: options };
    }
    this.options = options;
    this.initialOpacity = options.initialOpacity || 1;
    this.text = options.text || '';
    this.content = options.content || this.text;
    this.contentFrom = options.contentFrom || '';
    this.modal = options.modal ?? true;

    this.header = options.header ?? false;
    if (this.header === '' || this.header === true) {
      this.header = '';
    }
    this.draggable = options.draggable || (this.header !== false);
    if (this.draggable && this.header === false) {
      this.header = '';
    }

    this.trigger = options.triggerSource || options.trigger || true;
    this.triggerEvent = options.triggerEvent || 'click';
    this.anker = options.anker || 'body';
    this.closeOnBgClick = options.closeOnBgClick ?? true;
    this.closeButton = options.closeButton ?? true;

    this.buttons = (typeof options.buttons === 'string' && options.buttons)
      ? options.buttons.split(/\s*,\s*/)
      : [];

    // omit closeButton if buttons are defined (unless header is active):
    if (options.closeButton === undefined && this.buttons.length && !this.header) {
      this.closeButton = false;
    }

    this.callbackArg = options.callbackArg || '';
    this.onConfirm = options.onConfirm || '';
    this.onCancel = options.onCancel || '';
    this.onOk = options.onOk || '';
    this.onContinue = options.onContinue || '';
    this.onClose = options.onClose || '';
    this.onOpen = options.onOpen || '';
    this.autofocus = options.autofocus || false;
    this.containerClass = options.containerClass ? ' ' + options.containerClass : '';

    this.popupClass = options.class || options.popupClass || '';
    if (this.closeButton) {
      this.popupClass += ' pfy-popup-closebtn';
    }

    this.buttonsClass = options.buttonsClass || 'pfy-button';
    this.buttonClasses = null;
    if (typeof options.buttonClasses === 'object') {
      this.buttonClasses = options.buttonClasses;
    } else if (typeof options.buttonClasses === 'string') {
      if (options.buttonClasses.includes(',')) {
        this.buttonClasses = options.buttonClasses.split(/\s*,\s*/);
      } else {
        this.buttonsClass = options.buttonClasses;
      }
    }

    this.wrapperClass = options.wrapperClass || '';
    this.scrollHints = options.scrollHints ?? true;
  } // parseArgs


  setupGlobalTriggers() {
    if (PfyPopup.globalTriggersInitialized) {
      return;
    }
    PfyPopup.globalTriggersInitialized = true;

    // handle dragging (checks for .pfy-draggable class, so works for any popup):
    document.addEventListener('pointerdown', (ev) => {
      if (ev.target.closest('.pfy-popup-header.pfy-draggable > div')) {
        PfyPopup.dragPopup(ev);
      }
    });

    // click events (instance is looked up from DOM, not captured in closure):
    document.addEventListener('click', (ev) => {
      const target = ev.target;

      if (target.closest('.pfy-popup-close-button')) {
        const instance = PfyPopup.getInstanceFromElement(target);
        if (instance) {
          instance.close(target);
        }

      } else if (target.closest('.pfy-popup-buttons')) {
        const instance = PfyPopup.getInstanceFromElement(target);
        if (instance) {
          instance.handleButtonTriggers(target);
        }

      } else if (!target.closest('.pfy-popup-wrapper') && document.querySelector('.pfy-close-on-bg-click')) {
        const popupEl = document.querySelector('.pfy-popup-wrapper');
        if (popupEl && popupEl.querySelector('.pfy-form-is-modified')) {
          return;
        }
        const instance = PfyPopup.getInstanceFromElement(document.querySelector('.pfy-popup-bg'));
        if (instance) {
          instance.close(target);
        }
      }
    });

    // handle Esc and Enter:
    document.addEventListener('keyup', (ev) => {
      const target = ev.target;
      if (!target.closest('.pfy-popup-wrapper')) {
        return;
      }
      const instance = PfyPopup.getInstanceFromElement(target);
      if (!instance) {
        return;
      }

      // === ESC:
      if (ev.key === 'Escape') {
        if (instance.onCancel) {
          instance.inhibitClosing = !instance.executeCallback(instance.onCancel);
        } else if (instance.onClose && document.querySelectorAll('.pfy-popup-btn-close').length) {
          instance.inhibitClosing = !instance.executeCallback(instance.onClose);
        }
        instance.close();
        ev.preventDefault();
      }

      // === Enter:
      if (ev.key === 'Enter' && target.tagName !== 'TEXTAREA') {
        if (target.closest('.pfy-popup-btn-confirm')) {
          instance.inhibitClosing = !instance.executeCallback(instance.onConfirm);
        } else if (target.closest('.pfy-popup-btn-ok')) {
          instance.inhibitClosing = !instance.executeCallback(instance.onOk);
        } else if (target.closest('.pfy-popup-btn-continue')) {
          instance.inhibitClosing = !instance.executeCallback(instance.onContinue);
        }
        instance.close();
        ev.preventDefault();
      }
    });
  } // setupGlobalTriggers


  static getInstanceFromElement(el) {
    if (!el) return null;
    const popupBg = el.closest('.pfy-popup-bg') || el;
    return popupBg._pfyPopupInstance || null;
  } // getInstanceFromElement


  static dragPopup(ev) {
    const popup = ev.target.closest('.pfy-popup-wrapper');
    if (!popup) return;

    let translX = parseFloat(popup.dataset.translX) || 0;
    let translY = parseFloat(popup.dataset.translY) || 0;
    let mouseX = 0;
    let mouseY = 0;
    let mouseX0 = 0;
    let mouseY0 = 0;

    // start dragging:
    ev.preventDefault();
    mouseX = mouseX0 = ev.clientX;
    mouseY = mouseY0 = ev.clientY;
    document.onpointerup = closeDragElement;
    document.onpointermove = elementDrag;

    function elementDrag(ev) {
      ev.preventDefault();
      mouseX = ev.clientX;
      mouseY = ev.clientY;
      const dx = translX + mouseX - mouseX0;
      const dy = translY + mouseY - mouseY0;
      popup.style.transform = `translate(${dx}px, ${dy}px)`;
    }

    function closeDragElement() {
      popup.dataset.translX = translX + mouseX - mouseX0;
      popup.dataset.translY = translY + mouseY - mouseY0;
      document.onpointerup = null;
      document.onpointermove = null;
    }
  } // dragPopup


  renderContent() {
    let cls = 'pfy-popup-wrapper';
    if (this.popupClass) {
      cls += ' ' + this.popupClass;
    }

    let wrapperClass = 'pfy-popup-bg';
    if (this.wrapperClass) {
      wrapperClass += ' ' + this.wrapperClass;
    }
    if (this.closeOnBgClick) {
      wrapperClass += ' pfy-close-on-bg-click';
    }

    let containerClass = 'pfy-popup-container';
    if (this.scrollHints) {
      containerClass += ' pfy-scroll-hints';
    }
    if (this.containerClass) {
      containerClass += ' ' + this.containerClass;
    }

    const header = this.renderHeader();
    let content = this.content;
    if (this.contentFrom) {
      content += this.getContentFrom();
    }

    const html = `
              <dialog class="${cls}">
                   ${header}
                  <div class="${containerClass}" role="document">
                    <div>
                      ${content}
                    </div>
                  </div>
                  ${this.buttonHtml}
              </dialog>
          `;

    const ankerElement = document.querySelector(this.anker);
    const pElement = document.createElement('div');

    wrapperClass = this.id + ' ' + wrapperClass;
    pElement.setAttribute('class', wrapperClass);
    pElement.innerHTML = html;
    pElement._pfyPopupInstance = this;
    ankerElement.appendChild(pElement);

    const dialog = document.querySelector(`.${this.id} > dialog`);
    dialog.show();
    return dialog;
  } // renderContent


  getContentFrom() {
    let contentFrom = this.contentFrom;
    let cFromEl = null;

    if (typeof contentFrom === 'string') {
      if (contentFrom.charAt(0) !== '#' && contentFrom.charAt(0) !== '.') {
        contentFrom = '#' + contentFrom;
      }
      cFromEl = document.querySelector(contentFrom);

    } else if (contentFrom instanceof Element) {
      cFromEl = contentFrom;

    } else {
      throw new Error('Error in popup.js:getContentFrom() -> unable to handle contentFrom');
    }
    let html = cFromEl.outerHTML;

    // as we are cloning code, we need to fix ids and related attributes
    let m;

    // fix "#ref", unless it's in an href:
    while (m = html.match(/(?<!href=["'])#(?!pfy-popped)([\w_-]+)/)) {
      const regex = new RegExp(m[0], 'm');
      html = html.replace(regex, '#pfy-popped-' + m[1]);
    }
    // fix "for='id'" in labels:
    while (m = html.match(/ for=(['"])(.*?)['"]/)) {
      const regex = new RegExp(m[0], 'm');
      html = html.replace(regex, ' FOR="pfy-popped-' + m[2] + '"');
    }
    // fix "id='xy'", apply a prefix:
    while (m = html.match(/ id=(['"])(.*?)['"]/)) {
      const regex = new RegExp(m[0], 'm');
      html = html.replace(regex, ' ID="pfy-popped-' + m[2] + '"');
    }
    // fix "aria-XY=" which use an id as argument:
    while (m = html.match(/ aria-(controls|describedby|labelledby)=(['"])(.*?)['"]/)) {
      const regex = new RegExp(m[0], 'm');
      html = html.replace(regex, ` ARIA-${m[1]}="pfy-popped-${m[3]}"`);
    }
    return html;
  } // getContentFrom


  renderHeader() {
    const cls = this.draggable ? ' pfy-draggable' : '';
    let header = '';

    if (this.header !== false) {
      header = `
  <div class="pfy-popup-header${cls}">
    <div>${this.header}</div>
    ${this.closeButton}
  </div><!-- /pfy-popup-header -->
`;
      this.popupClass += ' pfy-popup-with-header';

    } else {
      header = this.closeButton;
    }
    return header;
  } // renderHeader


  renderButtons() {
    // render close button in upper right corner:
    if (this.closeButton) {
      this.closeButton = '<button class="pfy-close-button pfy-popup-close-button" type="button">✕</button>';
    } else {
      this.closeButton = '';
    }

    this.buttonHtml = '';
    if (!this.buttons.length) {
      return;
    }

    const buttonMap = {
      'cancel':   (id, k, cl) => `<button id="${id}" class="pfy-popup-btn-cancel pfy-popup-btn-${k} ${cl}">{{ pfy-cancel }}</button>`,
      'close':    (id, k, cl) => `<button id="${id}" class="pfy-popup-btn-close pfy-popup-btn-${k} ${cl}">{{ pfy-close }}</button>`,
      'ok':       (id, k, cl) => `<button id="${id}" class="pfy-button-submit pfy-popup-btn-ok pfy-popup-btn-${k} ${cl}">{{ pfy-ok }}</button>`,
      'continue': (id, k, cl) => `<button id="${id}" class="pfy-button-submit pfy-popup-btn-continue pfy-popup-btn-${k} ${cl}">{{ pfy-continue }}</button>`,
      'confirm':  (id, k, cl) => `<button id="${id}" class="pfy-button-submit pfy-popup-btn-confirm pfy-popup-btn-${k} ${cl}">{{ pfy-confirm }}</button>`,
    };

    let bCl = this.buttonsClass;
    let html = '';

    for (let i = 0; i < this.buttons.length; i++) {
      const k = i + 1;
      const id = `pfy-popup-btn-${k}`;
      if (this.buttonClasses !== null && typeof this.buttonClasses[i] === 'string') {
        bCl = this.buttonClasses[i];
      }

      const button = (typeof this.buttons[i] === 'string') ? this.buttons[i] : '';
      const key = button.toLowerCase();

      if (buttonMap[key]) {
        html += buttonMap[key](id, k, bCl);
      } else {
        html += `<button id="${id}" class="pfy-popup-btn-${k} ${bCl}">${button}</button>`;
      }
    }

    if (html) {
      this.buttonHtml = '<div class="pfy-popup-buttons">' + html + '</div>';
    }
  } // renderButtons


  setupOpenTrigger() {
    if (this.trigger === true) {
      this.openPopup();
      return;
    }
    if (!this.triggerInitialized && this.trigger) {
      if (this.triggerEvent === 'right-click') {
        this.triggerEvent = 'contextmenu';
      }
      this.triggerElem = document.querySelector(this.trigger);
      if (this.triggerElem) {
        this.triggerElem.setAttribute('aria-expanded', 'false');
        this.triggerElem.addEventListener(this.triggerEvent, (e) => {
          e.stopPropagation();
          e.preventDefault();
          this.triggerElem.setAttribute('aria-expanded', 'true');
          this.openPopup();
        });
      }
      this.triggerInitialized = true;
    }
  } // setupOpenTrigger


  handleButtonTriggers(button) {
    const btnClasses = button.classList;
    if (btnClasses.contains('pfy-popup-btn-cancel') && this.onCancel) {
      this.inhibitClosing = !this.executeCallback(this.onCancel);

    } else if (btnClasses.contains('pfy-popup-btn-ok') && this.onOk) {
      this.inhibitClosing = !this.executeCallback(this.onOk);

    } else if (btnClasses.contains('pfy-popup-btn-continue') && this.onContinue) {
      this.inhibitClosing = !this.executeCallback(this.onContinue);

    } else if (btnClasses.contains('pfy-popup-btn-confirm') && this.onConfirm) {
      this.inhibitClosing = !this.executeCallback(this.onConfirm);

    // if none of the above triggered, try the second button:
    } else if (btnClasses.contains('pfy-popup-btn-2') && this.onOk) {
      this.inhibitClosing = !this.executeCallback(this.onOk);

    } else {
      this.inhibitClosing = false;
    }

    if (!this.inhibitClosing) {
      const popup = button.closest('.pfy-popup-bg');
      this.close(popup);
    }
  } // handleButtonTriggers


  openPopup() {
    this.id = 'pfy-popup-' + this.popupInstance;

    this.renderButtons();
    const popup = this.renderContent();
    popup.parentElement.removeAttribute('style');
    popup.style.display = 'block';
    popup.style.opacity = this.initialOpacity;
    this.popup = popup;

    // freeze background:
    if (this.modal) {
      document.body.classList.add('pfy-no-scroll', 'pfy-modal');
    } else {
      document.body.classList.add('pfy-no-scroll');
    }
    document.querySelector('.pfy-page').setAttribute('inert', '');

    // set focus to either first input, ok-button or close-button:
    if (this.autofocus) {
      setTimeout(() => {
        const inputEl = this.popup.querySelectorAll('input');
        if (inputEl.length) {
          inputEl[0].focus();
        } else {
          let buttons = this.popup.querySelectorAll('.pfy-popup-btn-ok, .pfy-popup-btn-confirm, .pfy-popup-btn-continue');
          if (buttons.length) {
            buttons[0].focus();
          } else {
            buttons = this.popup.querySelectorAll('.pfy-close-button, .pfy-popup-close-button');
            if (buttons.length) {
              buttons[0].focus();
            }
          }
        }
      }, 50);
    }

    if (this.onOpen) {
      this.executeCallback(this.onOpen);
    }

    // for accessibility: make sure focus can't go outside popup (workaround while 'inert' is not reliable):
    this.trapFocus(popup);

    return this;
  } // openPopup


  close(el) {
    if (el === undefined || el === document.body) {
      domForEach('.pfy-popup-bg', (popupBg) => {
        const instance = popupBg._pfyPopupInstance || this;
        instance._close(popupBg);
      });

    } else {
      const popupBg = el.closest ? el.closest('.pfy-popup-bg') : el;
      this._close(popupBg);
    }
  } // close


  _close(popupBg) {
    // exec onClose callback, if defined:
    if (this.onClose) {
      this.inhibitClosing = !this.executeCallback(this.onClose);
    }
    if (this.inhibitClosing) {
      return;
    }

    // close popup now:
    if (popupBg) {
      popupBg.remove();
    }

    if (!document.querySelector('.pfy-popup-bg')) {
      document.body.classList.remove('pfy-no-scroll', 'pfy-modal');
      document.querySelector('.pfy-page').removeAttribute('inert');
    }

    if (this.triggerElem) {
      this.triggerElem.setAttribute('aria-expanded', 'false');
      this.triggerElem.focus();
    }
  } // _close


  trapFocus(popup) {
    const popupWrapper = popup.closest('.pfy-popup-wrapper') || popup;
    const focusableEls = popupWrapper.querySelectorAll(
        'a[href]:not([disabled]), button:not([disabled]), ' +
        'textarea:not([disabled]), input[type="text"]:not([disabled]), input[type="radio"]:not([disabled]), ' +
        'input[type="checkbox"]:not([disabled]), select:not([disabled]), ' +
        'input[type="submit"]:not([disabled]), input[type="cancel"]:not([disabled]), input[type="button"]:not([disabled])');
    const firstFocusableEl = focusableEls[0];
    const lastFocusableEl = focusableEls[focusableEls.length - 1];

    popupWrapper.addEventListener('keydown', (ev) => {
      if (ev.key !== 'Tab') {
        return;
      }

      if (ev.shiftKey) {
        if (document.activeElement === firstFocusableEl) {
          lastFocusableEl.focus();
          ev.preventDefault();
        }
      } else {
        if (document.activeElement === lastFocusableEl) {
          firstFocusableEl.focus();
          ev.preventDefault();
        }
      }
    });
  } // trapFocus


  executeCallback(callback) {
    let res = true;
    if (typeof callback === 'function') {
      res = callback(this, this.callbackArg);

    } else if (typeof window[callback] === 'function') {
      res = window[callback](this, this.callbackArg);

    } else if (typeof callback === 'string') {
      try {
        res = new Function(callback)(this, this.callbackArg);
      } catch (e) {
        console.error(e);
      }
    }
    return (typeof res !== 'undefined') ? res : true;
  } // executeCallback

} // PfyPopup



// === Factory function (backward-compatible API) ===
function pfyPopup(options) {
  return new PfyPopup(options);
} // pfyPopup



// === Convenience wrapper functions ============================================

/*
pfyPopupPromise({
    text: 'Text',
    header: 'Header',
    buttons: 'Cancel,Continue',
})
.then(
    (data) => { console.log('success: ' + data); },
    (data) => { console.log('failed: ' + data);  }
);
 */
function pfyPopupPromise(options) {
  return new Promise(function(resolve) {
    // affirmative reactions:
    const onOk = options.onOk;
    options.onOk = function(popup) {
      if (onOk) popup.executeCallback(onOk);
      resolve(true);
    };
    const onContinue = options.onContinue;
    options.onContinue = function(popup) {
      if (onContinue) popup.executeCallback(onContinue);
      resolve(true);
    };
    const onConfirm = options.onConfirm;
    options.onConfirm = function(popup) {
      if (onConfirm) popup.executeCallback(onConfirm);
      resolve(true);
    };

    // rejecting reactions:
    const onCancel = options.onCancel;
    options.onCancel = function(popup) {
      if (onCancel) popup.executeCallback(onCancel);
      resolve(false);
    };
    const onClose = options.onClose;
    options.onClose = function(popup) {
      if (onClose) popup.executeCallback(onClose);
      resolve(false);
    };

    pfyPopup(options);
  });
} // pfyPopupPromise



/*
pfyConfirm('Test')
    .then(
        (data) => { console.log('success: ' + data); },
        (data) => { console.log('failed: ' + data);  }
    );
 */
function pfyConfirm(options) {
  if (typeof options === 'string') {
    options = { text: options };
  }
  if (options.triggerSource === undefined) {
    options.triggerSource = true;
  }
  options.header = `{{pfy-confirm-header}}`;
  options.closeOnBgClick = false;
  options.closeButton = true;
  return new Promise(function(resolve, reject) {
      options.onOk = function() {
        resolve(true);
      };

      // rejecting reactions:
      options.onCancel = function() {
        reject(false);
      };
      if (options.buttons === undefined) {
        options.buttons = 'Cancel,Ok';
      }
      pfyPopup(options);
    });
} // pfyConfirm



/*
pfyAlert('Alert!');
 */
function pfyAlert(options) {
  if (typeof options === 'string') {
    options = { text: options };
  }
  if (options.triggerSource === undefined) {
    options.triggerSource = true;
  }
  options.closeOnBgClick = true;
  options.closeButton = true;
  options.buttons = 'Ok';
  options.header = `{{pfy-alert-header}}`;
  return new Promise(function(resolve) {
      options.onOk = function() {
        resolve(true);
      };
      pfyPopup(options);
    });
} // pfyAlert



function pfyPopupClose() {
  domForEach('.pfy-popup-bg', (popup) => {
    popup.remove();
  });
  document.body.classList.remove('pfy-no-scroll', 'pfy-modal');
  document.querySelector('.pfy-page').removeAttribute('inert');
} // pfyPopupClose
