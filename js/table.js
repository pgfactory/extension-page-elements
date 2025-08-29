/*
 *  Table helper
 *    -> support for table buttons: delete records, download table
 */

"use strict";

const tableHelper = {
  formRecLocking: false,
  recLocked: false,
  downloadButtonInitialized: false,
  tableWidgetWidths: {},

  init: function () {
    this.formRecLocking = (typeof pfyFormRecLocking !== 'undefined') && pfyFormRecLocking;

    const tables = document.querySelectorAll('.pfy-table');
    if ((typeof tables !== 'undefined') && tables.length) {
      tableHelper.setupEventHandlers();

      tables.forEach(function (table) {
        tableHelper.setupPropagateCheckbox(table); // problematic to handle by global trigger
      });
      tableHelper.prepareAdaptToWidthHandler();
    }
  }, // init


  setupEventHandlers: function () {
    document.addEventListener('click', function (ev) {
      const el = ev.target;

      if (el.closest('.pfy-table-delete-recs-open-dialog')) {
        tableHelper.openDeleteRecordsDialogHandler(ev);
      }
      if (el.closest('.pfy-table-mail-open-dialog')) {
        tableHelper.openCreateMailDialogHandler(ev);
      }
      if (el.closest('.pfy-table-archive-recs-open-dialog')) {
        tableHelper.openArchiveRecordsDialogHandler(ev);
      }
      if (el.closest('.pfy-table-download-start')) {
        tableHelper.downloadButtonHandler(ev);
      }
      if (el.closest('td .pfy-row-send-button')) {
        tableHelper.sendTableButtonHandler(ev);
      }
      if (el.closest('td .pfy-row-edit-button, .pfy-table-fill-first-row')) {
        tableHelper.editButtonsHandler(ev);
      }
      if (el.closest('.pfy-form-and-table-wrapper')) {
        tableHelper.newRecButtonHandler(ev);
      }
      if (ev.target.closest('tr') && !ev.target.closest('.pfy-service-col')) {
        tableHelper.rowTriggerhandler(ev);
      }
      if (ev.target.closest('.pfy-row-view-button')) {
        tableHelper.viewButtonsHandler(ev);
      }

    }); // click events

    document.addEventListener('change', function (ev) {
      const el = ev.target;
      if (el.closest('.pfy-table-buttons select')) {
        tableHelper.tableWidgetHandlerHandler(ev);
      }
    }); // change events

    document.addEventListener('keydown', function (ev) {
      tableHelper.rowKeyTriggerhandler(ev);
    }) // keydown events

    window.addEventListener('resize', function (ev) {
      tableHelper.adaptToWidthHandler();
    }) // keydown events

  }, // setupEventHandlers


  prepareAdaptToWidthHandler() {
    // need to wait for DataTables to finish rendering:
    setTimeout(function () {
      // now get size of table button bar and filter widget:
      domForAll('.pfy-table-wrapper', tableWrapperEl => {
        domForOne(tableWrapperEl, '.dt-search', el => {
          // copy label text to placeholder, in case there's not enough space for the label:
          let text = el.querySelector('label').innerText;
          text = text.replace(/:$/, '');
          const input = el.querySelector('input');
          input.setAttribute('placeholder', text);

          // determine width of filter widget:
          const id = tableWrapperEl.getAttribute('id');
          tableHelper.tableWidgetWidths[id] = {};
          const filterEl = tableWrapperEl.querySelector('.dt-search');
          if (filterEl) {
            tableHelper.tableWidgetWidths[id]['dtFilter'] = filterEl.offsetWidth;
            const filterInputEl = filterEl.querySelector('input');
            if (filterInputEl) {
              tableHelper.tableWidgetWidths[id]['dtFilterInput'] = filterInputEl.offsetWidth;
            }
          }
          // determine width of table button bar:
          const tableButtonsEl = tableWrapperEl.querySelector('.pfy-table-buttons');
          if (tableButtonsEl) {
            tableHelper.tableWidgetWidths[id]['buttons'] = tableButtonsEl.offsetWidth;
          }
        });
      });
      tableHelper.adaptToWidthHandler();
    }, 50);
  }, // prepareAdaptToWidthHandler


  adaptToWidthHandler() {
    // for filter field, copy label text to placeholder, in case table is too narrow to show label:
    domForAll('.pfy-table-wrapper', tableWrapperEl => {
      const id = tableWrapperEl.getAttribute('id');
      const wrapperElWidth = tableWrapperEl.getBoundingClientRect().width;
      const buttonsElWidth = tableHelper.tableWidgetWidths[id]['buttons'];
      const filterElWidth = tableHelper.tableWidgetWidths[id]['dtFilter'];
      const filterInputWidth = tableHelper.tableWidgetWidths[id]['dtFilterInput'];
        if (wrapperElWidth < (filterElWidth + buttonsElWidth)) { // space for buttons and filter and filter-label?
          if (wrapperElWidth < (filterInputWidth + buttonsElWidth)) { // space for buttons and filter but no filter-label?
            tableWrapperEl.classList.add('pfy-table-very-narrow');
            tableWrapperEl.classList.remove('pfy-table-narrow');
          } else {
            tableWrapperEl.classList.remove('pfy-table-very-narrow');
            tableWrapperEl.classList.add('pfy-table-narrow');
          }
        } else { // space for buttons and filter and filter-label
          tableWrapperEl.classList.remove('pfy-table-narrow,pfy-table-very-narrow');
        }
    });
  }, // adaptToWidthHandler


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
      domForEach(table, 'tbody .pfy-row-selector input[type=checkbox]', rowCheckbox => {
        rowCheckbox.checked = isChecked;
      });
    });
  }, // setupPropagateCheckbox


  openDeleteRecordsDialogHandler(ev) {
    ev.stopPropagation();
    const wrapper = ev.target.closest('.pfy-table-wrapper');
    const form = wrapper.querySelector('form');
    const table = wrapper.querySelector('table');
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
  }, // setupOpenDeleteRecordsDialog


  openArchiveRecordsDialogHandler(ev) {
    ev.stopPropagation();
    const wrapper = ev.target.closest('.pfy-table-wrapper');
    const form = wrapper.querySelector('form');
    const table = wrapper.querySelector('.pfy-table');
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
  }, // openArchiveRecordsDialogHandler



  openCreateMailDialogHandler(ev) {
    ev.stopPropagation();
    const mailFieldSelector = '.pfy-col-e-mail'; //ToDo: parameterize
    const wrapper = ev.target.closest('.pfy-table-wrapper');
    const table = wrapper.querySelector('table');
    let mailAddresses = '';
    let selected = table.querySelectorAll('tbody .pfy-row-selector input[type=checkbox]:checked');
    if (!selected.length) {
      // -> if none selected, include all
      selected = table.querySelectorAll('tbody .pfy-row-selector input');
    }
    if (selected.length) {
      selected.forEach(input => {
        domForOne(input, '^tr ' + mailFieldSelector, el => {
          mailAddresses += ',' + el.innerText;
        });
      });
    }

    mailAddresses = mailAddresses.replace(/^[,;]/, '');
    initiateMail({
      to: (typeof formOwnerEmail !== 'undefined') ? formOwnerEmail : 'me@domain.net',
      bcc: mailAddresses,
    });
  }, // openCreateMailDialogHandler


