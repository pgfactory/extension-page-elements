<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\PageFactoryElements\Calendar;

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['File path where data shall be fetched from (and stored if in editing mode).', 'calendar.yaml'],
            'template' => ['[file] Points to file containing one (.txt) or multiple (.yaml) templates.<br>'.
                'The template is Twig-compiled and the output used as content of each event. '.
                'Inject event values via pattern `%value-name%`.<br>'.
                'In case of multiple, category decides which template to use. ', null],
            'eventTemplate' => ['Synonyme for "template"', null],
            'admin' => ['Defines, who will have administration permission, e.g. "admin".<br>'.
                'Admins can change events of any users as well as such in the past, normal users don\'t.', false],
            'edit' => ['Synonyme for "editPermission"', null],
            'editPermission' => ['[anybody|group|users] Defines, who will be able to modify calendar entries.', false],
            'modifyPermission' => ['[name(s)] If defined, logged-in users can only modify events that match given '.
                'condition., E.g. "modifyPermission:\'tom,alice\'".', null],
            'defaultEventDuration' => ['[allday|minutes] In month and year view: defines the default duration of new events.', 60],
            'class' => ['Class applied to wrapper div.', null],
            'freezePast' => ['[bool] If true, users (other than admin) cannot modify events in the past.', true],
            'categories' => ['[comma-separated-list] A list of supported categories: only events with matching '.
                'category attribute will be displayed.', false],
            'defaultView' => ['[week,month,year] Defines the initial view when the widget is presented for the very first time', ''],
            'headerLeftButtons' => ['[next,prev,today] Defines which buttons (to select views) will be displayed and in which order (ltr)', 'prev,today,next'],
            'headerRightButtons' => ['[day,week,month,year] Defines which buttons (to select views) will be displayed and in which order (ltr)', 'week,month,year'],
            'businessHours' => ['[hh:mm-hh:mm] Defines business hours (white background).', '08:00-17:00'],
            'visibleHours' => ['[hh:mm-hh:mm] Defines hours shown in week view.', '07:00-21:00'],
            'fullCalendarOptions' => ['[string] Will be passed through to the FullCalendar object (see https://fullcalendar.io/docs#toc)', ''],
            'keepDataDuration' => ['[month] Defines the time after which older events are discarded and '.
                'moved to an archive file.', 1],
            'form' => ['Definition of form fields.', null],
            'useDblClick' => ['[bool] Whether to open calendar popups on single or double clicks.', false],
//            'publish' => ['[true|filepath] If given, the calendar will be exported to designated file. The file will be place in ics/ if not specified explicitly.', false],
//            'publishCallback' => ['[string] Provide name of a script in code/ to render output for the \'description\' field of events. Script name must start with "-" (to distinguish from other types of scripts).', false],
//            'output' => ['[true|false] If false, no output will be rendered (useful in conjunction with publish).', true],
        ],
        'summary' => <<<EOT

# $funcName()

ToDo: describe purpose of function
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx, $macroName, $auxOptions) = $res;
        $str = $sourceCode;
    }
    if ($inx > 1) {
        throw new \Exception("Only 1 Calendar per page supported.");
    }

    if ($options['editPermission']??false) {
        $options['edit'] = $options['editPermission'];
    }
    $options['userCategories'] = (bool)preg_match('/category:.*?options:.*?\$\[users/', $args);
    $options['template'] = $options['template'] ?: ($options['eventTemplate']??'');

    // assemble output:
    $str .= "\n<!-- calendar -->\n";

    $cal = new Calendar($options);
    $str .= $cal->render();
    $str .= "<!-- /calendar -->\n\n";

    // if categories are defined, inject styles to show/hide associated form fields
    // (in form field def: e.g. category='public')
    if ($options['categories']??false) {
        $categories = explodeTrim(',', $options['categories'], excludeEmptyElems: true);
        $style = '';
        $style2 = '';
        foreach ($categories as $category) {
            $style .= ".pfy-for-category-$category,\n";
            $style2 .= <<<EOT
.pfy-category-$category .pfy-for-category-$category {
    display: initial;
}

EOT;

        }
        $style = rtrim($style, ",\n");
        $style = <<<EOT
$style {
    display: none;
}
$style2
EOT;
        PageFactory::$pg->addCss($style);
    }

    return $str; // return [$str]; if result needs to be shielded
};

