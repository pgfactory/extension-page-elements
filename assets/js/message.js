// === Message Box =================================

"use strict";

let pfyMsgHideTimer = null;

function setupMessageHandler(delay) {
    const msgbox = document.querySelector('.pfy-msgbox');
    if (!msgbox) {
        return;
    }

    if (pfyMsgHideTimer) {
        clearTimeout(pfyMsgHideTimer);
    }

    setTimeout(() => {
        msgbox.classList.add('pfy-msg-show');
        pfyMsgHideTimer = setTimeout(() => {
            msgbox.classList.remove('pfy-msg-show');
        }, 5000);
    }, delay);

    msgbox.addEventListener('click', function () {
        this.classList.toggle('pfy-msg-show');
    });

    msgbox.addEventListener('dblclick', function () {
        this.style.display = 'none';
    });
}

function showMessage(txt) {
    const oldMsgbox = document.querySelector('.pfy-msgbox');
    if (oldMsgbox) {
        oldMsgbox.remove();
    }

    const msgbox = document.createElement('div');
    msgbox.className = 'pfy-msgbox';
    const p = document.createElement('p');
    p.textContent = txt;
    msgbox.appendChild(p);

    document.body.insertBefore(msgbox, document.body.firstChild);
    setupMessageHandler(500);
}

domReady(() => {
    const msgbox = document.querySelector('.pfy-msgbox');
    if (msgbox) {
        setupMessageHandler(500);
    }
});
