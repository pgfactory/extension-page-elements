<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Enlist;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PfyForm.php';



/*
 * PageFactory Macro
 */


return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'nSlots' =>	['[integer] Number of slots to show in the enlistment table. (default: 1)', 1],
            'nReserveSlots' =>	['[integer] Number of reserve slots to show in the enlistment table. (default: 0)', 0],
            'title' =>	['[string] Title of enlistment table. (default: false)', null],
            'listId' =>	['[string] Key that identifies data in the dB. Can be used in case the title or position of '.
                'this list needs to be modified while in use. (default: false)', null],

            'freezeTime' =>	['[integer] The time (hours) within which a user can delete the entry. (default: false)', null],
            'deadline' =>	['[ISO-datetime|relative] The time until when people can enlist. (default: false)', null],

            'id' =>	['Id applied to the list wrapper.', false],

            'class' =>	['Class applied to the list wrapper. (default: false)', null],

            'info' =>	['[string] Content of info-tooltip next to title. (default: false)', null],

            'placeholder' => ['Placeholder shown as long as the field is empty.', null],

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
                'To customize, define variables ``pfy-enlist-activated-visitor-confirmation-subject`` and '.
                '``pfy-enlist-activated-visitor-confirmation-message``. (default: false)', null],

            'obfuscate' =>	['[false|placeholder|initials] If true, placeholders are shown for existing entries '.
                'instead of the names. If "initials", the names initials are shown. (default: false)', null],
            'file' =>	['[filename] Name of the data file in which to store entries. (default: false)', null],
            'admin' =>	['[bool|permissionQuery] Defines who may administrate the enlistment. Default is "true", which '.
                'means "loggedin|localhost". (default: true)', true],
            'adminEmail' => ['[string] The enlist admin\'s email address. Used when creating an email to '.
                'enlisted people. (default: false)', null],
            'adminMail' =>	['[string] Synonyme for adminEmail.', null],
            'schedule' => ['{options} If defined, the Events module is invoked to determine the next event and '.
                'based on that, presets "file", "maxCount" and "minRows". '.
                'The event\'s rendered output becomes available as "%eventBanner%" to form banners.'.
                '(E.g. `formTop: "\<div>%eventBanner%\</div>"`).<br>'.
                'Moreover, all values of found event are made available to form banners as `%key%`. '.
                '(For ref see macro *events()*).', false],

            'ical' =>	['[bool|string|array] If set (and using "schedule"), a calendar icon is added to the list. Clicking on it will '.
                'download a calendar entry (.ics). The arg\'s value is used as the event SUMMARY (aka event-title). '.
                'Use placeholders to compose meaningful titles, e.g. `ical:"[XY] %title%"`, where `%title%` is the '.
                'field-name in the event record.', null],

            'tableOptions' =>	['[assoc array] Options for table rendering.', null],

            'listName' =>	['[string] For legacy compatibility.', null],

            'emailFromName' =>	['[string] Name used in notification and confirmation mails.', null],

            'output' =>	['[bool] If false, no output is rendered (can be useful to set defaults.', true],

            'setDefaults' => ['[bool] If true, sets persistent options: '.
                '[freezeTime,sendConfirmation,notifyOwner,obfuscate,admin,adminEmail,class,deadline].'.
                'Thus, subsequent instances of enlist() may omit these options.', true],

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
        schedule:{src:'\~config/events.json', templatesFile: \~page/template.txt},
    ) }}

### Custom Fields
If desired, you can define custom fields which will also be presented in the list.

Example:
    \{{ enlist(
        \...
        Bring: {label:'I bring:', type:textarea}
    ) }}


### Defining Persistent Options

If you need multiple enlistment fields with common options, you can define them:

-> use option ``setDefaults: true``.

Persistent options:  
freezeTime, sendConfirmation, notifyOwner, obfuscate, admin, adminEmail, class, deadline

Example:
    \{{ enlist(freezeTime:2, setDefaults: true) }}

### Using Schedule and iCal Options

Example:
    ical: {
        shortName:      '[XY]'
        title:          'XY Event Support'
        location:       'LOCATION'
        description:    'DESCRIPTION ... %topic%'
        organizer:      'ORGANIZER'
    }

    schedule:  { 
        src: '\~config/events.json', 
        category: 'EVENT-CATEGORY', 
        template:{
            file:'\~page/template.txt', 
        }
        count:2
    },

Template File "template.txt" (used for rendering list header):

    \*\{{ start|intlDate("D, d.F Y") }} {{ start|date("H.i") }}\*

### Notification Email Templates (sent to webmaster):
    pfy-enlist-add-notification-subject:
    pfy-enlist-add-notification-message:
    pfy-enlist-del-notification-subject:
    pfy-enlist-del-notification-message:
    pfy-enlist-activated-notification-subject:
    pfy-enlist-activated-notification-message:
    pfy-enlist-del-activated-notification-subject:
    pfy-enlist-del-activated-notification-message:
    pfy-enlist-collapse-notification-subject:
    pfy-enlist-collapse-notification-message:

### Confirmation Email Templates (sent to enlisted people):
    pfy-enlist-add-visitor-confirmation-subject:
    pfy-enlist-add-visitor-confirmation-message:
    pfy-enlist-del-visitor-confirmation-subject:
    pfy-enlist-del-visitor-confirmation-message:
    pfy-enlist-activated-visitor-confirmation-subject:
    pfy-enlist-activated-visitor-confirmation-message:

Alternative: 'pfy-enlist-subject' overrides all subjects above.

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
    unset($options['adminMail']);
    unset($options['inx']);

    $enlist = new Enlist($options, $auxOptions);

    if ($options['output']) {
        $html .= $enlist->render();
    }

    return $html;
}; // enlist



