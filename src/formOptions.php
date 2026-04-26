<?php
// Definition of arguments and help-text for form() and forms() macros:
return  [
        'file' =>	['File where to store data submitted by users. E.g. "&#126;data/form.json"', false],

        'id' =>	['Id applied to the form element.', false],

        'class' =>	['Class applied to the form element.<br>(Note: to prevent default coloring of form, override class '.
            'without "pfy-form-colored")', 'pfy-form-colored'],

        'wrapperClass' =>	['Class applied to the form wrapper.', null],

        'outerWrapperClass' =>	['Class applied to the outer div wrapping form and table.', null],

        'labelWidth' =>	['Sets the label width (-> defines CSS-variable ``-\-form-label-width``)', false],

        'action' =>	['Argument applied to the form element\'s "action"-attribute.', false],

        'responseLabel' =>	['Label that describes the nature of form data. Will be used in owner notification '.
            'confirmation emails. (alternatively you can define variable `pfy-form-response-label`).', false],

        'ownerNotificationTo' =>	['If set, an email will be sent to this address each time the form is filled in. '.
            '(If true, the webmaster email is used).', false],
        'mailTo' =>	['Synonym for "ownerNotificationTo".', false],

        'confirmationEmailTo' =>	['[true|name-of-email-field] Sends a confirmation mail to the user. '.
            'If true, picks the first e-mail field in the form.'.
            'Else provide the name of an e-mail field within the form.<br>'.
            'Variables ``&#123;&#123; pfy-confirmation-response-subject }}`` and '.
            '``&#123;&#123; pfy-confirmation-response-message }}`` are used to compose message. '.
            'Use placeholders like ``%key%`` to render corresponding form fields.', null],

        'ownerNotificationIf' =>	['(field-name) If set, notification mail is sent ONLY if given field '.
            'is NOT empty. E.g. "ownerNotificationIf: Comment".', null],

        'confirmationEmail' =>	['Synonym for "confirmationEmailTo".', null],

        'mailFrom' =>	['The address from which service emails are sent. (default: "{{ webmaster_email }}").', false],
        'mailFromName' =>	['Name from which service emails are sent.', ''],

        'formTop' =>	['Text rendered above the form. BR Note: formTop/formHint/formBottom will not show up in '.
            'form response following form submission. BR '.
            'These fields may contain placeholders ``%deadline%``, ``%count%``, ``%sum%``, ``%available%``, ``%max%`` '.
            '(or ``%total%``).', false],

        'formHint' =>	['Text rendered above the form buttons. (Default: ``\{\{ pfy-form-required-info }}``)', false],

        'formBottom' =>	['Text rendered below the form buttons.', false],

        'deadline' =>	['(ISO-date) If set, the form will be disabled after deadline has passed. '.
            'Then ``\{\{ pfy-form-deadline-expired }}`` is shown.', false],

        'deadlineNotice' =>	['(string) Defines the response displayed when deadline has passed.', false],

        'maxCount' =>	['If set, the number of sign-ups will be limited. '.
            'If exeeded, ``\{\{ pfy-form-maxcount-reached }}`` is shown.', null, 'scalar'],

        'maxCountOn' =>	['If maxCount is set, identifies the field to use for counting sign-ups.', false],

        'lowSeatsWarning' => ['(integer) If lowSeatsWarning is set, variable "pfy-form-available-seats-banner" '.
            'is shown, if number of available seats is less that given value.', false],

        'next' =>	['[URL] If set, defines the link target (href) of the "Continue..." response.', false],

        'formFreezeTime' =>	['[false, time-spec] If not false, window will freeze after specified time, '.
            'e.g. "+1 hour" or number of seconds.', '+1 hour'],

        'confirmationText' =>	['The text rendered upon successful completion of a form entry. '.
            'Which is followed by a "Continue..." link.'.
            '(Default: ``\{\{ pfy-form-submit-success }}``).', null],

        'schedule' => ['{options} If defined, Events class is invoked to determine the next event and .'.
            'based on that, presets "file", "maxCount" and "minRows". '.
            'The event\'s rendered output becomes available as "%eventBanner%" to form banners.'.
            '(E.g. formTop: "\<div>%eventBanner%\</div>").<br>'.
            'Moreover, all values of found event are made available to form banners as "%key%". '.
            '(For ref see macro *events()*).', false],

        'feedback' =>	['["inpage","notification"] Defines, how the system responds to data submission. '.
            '"inpage" means that the form is replaced with a confirmation (or error) text. '.
            'In "notification" mode the form remain visible and confiration info is presented as a '.
            'notification banner.', 'inpage'],

        'showDirectFeedback' =>	['[bool] Depricated: synonym for "feedback: notification".', null],

        'enableLocalFormCache' =>	['[bool] If true, data entered in the form will be preserved in case the user '.
            'accidentially leaves the page and comes back to continue. This improves usability, but introduces '.
            'new security risks (in particular, if using third party JS like trackers).', false],

        'retainData' =>	['[bool] If true, entered data remains in the form after submitting it. '.
            'This mimicks working with a local data entry system like a database.', false],

        'formDataId' =>	['[string] If defined, data is retained under this name. This way, multiple forms '.
            'can be synchronized.', null],

        'avoidDuplicates' =>	['If true, checks whether identical data-rec already '.
            'exists in DB. If so, skips storing data.', true],

        'showData' =>	['[true] Shows received data. Short-hand for:<br>'.
            '``{tableButtons: download, serviceColumns: num, permission:\'loggedin,localhost\'}`` '.
            '(see editData for reference.)', false],

        'editData' =>	['[true|{options}] Shows received data and defines, whether data records can be edited. '.
            'Available options:<br>``tableButtons:\'delete,archive,new,download\'``,<br>'.
            '``serviceColumns:\'select,num,edit,send\'``,<br>'.
            '``permission:\'loggedin,group,localhost\'``,<br>``mode:popup<br>``'.
            '("editData:true" is shorthand for typical set of options.)', false],

        'edit' =>	['Synonym for "editData".', null],

        'recLocking' =>	['[bool] Defines, whether record-locking shall be activated while editing a record.', false],

        'includeSystemFields' => ['[bool] If true, system fields "_timestamp" and "_reckey" are included '.
            'in output table.', false],

        'tableOptions' =>	['[{options...}] Options that are forwarded to table rendering (see table() macro).', []],

        'minRows' =>	['[integer] If defined, the "showData" table is filled with '.
            'empty rows up to given number. BR '.
            'Note: if ``maxCount`` is active, ``minRows`` will be automatically set to that value.', false],

        'interactiveTable' =>	['[bool] If true, data table can be interactively sorted and filtered.', false],

        'emailFieldName' =>	['[name-of-email-field] Replaces option "confirmationEmail", if that is not used. '.
            'Identifies the field containing an e-mail address within the dataset. It is used by tableOptions "mail"', false],

        'dataReceivedCallback' =>	['Defines a callback function to be invoked when the backend receives user input. '.
            'Can be a PHP function or a PHP file, e.g. "~custom/sanitize.php".', false],

        'callback' =>	['Legacy synonyme for "dataReceivedCallback"', false],

        'presetCallbackJs' =>	['Defines a callback JS function to be invoked when the form is preset. '.
            'The JS function must be defined elsewhere.', false],

        'onSubmitCallbackJs' =>	['Defines a callback JS function to be invoked before the form is submitted.'.
            'The callback function must return an error-value: false => Ok; string => show alert; '.
            '[errMsg, field-selector] => show error msg next to input field.', false],

        'scriptInjectionFilter' =>	['Activates a filter against script injection attacks. '.
            'Example: "`<script>alert(\'malicious code\')</script>`".<br>Caution: only disable in justified cases!', true],

        'dbOptions' =>	['[{options}] Provide auxiliary options to DataStore class, e.g. "dbOptions: {masterFileRecKeySort: true}".', []],

        'output' =>	['Option to control split syntax rendering: <br>'.
            '1) ``false`` to define form without output.<br>'.
            '2) ``head`` to render form head.<br>'.
            '3) ``name-of-elem`` to render fields up to that field (optional and repeatable).<br>'.
            '4) ``rest`` to render all remaining form elements<br>'.
            '5) ``tail`` form tail.', true],

        'problemWithFormBanner' =>	['If true, a banner is added below the form, providing the '.
            'webmaster-email to contact in case of problems with the form.', true],

        'readonly' =>	['If true, adds class "pfy-form-readonly" to form wrapper class -> '.
            '-> freezes entire form.', false],

        'sideBySide' =>	['If true, the data table will be rendered next to the form. '.
            'Moreover, clicking a row will present the corresponding record in the from.', null],

        'warnBeforeLeavingPage' => ['[bool] If true and user has modified form fields and then wants '.
            'to leave the page, the browser shows a warning.', false],
    ];