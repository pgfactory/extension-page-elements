// === Message Box =================================
var lzyMsgInitialized = null;
function setupMessageHandler( delay) {
    setTimeout(function () {
        $('.lzy-msgbox').addClass('lzy-msg-show');
    }, delay);
    setTimeout(function () {
        $('.lzy-msgbox').removeClass('lzy-msg-show');
    }, 5000);
    $('.lzy-msgbox').click(function () {
        $(this).toggleClass('lzy-msg-show');
    }).dblclick(function () {
        $(this).hide();
    });
    lzyMsgInitialized = true;
} // setupMessageHandler



function showMessage( txt ) {
    $('.lzy-msgbox').remove();
    $('body').prepend( '<div class="lzy-msgbox"><p>' + txt + '</p></div>' );
    setupMessageHandler(0);
} // showMessage



$( document ).ready(function() {
    if ($('.lzy-msgbox').length) {
        setupMessageHandler(800);
    }
});
