<?php

namespace PgFactory\PageFactoryElements;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use DateTime;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\writeFile;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\translateToFilename;

const ICAL_DOWNLOAD_PATH = '~/media/pgfactory/ical/';


class Ical
{
    /**
     *  https://github.com/spatie/icalendar-generator
     * @return string
     * @throws \Exception
     */
    public static function render(array $rec, array $options = []): string
    {
        $ics = self::createICalRecord($rec, $options);
        $shortName = $path = $options['shortName']??'';
        if ($shortName) {
            $path = translateToFilename($shortName, false).'/';
            $shortName = $shortName.'_';
        }

        $start  = $rec['start']??'';
        $date = date('Y-m-d\THi', strtotime($start));
        $filename = $shortName.$date.'.ics';
        $file = ICAL_DOWNLOAD_PATH . $path . $filename;
        $filePath = resolvePath($file);
        if (($options['saveToFile']??true) && !file_exists($filePath)) {
            preparePath($filePath, 0755);
            writeFile($filePath, $ics, 0710);
        }
        $url = Utils::resolveUrls($file, true);

        $calIcon = ENLIST_CALENDAR_ICON;
        $iCal = <<<EOT

<div class='pfy-enlist-ical-wrapper'>
<a href="$url" download="$filename" title="{{ pfy-enlist-ical-tooltip }}">$calIcon</a>
</div>

EOT;

        return $iCal;
    } // renderICal


    /**
     * @param array $rec
     * @return string
     * @throws \Exception
     */
    private static function createICalRecord(array $rec, array $iCalArgs): string
    {
        if (is_array($iCalArgs)) {
            $iCalArgs += ICAL_DEFAULT_OPTIONS;
        } elseif (is_string($iCalArgs)) {
            $iCalArgs = ICAL_DEFAULT_OPTIONS;
            $iCalArgs['title'] = $iCalArgs;
        }

        $icalOptions = [
            'start'         => $rec['start'],
            'end'           => $rec['end'],
            'title'         => self::compileICalElement($iCalArgs['title'], $rec),
            'location'      => self::compileICalElement($iCalArgs['location'], $rec),
            'description'   => self::compileICalElement($iCalArgs['description'], $rec),
            'organizer'     => self::compileICalElement($iCalArgs['organizer'], $rec),
            'status'        => self::compileICalElement($iCalArgs['status'], $rec),
            'fullDay'       => self::compileICalElement($iCalArgs['fullDay'], $rec),
            'uniqueIdentifier' => self::compileICalElement($iCalArgs['uniqueIdentifier'], $rec),
        ];
        $cal = self::compileICalEvent($icalOptions);
        return $cal;
    } // createICalRecord


    /**
     * @param string $fieldValue
     * @param array $rec
     * @return string
     */
    private static function compileICalElement(string $fieldValue, array $rec): string
    {
        // replace %placeholders% with values from current rec:
        while (preg_match('/%(.{2,20}?)%/', $fieldValue, $m)) {
            // check current rec for matching field:
            if (isset($rec[$m[1]])) {
                $value = $rec[$m[1]]??'';
            } else {
                // if not found, check PFY variables:
                $value = TransVars::getVariable($m[1]);
            }
            if (!is_string($value)) {
                $value = '';
            }
            $fieldValue = str_replace($m[0], $value, $fieldValue);
        }
        return $fieldValue;
    } // compileICalElement


    /**
     * @param array $iCalOptions
     * @return string
     * @throws \DateMalformedStringException
     */
    public static function compileICalEvent(array $iCalOptions): string
    {
        $event = Event::create($iCalOptions['title']);
        $event->startsAt(new DateTime($iCalOptions['start']));
        $event->endsAt(new DateTime($iCalOptions['end']));

        if ($description = ($iCalOptions['description']??false)) {
            $event->description($description);
        }
        if ($organiser = ($iCalOptions['organiser']??false)) {
            $event->organizer($organiser);
        }
        if ($location = ($iCalOptions['location']??false)) {
            $event->address($location);
        }
        if ($iCalOptions['fullDay']??false) {
            $event->fullDay();
        }
        if ($uniqueIdentifier = ($iCalOptions['uniqueIdentifier']??false)) {
            $event->uniqueIdentifier($uniqueIdentifier);
        }
        if ($status = ($iCalOptions['status']??false)) {
            $event->status($status);
        }

        $cal = Calendar::create();
        $cal->event($event);
        return $cal->get();
    } // compileICalEvent

} // Ical