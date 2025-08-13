/*
 *  Table helper
 *    -> support for table buttons: delete records, download table
 */

"use strict";

const tableHelper = {
  formRecLocking: false,
  recLocked: false,
  downloadButtonInitialized: false,

  init: function () {
    this.formRecLocking = (typeof pfyFormRecLocking !== 'undefined') && pfyFormRecLocking;

    const tables = document.querySelectorAll('.pfy-table');
    if ((typeof tables !== 'undefined') && tables.length) {
      tables.forEach(function (table) {
        tableHelper.setupPropagateCheckbox(table);
        tableHelper.setupOpenDeleteRecordsDialog(table);
        tableHelper.setupOpenCreateMailDialog(table);
        tableHelper.setupOpenArchiveRecordsDialog(table);
        tableHelper.setupTableButtons(table);

        // row service buttons:
        tableHelper.setupSendButtons(table);
        const tableInx = table.dataset.tableinx;
        // extension when called by PfyForms:
        if (table.closest('.pfy-form-and-table-wrapper')) {
          tableHelper.setupEditButtons(table, tableInx);
          tableHelper.setupNewRecButton(table, tableInx);
        }
        tableHelper.setupRowTriggers(table);
        tableHelper.setupViewButtons(table, tableInx);
      });
      tableHelper.setupInteractiveFilterHack();
    }
  }, // init


  setupInteractiveFilterHack() {
    // for filter field, copy label text to placeholder, in case table is too narrow to show label:
    setTimeout(function () {
      domForAll('.dt-search', el => {
        let text = el.querySelector('label').innerText;
        text = text.replace(/:$/, '');
        const input = el.querySelector('input');
        input.setAttribute('placeholder', text);
      });
    }, 50);
  }, // setupInteractiveFilterHack


  setupPropagateCheckbox: function (table) {
    const thead = table.querySelector('thead');
    thead.addEventListener('click', (ev) => {
      if (!ev.target.closest('.pfy-row-selector')) {
        return;
      }
      ev.stopImmediatePropagation();
      let checkboxEl = ev.target;
      if (checkboxEl.nodeName !== 'INPUT') {
        checkboxEl = checkboxEl.querySelector('input');
        checkboxEl.checked = !checkboxEl.checked;
      }
      var isChecked = checkboxEl.checked;
      domForEach('tbody .pfy-row-selector input[type=checkbox]', rowCheckbox => {
        rowCheckbox.checked = isChecked;
      });
    });
  }, // setupPropagateCheckbox


  setupOpenDeleteRecordsDialog: function (table) {
    const wrapper = table.closest('.pfy-table-wrapper');
    const form = wrapper.querySelector('form');
    if (!form) {
      return;
    }
    const deleteButton = form.querySelector('.pfy-table-delete-recs-open-dialog');
    if (deleteButton) {
      deleteButton.addEventListener('click', function (e) {
        e.stopPropagation();
        const wrapper = e.target.closest('.pfy-table-wrapper');
        const selected = table.querySelectorAll('tbody .pfy-row-selector input[type=checkbox]:checked');
        let options = {};
        if (!selected.length) {
          options = {
            text: `{{ pfy-table-delete-nothing-selected }}`,
            header: `{{ pfy-table-delete-recs-header }}`,
            closeOnBgClick: true,
            buttons: 'Ok'
          };
        } else {
          options = {
            text: `{{ pfy-data-delete-records }}`,
            header: `{{ pfy-table-delete-recs-header }}`,
            closeOnBgClick: true,
            buttons: 'Cancel, Confirm',
            wrapperClass: 'pfy-data-delete-records',
            callbackArg: form,
            onConfirm: function (that, form) {
              localStorage.setItem('scrollpos', parseInt(document.documentElement.scrollTop));
              form.setAttribute('action', pageUrl + '?delete');
              form.submit();
            }
          };
        }
        pfyPopup(options);
      });
    }
  }, // setupOpenDeleteRecordsDialog


  setupOpenArchiveRecordsDialog: function (table) {
    const wrapper = table.closest('.pfy-table-wrapper');
    const form = wrapper.querySelector('form');
    if (!form) {
      return;
    }
    const archiveButton = form.querySelector('.pfy-table-archive-recs-open-dialog');
    if (archiveButton) {
      archiveButton.addEventListener('click', function (e) {
        e.stopPropagation();
        const selected = table.querySelectorAll('tbody .pfy-row-selector input[type=checkbox]:checked');
        let options = {};
        if (!selected.length) {
          options = {
            text: `{{ pfy-table-delete-nothing-selected }}`,
            header: `{{ pfy-table-archive-recs-header }}`,
            closeOnBgClick: true,
            buttons: 'Ok'
          };
        } else {
          options = {
            text: `{{ pfy-data-archive-records }}`,
            header: `{{ pfy-table-archive-recs-header }}`,
            closeOnBgClick: true,
            buttons: 'Cancel, Confirm',
            wrapperClass: 'pfy-data-archive-records',
            callbackArg: form,
            onConfirm: function (that, form) {
              form.setAttribute('action', pageUrl + '?archive');
              form.submit();
            }
          };
        }
        pfyPopup(options);
      });
    }
  }, // setupOpenArchiveRecordsDialog


  setupOpenCreateMailDialog: function (table) {
    const parent = this;
    const wrapper = table.closest('.pfy-table-wrapper');
    const form = wrapper.querySelector('form');
    if (!form) {
      return;
    }
    const mailButton = form.querySelector('.pfy-table-mail-open-dialog');
    if (mailButton) {
      mailButton.addEventListener('click', function (e) {
        e.stopPropagation();
        let mailAddresses = '';
        let selected = table.querySelectorAll('tbody .pfy-row-selector input[type=checkbox]:checked');
        if (!selected.length) {
          // -> if none selected, include all
          selected = table.querySelectorAll('tbody .pfy-row-selector input');
        }
        if (selected.length) {
          let selector = '';
          selected.forEach(function (el) {
            const row = el.closest('tr');
            el = row.querySelector('.'+mailFieldSelector);
            if (el) {
              const email = el.innerText;
              mailAddresses += ',' + email;
            }
          });
        }

        mailAddresses = mailAddresses.replace(/^[,;]/, '');
        mylog('MailTo: ' + mailAddresses);
        const to = (typeof formOwnerEmail !== 'undefined') ? formOwnerEmail : 'me@domain.net';
        const url = `mailto:${to}?bcc=${mailAddresses}`;
        window.location.href = url;
      });
    }
  }, // setupOpenCreateMailDialog


  setupRowTriggers: function (table) {
    const parent = this;
    const tbody = table.querySelector('tbody');
    // handle clicks on row:
    tbody.addEventListener('click', function (ev) {
      if (!ev.target.closest('.pfy-service-col')) {
        parent.handleRowTrigger(ev);
      }
    });

    // if a row is selected, handle up and down cursor keys:
    tbody.addEventListener('keydown', function (ev) {
      domForEach(ev.target, '.pfy-row-selected', el => {
        const key = ev.key;
        if (key === 'ArrowUp') {
          el = el.previousElementSibling;
        } else if (key === 'ArrowDown') {
          el = el.nextElementSibling;
        } else {
          return;
        }
        if (el) {
          parent.handleRowTrigger(ev);
        }
      });
      ev.preventDefault();
    });
  }, // setupRowTriggers


  setupEditButtons: function (table, tableInx) {
    const parent = this;
    const tableFormWrapper = table.closest('.pfy-form-and-table-wrapper');
    if (!tableFormWrapper) {
      return;
    }
    const tableForm = tableFormWrapper.querySelector('.pfy-form');
    if (!tableForm) {
      return;
    }
    const editBtns = table.querySelectorAll('td .pfy-row-edit-button');
    if (editBtns && editBtns.length) {
      editBtns.forEach(function (editBtn) {
        editBtn.addEventListener('click', function (ev) {
          ev.stopImmediatePropagation();
          const tr = ev.target.closest('tr');
          const recKey = (typeof tr.dataset.reckey !== 'undefined') ? tr.dataset.reckey : '';
          parent.prepareEditForm(table, tableForm, recKey, editBtn, tableInx);
//          this.prepareEditForm(tableEl, tableForm, recKey, data, editBtn, tableInx);
//          parent.fillForm(ev.target, table, tableForm, tableInx, editBtn);
        });
      });
    }
  }, // setupEditButtons


  setupViewButtons: function (table, tableInx) {
    const parent = this;
    const templateClass = '.pfy-table-view-template-' + tableInx;
    const viewTemplateEl = document.querySelector(templateClass);
    if (!viewTemplateEl) {
      return;
    }
    const viewBtns = table.querySelectorAll('td .pfy-row-view-button');
    if (viewBtns && viewBtns.length) {
      viewBtns.forEach(function (viewBtn) {
        viewBtn.addEventListener('click', function (ev) {
          ev.stopImmediatePropagation();
          parent.showViewTemplate(ev.target, table, tableInx, templateClass);
        });
      });
    }
  }, // setupViewButtons



  // === Table Button Handlers:

  setupTableButtons: function (table) {
    tableHelper.setupDownloadButtonHandler();
    tableHelper.setupTableWidgetHandler(table);
  }, // setupTableButtons


  setupDownloadButtonHandler: function() {
    if (this.downloadButtonInitialized) {
      return;
    }
    this.downloadButtonInitialized = true;
    domForOne('.pfy-table-download-start', downloadBtn => {
      downloadBtn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        ev.preventDefault();
        const btnEl = ev.target.closest('.pfy-table-download-start');
        domForOne(btnEl.parentElement, 'a', aEl => {
          aEl.click();
        });
      });
    });
  }, // setupDownloadButtonHandler


  setupTableWidgetHandler: function () {
    domForEach('.pfy-table-buttons select', selectWidget => {
      selectWidget.addEventListener('change', function (ev) {
        ev.stopPropagation();
        ev.preventDefault();
        const selectEl = ev.target;
        const selectedOption = selectEl.value;
        const callback = selectEl.dataset.callback;
        if (typeof callback === 'string' && callback && (typeof window[callback] === 'function')) {
          window[callback](ev, selectedOption);
        } else {
          console.log(`table widget callback function "${callback}" missing.`);
        }
      });
    });
  }, // setupTableWidgetHandler


  setupSendButtons: function (table) {
    const parent = this;
    const sendBtns = table.querySelectorAll('td .pfy-row-send-button');
    if (sendBtns && sendBtns.length) {
      sendBtns.forEach(function (sendBtn) {
        sendBtn.addEventListener('click', function () {
          // upon clicking one of the edit buttons:
          tableHelper.disableEditButtons(table);
          const headers = [];
          let i = 0;
          domForAll(table, 'th:not(.pfy-service-col)', th => {
            let str = th.innerText + ':';
            str = str.padEnd(6, ' ') + '\t\t';
            headers[i++] = str;
          });
          const tr = this.closest('tr');
          let body = '\n\n';
          i = 0;
          domForAll(tr, 'td:not(.pfy-service-col) div', el => {
            body += `${headers[i++]} ${el.innerText}\n`;
          })
          console.log(`send: ${body}`);
          initiateMail({body: body});
        });
      });
    }
  }, // setupSendButtons


  doSendRec: function (args, recKey) {
    const input = document.getElementById('pfy-table-send-rec-input');
    if (input) {
      const email = encodeURI(input.value);
      mylog(window.location.href + '?sendto='+email+'&recId='+recKey);
      window.location.href = window.location.href + '?sendto='+email+'&recid='+recKey;
    }
  }, // doSendRec


  setupNewRecButton: function (table, tableInx) {
    const formWrapper = table.closest('.pfy-form-and-table-wrapper');
    if (!formWrapper) {
      return;
    }
    const parentForm = formWrapper.querySelector('.pfy-form');
    const newRecBtn = formWrapper.querySelector('.pfy-table-new-rec');
    if (newRecBtn) {
      newRecBtn.addEventListener('click', function () {
        const editbyPopupMode = table.classList.contains('pfy-table-edit-popup');
        if (editbyPopupMode) {
          const options = {
            header: `{{ pfy-table-new-rec-popup-header }}`,
            contentFrom: parentForm,
            closeOnBgClick: false,
            id: 'pfy-popup-form',
            onOpen: function () {
              mylog('prepareEditForm - onOpen new');
              const form = document.querySelector('.pfy-popup-wrapper .pfy-form');
              if (form) {
                form.removeAttribute('aria-hidden');
                form.removeAttribute('id');

                const cancelInputs = form.querySelectorAll('input.pfy-cancel');
                if (cancelInputs.length) {
                  cancelInputs.forEach(function(input) {
                    input.addEventListener('click', function(e) {
                      pfyPopupClose();
                      tableHelper.unlockRecs(tableInx);
                      // set newRecButton back to not-expanded:
                      const tableWrapper = table.closest('.pfy-table-wrapper');
                      const newRecButton = tableWrapper.querySelector('.pfy-table-new-rec');
                      if (newRecButton) {
                        newRecButton.setAttribute('aria-expanded', 'false');
                      }
                    });
                  });
                }
              }
              tableHelper.enableEditButtons(table);
            },
          };
          tableHelper.popupForm(options, editbyPopupMode, parentForm);
          pfyFormsHelper.init('.pfy-popup-container .pfy-form', true);

        } else {
          pfyFormsHelper.init(parentForm, true);
        }
        newRecBtn.setAttribute('aria-expanded', 'true');
      });
    }
  }, // setupNewRecButton


  setupCancelButton: function(table, tableInx) {
    const cancelInputs = document.querySelectorAll('.pfy-popup-wrapper .pfy-form input.pfy-cancel');
    if (cancelInputs.length) {
      cancelInputs.forEach(function(input) {
        input.addEventListener('click', function(e) {
          pfyPopupClose();
          tableHelper.unlockRecs(tableInx);
          const newRecOpenButton = table.querySelector('.pfy-table-new-rec');
          if (newRecOpenButton) {
            newRecOpenButton.setAttribute('aria-expanded', 'false');
          }
        });
      });
    }
  }, // setupCancelButton


  handleRowTrigger: async function(ev) {
    const el = ev.target;
    const tableWrapperEl = el.closest('.pfy-table-wrapper');
    const rowCallback = tableWrapperEl.dataset.rowCallback;
    if (!rowCallback) {
      return;
    }

    // invoke row callback function:
    if (typeof rowCallback === 'string' && rowCallback !== 'true' && isNaN(rowCallback)) {
      let res = await window[rowCallback](ev);
      if (!res) {
        return;
      }
    }

    // default callback handler for tables linked with a form:
    const tableFormWrapper = el.closest('.pfy-form-and-table-wrapper');
    if (!tableFormWrapper) {
      return;
    }

    if (pfyFormsHelper.isFormModified(tableFormWrapper)) {
      pfyConfirm({
        text: `{{ pfy-tableform-data-modified-warning }}`,
      }).then(
        () => {
          // after confirmation:
          this.defaultRowClickHandler(el, tableFormWrapper);
        },
        () => { mylog('denied');  }
      );
    } else {
      this.defaultRowClickHandler(el, tableFormWrapper);
    }
  }, // handleRowTrigger

