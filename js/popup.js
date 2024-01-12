/*
* pfyPopup
* to see help, invoke
*   pfyPopup({text: 'help'});
*/

"use strict";

var currentlyOpenPopup = false;

function pfyPopup( options ) {
  var parent = this;

  this.init = function (options) {
    this.checkPopupAlreadyOpen();
    this.inihibitClosing = false;
    this.triggerInitialized = false;
    this.parseArgs( options );
    this.setupTriggers();
  }; // init


  this.checkPopupAlreadyOpen = function() {
    const popupElem = document.querySelector('#pfy-popup');
    if (popupElem) {
      if (currentlyOpenPopup) {
        currentlyOpenPopup.close();
        currentlyOpenPopup = false;
      } else {
        try {
          popupElem.remove();
        } catch (error) {}
      }
    }
  }


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

    if (typeof options.id === 'undefined') {
      this.id ='pfy-popup';
    } else {
      this.id  = options.id;
    }

    this.contentFrom = (typeof options.contentFrom !== 'undefined' && options.contentFrom) ? options.contentFrom : ''; // contentFrom synonyme for contentRef

    this.modal  = (typeof options.modal !== 'undefined' && options.modal)? options.modal : true;

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
    if (typeof options.closeOnBgClick === 'undefined') {
      this.closeOnBgClick = true;
    } else {
      this.closeOnBgClick = options.closeOnBgClick;
    }
    this.closeButton = (typeof options.closeButton !== 'undefined' && options.closeButton) ? options.closeButton : true;
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

    if (this.content === 'help') {
      this.content = this.renderHelp();
      this.closeButton = true;
      this.closeOnBgClick = true;
      this.draggable = true;
      this.trigger = true;
      this.containerClass = 'pfy-macro-help pfy-encapsulated';
      this.header = 'Options for popup()';
    }
  }; // parseArgs



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

    let containerClass = 'pfy-popup-container pfy-scroll-hints';
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
    pElement.setAttribute('id',parent.id);
    pElement.setAttribute('class', wrapperClass);
    pElement.innerHTML = html;
    ankerElement.appendChild(pElement);

    return document.querySelector( '#' + this.id + ' .pfy-popup-wrapper');
  }; // renderContent


  this.getContentFrom = function() {
    let contentFrom = this.contentFrom;
    let $cFrom = null;
    if (typeof contentFrom === 'string') {
      if ((contentFrom.charAt(0) !== '#') && (contentFrom.charAt(0) !== '.')) {
        contentFrom = '#' + contentFrom;
      }
      $cFrom = document.querySelector( contentFrom );

    } else if ( (typeof contentFrom !== false)  && contentFrom.length) { // case jQ-object
      $cFrom = contentFrom;
    } else {
      alert('Error in popup.js:prepareContent() -> unable to handle contentFrom');
      return ''; // error
    }
    return $cFrom.outerHTML;
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


  this.setupTriggers = function () {
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
  }; // setupTriggers



  this.setupButtonTriggers = function () {
    const buttons = document.querySelectorAll('.pfy-popup-buttons button');
    if (buttons.length) {
      buttons.forEach(function (button) {
        button.addEventListener('click', function () {
          const btnClasses = this.classList;
          if (btnClasses.contains('pfy-popup-btn-cancel') && parent.onCancel) {
            parent.inihibitClosing = !executeCallback(parent.onCancel);

          } else if (btnClasses.contains('pfy-popup-btn-ok') && parent.onOk) {
            parent.inihibitClosing = !executeCallback(parent.onOk);

          } else if (btnClasses.contains('pfy-popup-btn-continue') && parent.onContinue) {
            parent.inihibitClosing = !executeCallback(parent.onContinue);

          } else if (btnClasses.contains('pfy-popup-btn-confirm') && parent.onConfirm) {
            parent.inihibitClosing = !executeCallback(parent.onConfirm);

          // if none of the above triggered, try the second button:
          } else if (btnClasses.contains('pfy-popup-btn-2') && parent.onOk) {
            parent.inihibitClosing = !executeCallback(parent.onOk);

          } else {
            parent.inihibitClosing = false;
          }

          if (!parent.inihibitClosing) {
            parent.close();
          }
        });
      });
    }
  }; // setupButtonTriggers



  this.setupCloseTriggers = function () {
    const elemWrapper = document.querySelector('.pfy-popup-wrapper');
    elemWrapper.addEventListener('click', function (e) {
      // stop propagation except for cancel button:
      if (!e.target.matches('input.pfy-cancel')) {
        e.stopPropagation();
      }
    });

    // set up close button:
    const elems = document.querySelectorAll('.pfy-popup-close-button');
    if (elems.length) {
      elems.forEach(function (elem) {
        elem.addEventListener('click', function (e) {
          parent.close();
        });
      });
    }

    // set up background click to close:
    const elem = document.querySelector('.pfy-popup-bg.pfy-close-on-bg-click');
    if (elem) {
      elem.addEventListener('click', function (e) {
        parent.close();
      });
    }
  }; // setupCloseTriggers


  this.setupKeyHandlers = function () {
    const $elem = document.querySelector('.pfy-popup-wrapper');

    $elem.addEventListener('keyup', function (e) {
      const key = e.key;

      // avoid popup's default action while within form fields:
      if (e.target.tagName === 'TEXTAREA') {
        return;
      }

      if (key === 'Escape' && (e.target.tagName !== 'INPUT')) {    // ESC
          if (parent.onCancel && document.querySelectorAll('.pfy-popup-btn-cancel').length) {
            parent.inihibitClosing = !executeCallback(parent.onCancel);

          } else if (parent.onClose && document.querySelectorAll('.pfy-popup-btn-close').length) {
            parent.inihibitClosing = !executeCallback(parent.onClose);
          }
          parent.close();
          e.preventDefault();
        }
        if (key === 'Enter') {         // Return
          if (parent.onConfirm && document.querySelectorAll('.pfy-popup-btn-confirm').length) {
            parent.inihibitClosing = !executeCallback(parent.onConfirm);

          } else if (parent.onOk && document.querySelectorAll('.pfy-popup-btn-ok').length) {
            parent.inihibitClosing = !executeCallback(parent.onOk);

          } else if (parent.onContinue && document.querySelectorAll('.pfy-popup-btn-continue').length) {
            parent.inihibitClosing = !executeCallback(parent.onContinue);
          }
          parent.close();
          e.preventDefault();
        }
      });
  }; // setupKeyHandlers


  this.trapFocus = function () {
    const popupWrapper = this.popup;
    const focusableEls = popupWrapper.querySelectorAll(
      'a[href]:not([disabled]), button:not([disabled]), ' +
      'textarea:not([disabled]), input[type="text"]:not([disabled]), input[type="radio"]:not([disabled]), ' +
      'input[type="checkbox"]:not([disabled]), select:not([disabled]), ' +
      'input[type="submit"]:not([disabled]), input[type="cancel"]:not([disabled]), input[type="button"]:not([disabled])');
    const firstFocusableEl = focusableEls[0];
    const lastFocusableEl = focusableEls[focusableEls.length - 1];
    const KEYCODE_TAB = 9;

    popupWrapper.addEventListener('keydown', function(e) {
      const isTabPressed = (e.key === 'Tab' || e.keyCode === KEYCODE_TAB);

      if (!isTabPressed) {
        return;
      }

      if ( e.shiftKey ) /* shift + tab */ {
        if (document.activeElement === firstFocusableEl) {
          lastFocusableEl.focus();
          e.preventDefault();
        }
      } else /* tab */ {
        if (document.activeElement === lastFocusableEl) {
          firstFocusableEl.focus();
          e.preventDefault();
        }
      }
    });
  } // trapFocus



  this.initDraggable = function () {
    if (!this.draggable) {
      return;
    }

    parent.translX = 0;
    parent.translY = 0;
    let mouseX = 0;
    let mouseY = 0;
    let mouseX0 = 0;
    let mouseY0 = 0;

    const elmntHeader = parent.popup.querySelector( '.pfy-popup-header > div' );
    elmntHeader.onpointerdown = dragMouseDown;

    function dragMouseDown(e) {
      e.preventDefault();
      mouseX = mouseX0 = e.clientX;
      mouseY = mouseY0 = e.clientY;
      document.onpointerup = closeDragElement;
      document.onpointermove = elementDrag;
    }

    function elementDrag(e) {
      e.preventDefault();
      mouseX = e.clientX;
      mouseY = e.clientY;
      let dx = parent.translX + mouseX - mouseX0;
      let dy = parent.translY + mouseY - mouseY0;
      parent.popup.style.transform = `translate(${dx}px, ${dy}px)`;
    }

    function closeDragElement() {
      // stop moving when mouse button is released:
      parent.translX += (mouseX - mouseX0);
      parent.translY += (mouseY - mouseY0);
      document.onpointerup = null;
      document.onpointermove = null;
    }
  }; // initDraggable


  this.open = function () {

    this.checkPopupAlreadyOpen();

    this.renderButtons();
    this.popup = this.renderContent();
    this.initDraggable();
    this.setupButtonTriggers();
    this.setupKeyHandlers();
    this.setupCloseTriggers();
    this.popup.parentElement.removeAttribute('style');
    this.popup.style.display = 'initial';
    this.popup.style.opacity = this.initialOpacity;

    // freeze background:
    if (this.modal) {
      document.body.classList.add('pfy-no-scroll', 'pfy-modal');

    } else {
      document.body.classList.add('pfy-no-scroll');
    }
    document.querySelector('.pfy-page').setAttribute('inert', '');

    // for accessibility: make sure focus can't go outside popup. (workaround while 'inert' is not reliable)
    this.trapFocus();

    // set focus to either first input, ok-button or close-button:
    if (this.autofocus) {
      setTimeout(function () {
        let $input = parent.popup.querySelectorAll('input')
        if ($input.length) {
          $input[0].focus();
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
      executeCallback(this.onOpen);
    }
    return this;
  }; // open


  this.close = function () {
    // exec onClose callback, if defined:
    if (this.onClose) {
      this.inihibitClosing = !executeCallback(parent.onClose);
    }
    if (!parent.popup) {
      // there is nothing to close:
      return;
    }

    // close popup now:
    const popupWrapper = parent.popup.parentElement;
    if (typeof popupWrapper !== 'undefined') {
      popupWrapper.remove();
    }
    document.body.classList.remove('pfy-no-scroll', 'pfy-modal');
    document.querySelector('.pfy-page').removeAttribute('inert');

    if (this.triggerElem) {
      this.triggerElem.setAttribute('aria-expanded', 'false');
      this.triggerElem.focus();
    }

    if (currentlyOpenPopup) {
      currentlyOpenPopup = false;
    }
  }; // close


  this.executeCallback = function(callback)
  {
    let res = true; // default is 'not inihibitClosing'
    if (typeof callback === 'function') {
       res = callback( parent, parent.callbackArg );

    } else if (typeof window[callback] === 'function') {
      res = window[callback]( parent, parent.callbackArg );
    }
    return (typeof res !== 'undefined') ? res: true;
  } // executeCallback



  this.renderHelp = function() {
    return '\t<dl>\n' +
      '\t<dt>text:</dt>\n' +
      '\t\t<dd>[html or string]Text to be displayed in the popup (for small messages, otherwise use contentFrom).<br>'+
      '"content" functions as synonym for "text". </dd>\n' +

      '\t<dt>contentFrom:</dt>\n' +
      '\t\t<dd>[string] Selector that identifies content which will be imported and displayed in the popup (example: "#box"). </dd>\n' +

      '\t<dt>header:</dt>\n' +
      '\t\t<dd>[string] Defines the text in the popup header. If false, no header is displayed.</dd>\n' +

      '\t<dt>triggerSource:</dt>\n' +
      '\t\t<dd>[true, string, false] If set, the popup opens upon activation of the trigger source element (example: "#btn"). </dd>\n' +

      '\t<dt>triggerEvent:</dt>\n' +
      '\t\t<dd>[click, right-click, dblclick, mouseover, blur] Specifies the type of event that shall open the popup. </dd>\n' +

      '\t<dt>closeButton:</dt>\n' +
      '\t\t<dd>[true,false] Specifies whether a close button shall be displayed in the upper right corner (default: true). </dd>\n' +

      '\t<dt>closeOnBgClick:</dt>\n' +
      '\t\t<dd>[true,false] Specifies whether clicks on the background will close the popup (default: true). </dd>\n' +

      '\t<dt>buttons:</dt>\n' +
      '\t\t<dd>[Comma-separated-list of button labels] Example: "Cancel,Ok".<br>'+
      'Predefined: "Cancel", "Close", "Ok", "Continue", "Confirm".</dd>\n' +

      '\t<dt>onOpen:</dt>\n' +
      '\t\t<dd>[function or string] Callback function invoked when popup is opened.<br>'+
      'Example: <code>onOpen: function(parent, callbackArg) { mylog(\'onOpen: \' + callbackArg); }</code></dd>\n' +

      '\t<dt>onOk, onConfirm, onContinue, onContinue, onCancel:</dt>\n' +
      '\t\t<dd>[function or string] Callback function invoked when corresponding key is activated.<br>'+
      'Example: <code>onOk: function(parent, callbackArg) { mylog(\'onOk: \' + callbackArg); }</code></dd>\n' +

      '\t<dt>onClose:</dt>\n' +
      '\t\t<dd>[function or string] Callback function invoked when popup is closed</dd>\n' +

      '\t<dt>callbackArg:</dt>\n' +
      '\t\t<dd>[any variable] Value or object that will be available inside callback functions.</dd>\n' +

      '\t<dt>id:</dt>\n' +
      '\t\t<dd>[string] ID to be applied to the popup element. (Default: pfy-popup-N)</dd>\n' +

      '\t<dt>wrapperClass:</dt>\n' +
      '\t\t<dd>[string] Class(es) applied to wrapper around Popup element. </dd>\n' +

      '\t<dt>popupClass:</dt>\n' +
      '\t\t<dd>[string] Class(es) applied to popup element. </dd>\n' +

      '\t<dt>containerClass:</dt>\n' +
      '\t\t<dd>[string] Class(es) applied to container element. </dd>\n' +

      '\t<dt>buttonsClass:</dt>\n' +
      '\t\t<dd>[string] Will be applied to buttons defined by "buttons" argument.</dd>\n' +

      '\t<dt>buttonClasses:</dt>\n' +
      '\t\t<dd>[Comma-separated-list of classes] Will be applied to corresponding buttons defined by "buttons" argument.</dd>\n' +

      '\t<dt>anker:</dt>\n' +
      '\t\t<dd>[string] If defined, popup will be placed inside elemented selected by "anker" (e.g. ".box"). '+
          'Default: "body". </dd>\n' +

      '\t</dl>\n';
  }; // renderHelp



  this.init( options );
  return this;
} // PfyPopup



// === convenience wrapper functions ============================================
function pfyPopupPromise( options ) {
    return new Promise(function(resolve, reject) {
      // affirmative reactions:
      let onOk = options.onOk;
      options.onOk = function() {
        executeCallback(onOk);
        resolve( true );
      };
      let onContinue = options.onContinue;
      options.onContinue = function() {
        executeCallback(onContinue);
        resolve( true );
      };
      let onConfirm = options.onConfirm;
      options.onConfirm = function() {
        executeCallback(onConfirm);
        resolve( true );
      };

      // rejecting reactions:
      let onCancel = options.onCancel;
      options.onCancel = function() {
        executeCallback(onCancel);
        resolve( false );
      };
      let onClose = options.onClose;
      options.onClose = function () {
        executeCallback(onClose);
        resolve( false );
      };

      currentlyOpenPopup = pfyPopup( options );
    });
} // pfyPopupPromise




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
      currentlyOpenPopup = pfyPopup( options );
    });
} // pfyConfirm




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
    currentlyOpenPopup = pfyPopup( options );
    });
} // pfyAlert




function pfyPopupClose() {
  // first try properly opened popups:
  if (currentlyOpenPopup) {
    currentlyOpenPopup.close();
    currentlyOpenPopup = false;
  }

  // just in case some popup code remained in body:
  const popups = document.querySelectorAll('.pfy-popup-bg');
  if (popups.length) {
    popups.forEach(function (popup) {
      popup.remove();
    });
  }
  document.body.classList.remove('pfy-no-scroll', 'pfy-modal');
  document.querySelector('.pfy-page').removeAttribute('inert');
} // pfyPopupClose



function pfyPopupHelp() {
  pfyPopup({text:'help'});
}
