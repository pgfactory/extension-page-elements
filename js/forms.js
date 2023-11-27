/*
 *  forms.js
 */

"use strict";


const pfyFormsHelper = {

  windowTimeout: false,

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


    // initialize freeze timer:
    this.freezeWindowAfter('1 hour');
  }, // init


  initForm(form, setFocus) {
    pfyFormsHelper.handleErrorInForm(form);
    pfyFormsHelper.setupCancelButtonHandler(form);
    pfyFormsHelper.setupSubmitHandler(form);
    pfyFormsHelper.setupModifiedMonitor(form);
    pfyFormsHelper.setupPwTrigger(form);
    pfyFormsHelper.presetForm(form);

    if (typeof setFocus !== 'undefined') {
      const input1 = form.querySelector('.pfy-input-wrapper input');
      if (input1) {
        input1.focus();
      }
    }
  }, // initForm


  setupPwTrigger(form) {
    const pwButtons = form.querySelectorAll('.pfy-form-show-pw ');
    if (pwButtons) {
      pwButtons.forEach(function (pwButton) {
        pwButton.addEventListener('click', function (e) {
          e.stopPropagation();
          e.stopImmediatePropagation();
          e.preventDefault();
          const btn = e.currentTarget;
          const wrapper = btn.closest('.pfy-input-wrapper');
          const pwInput = wrapper.querySelector('input');
          if (btn.classList.contains('show')) {
            btn.classList.remove('show');
            btn.setAttribute('aria-pressed', false);
            pwInput.setAttribute('type', 'password');
          } else {
            btn.classList.add('show');
            btn.setAttribute('aria-pressed', true);
            pwInput.setAttribute('type', 'text');
          }
        });
      })
    }
  }, // setupPwTrigger


  setupModifiedMonitor(form) {
    const formInputs = form.querySelectorAll('input, textarea, select');
    if (formInputs) {
      formInputs.forEach(function (input) {
        if (input.classList.contains('button')) {
          return;
        }
        // monitor changes:
        input.addEventListener('change', function () {
          form.dataset.changed = true;
        });

        // monitor form-timeout:
        input.addEventListener('keydown', function () {
          // check whether page timed out:
          if (pageLoaded < (Math.floor(Date.now()/1000) - 3600)) {
            pfyConfirm({
              text: `{{ pfy-form-timed-out }}`
            })
            .then(function () {
              reloadAgent();
            });
          }
        });
      });
    }

    const categorySelector = form.querySelector('select[name="category"]');
    if (categorySelector) {
      categorySelector.addEventListener('change', function (ev) {
        const activeCategory = ev.target.value;
        let wrapperClasses = form.getAttribute('class');
        wrapperClasses = wrapperClasses.replace(/\s*category-\w+/, '');
        wrapperClasses += ' category-' + activeCategory;
        form.setAttribute('class', wrapperClasses);
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
    const cancelInputs = form.querySelectorAll('[name="_cancel"]');
    if (cancelInputs.length) {
      cancelInputs.forEach(function(input) {
        input.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          const next = input.dataset.next ?? false;
          if (next) {
            reloadAgent('', next);
          }
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
      const check = pfyFormsHelper.checkHonigtopf(form);
      if (!check) {
        e.stopPropagation();
        return;
      }

      pfyFormsHelper.disableForm(form);
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
    this.disableForm(form,false);
    this.setTriggerOnContinueLink();
  }, // presetForm


  disableForm(form, value = true)
  {
    // disable form buttons:
    const frmButtons = form.querySelectorAll('[name="_submit"]');
    if (frmButtons) {
      frmButtons.forEach(function (frmButton) {
        frmButton.disabled = value;
      });
    }
  }, // disableForm


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
    let fields = form.querySelectorAll('.pfy-elem-wrapper input[type=radio]');
    if (fields.length) {
      fields.forEach(function (field) {
        const name = field.getAttribute('name');
        const val = field.getAttribute('value') ?? '';
        const preset = pfyFormsHelper.getFieldValue(field, data, name);
        field.checked = (val && preset === val);
      });
    }

    // reset choice elements 'checkbox':
    fields = form.querySelectorAll('.pfy-elem-wrapper input[type=checkbox]');
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
      const recKey = form.querySelector('input[name=_reckey]');
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


  checkHonigtopf(form) {
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
  }, // checkHonigtopf


  openCheckPopup(form, referenceValue, referenceName) {
    let text = `<label>{{ pfy-form-override-honeypot }} <input type="text" id="pfy-check-input"></label>`;
    text = text.replace(/%refName%/, referenceName);
    let pfyResponseValue;
    pfyResponseValue = '?';
    pfyPopupPromise({
      text: text,
      header: 'AntiSpam',
      buttons: 'cancel,confirm',
      onClose: function() {
        pfyResponseValue = document.querySelector('#pfy-check-input').value;
      },
      onOpen: function () {
        setTimeout(function () {
          const input = document.querySelector('#pfy-check-input');
          if (input) {
            input.focus();
          }
        }, 50);
      },
    }).then(
      function() {
        if (referenceValue.charAt(0).toLowerCase() === pfyResponseValue.toLowerCase()) {
          const honigtopf = form.querySelector('[tabindex="-1"]');
          if (honigtopf) {
            honigtopf.value = '';
          }
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

    // leave scroll-request in localStorage:
    localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));

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


  freezeWindowAfter(delay, onClick = false, retrigger = false) {
    let t = 0;
    if (typeof delay === 'number') {
      t = delay;
    } else if (typeof delay === 'string') {
      let m = delay.match(/(\d+)\s*(\w+)/);
      if (m) {
        let unit = m[2];
        switch (unit.charAt(0).toLowerCase()) {
          case 's':
            t = m[1] * 1000;
            break;
          case 'm':
            t = m[1] * 60000;
            break;
          case 'h':
            t = m[1] * 3600000;
            break;
          case 'd':
            t = m[1] * 86400000;
            break;
        }
      }
    }
    const img = hostUrl + 'media/plugins/pgfactory/pagefactory-pageelements/icons/sleeping.png';
    const overlay = '<div class="pfy-overlay-background pfy-v-h-centered"><div><img src="' + img + '" alt="Sleeping..." class="pfy-timeout-img" /></div></div>';

    if (this.windowTimeout) {
      clearTimeout(this.windowTimeout);
    }

    this.windowTimeout = setTimeout(function () {
      var body = document.body;
      body.insertAdjacentHTML('beforeend', overlay);
      body.classList.add('pfy-overlay-background-frozen');

      var overlayElement = document.querySelector('.pfy-overlay-background');
      overlayElement.addEventListener('click', function () {
        body.classList.remove('pfy-overlay-background-frozen');

        if (typeof onClick === 'function') {
          overlayElement.remove();
          var res = onClick();
          if (res || retrigger) {
            freezeWindowAfter(delay, onClick, retrigger);
          }
        } else {
          pfyConfirm({
            text: `{{ pfy-form-timeout-alert }}`,
            buttons: `Cancel,{{ pfy-form-reload-btn }}`,
          })
          .then(
              function () {
                reloadAgent();
              },
              function () {
                overlayElement.remove();
                pfyPopupClose();
                pfyFormsHelper.freezeWindowAfter('1 minute');
              });
        }
      });
    }, t);
  }, // freezeWindowAfter


  setTriggerOnContinueLink()
  {
    const continueLinks = document.querySelectorAll('.pfy-form-continue-same');
    if (continueLinks) {
      continueLinks.forEach(function (link) {
        link.addEventListener('click', function () {
          localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));
        });
      });
    }
  }, // setTriggerOnContinueLink



}; // pfyFormsHelper

pfyFormsHelper.init();
