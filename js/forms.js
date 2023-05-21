// forms

$(document).ready(function() {

  // handle errors in input -> scroll into view:
  $('.pfy-form .error').first().each(function() {
    let $input = $('input', $(this).parent());
    $input[0].scrollIntoView(false);
    setTimeout(function() {
      $input.focus();
    }, 500);
  });
});


// handle submit -> send log-info to server before submitting:
$('.pfy-form').submit(function (e) {
  if (typeof submitNow !== 'undefined') {
    return;
  }
  e.preventDefault();
  let $form = $(this);
  $('.button', $form).prop('disabled', true).addClass('pfy-button-disabled');

  // create copy of form data and replace any password fields with '*****':
  let $clone = $form.clone();
  $('input[type=password]', $clone).val('******');
  const dataStr = JSON.stringify( $clone.serializeArray() );
  serverLog('Browser submits: ' + dataStr, 'form-log.txt');

  submitNow = true;
  $form.submit();
});


// handle click on Cancel
$('input.pfy-cancel').click(function (e) {
  e.preventDefault();
  let $form = $(this).closest('.pfy-form');
  $form.trigger('reset');
});