//  setupRowTriggers: function (table) {
//    const parent = this;
//    const tbody = table.querySelector('tbody');
    // handle clicks on row:
//    tbody.addEventListener('click', function (ev) {
//      if (!ev.target.closest('.pfy-service-col')) {
//        parent.rowTriggerhandler(ev);
//      }
//    });
//
    // if a row is selected, handle up and down cursor keys:
//    tbody.addEventListener('keydown', function (ev) {
//      domForEach(ev.target, '.pfy-row-selected', el => {
//        const key = ev.key;
//        if (key === 'ArrowUp') {
//          el = el.previousElementSibling;
//        } else if (key === 'ArrowDown') {
//          el = el.nextElementSibling;
//        } else {
//          return;
//        }
//        if (el) {
//          tableHelper.rowTriggerhandler(ev);
//        }
//      });
//      ev.preventDefault();
//    });
//  }, // setupRowTriggers


  rowKeyTriggerhandler: function (ev) {
//    let el = ev.target.closest('tr');
    let el = document.querySelector('tr.pfy-row-selected');
    if (!el) {
      return;
    }
    const key = ev.key;
    if (key === 'ArrowUp') {
      el = el.previousElementSibling;
    } else if (key === 'ArrowDown') {
      el = el.nextElementSibling;
    } else {
      return;
    }
    ev.stopImmediatePropagation();
    ev.stopPropagation();
    ev.preventDefault();
    if (el) {
      tableHelper.rowTriggerhandler(el);
    }
  }, // rowKeyTriggerhandler

