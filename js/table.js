// htmltable.js

"use strict";

var lzyActiveTables = [];
$( document ).ready(function() {
	$('.lzy-active-table').each(function () {
		var tableInx = $(this).data('inx');
		lzyActiveTables[tableInx] = new HTMLtable(this);
	});

	$('.lzy-active-table').on('click', '.lzy-table-view-btn', function () {
		var tableInx = $(this).closest('.lzy-active-table').data('inx');
		lzyActiveTables[tableInx].openViewRecPopup(this);
	});

	$('.lzy-active-table').on('click', '.lzy-table-edit-btn', function () {
		var tableInx = $(this).closest('.lzy-active-table').data('inx');
		lzyActiveTables[tableInx].openForm(this);
		// lzyActiveTables[tableInx].openFormPopup(this);
	});
});


function HTMLtable( tableObj ) {
	this.$table = tableObj;
	if (typeof this.$table[0] === 'undefined') {
		this.$table = $( tableObj );
	}
	this.$tableWrapper = this.$table.closest('.lzy-table-wrapper');
	this.tableInx = this.$table.data('inx');
	this.dataRef = this.$table.closest('[data-datasrc-ref]').data('datasrc-ref');
	this.formHtml = null;
	this.lzyTableNewRec = false;
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
		this.$form = $( formId ).closest('.lzy-edit-rec-form-wrapper');
		this.recViewPopupId = 'lzy-recview-popup-' + this.formInx;
		this.recEditPopupId = 'lzy-recedit-popup-' + this.formInx;

		if (this.$form.length) {
			this.permanentForm = this.$tableWrapper.hasClass('lzy-table-permanent-form');
			if (this.permanentForm) {
				$('.lzy-edit-rec-form-wrapper', this.$tableWrapper).show();
				$('.lzy-edit-rec-delete-checkbox', this.$tableWrapper).hide();
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
		$('.lzy-table').each(function () {
			const $table = $( this ).closest('.lzy-table');
			$table.on('init.dt', function () {
				const $tableWrapper = $table.closest('.lzy-table-wrapper');
				const $tableButtons = $('.lzy-table-action-btns', $tableWrapper);
				if ($tableButtons.length) {
					const html = $tableButtons.html();
					$tableButtons.remove();
					$('.fg-toolbar:first-child', $tableWrapper).prepend( html );
				}
			});
		});
	}; // initDataTables



	this.initViewRecPopup = function () {
		if (!this.$table.hasClass('lzy-rec-preview')) {
			return;
		}

		// modify all IDs to avoid id-clashes:
		var formHtml = this.formHtml;

		// remove all ids from form (which serves no longer as a form, but rather as container):
		formHtml = formHtml.replace(/(id=['"].*?['"])/g, '');
		const cls = 'lzy-popup-bg lzy-popup-' + this.formInx + ' lzy-close-on-bg-click lzy-popup-with-header lzy-recview-popup';

		// inject popup code at end of body:
		const popupHtml = '<div id="' + this.recViewPopupId + '" class="' + cls + '" style="display: none;">\n' +
			'<div class="lzy-popup-wrapper lzy-popup-wrapper-ref" data-popup-inx="' + this.recViewHash + '">\n' +
			'<div class="lzy-popup-header lzy-draggable">' +
				'<div></div>' +
				'<button class="lzy-popup-close-button">×</button>' +
			'</div>' +
			'<div class="lzy-popup-container lzy-scroll-hints">\n' +
			'<div class="lzy-recview-container">\n' +
				formHtml +
			'</div></div></div></div>\n';
		$( 'body' ).append( popupHtml );


		// change form fields into read-only fields:
		this.$recviewPopup = $('#' + this.recViewPopupId);
		this.makeReadonly( this.$recviewPopup );
	}; // initViewRecPopup



	this.initEditFormPopup = function () {
		const cls = 'lzy-popup-bg lzy-popup-' + this.formInx + ' lzy-popup-with-header';
		const formHtml = this.formHtml;
		const popupHtml = '<div id="' + this.recEditPopupId + '" class="' + cls + '" style="display: none;">\n' +
			'<div class="lzy-popup-wrapper lzy-popup-wrapper-ref" data-popup-inx="' + this.recEditHash + '">\n' +
			'<div class="lzy-popup-header lzy-draggable">' +
			'<div></div>' +
			'</div>' +
			'<div class="lzy-popup-container lzy-scroll-hints">\n' +
			'<div id="lzy-edit-rec-form-container">\n' +
			formHtml +
			'</div></div></div></div>\n';
		$( 'body' ).append( popupHtml );
		this.$recEditForm = $( '#' + this.recEditPopupId + ' form');
	}; // initEditFormPopup



	this.openViewRecPopup = function ( $triggerSrc ) {
		if (typeof $triggerSrc[0] === 'undefined') {
			$triggerSrc = $( $triggerSrc );
		}
		lzyPopup({
			id: 'lzy-recview-popup-' + this.formInx,
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
				$('.lzy-form-field-placeholder', $formEl ).text( val );
			}
		});

		var header = $('#lzy-recview-header').text();
		if (!header) {
			header = '&nbsp;';
		}
		$('.lzy-popup-header > div').html( header );

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
		$('.lzy-edit-rec-delete-checkbox', $form).show();
		$form.data('reckey', this.recKey);
		$('input[name=_rec-key]',$form).val( this.recKey );

		if (this.recKey === 'new-rec') {
			$form.addClass('lzy-new-data');
			$('.lzy-edit-rec-delete-checkbox').hide();

		} else  {
			$form.removeClass('lzy-new-data');
			$('.lzy-form-wrapper [name=_rec-key]').val( this.recKey );
			$('.lzy-edit-rec-delete-checkbox').show();
		}

		$('[type=submit]', $form).val( $('#lzy-edit-form-submit').text() );
		$('#lzy-edit-rec-delete-checkbox input[type=checkbox]').prop('checked', false);

		lzyForms.onOpen( this.recKey, $form, true );
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
		const popupId = '#lzy-recedit-popup-' + this.formInx;
		const $popup = $( popupId );

		if (this.recKey === 'new-rec') {
			formTitle = $('#lzy-edit-form-new-rec').html();
			fldPreset = '';
			this.lzyTableNewRec = true;

		} else { // existing rec:
			formTitle = $('#lzy-edit-form-rec').html();
			this.lzyTableNewRec = false;
		}

		lzyPopup({
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
			$form.addClass('lzy-new-data');
			$('.lzy-edit-rec-delete-checkbox').hide();

		} else  {
			$form.removeClass('lzy-new-data');
			$('.lzy-form-wrapper [name=_rec-key]').val( this.recKey );
			$('.lzy-edit-rec-delete-checkbox').show();
		}

		$('[type=submit]', $form).val( $('#lzy-edit-form-submit').text() );
		$('#lzy-edit-rec-delete-checkbox input[type=checkbox]').prop('checked', false);

		lzyForms.onOpen( this.recKey, $form, true );
	}; // openFormPopup



	this.prefillFields = function() {
		$('[data-default-value]').each(function () {
			let $this = $( this );
			let defaultValue = $this.data('default-value');
			$this.val( defaultValue );
		});
		if (typeof lzyTableFormPrefill !== 'undefined') {
			for (let elem in lzyTableFormPrefill) {
				let value = lzyTableFormPrefill[elem];
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
			.on('click', '.lzy-edit-data-form input[type=reset]', function(e) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();

				const $form = $(this).closest('.lzy-form');

				// only in case of existing rec we need to unlock the record:
				if (!$(this).closest('.lzy-new-data').length) {
					parent.unlockRecord();
				}
				lzyForms.clearForm( $form );
				$('.lzy-edit-rec-delete-checkbox', $form).hide();
				$('[name=_rec-key]', $form).val('');
				lzyPopupClose();
			})

			// submit button in form:
			.on('click', '.lzy-edit-data-form input[type=submit]', function() {
				if ( parent.lzyTableNewRec ) {
					$('#lzy-chckb__delete_1').prop('checked', false);
				}
			})

			// delete checkbox in form:
			.on('change', 'input.lzy-edit-rec-delete-checkbox', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const $form = $(this).closest('.lzy-edit-data-form');
				var text;
				var val;
				if ($(this).prop('checked')) {
					text = lzyEditRecDeleteBtn;
					val = true;
					mylog('delete');
				} else {
					text = lzyEditFormSubmit;
					val = false;
					mylog('don\'t delete');
				}
				$('.lzy-form-button-submit', $form).val(text);
				$form.data('delete', val);
			});
	}; // setupEventHandlers



	this.makeReadonly = function ( $form ) {
		if (typeof lzyForms !== 'undefined') {
			lzyForms.makeReadonly( $form );
		} else {
			lzyPopup('Error: forms.js not loaded.');
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
		const $wrapper = this.$table.closest('.lzy-table-wrapper');
		if (!$btn.hasClass('lzy-button-active')) {	// turn off
			lzyReload();
// $wrapper.removeClass('lzy-table-editable').addClass('lzy-table-editable-inactive');
// $('.lzy-editable', $wrapper).removeClass('lzy-editable').addClass('lzy-editable-inactive');

		} else {	// turn on
			$wrapper.addClass('lzy-table-editable').removeClass('lzy-table-editable-inactive');
			$('.lzy-editable-inactive', $wrapper).addClass('lzy-editable').removeClass('lzy-editable-inactive');
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