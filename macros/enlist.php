<?php
namespace Usility\PageFactory;

use Usility\PageFactoryElements\Enlist;

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
            'title' =>	['[string] Title of enlistment table.', false],
            'listName' =>	['[string] Defines how the dataset will be named within the data-file. '.
                'If not set, listName will be derived from title. If that\'s not set, a default name is used.', false],

            'id' =>	['Id applied to the form element.', false],

            'class' =>	['Class applied to the form element.', false],

            'info' =>	['[string] Content of info-tooltip next to title.', false],
            'description' =>	['[string] synonyme for "info".', false],
            'datetime' =>	['[ISO-datetime] When the task for enlisted people starts.', false],
            'deadline' =>	['[ISO-datetime|relative] The time until when people can enlist.<br>'.
                'Can be an ISO-datetime (e.g. 2023-06-12T1159) or an expression relative to "datetime" (e.g. "-1day").', false],
            'nSlots' =>	['[integer] Number of slots to show in the enlistment table.', 1],
            'nReserveSlots' =>	['[integer] Number of reserve slots to show in the enlistment table.', 0],
            'sendConfirmation' =>	['[bool] If true, a confirmation mail is sent to the address stated when '.
                'somebody enlists.', false],
            'freezeTime' =>	['[integer] The time (hours) within which a user can delete the entry.', false],
            'notifyOwner' =>	['[email] If set, a notification mail will be sent to this address when '.
                'somebody makes a an entry/modification to the list.', false],
            'obfuscate' =>	['[false|placeholder|initials] If true, placeholders are shown for existing entries '.
                'instead of the names. If "initials", the names initials are shown.', false],
            'file' =>	['[filename] Name of the data file in which to store entries.', false],
            'admin' =>	['[bool|permissionQuery] Defines who may administrate the enlistment.', true],
            'adminEmail' => ['[string] The enlist admin\'s email address. Used when creating an email to '.
                'enlisted people.', false],
            'adminMail' =>	['[string] Synonyme for adminEmail.', false],
        ],
        'summary' => <<<EOT

# $funcName()


{{ vgap }}

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
    unset($options['adminMail']);

    $enlist = new Enlist($options, $auxOptions);
    $html .= $enlist->render();

    if ($inx === 1) {
        $html .= $enlist->renderForm();
        PageFactory::$pg->addAssets('ENLIST');
        PageFactory::$pg->addAssets('POPUPS');
    }

    return [$html];
} // form



