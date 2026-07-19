<?php
namespace PgFactory\PageFactory;

use Kirby\Exception\InvalidArgumentException;
use PgFactory\PageFactoryElements\Events;

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
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => require __DIR__ . "/../src/formOptions.php",
        'summary' => <<<EOT

# $funcName()

Works exactly like `form()` macro, but renders multiple instances of scheduled forms.

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

    \{{ forms(
        file:                '\~data/db.json',
        count:               3
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

    // ownerNotificationTo synonyme for mailTo:
    if ($options['ownerNotificationTo']??false) {
        $options['mailTo'] = $options['ownerNotificationTo'];
    }
    // ownerNotificationTo synonyme for mailTo:
    if ($options['confirmationEmail']??false) {
        $options['confirmationEmailTo'] = $options['confirmationEmail'];
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

    $count = $options['count'] ?: 999;
    $eventOptions = $options['schedule'];
    $offset = ($eventOptions['offset']??false) ?: 0;
    if (!($src = $eventOptions['src']??false)) {
        if (!($src = $eventOptions['file']??false)) { // allow 'file' as synonyme for 'src'
            throw new \Exception("Form: option 'schedule' without option 'src'.");
        }
    }

    $eventOptions['file'] = $src;
    $eventOptions['count'] = $count;
    $eventOptions['macroName'] = $options['macroName'];
    $sched = new Events($eventOptions);
    $nextEvents = $sched->getNextEvents();
    $count = count($nextEvents);

    $i = 0;
    $htmlOut = '';
    while ($i++ < $count) {
        $htmlOut .= "<a id='$i'></a>\n";

        $eventOptions['offset'] = $offset++;
        $options['schedule'] = $eventOptions;

        if ($output === true) {
            // normal invocation in one junk:
            $form = new PfyForm($options);
            $htmlOut .= $form->renderForm($formFields);

        } else {
            if ($output === false) {
                $form = $GLOBALS['pfy.form'] = new PfyFormSplitSyntax($options);
                list($continue, $html) = $form->initRenderForm($formFields);
                $htmlOut .= $html;
                if ($continue) {
                    $htmlOut .= $form->renderFormWrapperHead();
                }

            } else {
                $htmlOut .= $GLOBALS['pfy.form']->renderFormPieces(uptoWhich: $output);
            }
        }
    }

    return $htmlOut;
}; // form



