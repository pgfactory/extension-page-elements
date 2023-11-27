/*
 * enlist.js
 */

const Enlist = {
  isEnlistAdmin: Boolean(document.querySelector('.pfy-enlist-admin')),
  isWindows: window.navigator.platform.match(/win/),
  currentlyOpenPopup: null,

  init: function() {
    this.addEventListeners();
  }, // init

  addEventListeners: function() {
    const addButtons = this.isEnlistAdmin?
        document.querySelectorAll('.pfy-enlist-add button'):
        document.querySelectorAll('.pfy-enlist-add');

    if (addButtons) {
      addButtons.forEach(function(addButton) {
        addButton.addEventListener('click', function(e) {
          e.stopPropagation();
          Enlist.openPopup(this, 'add');
        });
      });
    } // addButtons


    const delButtons = this.isEnlistAdmin?
        document.querySelectorAll('.pfy-enlist-delete button'):
        document.querySelectorAll('.pfy-enlist-delete');

    if (delButtons) {
      delButtons.forEach(function(delButton) {
        delButton.addEventListener('click', function(e) {
          e.stopPropagation();
          const hasExpiredClass = this.closest('.pfy-enlist-expired');
          if (hasExpiredClass && !Enlist.isEnlistAdmin) {
            pfyAlert(`{{ pfy-enlist-deadline-expired-alert }}`);
          } else {
            Enlist.openPopup(this, 'del');
          }
        });
      });
    } // delButtons


    const sendToAllBtns = document.querySelectorAll('.pfy-enlist-sendmail-button');
    if (sendToAllBtns) {
      const sep = this.isWindows ? ';' : ',';
      let mailAddresses = '';
      sendToAllBtns.forEach(function(adminBtn) {
        adminBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          e.stopImmediatePropagation();
          e.preventDefault();
          const wrapper = adminBtn.closest('.pfy-enlist-wrapper');
          const entries = wrapper.querySelectorAll('.pfy-enlist-delete');
          if (entries) {
            entries.forEach(function (entry) {
              const name = entry.querySelector('.pfy-enlist-name').textContent;
              const email = entry.querySelector('.pfy-enlist-email').textContent;
              mailAddresses = mailAddresses + sep + email;
            });
          }

          mailAddresses = mailAddresses.replace(/^[,;]/, '');
          mylog('MailTo: ' + mailAddresses);
          const to = (typeof adminEmail !== 'undefined') ? adminEmail : 'me@domain.net';
          const url = `mailto:${to}?cc=${mailAddresses}`;
          window.open(url,"_blank");
        });
      });
    } // sendToAllBtns
  }, // addEventListeners


  openPopup: function(elem, mode) {
    // check whether page timed out:
    if (pageLoaded < (Math.floor(Date.now()/1000) - 600)) {
      pfyConfirm({text: `{{ pfy-form-timed-out }}`})
        .then(function () {
          reloadAgent();
        });
      return;
    }

    let options = {
      contentFrom: '#pfy-enlist-form .pfy-enlist-form-wrapper',
      header: `<span class="add">{{ pfy-enlist-add-popup-header }}</span><span class="modify">{{ pfy-enlist-modify-popup-header }}</span><span class="del">{{ pfy-enlist-del-popup-header }}</span>`,
      autofocus: false,
      closeOnBgClick: true,
    };
    if (typeof elem !== 'undefined') {
      const $tableRow = elem.classList.contains('pfy-enlist-field')? elem: elem.closest('tr');
      const elemId = $tableRow.dataset.reckey;
      options.onOpen = function() { Enlist.preparePopupForm(mode, $tableRow, elemId); };
    }
    Enlist.currentlyOpenPopup = pfyPopup(options);

    const form = document.querySelector('.pfy-popup-container .pfy-form');
    if (form) {
      pfyFormsHelper.setupModifiedMonitor(form);
    }
  }, // openPopup



  preparePopupForm: function(mode, $tableRow, elemId) {
    let name = $tableRow.querySelector('.pfy-enlist-name').innerHTML;
    name = name.substring(5).replace('\n', '');
    name = name.replace(/\s*<.*/, '');
    const $list = $tableRow.closest('.pfy-enlist-wrapper');
    const setname = $list.dataset.setname;
    let   directreserve = $list.dataset.directreserve;
    const popupWrapper = document.querySelector('.pfy-popup-wrapper');

    const $form = popupWrapper.querySelector('.pfy-enlist-form-wrapper .pfy-form');

    localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));

    const $deleteCheckbox = $form.querySelector('#pfy-enlist-delete');
    let $deleteChoice = null;
    if ($deleteCheckbox && (mode !== 'add')) {
      $deleteChoice = $deleteCheckbox.closest('.pfy-elem-wrapper');
      mode = 'modify';
    }

    popupWrapper.classList.add('pfy-enlist-' + mode + '-mode');

    const setnameElem = $form.querySelector('[name=setname]');
    const customFields = $form.querySelectorAll('.pfy-enlist-custom');
    setnameElem.setAttribute('value', setname);

    // disable submit button to prevent multiple submits:
    const $submit = $form.querySelector('[type="submit"]');
    $submit.disabled = true;

    // inhibit submit by enter key while in textarea:
    const textareaFields = $form.querySelectorAll('textarea');
    if (textareaFields.length) {
      textareaFields.forEach(function (textareaField) {
        textareaField.addEventListener('keyup', function (e) {
          if (e.key === 13) {
            e.stopPropagation();
          }
        });
      });
    }

    const nameField = $form.querySelector('[name=Name]');

    this.setupModifiedMonitor($form);

    // === add mode =========================================
    if (mode === 'add') {
      $form.classList.add('pfy-enlist-add-mode');
      $submit.setAttribute('value', `{{ pfy-enlist-add-btn }}`);
      $submit.setAttribute('name', 'add');

      if ($deleteChoice) {
        $deleteChoice.style.display = 'none';
      }

      setTimeout(function() {
        nameField.focus();
      }, 60);
    } else {

      nameField.setAttribute('value', name);
      const nameLabel = nameField.parentElement.parentElement.querySelector('label');
      nameLabel.classList.remove('required');

      this.fillFormValues($form, $tableRow, elemId);

      // === delete mode ================================
      if (mode === 'del') {
        $form.classList.add('pfy-enlist-delete-mode');
        nameField.setAttribute('readonly', true);

        // set submit button:
        $submit.setAttribute('name', 'delete');
        $submit.setAttribute('value', `{{ pfy-enlist-delete-btn }}`);

        directreserve = false;
      } else // mode del


      // === modify mode ================================
      if (mode === 'modify') {
        $form.classList.add('pfy-enlist-modify-mode');

        if ($deleteChoice) {
          if (customFields) {
            $submit.setAttribute('value', `{{ pfy-enlist-modify-btn }}`);
          } else {
            $submit.setAttribute('value', `{{ pfy-enlist-delete-btn }}`);
          }
          $deleteCheckbox.addEventListener('change', function (e) {
            if (this.checked) {
              $submit.value = `{{ pfy-enlist-delete-btn }}`;
                popupWrapper.classList.remove('pfy-enlist-modify-mode');
                popupWrapper.classList.add('pfy-enlist-del-mode');
            } else {
              $submit.value = `{{ pfy-enlist-modify-btn }}`;
                popupWrapper.classList.add('pfy-enlist-modify-mode');
                popupWrapper.classList.remove('pfy-enlist-del-mode');
            }
          });
        } else {
          $submit.setAttribute('value', `{{ pfy-enlist-delete-btn }}`);
        }
        $submit.setAttribute('name', 'modify');
      } // mode modify
    } // not add mode

  }, // preparePopupForm


  setupModifiedMonitor: function ($form) {
    // enable submit when email-field non-empty:
    const $submit = $form.querySelector('[type="submit"]');
    const nameField = $form.querySelector('[name=Name]');
      const emailField = $form.querySelector('[name=Email]');
      nameField.addEventListener('keyup', function(e) {
      if (this.value && emailField.value) {
        $submit.disabled = false;
      }
    });
    emailField.addEventListener('keyup', function(e) {
      if (this.value && nameField.value) {
        $submit.disabled = false;
      }
    });
  }, // setupModifiedMonitor


  fillFormValues: function($form, $tableRow, elemId) {
    // get elemId
    const recIdField = $form.querySelector('[name=elemId]');
    recIdField.setAttribute('value', elemId);

    // get Email, if in admin mode:
    const emailField = $form.querySelector('[name=Email]');
    if (this.isEnlistAdmin) {
      const $email = $tableRow.querySelector('.pfy-enlist-email');
      if ($email) {
        const email = $tableRow.querySelector('.pfy-enlist-email').textContent;
        emailField.setAttribute('value', email);
      }
      const $submit = $form.querySelector('[type="submit"]');
      $submit.disabled = false;
    } else {
      // set focus to email-field:
      setTimeout(function () {
        emailField.focus();
      }, 60);
    }

    // get custom elements:
    const $customFields = $form.querySelectorAll('.pfy-elem-wrapper.pfy-enlist-custom');
    if ($customFields) {
      $customFields.forEach(function ($elem) {
        const classList = $elem.classList;
        let idy = false;
        classList.forEach(function(cls) {
          const m = cls.match(/pfy-elem_(.*)/);
          if (!idy && m) {
            idy = m[1];
          }
        });
        const $inputs = $elem.querySelectorAll('input');
        if ($inputs) {
          $inputs.forEach($input => {
            const type = $input.type;

            if (type === 'radio' || type === 'checkbox') {
              let srcIdy = '.pfy-elem_' + idy;
              let $tableElem = $tableRow.querySelector(srcIdy.toLowerCase());
              if ($tableElem) {
                // case no splitOutput:
                const x = $tableElem.innerText;
                $input.checked = ($tableElem.innerText.includes(optionLabel));

              } else {
                // case splitOutput:
                srcIdy = '.pfy-elem_' + idy + '-' + $input.value;
                $tableElem = $tableRow.querySelector(srcIdy.toLowerCase());
                if ($tableElem) {
                  $input.checked = ($tableElem.innerText !== '0');
                }
              }

            } else {
              const srcIdy = '.pfy-elem_' + idy;
              const $tableElem = $tableRow.querySelector(srcIdy);
              $input.value = $tableElem.innerText;
            }
          });
        }

      }); // $customFields
    }
  }, // fillFormValues


};

Enlist.init();
