

// propagate click on selection checkbox in header row to all rows:
$('.pfy-table-header .pfy-col-1 input[type=checkbox]').click(function () {
  let $table = $(this).closest('.pfy-table-wrapper');
  let val = $(this).prop('checked');
  $('.pfy-table .pfy-col-1 input[type=checkbox]', $table).prop('checked', val);
});

$('.pfy-table-delete-recs-open-dialog').click(function (e) {
  e.stopPropagation();
  let $form = $('form', $(this).closest('.pfy-table-wrapper'));
  let selected = $('.td-row-selector input[type=checkbox]:checked', $form);
  let dialog = new PfyPopup;

  if (!selected.length) {
    dialog.open(pfyNothingSelected, null, {payload: $form, labelCancel: 'Ok'});
  }

  dialog.open(pfyDeleteRecDialog, function($dialog, $form) {
    $form.attr('action', pageUrl + '?delete');
    $form.submit();
  }, {payload: $form});
});

// $('.pfy-table-delete-recs-open-dialog').click(function (e) {
//   e.stopPropagation();
//   let $form = $(this).closest('form');
//   let tableId = $('.pfy-table', $form).attr('id');
//   $('.pfy-page').append('<dialog id="pfy-rec-delete-dialog" class="pfy-dialog" data-id="'+tableId+'">' + pfyDeleteRecDialog + '</dialog>');
//   let $dialog = $('#pfy-rec-delete-dialog');
//   // $dialog[0].show();
//   // let dialog = document.getElementById("pfy-rec-delete-dialog");
//   // dialog.show();
//   $dialog[0].showModal();
//   $('.pfy-page').addClass('pfy-dimmed');
//
//   $('button[value=submit]', $dialog).click(function () {
//     let tableId = $(this).closest('dialog').data('id');
//     let $form = $('#'+tableId).closest('form');
//     // let $form = $('.pfy-table-wrapper > form');
//     $form.attr('action',pageUrl + '?delete');
//     $form.submit();
//     // $form.attr('action','?delete').submit();
//     $dialog.remove();
//     $('.pfy-page').removeClass('pfy-dimmed');
//   });
//
//   $('button[value=cancel]', $dialog).click(function () {
//     // $dialog[0].close();
//     $dialog.remove();
//     $('.pfy-page').removeClass('pfy-dimmed');
//   });
//   $('body').click(function () {
//     $dialog.remove();
//     $('.pfy-page').removeClass('pfy-dimmed');
//   });
//
//   // if (window.confirm(pfyTableDeletePopup)) {
//   //   $(this).closest('form').attr('action','?delete');
//   //   $(this).closest('form').submit();
//   // }
// });


//   $('button[value=submit]', $dialog).click(function () {
//     let tableId = $(this).closest('dialog').data('id');
//     let $form = $('#'+tableId).closest('form');
//     // let $form = $('.pfy-table-wrapper > form');
//     $form.attr('action',pageUrl + '?delete');
//     $form.submit();
//     // $form.attr('action','?delete').submit();
//     $dialog.remove();
//     $('.pfy-page').removeClass('pfy-dimmed');
//   });
//
//   $('button[value=cancel]', $dialog).click(function () {
//     // $dialog[0].close();
//     $dialog.remove();
//     $('.pfy-page').removeClass('pfy-dimmed');
//   });
//   $('body').click(function () {
//     $dialog.remove();
//     $('.pfy-page').removeClass('pfy-dimmed');
//   });
//
//   // if (window.confirm(pfyTableDeletePopup)) {
//   //   $(this).closest('form').attr('action','?delete');
//   //   $(this).closest('form').submit();
//   // }
// });

// $('#pfy-table-delete-submit').click(function () {
//   $('body').append('<dialog id="pfy-rec-delete-dialog" class="pfy-dialog">' + pfyDeleteRecDialog + '</dialog>');
//   let $dialog = $('#pfy-rec-delete-dialog');
//   $dialog[0].showModal();
//   $('button[value=submit]', $dialog).click(function () {
//     $dialog[0].close();
//   });
//   $('button[value=cancel]', $dialog).click(function () {
//     $dialog[0].close();
//   });
//   // if (!$('#pfy-temp-iframe').length) {
//   //   $('body').append('<iframe id="pfy-temp-iframe" style="display:none;"></iframe>');
//   // }
//   // $('#pfy-temp-iframe').attr('src', fileUrl);
// });

$('.pfy-table-download-start').click(function () {
  let fileUrl = hostUrl + $(this).data('file');
  $('body').append('<dialog id="pfy-table-download-dialog" class="pfy-dialog">' + pfyDownloadDialog + '</dialog>');
  let $dialog = $('#pfy-table-download-dialog');
  $dialog[0].showModal();
  $('button[value=cancel]', $dialog).click(function () {
    $dialog[0].close();
  });
});


// if (!$('#pfy-temp-iframe').length) {
//   $('body').append('<iframe id="pfy-temp-iframe" style="display:none;"></iframe>');
// }
// $('#pfy-temp-iframe').attr('src', fileUrl);