/*
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
        });
      });
    }
  }, // setupEditButtons
*/

  editButtonsHandler: function (ev) {
    const el = ev.target;
    if (!el.closest('.pfy-form-and-table-wrapper')) {
      alert(`Error: edit button in table not supported without related form.`);
    }

    let table, tableFormWrapper, tableForm, tableInx, recKey;
    let editBtn;
    editBtn = el.closest('.pfy-row-edit-button');
    if (editBtn) {
      table = el.closest('.pfy-table');
      tableFormWrapper = table.closest('.pfy-form-and-table-wrapper');
      tableForm = tableFormWrapper.querySelector('.pfy-form');
      tableInx = table.dataset.tableinx;
      const tr = el.closest('tr');
      recKey = (typeof tr.dataset.reckey !== 'undefined') ? tr.dataset.reckey : '';

    } else {
      editBtn = el.closest('.pfy-table-fill-first-row');
      if (!editBtn) {
        return;
      }
      table = tableInx = false;
      tableFormWrapper = el.closest('.pfy-form-and-table-wrapper');
      tableForm = tableFormWrapper.querySelector('.pfy-form');
      tableInx = tableFormWrapper.dataset.tableinx;
    }

    ev.stopImmediatePropagation();
    tableHelper.prepareEditForm(table, tableForm, recKey, editBtn, tableInx);
  }, // setupEditButtons


  viewButtonsHandler: function (ev) {
    const el = ev.target;
    const table = ev.target.closest('.pfy-table');
    const tableInx = table.dataset.tableinx;
    const templateClass = '.pfy-table-view-template-' + tableInx;
    ev.stopImmediatePropagation();
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

  }, // viewButtonsHandler

