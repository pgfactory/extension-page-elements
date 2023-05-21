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

            'action' =>	['Argument applied to the form element\'s "action"-attribute.', false],

            'mailTo' =>	['If set, an email will be sent to this address each time the form is filled in.', false],

            'mailFrom' =>	['The address from which above email is sent. (default: "{{ webmaster_email }}").', false],
            'mailFromName' =>	['Name from which above email is sent. (default: "").', false],

            'formTop' =>	['Text rendered above the form. Will be hidden upon successful completion of form entry.', false],

            'formHint' =>	['Text rendered above the form buttons. Will be hidden upon successful completion of form entry.', false],

            'formBottom' =>	['Text rendered below the form buttons. Will be hidden upon successful completion of form entry.', false],

            'deadline' =>	['(ISO-date) If set, the form will be disabled after deadline has passed.', false],

            'deadlineNotice' =>	['(string) Defines the response displayed when deadline has passed.', false],

            'maxCount' =>	['If set, the number of sign-ups will be limited.', false],

            'maxCountOn' =>	['If maxCount is set, identifies the field to use for counting sign-ups.', false],

            'customResponseEvaluation' =>	['Name of a PHP function to be called when user submitted form data.', false],

            'confirmationText' =>	['The text rendered upon successful completion of a form entry.', false],

            'file' =>	['File where to store data submitted by users. E.g. "&#126;data/form.yaml"', false],

            'formTimeout' =>	['Defines the max time a user can wait between opening the form and '.
                'submitting it. (Default:false)', false],

            'avoidDuplicates' =>	['If true, Lizzy checks whether identical data-rec already '.
                'exists in DB. If so, skips storing new rec. (default: true).', true],

            'antiSpam' =>	['[false,field-ID] If true, an invisible "honey-pot" field is added '.
                'to the form. Spam-attacks typically try to fill in data and thus can be identified on the server. '.
                'To use, provide the id of the input field that accepts "last-name" input. The user can then override the '.
                'mechanism by re-entering his/her last name in UPPERCASE letters. You could use any other input field,  '.
                'but need to modify the text resource "lzy-form-override-honeypot" accordingly. (default: false)', false],

            'showData' =>	['[false, true, loggedIn] '.
                'Defines, to whom previously received data is presented (Default: false).', false],

            'tableFooters' =>	['(recId:\'%sum\' or \'%count\' or \'string\') '.
                'Adds a footer row to the table showing counts and sums for specified columns.  (Default: false).', false],

            'confirmationEmail' =>	['[name-of-email-field] If set to the name of an '.
                'e-mail field within the form, a confirmation mail will be sent (Default: false).', false],

            'confirmationEmailTemplate' =>	['[name-of-template-file,true] This defines '.
                'what to put into the mail. If true, standard variables will be used: "lzy-confirmation-response-subject" '.
                'and "lzy-confirmation-response-message". Alternatively, you can specify the name of a template file. '.
                'All form-inputs are available as variables of the form "&#123;&#123; <strong>var_name</strong>_value }}" '.
                '(Default: false).', false],

//            'showDataMinRows' =>	['[integer] If defined, the "showData" table is filled with '.
//                'empty rows up to given number. (Default: false)', false],

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
if ($inx !== 1) {
    throw new \Exception("Forms: multiple instances not possible yet.");
}
    $options['formInx'] = $inx;
    $form = new PfyForm($options);
    $form->createForm(null, $auxOptions);

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


