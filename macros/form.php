<?php
namespace Usility\PageFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PfyForm.php';



/*
 * PageFactory Macro (and Twig Function)
 */


function form($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'id' =>	['Id applied to the form element.', false],

            'class' =>	['Class applied to the form element.', false],

            'action' =>	['Argument applied to the form element\'s "action"-attribute.', 'post'],

            'mailTo' =>	['If set, an email will be sent to this address each time the form is filled in.', false],

            'mailFrom' =>	['The address from which service emails are sent. (default: "{{ webmaster_email }}").', false],
            'mailFromName' =>	['Name from which service emails are sent.', ''],

            'formTop' =>	['Text rendered above the form. BR Note: formTop/formHint/formBottom will not show up in '.
                'form response following form submission. BR '.
                'These fields may contain placeholders ``%deadline``, ``%count``, ``%sum``, ``%available``, ``%max`` '.
                '(or ``%total``).', false],

            'formHint' =>	['Text rendered above the form buttons.', false],

            'formBottom' =>	['Text rendered below the form buttons.', false],

            'deadline' =>	['(ISO-date) If set, the form will be disabled after deadline has passed.', false],

            'deadlineNotice' =>	['(string) Defines the response displayed when deadline has passed.', false],

            'maxCount' =>	['If set, the number of sign-ups will be limited.', false],

            'maxCountOn' =>	['If maxCount is set, identifies the field to use for counting sign-ups.', false],

            'customResponseEvaluation' =>	['Name of a PHP function to be called when user submitted form data.', false],

            'confirmationText' =>	['The text rendered upon successful completion of a form entry.', false],

            'file' =>	['File where to store data submitted by users. E.g. "&#126;data/form.yaml"', false],

            'avoidDuplicates' =>	['If true, checks whether identical data-rec already '.
                'exists in DB. If so, skips storing data.', true],

            'showData' =>	['[false, true, loggedIn] '.
                'Defines, to whom previously received data is presented.', false],

            'sortData' =>	['[bool] Defines, whether data table shall be sorted and on which column.', false],

            'includeSystemFields' => ['[bool] If true, system fields "_timestamp" and "_reckey" are included '.
                'in output table.', false],

            'tableFooters' =>	['(recId:\'%sum\' or \'%count\' or \'string\') '.
                'Adds a footer row to the table showing counts and sums for specified columns..', false],

            'minRows' =>	['[integer] If defined, the "showData" table is filled with '.
                'empty rows up to given number. BR '.
                'Note: if ``maxCount`` is active, ``minRows`` will be set automatically to that value.', false],

            'confirmationEmail' =>	['[name-of-email-field] If set to the name of an '.
                'e-mail field within the form, a confirmation mail will be sent.', false],

            'confirmationEmailTemplate' =>	['[name-of-template-file,true] This defines '.
                'what to put into the mail. If true, standard variables will be used: "&#123;&#123;pfy-confirmation-response-subject }}" '.
                'and "&#123;&#123;pfy-confirmation-response-message }}".<br>Alternatively, you can specify the name of a template file. <br>'.
                'All form-inputs are available as variables of the form "&#123;&#123; <strong>&#95;&#95;fieldName&#95;&#95;</strong> }}" '
                , false],

            'labelWidth' =>	['Sets the label width (-> defines CSS-variable "-\-form-label-width")', false],
        ],
        'summary' => <<<EOT

# $funcName()

#### Example:

    @@@ .screen-only
    ## Form
    @@@
    \{{ form(
    \// form arguments:
        file:			'\~data/db.yaml',
        showData:       true
        maxCount:       12
    \// form fields:  "name: { field arguments... }"
        Name:           {required:true}
        Name2:          { antiSpam:Name }
        Comment:		{type: textarea },
    \// buttons:
        cancel:    		{ },
        submit:    		{ },
        ) 
    }}

#### Form Fields:
Syntax: ``field-name: { field arguments, \... }`

``field-name``   10em>> -> name under which data will be stored in DB.
                >> -> Also row header in "showData" table

#### Supported Field Types:

`text,password,email,textarea,hidden,
url,date,datetime-local,time,datetime,month,number,integer,range,tel,file,
radio,checkbox,dropdown,select,multiselect,upload,multiupload,bypassed,
button,reset,submit,cancel`

Default type: **text**  
Types automatically derived from field *field-names*: `email`, `passwor*`, `submit`, `cancel`

#### Field Arguments

All:
: - id   >> [string] 
: - class   >> [string] (-> e.g. class:short )
: - label   >> [string] 
: - placeholder   >> [string] 
: - value   >> [any] initial value
: - default   >> [any] same as value
: - required    >> [bool]
: - disabled    >> [bool]
: - readonly    >> [bool]
: - info        >> [string] info icon showing info text
: - description     >> [string] text next/below input field
: - antiSpam        >> [string] -> see below

textarea:
: - reveal      >> [string] If set, textarea is hidden until label is clicked

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
: - maxMegaByte     10em>> [integer] allowed file size in MB

{{ vgap }}

#### AntiSpam

To activate the anti-spam mechanism, you need to add an additional text field to your form, e.g. 

    Name2: { antiSpam:Name }

where *Name* is the field-name of another field in your form. This will insert an invisible honeypot field.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return shieldStr($res);
    } else {
        list($options, $sourceCode, $inx, $macroName, $auxOptions) = $res;
        $html = $sourceCode;
    }
if ($inx !== 1) {
    throw new \Exception("Forms: multiple instances not possible yet.");
}
    $options['formInx'] = $inx;

    if ($options['maxCount'] && !$options['minRows']) {
        $options['minRows'] = $options['maxCount'];
    }
    $form = new PfyForm($options);
    $form->createForm(null, $auxOptions);

    if ($lWidth = $options['labelWidth']) {
        PageFactory::$pg->addCss(".pfy-form-$inx { --form-label-width: $lWidth}\n");
    }

    $res = false;
    if ($form->isSuccess()) {
        $res = $form->handleReceivedData($inx);
    }
    if (!$res) {
        $html .= $form->renderForm();
    } else {
        $html .= $res;
    }

    $html = TransVars::translate($html);
    $html = shieldStr($html, 'inline');
    return $html;
} // form



