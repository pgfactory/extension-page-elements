<?php
namespace PgFactory\PageFactory;

use Kirby\Exception\InvalidArgumentException;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PfyForm.php';
require_once __DIR__ . "/../src/PfyFormSplitSyntax.php";



if (!isset($GLOBALS['pfy.form'])) {
    $GLOBALS['pfy.form'] = false;
}
/*
 * PageFactory Macro (and Twig Function)
 */


/**
 * @throws InvalidArgumentException
 */
return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
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

            //'ownerNotificationTemplate' =>	['(string) Name of a special TransVar that contains elements "subject" and "message". '.
            //  'Each may contain sub-elements containing language variants, such as "de" or "_".'.
            //  'If not template is specified, TransVars "pfy-form-owner-notification-subject" and "pfy-form-owner-notification-message" '.
            //  'are used instead.', null],

            'confirmationEmailTo' =>	['[true|name-of-email-field] Sends a confirmation mail to the user. '.
                'If true, picks the first e-mail field in the form.'.
                'Else provide the name of an e-mail field within the form.<br>'.
                'Variables ``&#123;&#123; pfy-confirmation-response-subject }}`` and '.
                '``&#123;&#123; pfy-confirmation-response-message }}`` are used to compose message. '.
                'Use placeholders like ``%key%`` to render corresponding form fields.', null],

            'ownerNotificationIf' =>	['(field-name) If set, notification mail is sent ONLY if given field '.
                'is NOT empty. E.g. "ownerNotificationIf: Comment".', null],

            'confirmationEmail' =>	['Synonym for "confirmationEmailTo".', null],

            //'confirmationEmailTemplate' =>	['(string) Name of a special TransVar that contains elements "subject" and "message". '.
            //    'Each may contain sub-elements containing language variants, such as "de" or "_".'.
            //    'If not template is specified, TransVars "pfy-confirmation-response-subject" and "pfy-confirmation-response-message" '.
            //    'are used instead..', null],

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

            'scriptInjectionFilter' =>	['Activates a filter against script injection attacks. '.
                'Example: "`<script>alert(\'malicious code\')</script>`".<br>Caution: only disable in justified cases!', true],

            'dbOptions' =>	['[{options}] Provide auxiliary options to DataSet class, e.g. "dbOptions: {masterFileRecKeySort: true}".', []],

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
        ],
        'summary' => <<<EOT

# $funcName()

#### Example:

    \// Frontmatter:
    variables:
    pfy-form-response-label: 'Event registration'
    pfy-form-owner-notification-subject: .\..
    pfy-form-owner-notification-message: .\..
    pfy-confirmation-response-subject: .\.. %pageUrl%
    pfy-confirmation-response-message: |
        Plaintext version.\..
        ==== CSS
        .pfy-htmlmail-inner-wrapper td { font-size:12pt;padding: 0.2em 1.5em 0.2em 0; }
        ==== HTML
        Hello %Name%,
        .\.. 

    \-\-\-\-

    \{{ form(
        file:                '\~data/db.json',
        editData:            true
        tableOptions:        { interactive:true, paging:true }
        responseLabel:       'Event registration' \// used in notification and confirmation emails
        ownerNotificationTo: true  \// 'true' for webmaster-email or explicit e-mail address 
        confirmationEmailTo: true  \// 'true' selects the first e-mail field below
        mailFromName:        'Our organization'
        warnBeforeLeavingPage: true
        \//maxCount:          12
        \//deadline:          2025-06-03

        Name:                { required:true }
        \// Name2:            { antiSpam:Name }
        EMail:               { type:email }
        Comment:             { type:textarea, reveal:true },

        cancel:              { },
        submit:              { },
        ) 
    }}

#### Form Arguments:
-> any arguments stated below under **Arguments** are interpreted as *form arguments*.\
All other arguments are interpreted as field definitions/buttons.

#### Form Fields:
Syntax: ``field-name: { field arguments, \... }``

``field-name``   10em>> -> name under which data will be stored in DB.
                >> -> Also row header in "showData" table

#### Supported Field Types:

`text, password, email, textarea, hidden, url,
date, datetime (resp. datetime-local), time, month,
number, integer, range, tel, 
radio, checkbox, dropdown, select, multiselect, upload, multiupload, bypassed, 
button, reset, submit, cancel`

Default type: **text**  
Types automatically derived from field *field-names*: `email`, `passwor*`, `submit`, `cancel`

#### Field Arguments

@@@ --tt1-width: 8.5em

All:
: - id   >> [string] 
: - class   >> [string] (-> e.g. class:short )
: - label   >> [string] 
: - placeholder   >> [string] 
: - preset   >> [any] initial value (also: 'default' or 'value')
: - required    >> [bool,identifier]
: - autocomplete   >> [string|bool]
: - disabled    >> [bool]
: - readonly    >> [bool]
: - info        >> [string] info icon showing info text as tooltip
: - description     >> [string] text next/below input field
: - antiSpam        >> [string] -> see below
: - columnHeader    >> [string] -> if set, replaces 'label' as column header for the output table

