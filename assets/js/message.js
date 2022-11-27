// === Message Box =================================
var pfyMsgInitialized = null;
function setupMessageHandler( delay) {
    setTimeout(function () {
        $('.pfy-msgbox').addClass('pfy-msg-show');
    }, delay);
    setTimeout(function () {
        $('.pfy-msgbox').removeClass('pfy-msg-show');
    }, 5000);
    $('.pfy-msgbox').click(function () {
        $(this).toggleClass('pfy-msg-show');
    }).dblclick(function () {
        $(this).hide();
    });
    pfyMsgInitialized = true;
} // setupMessageHandler



function showMessage( txt ) {
    $('.pfy-msgbox').remove();
    $('body').prepend( '<div class="pfy-msgbox"><p>' + txt + '</p></div>' );
    setupMessageHandler(0);
} // showMessage



$( document ).ready(function() {
    if ($('.pfy-msgbox').length) {
        setupMessageHandler(800);
    }
});
