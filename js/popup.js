/*
* pfyPopup
*
* Usage:
  pfyConfirm('Continue...?').then(
    function(value) {
      mylog('Ok');
    },
    function(error) {
      mylog('Canceled');
    }
  );
*/

"use strict";

/*
class XY {

  constructor(options) {
     this.options = options;

      this.init();
  } // constructor


  init() {

  } // init

} // class
*/

var globalTriggersInitialized = false;
var popupInstance = 0;

function pfyPopup( options ) {
  var popupParent = this;

  this.init = function (options) {
    this.popupInstance = popupInstance++;
    this.inihibitClosing = false;
    this.triggerInitialized = false;
    this.parseArgs( options );
    this.setupTriggers();
    this.setupOpenTrigger();
    return this;
  }; // init


  this.parseArgs = function () {
    if (typeof options === 'string') {
      const str = options;
      options = null;
      options = { text: str };
    }
    this.options = options;
    this.initialOpacity = options.initialOpacity || 1;
    this.text  = (typeof options.text !== 'undefined' && options.text)? options.text : ''; // text synonym for content
    this.content  = (typeof options.content !== 'undefined' && options.content)? options.content : this.text;

    this.contentFrom = (typeof options.contentFrom !== 'undefined' && options.contentFrom) ? options.contentFrom : ''; // contentFrom synonyme for contentRef

    this.modal  = (typeof options.modal !== 'undefined')? options.modal : true;

    this.header  = (typeof options.header !== 'undefined' && options.header)? options.header : false;
    if ((this.header === '') || (this.header === true)) {
      this.header = '';
    }
    this.draggable  = (typeof options.draggable !== 'undefined' && options.draggable)? options.draggable : (this.header !== false);
    if (this.draggable && (this.header === false)) {
      this.header = '';
    }

    this.trigger = (typeof options.trigger !== 'undefined' && options.trigger) ? options.trigger : true; // default=autoopen
    this.trigger = (typeof options.triggerSource !== 'undefined' && options.triggerSource) ? options.triggerSource : this.trigger;
    this.triggerEvent = (typeof options.triggerEvent !== 'undefined' && options.triggerEvent) ? options.triggerEvent : 'click';
    this.anker = (typeof options.anker !== 'undefined' && options.anker) ? options.anker : 'body';
    this.closeOnBgClick = (typeof options.closeOnBgClick !== 'undefined') ? options.closeOnBgClick : true;

    if (typeof options.closeButton !== 'undefined') {
      this.closeButton = options.closeButton;
    } else {
      this.closeButton = true;
    }
    this.buttons = (typeof options.buttons === 'string' && options.buttons) ? options.buttons.split(/\s*,\s*/) : [];

    // omit closeButton if buttons are defined (unless header is active):
    if ((typeof options.closeButton === 'undefined') && (this.buttons.length) && !this.header) {
      this.closeButton = false;
    }
    this.callbackArg  = (typeof options.callbackArg !== 'undefined' && options.callbackArg) ? options.callbackArg : '';
    this.onConfirm    = (typeof options.onConfirm !== 'undefined' && options.onConfirm) ? options.onConfirm : '';
    this.onCancel     = (typeof options.onCancel !== 'undefined' && options.onCancel) ? options.onCancel : '';
    this.onOk         = (typeof options.onOk !== 'undefined' && options.onOk) ? options.onOk : '';
    this.onContinue   = (typeof options.onContinue !== 'undefined' && options.onContinue) ? options.onContinue : '';
    this.onClose      = (typeof options.onClose !== 'undefined' && options.onClose) ? options.onClose : '';
    this.onOpen       = (typeof options.onOpen !== 'undefined' && options.onOpen) ? options.onOpen : '';

    this.autofocus = (typeof options.autofocus !== 'undefined' && options.autofocus) ? options.autofocus : false;

    this.containerClass = (typeof options.containerClass !== 'undefined' && options.containerClass) ? ' ' + options.containerClass : '';

    this.popupClass = '';
    if ((typeof options.class !== 'undefined' && options.class) && options.class) {
      this.popupClass = options.class;
    } else if ((typeof options.popupClass !== 'undefined') && options.popupClass) {
      this.popupClass = options.popupClass;
    }

    if (this.closeButton) {
      this.popupClass += ' pfy-popup-closebtn';
    }
    this.buttonsClass = (typeof options.buttonsClass !== 'undefined' && options.buttonsClass) ? ' ' + options.buttonsClass : 'pfy-button';
    this.buttonClasses = null;
    if ((typeof options.buttonClasses === 'object')) {
      this.buttonClasses = options.buttonClasses;
    } else if ((typeof options.buttonClasses === 'string')) {
      if (options.buttonClasses.match(',')) {
        this.buttonClasses = options.buttonClasses.split(/\s*,\s*/);
      } else {
        this.buttonsClass = options.buttonClasses;
      }
    }
    this.wrapperClass  = (typeof options.wrapperClass !== 'undefined' && options.wrapperClass)? options.wrapperClass : '';
    this.scrollHints  = (typeof options.scrollHints !== 'undefined')? options.scrollHints : true;

  }; // parseArgs


  this.setupTriggers = function() {
    if (globalTriggersInitialized) {
      return;
    }
    globalTriggersInitialized = true;

    // handle dragging:
    this.setupDragHandler();

    // click events:
    this.setupCLickHandlers();

    // handle Esc and Enter:
    this.setupKeyHandlers();

  } // setupTriggers


  this.setupDragHandler = function() {
    const parent = popupParent;
    if (this.draggable) {
      document.addEventListener('pointerdown', function (ev) {
        if (ev.target.closest('.pfy-popup-header > div')) {
          parent.dragPopup(ev);
        }
      });
    }
  } // setupDragHandler


  this.setupCLickHandlers = function() {
    const parent = popupParent;
    document.addEventListener('click', function (ev) {
      const target = ev.target;

      if (target.closest('.pfy-popup-close-button')) {
        parent.close(target);

      } else if (target.closest('.pfy-popup-buttons')) {
        parent.handleButtonTriggers(target);

      } else if (!target.closest('.pfy-popup-wrapper') && document.querySelector('.pfy-close-on-bg-click')) {
        const popupEl = document.querySelector('.pfy-popup-wrapper');
        if (popupEl && popupEl.querySelector('.pfy-form-is-modified')) {
          return;
        }
        parent.close(target);
      }
    });
  } // setupCLickHandlers


  this.setupKeyHandlers = function() {
    const parent = popupParent;
    document.addEventListener('keyup', function (ev) {
      const target = ev.target;
      const key = ev.key;
      if (!target.closest('.pfy-popup-wrapper')) {
        return;
      }

      // === ESC:
      if (key === 'Escape') {
        if (parent.onCancel) {
          parent.inihibitClosing = !parent.executeCallback(parent.onCancel);

        } else if (parent.onClose && document.querySelectorAll('.pfy-popup-btn-close').length) {
          parent.inihibitClosing = !parent.executeCallback(parent.onClose);
        }
        parent.close();
        ev.preventDefault();
      } //  Escape

      // === Enter:
      if (key === 'Enter' && target.tagName !== 'TEXTAREA') {
        if (target.closest('.pfy-popup-btn-confirm')) {
          parent.inihibitClosing = !parent.executeCallback(parent.onConfirm);
        } else if (target.closest('.pfy-popup-btn-ok')) {
          parent.inihibitClosing = !parent.executeCallback(parent.onOk);
        } else if (target.closest('.pfy-popup-btn-continue')) {
          parent.inihibitClosing = !parent.executeCallback(parent.onContinue);
        }
        parent.close();
        ev.preventDefault();
      }
    });
  } // setupKeyHandlers


  this.renderContent = function () {
    let cls = 'pfy-popup-wrapper';
    if (this.popupClass) {
      cls += ' ' + this.popupClass;
    }

    let wrapperClass = 'pfy-popup-bg';
    if (this.wrapperClass) {
      wrapperClass += ' ' + this.wrapperClass;
    }

    // activate closeOnBgClick if requested:
    if (this.closeOnBgClick) {
      wrapperClass += ' pfy-close-on-bg-click';
    }

    let containerClass = 'pfy-popup-container';
    if (this.scrollHints) {
      containerClass += ' pfy-scroll-hints';
    }
    if (this.containerClass) {
      containerClass += ' '+this.containerClass;
    }

    let header = this.renderHeader();
    let content = this.content;
    if (this.contentFrom) {
      content += this.getContentFrom();
    }
    let html = `
              <dialog class="${cls}">
                   ${header}
                  <div class="${containerClass}" role="document">
                      ${content}
                  </div>
                  ${this.buttonHtml}
              </dialog>
          `;

    const ankerElement = document.querySelector(this.anker);
    const pElement = document.createElement('div');

    wrapperClass = parent.id + ' ' + wrapperClass;
    pElement.setAttribute('class', wrapperClass);
    pElement.innerHTML = html;
    ankerElement.appendChild(pElement);
    const dialog = document.querySelector( `.${parent.id} > dialog`);
    dialog.show();
    return dialog;
  }; // renderContent


  this.getContentFrom = function() {
    let contentFrom = this.contentFrom;
    let cFromEl = null;
    if (typeof contentFrom === 'string') {
      if ((contentFrom.charAt(0) !== '#') && (contentFrom.charAt(0) !== '.')) {
        contentFrom = '#' + contentFrom;
      }
      cFromEl = document.querySelector( contentFrom );

    } else if ( (typeof contentFrom !== false)  && contentFrom.length) { // case jQ-object
      cFromEl = contentFrom;
    } else {
      alert('Error in popup.js:prepareContent() -> unable to handle contentFrom');
      return ''; // error
    }
    let html = cFromEl.outerHTML;

    // as we are cloning code, we need to fix ids and related attributes
    let m;

    // fix "#ref", unless it's in an href:
    while (m = html.match(/(?<!href=["'])#(?!pfy-popped)([\w_-]+)/)) {
      const regex = new RegExp(m[0], "m");
      const newVal = '#pfy-popped-' + m[1];
      html = html.replace(regex, newVal);
    }
    // fix "for='id'" in labels:
    while (m = html.match(/ for=(['"])(.*?)['"]/)) {
      const regex = new RegExp(m[0], "m");
      const newVal = ' FOR="pfy-popped-' + m[2] + '"';
      html = html.replace(regex, newVal);
    }
    // fix "id='xy'", apply a prefix:
    while (m = html.match(/ id=(['"])(.*?)['"]/)) {
      const regex = new RegExp(m[0], "m");
      const newVal = ' ID="pfy-popped-' + m[2] + '"';
      html = html.replace(regex, newVal);
    }
    // fix "aria-XY=" which use an id as argument:
    while (m = html.match(/ aria-(controls|describedby|labelledby)=(['"])(.*?)['"]/)) {
      const regex = new RegExp(m[0], "m");
      const newVal = ` ARIA-${m[1]}="pfy-popped-${m[3]}"`;
      html = html.replace(regex, newVal);
    }
    return html;
  }; // getContentFrom


  this.renderHeader = function() {
    let cls = this.draggable? ' pfy-draggable': '';
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
  }; // renderHeader


  this.renderButtons = function () {
    let buttonHtml = '';

    // render close button in upper right corner:
    if (this.closeButton) {
      this.closeButton = '<button class="pfy-close-button pfy-popup-close-button" type="button">✕</button>';
    } else {
      this.closeButton = '';
    }

    this.buttonHtml = '';
    if (typeof this.buttons === 'undefined') {
      return;
    }
    let bCl = this.buttonsClass;

    // render buttons in lower right space below popup content:
    for (let i in this.buttons) {
      let k = parseInt(i) + 1;
      let id = `pfy-popup-btn-${k}`;
      if ((this.buttonClasses !== null) && (typeof this.buttonClasses[i] === 'string')) {
        bCl = this.buttonClasses[i];
      }

      let button = (typeof this.buttons[i] === 'string')? this.buttons[i]: '';

      // predefined button 'Cancel':
      if (button.toLowerCase() === 'cancel') {
        buttonHtml += `<button id="${id}" class="pfy-popup-btn-cancel pfy-popup-btn-${k} ${bCl}">{{ pfy-cancel }}</button>`;

        // predefined button 'Close':
      } else if (button.toLowerCase() === 'close') {
        buttonHtml += `<button id="${id}" class="pfy-popup-btn-close pfy-popup-btn-${k} ${bCl}">{{ pfy-close }}</button>`;

        // predefined button 'Ok':
      } else if (button.toLowerCase() === 'ok') {
        buttonHtml += `<button id="${id}" class="pfy-button-submit pfy-popup-btn-ok pfy-popup-btn-${k} ${bCl}">{{ pfy-ok }}</button>`;

      // predefined button 'Continue':
      } else if (button.toLowerCase() === 'continue') {
        buttonHtml += `<button id="${id}" class="pfy-button-submit pfy-popup-btn-continue pfy-popup-btn-${k} ${bCl}">{{ pfy-continue }}</button>`;

      // predefined button 'Confirm':
      } else if (button.toLowerCase() === 'confirm') {
        buttonHtml += `<button id="${id}" class="pfy-button-submit pfy-popup-btn-confirm pfy-popup-btn-${k} ${bCl}">{{ pfy-confirm }}</button>`;

      // custom buttons:
      } else {
        buttonHtml += `<button id="${id}" class="pfy-popup-btn-${k} ${bCl}">${button}</button>`;
      }

    }
    if (buttonHtml) {
      this.buttonHtml = '<div class="pfy-popup-buttons">' + buttonHtml + '</div>';
    }
  }; // renderButtons


  this.setupOpenTrigger = function () {
    if (this.trigger === true) { // open immediately
        this.open();
    }
    if (!this.triggerInitialized && this.trigger && (this.trigger !== true)) {
      if (this.triggerEvent === 'right-click') {
        this.triggerEvent = 'contextmenu';
      }
      this.triggerElem = document.querySelector(this.trigger);
      if (this.triggerElem) {
        this.triggerElem.setAttribute('aria-expanded','false');
        this.triggerElem.addEventListener(this.triggerEvent, function (e) {
          e.stopPropagation();
          e.preventDefault();
          parent.triggerElem.setAttribute('aria-expanded','true');
          parent.open();
        });
      }
      this.triggerInitialized = true;
    }
  }; // setupOpenTrigger


  this.handleButtonTriggers = function (button) {
    const parent = this; //???
    const btnClasses = button.classList;
    if (btnClasses.contains('pfy-popup-btn-cancel') && parent.onCancel) {
      parent.inihibitClosing = !this.executeCallback(parent.onCancel);

    } else if (btnClasses.contains('pfy-popup-btn-ok') && parent.onOk) {
      parent.inihibitClosing = !this.executeCallback(parent.onOk);

    } else if (btnClasses.contains('pfy-popup-btn-continue') && parent.onContinue) {
      parent.inihibitClosing = !this.executeCallback(parent.onContinue);

    } else if (btnClasses.contains('pfy-popup-btn-confirm') && parent.onConfirm) {
      parent.inihibitClosing = !this.executeCallback(parent.onConfirm);

    // if none of the above triggered, try the second button:
    } else if (btnClasses.contains('pfy-popup-btn-2') && parent.onOk) {
      parent.inihibitClosing = !this.executeCallback(parent.onOk);

    } else {
      parent.inihibitClosing = false;
    }

    if (!parent.inihibitClosing) {
      const popup = button.closest('.pfy-popup-bg');
      parent.close(popup);
    }
  }; // handleButtonTriggers


  this.dragPopup = function (ev) {
    const popup = ev.target.closest('.pfy-popup-wrapper');
    let translX;
    let translY;
    if (typeof popup.dataset.translX === 'undefined') {
      translX = 0;
    } else {
      translX = parseFloat(popup.dataset.translX);
    }
    if (typeof popup.dataset.translY === 'undefined') {
      translY = 0;
    } else {
      translY = parseFloat(popup.dataset.translY);
    }
    let mouseX = 0;
    let mouseY = 0;
    let mouseX0 = 0;
    let mouseY0 = 0;
    // start dragging:
    dragMouseDown(ev);

    function dragMouseDown(ev) {
      ev.preventDefault();
      mouseX = mouseX0 = ev.clientX;
      mouseY = mouseY0 = ev.clientY;
      document.onpointerup = closeDragElement;
      document.onpointermove = elementDrag;
    }

    function elementDrag(ev) {
      ev.preventDefault();
      mouseX = ev.clientX;
      mouseY = ev.clientY;
      const dx = translX + mouseX - mouseX0;
      const dy = translY + mouseY - mouseY0;
      popup.style.transform = `translate(${dx}px, ${dy}px)`;
    }

    function closeDragElement() {
      // stop moving when mouse button is released:
      popup.dataset.translX = translX + mouseX - mouseX0;
      popup.dataset.translY = translY + mouseY - mouseY0;
      document.onpointerup = null;
      document.onpointermove = null;
    }
  }; // initDraggable


  this.open = function () {
    this.popupInstance++;
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
      setTimeout(function () {
        let inputEl = parent.popup.querySelectorAll('input')
        if (inputEl.length) {
          inputEl[0].focus();
        } else {
          let buttons = parent.popup.querySelectorAll('.pfy-popup-btn-ok, .pfy-popup-btn-confirm, .pfy-popup-btn-continue');
          if (buttons.length) {
            buttons[0].focus();
          } else {
            buttons = parent.popup.querySelectorAll('.pfy-close-button, .pfy-popup-close-button');
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

    // for accessibility: make sure focus can't go outside popup. (workaround while 'inert' is not reliable)
    this.trapFocus(popup);

    return this;
  }; // open


  this.close = function (el) {
    if (typeof el === 'undefined' || el === document.body) {
      const parent = this;
      domForEach('.pfy-popup-bg', function (popupBg) {
        //mylog('closing all popups');
        parent._close(popupBg);
      });

    } else {
      const popupBg = el.closest('.pfy-popup-bg');
      this._close(popupBg);
    }
  } // close


  this._close = function (popupBg) {
    // exec onClose callback, if defined:
    if (this.onClose) {
      this.inihibitClosing = !this.executeCallback(parent.onClose);
    }
    if (this.inihibitClosing) {
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
  }; // _close


  this.trapFocus = function (popup) {
    const popupWrapper = popup.closest('.pfy-popup-wrapper');
    const focusableEls = popupWrapper.querySelectorAll(
        'a[href]:not([disabled]), button:not([disabled]), ' +
        'textarea:not([disabled]), input[type="text"]:not([disabled]), input[type="radio"]:not([disabled]), ' +
        'input[type="checkbox"]:not([disabled]), select:not([disabled]), ' +
        'input[type="submit"]:not([disabled]), input[type="cancel"]:not([disabled]), input[type="button"]:not([disabled])');
    const firstFocusableEl = focusableEls[0];
    const lastFocusableEl = focusableEls[focusableEls.length - 1];
    const KEYCODE_TAB = 9;

    popupWrapper.addEventListener('keydown', function(ev) {
      const isTabPressed = (ev.key === 'Tab' || ev.keyCode === KEYCODE_TAB);

      if (!isTabPressed) {
        return;
      }

    if ( ev.shiftKey ) /* shift + tab */ {
      if (document.activeElement === firstFocusableEl) {
        lastFocusableEl.focus();
        ev.preventDefault();
      }
    } else /* tab */ {
      if (document.activeElement === lastFocusableEl) {
        firstFocusableEl.focus();
        ev.preventDefault();
      }
    }
    });
  } // trapFocus


  this.executeCallback = function(callback) {
    let res = true; // default is 'not inihibitClosing'
    if (typeof callback === 'function') {
       res = callback( parent, parent.callbackArg );

    } else if (typeof window[callback] === 'function') {
      res = window[callback]( parent, parent.callbackArg );
    } else if (typeof callback === 'string') {
      // if it's a string, try to execute it as js code:
      try {
        res = new Function(callback)( parent, parent.callbackArg );
      } catch (e) {
        console.error(e);
      }
    }
    return (typeof res !== 'undefined') ? res: true;
  } // executeCallback


  this.init( options );
  return this;
} // PfyPopup



 // === convenience wrapper functions ============================================
/*
pfyPopupPromise({
    text: 'Text',
    header: 'Header',
    buttons: 'Cancel,Continue',
})
.then(
    (data) => { mylog('success: ' + data); },
    (data) => { mylog('failed: ' + data);  }
);
 */
function pfyPopupPromise( options ) {
    return new Promise(function(resolve, reject) {
      // affirmative reactions:
      let onOk = options.onOk;
      options.onOk = function() {
        this.executeCallback(onOk);
        resolve( true );
      };
      let onContinue = options.onContinue;
      options.onContinue = function() {
        this.executeCallback(onContinue);
        resolve( true );
      };
      let onConfirm = options.onConfirm;
      options.onConfirm = function() {
        this.executeCallback(onConfirm);
        resolve( true );
      };

      // rejecting reactions:
      let onCancel = options.onCancel;
      options.onCancel = function() {
        this.executeCallback(onCancel);
        resolve( false );
      };
      let onClose = options.onClose;
      options.onClose = function () {
        this.executeCallback(onClose);
        resolve( false );
      };

      pfyPopup( options );
    });
} // pfyPopupPromise



/*
pfyConfirm('Test')
    .then(
        (data) => { mylog('success: ' + data); },
        (data) => { mylog('failed: ' + data);  }
    );
 */
function pfyConfirm( options ) {
  if (typeof options === 'string') {
    let text = options;
    options = {};
    options.text = text;
  }
  if (typeof options.triggerSource === 'undefined') {
    options.triggerSource = true;
  }
  options.header = `{{pfy-confirm-header}}`;
  options.closeOnBgClick = false;
  options.closeButton = true;
  return new Promise(function(resolve, reject) {
      options.onOk = function() {
        resolve( true );
      };

      // rejecting reactions:
      options.onCancel = function() {
        reject( false );
      };
      if (typeof options.buttons === 'undefined') {
        options.buttons = 'Cancel,Ok';
      }
      pfyPopup( options );
    });
} // pfyConfirm



/*
pfyAlert('Alert!');
 */
function pfyAlert( options ) {
  if (typeof options === 'string') {
    let text = options;
    options = {};
    options.text = text;
  }
  if (typeof options.triggerSource === 'undefined') {
    options.triggerSource = true;
  }
  options.closeOnBgClick = true;
  options.closeButton = true;
  options.buttons = 'Ok';
  options.header = `{{pfy-alert-header}}`;
  return new Promise(function(resolve, reject) {
      options.onOk = function() {
        resolve( true );
      };
      pfyPopup( options );
    });
} // pfyAlert




function pfyPopupClose() {
  domForEach('.pfy-popup-bg', (popup) => {
    popup.remove();
  })
  document.body.classList.remove('pfy-no-scroll', 'pfy-modal');
  document.querySelector('.pfy-page').removeAttribute('inert');
} // pfyPopupClose

