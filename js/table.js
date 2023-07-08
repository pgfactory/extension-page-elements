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
        const tableInx = table.dataset.tableinx;
        tableHelper.setupPropagateCheckbox(table);
        tableHelper.setupOpenDeleteRecordsDialog(table);
        tableHelper.setupDownloadButton(table, tableInx);
        tableHelper.setupEditButtons(table, tableInx);
        tableHelper.setupNewRecButton(table, tableInx);
        tableHelper.setupUnloadEvent(table, tableInx);
      });
    }
  }, // init


  setupUnloadEvent: function(table, tableInx) {
    window.addEventListener('beforeunload', (event) => {
      mylog('unlocking locked records');
      tableHelper.unlockRecs(tableInx);
    });
  }, // setupUnloadEvent


  setupPropagateCheckbox: function (table) {
    const hdrCheckbox = table.querySelector('thead .pfy-row-selector input[type=checkbox]');
    if (hdrCheckbox) {
      hdrCheckbox.addEventListener('change', function () {
        var isChecked = this.checked;
        const checkboxes = table.querySelectorAll('tbody .pfy-row-selector input[type=checkbox]');
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
              form.setAttribute('action', pageUrl + '?delete');
              form.submit();
            }
          };
        }
        currentlyOpenPopup = pfyPopup(options);
      });

    } else {
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
          currentlyOpenPopup = pfyPopup(options);
        });

      }
    }
  }, // setupOpenDeleteRecordsDialog


  setupDownloadButton: function (table, tableInx) {
    const form = table.closest('.pfy-table-wrapper').querySelector('form');
    if (!form) {
      return;
    }
    const downloadBtn = form.querySelector('.pfy-table-download-start');
    if (downloadBtn) {
      downloadBtn.addEventListener('click', function () {
        const id = table.getAttribute('id');
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


  setupEditButtons: function (table, tableInx) {
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
        editBtn.addEventListener('click', function () {
          // upon clicking one of the edit buttons:
          tableHelper.disableEditButtons(table);
          const tr = this.closest('tr');
          const recKey = tr.dataset.reckey ?? '';

          // get latest data for this record:
          const args = 'getRec='+recKey+'&datasrcinx='+tableInx+'&lock';
          mylog('fetching data record '+recKey);
          execAjaxPromise(args, {})
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
              mylog(data);
              tableHelper.prepareEditForm(table, tableForm, recKey, data, editBtn, tableInx);
              const input1 = tableForm.querySelector('input');
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


  setupNewRecButton: function (table, tableInx) {
    const formWrapper = table.closest('.pfy-form-wrapper');
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
              const form = document.querySelector('#pfy-popup-form .pfy-form');
              if (form) {
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
        } else {
          const input1 = parentForm.querySelector('.pfy-input-wrapper input');
          if (input1) {
            input1.focus();
          }
        }
        newRecBtn.setAttribute('aria-expanded', 'true');
      });
    }
  }, // setupNewRecButton


  setupCancelButton: function(table, tableInx) {
    const cancelInputs = document.querySelectorAll('#pfy-popup-form input.pfy-cancel');
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


  prepareEditForm: function (table, parentForm, recKey, data, editBtn, tableInx) {
    const editbyPopupMode = table.classList.contains('pfy-table-edit-popup');
    if (editbyPopupMode) {
      const options = {
        id: 'pfy-popup-form',
        header: `{{ pfy-table-edit-rec-popup-header }}`,
        contentFrom: parentForm,
        closeOnBgClick: false,
        onClose: function () {
          tableHelper.unlockRecs(tableInx);
        },
        onOpen: function () {
          mylog('prepareEditForm - onOpen');
          mylog(data);
          const form = document.querySelector('#pfy-popup-form .pfy-form');
          if (form) {
            pfyFormsHelper.init(form);
            pfyFormsHelper.setupCancelButtonHandler(form);
            pfyFormsHelper.presetForm(form, data, recKey);
            tableHelper.setupCancelButton(table, tableInx);
          }
        }
      };
      tableHelper.popupForm(options, editbyPopupMode, parentForm)
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
    mylog('unlocking locked records');
    if (typeof tableInx === 'undefined') {
      const tables = document.querySelectorAll('.pfy-table');
      if (tables) {
        tables.forEach(function (table) {
          const tableInx = table.dataset.tableinx;
          const args = 'unlockAll' + '&datasrcinx=' + tableInx;
          execAjaxPromise(args, {})
            .then(function (data) {
              mylog(data);
            })
            .then(function (msg) {});
        });
      }
    } else {
      const args = 'unlockAll' + '&datasrcinx=' + tableInx;
      execAjaxPromise(args, {})
        .then(function (data) {
          mylog(data);
        })
        .then(function (msg) {});
    }
  }, // unlockRecs

}; // tableHelper


tableHelper.init();
