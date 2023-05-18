/* table.js */


// propagate click on selection checkbox in header row to all rows:
$('.pfy-table-header .td-row-selector input[type=checkbox]').click(function () {
  let $table = $(this).closest('.pfy-table-wrapper');
  let val = $(this).prop('checked');
  $('.pfy-table .td-row-selector input[type=checkbox]', $table).prop('checked', val);
});



$('.pfy-table-delete-recs-open-dialog').click(function (e) {
  e.stopPropagation();
  let $form = $('form', $(this).closest('.pfy-table-wrapper'));
  let selected = $('.td-row-selector input[type=checkbox]:checked', $form);
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
        buttons: `Cancel, Confirm`,
        // buttons: `Cancel, {{ pfy-edit-rec-delete-btn }}`,
        wrapperClass: 'pfy-data-delete-records',
        callbackArg: $form,
        onConfirm:  function (that, $form) {
          // pfyReload('?delete=');
          $form.attr('action', pageUrl + '?delete');
          $form.submit();
        }

      // callbacks: [
        //   null,
        //   function (that, $form) {
        //     $form.attr('action', pageUrl + '?delete');
        //     $form.submit();
        //   }
        // ],
      });
  }
});



$('.pfy-popup-wrapper.pfy-data-delete-records .pfy-popup-btn-2').click(function () {
  let $form = $('form');
  $form.attr('action', pageUrl + '?delete');
  $form.submit();
});



$('.pfy-table-download-start').click(function () {
  let $form = $('form', $(this).closest('.pfy-table-wrapper'));
  let $table = $('.pfy-table', $form);
  let id = $table.attr('id');
  let tableInx = $table.attr('id').replace(/\D*/, '');
  mylog('tableInx: ' + tableInx);
  currentlyOpenPopup = pfyPopup({
    text: pfyDownloadDialog[tableInx],
    header: `{{ pfy-popup-download-header }}`,
    closeButton: true,
    closeOnBgClick: true,
    buttons: 'Close',
  });
});

