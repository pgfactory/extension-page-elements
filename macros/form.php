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
            'formName' =>	['(string) formName.', false],

            'id' =>	['Id applied to the form element. If not supplied it will be derived from argument "formName".', false],

            'class' =>	['Class applied to the form element.', false],

//            'method' =>	['[post|get] Argument applied to the form element\'s "method"-attribute.', false],

            'action' =>	['Argument applied to the form element\'s "action"-attribute.', false],

            'mailTo' =>	['If set, an email will be sent to this address each time the form is filled in.', false],

            'mailFrom' =>	['The address from which above email is sent. (default: "{{ webmaster_email }}").', false],
            'mailFromName' =>	['Name from which above email is sent. (default: "{{ webmaster_email }}").', false],

            'formHeader' =>	['Text rendered above the form. Will be hidden upon successful completion of form entry.', false],

            'formHint' =>	['Text rendered above the form buttons. Will be hidden upon successful completion of form entry.', false],

            'formFooter' =>	['Text rendered below the form buttons. Will be hidden upon successful completion of form entry.', false],

            'customResponseEvaluation' =>	['Name of a PHP function to be called when user submitted form data.', false],

            'next' =>	['When a user successfully submits a form, "confirmationText" will be output. '.
                'Default contains a "continue..." link, whose address is defined by this argument.', false],

            'confirmationText' =>	['The text rendered upon completion of a form entry.', false],
            'file' =>	['File where to store data submitted by users. E.g. "&#126;data/form.yaml"', false],
            'formDataCaching' =>	['If the user enters data into the form an leaves the page,  '.
                ' without submitting, values will be cached on the server and prefilled upon returning to the page. '.
                'This can be disabled by setting this option to false (Default: true).', true],

            'options' =>	['[nocolor,validate,norequiredcomment] "nocolor" disables default coloring '.
                'of form elements; "validate" enables form validation by browser; "norequiredcomment" suppresses the explation of *=required', false],

            'labelWidth' =>	['[width incl. unit] Defines the default label width, e.g. "8em" (default: 6em)',  'auto'],

            'labelPosition' =>	['[left,above,auto] Defines where field labels are positioned (default: auto)',  'auto'],
            'labelColons' =>	['[true,false] Defines whether to put a colon after each label (default: leave as is; false: suppress even if contained in label string)', false],

//            'translateLabels' =>	['If true, Lizzy will try to translate all labels in this form (default: false)', false],

            'formTimeout' =>	['Defines the max time a user can wait between opening the form and '.
                'submitting it. (Default:false)', false],

            'avoidDuplicates' =>	['If true, Lizzy checks whether identical data-rec already '.
                'exists in DB. If so, skips storing new rec. (default: true).', true],

//            'prefill' =>	['[hash,url-arg] Hash corresponds to the key in the form-DB, i.e. where '.
//                'previous form entries are stored. The "prefill" arguments lets you render the form prefilled with an '.
//                'existing form-data-record. Hash can either be applied directly in this argument or indirectly via an '.
//                'URL-argument of given name. E.g. ?key=ABCDEF.', false],

            //$this->getArg($macroName' =>	['preventMultipleSubmit',  'If true and if a user has started entering data, he/she '.
            //    'will be warned when trying to leave the page without submiting the form. Default: true.', true],

            'replaceQuotes' =>	['If true, quote characters (\' and ") contained in user\'s '.
                'entries will be converted to lookalikes, which cannot interfere with data-file-formats (yaml, json, csv). Default: true.', true],

            'antiSpam' =>	['[false,field-ID] If true, an invisible "honey-pot" field is added '.
                'to the form. Spam-attacks typically try to fill in data and thus can be identified on the server. '.
                'To use, provide the id of the input field that accepts "last-name" input. The user can then override the '.
                'mechanism by re-entering his/her last name in UPPERCASE letters. You could use any other input field,  '.
                'but need to modify the text resource "lzy-form-override-honeypot" accordingly. (default: false)', false],

            'validate' =>	['If true, the browser\'s validation mechanism is activated,  '.
                'e.g. checking for required fields and compliance with field types. Note: this may conflict with '.
                'the "requiredGroup" mechanism. (default: false)', false],

            'showData' =>	['[false, true, loggedIn, privileged, localhost, {group}] '.
                'Defines, to whom previously received data is presented (Default: false).', false],

            'tableFooters' =>	['(recId:\'%sum\' or \'%count\' or \'string\') '.
                'Defines,  (Default: false).', false],

            'confirmationEmail' =>	['[name-of-email-field] If set to the name of an '.
                'e-mail field within the form, a confirmation mail will be sent (Default: false).', false],

            'confirmationEmailTemplate' =>	['[name-of-template-file,true] This defines '.
                'what to put into the mail. If true, standard variables will be used: "lzy-confirmation-response-subject" '.
                'and "lzy-confirmation-response-message". Alternatively, you can specify the name of a template file. '.
                'All form-inputs are available as variables of the form "&#123;&#123; <strong>var_name</strong>_value }}" '.
                '(Default: false).', false],

            'showDataMinRows' =>	['[integer] If defined, the "showData" table is filled with '.
                'empty rows up to given number. (Default: false)', false],

//            'encapsulate' =>	['If true, activates Lizzy\'s widget encapsulation scheme '.
//                '(i.e. adds class "lzy-encapsulated" to the form element).', false],
//
//            'responseViaSideChannels' =>	['If true, responses to user inputs will be shown in popups. '.
//                'Otherwise, they are presented in the page.', false],
//
//            'exportStructure' =>	['If true, Lizzy exports the datastructure corresponding to '.
//                'given form data to file "filename_structure.yaml". This is needed when outputting data as a table. '.
//                '(Default: true)', false],
        ],
        'summary' => <<<EOT

# $funcName()

Supported field types:

- text
- textarea
- ...

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx, $macroName, $auxOptions) = $res;
        $html = $sourceCode;
    }

    $headKeys = ['formName','mailfrom','mailto','formHeader','formHint','formFooter','showData','antiSpam',
        'id','class','method','action','mailTo','mailFrom','customResponseEvaluation','next','confirmationText',
        'file','formDataCaching','options','labelWidth','labelPosition','labelColons','formTimeout',
        'avoidDuplicates','replaceQuotes','validate','confirmationEmail','confirmationEmailTemplate','showDataMinRows',
        'tableFooters'];

    $form = new PfyForm($options);
    $form->createForm(null, $auxOptions);

    if ($form->isSuccess()) {
        $html .= $form->handleReceivedData();
    } else {
        $html = $form->renderForm($options, $auxOptions);
    }

    $html = TransVars::translate($html);
    $html = shieldStr($html, 'inline');
    return $html;
} // form