//  setupViewButtons: function (table, tableInx) {
//    const parent = this;
//    const templateClass = '.pfy-table-view-template-' + tableInx;
//    const viewTemplateEl = document.querySelector(templateClass);
//    if (!viewTemplateEl) {
//      return;
//    }
//    const viewBtns = table.querySelectorAll('td .pfy-row-view-button');
//    if (viewBtns && viewBtns.length) {
//      viewBtns.forEach(function (viewBtn) {
//        viewBtn.addEventListener('click', function (ev) {
//          ev.stopImmediatePropagation();
//          parent.showViewTemplate(ev.target, table, tableInx, templateClass);
//        });
//      });
//    }
//  }, // setupViewButtons



  // === Table Button Handlers:

  downloadButtonHandler(ev) {
    ev.stopPropagation();
    ev.preventDefault();
    const btnEl = ev.target.closest('.pfy-table-download-start');
    domForOne(btnEl.parentElement, 'a', aEl => {
      aEl.click();
    });
  }, // downloadButtonHandler


  tableWidgetHandlerHandler(ev) {
    ev.stopPropagation();
    ev.preventDefault();
    const selectEl = ev.target;
    const selectedOption = selectEl.value;
    const callback = selectEl.dataset.callback;
    if (executeCallbackCode(callback, selectedOption) === null) {
      console.log(`table widget callback function "${callback}" missing.`);
    }
  }, // tableWidgetHandlerHandler


  sendTableButtonHandler(ev) {
    const table = ev.target.closest('.pfy-table');
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
  }, // sendTableButtonHandler


  doSendRec: function (args, recKey) {
    const input = document.getElementById('pfy-table-send-rec-input');
    if (input) {
      const email = encodeURI(input.value);
      mylog(window.location.href + '?sendto='+email+'&recId='+recKey);
      window.location.href = window.location.href + '?sendto='+email+'&recid='+recKey;
    }
  }, // doSendRec


  newRecButtonHandler: function (ev) {
    const newRecBtn = ev.target.closest('.pfy-table-new-rec');
    if (!newRecBtn) {
      return;
    }
    ev.stopPropagation();
    const tableWrapperEl = ev.target.closest('.pfy-form-and-table-wrapper');
    const tableEl = tableWrapperEl.querySelector('.pfy-table');
    const tableInx = tableEl.dataset.tableinx;
    const editbyPopupMode = tableEl.closest('.pfy-table-edit-popup');
    const formEl = tableWrapperEl.querySelector('.pfy-form-wrapper');
    if (editbyPopupMode) {
      const options = {
        header: `{{ pfy-table-new-rec-popup-header }}`,
        contentFrom: formEl,
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
                  const tableWrapper = tableEl.closest('.pfy-table-wrapper');
                  const newRecButton = tableWrapper.querySelector('.pfy-table-new-rec');
                  if (newRecButton) {
                    newRecButton.setAttribute('aria-expanded', 'false');
                  }
                });
              });
            }
          }
          tableHelper.enableEditButtons(tableEl);
        },
      };
      tableHelper.popupForm(options, editbyPopupMode, formEl);
      pfyFormsHelper.init('.pfy-popup-container .pfy-form', true);

    } else {
      pfyFormsHelper.init(formEl, true);
    }
    newRecBtn.setAttribute('aria-expanded', 'true');
  }, // newRecButtonHandler


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


  rowTriggerhandler: async function(ev) {

    let el = ev;
    if (!(ev instanceof Element)) {
      el = ev.target;
    }
    const tableWrapperEl = el.closest('.pfy-table-wrapper');
    if (!tableWrapperEl) {
      return;
    }
    const rowCallback = tableWrapperEl.dataset.rowCallback;
    if (!rowCallback) {
      return;
    }
    // invoke row callback function:
    if (typeof rowCallback === 'string' && rowCallback !== 'true' && isNaN(rowCallback)) {
      let res = executeCallbackCode(rowCallback, ev);
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
  }, // rowTriggerhandler


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


//  showViewTemplate: function (el, table, tableInx, templateClass) {
//    const row = el.closest('tr');
//    let data = {};
//    let names = [];
//    let i = 0;
//    const popupTemplateSelector = '.pfy-popup-container ';
//    pfyPopup({
//      contentFrom: '.pfy-table-wrapper ' + templateClass,
//      modal: false,
//      scrollHints: false,
//      closeOnBgClick: true,
//      onOpen: () => {
//        domForEach(`.pfy-table-${tableInx} th`, el => {
//          if (el.closest('.pfy-service-col')) {
//            return;
//          }
//          names[i++] = el.dataset.elemname;
//        });
//        i = 0;
//        domForEach(row, 'td > div', el => {
//          if (el.closest('.pfy-service-col')) {
//            return;
//          }
//          data[names[i++]] = el.innerText;
//        });
//        const aaa = document.querySelector(popupTemplateSelector);
//        domForEach(popupTemplateSelector + '  td + td', tdEl => {
//          const name = tdEl.innerText.replace(/%/g, '');
//          tdEl.innerText = data[name];
//        });
//      },
//
//    });
//  }, // showViewTemplate


  prepareEditForm: function (table, parentForm, recKey, editBtn, tableInx) {
    const editbyPopupMode = !table || table.closest('.pfy-table-edit-popup');
    const formWrapper = parentForm.closest('.pfy-form-wrapper');
    if (editbyPopupMode) {
      const options = {
        header: `{{ pfy-table-edit-rec-popup-header }}`,
        contentFrom: formWrapper,
        closeOnBgClick: false,
        onClose: function () {
          tableHelper.unlockRecs(tableInx);
        },
        onOpen: function () {
          const form = document.querySelector('.pfy-popup-wrapper .pfy-form');
          if (form) {
            pfyFormsHelper.init(form);
            pfyFormsHelper.fetchDataAndFillForm(form, recKey, true);
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
    let i = 10;
    let t = setInterval(() => {
      if (table.querySelector('.dt-search input')) {
        clearInterval(t);
        domForOne(table, '.dt-search input', filterEl => {
          filterEl.value = value;
          filterEl.dispatchEvent(new Event("input", { bubbles: true }));
        });
      } else if (i-- < 1) {
        clearInterval(t);
      }
    }, 10);
  } ,// activateDataTablesFilter


}; // tableHelper


tableHelper.init();
