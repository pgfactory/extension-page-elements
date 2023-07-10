/*
 *  forms.js
 */

"use strict";


const pfyFormsHelper = {

  init(forms) {
    if (typeof forms === 'string') {
      forms = document.querySelectorAll(forms);
    } else if (typeof form !== 'object') {
      forms = document.querySelectorAll('.pfy-form');
    }
    if ((typeof forms !== 'undefined') && forms.length) {
      forms.forEach(function(form) {
        pfyFormsHelper.handleErrorInForm(form);
        pfyFormsHelper.setupCancelButtonHandler(form);
        pfyFormsHelper.setupSubmitHandler(form);
        pfyFormsHelper.setupModifiedMonitor(form);
        pfyFormsHelper.resetForm(form);
      });
    }
  }, // init


  setupModifiedMonitor(form) {
    const formInputs = form.querySelectorAll('input, textarea, select');
    if (formInputs) {
      formInputs.forEach(function (input) {
        if (input.classList.contains('button')) {
          return;
        }
        input.addEventListener('change', function () {
          form.dataset.changed = true;
        });
      });
    }
  }, // setupModifiedMonitor


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
          if (pfyFormsHelper.isFormChanged(form)) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            form.dataset.changed = false;
            pfyFormsHelper.resetForm(form);
            pfyFormsHelper.unlockRecs();
            form.reset();
          }
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



  resetForm(form) {
    let fields = form.querySelectorAll('input');
    if (fields.length) {
      const complexTypes = 'submit,cancel,checkbox,radio,button';
      fields.forEach(function (field) {
        const type = field.getAttribute('type');
        const readonly = field.getAttribute('readonly') !== null;
        if (!readonly && !complexTypes.includes(type)) {
          field.value = field.dataset.default ?? '';
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

    // reset _formInx hidden field:
    const formInxField = form.querySelector('input[name=_formInx]');
    if (formInxField) {
      formInxField.value = formInxField.dataset.default ?? '';
    }

    const errors = form.querySelectorAll('.error');
    if ((typeof errors !== 'undefined') && errors.length) {
      errors.forEach(function(field) {
        field.remove();
      });
    }
  }, // resetForm



  presetForm(form, data, recId) {
    if (typeof data === 'string') {
      mylog('no data received');
      return;
    }
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }
    this.resetForm(form);
    const complexTypes = 'submit,cancel,checkbox,radio,button';
    if (data) {
      if (typeof recId !== 'undefined') {
        const recKey = form.querySelector('input[name=_recKey]');
        if (recKey) {
          recKey.value = recId;
        }
      }
      for (let name in data) {
        const val = data[name];
        if (typeof val === 'object') {
          // it's a choice field:
          for (let opt in val) {
            if (opt === '_') { // skip summary elem
              continue;
            }
            const field = form.querySelector('[name=' + name + '\\[\\]]');
            if (!field) {
              continue; // elem not found, skip it
            }
            const inputWrapper = field.closest('.pfy-input-wrapper');
            const fld = inputWrapper.querySelector('[value=' + opt + ']');
            if (fld) {
              fld.checked = val[opt];
              fld.selected = val[opt];
            }
          }

        } else {
          // it's an input or textarea field:
          const query = '[name=' + name + ']';
          const field = form.querySelector(query);
          if (!field) {
            continue;
          }
          const tagName = field.tagName;
          const type = field.getAttribute('type');
          if (type !== null) {
            mylog(`name: ${name}  type: ${type}  val: ${val}`);
            if (!complexTypes.includes(type)) {
              field.value = val;

            } else if (type === 'radio') {
              const inputWrapper = field.closest('.pfy-input-wrapper');
              const fld = inputWrapper.querySelector('[value=' + val + ']');
              if (fld) {
                fld.checked = true;
                fld.selected = true;
              }

            } else if (type === 'checkbox') { // single checkbox, multiple handled above under choice fields
              field.checked = val;

            } else {
              mylog('presetForm() -> choice elems not implemented yet');
            }
          } else if (tagName === 'TEXTAREA') {
            field.innerText = val;
          } else if (tagName === 'SELECT') {
            const selectedOption = field.querySelector('[value='+val+']');
            if (selectedOption !== null) {
              selectedOption.selected = true;
            }
          } else {
            mylog(`Error: type "${type}" unknown.`);
          }
        }
      }
    }
    this.prefillComputedFields(form);
    form.dataset.changed = true;
  }, // presetForm


  prefillComputedFields(form) {
    let fields = form.querySelectorAll('input');
    if (fields.length) {
      const types = 'hidden,submit,cancel,checkbox,radio,button';
      fields.forEach(function (field) {
        const type = field.getAttribute('type');
        const readonly = field.getAttribute('readonly') !== null;
        if (!readonly && !types.includes(type)) {
          let val = field.dataset.preset ?? '';
          const ch1 = val.charAt(0);
          if (ch1 === '=') {
            val = val.substring(1);
            val = pfyFormsHelper.evalExpr(form, val);
            const name = field.getAttribute('name');
            mylog(`computed name: ${name}  type: ${type}  val: ${val}`);
            field.value = val;
          }
        }
      });
    }
  }, // prefillComputedFields


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
      opOpen: function () {
        setTimeout(function () {
          const input = document.querySelector('#pfy-check-input');
          input.focus();
        }, 50);
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


  unlockRecs: function () {
    if (typeof tableHelper !== 'undefined') {
      tableHelper.unlockRecs();
    }
  }, // unlockRecs


  isFormChanged(form) {
    const changed = form.dataset.changed??'false';
    return (changed !== 'false');
  }, // isFormChanged

  evalExpr(form, str) {
    let m;
    while (m = str.match(/\$([\w-]+)/)) {
      const src = m[1];
      let v = str;
      const elem = form.querySelector('[name='+src+']');
      if (elem) {
        v = elem.value;
      }
      str = str.replace(m[0], `'${v}'`);
    }
    return new Function('return ' + str)();
  }, // isFormChanged

}; // pfyFormsHelper

pfyFormsHelper.init();
