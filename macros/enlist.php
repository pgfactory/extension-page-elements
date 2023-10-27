<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Enlist;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PfyForm.php';



/*
 * PageFactory Macro (and Twig Function)
 */


function enlist($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'title' =>	['[string] Title of enlistment table. (default: false)', null],
            'nSlots' =>	['[integer] Number of slots to show in the enlistment table. (default: 1)', null],
            'nReserveSlots' =>	['[integer] Number of reserve slots to show in the enlistment table. (default: 0)', null],

            'freezeTime' =>	['[integer] The time (hours) within which a user can delete the entry. (default: false)', null],
            'deadline' =>	['[ISO-datetime|relative] The time until when people can enlist. (default: false)', null],

            'id' =>	['Id applied to the list wrapper.', false],

            'class' =>	['Class applied to the list wrapper. (default: false)', null],

            'listName' =>	['[string] Defines how the dataset will be named within the data-file. '.
                'If not set, listName will be derived from title. If that\'s not set, a default name is used.', false],

            'info' =>	['[string] Content of info-tooltip next to title. (default: false)', null],
            'description' =>	['[string] synonyme for "info".', false],

            'sendConfirmation' =>	['[bool] If true, a confirmation mail is sent to the address stated when '.
                'somebody enlists. (default: false)', null],
            'notifyOwner' =>	['[email] If set, a notification mail will be sent to this address when '.
                'somebody makes a an entry/modification to the list. (default: false)', null],
            'notifyActivatedReserve' =>	['[bool] If true, a notification mail is sent to the person '.
                'who becomes active from a reserve position after a position ahead of that has been deleted. '.
                'To customize, define variables ``pfy-enlist-notify-activated-reserve-subject`` and '.
                '``pfy-enlist-notify-activated-reserve-subject``. (default: false)', null],

            'obfuscate' =>	['[false|placeholder|initials] If true, placeholders are shown for existing entries '.
                'instead of the names. If "initials", the names initials are shown. (default: false)', null],
            'file' =>	['[filename] Name of the data file in which to store entries. (default: false)', null],
            'admin' =>	['[bool|permissionQuery] Defines who may administrate the enlistment. Default is "true", which '.
                'means "loggedin|localhost". (default: true)', null],
            'adminEmail' => ['[string] The enlist admin\'s email address. Used when creating an email to '.
                'enlisted people. (default: false)', null],
            'adminMail' =>	['[string] Synonyme for adminEmail.', null],
            'output' =>	['[bool] If true, no output is rendered -> used to set persisent options: '.
                '[freezeTime,sendConfirmation,notifyOwner,obfuscate,admin,adminEmail,class,deadline].', true],
        ],
        'summary' => <<<EOT

# $funcName()

Endlist is a tool designed for situations in which you want to organize an event and need helpers.

For each task you can define the number of people you need (as well as number of reserve helpers).

By default, the tool requests name and e-mail addres for each entry. People can delete their entry later on, if 
configured accordingly. The time during which they can delete their entry can be limited, e.g. to 24 hours.

If desired, you can define custom fields which will also be presented in the list.

### Presetting Persistent Options

If you need multiple enlistment fields with common options, you can preset them via a special call of the macro.

-> use option ``output: false``.

Persistent options:  
freezeTime, sendConfirmation, notifyOwner, obfuscate, admin, adminEmail, class, deadline

Example:
    \{{ enlist(freezeTime:2, output:false) }}

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return shieldStr($res);
    } else {
        list($options, $sourceCode, $inx, $macroName, $auxOptions) = $res;
        $html = $sourceCode;
    }

    // adminMail synonyme for adminEmail:
    if ($options['adminMail']) {
        $options['adminEmail'] = $options['adminMail'];
    }
    // file -> remove extension if contained:
    if ($options['file'] && (fileExt($options['file']))) {
        $options['file'] = fileExt($options['file'], true);
    }
    unset($options['adminMail']);
    unset($options['inx']);

    $enlist = new Enlist($options, $auxOptions);
    if ($options['output']) {
        $html .= $enlist->render();
    }

    if ($inx === 1) {
        $html .= $enlist->renderForm();
        PageFactory::$pg->addAssets('ENLIST');
        PageFactory::$pg->addAssets('POPUPS');

        PageFactory::$pg->applyRobotsAttrib();
    }

    return [$html];
} // form



