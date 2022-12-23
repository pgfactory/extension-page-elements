/*  pfyPopup */

"use strict";

var currentlyOpenPopup = false;

function pfyPopup( options, index ) {
    var parent = this;

    this.init = function (options) {
      this.inihibitClosing = false;
      this.triggerInitialized = false;
      this.parseArgs( options );
      this.setupTriggers();
    }; // init



    this.parseArgs = function () {
        if (typeof options === 'string') {
            const str = options;
            options = null;
            options = { text: str };
        }
        this.options = options;
        this.text  = (typeof options.text !== 'undefined' && options.text)? options.text : ''; // text synonym for content
        this.content  = (typeof options.content !== 'undefined' && options.content)? options.content : this.text;

        this.id  = (typeof options.id !== 'undefined' && options.id)? options.id : 'pfy-popup';

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
        this.closeOnBgClick = (typeof options.closeOnBgClick !== 'undefined' && options.closeOnBgClick) ? options.closeOnBgClick : true;
        this.closeButton = (typeof options.closeButton !== 'undefined' && options.closeButton) ? options.closeButton : true;
        this.buttons = (typeof options.buttons === 'string' && options.buttons) ? options.buttons.split(/\s*,\s*/) : [];

        // omit closeButton if buttons are defined (unless header is active):
        if ((typeof options.closeButton === 'undefined') && (this.buttons.length) && !this.header) {
            this.closeButton = false;
        }
        this.callbackArg = (typeof options.callbackArg !== 'undefined' && options.callbackArg) ? options.callbackArg : '';
        this.onConfirm = (typeof options.onConfirm !== 'undefined' && options.onConfirm) ? options.onConfirm : '';
        this.onCancel = (typeof options.onCancel !== 'undefined' && options.onCancel) ? options.onCancel : '';
        this.onOk = (typeof options.onOk !== 'undefined' && options.onOk) ? options.onOk : '';
        this.onContinue = (typeof options.onContinue !== 'undefined' && options.onContinue) ? options.onContinue : '';
        this.onClose = (typeof options.onClose !== 'undefined' && options.onClose) ? options.onClose : '';
        this.closeCallback = (typeof options.closeCallback !== 'undefined' && options.closeCallback) ? options.closeCallback : '';

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
          <div id="${parent.id}" class="${wrapperClass}">
              <dialog class="${cls}">
                   ${header}
                  <div class="${containerClass}">
                      ${content}
                  </div>
                  ${this.buttonHtml}
              </dialog>
          </div>`;

    $(this.anker).append(html);
    return $( '#' + this.id + ' .pfy-popup-wrapper');
  }; // renderContent


  this.getContentFrom = function() {
    let contentFrom = this.contentFrom;
    let $cFrom = null;
    if (typeof contentFrom === 'string') {
      if ((contentFrom.charAt(0) !== '#') && (contentFrom.charAt(0) !== '.')) {
        contentFrom = '#' + contentFrom;
      }
      $cFrom = $( contentFrom );

    } else if ( (typeof contentFrom[0] !== false)  && contentFrom.length) { // case jQ-object
      $cFrom = contentFrom;
    } else {
      alert('Error in popup.js:prepareContent() -> unable to handle contentFrom');
      return ''; // error
    }
    return $cFrom.html();
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
      // this.closeButton = '<button class="pfy-close-button `pfy-popup-close-button`" type="button">&#x0D7;</button>';
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
      // } else if (this.triggerEvent === 'mouseover') {
      //   $(this.trigger).on('mouseout', function( e ){
      //     parent.modal = false;
      //     e.stopPropagation();
      //     e.stopImmediatePropagation();
      //     e.preventDefault();
      //     parent.close();
      //   });
      }
      $(this.trigger).on(this.triggerEvent, function( e ){
          e.stopPropagation();
          e.preventDefault();
          parent.open();
      });
      this.triggerInitialized = true;
    }
  }; // setupTriggers



  this.setupButtonTriggers = function () {
    $('.pfy-popup-buttons button').click(function () {
      let $button = $(this);

      // activate onXY callbacks:
      if ($button.hasClass('pfy-popup-btn-cancel') && parent.onCancel) {
        parent.inihibitClosing = !executeCallback(parent.onCancel);

      } else if ($button.hasClass('pfy-popup-btn-close') && parent.onClose) {
        parent.inihibitClosing = !executeCallback(parent.onClose);

      } else if ($button.hasClass('pfy-popup-btn-confirm') && parent.onConfirm) {
        parent.inihibitClosing = !executeCallback(parent.onConfirm);

      } else if ($button.hasClass('pfy-popup-btn-ok') && parent.onOk) {
        parent.inihibitClosing = !executeCallback(parent.onOk);

      } else if ($button.hasClass('pfy-popup-btn-continue') && parent.onContinue) {
        parent.inihibitClosing = !executeCallback(parent.onContinue);
      }
      parent.close();
    });
  }; // setupButtonTriggers



  this.setupCloseTriggers = function () {
    $('.pfy-popup-wrapper').on('click', function (e) {
      e.stopPropagation();
    });
    $('.pfy-popup-close-button, .pfy-button-cancel').on('click', function () {
      parent.close();
    })
    $('.pfy-popup-bg.pfy-close-on-bg-click').on('click', function (e) {
      parent.close();
    });
  }; // setupCloseTriggers



  this.setupKeyHandlers = function () {
    let $body = $('body');
    $body.on('keyup', function(e) {
        const key = e.which;
        if (key === 27) {                // ESC
          if (parent.onCancel && $('.pfy-popup-btn-cancel').length) {
            parent.inihibitClosing = parent.onCancel();

          } else if (parent.onClose && $('.pfy-popup-btn-close').length) {
            parent.inihibitClosing = parent.onClose();
          }
          parent.close();
          e.preventDefault();
        }
      });
    $body.on('keyup', '.pfy-popup-wrapper', function(e) {
        const key = e.which;
        if (key === 13) {         // Return
          if (parent.onConfirm && $('.pfy-popup-btn-confirm').length) {
            parent.inihibitClosing = parent.onConfirm();

          } else if (parent.onOk && $('.pfy-popup-btn-ok').length) {
            parent.inihibitClosing = parent.onOk();

          } else if (parent.onContinue && $('.pfy-popup-btn-continue').length) {
            parent.inihibitClosing = parent.onContinue();
          }
          parent.close();
          e.preventDefault();
        }
      });
  }; // setupKeyHandlers


  this.trapFocus = function () {
    const element = this.$popup[0];
    const focusableEls = element.querySelectorAll('a[href]:not([disabled]), button:not([disabled]), ' +
      'textarea:not([disabled]), input[type="text"]:not([disabled]), input[type="radio"]:not([disabled]), ' +
      'input[type="checkbox"]:not([disabled]), select:not([disabled])');
    const firstFocusableEl = focusableEls[0];
    const lastFocusableEl = focusableEls[focusableEls.length - 1];
    const KEYCODE_TAB = 9;

    element.addEventListener('keydown', function(e) {
      var isTabPressed = (e.key === 'Tab' || e.keyCode === KEYCODE_TAB);

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
    if (typeof $.ueSetGlobalCb === 'undefined') {
        alert('Error: popup option "draggable" initiated, but module EVENT_UE not loaded');
    }
    this.lastX = 0;
    this.lastY = 0;
    $( '.pfy-popup-header > div', parent.$popup )
        .bind( 'udragstart', function(e) {
            e.stopPropagation();
        })
        .bind( 'udragmove',  function(e) {
            e.stopPropagation();
            parent.$popup.css({ left: e.px_tdelta_x * -1, top: e.px_tdelta_y * -1 });
        })
        .bind( 'udragend',   function(e) {
            e.stopPropagation();
            parent.lastX -= e.px_tdelta_x;
            parent.lastY -= e.px_tdelta_y;
            parent.$popup.css({ transform: 'translate('+ parent.lastX +'px, ' + parent.lastY + 'px)', top:0,left:0 });
        })
        .bind( 'click',      function(e) {
            e.stopPropagation();
        });
  }; // initDraggable


  this.open = function () {
    // prevent multiple popups being open:
    if (currentlyOpenPopup) {
      currentlyOpenPopup.close();
      currentlyOpenPopup = false;
    }
// mylog('modal: ' + this.modal);
    this.renderButtons();
    this.$popup = this.renderContent();
    this.initDraggable();
    this.setupButtonTriggers();
    this.setupKeyHandlers();
    this.setupCloseTriggers();
    this.$popup.parent().show();
    this.$popup.show();

    // freeze background:
    if (this.modal) {
      $('body').addClass('pfy-no-scroll pfy-modal').attr('aria-hidden', 'true');
    } else {
      $('body').addClass('pfy-no-scroll').attr('aria-hidden', 'true');
    }
    $('.pfy-page').attr('inert','');

    // for accessibility: make sure focus can't go outside of popup. (workaround while 'inert' is not reliable)
    this.trapFocus();
    setTimeout(function () {
      $('.pfy-popup-btn-ok, .pfy-popup-btn-confirm, .pfy-popup-btn-continue', parent.$popup).focus();
    }, 50);
  }; // open



  this.close = function () {
    // execute closeCallback and check for inihibitClosing popup:
    if (this.inihibitClosing || !this.executeCallback(parent.closeCallback)) {
      this.inihibitClosing = false;
      return;
    }

    // close popup now:
    if (typeof parent.$popup !== 'undefined') {
      parent.$popup.parent().remove();
    }
    $('body').removeClass('pfy-no-scroll pfy-modal').removeAttr('aria-hidden');
    // $('body').removeClass('pfy-no-scroll').removeAttr('aria-hidden');
    $('.pfy-page').removeAttr('inert');
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
      '\t\t<dd>[click, right-click, dblclick, blur] Specifies the type of event that shall open the popup. </dd>\n' +

      '\t<dt>closeButton:</dt>\n' +
      '\t\t<dd>[true,false] Specifies whether a close button shall be displayed in the upper right corner (default: true). </dd>\n' +

      '\t<dt>closeOnBgClick:</dt>\n' +
      '\t\t<dd>[true,false] Specifies whether clicks on the background will close the popup (default: true). </dd>\n' +

      '\t<dt>buttons:</dt>\n' +
      '\t\t<dd>[Comma-separated-list of button labels] Example: "Cancel,Ok".<br>'+
      'Predefined: "Cancel", "Close", "Ok", "Continue", "Confirm".</dd>\n' +

      '\t<dt>closeCallback:</dt>\n' +
      '\t\t<dd>[function or string] A function to be executed upon closing the popup, no matter which '+
          'way closing was initiated (including click on background).</dd>\n' +

      '\t<dt>onOk, onConfirm, onContinue, onContinue, onCancel, onClose:</dt>\n' +
      '\t\t<dd>[function or string] Callback function invoked when a corresponding key is activated</dd>\n' +

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
      options.onOk = function() {
        resolve( true );
      };
      options.onContinue = function() {
        resolve( true );
      };
      options.onConfirm = function() {
        resolve( true );
      };

      // rejecting reactions:
      options.onCancel = function() {
        reject( false );
      };
      options.onClose = function() {
        reject( false );
      };
      currentlyOpenPopup = pfyPopup( options );
    });
} // pfyPopupPromise




function pfyConfirm( options ) {
  if (typeof options === 'string') {
    let text = options;
    let options = {};
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
    let options = {};
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
  if (currentlyOpenPopup) {
    currentlyOpenPopup.close();
    currentlyOpenPopup = false;
  }
  // just in case some popup code remained in body:
  let $popupElem = $('#pfy-popup');
  if ($popupElem) {
    $popupElem.remove();
  }
} // pfyPopupClose




// provide as jQuery extension functions:
(function( $ ){
    $.fn.pfyPopup = function( options ) {
      if (typeof options === 'undefined') {
        options = {};
      } else if (typeof options === 'string') {
        const text = options;
        options = {};
        options.text = text;
      }
      let $this = $(this);
      options.text = $this.html();
      options.buttons = (typeof options.buttons !== 'undefined')? options.buttons : 'Ok';
      options.header = (typeof options.header !== 'undefined')? options.header : true;
      options.closeButton = (typeof options.closeButton !== 'undefined')? options.closeButton : true;

      return pfyPopupPromise( options );
    }; // $.fn.pfyPopup


    $.fn.pfyConfirm = function( options ) {
      if (typeof options === 'undefined') {
        options = {};
      } else if (typeof options === 'string') {
        const text = options;
        options = {};
        options.text = text;
      }
      let $this = $(this);
      options.text = $this.html();
      return pfyConfirm( options );
    }; // $.fn.pfyConfirm


    $.fn.pfyAlert = function( options ) {
      if (typeof options === 'undefined') {
        options = {};
      } else if (typeof options === 'string') {
        const text = options;
        options = {};
        options.text = text;
      }
      let $this = $(this);
      options.text = $this.html();
      return pfyAlert( options );
    }; // $.fn.pfyAlert
})( jQuery );

