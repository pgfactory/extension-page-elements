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
 * PageFactory Macro
 */


/**
 * @throws InvalidArgumentException
 */
return function ($args = '')
{
    $recLockingDefaultTime = PfyForm::DEFAULT_MAX_REC_LOCKING_TIME . 's';

    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => require __DIR__ . "/../src/formOptions.php",
        'summary' => <<<EOT

# $funcName()

<> Simple Example 
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
        tableOptions:        { interactive:true, paging:true, fullWidth:true }
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

<>

<> Split Syntax Example

    \{{ form(
        file:			'~data/db.yaml',
        editData:       true
        **output:         false**

        Name:		    {required:true}
        Phone:		    {type:tel}
        EMail:		    {type:email}
    
        cancel:    		{},
        submit:    		{}
        )
    }}
    
    \{{ form(output: head) }}
    \{{ form(output: **Name**) }}
    * Text in between.\..*
    \{{ form(output: rest) }}
    \{{ form(output: tail) }}

<>

<> Schedule Controlled Forms

    \{{ form(
        file:			'~data/registrations.json',
        editData:       { permission:'localhost|loggedin'}
        tableOptions:   {
            tableButtons: 'delete,download,mail'
            tableTitle:     '## Registrations for %start%'
            tableFooters: 	{ Number: %sum% }
            includeTimestamp: true
            order:          Lastname
        }
    
        confirmationEmailTo: true
        
        mailFrom:       'stamm@sfs-meilen.ch'
        mailFromName:   'SfS Meilen'
    
        schedule:       { 
            src:'~config/events.json', 
            category: Stamm, 
            template: {
                file:~page/template.txt
            }
            \// modifiers: 'FILENAME_INCL_TIME' \*)
        },
        formTop:        '<div>%eventBanner%</div>'
        maxCount:        25
    
        Firstname:      {required:true},
        Lastname:       {required:true},
        Name:           {antiSpam: Lastname}
        Number:         {type: integer, value:1, min:1, max:4, class:short, required:true }
        E-Mail:         {label: 'E-Mail:', type: email, required:true}
    
        cancel:    		{},
        submit:    		{}
        )
    }}
    
*) allows multiple events per day -> uses long form of timestamp in filename.
<>

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
radio, checkbox, dropdown, select, multiselect, countedchoices, upload, multiupload, bypassed, 
button, reset, submit, cancel`

Default type: **text**  
Types automatically derived from field *field-names*: `email`, `passwor*`, `submit`, `cancel`

#### Field Arguments

@@@ --tt1-width: 8.5em

All:
: - id   8em>> [string] 
: - class   8em>> [string] (-> e.g. class:short )
: - label   8em>> [string] 
: - placeholder   8em>> [string] 
: - preset   8em>> [any] initial value (also: 'default' or 'value')
: - required    8em>> [bool,identifier]
: - autocomplete   8em>> [string|bool]
: - disabled    8em>> [bool]
: - readonly    8em>> [bool]
: - info        8em>> [string] info icon showing info text as tooltip
: - postfix     8em>> [string] text just behind input field
: - description     8em>> [string] text next/below input field
: - antiSpam        8em>> [string] -> see below
: - columnHeader    8em>> [string] -> if set, replaces 'label' as column header for the output table

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

countedchoices:
: - id         >> Id applied to the outer wrapper
: - attrib     >> Use attrib to define reference to controller, e.g. "attrib: 'data-controlled-by:#controller-id'"
: - label      >> Defines the outer widget label, e.g. 'Menu Choice:'
: - max        >> Identifies the field controlling the max number of visitors per sign-up, e.g. 'Count', 
: - options    >> Defines the options, e.g. 'Meat: meat dish, Vegi: Vegetarian meal, Salad: Salad plate',

    Count:{ type:integer, min:1, max:3, preset:1, attrib:'aria-controls:menu'} \// controlling field
    Menu: {
        id:         menu \// id of outer widget -> to refer from controlled field
        attrib:     'data-controlled-by:#count' \// id of controlling field
        type:       countedchoices, 
        label:      'Menu Choice:', \// Outer label of widget
        max:        '=Count', \// where 'Count is name of controlling field
        options:    'Meat: meat dish, Vegi: Vegetarian meal, Salad: Salad plate',
        preset:     0 
    },


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
- `-\-pfy-form-tooltip-anchor-color`		(inherit)
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



