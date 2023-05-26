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
      });
    }
  }, // init


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
    const form = table.closest('.pfy-table-wrapper').querySelector('form');
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
  } // setupDownloadButton
};

tableHelper.init();