//  handleRowTrigger: function(ev) {
//    const el = ev.target;
//    const tableWrapperEl = el.closest('.pfy-table-wrapper');
//    const rowCallback = tableWrapperEl.dataset.rowCallback;
//    if (!rowCallback) {
//      return;
//    }
//
//    // invoke row callback function:
//    if (typeof rowCallback === 'string' && rowCallback !== 'true' && isNaN(rowCallback)) {
//      let res = window[rowCallback](ev);
//      if (!res) {
//        return;
//      }
//    }
//
//    // default callback handler for tables linked with a form:
//    const tableFormWrapper = el.closest('.pfy-form-and-table-wrapper');
//    if (!tableFormWrapper) {
//      return;
//    }
//
//    if (pfyFormsHelper.isFormModified(tableFormWrapper)) {
//      pfyConfirm({
//        text: `{{ pfy-tableform-data-modified-warning }}`,
//      }).then(
//        () => {
//          // after confirmation:
//          this.defaultRowClickHandler(el, tableFormWrapper);
//        },
//        () => { mylog('denied');  }
//      );
//    } else {
//      this.defaultRowClickHandler(el, tableFormWrapper);
//    }
//  }, // handleRowTrigger


  defaultRowClickHandler: function(el, tableFormWrapper) {
    if (typeof tableFormWrapper === 'undefined') {
      tableFormWrapper = el.closest('.pfy-form-and-table-wrapper');
    }
    const tableEl = el.closest('.pfy-table');
    const tableForm = tableFormWrapper.querySelector('.pfy-form');
    const tr = el.closest('tr');
    if (tr && tr.classList.contains('pfy-row-selected')) {
      pfyFormsHelper.presetForm(tableForm); // reset form
      tr.classList.remove('pfy-row-selected');

    } else {
      domForEach(tableEl, 'tr', tr => {
        tr.classList.remove('pfy-row-selected');
      });
      const tableInxEL = tableFormWrapper.querySelector('[data-tableinx]');
      if (!tableInxEL) {
        return;
      }
      const tableInx = tableInxEL.dataset.tableinx;
      if (tr) {
        tr.classList.add('pfy-row-selected');
        const recKey = (typeof tr.dataset.reckey !== 'undefined') ? tr.dataset.reckey : '';
        this.prepareEditForm(tableEl, tableForm, recKey, null, tableInx);
//        this.prepareEditForm(tableEl, tableForm, recKey, data, null, tableInx);
//        this.fillForm(el, tableEl, tableForm, tableInx, null);
      }
    }
  }, // defaultRowClickHandler


  fillForm: function (el, table, tableForm, tableInx, editBtn) {
    // upon clicking one of the edit buttons:
    tableHelper.disableEditButtons(table);
    const tr = el.closest('tr');
    const recKey = (typeof tr.dataset.reckey !== 'undefined') ? tr.dataset.reckey : '';

    // get latest data for this record:
    const formInx = tableForm.querySelector('[name=_formInx]').value
    domForOne('.pfy-form-' + formInx, form => {
      pfyFormsHelper.fetchDataAndFillForm(form, recKey, true);
    });

    if (typeof editBtn !== 'undefined' && editBtn) {
      editBtn.setAttribute('aria-expanded', 'true');
    }
  }, // fillForm


  showViewTemplate: function (el, table, tableInx, templateClass) {
    const row = el.closest('tr');
    let data = {};
    let names = [];
    let i = 0;
    const popupTemplateSelector = '.pfy-popup-container ';
    pfyPopup({
      contentFrom: '.pfy-table-wrapper ' + templateClass,
      modal: false,
      scrollHints: false,
      closeOnBgClick: true,
      onOpen: () => {
        domForEach(`.pfy-table-${tableInx} th`, el => {
          if (el.closest('.pfy-service-col')) {
            return;
          }
          names[i++] = el.dataset.elemname;
        });
        i = 0;
        domForEach(row, 'td > div', el => {
          if (el.closest('.pfy-service-col')) {
            return;
          }
          data[names[i++]] = el.innerText;
        });
        const aaa = document.querySelector(popupTemplateSelector);
        domForEach(popupTemplateSelector + '  td + td', tdEl => {
          const name = tdEl.innerText.replace(/%/g, '');
          tdEl.innerText = data[name];
        });
      },

    });
  }, // showViewTemplate


  prepareEditForm: function (table, parentForm, recKey, editBtn, tableInx) {
//  prepareEditForm: function (table, parentForm, recKey, data, editBtn, tableInx) {
    const editbyPopupMode = table.closest('.pfy-table-edit-popup');
//    const editbyPopupMode = table.classList.contains('pfy-table-edit-popup');
    if (editbyPopupMode) {
      const options = {
        header: `{{ pfy-table-edit-rec-popup-header }}`,
        contentFrom: parentForm,
        closeOnBgClick: false,
        onClose: function () {
          tableHelper.unlockRecs(tableInx);
        },
        onOpen: function () {
          const form = document.querySelector('.pfy-popup-wrapper .pfy-form');
          if (form) {
            pfyFormsHelper.init(form);
            pfyFormsHelper.fetchDataAndFillForm(form, recKey, true);
//            pfyFormsHelper.presetForm(form, data, recKey);
            tableHelper.setupCancelButton(table, tableInx);
            form.removeAttribute('aria-hidden');
            form.removeAttribute('id');
          }
        }
      };
      tableHelper.popupForm(options, editbyPopupMode, parentForm)
        .then(function (data) {
          tableHelper.enableEditButtons(table);
          if (editBtn) {
            editBtn.setAttribute('aria-expanded', 'false');
          }
        })
   } else {
      pfyFormsHelper.fetchDataAndFillForm(parentForm, recKey, true);
//      pfyFormsHelper.presetForm(parentForm, data, recKey);
      tableHelper.enableEditButtons(table);
    }
  }, // prepareEditForm


  popupForm: function(options, editbyPopupMode, parentForm) {
    if (editbyPopupMode === true) {
      if (parentForm.classList.contains('pfy-fully-hidden')) {
        const id = parentForm.getAttribute('id');
        const wrapperId = id + '-wrapper';
        let html = parentForm.outerHTML;
        html =  html.replace(/pfy-fully-hidden/, '');
        const wrapperHtml = '<div id="' + wrapperId + '" class="pfy-fully-hidden"></div>';
        let form = document.getElementById(id);
        form.outerHTML = wrapperHtml;
        let formWrapper = document.getElementById(wrapperId);
        formWrapper.innerHTML = html;
        parentForm = formWrapper.querySelector('.pfy-form');
        options.contentFrom = parentForm;
      }
    }
    return pfyPopupPromise(options);
  }, // popupForm


  disableEditButtons: function (table) {
    const editButtons = document.querySelectorAll('.pfy-table-wrapper button');
    if (editButtons) {
      editButtons.forEach(function (editButton) {
        editButton.disabled = true;
      });
    }
  }, // disableEditButtons


  enableEditButtons: function (table) {
    const editButtons = document.querySelectorAll('.pfy-table-wrapper button');
    if (editButtons) {
      editButtons.forEach(function (editButton) {
        editButton.disabled = false;
      });
    }
  }, // enableEditButtons


  unlockRecs: function (tableInx) {
    if (!tableHelper.formRecLocking) {
      return;
    }

    if (typeof tableInx === 'undefined') {
      const tables = document.querySelectorAll('.pfy-table');
      if (tables) {
        tables.forEach(function (table) {
          if ((table.dataset.unlockExecuted !== 'undefined') && table.dataset.unlockExecuted) {
            return;
          }
          const tableInx = table.dataset.tableinx;
          table.dataset.unlockExecuted = true;
          mylog('unlocking locked records (' + tableInx + ')');

          const args = 'unlockAll' + '&datasrcinx=' + tableInx;
          execAjaxPromise(args, {})
            .then(function (data) {
              mylog(data);
            })
            .then(function (msg) {});
        });
      }
    } else {
      const patt = 'table[data-tableinx="'+tableInx+'"]';
      const table = document.querySelector(patt);
      if ((table.dataset.unlockExecuted !== 'undefined') && table.dataset.unlockExecuted) {
        return;
      }
      table.dataset.unlockExecuted = true;
      mylog('unlocking locked records (' + tableInx + ')');

      const args = 'unlockAll' + '&datasrcinx=' + tableInx;
      execAjaxPromise(args, {})
        .then(function (data) {
          mylog(data);
        })
        .then(function (msg) {});
    }
  }, // unlockRecs


  filter: function (table, filterStr) {
    let tableInx = 1;
    if (typeof table === 'object') {
      tableInx = table.dataset.tableinx;
    } else if (typeof table === 'number') {
      tableInx = table;
    }
    pfyDataTable[tableInx].search( filterStr ).draw();
  }, // filter


  activateDataTablesFilter: function(table, value) {
    domForOne(table, '.dt-search input', filterEl => {
      filterEl.value = value;
      filterEl.dispatchEvent(new Event("input", { bubbles: true }));
    });
  } ,// activateDataTablesFilter


}; // tableHelper


tableHelper.init();
