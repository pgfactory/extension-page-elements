// htmltable.js

"use strict";

var pfyActiveTables = [];
$( document ).ready(function() {
	$('.pfy-active-table').each(function () {
		var tableInx = $(this).data('inx');
		pfyActiveTables[tableInx] = new HTMLtable(this);
	});

	$('.pfy-active-table').on('click', '.pfy-table-view-btn', function () {
		var tableInx = $(this).closest('.pfy-active-table').data('inx');
		pfyActiveTables[tableInx].openViewRecPopup(this);
	});

	$('.pfy-active-table').on('click', '.pfy-table-edit-btn', function () {
		var tableInx = $(this).closest('.pfy-active-table').data('inx');
		pfyActiveTables[tableInx].openForm(this);
		// pfyActiveTables[tableInx].openFormPopup(this);
	});
});


function HTMLtable( tableObj ) {
	this.$table = tableObj;
	if (typeof this.$table[0] === 'undefined') {
		this.$table = $( tableObj );
	}
	this.$tableWrapper = this.$table.closest('.pfy-table-wrapper');
	this.tableInx = this.$table.data('inx');
	this.dataRef = this.$table.closest('[data-datasrc-ref]').data('datasrc-ref');
	this.formHtml = null;
	this.pfyTableNewRec = false;
	this.formInx = false;
	this.$form = null;
	this.recKey = false;
	this.$recviewPopup = null;
	this.recViewPopupId = null;
	this.recEditPopupId = null;
	this.$recEditForm = null;
	var tmpHash = Math.random().toString().substr(2,10);
	this.recViewHash = 'V' + tmpHash;
	this.recEditHash = 'E' + tmpHash;
	this.waitSymbol = '⌛';




	this.init = function () {
		var formId = this.$table.attr('data-form-id');
		if (typeof formId === 'undefined') {
			return;
		}
		this.formInx = formId.replace(/\D/g, '');
		this.$form = $( formId ).closest('.pfy-edit-rec-form-wrapper');
		this.recViewPopupId = 'pfy-recview-popup-' + this.formInx;
		this.recEditPopupId = 'pfy-recedit-popup-' + this.formInx;

		if (this.$form.length) {
			this.permanentForm = this.$tableWrapper.hasClass('pfy-table-permanent-form');
			if (this.permanentForm) {
				$('.pfy-edit-rec-form-wrapper', this.$tableWrapper).show();
				$('.pfy-edit-rec-delete-checkbox', this.$tableWrapper).hide();
			} else {
				this.formHtml = this.$form.html();
				this.$form.remove();
				this.initViewRecPopup();
				this.initEditFormPopup();
			}
			this.setupEventHandlers();
		}
		this.initDataTables();
	}; // init



	this.initDataTables = function () {
		// Check for each table, whether DataTable is active and TableButtons are present.
		// If so, move them into DataTable's own toolbar for aesthetic reasons.
		$('.pfy-table').each(function () {
			const $table = $( this ).closest('.pfy-table');
			$table.on('init.dt', function () {
				const $tableWrapper = $table.closest('.pfy-table-wrapper');
				const $tableButtons = $('.pfy-table-action-btns', $tableWrapper);
				if ($tableButtons.length) {
					const html = $tableButtons.html();
					$tableButtons.remove();
					$('.fg-toolbar:first-child', $tableWrapper).prepend( html );
				}
			});
		});
	}; // initDataTables



	this.initViewRecPopup = function () {
		if (!this.$table.hasClass('pfy-rec-preview')) {
			return;
		}

		// modify all IDs to avoid id-clashes:
		var formHtml = this.formHtml;

		// remove all ids from form (which serves no longer as a form, but rather as container):
		formHtml = formHtml.replace(/(id=['"].*?['"])/g, '');
		const cls = 'pfy-popup-bg pfy-popup-' + this.formInx + ' pfy-close-on-bg-click pfy-popup-with-header pfy-recview-popup';

		// inject popup code at end of body:
		const popupHtml = '<div id="' + this.recViewPopupId + '" class="' + cls + '" style="display: none;">\n' +
			'<div class="pfy-popup-wrapper pfy-popup-wrapper-ref" data-popup-inx="' + this.recViewHash + '">\n' +
			'<div class="pfy-popup-header pfy-draggable">' +
				'<div></div>' +
				'<button class="pfy-popup-close-button">×</button>' +
			'</div>' +
			'<div class="pfy-popup-container pfy-scroll-hints">\n' +
			'<div class="pfy-recview-container">\n' +
				formHtml +
			'</div></div></div></div>\n';
		$( 'body' ).append( popupHtml );


		// change form fields into read-only fields:
		this.$recviewPopup = $('#' + this.recViewPopupId);
		this.makeReadonly( this.$recviewPopup );
	}; // initViewRecPopup



	this.initEditFormPopup = function () {
		const cls = 'pfy-popup-bg pfy-popup-' + this.formInx + ' pfy-popup-with-header';
		const formHtml = this.formHtml;
		const popupHtml = '<div id="' + this.recEditPopupId + '" class="' + cls + '" style="display: none;">\n' +
			'<div class="pfy-popup-wrapper pfy-popup-wrapper-ref" data-popup-inx="' + this.recEditHash + '">\n' +
			'<div class="pfy-popup-header pfy-draggable">' +
			'<div></div>' +
			'</div>' +
			'<div class="pfy-popup-container pfy-scroll-hints">\n' +
			'<div id="pfy-edit-rec-form-container">\n' +
			formHtml +
			'</div></div></div></div>\n';
		$( 'body' ).append( popupHtml );
		this.$recEditForm = $( '#' + this.recEditPopupId + ' form');
	}; // initEditFormPopup



	this.openViewRecPopup = function ( $triggerSrc ) {
		if (typeof $triggerSrc[0] === 'undefined') {
			$triggerSrc = $( $triggerSrc );
		}
		pfyPopup({
			id: 'pfy-recview-popup-' + this.formInx,
			contentRef: this.$recviewPopup,
			header: true,
			draggable: true,
		}, this.recViewHash);

		// copy table cell values into preview popup form:
		const $row = $triggerSrc.closest('tr');
		$('td', $row).each(function () {
			const $td = $(this);
			var val = $td.text();
			val = val? val: ' ';
			const dataRef = $td.attr('data-ref');
			if (typeof dataRef === 'undefined') {
				return;
			}
			const elemInx = parseInt( dataRef.replace(/^.*,/, '') ) + 1;
			const $formEl = $('[data-elem-inx=' + elemInx + ']', this.$recViewPopup);
			if ( $formEl.length ) {
				$('.pfy-form-field-placeholder', $formEl ).text( val );
			}
		});

		var header = $('#pfy-recview-header').text();
		if (!header) {
			header = '&nbsp;';
		}
		$('.pfy-popup-header > div').html( header );

	}; // openViewRecPopup



	this.openForm = function ( $triggerSrc ) {
		// triggered by click on edit-row button:
		if (this.permanentForm) {
			this.populateForm( $triggerSrc );
		} else {
			this.openFormPopup( $triggerSrc );
		}
	}; // openForm



	this.populateForm = function ( $triggerSrc ) {
		const parent = this;
		var $table = null;
		var fldPreset = this.waitSymbol;
		parent.recKey = 'new-rec';
		if (typeof $triggerSrc[0] === 'undefined') {
			$triggerSrc = $( $triggerSrc );
			$table = $triggerSrc.closest('[data-form-id]');
			this.recKey = $triggerSrc.closest('[data-reckey]').data('reckey');
		} else {
			$table = $triggerSrc;
			fldPreset = '';
		}

		const $form = this.$form;
		if (!$form.length) {
			return; // error case, should not occure
		}
		$('.pfy-edit-rec-delete-checkbox', $form).show();
		$form.data('reckey', this.recKey);
		$('input[name=_rec-key]',$form).val( this.recKey );

		if (this.recKey === 'new-rec') {
			$form.addClass('pfy-new-data');
			$('.pfy-edit-rec-delete-checkbox').hide();

		} else  {
			$form.removeClass('pfy-new-data');
			$('.pfy-form-wrapper [name=_rec-key]').val( this.recKey );
			$('.pfy-edit-rec-delete-checkbox').show();
		}

		$('[type=submit]', $form).val( $('#pfy-edit-form-submit').text() );
		$('#pfy-edit-rec-delete-checkbox input[type=checkbox]').prop('checked', false);

		pfyForms.onOpen( this.recKey, $form, true );
	}; // populateForm



	this.openFormPopup = function ( $triggerSrc ) {
		// triggered by click on edit-row button:
		const parent = this;
		var $table = null;
		var fldPreset = this.waitSymbol;
		parent.recKey = 'new-rec';
		if (typeof $triggerSrc[0] === 'undefined') {
			$triggerSrc = $( $triggerSrc );
			$table = $triggerSrc.closest('[data-form-id]');
			this.recKey = $triggerSrc.closest('[data-reckey]').data('reckey');
		} else {
			$table = $triggerSrc;
			fldPreset = '';
		}

		var formTitle = '';
		const popupId = '#pfy-recedit-popup-' + this.formInx;
		const $popup = $( popupId );

		if (this.recKey === 'new-rec') {
			formTitle = $('#pfy-edit-form-new-rec').html();
			fldPreset = '';
			this.pfyTableNewRec = true;

		} else { // existing rec:
			formTitle = $('#pfy-edit-form-rec').html();
			this.pfyTableNewRec = false;
		}

		pfyPopup({
			contentRef: $popup,
			closeButton: false,
			closeOnBgClick: false,
			header: formTitle,
			closeCallback: 'htmltableOnPopupClose',
		});

		const $form = this.$recEditForm;
		if (!$form.length) {
			return; // error case, should not occure
		}
		$form.data('reckey', this.recKey);
		$('input[name=_rec-key]',$form).val( this.recKey );

		if (this.recKey === 'new-rec') {
			$form.addClass('pfy-new-data');
			$('.pfy-edit-rec-delete-checkbox').hide();

		} else  {
			$form.removeClass('pfy-new-data');
			$('.pfy-form-wrapper [name=_rec-key]').val( this.recKey );
			$('.pfy-edit-rec-delete-checkbox').show();
		}

		$('[type=submit]', $form).val( $('#pfy-edit-form-submit').text() );
		$('#pfy-edit-rec-delete-checkbox input[type=checkbox]').prop('checked', false);

		pfyForms.onOpen( this.recKey, $form, true );
	}; // openFormPopup



	this.prefillFields = function() {
		$('[data-default-value]').each(function () {
			let $this = $( this );
			let defaultValue = $this.data('default-value');
			$this.val( defaultValue );
		});
		if (typeof pfyTableFormPrefill !== 'undefined') {
			for (let elem in pfyTableFormPrefill) {
				let value = pfyTableFormPrefill[elem];
				mylog(elem + ' => ' + value, false);
				let $field = $('[name=' + elem + ']');
				$field.val(value);
			}
		}
	}; // prefillFields



	this.setupEventHandlers = function () {
		const parent = this;
		$('body')
			// cancel button in form:
			.on('click', '.pfy-edit-data-form input[type=reset]', function(e) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();

				const $form = $(this).closest('.pfy-form');

				// only in case of existing rec we need to unlock the record:
				if (!$(this).closest('.pfy-new-data').length) {
					parent.unlockRecord();
				}
				pfyForms.clearForm( $form );
				$('.pfy-edit-rec-delete-checkbox', $form).hide();
				$('[name=_rec-key]', $form).val('');
				pfyPopupClose();
			})

			// submit button in form:
			.on('click', '.pfy-edit-data-form input[type=submit]', function() {
				if ( parent.pfyTableNewRec ) {
					$('#pfy-chckb__delete_1').prop('checked', false);
				}
			})

			// delete checkbox in form:
			.on('change', 'input.pfy-edit-rec-delete-checkbox', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const $form = $(this).closest('.pfy-edit-data-form');
				var text;
				var val;
				if ($(this).prop('checked')) {
					text = pfyEditRecDeleteBtn;
					val = true;
					mylog('delete');
				} else {
					text = pfyEditFormSubmit;
					val = false;
					mylog('don\'t delete');
				}
				$('.pfy-form-button-submit', $form).val(text);
				$form.data('delete', val);
			});
	}; // setupEventHandlers



	this.makeReadonly = function ( $form ) {
		if (typeof pfyForms !== 'undefined') {
			pfyForms.makeReadonly( $form );
		} else {
			pfyPopup('Error: forms.js not loaded.');
		}
	}; // makeReadonly



	this.unlockRecord = function () {
		const req = '?unlock-rec&ds=' + this.dataRef + '&recKey=' + this.recKey;
		execAjax(false, req, function(json) {
			mylog( json );
		});
	}; // unlockRecord



	this.activateInlineEditing = function ( elem ) {
		const $btn = $( elem );
		const $wrapper = this.$table.closest('.pfy-table-wrapper');
		if (!$btn.hasClass('pfy-button-active')) {	// turn off
			pfyReload();
// $wrapper.removeClass('pfy-table-editable').addClass('pfy-table-editable-inactive');
// $('.pfy-editable', $wrapper).removeClass('pfy-editable').addClass('pfy-editable-inactive');

		} else {	// turn on
			$wrapper.addClass('pfy-table-editable').removeClass('pfy-table-editable-inactive');
			$('.pfy-editable-inactive', $wrapper).addClass('pfy-editable').removeClass('pfy-editable-inactive');
			editables.init();
		}

	}; // activateInlineEditing


	this.init();

} // HTMLtable



function htmltableOnPopupClose() {
	$('[name=_lizzy-form]').each(function () {
		let dataRef = $(this).val();
		let url = appRoot + '_lizzy/_ajax_server.php?unlock-rec&ds=' + dataRef + '&recKey=*';
		$.ajax({
			url: url,
		});
	});
} // htmltableOnPopupClose
