<?php
namespace Usility\PageFactory;

use Usility\PageFactoryElements\Events as Events;

/*
 * PageFactory Macro (and Twig Function)
 */

function events($args = '')
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
            'templateBasename' => ['[string] Name (resp. base-name) of template variable(s).<br>'.
                'If no template is found in variables like this, alternatives including *category* and/or '.
                '*language-code* will be used, e.g. "template-en" or "template-games-de".', ''],
            'templatesFile' => ['[file-name] If set, this file is read. It must contain key:value tuples '.
                '(e.g. in YAML format). These values will be used instead of (PageFactory-)variables.', false],
            'markdown' => ['[bool] If true, result will be md-compiled.', true],
            'wrap' => ['[bool] If true, each event is wrapped in a DIV with a class applied.', true],
        ],
        'summary' => <<<EOT

# $funcName()

Access an event database, retrieves event records based on configurable criteria (e.g. category, time etc.)
and renders found data based on templates.

## Data Source

The data-source specified by ``file`` option should contain an array of records.

For the macro to work, each record needs to contain at least these fields:

- ``start``
- ``end``
- ``category``

Apart from these, records may contain any number of additional fields. They all can be used in templates 
as variables, e.g. ``\{{ start }}``. 

Variable replacement is performed by the Twig library, which
offers a variety of filters and more, see 
{{ link('https://twig.symfony.com/doc', 'Twig Documentation', target:newwin) }} for reference.

## Rolling Dates
In arguments ``from`` and ``till`` you can use letters for rolling values, e.g. "Y" for the current year.  
See {{ link('https://www.php.net/manual/de/datetime.format.php', 'PHP date()', target:newwin) }} for reference.

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

    if (!$options['file']) {
        return '';
    }

    // assemble output:
    $str .= '';

    $file = $options['file']??false;

    $ev = new Events($file, $options);
    $str .= $ev->render();

    if ($options['markdown']) {
        return [$str]; // return [$str]; if result needs to be shielded
    } else {
        return $str;
    }
} // events

