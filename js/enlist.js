/*
 * enlist.js
 */

const Enlist = {
  isEnlistAdmin: Boolean(document.querySelector('.pfy-enlist-admin')),
  isWindows: window.navigator.platform.match(/win/),
  currentlyOpenPopup: null,

  init: function() {
    this.addEventListeners();
    this.initTooltips();
    this.initPlaceholders();
  }, // init

  initPlaceholders: function () {
    domForEach('.pfy-enlist-wrapper', function (el) {
      const placeholder = el.dataset.placeholder;
      if (placeholder) {
        domForEach(el, '.pfy-enlist-add .pfy-enlist-name div', function (e) {
          const i = parseInt(e.closest('tr').dataset.reckey??0) + 1;
          e.innerHTML = placeholder.replace(/%%/, i);
          e.classList.add('pfy-placeholder');
        })
      }
    })
  },

  addEventListeners: function() {
    document.addEventListener('click', function(ev) {
      const el = ev.target;
      if (!el.closest('.pfy-enlist-icon, .pfy-enlist-sendmail-button, .pfy-enlist-delete-checkbox, .pfy-enlist-ical-button, .pfy-enlist-collapse-button')) {
        return;
      }
      ev.stopPropagation();
      if (el.closest('.pfy-enlist-sendmail-button')) {
        return Enlist.handleSendToAll(el);
      }

      if (el.closest('[name=delete_entry]')) {
        return Enlist.handleToggleModifyDelete(el);
      }

      if (Enlist.icalButtonHandler(ev)) {
        return;
      }

      if (Enlist.collapseListButtonHandler(ev)) {
        return;
      }

      Enlist.openPopup(el);
    });
  }, // addEventListeners


  handleSendToAll: function (el) {
    let mailAddresses = '';
    const enlistWrapper = el.closest('.pfy-enlist-wrapper');
    domForEach(enlistWrapper, '.pfy-enlist-email', el => {
      mailAddresses += ',' + el.innerText;
    });

    mailAddresses = mailAddresses.replace(/^[,;]/, '');
    mylog('MailTo: ' + mailAddresses);
    const url = `mailto:${mailAddresses}`;
    window.open(url,"_blank");
}, // handleSendToAll


  handleToggleModifyDelete: function (el) {
    domForOne(el, '^.pfy-enlist-delete-checkbox', el => {
      const isChecked = el.checked;
      domForOne(el, '^.pfy-form input.pfy-submit', btnEl => {
        if (isChecked) {
          btnEl.value = `{{ pfy-enlist-delete-btn }}`;
        } else {
          btnEl.value = `{{ pfy-enlist-modify-btn }}`;
        }
      });
    });
  },


  initTooltips: function() {
    const tooltips = document.querySelectorAll('.pfy-enlist-tooltip-anker');
    if (tooltips) {
      tippy('.pfy-enlist-tooltip-anker', {
        content: (el) => {
          const parentEl = el.parentElement;
          const textEl = parentEl.querySelector('.pfy-enlist-tooltip-content');
          let text = '';
          if (textEl) {
            text = textEl.innerHTML;
          }
          return text;
        },
        allowHTML: true,
        delay: 200,
        theme: 'light',
        trigger: 'mouseenter click focus',
      });
    }
  }, // initTooltips


  openPopup: function(elem) {
    let mode = '';
    if (elem.closest('.pfy-enlist-add')) {
      mode = 'add';

    } else if (elem.closest('.pfy-enlist-delete')) {
      mode = 'del';

    } else if (elem.closest('.pfy-enlist-modify')) {
      mode = 'modify';
    }

    if ('del,modify'.includes(mode)) {
      const hasExpiredClass = elem.closest('.pfy-enlist-expired');
      if (hasExpiredClass && !Enlist.isEnlistAdmin) {
        pfyAlert(`{{ pfy-enlist-deadline-expired-alert }}`);
        return;
      }
    }

    // check whether page timed out:
    if (pageLoaded < (Math.floor(Date.now()/1000) - 600)) {
      pfyConfirm({text: `{{ pfy-enlist-timed-out }}`})
        .then(function () {
          reloadAgent();
        });
      return;
    }

    let options = {
      contentFrom: '#pfy-enlist-form .pfy-form-wrapper',
      header: `<span class="add">{{ pfy-enlist-add-popup-header }}</span><span class="modify">{{ pfy-enlist-modify-popup-header }}</span><span class="del">{{ pfy-enlist-del-popup-header }}</span>`,
      autofocus: false,
      closeOnBgClick: true,
    };
    if (typeof elem !== 'undefined') {
      const rowEl = elem.classList.contains('pfy-enlist-field')? elem: elem.closest('tr');
      const enlistElemInx = rowEl.dataset.reckey;
      const widgetTitle = elem.closest('.pfy-enlist-wrapper').querySelector('.pfy-enlist-title-inner').innerText;
      options.onOpen = function() {
        Enlist.preparePopupForm(mode, rowEl, enlistElemInx);
        domForOne('.pfy-popup-wrapper .pfy-form-wrapper', popupFormWrapper => {
          if (mode === 'add' && rowEl.classList.contains('pfy-enlist-reserve')) {
            popupFormWrapper.classList.add('pfy-hide-direct-reserve');
          } else {
            popupFormWrapper.classList.remove('pfy-hide-direct-reserve');
          }
          domForOne(popupFormWrapper, 'input[name="widgetTitle"]', el => {
            el.value = widgetTitle;
          })
        });
      };
    }
    Enlist.currentlyOpenPopup = pfyPopup(options);
  }, // openPopup


  preparePopupForm: function(mode, rowEl, enlistElemInx) {
    const nameEl = rowEl.querySelector('.pfy-enlist-name span.pfy-enlist-name');
    const name = nameEl ? nameEl.innerText : '';
    const recKey = rowEl.dataset.reckey;
    const listEl = rowEl.closest('.pfy-enlist-wrapper');
    const widgetEl = rowEl.closest('.pfy-enlist-wrapper');
    const widgetInx = widgetEl.dataset.widgetInx;
    let   directreserve = listEl.dataset.directreserve;
    const popupWrapper = document.querySelector('.pfy-popup-wrapper');

    const formEl = popupWrapper.querySelector('.pfy-enlist-form-wrapper .pfy-form');

    localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));

    const deleteCheckboxEl = formEl.querySelector('input.pfy-enlist-delete-checkbox');
    if (deleteCheckboxEl && (mode !== 'add')) {
      domForOne(deleteCheckboxEl, '^tr .pfy-enlist-custom', () => {
        mode = 'modify';
      });
    }

    popupWrapper.classList.add('pfy-enlist-' + mode + '-mode');

    const hasCustomFields = formEl.querySelectorAll('.pfy-enlist-custom');

    // disable submit button to prevent multiple submits:
    const submitEl = formEl.querySelector('[type="submit"]');

    // inhibit submit by enter key while in textarea:
    const textareaFields = formEl.querySelectorAll('textarea');
    if (textareaFields.length) {
      textareaFields.forEach(function (textareaField) {
        textareaField.addEventListener('keyup', function (e) {
          if (e.key === 13) {
            e.stopPropagation();
          }
        });
      });
    }

    const nameField = formEl.querySelector('[name=Name]');
    const presetUser = (typeof userPreset !== 'undefined') && (!name || (name === userPreset.name));

    // handle user preset email:
    if (presetUser) {
      const emailField = formEl.querySelector('[name=Email]');
      emailField.setAttribute('value', userPreset.email);
    }

    domForEach(rowEl, '.pfy-enlist-custom', el => {
      const val = el.querySelector('div').innerText;
      const targClass = el.classList.value.match(/pfy-elem_\S+/)[0];
      console.log(`${targClass} => ${val}`);
      domForOne(formEl, `input.${targClass}`, targEl => {
        targEl.value = val;
      });
      domForOne(formEl, `textarea.${targClass}`, targEl => {
        targEl.innerText = val;
      });
    });

    const inxEl = formEl.querySelector('[name=widgetInx]');

    formEl.querySelector('[name=widgetInx]').value = `${widgetInx}/${recKey}`;

    // === add mode =========================================
    if (mode === 'add') {
      formEl.classList.add('pfy-enlist-add-mode');
      submitEl.setAttribute('value', `{{ pfy-enlist-add-btn }}`);
      submitEl.setAttribute('name', 'add');

      // handle user preset name:
      if (presetUser) {
        nameField.setAttribute('value', userPreset.name);
        pfyFormsHelper.setModifiedFlag(formEl);
      }

      if (deleteCheckboxEl) {
        deleteCheckboxEl.closest('.pfy-elem-wrapper').style.display = 'none';
      }

      setTimeout(function() {
        nameField.focus();
      }, 60);
    } else {

      nameField.setAttribute('value', name);
      const nameLabel = nameField.parentElement.parentElement.querySelector('label');
      nameLabel.classList.remove('required');

      this.fillFormValues(formEl, rowEl);

      // === delete mode ================================
      if (mode === 'del') {
        formEl.classList.add('pfy-enlist-delete-mode');
        nameField.setAttribute('readonly', true);

        // set submit button:
        submitEl.setAttribute('name', 'delete');
        submitEl.setAttribute('value', `{{ pfy-enlist-delete-btn }}`);

        directreserve = false;
        pfyFormsHelper.setModifiedFlag(formEl);

      } else // mode del

      // === modify mode ================================
      if (mode === 'modify') {
        formEl.classList.add('pfy-enlist-modify-mode');

        const modeEl = formEl.querySelector('[name=mode]');
        if (deleteCheckboxEl && !deleteCheckboxEl.checked) {
          if (hasCustomFields) {
            submitEl.setAttribute('value', `{{ pfy-enlist-modify-btn }}`);
          } else {
            submitEl.setAttribute('value', `{{ pfy-enlist-delete-btn }}`);
          }
          deleteCheckboxEl.addEventListener('change', function (e) {
            if (this.checked) {
              submitEl.value = `{{ pfy-enlist-delete-btn }}`;
              popupWrapper.classList.remove('pfy-enlist-modify-mode');
              popupWrapper.classList.add('pfy-enlist-del-mode');
              modeEl.value = 'del';
            } else {
              submitEl.value = `{{ pfy-enlist-modify-btn }}`;
              popupWrapper.classList.add('pfy-enlist-modify-mode');
              popupWrapper.classList.remove('pfy-enlist-del-mode');
              modeEl.value = 'modify';
            }
          });
        } else {
          submitEl.setAttribute('value', `{{ pfy-enlist-delete-btn }}`);
        }
        submitEl.setAttribute('name', 'modify');
      } // mode modify
    } // not add mode

    domForOne(formEl, '[name=mode]', el => {
      el.value = mode;
    })

    // hide directreserve elem if not used/required:
    if (!directreserve) {
      const directlyElem = formEl.querySelector('.pfy-enlist-directly');
      directlyElem.style.display = 'none';
      const directResInput = directlyElem.querySelector('input');
      directResInput.disabled = true;
    }
  }, // preparePopupForm


  fillFormValues: function(formEl, rowEl) {
    // get Email, if in admin mode:
    const emailField = formEl.querySelector('[name=Email]');
    if (this.isEnlistAdmin) {
      const mailEl = rowEl.querySelector('.pfy-enlist-email');
      if (mailEl) {
        const email = rowEl.querySelector('.pfy-enlist-email').textContent;
        emailField.setAttribute('value', email);
      }
    } else {
      // set focus to email-field:
      setTimeout(function () {
        emailField.focus();
      }, 60);
    }

    // get custom elements:
    const customFieldsEl = formEl.querySelectorAll('.pfy-elem-wrapper.pfy-enlist-custom');
    if (customFieldsEl) {
      customFieldsEl.forEach(function (el) {
        const classList = el.classList;
        let idy = false;
        classList.forEach(function(cls) {
          const m = cls.match(/pfy-elem_(.*)/);
          if (!idy && m) {
            idy = m[1];
          }
        });
        const inputElems = el.querySelectorAll('input');
        if (inputElems) {
          inputElems.forEach(inputEl => {
            const type = inputEl.type;

            if (type === 'radio' || type === 'checkbox') {
              let srcIdy = '.pfy-elem_' + idy;
              let tableEl = rowEl.querySelector(srcIdy.toLowerCase());
              if (tableEl) {
                // case no splitOutput:
                const x = tableEl.innerText;
                inputEl.checked = (tableEl.innerText.includes(optionLabel));

              } else {
                // case splitOutput:
                srcIdy = '.pfy-elem_' + idy + '-' + inputEl.value;
                tableEl = rowEl.querySelector(srcIdy.toLowerCase());
                if (tableEl) {
                  inputEl.checked = (tableEl.innerText !== '0');
                }
              }

            } else {
              const srcIdy = '.pfy-elem_' + idy;
              const tableEl = rowEl.querySelector(srcIdy);
              inputEl.value = tableEl.innerText;
            }
          });
        }

      }); // customFieldsEl
    }
  }, // fillFormValues


  icalButtonHandler: function(ev) {
    const btnEl = ev.target.closest('.pfy-enlist-ical-button');
    if (btnEl) {
      ev.stopPropagation();
      ev.preventDefault();
      domForOne(btnEl.parentElement, 'a', aEl => {
        aEl.click();
      });
      return true;
    }
    return false;
  }, // icalButtonHandler


  collapseListButtonHandler: function(ev) {
    const btnEl = ev.target.closest('.pfy-enlist-collapse-button');
    if (btnEl) {
      ev.stopPropagation();
      ev.preventDefault();
      let widgetTitle = '';
      domForOne(btnEl, '^.pfy-enlist-wrapper .pfy-enlist-title-inner', widgetTitleEl => {
        widgetTitle = encodeURI(widgetTitleEl.innerText);
      });
      pfyConfirm(`{{ pfy-enlist-collapse-confirm-text }}`).then(
        () => {
          const widgetInx = btnEl.closest('[data-widget-inx]').dataset.widgetInx;
          const arg = `?collapse-enlist=${widgetInx}&widgetTitle=${widgetTitle}`;
          reloadAgent(arg);
        },
        () => { console.log('Enlist collapse not executed.');}
      );
      return true;
    }
    return false;
  }, // collapseListButtonHandler

}; // Enlist

Enlist.init();
