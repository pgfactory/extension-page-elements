/*
 *  forms.js
 */

"use strict";


const pfyFormsHelper = {

  windowTimeout: false,

  init(forms, setFocus, windowFreezeTime = false) {
    if (forms instanceof Element) {
      pfyFormsHelper.initForm(forms, setFocus);

    // form undefined:
    } else {
      if ((typeof forms === 'undefined')) {
        // forms not defined -> apply to all forms in page:
        forms = document.querySelectorAll('.pfy-form');

      } else if (typeof forms === 'string') {
        // forms defined as query string -> get all matching forms:
        forms = document.querySelectorAll(forms);

      } else if (!(forms instanceof NodeList)) {
        return;
      }
      if (forms) {
        forms.forEach(function (form) {
          pfyFormsHelper.initForm(form, setFocus);
        })
      }
    }

    // initialize freeze timer:
    if (windowFreezeTime) {
      this.freezeWindowAfter(windowFreezeTime);
    }
  }, // init


  initForm(form, setFocus) {
    pfyFormsHelper.handleErrorInForm(form);
    pfyFormsHelper.setupCancelButtonHandler(form);
    pfyFormsHelper.setupSubmitHandler(form);
    pfyFormsHelper.setupModifiedMonitor(form);
    pfyFormsHelper.setupRevealHandler(form);
    pfyFormsHelper.setupPwTrigger(form);
    pfyFormsHelper.presetForm(form);
    pfyFormsHelper.initAutoGrow(form);

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


  setupRevealHandler(form) {

    const revealControllers = form.querySelectorAll('[data-reveal-target]');
    if (revealControllers) {
      revealControllers.forEach(function (revealController) {
        const targetSel = revealController.dataset.revealTarget;
        const revealContainer = document.querySelector(targetSel);
        if (!revealContainer.querySelector('.pfy-reveal-container-inner')) {
          const revealContent = revealContainer.innerHTML;
          revealContainer.innerHTML = '<div class="pfy-reveal-container-inner" style="display: none;"></div>';
          revealContainer.querySelector('.pfy-reveal-container-inner').innerHTML = revealContent;
        }

        revealController.addEventListener('change', function(el) {
          const open = el.target.checked;
          if (open) {
            pfyReveal.reveal(revealContainer, revealController);
          } else {
            pfyReveal.unreveal(revealContainer, revealController);
          }
        });
      });
    }
  }, // setupRevealHandler


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

          const hasErrors = form.classList.contains('pfy-form-has-errors') ||
              form.querySelector('.pfy-form-elem-alert');
          if (hasErrors) {
            reloadAgent();
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

      const enablesubmitElems = form.querySelectorAll('[data-enablesubmit]');
      if (enablesubmitElems) {
        let goOn = true;
        enablesubmitElems.forEach(function (elem) {
          if (!elem.checked) {
            goOn = false;
            const wrapper = elem.closest('.pfy-elem-wrapper');
            if (!wrapper.querySelector('.pfy-form-elem-alert')) {
              let div = document.createElement("div");
              div.innerText = `{{ pfy-form-enablesubmit-elem-not-checked }}`;
              div.classList.add('pfy-form-elem-alert');
              wrapper.appendChild(div);
            } else {
              wrapper.classList.add('pfy-form-elem-alert');
            }
          }
        });
        if (!goOn) {
          e.stopPropagation();
          return;
        }
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
    this.setTriggerOnContinueLink();
  }, // presetForm


  disableForm(form, value = true)  {
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
      const typesToSkip = 'submit,cancel,checkbox,radio,button';
      fields.forEach(function (field) {
        const type = field.getAttribute('type');
        const readonly = field.getAttribute('readonly') !== null;
        if (!readonly && typesToSkip.includes(type)) {
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
          field.checked = (preset !== '#novalue#') && preset.includes(val);
        } else {
          let preset = pfyFormsHelper.getFieldValue(field, data, name);
          field.checked = !!preset;
        }
      });
    }

    // reset select elements 'options':
    const selects = form.querySelectorAll('select');
    if (selects.length) {
      selects.forEach(function (selectElem) {
        // for each option: get 'data-preset' values, put them in 'value':
        let name = selectElem.getAttribute('name');
        const len = name.length;
        if (name.charAt(len - 1) === ']') {
          name = name.substring(0, len - 2);
        }
        let val = '';
        if ((typeof data !== 'undefined') && (typeof data[name] !== 'undefined')) {
          val = data[name];
        } else {
          val = selectElem.dataset.preset || '';
        }
        const options = selectElem.querySelectorAll('option');
        if (options) {
          options.forEach(function (option) {
            const optName = option.value;
            if (typeof val[optName] !== 'undefined') {
              option.selected = val[optName];
            } else {
              option.selected = (val===optName);
            }
          });
        }
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
      return field.dataset.preset ?? '#novalue#';
      // return field.dataset.preset ?? 'novalue';
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
      const val = rec[sub] ? sub : '#novalue#';
      // const val = rec[sub] ? sub : 'novalue';
      return val.toString();
    }
    return '';
  }, // getChoiceFieldValue


  prefillComputedFields(form) {
    let fields = form.querySelectorAll('input');
    if (fields.length) {
      const parent = this;
      const typesToSkip = 'hidden,submit,cancel,checkbox,radio,button';
      fields.forEach(function (field) {
        const type = field.getAttribute('type');
        const readonly = field.getAttribute('readonly') !== null;
        if (readonly || typesToSkip.includes(type)) {
          return;
        }

        // computed fields are identified by a leading '=' in 'data-preset':
        let val = field.dataset.preset ?? '';
        const ch1 = val.charAt(0);
        if (ch1 === '=') {
          val = val.substring(1);
          val = pfyFormsHelper.evalExpr(form, val);
          const name = field.getAttribute('name');
          mylog(`computed name: ${name}  type: ${type}  val: ${val}`);
          field.value = val;
        }

        // handle eventDuration-> field with 'data-event-duration':
        if (field.dataset.eventDuration ?? '') {
          parent.handleEventFields(form, field);
        }
      });
    }
  }, // prefillComputedFields


  handleEventFields(form, field) {
    const parent = this;
    const duration = parseInt(field.dataset.eventDuration ?? '');
    const relatedField = field.dataset.relatedField ?? '';
    const $startDate = form.querySelector('[name='+relatedField+']');
    const preset = $startDate.dataset.preset ?? false;
    const now = new Date();
    const nextFullHour = parent.roundUpMinutes(now);

    if (preset) {
      // preset event start:
      if (!$startDate.value) {
        if (preset === 'true') {
          $startDate.value = parent.toIsoLocalString(nextFullHour);
        } else {
          let dateStr = parent.toIsoLocalString();
          if (preset.length === 5) {
            dateStr = dateStr.substring(0,11) + preset;
          } else {
            dateStr = preset;
          }
          $startDate.value = dateStr;
        }
      }
    }

    // preset event end:
    if (!field.value && duration) {
      const startStr = $startDate.value;
      if (startStr) {
        const end = new Date(startStr);
        field.value = parent. fixDatetimeFormat(field, parent.addMinutes(end, duration));
      }
    }

    // handle changes in startDate -> adapt endDate:
    $startDate.addEventListener('change', function (e) {
      const start = new Date($startDate.value);
      const duration = parseInt(field.dataset.eventDuration ?? '');
      let newVal = parent.addMinutes(start, duration);
      newVal = parent.fixDatetimeFormat(field, newVal);
      field.value = newVal;
    });

    if ($startDate.value && field.value) {
      const start = new Date($startDate.value);
      const end = new Date(field.value);
      field.dataset.eventDuration = (end.getTime() - start.getTime()) / 60000;
    }


    // handle changes in endDate -> adapt duration:
    field.addEventListener('change', function (e) {
      const start = new Date($startDate.value);
      const end = new Date(field.value);
      field.dataset.eventDuration = (end.getTime() - start.getTime()) / 60000;
    });
  }, // handleEventFields


  fixDatetimeFormat(field, value) {
    if (field.type === 'date') {
      value = value.substring(0, 10);
    } else if (value.length < 16) {
      value = value.substring(0, 10) + ' 12:00';
    }
    return value;
  }, //fixDatetimeFormat


  toIsoLocalString(date) {
    if (typeof date === 'undefined') {
      date = new Date();
    }
    const currentIsoDateString = new Date(date - date.getTimezoneOffset() * 60000).toISOString();
    return currentIsoDateString.substring(0,16);
  }, // toIsoLocalString


  addMinutes(dateStr, minutes) {
    const t = Date.parse(dateStr);
    let   date = new Date;
    date.setTime(t + (minutes * 60000));
    return this.toIsoLocalString(date);
  }, // addMinutes


  roundUpMinutes(date) {
    date.setHours(date.getHours() + Math.ceil(date.getMinutes()/60));
    date.setMinutes(0, 0, 0); // Resets also seconds and milliseconds
    return date;
  }, // roundUpMinutes


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
        pfyFormsHelper.openAntiSpamPopup(form, referenceValue, label);
      }
    }
    return check;
  }, // checkHonigtopf


  openAntiSpamPopup(form, referenceValue, referenceName) {
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
  }, // openAntiSpamPopup


  doSubmitForm(form) {
    const buttons = form.querySelectorAll('.button');
    buttons.forEach(function(button) {
      button.disabled = true;
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


  initAutoGrow(form) {
    // source: https://css-tricks.com/the-cleanest-trick-for-autogrowing-textareas/
    const growers = document.querySelectorAll(".pfy-auto-grow .pfy-input-wrapper");
    if (growers) {
      growers.forEach((grower) => {
        const textarea = grower.querySelector("textarea");
        textarea.addEventListener("input", () => {
          grower.dataset.replicatedValue = textarea.value;
        });
      });
    }
  }, // initAutoGrow


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
          let text = `{{ pfy-form-timeout-alert }}`;
          if (typeof onClick === 'string') {
            text = onClick;
          }
          pfyConfirm({
            text: text,
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


  setTriggerOnContinueLink()  {
    const continueLinks = document.querySelectorAll('.pfy-form-continue-same');
    if (continueLinks) {
      continueLinks.forEach(function (link) {
        link.addEventListener('click', function () {
          localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));
        });
      });
    }
  }, // setTriggerOnContinueLink


  initRepetitionWidget: function (wrapperEl = null) {
    domForEach(wrapperEl, '.pfy-form-rrule-wrapper', function (rruleWrapper) {
      domForOne(rruleWrapper, '.pfy-rrule-elem-freq', function (select) {
        select.addEventListener('change', function (ev) {
          const selectEl = ev.target;
          const details = selectEl.closest('details');
          const rruleBody = details.querySelector('.pfy-form-rrule-body-wrapper');
          const selectedFreq = selectEl.options[selectEl.selectedIndex].value;
          if (selectedFreq === 'NONE') {
            details.open = false;
            rruleBody.classList.value = 'pfy-form-rrule-body-wrapper' ;
          } else {
            details.open = true;
            rruleBody.classList.value = 'pfy-form-rrule-body-wrapper pfy-form-rrule-' + selectedFreq.toLowerCase();
          }
        });
      });
    });
  },


}; // pfyFormsHelper
