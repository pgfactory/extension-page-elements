<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro
 */

use PgFactory\PageFactoryElements\Ical;

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'events' => ['', null],
            'event' => ['', null],
            'selector' => ['', null],
            'fieldTemplates' => ['', null],
            'icalOptions' => ['', null],
            'start' => ['', null],
            'end' => ['', null],
            'title' => ['', null],
            'description' => ['', null],
            'organizer' => ['', null],
            'location' => ['', null],
            'allday' => ['', null],
            'tooltip' => ['', null],
            'cancelled' => ['', null],
            'prefix' => ['', null],
            'output' => ['', true],
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
        foreach ($auxOptions as $k => $v) {
            if (str_starts_with($k, '_anon')) {
                $options['selector'] = $v;
                unset($auxOptions[$k]);
                break;
            }
        }
        $event = $auxOptions;
    }

    if ($options['output'] === false) {
        Ical::preset($options);
        return $str;
    }

    foreach (['start', 'end', 'title', 'description', 'organizer', 'location', 'allday'] as $k) {
        if ($options[$k] ?? false) {
            $event[$k] = $options[$k];
            unset($options[$k]);
        }
    }

    if ($options['event']) {
        $options['events'] = $options['event'];
        unset($options['event']);
    }

    $iCal = new Ical([$event], $options);
    $iCal->saveToFile();
    $str .= $iCal->renderIcsLink();

    return $str;
};
