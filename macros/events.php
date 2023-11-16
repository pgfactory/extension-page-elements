<?php
namespace PgFactory\PageFactory;

use PgFactory\PageFactoryElements\Events as Events;

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
            'template' => ['[.txt file-name] File should contain MarkdownPlus defining how data records will '.
                'be presented.<br>Inject values as \{{ key }}, where key is the name of a data element in the data record.', true],
            'templatesFile' => ['[.yaml file-name] For category specific templates. '.
                'Syntax:<br>``category: |``<br>&nbsp;&nbsp;``markdown ...`` (like above, but with leading blanks)<br>'.
                'If no template is found that directly matches the *category* name, alternatives are tried:<br> '.
                '\<language> (e.g. ``en:``), \<category-language> (e.g. ``sports-de:``),<br>'.
                'If that still doesn\'t lead to a hit, the default template identified as  ``_:`` is selected.', false],
            'templateBasename' => ['[string] If defined, ``templateBasename-`` will be prepended to the *category* name '.
                'before selecting the template.', ''],
            'markdown' => ['[bool] If true, result will be md-compiled.', true],
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
- ``category``

Apart from these, records may contain any number of additional fields. They all can be used in templates 
as variables, e.g. ``\{{ start }}``. 

Variable replacement is performed by the Twig library, which
offers a variety of filters and more, see 
<a href='https://twig.symfony.com/doc' target="_blank">Twig Documentation</a> for reference.

## Rolling Dates
In arguments ``from`` and ``till`` you can use letters for rolling values, e.g. "Y" for the current year.  
See <a href='https://www.php.net/manual/en/datetime.format.php' target="_blank">PHP date()</a> for reference.

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

    if (isset($options['template'])) {
        $options['templatesFile'] = $options['template'];
    }

    // assemble output:
    $str .= '';

    $file = $options['file']??false;

    $ev = new Events($file, $options);
    $str .= $ev->render();

    if ($options['wrap']) {
        $str = <<<EOT

<div class="pfy-events-list pfy-events-list-$inx">
$str
</div> <!-- pfy-events-list -->

EOT;

    }

    if ($options['markdown']) {
        return [$str]; // return [$str]; if result needs to be shielded
    } else {
        return $str;
    }
} // events

