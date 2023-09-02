// === Message Box =================================


var pfyMsgInitialized = null;

function setupMessageHandler(delay) {
    setTimeout(function() {
        let msgbox = document.querySelector('.pfy-msgbox');
        msgbox.classList.add('pfy-msg-show');
    }, delay);

    setTimeout(function() {
        let msgbox = document.querySelector('.pfy-msgbox');
        msgbox.classList.remove('pfy-msg-show');
    }, 5000);

    let msgbox = document.querySelector('.pfy-msgbox');
    msgbox.addEventListener('click', function() {
        this.classList.toggle('pfy-msg-show');
    });

    msgbox.addEventListener('dblclick', function() {
        this.style.display = 'none';
    });

    pfyMsgInitialized = true;
}

function showMessage(txt) {
    let msgbox = document.querySelector('.pfy-msgbox');
    if (msgbox) {
        msgbox.parentNode.removeChild(msgbox);
    }

    let newMsgbox = document.createElement('div');
    newMsgbox.className = 'pfy-msgbox';
    newMsgbox.innerHTML = '<p>' + txt + '</p>';

    document.body.insertBefore(newMsgbox, document.body.firstChild);
    setupMessageHandler(500);
}

document.addEventListener('DOMContentLoaded', function() {
    let msgbox = document.querySelector('.pfy-msgbox');
    if (msgbox) {
        setupMessageHandler(500);
    }
});
