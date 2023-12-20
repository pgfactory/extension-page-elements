<?php
namespace PgFactory\PageFactory;

use Kirby\Exception\InvalidArgumentException;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PfyForm.php';


$GLOBALS['pfy.form'] = false;
/*
 * PageFactory Macro (and Twig Function)
 */


/**
 * @throws InvalidArgumentException
 */
function form($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' =>	['File where to store data submitted by users. E.g. "&#126;data/form.yaml"', false],

            'part' =>	['[head,fields,tail] If set, defines which part of a form is constructed. '.
                '"head" accepts form-options. "fields" defines form fields and can be invoked multiple times. '.
                '"tail" terminates form assembly and renders final the HTML.', false],

            'id' =>	['Id applied to the form element.', false],

            'class' =>	['Class applied to the form element.', 'pfy-form-colored'],

            'wrapperClass' =>	['Class applied to the form wrapper.', false],

            'action' =>	['Argument applied to the form element\'s "action"-attribute.', false],

            'mailTo' =>	['If set, an email will be sent to this address each time the form is filled in.', false],

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
                'If exeeded, ``\{\{ pfy-form-maxcount-reached }}`` is shown.', false],

            'maxCountOn' =>	['If maxCount is set, identifies the field to use for counting sign-ups.', false],

            'next' =>	['[URL] If set, defines the link target (href) of the "Continue..." response.', false],

            'confirmationText' =>	['The text rendered upon successful completion of a form entry. '.
                'Which is followed by a "Continue..." link.'.
                '(Default: ``\{\{ pfy-form-submit-success }}``).', null],

            'schedule' => ['{options} If defined, Events class is invoked to determine the next event and .'.
                'based on that, presets "file", "maxCount" and "minRows". '.
                'The event\'s rendered output becomes available as "%eventBanner%" to form banners.'.
                '(E.g. formTop: "\<div>%eventBanner%\</div>").<br>'.
                'Moreover, all values of found event are made available to form banners as "%key%". '.
                '(For ref see macro *events()*).', false],

            'showDirectFeedback' =>	['[bool] If true, a confiration text is presented upon successful completion of a '.
                'form entry. Otherwise, only an information banner is shown.', true],

            'avoidDuplicates' =>	['If true, checks whether identical data-rec already '.
                'exists in DB. If so, skips storing data.', true],

            'showData' =>	['[true] Shows received data. Short-hand for:<br>'.
                '``{tableButtons: download, serviceColumns: num, permission:\'loggedin,localhost\'}`` '.
                '(see editData for reference.)', false],

            'editData' =>	['[true|{options}] Shows received data and defines, whether data records can be edited. '.
                'Available options:<br>``tableButtons:\'delete,archive,new,download\'``,<br>'.
                '``serviceColumns:\'select,num,edit\'``,<br>'.
                '``permission:\'loggedin,group,localhost\'``,<br>``mode:popup<br>``'.
                '("editData:true" is shorthand for typical set of options.)', false],

            'recLocking' =>	['[bool] Defines, whether record-locking shall be activated while editing a record.', false],

            'sortData' =>	['[bool] Defines, whether data table shall be sorted and on which column.', false],

            'includeSystemFields' => ['[bool] If true, system fields "_timestamp" and "_reckey" are included '.
                'in output table.', false],

            'tableFooters' =>	['(recId:\'%sum%\' or \'%count%\' or \'string\') '.
                'Adds a footer row to the table showing counts and sums for specified columns..', false],

            'minRows' =>	['[integer] If defined, the "showData" table is filled with '.
                'empty rows up to given number. BR '.
                'Note: if ``maxCount`` is active, ``minRows`` will be automatically set to that value.', false],

            'interactiveTable' =>	['[bool] If true, data table can be interactively sorted and filtered.', false],

            'confirmationEmail' =>	['[name-of-email-field] If set to the name of an '.
                'e-mail field within the form, a confirmation mail will be sent.', false],

            'confirmationEmailTemplate' =>	['[name-of-template-file,true] This defines '.
                'what to put into the mail. If true, standard variables will be used: ``&#123;&#123;pfy-confirmation-response-subject }}`` '.
                'and ``&#123;&#123;pfy-confirmation-response-message }}``.<br>Alternatively, you can specify the name of a template file. <br>'.
                'All form-inputs are available as variables of the form ``&#123;&#123; <strong>&#95;fieldName&#95;</strong> }}`` '
                , true],

            'labelWidth' =>	['Sets the label width (-> defines CSS-variable ``-\-form-label-width``)', false],

            'callback' =>	['Defines a callback function to be invoked upon receiving user input. '.
                'Can be a PHP function or a PHP file, e.g. "~custom/sanitize.php".', false],

            'tableTitle' =>	['If set, defines the title above the data-table in markdown format. '.
                'Supported placeholders: ``%today%`` ``%now%``', false],

            'dbOptions' =>	['[{options}] Provide auxiliary options to DataSet class, e.g. "dbOptions: {masterFileRecKeySort: true}".', []],

            'output' =>	['Option to control peacemeal rendering: <br>'.
                '1) ``false`` to define form without output.<br>'.
                '2) ``name-of-elem`` to render fields up to that field.<br>'.
                '3) ``rest`` to render rest of form.', true],

            'problemWithFormBanner' =>	['If true, a banner is added below the form, providing the '.
                'webmaster-email to contact in case of problems with the form.', true],
        ],
        'summary' => <<<EOT

