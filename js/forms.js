/*
 *  forms.js
 */

"use strict";


const pfyFormsHelper = {

  init(forms, setFocus) {
    // forms defined as query string:
    if (typeof forms === 'string') {
      forms = document.querySelectorAll(forms);

    // form defined as DOM element:
    } else if (forms instanceof Element) {
      pfyFormsHelper.initForm(forms, setFocus);
      return;

    // form undefined:
    } else if ((typeof forms === 'undefined') || !(forms instanceof NodeList)) {
      forms = document.querySelectorAll('.pfy-form');
    }

    // at this point forms is certainly a NoteList:
    if ((typeof forms !== 'undefined') && forms.length) {
      forms.forEach(function(form) {
        pfyFormsHelper.initForm(form, setFocus);
      });
    }
  }, // init


  initForm(form, setFocus) {
    pfyFormsHelper.handleErrorInForm(form);
    pfyFormsHelper.setupCancelButtonHandler(form);
    pfyFormsHelper.setupSubmitHandler(form);
    pfyFormsHelper.setupModifiedMonitor(form);
    pfyFormsHelper.presetForm(form);

    if (typeof setFocus !== 'undefined') {
      const input1 = form.querySelector('.pfy-input-wrapper input');
      if (input1) {
        input1.focus();
      }
    }
  }, // initForm


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
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          const wasModified = (form.dataset.changed ?? 'false') !== 'false';
          form.dataset.changed = false;
          pfyFormsHelper.presetForm(form);
          pfyFormsHelper.unlockRecs();
          const openInPopup = form.closest('.pfy-popup-bg');
          if (!wasModified && openInPopup) {
            mylog('close popup');
            pfyPopupClose();
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



  presetForm(form, data, recId) {
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }
    if (typeof data === 'undefined') {
      data = {};
    }

    this.presetScalarFields(form, data);
    this.presetChoiceFields(form, data);
    this.resetFormInx(form);
    this.setRecId(form, recId);
    this.resetErrorStates(form);
    this.prefillComputedFields(form);
  }, // presetForm


  presetScalarFields(form, data) {
    // reset 'textarea' and 'input' fields, except 'submit,cancel,checkbox,radio,button':
    let fields = form.querySelectorAll('input, textarea');
    if (fields.length) {
      const excludeTypes = 'submit,cancel,checkbox,radio,button';
      fields.forEach(function (field) {
        const type = field.getAttribute('type');
        const readonly = field.getAttribute('readonly') !== null;
        if (!readonly && excludeTypes.includes(type)) {
          return;
        }
        const val = pfyFormsHelper.getFieldValue(field, data);
        field.value = val;

        // handle case of revealed textarea:
        if (field.classList.contains('pfy-reveal-target')) {
          const wrapper = field.closest('.pfy-elems-wrapper');
          const controller = wrapper.querySelector('input.pfy-reveal-controller');
          if (val) {
            pfyRevealPanel(controller);
          } else {
            pfyUnrevealPanel(controller);
          }
        }
      });
    }
  }, // presetScalarFields


  presetChoiceFields(form, data) {
    // reset choice elements 'radio':
    let fields = form.querySelectorAll('input[type=radio]');
    if (fields.length) {
      fields.forEach(function (field) {
        const name = field.getAttribute('name');
        const val = field.getAttribute('value') ?? '';
        const preset = pfyFormsHelper.getFieldValue(field, data, name);
        field.checked = (val && preset === val);
      });
    }

    // reset choice elements 'checkbox':
    fields = form.querySelectorAll('input[type=checkbox]');
    if (fields.length) {
      fields.forEach(function (field) {
        const name = field.getAttribute('name');
        const len = name.length;
        if (name.charAt(len - 1) === ']') {
          const val = field.value;
          const preset = pfyFormsHelper.getChoiceFieldValue(field, null, name, data);
          field.checked = preset.includes(val);
        } else {
          let preset = pfyFormsHelper.getFieldValue(field, data, name);
          field.checked = !!preset;
        }
      });
    }

    // reset choice elements 'select':
    const options = form.querySelectorAll('option');
    if (options.length) {
      options.forEach(function (optionElem) {
        const selectField = optionElem.parentElement;
        const optionVal = optionElem.value ? optionElem.value : 'impossiblevalue';
        let   preset = 'undefined';
        let   name = selectField.getAttribute('name');
        const len = name.length;
        if (name.charAt(len - 1) === ']') {
          preset = pfyFormsHelper.getChoiceFieldValue(selectField, optionElem, name, data);
          preset = preset.includes(optionVal);
        } else {
          preset = pfyFormsHelper.getFieldValue(selectField, data, name);
          preset = (preset === optionVal);
        }
        optionElem.selected = preset;
      });
    }
  }, // presetChoiceFields


  resetFormInx(form){
    // reset _formInx hidden field:
    const formInxField = form.querySelector('input[name=_formInx]');
    if (formInxField) {
      formInxField.value = formInxField.dataset.preset ?? '';
    }
  }, // resetFormInx


  setRecId(form, recId) {
    if (typeof recId !== 'undefined') {
      const recKey = form.querySelector('input[name=_recKey]');
      if (recKey) {
        recKey.value = recId;
      }
    }
  }, // setRecId


  resetErrorStates(form){
    // reset error states:
    const errors = form.querySelectorAll('.error');
    if ((typeof errors !== 'undefined') && errors.length) {
      errors.forEach(function (field) {
        field.remove();
      });
    }
  }, // resetErrorStates


  getFieldValue(field, data, name) {
    if (typeof name === 'undefined') {
      name = field.getAttribute('name');
    }
    let val = '';
    if ((typeof data !== 'undefined') && (typeof data[name] !== 'undefined')) {
      val = data[name];
    } else {
      val = field.dataset.preset ?? '';
    }
    return val;
  }, // getFieldValue


  getChoiceFieldValue(field, optionElem, name, data) {
    const dataAvailable = Object.keys(data).length;
    if (typeof data === 'undefined' || !dataAvailable) {
      return field.dataset.preset ?? 'novalue';
    }
    name = name.substring(0, name.length - 2);
    const rec = data[name] ?? false;
    if (rec) {
      let sub;
      if ((typeof optionElem !== 'undefined') && (optionElem !== null)) {
        sub = optionElem.value;
      } else {
        sub = field.value;
      }
      const val = rec[sub] ? sub : 'novalue';
      return val.toString();
    }
    return '';
  }, // getChoiceFieldValue


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
