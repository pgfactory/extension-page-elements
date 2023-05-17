// forms

$(document).ready(function() {

  // handle errors in input:
  $('.pfy-form .error').first().each(function() {
    let $input = $('input', $(this).parent());
    $input[0].scrollIntoView(false);
    setTimeout(function() {
      $input.focus();
    }, 500);
  });
});
