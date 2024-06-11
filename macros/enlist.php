<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Enlist;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PfyForm.php';



/*
 * PageFactory Macro (and Twig Function)
 */


return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'nSlots' =>	['[integer] Number of slots to show in the enlistment table. (default: 1)', null],
            'nReserveSlots' =>	['[integer] Number of reserve slots to show in the enlistment table. (default: 0)', null],
            'title' =>	['[string] Title of enlistment table. (default: false)', null],

            'freezeTime' =>	['[integer] The time (hours) within which a user can delete the entry. (default: false)', null],
            'deadline' =>	['[ISO-datetime|relative] The time until when people can enlist. (default: false)', null],

            'id' =>	['Id applied to the list wrapper.', false],

            'class' =>	['Class applied to the list wrapper. (default: false)', null],

            'listName' =>	['[string] Defines how the dataset will be named within the data-file. '.
                'If not set, listName will be derived from title. If that\'s not set, a default name is used.', false],

            'info' =>	['[string] Content of info-tooltip next to title. (default: false)', null],

            'ical' =>	['[string|bool] If set, a calendar icon is added to the list. Clicking on it will '.
                'download a calendar entry (.ics). The arg\'s value is used as the event SUMMARY (aka event-title). '.
                'Use placeholders to compose meaningful titles, e.g. `ical:"[XY] %title%"`, where `%title%` is the '.
                'field-name in the event record.', null],

            'icalElements' =>	['[assoc array] A comma-separated list of tuples like "`ical-arg`:`enlist-field-name`,". '.
                'Supported ical-arguments: `uniqueIdentifier`, `createdAt`, `addressName`, `coordinates`, '.
                '`attendee`, `transparent`, `fullDay`. '.
                'Example: `{description:%Comment%, address:%Location%}`.', null],

            'icalOrganiser' =>	['[string] Adds an \"organiser\" field to the iCal. The value should be an e-mail address. '.
                ' (Default: = adminMail)', null],

            'description' =>	['[string] synonyme for "info".', false],

            'editable' =>	 ['[bool] If true, users can modify their entries - as long as `freezeTime` has '.
                'not expired.', false],

            'directlyToReserve' =>	['[bool] If true, the new entry is placed in the reserved section of the list.'.
                'Thus, other users can continue filling in the normal slots.', false],

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
            'schedule' => ['{options} If defined, the Events module is invoked to determine the next event and '.
                'based on that, presets "file", "maxCount" and "minRows". '.
                'The event\'s rendered output becomes available as "%eventBanner%" to form banners.'.
                '(E.g. `formTop: "\<div>%eventBanner%\</div>"`).<br>'.
                'Moreover, all values of found event are made available to form banners as `%key%`. '.
                '(For ref see macro *events()*).', false],

            'output' =>	['[bool] If true, no output is rendered -> used to set persisent options: '.
                '[freezeTime,sendConfirmation,notifyOwner,obfuscate,admin,adminEmail,class,deadline].', true],
            'rejectRobots' => ['If true, instructions are added to the head tag to reject search-engine robots.', true],
        ],
        'summary' => <<<EOT

# $funcName()

Endlist is a tool designed for situations in which you want to organize an event and need helpers.

For each task you can define the number of people you need (as well as number of reserve helpers).

By default, the tool requests name and e-mail address for each entry. People can delete their entry later on, if 
configured accordingly. The time during which they can delete their entry can be limited, e.g. to 24 hours.

Example:
    \{{ enlist(
        nSlots: 3
        nReserveSlots: 2
        title: TITLE
        schedule:{src:'\~config/events.yaml', templatesFile: \~page/template.txt},
    ) }}

### Custom Fields
If desired, you can define custom fields which will also be presented in the list.

Example:
    \{{ enlist(
        \...
        Bring: {label:'I bring:', type:textarea}
    ) }}


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
    if ($options['adminEmail'] === null) {
        $options['adminEmail'] = PageFactory::$webmasterEmail;
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

    return $html;
}; // form



