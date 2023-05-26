/*
 *  forms.js
 */

"use strict";


const pfyFormsHelper = {

  init() {
    const forms = document.querySelectorAll('.pfy-form');
    if ((typeof forms !== 'undefined') && forms.length) {
      forms.forEach(function(form) {
        pfyFormsHelper.handleErrorInForm(form);
        pfyFormsHelper.setupCancelButtonHandler(form);
        pfyFormsHelper.setupSubmitHandler(form);
      });
    }
  }, // init



  handleErrorInForm(form) {
    document.addEventListener('DOMContentLoaded', function() {
      const errorElement = form.querySelector('.error');
      if (errorElement) {
        const input = errorElement.parentNode.querySelector('input');
        input.scrollIntoView({ block: 'end' });
        setTimeout(function() {
          input.focus();
        }, 500);
      }
    });
  }, // handleErrorInForm


  setupCancelButtonHandler(form) {
    const cancelInputs = form.querySelectorAll('input.pfy-cancel');
    if (cancelInputs.length) {
      cancelInputs.forEach(function(input) {
        input.addEventListener('click', function(e) {
          e.preventDefault();
          pfyFormsHelper.clearForm(form);
          form.reset();
        });
      });
    }
  }, // setupCancelButtonHandler


  setupSubmitHandler(form) {
    form.addEventListener('submit', function(e) {
      if (typeof pfyFormsHelper.submitNow !== 'undefined') {
        return;
      }
      e.preventDefault();
      const check = pfyFormsHelper.checkHoneypot(form);
      if (!check) {
        e.stopPropagation();
        return;
      }
      pfyFormsHelper.doSubmitForm(form);
    });
  }, // setupSubmitHandler



  clearForm(form) {
    let fields = form.querySelectorAll('input');
    if (fields.length) {
      const types = 'hidden,submit,cancel,checkbox,radio,button';
      fields.forEach(function (field) {
        const type = field.getAttribute('type');
        const readonly = field.getAttribute('readonly') !== null;
        if (!readonly && !types.includes(type)) {
          const val = field.dataset.default ?? '';
          field.setAttribute('value', val);
        }
      });

      fields = form.querySelectorAll('textarea');
      if (fields.length) {
        fields.forEach(function (field) {
          field.innerText = field.dataset.default ?? '';
        });
      }

      fields = form.querySelectorAll('option, input[type=checkbox], input[type=radio]');
      if (fields.length) {
        fields.forEach(function (field) {
          field.removeAttribute('selected');
          field.removeAttribute('checked');
          const preset = field.dataset.preset ?? '';
          const val = field.getAttribute('value') ?? '';
          if (val && preset === val) {
            field.setAttribute('selected', '');
            field.setAttribute('checked', '');
          }
        });
      }
    }
    const errors = form.querySelectorAll('.error');
    if ((typeof errors !== 'undefined') && errors.length) {
      errors.forEach(function(field) {
        field.remove();
      });
    }
  }, // clearForm



  checkHoneypot(form) {
    let check = true;
    const checkElement = form.querySelector('[data-check]');
    if (checkElement) {
      const name = checkElement.dataset.check;
      const wrapper = document.querySelector('input[name=' + name + ']').closest('.pfy-elem-wrapper');
      const label = wrapper.querySelector('.pfy-label-wrapper label').innerText.replace(/(.*):(.*)/, "$1");
      const value = checkElement.value;
      const referenceElement = form.querySelector('[name="' + name + '"]');
      const referenceValue = referenceElement.value;
      check = !value;
      if (!check) {
        pfyFormsHelper.openCheckPopup(form, referenceValue, label);
      }
    }
    return check;
  }, // checkHoneypot


  openCheckPopup(form, referenceValue, referenceName) {
    let text = `<label>{{ pfy-form-override-honeypot }} <input type="text" id="pfy-check-input"></label>`;
    text = text.replace(/%refName/, referenceName);
    let pfyResponseValue;
    pfyResponseValue = '?';
    pfyPopupPromise({
      text: text,
      header: 'AntiSpam',
      buttons: 'cancel,confirm',
      onClose: function() {
        pfyResponseValue = document.querySelector('#pfy-check-input').value;
      },
    }).then(
      function() {
        if (referenceValue.charAt(0).toLowerCase() === pfyResponseValue.toLowerCase()) {
          pfyFormsHelper.doSubmitForm(form);
        } else {
          pfyAlert({
            text: `{{ pfy-form-honeypot-failed }}`,
          });
        }
      },
      function (error) { }
    );
  }, // openCheckPopup


  doSubmitForm(form) {
    const buttons = form.querySelectorAll('.button');
    buttons.forEach(function(button) {
      button.disabled = true;
      button.classList.add('pfy-button-disabled');
    });

    const clone = form.cloneNode(true);
    const passwordInputs = clone.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(function(input) {
      input.value = '******';
    });
    const data = new FormData(clone);
    const dataStr = JSON.stringify(Array.from(data.entries()));
    serverLog('Browser submits: ' + dataStr, 'form-log.txt');

    pfyFormsHelper.submitNow = true;
    form.submit();
  }, // doSubmitForm

}; // pfyFormsHelper

pfyFormsHelper.init();