# $funcName()

#### Example:

    @@@ .pfy-screen-only
    ## Form
    @@@
    \{{ form(
    \//=== form arguments:
        file:			'\~data/db.yaml',
        showData:       true
        maxCount:       12
    \//=== form fields:
        Name:           { required:true }
        Name2:          { antiSpam:Name }
        Comment:		{ type: textarea },
    \//=== buttons:
        cancel:    		{ },
        submit:    		{ },
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
date, datetime-local, time, datetime, month,
number, integer, range, tel, file,
radio, checkbox, dropdown, select, multiselect, upload, multiupload, bypassed, 
button, reset, submit, cancel`

Default type: **text**  
Types automatically derived from field *field-names*: `email`, `passwor*`, `submit`, `cancel`

#### Field Arguments

All:
: - id   >> [string] 
: - class   >> [string] (-> e.g. class:short )
: - label   >> [string] 
: - placeholder   >> [string] 
: - preset   >> [any] initial value (also: 'default' or 'value')
: - required    >> [bool]
: - disabled    >> [bool]
: - readonly    >> [bool]
: - info        >> [string] info icon showing info text as tooltip
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

<div class="pfy-vgap" style="margin:0.7em 0;">&nbsp;</div>

#### AntiSpam

To activate the anti-spam mechanism, you need to add an additional text field to your form, e.g. 

    Name2: { antiSpam:Name }

where *Name* is the field-name of another field in your form. This will insert an invisible honeypot field.

#### CSS-Variables:
- -\-form-width
- -\-form-row-gap-height
- -\-form-label-width
- -\-form-input-width
- -\-form-field-background-color
- -\-form-required-marker-color
- -\-form-tooltip-anker-color
- -\-form-field-description-color:
- -\-form-field-error-color:

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

    if ($options['maxCount'] && !$options['minRows']) {
        $options['minRows'] = $options['maxCount'];
    }
    $output = $options['output'];

    if ($output === true) {
        // normal invocation in one junk:
        $form = new PfyForm($options);
        $html .= $form->renderForm($auxOptions);

    } else {
        if ($output === false) {
            $form = $GLOBALS['pfy.form'] = new PfyFormSplitSyntax($options);
            $html = $form->init($auxOptions);

        } else {
            $html .= $GLOBALS['pfy.form']->renderFormPieces(uptoWhich: $output);
        }
    }

    if ($html) {
        $html = shieldStr($html);
    }
    return $html;
} // form



 // === class PfyFormSplitSyntax ======================================
class PfyFormSplitSyntax extends PfyForm
{
    private mixed $lastRendered = false;

    public function init(array $formElements): string
    {
        $this->createForm($formElements);

        $html = "\n\n<!-- === pfy form widget === -->\n";

        $this->handleReceivedData();

        // check for form issues: deadlinePassed and maxCountExceeded:
        $formIssueResponse = '';
        if ($this->deadlinePassed) {
            $formIssueResponse = $this->deadlinePassed;
            if (!$this->isFormAdmin) {
                $this->showForm = false;
            }
        } elseif ($this->maxCountExceeded) {
            $formIssueResponse = $this->maxCountExceeded;
            if (!$this->isFormAdmin) {
                $this->showForm = false;
            }
        }

        if (!$this->showForm && $this->showDirectFeedback) {
            // normal case after data received -> show response, hide form:
            $html .= $formIssueResponse.$this->formResponse;
            $this->injectNoShowCssRule();
            $html .= "<div class='pfy-show-unless-form-data-received-$this->formIndex'>\n";

        } else {
            // check for data-received feedback:
            if (!$this->showDirectFeedback && $this->formResponse) {
                // no showDirectFeedback -> send feedback via banner:
                reloadAgent(message: strip_tags($this->formResponse));
            }
            // normal case when no data-received and/or form-issue encountered:
            $html .= $formIssueResponse;
            if ($this->showForm) {
                // show form, either because no data-received or admin-mode:
                $html .= $this->renderFormWrapperHead();        // pfy-form-and-table-wrapper
            } else {
                // don't show form:
                $html .= $this->injectNoShowCssRule();
            }
        }
        return $html;
    } // init


    /**
     * Prerequisite: createForm() executed -> form structure ($this->formElements) is set up at this point.
     * Now render elements in chunks (defined by the name of the last element to render)
     * Keeps track via $this->lastRendered what elements have been rendered before.
     * @param string|bool $uptoWhich
     * @return string
     * @throws \Exception
     */
    public function renderFormPieces(string|bool $uptoWhich): string
    {
        $html = '';
        // formResponse is set by received data eval
        // -> can be
        //      error in data
        //      deadline expired
        //      maxCount exceeded

        // lastRendered:
        //   = false: this is the very first run -> render form head
        //   = true:  all elements have been rendered -> render form tail
        //   = int:   index of next element to be rendered
        if ($this->lastRendered === false) {
            $this->lastRendered = 0;
            if (!$this->showForm) {
                return '';
            }
            $html = $this->renderFormHead();
            $i = 0; // render elem 0 next

        } else {
            $i = $this->lastRendered + 1;
        }

        $names = array_keys($this->formElements);
        $lastElemInx = sizeof($names) - 1;

        // determine which pieces to render next:
        //      from = $i  to = $upTo
        if ($uptoWhich === true || $uptoWhich === 'rest') {
            $upTo = $lastElemInx;
            $this->lastRendered = true;

        } else {
            $upTo = array_search($uptoWhich, $names);
            if ($upTo === false) {
                throw new \Exception("Form element unknown: '$uptoWhich'");
            }
            $this->lastRendered = ($upTo === $lastElemInx)? true: $upTo;
        }

        // render elements of specified piece:  from $i to $upTo
        if ($this->showForm) {
            for (; $i <= $upTo; $i++) {
                $name = $names[$i];
                $html .= $this->renderFormElement($name);
            }
        }

        // render everything after the last form element:
        if ($this->lastRendered === true) {
            if ($this->showForm) {
                $html .= $this->renderFormTail();               //        /pfy-elems-wrapper
                                                                //      /form
                                                                //    /pfy-form-wrapper
                $html .= $this->renderDataTable();              //    pfy-table-data-output-wrapper/
                $html .= $this->renderFormTableWrapperTail();   // /pfy-form-and-table-wrapper
                $html .= $this->renderProblemWithFormBanner();  // pfy-problem-with-form-hint/
            }

            $html .= $this->injectNoSHowEnd();
            $html .= "<!-- === /pfy form widget === -->\n\n";
        }

        return $html;
    } // renderFormPieces


} // PfyFormSplitSyntax