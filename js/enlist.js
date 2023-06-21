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
          Enlist.openPopup(this, 'del');
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
    let options = {
      contentFrom: '#pfy-enlist-form .pfy-enlist-form-wrapper',
      header: `<span class="add">{{ pfy-enlist-add-popup-header }}</span><span class="del">{{ pfy-enlist-del-popup-header }}</span>`,
      autofocus: false,
    };
    if (typeof elem !== 'undefined') {
      const elemWrapper = elem.classList.contains('pfy-enlist-field')? elem: elem.closest('tr');
      const recId = elemWrapper.dataset.inx;
      options.onOpen = function() { Enlist.preparePopupForm(mode, elemWrapper, recId); };
    }
    Enlist.currentlyOpenPopup = pfyPopup(options);
  }, // openPopup


  preparePopupForm: function(mode, elemWrapper, recId) {
    const name = elemWrapper.querySelector('.pfy-enlist-name').textContent;
    const setInx = elemWrapper.closest('.pfy-enlist-wrapper').dataset.setinx;
    const popupWrapper = document.querySelector('.pfy-popup-wrapper');
    popupWrapper.classList.add('pfy-enlist-' + mode + '-mode');

    const form = popupWrapper.querySelector('.pfy-enlist-form-wrapper .pfy-form');
    const setinxElem = form.querySelector('[name=setinx]');
    setinxElem.setAttribute('value', setInx);

    const nameField = form.querySelector('[name=Name]');
    if (mode === 'add') {
      setTimeout(function() {
        nameField.focus();
      }, 60);
    }

    if (mode === 'del') {
      nameField.setAttribute('value', name);
      nameField.setAttribute('readonly', true);

      const nameLabel = nameField.parentElement.parentElement.querySelector('label');
      nameLabel.classList.remove('required');

      const submitBtn = form.querySelector('[name=_submit]');
      submitBtn.setAttribute('value', `{{ pfy-enlist-delete }}`);
      submitBtn.setAttribute('name', 'delete');

      const redIdField = form.querySelector('[name=recid]');
      redIdField.setAttribute('value', recId);

      const emailField = form.querySelector('[name=Email]');
      if (this.isEnlistAdmin) {
        const email = elemWrapper.querySelector('.pfy-enlist-email').textContent;
        emailField.setAttribute('value', email);
      } else {
        setTimeout(function () {
          emailField.focus();
        }, 60);
      }
    }
  } // preparePopupForm
};

Enlist.init();
