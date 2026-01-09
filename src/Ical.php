<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\MarkdownPlus\MarkdownPlus;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use DateTime;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\base_name;
use function PgFactory\PageFactory\dir_name;
use function PgFactory\PageFactory\fileTime;
use function PgFactory\PageFactory\writeFile;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\translateToFilename;

const ICAL_DOWNLOAD_PATH = PFY_TEMP_PATH.'ical/';
const ICAL_DEFAULT_OPTIONS = [
    'title' => '',
    'location' => '',
    'description' => '',
    'organizer' => '',
    'status' => '',
    'fullDay' => false,
    'uniqueIdentifier' => '',
];
const ICAL_CALENDAR_ICON  = ':calendar_date:';
const ICAL_UNIQUE_IDENTIFIER_PREFIX = 'pgfactory_';


class Ical
{
    private array $events;
    private array $options;
    private Calendar $icalObj;
    private string $path;
    private string $filename;
    private string $targetFilePath;
    private string $targetFileUrl;
    private string|null $iCalStr = null;

    /**
     * @param array $events
     * @param array $options
     */
    public function __construct(array $events, array $options)
    {
        $this->events = $events;
        $this->options = $this->parseOptions($options);
    } // __construct


    /**
     * @return int
     */
    public function getTargetFileTime(): int
    {
        return fileTime($this->targetFilePath);
    } // getTargetFileTime


    /**
     * @return string
     */
    public function getTargetFile(): string
    {
        return $this->targetFilePath;
    } // getTargetFile


    public function getTargetUrl(): string
    {
        return $this->targetFileUrl;
    } // getTargetUrl


    /**
     * @return void
     * @throws \Exception
     */
    public function saveToFile(): void
    {
        $icsStr = $this->renderICalStr();
        $filePath = $this->targetFilePath;
        preparePath($filePath, 0755);
        writeFile($filePath, $icsStr, permissions: 0644);
    } // saveToFile


    /**
     * @return string
     */
    public function renderIcsLink(): string
    {
        $url = $this->targetFileUrl;
        $asButton = $this->options['asButton'] ?? false;
        $tooltip = $this->options['tooltip']??'';
        $linkText = ($this->options['linkText']??null);
        if ($linkText === null) {
            $linkText = '{{ pfy-ical-link-text }}';
        }
        if ($linkText) {
            $linkText = TransVars::translate($linkText);
        }
        $calIcon = ($this->options['icon']??'') ?: ICAL_CALENDAR_ICON;
        $linkText = str_replace('%icon%', $calIcon, $linkText);
        $mdp = new MarkdownPlus();
        $linkText = $mdp->compileParagraph($linkText);
        if ($asButton) {
            $link = "<button class='pfy-enlist-ical-button pfy-button pfy-button-lean' title='$tooltip' type='button'>$linkText</button>";
            $link .= "<a href='$url' download='$this->filename' class='pfy-dispno'>$linkText</a>";
        } else {
            $link = "<a href='$url' download='$this->filename' title='$tooltip'>$linkText</a>";
        }
        return $link;
    } // renderIcsLink


    /**
     * @param array $rec
     * @return string
     * @throws \Exception
     */
    private function renderICalStr(): string
    {
        if ($this->iCalStr) {
            return $this->iCalStr;
        }

        $recs = $this->events;
        $this->icalObj = Calendar::create();
        foreach ($recs as $rec) {
            $this->compileICalRec($rec);
        }
        $this->iCalStr = $isc = $this->icalObj->get();
        return $isc;
    } // renderICalStr


    /**
     * @param array $rec
     * @return void
     */
    private function compileICalRec(array $rec): void
    {
        $icalElements = $this->popupateICalElements($rec);
        $this->addICalEvent($icalElements);
    } // compileICalRec


