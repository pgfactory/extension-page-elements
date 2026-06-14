/*
 *  Table helper
 *    -> support for table buttons: delete records, download table
 */

"use strict";

console.debug('table.js');

const tableHelper = {
  downloadButtonInitialized: false,
  tableWidgetWidths: {},

  init: function () {
    const tables = document.querySelectorAll('.pfy-table-wrapper');
    if (tables.length) {
      tableHelper.setupEventHandlers();

      tables.forEach(function (table) {
        tableHelper.setupPropagateCheckbox(table); // problematic to handle by global trigger
        domForOne(table, '^.pfy-form-and-table-wrapper.pfy-table-edit-popup .pfy-form-has-errors', form => {
          // if form related to table has errors, open edit popup immediately:
          tableHelper.prepareForm(form);
        })
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
      if (el.closest('td .pfy-row-duplicate-button')) {
        tableHelper.duplicateTableButtonHandler(ev);
      }
      if (el.closest('td .pfy-row-edit-button')) {
        tableHelper.editButtonsHandler(ev);
      }
      if (el.closest('.pfy-form-and-table-wrapper, .pfy-table-fill-first-row')) {
        tableHelper.newRecButtonHandler(ev);
      }
      if (el.closest('tr') && !el.closest('.pfy-service-col')) {
        tableHelper.rowTriggerHandler(ev);
      }
      if (el.closest('.pfy-row-view-button')) {
        tableHelper.viewButtonsHandler(ev);
      }
    }); // click events

    document.addEventListener('change', function (ev) {
      const el = ev.target;
      if (el.closest('.pfy-table-buttons select')) {
        tableHelper.tableWidgetHandler(ev);
      }
    }); // change events

    document.addEventListener('keydown', function (ev) {
      tableHelper.rowKeyTriggerHandler(ev);
    }) // keydown events

    window.addEventListener('resize', function (ev) {
      tableHelper.adaptToWidthHandler();
    }) // resize events

  }, // setupEventHandlers


  prepareAdaptToWidthHandler() {
    let dataTables = document.querySelectorAll('.pfy-table-wrapper.pfy-interactive');
    if (dataTables.length > 0) {
      // there are dataTables, so wait until all are loaded:
      let goOn;
      let counter = 10;
      let t = setInterval(() => {
        goOn = true;
        dataTables.forEach((tableWrapperEl) => {
          if (!tableWrapperEl.querySelector('.dt-container')) {
            goOn = false;
          }
        });
        counter--;
        if (counter === 0) {
          clearInterval(t);
          return;
        }
        if (goOn) {
          clearInterval(t);
          dataTables.forEach((tableWrapperEl) => {
            tableHelper.prepareTableWidths(tableWrapperEl);
          });
        }
      }, 100);

    } else {
      domForEach('.pfy-table-wrapper', function (tableWrapperEl) {
        tableHelper.prepareTableWidths(tableWrapperEl);
      })
    }
  }, // prepareAdaptToWidthHandler


  prepareTableWidths(tableWrapperEl) {
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
    tableHelper.adaptToWidthHandler();
  }, // prepareTableWidths


  adaptToWidthHandler() {
    // for filter field, copy label text to placeholder, in case table is too narrow to show label:
    domForAll('.pfy-table-wrapper', tableWrapperEl => {
      const wrapperElWidth = tableWrapperEl.getBoundingClientRect().width;

      const id = tableWrapperEl.getAttribute('id');
      const widths = tableHelper.tableWidgetWidths[id] ?? false;
      if (!widths) {
        return;
      }
      const buttonsElWidth    = widths['buttons'];
      const filterElWidth     = widths['dtFilter'];
      const filterInputWidth  = widths['dtFilterInput'];
      if (wrapperElWidth < (filterElWidth + buttonsElWidth)) { // space for buttons and filter and filter-label?
        if (wrapperElWidth < (filterInputWidth + buttonsElWidth)) { // space for buttons and filter but no filter-label?
          tableWrapperEl.classList.add('pfy-table-very-narrow');
          tableWrapperEl.classList.remove('pfy-table-narrow');
        } else {
          tableWrapperEl.classList.remove('pfy-table-very-narrow');
          tableWrapperEl.classList.add('pfy-table-narrow');
        }
      } else { // space for buttons and filter and filter-label
        tableWrapperEl.classList.remove('pfy-table-narrow', 'pfy-table-very-narrow');
      }
    });
  }, // adaptToWidthHandler


  setupPropagateCheckbox: function (table) {
    const thead = table.querySelector('thead');
    if (thead) {
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
        const isChecked = checkboxEl.checked;
        domForEach(table, 'tbody .pfy-row-selector input[type=checkbox]', rowCheckbox => {
          rowCheckbox.checked = isChecked;
        });
      });
    }
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
          const url = appendToUrl(pageUrl, 'delete')
          form.setAttribute('action', url);
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


  rowKeyTriggerHandler: function (ev) {
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
    ev.preventDefault();
    if (el) {
      tableHelper.rowTriggerHandler(el);
    }
  }, // rowKeyTriggerhandler


  editButtonsHandler: function (ev) {
    const el = ev.target;
    if (!el.closest('.pfy-form-and-table-wrapper')) {
      alert(`Error: edit button in table not supported without related form.`);
    }

    if (!(el.closest('.pfy-row-edit-button') || el.closest('.pfy-table-fill-first-row'))) {
      return;
    }

    if (el.closest('.pfy-rec-locked')) {
      el.closest('.pfy-rec-locked').classList.remove('pfy-rec-locked');
    }

    ev.stopImmediatePropagation();
    tableHelper.prepareForm(el);
  }, // setupEditButtons


  viewButtonsHandler: function (ev) {
    const el = ev.target;
    const tableInx = el.closest('[data-tableinx]').dataset.tableinx;
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
      header: `{{ pfy-table-rec-preview-popup-header }}`,
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
        domForEach(popupTemplateSelector + ' td + td', tdEl => {
          const name = tdEl.innerText.replace(/%/g, '');
          tdEl.innerText = data[name];
        });
      },

    });

  }, // viewButtonsHandler


  // === Table Button Handlers:
  downloadButtonHandler(ev) {
    ev.stopPropagation();
    ev.preventDefault();
    const btnEl = ev.target.closest('.pfy-table-download-start');
    domForOne(btnEl.parentElement, 'a', aEl => {
      aEl.click();
    });
  }, // downloadButtonHandler


  tableWidgetHandler(ev) {
    ev.stopPropagation();
    ev.preventDefault();
    const selectEl = ev.target;
    const selectedOption = selectEl.value;
    const callback = selectEl.dataset.callback;
    if (executeCallbackCode(callback, selectedOption) === null) {
      console.log(`table widget callback function "${callback}" missing.`);
    }
  }, // tableWidgetHandler


  sendTableButtonHandler(ev) {
    const table = ev.target.closest('.pfy-table');
    const headers = [];
    let i = 0;
    domForAll(table, 'th:not(.pfy-service-col)', th => {
      let str = th.innerText + ':';
      str = str.padEnd(6, ' ') + '\t\t';
      headers[i++] = str;
    });
    const tr = ev.target.closest('tr');
    let body = '\n\n';
    i = 0;
    domForAll(tr, 'td:not(.pfy-service-col) div', el => {
      body += `${headers[i++]} ${el.innerText}\n`;
    })
    initiateMail({body: body});
  }, // sendTableButtonHandler


  duplicateTableButtonHandler(ev) {
    const el = ev.target;
    if (!el.closest('.pfy-form-and-table-wrapper')) {
      alert(`Error: edit button in table not supported without related form.`);
    }

    if (!(el.closest('.pfy-row-duplicate-button'))) {
      return;
    }

    if (el.closest('.pfy-rec-locked')) {
      el.closest('.pfy-rec-locked').classList.remove('pfy-rec-locked');
    }

    ev.stopImmediatePropagation();
    tableHelper.prepareForm(el, 'duplicate');
  }, // duplicateTableButtonHandler


  doSendRec: function (args, recKey) {
    const input = document.getElementById('pfy-table-send-rec-input');
    if (input) {
      const email = encodeURI(input.value);
      window.location.href = window.location.href + '?sendto=' + email + '&recid=' + recKey;
    }
  }, // doSendRec


  newRecButtonHandler: function (ev) {
    const newRecBtn = ev.target.closest('.pfy-table-new-rec, .pfy-table-fill-first-row');
    if (!newRecBtn) {
      return;
    }
    ev.stopPropagation();
    tableHelper.prepareForm(newRecBtn);
  }, // newRecButtonHandler


  rowTriggerHandler: async function(ev) {
    let el = ev;
    if (!(ev instanceof Element)) {
      el = ev.target;
    }
    const tableWrapperEl = el.closest('.pfy-table-wrapper');
    if (!tableWrapperEl || !el.closest('tbody')) {
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
        () => {}
      );
    } else {
      this.defaultRowClickHandler(el, tableFormWrapper);
    }
  }, // rowTriggerHandler


  defaultRowClickHandler: function(el, tableFormWrapper) {
    if (!tableFormWrapper) {
      tableFormWrapper = el.closest('.pfy-form-and-table-wrapper');
    }
    const tableEl = el.closest('.pfy-table');
    const tableForm = tableFormWrapper.querySelector('.pfy-form');
    const tr = el.closest('tr');
    if (tr && tr.classList.contains('pfy-row-selected')) {
      // reset form:
      domForOne(el, '^.pfy-form-and-table-wrapper .pfy-retain-data', el => {
        const dataSrcInx = el.closest('.pfy-form-and-table-wrapper').dataset.srcInx;
        reloadAgent(`clearform=${dataSrcInx}`);
      })
      pfyFormsHelper.presetForm(tableForm);
      tableHelper.markRowAsSelected(tr, false);

    } else {
      // preset form:
      domForEach(tableEl, 'tr', tr => {
        tableHelper.markRowAsSelected(tr, false);
      });
      if (tr) {
        tableHelper.markRowAsSelected(tr, true);
        this.prepareForm(el);
      }
    }
  }, // defaultRowClickHandler


  markRowAsSelected: function(tr, value) {
    if (tr.closest('.pfy-table-edit-popup')) {
      return;
    }
    if (value) {
      tr.classList.add('pfy-row-selected');
    } else {
      tr.classList.remove('pfy-row-selected');
    }
  }, // markRowAsSelected


  fillForm: function (el, table, tableForm, tableInx, editBtn) {
    const outerWrapperEl = el.closest('.pfy-form-and-table-wrapper');
    // upon clicking one of the edit buttons:
    tableHelper.disableEditButtons(table);
    const tr = el.closest('tr');
    const recKey = tr.dataset.reckey ?? '';

    // get latest data for this record:
    const formInx = outerWrapperEl.querySelector('[name=_form_]').value
    domForOne('.pfy-form-' + formInx, form => {
      pfyFormsHelper.fetchDataAndFillForm(form, recKey, true);
    });
  }, // fillForm


  prepareForm: function (el, duplicateMode = false) {
    const outerWrapperEl = el.closest('.pfy-form-and-table-wrapper');
    const tableWrapperEl = el.closest('.pfy-table-wrapper');
    const editbyPopupMode = !!el.closest('.pfy-table-edit-popup');
    const formWrapper = outerWrapperEl.querySelector('.pfy-form-wrapper');
    const recKeyEl = el.closest('[data-reckey]');
    const recKey = recKeyEl ? recKeyEl.dataset.reckey : '';
    const dataSrcInx = outerWrapperEl.querySelector('[data-src-inx]').dataset.srcInx;
    const headerStr = duplicateMode ? `{{ pfy-table-duplicate-rec-popup-header }}` : `{{ pfy-table-edit-rec-popup-header }}`;
    if (editbyPopupMode) {
      const options = {
        header: headerStr,
        contentFrom: formWrapper,
        closeOnBgClick: false,
        onClose: function () {
          pfyFormsHelper.unlockRecs(dataSrcInx);
        },
        onOpen: function () {
          domForOne('.pfy-popup-wrapper .pfy-form', formEl => {
            if (recKey) {
              pfyFormsHelper.fetchDataAndFillForm(formEl, recKey, true, duplicateMode);
            } else {
              pfyFormsHelper.presetForm(formEl);
            }
            formEl.removeAttribute('id');

            domForAll(formEl, 'input.pfy-cancel', input => {
              input.addEventListener('click', () => {
                pfyFormsHelper.unlockRecs(dataSrcInx);
              });
            });
          });
        }
      };
      pfyPopupPromise(options)
        .then(function (data) {
          tableHelper.enableEditButtons(tableWrapperEl);
        })
    } else {
      if (recKey) {
        pfyFormsHelper.fetchDataAndFillForm(tableWrapperEl, recKey, true, duplicateMode);
      }
      tableHelper.enableEditButtons(tableWrapperEl);
    }
  }, // prepareForm


  disableEditButtons: function (wrapper) {
    const scope = wrapper || document;
    scope.querySelectorAll('.pfy-table-wrapper button').forEach(function (btn) {
      btn.disabled = true;
    });
  }, // disableEditButtons


  enableEditButtons: function (wrapper) {
    const scope = wrapper || document;
    scope.querySelectorAll('.pfy-table-wrapper button').forEach(function (btn) {
      btn.disabled = false;
    });
  }, // enableEditButtons


  filter: function (table, filterStr) {
    let tableInx = 1;
    if (typeof table === 'object') {
      tableInx = table.closest('[data-tableinx]').dataset.tableinx;
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



  // tableHelper.hasSelectedRows(ev)
  hasSelectedRows: function(ev) {
    ev.stopPropagation();
    const wrapper = ev.target.closest('.pfy-table-wrapper');
    const form = wrapper.querySelector('form');
    const table = wrapper.querySelector('table');
    const selected = table.querySelectorAll('tbody .pfy-row-selector input[type=checkbox]:checked');
    if (selected.length) {
      return true;
    }
    const options = {
      text: `{{ pfy-table-nothing-selected }}`,
      header: `{{ pfy-table-nothing-selected-header }}`,
      closeOnBgClick: true,
      buttons: 'Ok'
    };
    pfyPopup(options);
    return false;
  }, // hasSelectedRows

}; // tableHelper


tableHelper.init();
