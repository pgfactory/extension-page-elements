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
            'fieldTemplates' => ['(array of event-values) Defines the match between event data-elements and iCal fields.<br>'.
                'Available iCal fields: [start, end, title, description, organizer, location, allday, cancelled].<br>'.
                'Field definitions may be name-of-event-field or a pattern, like "X %title% Y"', null],
            'templateSelector' => ['(optional string) fieldTemplates may be an array of fieldTemplates. In this case '.
                'templateSelector selects the desired template.', null],

            // directly provided event info:
            'start' => ['(ISO date-time) Defines the start of the event, date and time (unless allday).', null],
            'end' => ['(ISO date-time) Defines the end of the event.', null],
            'title' => ['(string) Defines the title or summary of the event.', null],
            'description' => ['(string) Optional description of the event.', null],
            'location' => ['(string) Defeines the location of the event.', null],
            'organizer' => ['(optional email) If provided, must be an e-mail address associated with the event organizer.', null],
            'allday' => ['(true|false) If true, an allday event is rendered.', false],
            'cancelled' => ['(true|false) If true, the event\'s status field is set to "cancelled".', false],

            // alternative to directly provided event(s):
            'event' => ['(array) Provide event as array {start, end,etc.}.', null],
            'events' => ['(array)  Provide as array of events { {start, end,etc.}, {.\..}..\. }', null],
            'eventSelector' => ['(first or index or all) Defines which events to select.', 'first'],

            // output options:
            'output' => ['[false|icon|buttom|link|file|filepath|true] Defines what is rendered.<br>'.
                '("file" renders the ICS file for debugging.)', 'icon'],
            'linkText' => ['(string) If rendered as a link, this text is used.', '{{ pfy-ical-link-text }}'],
            'tooltip' => ['(string) If rendered as a button, this tooltip is added.', '{{ pfy-ical-link-tooltip }}'],

            // ics file options:
            'path' => ['(string) Lets you override the location of ics files. '.
                '(default: "\~/media/pgfactory/ical/".', null],
            'filename' => ['(string) Defines the ics file name.', null],
            'filePrefix' => ['(string) Defines an optional string to be prepended to ics file names.', null],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a access (icon, link or button) to an ICS file, which users can import to their own agenda.

	\{{ ical(
		fieldTemplates: {
			_:  {
				'start': '%start%'
				'end': '%end%'
				'title': '%Thema%'
				'location': '%Ort%'
				'description': '%Beschreibung%'
				'organizer': 'Computeria Meilen'
			}
			2: {
				'start': '%start2%'
				'end': '%end2%'
				'title': '%Thema%'
				'location': '%Ort%'
				'description': '%Beschreibung%'
				'organizer': 'Computeria Meilen'
			}
		}
		title: 'Testeintrag 1.Mai'
		events: {
			0: {
			start: '2026-05-01 19:15'
			end: '2026-05-01 21:00'
			}
			1: {
			start: '2026-05-02 09:15'
			end: '2026-05-02 11:00'
			}
		}
		output: false \// just preset options for the following invocations
	) }}
	
    As Icon:    >> \{{ ical() }}
    As Link:    >> \{{ ical(output:link) }}
    As Button:  >> \{{ ical(output:button) }}
	
	File:       >>  \{{ ical(output:filepath) }}
	
	\{{ ical(output:file) }}


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
        $events = [$auxOptions];
    }

    // handle output [false|file|filepath]:
    if ($options['output'] === false) {
        Ical::preset($options);
        return $str;

    } elseif ($options['output'] === 'file') {
        $f = Ical::getLastFilepath();
        return $str.Import::render([
            'file' => $f,
            'pre' => true,
        ]);

    } elseif ($options['output'] === 'filepath') {
        $f = Ical::getLastFilepath();
        return $str.str_replace('~', '&#126;', $f);

    } elseif ($options['output'] === true) {
        $options['output'] = 'icon';
    }

    // fix events args:
    if (($options['events'] ?? false) && !($options['events']['file'] ?? false)) {
        $events += $options['events'];
        $options['events'] = null;
    }
    if ($options['event'] ?? false) {
        $events[0] += $options['event'];
        $options['event'] = null;
    }

    // move event related args into events[] arg:
    foreach (['start', 'end', 'title', 'description', 'organizer', 'location', 'allday'] as $k) {
        if (!($ev ?? false)) {
            // first run -> check/fix that events is array of events:
            if (isset($events['start'])) {
                $tmp = $events['start'];
                $events = [];
                $events[0] = $tmp;
            }
            $ev = &$events[0];
        }
        if ($options[$k] ?? false) {
            $ev[$k] = $options[$k];
            unset($options[$k]);
        }
    }

    $iCal = new Ical($events, $options);
    $iCal->saveToFile();

    // render depending on output options icon|buttom|link:
    if ($options['output'] === 'icon') {
        $icon = $iCal->renderIcalIcon();
        $str .= <<<EOT
<span class='pfy-ical-icon'>$icon</span>
EOT;

    } elseif ($options['output'] === 'button') {
        $button = $iCal->renderIcsLink(asButton: true);
        $str .= <<<EOT
<span class='pfy-ical-button'>$button</span>
EOT;

    } elseif ($options['output'] === 'link') {
        $link = $iCal->renderIcsLink();
        $str .= <<<EOT
<span class='pfy-ical-link'>$link</span>
EOT;
    }

    return $str;
};