    /**
     * @param array $rec
     * @return array
     */
    private function popupateICalElements(array $rec): array
    {
        if ($rec['allday']??false) {
            $rec['start'] = substr($rec['start'], 0, 10);
                if (isset($rec['end'])) {
                        $rec['end'] = date('Y-m-d', strtotime('+1 day', strtotime($rec['end'])));
                } else {
                    $rec['end'] = date('Y-m-d', strtotime('+1 day', strtotime($rec['start'])));
                }
        }
        $icalElements = [
            'start'         => $rec['start']??'',
            'end'           => $rec['end']??'',
            'title'         => $this->compileICalElement('title', $rec),
            'location'      => $this->compileICalElement('location', $rec),
            'description'   => $this->compileICalElement('description', $rec),
            'organizer'     => $this->compileICalElement('organizer', $rec),
            'status'        => $this->compileICalElement('status', $rec),
            'fullDay'       => $this->compileICalElement('allday', $rec),
        ];
        $uniqueIdentifier = $rec['_reckey']??'';
        if ($uniqueIdentifier) {
            $icalElements['uniqueIdentifier'] = ICAL_UNIQUE_IDENTIFIER_PREFIX.$uniqueIdentifier;
        }
        return $icalElements;
    } // popupateICalElements


    /**
     * @param string $fieldName
     * @param array $rec
     * @return string
     */
    private function compileICalElement(string $fieldName, array $rec): string|bool
    {
        if (isset($rec[$fieldName])) {
            if ($fieldName === 'allday') {
                return ($rec[$fieldName] !== 'false');
            } else {
                return $rec[$fieldName];
            }
        }
        $fieldValue = $this->options[$fieldName]??'';
        if (!$fieldValue) {
            return '';
        }

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
     * @param array $icalElements
     * @return void
     * @throws \Exception
     */
    private function addICalEvent(array $icalElements): void
    {
        $event = Event::create($icalElements['title']);
        $event->startsAt(new DateTime($icalElements['start']));
        $event->endsAt(new DateTime($icalElements['end']));

        if ($description = ($icalElements['description']??false)) {
            $event->description($description);
        }
        if ($organiser = ($icalElements['organiser']??false)) {
            $event->organizer($organiser);
        }
        if ($location = ($icalElements['location']??false)) {
            $event->address($location);
        }
        if ($icalElements['fullDay']??false) {
            $event->fullDay();
        }
        if ($uniqueIdentifier = ($icalElements['uniqueIdentifier']??false)) {
            $event->uniqueIdentifier($uniqueIdentifier);
        }
        if ($status = ($icalElements['status']??false)) {
            $event->status($status);
        }

        $this->icalObj->event($event);
    } // addICalEvent


    /**
     * @param array $options
     * @return array
     */
    private function parseOptions(array $options): array
    {
        if (is_array($options)) {
            $options += ICAL_DEFAULT_OPTIONS;
        } elseif (is_string($options)) {
            $options = ICAL_DEFAULT_OPTIONS;
            $options['title'] = $options;
        }
        $this->options = $options;
        $this->determineTargetFile();
        return $options;
    } // parseOptions


    /**
     * @return void
     * @throws \Exception
     */
    private function determineTargetFile(): void
    {
        $options = $this->options;
        $prefix = $prefix_ = translateToFilename($options['prefix'] ?? '', false);
        if ($prefix) {
            $prefix_ .= '/';
            $prefix .= '_';
        }
        if ($options['saveAllToFile']??false) {
            $file = $options['saveAllToFile'];
            $this->path = dir_name($file);
            $this->filename = base_name($file, false) . '.ics';
            $this->options['saveAllToFile'] = false;

        } elseif ($options['saveToFile']??false) {
            $file = $options['saveToFile'];
            $this->path = dir_name($file);
            $this->filename = base_name($file, false) . '.ics';

        } else {
            $rec = reset($this->events);
            $start = $rec['start'] ?? '';
            $date = date('Y-m-d\THi', strtotime($start));
            $filename = $prefix . $date . '.ics';
            $this->path = '';
            $this->filename = $filename;
        }
        if (($this->path[0]??'') === '~') {
            $file = $this->path . $this->filename;
        } else {
            if ($prefix_) {
                $this->path = $prefix_ . $this->path;
            }
            $file = ICAL_DOWNLOAD_PATH . $this->path . $this->filename;
        }
        $this->targetFilePath = Utils::resolvePath($file);
        $this->targetFileUrl  = Utils::resolveUrls($file, forResoucres:true);
    } // determineTargetFile

} // Ical
