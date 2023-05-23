document.addEventListener('DOMContentLoaded', function() {
  var errorElement = document.querySelector('.pfy-form .error');
  if (errorElement) {
    var input = errorElement.parentNode.querySelector('input');
    input.scrollIntoView({ block: 'end' });
    setTimeout(function() {
      input.focus();
    }, 500);
  }
});

var forms = document.querySelectorAll('.pfy-form');
if (forms) {
  forms.forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (typeof submitNow !== 'undefined') {
        return;
      }
      e.preventDefault();
      var check = pfyCheck(form);
      if (!check) {
        e.stopPropagation();
        return;
      }
      doSubmitForm(form);
    });
  });
}

var cancelInputs = document.querySelectorAll('input.pfy-cancel');
if (cancelInputs) {
  cancelInputs.forEach(function (input) {
    input.addEventListener('click', function (e) {
      e.preventDefault();
      var form = input.closest('.pfy-form');
      form.reset();
    });
  });
}

function pfyCheck(form) {
  var check = true;
  var checkElement = form.querySelector('[data-check]');
  if (checkElement) {
    const name = checkElement.dataset.check;
    const wrapper = document.querySelector('input[name='+name+']').closest('.pfy-elem-wrapper');
    const label = wrapper.querySelector('.pfy-label-wrapper span').innerHTML.replace(/(.*):(.*)/, "$1");
    const value = checkElement.value;
    const referenceElement = form.querySelector('[name="' + name + '"]');
    const referenceValue = referenceElement.value;
    check = !value;
    if (!check) {
      openCheckPopup(form, referenceValue, label);
    }
  }
  return check;
}

function openCheckPopup(form, referenceValue, referenceName) {
  var text = `<label>{{ pfy-form-override-honeypot }} <input type="text" id="pfy-check-input"></label>`;
  text = text.replace(/%refName/, referenceName);
  pfyPopupPromise({
    text: text,
    buttons: 'cancel,confirm',
    closeCallback: function() {
      pfyResponseValue = document.querySelector('#pfy-check-input').value;
    },
  }).then(
    function() {
      if (referenceValue.charAt(0).toLowerCase() === pfyResponseValue.toLowerCase()) {
        doSubmitForm(form);
      } else {
        pfyAlert({
          text: `{{ pfy-form-honeypot-failed }}`,
        });
      }
    }
  );
}

function doSubmitForm(form) {
  var buttons = form.querySelectorAll('.button');
  buttons.forEach(function(button) {
    button.disabled = true;
    button.classList.add('pfy-button-disabled');
  });

  var clone = form.cloneNode(true);
  var passwordInputs = clone.querySelectorAll('input[type="password"]');
  passwordInputs.forEach(function(input) {
    input.value = '******';
  });
  var data = new FormData(clone);
  var dataStr = JSON.stringify(Array.from(data.entries()));
  serverLog('Browser submits: ' + dataStr, 'form-log.txt');

  submitNow = true;
  form.submit();
}
