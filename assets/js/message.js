// === Message Box =================================

"use strict";

console.debug("message.js");

/**
 * MessageBox
 *
 * Encapsulates the "toast"-style message box behaviour that was
 * previously spread across module-level functions and a module-level
 * timer variable (pfyMsgHideTimer).
 *
 * Usage:
 *   const messageBox = new MessageBox();
 *   messageBox.init();          // picks up an existing .pfy-msgbox on the page
 *   messageBox.show('Saved!');  // creates/replaces the box and shows it
 *
 * Backward-compatible global wrappers (showMessage/hideMessage) are
 * provided at the bottom so existing call sites don't need to change.
 */
class MessageBox {
    /**
     * @param {Object} [options]
     * @param {string} [options.selector='.pfy-msgbox']   Selector/class for the message box element.
     * @param {string} [options.visibleClass='pfy-msg-show'] Class toggled to show/hide the box.
     * @param {number} [options.showDelay=500]            Delay (ms) before the box becomes visible.
     * @param {number} [options.hideDelay=5000]           How long (ms) the box stays visible before auto-hiding.
     */
    constructor(options = {}) {
        this.selector = options.selector || '.pfy-msgbox';
        this.visibleClass = options.visibleClass || 'pfy-msg-show';
        this.showDelay = options.showDelay ?? 500;
        this.hideDelay = options.hideDelay ?? 5000;

        this.msgbox = null;
        this.showTimer = null;
        this.hideTimer = null;

        // Bind once so we can safely add/remove the same listener reference.
        this._onClick = this._onClick.bind(this);
        this._onDblClick = this._onDblClick.bind(this);
    }

    /**
     * Picks up an already-existing message box in the DOM (e.g. rendered
     * server-side) and wires up its behaviour. No-op if none is found.
     */
    init() {
        this.msgbox = document.querySelector(this.selector);
        if (!this.msgbox) {
            return;
        }
        this._setupHandler(this.showDelay);
    }

    /**
     * Creates a fresh message box with the given text, replacing any
     * existing one, and shows it.
     * @param {string} txt
     */
    show(txt) {
        const oldMsgbox = document.querySelector(this.selector);
        if (oldMsgbox) {
            oldMsgbox.remove();
        }

        const msgbox = document.createElement('div');
        msgbox.className = this.selector.replace(/^\./, '');

        const p = document.createElement('p');
        p.textContent = txt;
        msgbox.appendChild(p);

        document.body.insertBefore(msgbox, document.body.firstChild);

        this.msgbox = msgbox;
        this._setupHandler(this.showDelay);
    }

    /**
     * Hides the currently tracked message box, if any.
     */
    hide() {
        if (this.msgbox) {
            this.msgbox.classList.remove(this.visibleClass);
        }
    }

    /**
     * Wires up the show/auto-hide timers and click handlers for the
     * currently tracked message box.
     * @private
     * @param {number} delay
     */
    _setupHandler(delay) {
        if (!this.msgbox) {
            return;
        }

        // Clear any previously scheduled timers so repeated calls don't stack.
        if (this.showTimer) {
            clearTimeout(this.showTimer);
        }
        if (this.hideTimer) {
            clearTimeout(this.hideTimer);
        }

        this.showTimer = setTimeout(() => {
            this.msgbox.classList.add(this.visibleClass);
            this.hideTimer = setTimeout(() => {
                this.hide();
            }, this.hideDelay);
        }, delay);

        // Remove before adding so listeners never pile up across calls.
        this.msgbox.removeEventListener('click', this._onClick);
        this.msgbox.removeEventListener('dblclick', this._onDblClick);
        this.msgbox.addEventListener('click', this._onClick);
        this.msgbox.addEventListener('dblclick', this._onDblClick);
    }

    /** @private */
    _onClick() {
        this.msgbox.classList.toggle(this.visibleClass);
    }

    /** @private */
    _onDblClick() {
        this.msgbox.style.display = 'none';
    }
}

// --- Backward-compatible global API --------------------------------
const pfyMessageBox = new MessageBox();

function showMessage(txt) {
    pfyMessageBox.show(txt);
}

function hideMessage() {
    pfyMessageBox.hide();
}

domReady(() => {
    pfyMessageBox.init();

    pfyHandleEvent('body', ev => {
      // skip clicks on message itself:
      if (!document.querySelector('.pfy-msg-show') || ev.target.closest('.pfy-msg-show')) {
        return;
      }
      hideMessage();
    })
});
