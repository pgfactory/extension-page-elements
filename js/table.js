/*
 *  Table helper
 *    -> support for table buttons: delete records, download table
 */

"use strict";

const tableHelper = {
  init: function () {
    const tables = document.querySelectorAll('.pfy-table');
    if ((typeof tables !== 'undefined') && tables.length) {
      tables.forEach(function (table) {
        tableHelper.setupPropagateCheckbox(table);
        tableHelper.setupOpenDeleteRecordsDialog(table);
        tableHelper.setupDownloadButton(table);
        tableHelper.setupEditButtons(table);
        tableHelper.setupNewRecButton(table);
        tableHelper.setupUnloadEvent(table);
      });
    }
  }, // init


  setupUnloadEvent: function(table) {
    window.addEventListener('beforeunload', (event) => {
      mylog('unlocking locked records');
      tableHelper.unlockRecs();
    });
  }, // setupUnloadEvent


  setupPropagateCheckbox: function (table) {
    const hdrCheckbox = table.querySelector('thead .td-row-selector input[type=checkbox]');
    if (hdrCheckbox) {
      hdrCheckbox.addEventListener('change', function () {
        var isChecked = this.checked;
        const checkboxes = table.querySelectorAll('tbody .td-row-selector input[type=checkbox]');
        if (checkboxes.length) {
          checkboxes.forEach(function(rowCheckbox) {
            rowCheckbox.checked = isChecked;
          });
        }
      });
    }
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
        var selected = table.querySelectorAll('tbody .td-row-selector input[type=checkbox]:checked');
        if (!selected.length) {
          currentlyOpenPopup = pfyPopup({
            text: `{{ pfy-table-delete-nothing-selected }}`,
            header: `{{ pfy-table-delete-recs-header }}`,
            closeOnBgClick: true,
            buttons: 'Ok'
          });
        } else {
          currentlyOpenPopup = pfyPopup({
            text: `{{ pfy-data-delete-records }}`,
            header: `{{ pfy-table-delete-recs-header }}`,
            closeOnBgClick: true,
            buttons: 'Cancel, Confirm',
            wrapperClass: 'pfy-data-delete-records',
            callbackArg: form,
            onConfirm: function (that, form) {
              form.setAttribute('action', pageUrl + '?delete');
              form.submit();
            }
          });
        }
      });
    }
  }, // setupOpenDeleteRecordsDialog


  setupDownloadButton: function (table) {
    const form = table.closest('.pfy-table-wrapper').querySelector('form');
    if (!form) {
      return;
    }
    const downloadBtn = form.querySelector('.pfy-table-download-start');
    if (downloadBtn) {
      downloadBtn.addEventListener('click', function () {
        const id = table.getAttribute('id');
        const tableInx = id.replace(/\D*/, '');
        mylog('tableInx: ' + tableInx);
        currentlyOpenPopup = pfyPopup({
          text: pfyDownloadDialog[tableInx],
          header: `{{ pfy-popup-download-header }}`,
          closeButton: true,
          closeOnBgClick: true,
          buttons: 'Close'
        });
      });
    }
  }, // setupDownloadButton


  setupEditButtons: function (table) {
    const form = table.closest('.pfy-table-wrapper').querySelector('form');
    if (!form) {
      return;
    }
    const parentForm = table.closest('.pfy-form-wrapper').querySelector('.pfy-form');
    const editbyPopupMode = table.classList.contains('pfy-table-edit-popup');
    const editBtns = form.querySelectorAll('td .pfy-table-edit-button');
    if (editBtns) {
      editBtns.forEach(function (editBtn) {
        editBtn.addEventListener('click', function () {
          // upon clicking one of the edit buttons:
          tableHelper.disableEditButtons(table);
          const tr = this.closest('tr');
          const recKey = tr.dataset.reckey ?? '';

          // get latest data for this record:
          execAjaxPromise('getRec='+recKey+'&lock&ajax', {})
            .then(function (data) {
              if (data.status === 'error') {
                // handle case where rec locked by somebody else:
                mylog('Rec locked.');
                const row = table.querySelector('[data-reckey='+recKey+']');
                tableHelper.enableEditButtons(table);
                row.classList.add('pfy-rec-locked');
                return;
              }
              // popup is open, now prepare the form, inject obtained data:
              tableHelper.prepareEditForm(table, parentForm, editbyPopupMode, recKey, data, editBtn);
              const input1 = parentForm.querySelector('input');
              if (input1) {
                input1.focus();
              }
            })
            .then(function (msg) {
            });
          editBtn.setAttribute('aria-expanded', 'true');
        });
      });
    }
  }, // setupEditButtons


  setupNewRecButton: function (table) {
    const form = table.closest('.pfy-table-wrapper').querySelector('form');
    if (!form) {
      return;
    }
    const parentForm = table.closest('.pfy-form-wrapper').querySelector('.pfy-form');
    const editBtn = form.querySelector('.pfy-table-new-rec');
    if (editBtn) {
      editBtn.addEventListener('click', function () {
        const options = {
          header: `{{ pfy-table-new-rec-popup-header }}`,
          contentFrom: parentForm,
          closeOnBgClick: false,
          id: 'pfy-popup-form',
          onOpen: function () {
            mylog('prepareEditForm - onOpen new');
            const form = document.querySelector('#pfy-popup-form .pfy-form');
            if (form) {
              const cancelInputs = form.querySelectorAll('input.pfy-cancel');
              if (cancelInputs.length) {
                cancelInputs.forEach(function(input) {
                  input.addEventListener('click', function(e) {
                    pfyPopupClose();
                    tableHelper.unlockRecs();
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
        pfyPopup(options);
        editBtn.setAttribute('aria-expanded', 'true');
      });
    }
  }, // setupEditButtons


  setupCancelButton: function(table) {
    const cancelInputs = document.querySelectorAll('#pfy-popup-form input.pfy-cancel');
    if (cancelInputs.length) {
      cancelInputs.forEach(function(input) {
        input.addEventListener('click', function(e) {
          pfyPopupClose();
          tableHelper.unlockRecs();
          const newRecOpenButton = table.querySelector('.pfy-table-new-rec');
          if (newRecOpenButton) {
            newRecOpenButton.setAttribute('aria-expanded', 'false');
          }
        });
      });
    }
  }, // setupCancelButton


  prepareEditForm: function (table, parentForm, editbyPopupMode, recKey, data, editBtn) {
    if (editbyPopupMode) {
      const options = {
        id: 'pfy-popup-form',
        header: `{{ pfy-table-edit-rec-popup-header }}`,
        contentFrom: parentForm,
        closeOnBgClick: false,
        onClose: function () {
          tableHelper.unlockRecs();
        },
        onOpen: function () {
          mylog('prepareEditForm - onOpen');
          mylog(data);
          const form = document.querySelector('#pfy-popup-form .pfy-form');
          if (form) {
            pfyFormsHelper.init(form);
            pfyFormsHelper.setupCancelButtonHandler(form);
            pfyFormsHelper.presetForm(form, data, recKey);
            tableHelper.setupCancelButton(table);
          }
          tableHelper.enableEditButtons(table);
        }
      };
      pfyPopupPromise(options)
        .then(function (data) {
          tableHelper.enableEditButtons(table);
          editBtn.setAttribute('aria-expanded', 'false');
        })
        .then(function () {});
    } else {
      pfyFormsHelper.presetForm(parentForm, data, recKey);
      tableHelper.enableEditButtons(table);
    }

  }, // prepareEditForm


  disableEditButtons: function (table) {
    const editButtons = table.querySelectorAll('.pfy-table-edit-button');
    if (editButtons) {
      editButtons.forEach(function (editButton) {
        editButton.disabled = true;
      });
    }
  }, // disableEditButtons


  enableEditButtons: function (table) {
    const editButtons = table.querySelectorAll('.pfy-table-edit-button');
    if (editButtons) {
      editButtons.forEach(function (editButton) {
        editButton.disabled = false;
      });
    }
  }, // enableEditButtons


  unlockRecs: function () {
    mylog('unlocking locked records');
    execAjaxPromise('unlockAll', {})
      .then(function (data) {
        mylog(data);
      })
      .then(function (msg) {
      });

  }, // unlockRecs

}; // tableHelper


tableHelper.init();
