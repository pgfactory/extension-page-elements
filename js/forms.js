/*
 *  forms.js
 */

"use strict";

const pfyFormsHelper = {

  windowTimeout: false,
  formInitialized: false,
  formRecLocking: (typeof pfyFormRecLocking !== 'undefined') && pfyFormRecLocking,
  recLocked: false,
  menuSelectWrapperEl: false,

  init(forms, setFocus, windowFreezeTime) {
    if (forms instanceof Element) {
      this.initForm(forms, setFocus);

    } else {
      if (forms == null) {
        forms = document.querySelectorAll('.pfy-form');

      } else if (typeof forms === 'string') {
        forms = document.querySelectorAll(forms);

      } else if (!(forms instanceof NodeList)) {
        return;
      }
      if (forms) {
        forms.forEach((form) => {
          this.initForm(form, setFocus);
        });
      }
    }

    // initialize freeze timer:
    if (typeof windowFreezeTime === 'undefined') {
      windowFreezeTime = (typeof formFreezeTime !== 'undefined') ? formFreezeTime : 0;
    }
    if (windowFreezeTime) {
      this.freezeWindowAfter(windowFreezeTime);
    }

    this.initSpinner();
  }, // init


  initForm(form, setFocus) {
    if (!this.formInitialized) {
      this.setupTriggers();
      this.setupRevealHandlers();
      this.setupMenuSelectWidget(form);
      this.formInitialized = true;
    }
    this.handleErrorInForm(form);
    this.presetForm(form);

    if (setFocus) {
      if (form.closest('.pfy-form-readonly')) {
        return;
      }
      const input1 = form.querySelector('.pfy-input-wrapper input');
      if (input1) {
        this.setFocus(input1);
      }
    }

    this.initReadonlyForm(form);
  }, // initForm


  setupTriggers() {
    document.body.addEventListener('click', (ev) => {
      this.cancelButtonHandler(ev);
      this.newrecButtonHandler(ev);
      this.buttonCallbacksHandler(ev);
      this.showPwHandler(ev);
      this.handleFrozenWindow(ev);
      this.handleSideBySideButtons(ev);
    });

    document.body.addEventListener('submit', (ev) => {
      if (!ev.target.closest('.pfy-form-is-modified')) {
        ev.preventDefault();
        return;
      }
      this.submitHandler(ev);
    });

    document.body.addEventListener('change', (ev) => {
      this.modifyMonitorHandler(ev);
      this.categoryChangeMonitorHandler(ev);
      this.revealHandler(ev.target);
      this.repetitionChangeHandler(ev);
      if (this.menuSelectWrapperEl) {
        this.menuSelectChangeHandler(ev);
      }
    });

    document.addEventListener('keydown', (ev) => {
      this.modifyMonitorHandler(ev);
      this.checkFormTimeout(ev);
    });

    document.body.addEventListener('input', (ev) => {
      this.handleTextareaGrowers(ev);
    });
  }, // setupTriggers


  handleSideBySideButtons(ev) {
    const btnWrapper = ev.target.closest('.pfy-side-by-side-buttons');
    if (!btnWrapper) {
      return;
    }
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    ev.preventDefault();
    const btn = ev.target.closest('.pfy-button');
    if (!btn) {
      return;
    }
    if (btn.classList.contains('pfy-two-windows')) {
      const otherBtn = btnWrapper.querySelector('.pfy-one-window');
      btn.setAttribute('aria-pressed', true);
      otherBtn.setAttribute('aria-pressed', false);
      const wrapper = btnWrapper.closest('.pfy-form-and-table-wrapper');
      wrapper.classList.add('pfy-side-by-side');

    } else {
      const otherBtn = btnWrapper.querySelector('.pfy-two-windows');
      btn.setAttribute('aria-pressed', true);
      otherBtn.setAttribute('aria-pressed', false);
      const wrapper = btnWrapper.closest('.pfy-form-and-table-wrapper');
      wrapper.classList.remove('pfy-side-by-side');
    }
  }, // handleSideBySideButtons


  buttonCallbacksHandler(ev) {
    const btn = ev.target.closest('input');
    if (!btn) {
      return;
    }

    const res = executeCallbackCode(btn.dataset.callback, ev);
    if (!res) {
      return;
    }
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    ev.preventDefault();
  }, // buttonCallbacksHandler


  cancelButtonHandler(ev) {
    const btn = ev.target.closest('input.pfy-cancel');
    if (!btn) {
      return;
    }

    const res = executeCallbackCode(btn.dataset.callback, ev);
    if (res === false) {
      return;
    }

    const form = btn.closest('.pfy-form');
    const changed = this.isFormModified(form);

    // reset form:
    const formInx = form.querySelector('[name=_form_]').value;
    if (form.closest('.pfy-form-wrapper') && form.closest('.pfy-form-wrapper').classList.contains('pfy-retain-data')) {
      pfyFormsHelper.reloadAgent(`clearform=${formInx}`);
    }

    this.clearErrors(form);
    this.clearModifiedFlag(form);
    this.clearPresetFlag(form);
    this.clearRowSelection(form);
    this.presetForm(form);
    this.setRecId(form, '');

    const cleared = form.classList.contains('pfy-form-cleared');
    if (!changed && cleared) {
      // check whether in popup, close it:
      const popup = form.closest('.pfy-popup-bg');
      if (popup) {
        pfyPopupClose(popup);
      } else if (btn.closest('.pfy-retain-data')) {
        pfyFormsHelper.reloadAgent(`clearform=${formInx}`);
      } else {
        form.classList.remove('pfy-form-cleared');
        return;
      }
    }
    form.classList.add('pfy-form-cleared');
  }, // cancelButtonHandler


  newrecButtonHandler(ev) {
    const btn = ev.target.closest('input');
    if (!btn) {
      return;
    }
    if (!btn.classList.contains('pfy-newrec')) {
      const name = String(btn.getAttribute('name'));
      if (!name.includes('newrec')) {
        return;
      }
    }
    const form = ev.target.closest('.pfy-form');

    const check = pfyFormsHelper.checkHonigtopf(form);
    if (!check) {
      ev.stopPropagation();
      return;
    }

    const res = executeCallbackCode(btn.dataset.callback, ev);
    if (res === false) {
      return;
    }

    // clear _reckey:
    domForOne(form, '[name=_reckey]', el => {
      el.value = '_create-new_';
    });
    pfyFormsHelper.setModifiedFlag(form);

    this.submitHandler(ev);
  }, // newrecButtonHandler


  showPwHandler(ev) {
    const btn = ev.target.closest('.pfy-form-show-pw');
    if (!btn) {
      return;
    }
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    ev.preventDefault();
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
  }, // showPwHandler


  modifyMonitorHandler(ev) {
    const el = ev.target;
    if (el.closest('.pfy-form-is-modified') || el.closest('.pfy-reveal-controller') || el.closest('.pfy-table-data-output-wrapper')) {
      return;
    }

    if (ev.type === 'keydown') {
      if (!el.closest('.pfy-input-wrapper') || el.closest('.pfy-form-button')) {
        return;
      }
      const key = ev.key;
      // ignore non-character keys except Delete and Backspace:
      if (key !== 'Delete' && key !== 'Backspace' && key.length > 1) {
        return;
      }
    }

    this.setModifiedFlag(el);
  }, // modifyMonitorHandler


  checkFormTimeout(ev) {
    if ((typeof pageLoaded !== 'undefined') && (pageLoaded < (Math.floor(Date.now()/1000) - 3600))) {
      pfyConfirm({
        text: `{{ pfy-form-timed-out }}`
      })
      .then(() => {
        pfyFormsHelper.reloadAgent();
      });
    }
  }, // checkFormTimeout


  categoryChangeMonitorHandler(ev) {
    const select = ev.target.closest('select[name="category"]');
    if (!select) {
      return;
    }
    const form = select.closest('.pfy-form');
    if (!form) {
      return;
    }
    const activeCategory = select.value;
    form.classList.forEach(cls => {
      if (cls.startsWith('category-')) {
        form.classList.remove(cls);
      }
    });
    if (activeCategory) {
      form.classList.add('category-' + activeCategory);
    }
  }, // categoryChangeMonitorHandler


  setupRevealHandlers() {
    domForEach('.pfy-form [data-reveal-target]', (el) => {
      this.revealHandler(el);
    });
  }, // setupRevealHandlers


  revealHandler(el) {
    const revealController = el.closest('[data-reveal-target]');
    if (!revealController || !revealController.dataset || !revealController.dataset.revealTarget) {
      return;
    }
    const targetSel = revealController.dataset.revealTarget;
    const revealContainer = document.querySelector(targetSel);

    // check whether target contains 'pfy-reveal-container-inner' wrapper, inject if not:
    if (!revealContainer.querySelector('.pfy-reveal-container-inner')) {
      const revealContent = revealContainer.innerHTML;
      revealContainer.innerHTML = '<div class="pfy-reveal-container-inner" style="display: none;"></div>';
      revealContainer.querySelector('.pfy-reveal-container-inner').innerHTML = revealContent;
    }

    let open = el.checked;

    // case radio: option with value == 'true' opens reveal target:
    if (el.type === 'radio' && el.value !== 'true') {
      open = false;
    }

    if (!this.formInitialized) {
      const textareaEl = revealContainer.querySelector('textarea');
      if (textareaEl) {
        open = !!textareaEl.innerHTML;
      }
    }

    if (open) {
      pfyReveal.reveal(revealController);
    } else {
      pfyReveal.unreveal(revealController);
    }
  }, // revealHandler


  handleErrorInForm(form) {
    const errorElement = form.querySelector('.error');
    if (errorElement) {
      const input = errorElement.parentNode.querySelector('input');
      if (input) {
        input.scrollIntoView({ block: 'end' });
        this.setFocus(input);
      }
    }
  }, // handleErrorInForm


  submitHandler(ev) {
    const form = ev.target.closest('.pfy-form');
    if (!form) {
      return;
    }

    if (!this.isFormModified(form)) {
      return;
    }
    if (typeof pfyFormsHelper.submitNow !== 'undefined') {
      return;
    }
    ev.preventDefault();
    const check = pfyFormsHelper.checkHonigtopf(form);
    if (!check) {
      ev.stopPropagation();
      return;
    }

    const enablesubmitElems = form.querySelectorAll('[data-enablesubmit]');
    if (enablesubmitElems.length) {
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
        ev.stopPropagation();
        return;
      }
    }

    pfyFormsHelper.disableForm();
    pfyFormsHelper.doSubmitForm(form);
  }, // submitHandler


  fetchDataAndFillForm(el, recKey, retainData = false, createNewRec = false) {
    let formWrapper;
    if (el.closest('.pfy-form-wrapper')) {
      formWrapper = el.closest('.pfy-form-wrapper');
    } else if (el.closest('.pfy-form-and-table-wrapper')) {
      formWrapper = el.closest('.pfy-form-and-table-wrapper').querySelector('.pfy-form-wrapper');
    }
    let form = formWrapper.querySelector('.pfy-form');
    const dataSrcinx = formWrapper.querySelector('[name=_dataSrcInx]').value;
    let args = 'getRec='+recKey+'&datasrcinx='+dataSrcinx;
    if (pfyFormsHelper.formRecLocking) {
      args += '&lock';
      pfyFormsHelper.recLocked = true;
    }
    let table = null;
    const tableRef = formWrapper.dataset.relatedTable;
    if (tableRef) {
      table = document.getElementById(tableRef);
    }

    if (form.closest('.pfy-retain-data') && retainData) {
      args += '&retainData='+dataSrcinx;
    }
    form.dataset.loading = true;
    execAjaxPromise(args, {})
      .then((data) => {
        if (data.status === 'error') {
          // handle case where rec locked by somebody else:
          const row = table.querySelector('[data-reckey="'+recKey+'"]');
          row.classList.add('pfy-rec-locked');
          if (formWrapper.closest('.pfy-popup-wrapper')) {
            pfyPopupClose();
            pfyAlert(`{{ pfy-form-rec-locked }}`);
          }
          return;
        }

        // popup is open, now prepare the form, inject obtained data:
        if (createNewRec) {
          recKey = '';
        }
        pfyFormsHelper.presetForm(form, data, recKey);
        form.removeAttribute('data-loading');
      })
      .then((msg) => {
        form.removeAttribute('data-loading');
      });
  }, // fetchDataAndFillForm


  presetForm(form, data, recId) {
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }
    if (isEmpty(data)) {
      data = {};
    }

    const applyPreset = () => {
      this.presetFields(form, data);
      this.setRecId(form, recId);
      this.resetErrorStates(form);
      this.prefillComputedFields(form);
      this.executeOnPresetCallback(form);
      this.handleMarkAsModifiedRequest(form);
      this.setTriggerOnContinueLink();
    };

    if (Object.keys(data).length && this.isFormModified(form)) {
      pfyConfirm({
        text: `{{ pfy-form-data-modified-warning }}`,
      }).then(
        applyPreset,
        () => {},
      );
    } else {
      applyPreset();
    }
  }, // presetForm


  presetFields(form, data) {
    if (form.closest('.pfy-table-edit-popup')) {
      return;
    }

    let isPreset = false;
    if (isEmpty(data)) {
      data = {};
      this.clearModifiedFlag(form);
      this.clearPresetFlag(form);
      domForOne(form, 'input[name=_reckey]', el => {
        el.value = '';
      });
    } else {
      isPreset = true;
    }
    domForEach(form, 'div.pfy-elem-wrapper', fieldWrapperElemEl => {
      pfyFormsHelper.presetField(form, fieldWrapperElemEl, data, isPreset);
    });

    domForOne(form, '[name=_reckey]', reckeyEl => {
      const val = reckeyEl.dataset.value;
      if (val !== undefined) {
        reckeyEl.value = val;
        reckeyEl.removeAttribute('data-value');
      }
    });
  }, // presetFields


  presetField(form, fieldWrapperElemEl, data, isPreset) {
    const typesToSkip = 'submit,cancel,checkbox,radio,button';
    const namesToSkip = '_csrf,_dataSrcInx,_form_';
    const name = fieldWrapperElemEl.querySelector('[name]').getAttribute('name').replace(/\[\]$/, '');

    // get type:
    let type = '';
    let val = '';
    if (fieldWrapperElemEl.querySelector('select')) {
      type = 'select';
    } else if (fieldWrapperElemEl.querySelector("textarea")) {
      type = 'textarea';
    } else {
      if (fieldWrapperElemEl.querySelector('.pfy-input-wrapper')) {
        type = fieldWrapperElemEl.querySelector('.pfy-input-wrapper [type]').getAttribute('type');
      } else {
        type = fieldWrapperElemEl.querySelector('[type]').getAttribute('type');
      }
    }

    // get value, first try data-preset:
    val = fieldWrapperElemEl.dataset.preset;
    if (!val) {
      domForOne(fieldWrapperElemEl, '[data-preset]', el => {
        val = el.dataset.preset;
      });
    }
    if (val) {
      isPreset = true;
      val = this.fixAttribValue(val);
    }

    // get value, next try data-value:
    if (fieldWrapperElemEl.dataset.value) {
      val = fieldWrapperElemEl.dataset.value;
      if (val) {
        fieldWrapperElemEl.removeAttribute('data-value');
        isPreset = true;
        val = this.fixAttribValue(val);
      }
    } else {
      domForOne(fieldWrapperElemEl, '[data-value]', el => {
        val = el.dataset.value;
        el.removeAttribute('data-value');
        isPreset = true;
        val = this.fixAttribValue(val);
      });
    }
    // next try given data-rec (if present):
    if (data[name]) {
      val = data[name];
    }
    if (typeof val === 'undefined') {
      val = '';
    }

    if (type === 'hidden') {
      const hiddenEl = fieldWrapperElemEl.querySelector('input');
      if (!val) {
        val = hiddenEl.dataset.value;
      }
      if (val !== undefined) {
        hiddenEl.value = val;
        hiddenEl.removeAttribute('data-value');
      }
    } else if ('radio,checkbox'.includes(type)) {
      // --- radio, checkbox
      if (typeof val === 'string') {
        const valPatt = `,${val},`;
        domForEach(fieldWrapperElemEl, 'input', option => {
          const hasNoValue = (option.getAttribute('value') === null);
          if (hasNoValue) {
            option.checked = !!val;
          } else {
            const v = ',' + option.value + ',';
            option.checked = valPatt.includes(v);
          }
        });
      } else if (typeof val === 'boolean') {
        domForEach(fieldWrapperElemEl, 'input', option => {
          option.checked = val;
        });
      } else {
        domForEach(fieldWrapperElemEl, 'input', option => {
          const v = option.value;
          option.checked = val[v];
        });
      }

    } else if (type === 'select' || type === 'multiselect') {
      // --- select
      if (typeof val === 'string') {
        val = `,${val},`;
        domForEach(fieldWrapperElemEl, 'option', option => {
          const v = ',' + option.value + ',';
          option.selected = val.includes(v);
        });
      } else {
        domForEach(fieldWrapperElemEl, 'option', option => {
          const v = option.value;
          option.selected = val[v];
        });
      }

    } else if (type === 'textarea') {
      val = val.replace(/\\n/g, '\n');
      fieldWrapperElemEl.querySelector('textarea').value = val;
      if (fieldWrapperElemEl.classList.contains('pfy-auto-grow')) {
        domForOne(fieldWrapperElemEl, 'span.pfy-input-wrapper', autogrowWrapperEl => {
          autogrowWrapperEl.dataset.replicatedValue = val;
        });
      }
      // open/close accordion if content is present or not:
      domForOne(fieldWrapperElemEl, 'details', el => {
        if (val) {
          el.setAttribute('open', true);
        } else {
          el.removeAttribute('open');
        }
      });

    } else {
      // --- text or hidden
      if (namesToSkip.includes(name) || typesToSkip.includes(type)) {
        return;
      }
      fieldWrapperElemEl.querySelector('input').value = val;
    }

    if (isPreset) {
      this.setPresetFlag(form);
    }
  }, // presetField


  fixAttribValue(val) {
    if (val === 'false') {
      val = false;
    } else {
      // unshield shielded characters in attributes:
      val = val.replace(/❛/g, '\'');
      val = val.replace(/❝/g, '"');
      val = val.replace(/∽/g, '~');
    }
    return val;
  }, // fixAttribValue


  setFocus(el) {
    setTimeout(() => {
      el.focus();
    }, 100);
  }, // setFocus


  disableForm() {
    showBusySpinner();
  }, // disableForm


  enableForm() {
    hideBusySpinner();
  }, // enableForm


  isFormModified(el) {
    return !!el.closest('.pfy-form-is-modified');
  }, // isFormModified


  isFormPreset(el) {
    return !!el.closest('.pfy-form-is-preset');
  }, // isFormPreset


  setModifiedFlag(el) {
    domForOne(el, '^.pfy-form-wrapper', formWrapper => {
      formWrapper.classList.add('pfy-form-is-modified');
      formWrapper.classList.remove('pfy-form-is-preset');
    });
  }, // setModifiedFlag


  setPresetFlag(el) {
    domForOne(el, '^.pfy-form-wrapper', formWrapper => {
      formWrapper.classList.add('pfy-form-is-preset');
      formWrapper.classList.remove('pfy-form-is-modified');
    });
  }, // setPresetFlag


  clearErrors(form) {
    domForAll(form, '.pfy-form-elem-error-msg', el => {
      el.remove();
    });
    domForAll(form, '.pfy-form-elem-has-error', el => {
      el.classList.remove('pfy-form-elem-has-error');
    });
  }, // clearErrors


  clearModifiedFlag(el) {
    domForOne(el, '^.pfy-form-wrapper', formWrapper => {
      formWrapper.classList.remove('pfy-form-is-modified');
    });
  }, // clearModifiedFlag


  clearPresetFlag(el) {
    domForOne(el, '^.pfy-form-wrapper', formWrapper => {
      formWrapper.classList.remove('pfy-form-is-preset');
    });
  }, // clearPresetFlag


  clearRowSelection(form) {
    domForEach(form, '^.pfy-form-and-table-wrapper .pfy-row-selected', el => {
      el.classList.remove('pfy-row-selected');
    });
  }, // clearRowSelection


  resetFormInx(form) {
    const formInxField = form.querySelector('input[name=_dataSrcInx]');
    if (formInxField) {
      if (formInxField.dataset.preset !== undefined) {
        formInxField.value = formInxField.dataset.preset;
      } else {
        formInxField.value = '';
      }
    }
  }, // resetFormInx


  setRecId(form, recId) {
    if (recId !== undefined) {
      const recKey = form.querySelector('input[name=_reckey]');
      if (recKey) {
        recKey.value = recId;
      }
    }
  }, // setRecId


  resetErrorStates(form) {
    form.querySelectorAll('.error').forEach(field => {
      field.remove();
    });
  }, // resetErrorStates


  getFieldValue(field, data, name) {
    if (!name) {
      name = field.getAttribute('name');
    }
    if (!data) {
      return '';
    }
    let val = '';
    if (data[name] !== undefined) {
      val = data[name];
    } else {
      if (field.dataset.preset !== undefined) {
        val = field.dataset.preset;
      }
      if (field.dataset.value !== undefined) {
        val = field.dataset.value;
        field.removeAttribute('data-value');
      }
      if (field.innerText) {
        val = field.innerText;
      }
    }
    return val;
  }, // getFieldValue


  getChoiceFieldValue(field, optionElem, name, data) {
    if (!data || !Object.keys(data).length) {
      if (field.dataset.selected) {
        field.removeAttribute('data-selected');
        return 'selected';
      } else if (field.dataset.checked) {
        field.removeAttribute('data-checked');
        return 'checked';
      } else {
        return '#novalue#';
      }
    }
    if (name.match(/\[\]$/)) {
      name = name.substring(0, name.length - 2);
    }
    let rec = false;
    if (data[name] !== undefined) {
      rec = data[name];
    }
    if (rec) {
      let sub;
      if (optionElem != null) {
        sub = optionElem.value;
      } else {
        sub = field.value;
      }
      const val = rec[sub] ? sub : '#novalue#';
      return val.toString();
    }
    return '';
  }, // getChoiceFieldValue


  prefillComputedFields(form) {
    const fields = form.querySelectorAll('input');
    if (!fields.length) {
      return;
    }
    const typesToSkip = 'hidden,submit,cancel,checkbox,radio,button';
    fields.forEach((field) => {
      const type = field.getAttribute('type');
      const readonly = field.getAttribute('readonly') !== null;
      if (readonly || typesToSkip.includes(type)) {
        return;
      }

      // computed fields are identified by a leading '=' in 'data-preset':
      let val = '';
      if (field.dataset.preset !== undefined) {
        val = field.dataset.preset;
      }
      const ch1 = val.charAt(0);
      if (ch1 === '=') {
        val = val.substring(1);
        val = pfyFormsHelper.evalExpr(form, val);
        field.value = val;
      }

      // handle eventDuration-> field with 'data-event-duration':
      if (field.dataset.eventDuration) {
        this.handleEventFields(form, field);
      }
    });
  }, // prefillComputedFields


  executeOnPresetCallback(form) {
    if (form.dataset.presetCallback !== undefined) {
      executeCallbackCode(form.dataset.presetCallback, form);
    }
  }, // executeOnPresetCallback


  handleEventFields(form, field) {
    const duration = field.dataset.eventDuration ? parseInt(field.dataset.eventDuration) : 0;
    const relatedField = field.dataset.relatedField || false;
    let $startDate;
    if (relatedField) {
      $startDate = form.querySelector('[name=' + relatedField + ']');
    } else {
      $startDate = form.querySelector('[name=start]');
    }
    if (!$startDate) {
      return;
    }
    const preset = $startDate.dataset.preset || false;
    const now = new Date();
    const nextFullHour = this.roundUpMinutes(now);

    if (preset) {
      // preset event start:
      if (!$startDate.value) {
        if (preset === 'true') {
          $startDate.value = this.toIsoLocalString(nextFullHour);
        } else {
          let dateStr = this.toIsoLocalString();
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
        field.value = this.fixDatetimeFormat(field, this.addMinutes(end, duration));
      }
    }

    // handle changes in startDate -> adapt endDate:
    $startDate.addEventListener('change', (e) => {
      const start = new Date($startDate.value);
      const dur = field.dataset.eventDuration ? parseInt(field.dataset.eventDuration) : 0;
      let newVal = this.addMinutes(start, dur);
      newVal = this.fixDatetimeFormat(field, newVal);
      field.value = newVal;
    });

    if ($startDate.value && field.value) {
      const start = new Date($startDate.value);
      const end = new Date(field.value);
      field.dataset.eventDuration = (end.getTime() - start.getTime()) / 60000;
    }

    // handle changes in endDate -> adapt duration:
    field.addEventListener('change', (e) => {
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
  }, // fixDatetimeFormat


  toIsoLocalString(date) {
    if (typeof date === 'undefined') {
      date = new Date();
    }
    const currentIsoDateString = new Date(date - date.getTimezoneOffset() * 60000).toISOString();
    return currentIsoDateString.substring(0,16);
  }, // toIsoLocalString


  addMinutes(dateStr, minutes) {
    const t = Date.parse(dateStr);
    const date = new Date();
    date.setTime(t + (minutes * 60000));
    return this.toIsoLocalString(date);
  }, // addMinutes


  roundUpMinutes(date) {
    date.setHours(date.getHours() + Math.ceil(date.getMinutes()/60));
    date.setMinutes(0, 0, 0);
    return date;
  }, // roundUpMinutes


  checkHonigtopf(form) {
    let check = true;
    const checkElement = form.querySelector('[data-check]');
    if (checkElement) {
      const name = checkElement.dataset.check;
      const wrapper = document.querySelector('input[name="' + name + '"]').closest('.pfy-elem-wrapper');
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
    let pfyResponseValue = '?';
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
            // for usability: send form without clicking button, if correct key was pressed:
            const honigtopf = form.querySelector('[tabindex="-1"]');
            const val = referenceValue.charAt(0).toLowerCase();
            input.addEventListener('keyup', function (e) {
              if (e.key.toLowerCase() === val) {
                honigtopf.value = '';
                pfyFormsHelper.doSubmitForm(form);
              }
            });
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
      function () { }
    );
  }, // openAntiSpamPopup


  doSubmitForm(form) {
    const clone = form.cloneNode(true);
    const passwordInputs = clone.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(function(input) {
      input.value = '******';
    });

    domForAll(form, '.pfy-form-menuselect-group', menuselectEl => {
      if (menuselectEl.classList.contains('pfy-multiple-enabled')) {
        return;
      }
      // if in radio mode, transfer checked values to integer fields:
      domForAll(menuselectEl, 'input.pfy-radio', radioEl => {
        const val = radioEl.checked ? 1 : 0;
        domForOne(radioEl, '^.pfy-input-wrapper input.pfy-integer', (integerEl) => {
          integerEl.value = val;
        });
      });
    });

    const data = new FormData(clone);
    const dataStr = JSON.stringify(Array.from(data.entries()));
    serverLog('Browser submits: ' + dataStr, 'form-log.txt');

    // leave scroll-request in localStorage:
    localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));

    pfyFormsHelper.submitNow = true;
    this.clearModifiedFlag(form);
    form.submit();
  }, // doSubmitForm


  unlockRecs(tableInx) {
    if (!pfyFormsHelper.formRecLocking) {
      return;
    }

    if (typeof tableInx === 'undefined') {
      const tables = document.querySelectorAll('.pfy-table-wrapper');
      tables.forEach((table) => {
        const tInx = table.dataset.tableinx;
        const args = 'unlockAll&datasrcinx=' + tInx;
        execAjaxPromise(args, {}).catch(() => {});
      });
    } else {
      const args = 'unlockAll&datasrcinx=' + tableInx;
      execAjaxPromise(args, {}).catch(() => {});
    }
  }, // unlockRecs


  evalExpr(form, str) {
    let m;
    while (m = str.match(/\$([\w-_]+)/)) {
      const src = m[1];
      let v = str;
      const elem = form.querySelector('[name='+src+']');
      if (elem) {
        v = elem.value;
      }
      str = str.replace(m[0], `"${v}"`);
    }
    str = str.replace(/\n/g, '\\n');
    str = 'return ' + str;
    return new Function(str)();
  }, // evalExpr


  handleTextareaGrowers(ev) {
    if (!ev.target.closest('.pfy-auto-grow')) {
      return;
    }
    if (ev.target.tagName !== 'TEXTAREA') {
      return;
    }
    const textareaEl = ev.target;
    const growWrapper = textareaEl.closest('.pfy-input-wrapper');
    growWrapper.dataset.replicatedValue = textareaEl.value;
  }, // handleTextareaGrowers


  initAutoGrow(form) {
    // source: https://css-tricks.com/the-cleanest-trick-for-autogrowing-textareas/
    // preset replicatedValue:
    domForEach(form, 'textarea.pfy-auto-grow', (textareaEl) => {
      const growWrapper = textareaEl.closest('.pfy-input-wrapper');
      growWrapper.dataset.replicatedValue = textareaEl.value;
    });
  }, // initAutoGrow


  freezeWindowAfter(delay) {
    let t = 0;
    if (typeof delay === 'number') {
      t = delay;
    } else if (typeof delay === 'string') {
      const m = delay.match(/(\d+)\s*(\w+)/);
      if (m) {
        const unit = m[2];
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
    const img = hostAssetUrl + 'media/plugins/pgfactory/pagefactory-pageelements/icons/sleeping.png';
    const overlay = '<div class="pfy-overlay-background pfy-v-h-centered"><div><img src="' + img + '" alt="Sleeping..." class="pfy-timeout-img" /></div></div>';

    if (this.windowTimeout) {
      clearTimeout(this.windowTimeout);
    }

    this.windowTimeout = setTimeout(function () {
      const body = document.body;
      body.insertAdjacentHTML('beforeend', overlay);
      body.classList.add('pfy-overlay-background-frozen');
      }, t);
  }, // freezeWindowAfter


  handleFrozenWindow(ev) {
    const overlayElement = ev.target.closest('.pfy-overlay-background');
    if (!overlayElement) {
      return;
    }
    document.body.classList.remove('pfy-overlay-background-frozen');
    pfyConfirm({
      text: `{{ pfy-form-timeout-alert }}`,
      buttons: `Cancel,{{ pfy-form-reload-btn }}`,
    })
    .then(
      function () {
        pfyFormsHelper.reloadAgent();
      },
      function () {
        overlayElement.remove();
        pfyFormsHelper.freezeWindowAfter('1 minute');
      });
  }, // handleFrozenWindow


  initSpinner() {
   domForEach('.pfy-form-hidden-spinner img[data-src]', (el) => {
     const url = el.dataset.src;
     el.setAttribute('src', url);
   });
  }, // initSpinner


  setupMenuSelectWidget(form) {
    domForOne(form, '.pfy-form-menuselect-group', (el) => {
      this.menuSelectWrapperEl = el;
      const maxRefName = el.dataset.max;
      domForOne(form, `[name=${maxRefName}]`, el => {
        this.menuSelectRefEl = el;
      });
    });
  }, // setupMenuSelectWidget


  setTriggerOnContinueLink() {
    const continueLinks = document.querySelectorAll('.pfy-form-continue-same');
    continueLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));
      });
    });
  }, // setTriggerOnContinueLink


  // handle case where form wrapper is marked with class 'pfy-form-mark-as-modified':
  // -> this is the case when url-arg "?presetForm=ABCDEF&asmodified" is used
  handleMarkAsModifiedRequest(form) {
    const formWrapper = form.closest('.pfy-form-wrapper');
    if (formWrapper && formWrapper.classList.contains('pfy-form-mark-as-modified')) {
      formWrapper.classList.remove('pfy-form-mark-as-modified');
      formWrapper.classList.add('pfy-form-is-modified');
    }
  }, // handleMarkAsModifiedRequest


  repetitionChangeHandler(ev) {
    const selectEl = ev.target;
    const wrapper = selectEl.closest('.pfy-form-rrule-wrapper');
    if (!wrapper) {
      return;
    }

    const details = selectEl.closest('details');
    const rruleBody = details.querySelector('.pfy-form-rrule-body-wrapper');
    const selectedFreq = selectEl.options[selectEl.selectedIndex].value;
    this.applyRepetitionFreq(details, rruleBody, selectedFreq);
  }, // repetitionChangeHandler


  applyRepetitionFreq(details, rruleBody, selectedFreq) {
    if (selectedFreq === 'NONE') {
      details.open = false;
      rruleBody.classList.value = 'pfy-form-rrule-body-wrapper';
    } else {
      details.open = true;
      rruleBody.classList.value = 'pfy-form-rrule-body-wrapper pfy-form-rrule-' + selectedFreq.toLowerCase();
    }
  }, // applyRepetitionFreq


  // every time the menuSelect controller or one of the menuselect-group's children changes,
  // we need to update the max value of each option:
  menuSelectChangeHandler(ev) {
    if (ev.target !== this.menuSelectRefEl && !ev.target.closest('.pfy-form-menuselect-group')) {
      return;
    }

    const targetId = ev.target.getAttribute('aria-controls');
    const groupEl = targetId ? document.querySelector(targetId) : ev.target.closest('.pfy-form-menuselect-group');
    const maxVal = parseInt(this.menuSelectRefEl.value);

    let currSum = 0;
    if (!groupEl) {
      console.error('pfyFormsHelper.menuSelectChangeHandler: groupEl not found!');
      return;
    }

    // switch between radio and integer input:
    if (maxVal === 1) {
      // switch to radio:
      if (groupEl.classList.contains('pfy-multiple-enabled')) {
        groupEl.classList.remove('pfy-multiple-enabled');
        domForEach(groupEl, 'input.pfy-integer', (integerEl) => {
          const val = integerEl.value !== '0';
          domForOne(integerEl, '^.pfy-input-wrapper input.pfy-radio', (radioEl) => {
            radioEl.checked = val;
          });
        });
      }
      return;
    }

    // switch to integer:
    if (!groupEl.classList.contains('pfy-multiple-enabled')) {
      // initialize integer inputs after switching from radio:
      domForEach(groupEl, 'input.pfy-radio', (radioEl) => {
        const val = radioEl.checked ? 1 : 0;
        domForOne(radioEl, '^.pfy-input-wrapper input.pfy-integer', (integerEl) => {
          integerEl.value = val;
        });
      });
    }
    groupEl.classList.add('pfy-multiple-enabled');

    // determine sum of existing choices:
    domForEach(groupEl, 'input.pfy-integer', (inputEl) => {
      currSum += inputEl.value ? parseInt(inputEl.value) : 0;
    });
    groupEl.dataset.max = currSum;
    const available = maxVal - currSum;

    // update all integer inputs:
    domForEach(groupEl, 'input.pfy-integer', (inputEl) => {
      const currVal = inputEl.value ? parseInt(inputEl.value) : 0;
      inputEl.value = currVal;
      inputEl.setAttribute('max', currVal + available);
    });
  }, // menuSelectChangeHandler


  // used by calendar.js:
  initRepetitionWidget(wrapperEl = null) {
    domForEach(wrapperEl, '.pfy-form-rrule-wrapper', (rruleWrapper) => {
      domForOne(rruleWrapper, '.pfy-rrule-elem-freq', (select) => {
        select.addEventListener('change', (ev) => {
          this.repetitionChangeHandler(ev);
        });
      });
    });
  }, // initRepetitionWidget


  initReadonlyForm(form) {
    if (!form.closest('.pfy-form-readonly')) {
      return;
    }

    const typesToSkip = 'submit,cancel,button';
    const namesToSkip = '_csrf,_dataSrcInx,_form_';
    domForEach(form, 'div.pfy-elem-wrapper', function (fieldWrapperElemEl) {
      // get name and type:
      const name = fieldWrapperElemEl.querySelector('[name]').getAttribute('name').replace(/\[\]$/, '');
      let type = '';
      if (fieldWrapperElemEl.querySelector('select')) {
        type = 'select';
      } else if (fieldWrapperElemEl.querySelector("textarea")) {
        type = 'textarea';
      } else {
        type = fieldWrapperElemEl.querySelector('[type]').getAttribute('type');
      }

      domForOne(fieldWrapperElemEl, '[type=submit]', el => {
        el.classList.add('pfy-no-pointer-events');
      });
      if (namesToSkip.includes(name) || typesToSkip.includes(type)) {
        return;
      }
      // set readonly attribute:
      domForOne(fieldWrapperElemEl, 'input,textarea', el => {
        if (el.getAttribute('type') !== 'hidden') {
          el.setAttribute('readonly', true);
        }
      });

      domForAll(fieldWrapperElemEl, '[type=checkbox],[type=radio]', el => {
        el.classList.add('pfy-no-pointer-events');
      });
    });
  }, // initReadonlyForm


  reloadAgent(arg) {
    if ((typeof pfyFormRecLocking !== 'undefined') && pfyFormRecLocking) {
      pfyFormsHelper.unlockRecs();
    }
    reloadAgent(arg);
  }, // reloadAgent

}; // pfyFormsHelper


if ((typeof pfyFormRecLocking !== 'undefined') && pfyFormRecLocking) {
  window.addEventListener("beforeunload", (ev) => {
    pfyFormsHelper.unlockRecs();
  });
}