textarea:
: - reveal      >> [string|true] If set, textarea is hidden until label is clicked

number/integer/range:
: - min         >> [integer]
: - max         >> [integer]
: - default     >> [integer]

radio/checkbox/dropdown/select/multiselect:
: - options          >> ['name:label' array] Choice options, e.g. "a:A, b:B, c:C"
: - prompt          >> [string] first option "call-to-action" (select only)
: - preset          >> [string] initially selected option
: - splitOutput     >> [bool] If true, table of "showData" output shows row for each option 
: - layout          >> [horizontal,vertical]

upload/multiupload: (currently only image files supported)
: - maxMegaByte     >> [integer] allowed file size in MB

@@@

<div class="pfy-vgap" style="margin:0.7em 0;">&nbsp;</div>

#### AntiSpam

To activate the anti-spam mechanism, you need to add an additional text field to your form, e.g. 

    Name2: { antiSpam:Name }

where *Name* is the field-name of another field in your form. This will insert an invisible honeypot field.

#### CSS-Variables:
- `-\-pfy-form-width`		(28em)
- `-\-pfy-form-label-width`		(6em)
- `-\-pfy-form-input-width`		(22em)
- `-\-pfy-form-row-gap-height`		(1em)

- `-\-pfy-form-input-height`		(2.2em)
- `-\-pfy-form-input-medium-width`		(4em)
- `-\-pfy-form-input-medium-width`		(10em)
- `-\-pfy-form-choice-option-width`		(6em)

- `-\-pfy-form-input-color`		(inherit)
- `-\-pfy-form-field-background-color`		(#fffff6)
- `-\-pfy-form-field-border`		(1px solid #b4b3b3)
- `-\-pfy-form-readonly-field-bg`		(#f8f8f8)

- `-\-pfy-form-required-marker-color`		(orange)
- `-\-pfy-form-tooltip-anker-color`		(inherit)
- `-\-pfy-form-field-description-color`		(inherit)
- `-\-pfy-form-tooltip-color`		(#222)
- `-\-pfy-form-tooltip-bg`		(#fef5e0)
- `-\-pfy-form-error-color-base`		(20deg, 100%)

- `-\-pfy-form-reveal-controller-bg`
- `-\-pfy-form-reveal-controller-border`		(1px solid #eee)
- `-\-pfy-form-reveal-container-bg`
- `-\-pfy-form-reveal-container-border`		(1px solid #eee)
- `-\-pfy-form-reveal-padding`		(0)

- `-\-pfy-problem-with-pfy-form-bg`		(#fff9ee)
- `-\-pfy-problem-with-pfy-form-border`		(orange)

Note: colors only active if `.pfy-form-colored` is applied to the form.

#### Classes

- `.pfy-show-unless-form-data-received`  -> hide content when data received
    
<div class="pfy-vgap" style="margin:0.7em 0;">&nbsp;</div>

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return shieldStr($res);
    } else {
        list($options, $sourceCode, $inx, $macroName, $auxOptions) = $res;
        $html = $sourceCode;
    }

    $formFields = $auxOptions;

    if ($options['edit']??false) {
        $options['editData'] = $options['edit'];
    }
    if ($options['editData'] && is_array($options['editData'])) { // depricated
        $options['tableOptions'] += $options['editData'];
    }
    if ($options['showData'] && is_array($options['showData'])) { // depricated
        $options['showData'] += $options['showData'];
        $options['showData'] = true;
    }

    // mailTo synonyme for ownerNotificationTo:
    if ($options['mailTo']??false) {
        $options['ownerNotificationTo'] = $options['mailTo'];
        unset($options['mailTo']);
    }
    // confirmationEmail synonyme for ownerNotificationTo:
    if ($options['confirmationEmail']??false) {
        $options['confirmationEmailTo'] = $options['confirmationEmail'];
        unset($options['confirmationEmail']);
    }

    // make type=datetime synonym for type=datetime-local:
    foreach ($formFields as $name => $rec) {
        if (($rec['type']??false) === 'datetime') {
            $formFields[$name]['type'] = 'datetime-local';
        }
    }

    if (($options['maxCount']??false) && !($options['minRows']??false)) {
        $options['minRows'] = $options['maxCount'];
    }

    $options['dataReceivedCallback'] = $options['dataReceivedCallback'] ?: $options['callback'];
    $output = ($options['output']??false);

    if ($output === true) {
        // normal invocation in one junk:
        $form = new PfyForm($options);
        $html .= $form->renderForm($formFields);

    } else {
        if ($output === false) {
            $form = $GLOBALS['pfy.form'] = new PfyFormSplitSyntax($options);
            list($continue, $html) = $form->initRenderForm($formFields);
            if ($continue) {
                $html .= $form->renderFormWrapperHead();
            }

        } else {
            $html .= $GLOBALS['pfy.form']->renderFormPieces(uptoWhich: $output);
        }
    }

    return $html;
}; // form



