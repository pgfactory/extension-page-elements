<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Events as Events;

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['[string] File from which to retrieve event information', false],
            'category' => ['[string] Category to use as filter.', false],
            'from' => ['[ISO-datetime] If set, defines the start of the selection window.<br>'.
                'Use letters for rolling values, e.g. "Y" for the current year.<br>'.
                '(If not set, current date will be used)', false],
            'till' => ['[ISO-datetime] If set, defines the end of the selection window.<br>'.
                'Use letters for rolling values, e.g. "Y" for the current year.<br>'.
                '(If not set, "unlimited" will be assumed)', false],
            'count' => ['[int] Defines the number of events to be rendered at most.', 1],
            'offset' => ['[int] Selects events relative to "from". "0" means next event etc. ', 0],
            'template' => ['[string|array] Text (in MarkdownPlus format) defining how data records will '.
                'be presented.<br>Inject values as \{{ key }}, where key is the name of a data element in the data record.', null],
            'rrule' => ['[string] Defition string for recurring events. Uses the RRULE syntax (see [icalendar.org](https://icalendar.org)). '.
                ' -> Use tool to assemble RRULE string: [icalendar.org/rrule-tool.html](https://icalendar.org/rrule-tool.html).', null],
            'markdown' => ['[bool] Whether result shall be markdown compiled.', false],
            'exceptions' => ['[string] Defines dates and ranges to exclude. Examples: `2024-12-24,2024-12-31` or `Y-02-15, Y-04 - Y-06` etc. '.
                'If day is omitted in ISO-Date string, the entire month is excluded.', null],
            'exceptionsFile' => ['[filename] Retrieves dates and ranges to exclude from a .txt file. '.
                'Syntax same as above, plus newlines as separators.', null],
            'startTime' => ['[string] Start time for recurring events.', null],
            'duration' => ['[string] Duration (in minutes) for recurring events.', null],
            'endTime' => ['[string]  End time for recurring events (instead of `duration`).', null],
            'eventValues' => ['[array] Additional values for rendering events -> used to replace variables in a Twig-template. '.
                'Example: `eventValues: {Topic:XY, Location: UV}`.', null],
            'timePattern' => ['[string] Alternative to all of the above: allows to render simple values, such as the '.
                'current year or month etc. Example: `timePattern: Y` or `timePattern: l` .'.
                'Values `M` (=Jan), `F` (=January), `D` (=Mon), `l` (=Monday) are translated to local language.', null],
            'wrap' => ['[bool] If false, omits the normally applied DIV wrapper, i.e. renders just as stated in the template.', true],
        ],
        'summary' => <<<EOT

# $funcName()

Accesses an event database, retrieves event records based on configurable criteria (e.g. category, time etc.)
and renders found data based on templates.

## Data Source

The data-source specified by ``file`` option should contain an array of records.

For the macro to work, each record needs to contain at least these fields:

- ``start``
- ``end``
- ``category`` (optional)

Apart from these, records may contain any number of additional fields. They all can be used in templates 
as variables, e.g. ``\{{ start }}``. 

Variable replacement is performed by Twig, TransVars or simply by string replacement. 
(decided by the "mode" option in the template file.)  
See <a href='https://twig.symfony.com/doc' target="_blank">Twig Documentation</a> for reference.

## Rolling Dates
In arguments ``from`` and ``till`` you can use letters for rolling values, e.g. "Y" for the current year.  
See <a href='https://www.php.net/manual/en/datetime.format.php' target="_blank">PHP date()</a> for reference.

In some cases, e.g. towards the end of the year, you may want to show events of the next year. 
For this use the special format `Yn`, where n is the number of days to switch earlier. 

## Variables: {:.h3}
- \{{ pfy-no-event-found }}
- \{{ pfy-no-event-template-found }}
- \{{ pfy-event-source-filetime-unknown }}

{{ vgap }}
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    if ($inx === 1) {
        PageFactory::$pg->addAssets('EVENTS');
    }

    // assemble output:
    $str .= '';

    $ev = new Events($options);
    $str .= $ev->render();

    if ($options['wrap']) {
        $str = <<<EOT

<div class="pfy-events-list pfy-events-list-$inx">
$str
</div> <!-- pfy-events-list -->

EOT;

    }

    return $str;
}; // events

