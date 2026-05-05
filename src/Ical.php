<?php

/*
 * Doc: https://packagist.org/packages/spatie/icalendar-generator
 */

namespace PgFactory\PageFactoryElements;
use PgFactory\MarkdownPlus\MarkdownPlus;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use DateTime;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use Spatie\IcalendarGenerator\Enums\EventStatus;
use function PgFactory\PageFactory\createHash;
use function PgFactory\PageFactory\fileTime;
use function PgFactory\PageFactory\isValidEmail;
use function PgFactory\PageFactory\writeFile;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\translateToFilename;

const ICAL_DOWNLOAD_PATH = PFY_PUBLIC_DOWNLOAD_PATH.'ical/';
const ICAL_CALENDAR_ICON  = ':calendar_move:';
const PFY_ICAL_DEFAULT_FIELDTEMPLATES = [
    'allday'       => false,
    'uniqueIdentifier' => '',
];
const ICAL_UNIQUE_IDENTIFIER_PREFIX = 'pgfactory_';

const PFY_ICAL_DEFAULT_OPTIONS = [
    'linkText' =>   '%icon%',
    'tooltip' =>    '{{ pfy-ical-link-tooltip }}',
    'prefix' =>     '',
    'asButton' =>   false,
    'selector' => '',
    'fieldTemplates' => [],
];


class Ical
{
    private array $events;
    private array $options;
    private Calendar $icalObj;
    private string $path;
    private string $filename;
    private string $targetFilePath = '';
    private string $targetFileUrl;
    private array $fieldTemplates;
    private array $selectedFieldTemplates;
    private static array $persistentOptions = [];

    /**
     * @param array $events
     * @param array $options
     */
    public function __construct(array $events, array $options = [])
    {
        $this->events = $events;
        $this->options = $this->parseOptions($options);
    } // __construct


    /**
     * @param array $options
     * @return void
     */
    public static function preset(array $options): void
    {
        self::$persistentOptions = $options + PFY_ICAL_DEFAULT_OPTIONS;
    } // preset


    /**
     * @return int
     */
    public function getTargetFileTime(): int
    {
        return fileTime($this->determineTargetFile());
    } // getTargetFileTime


    /**
     * @return string
     */
    public function getTargetFile(): string
    {
        return $this->determineTargetFile();
    } // getTargetFile


    /**
     * @return string
     */
    public function getTargetUrl(): string
    {
        return $this->targetFileUrl;
    } // getTargetUrl


    /**
     * @return void
     * @throws \Exception
     */
    public function saveToFile(int $referenceTime = 0): void
    {
        $this->selectFieldTemplates();

        $icsStr = $this->renderAllICalStr();
        $filePath = $this->determineTargetFile();

        $icalFileTime = $this->getTargetFileTime();
        if ($referenceTime < $icalFileTime) {
            return; // skip creating file if it's newer that reference time
        }

        preparePath($filePath, 0755);
        writeFile($filePath, $icsStr, permissions: 0644);

        if (sizeof($this->events) > 1) {
            foreach ($this->events as $key => $rec) {
                $icsStr = $this->renderICalStr($key, $rec);
                $targFile = $this->determineTargetFile($rec);
                writeFile($targFile, $icsStr, permissions: 0644);
            }
        }
    } // saveToFile


    /**
     * @return void
     */
    private function selectFieldTemplates(): void
    {
        $fieldTemplates = $this->fieldTemplates;
        $selector = $this->options['selector'];
        if (isset($fieldTemplates[$selector])) {
            $this->selectedFieldTemplates = $fieldTemplates[$selector];
        } else {
            $this->selectedFieldTemplates = $fieldTemplates['_'];
        }
    } // selectFieldTemplates


    /**
     * @return string
     */
    public function renderIcsLink(): string
    {
        $url = $this->targetFileUrl;
        $asButton = $this->options['asButton'] ?? false;
        $tooltip = ($this->options['tooltip'] ?? null);
        if ($tooltip === null) {
            $tooltip = '{{ pfy-ical-link-tooltip }}';
        }
        $linkText = ($this->options['linkText'] ?? null);
        if ($linkText === null) {
            $linkText = '{{ pfy-ical-link-text }}';
        }
        if ($linkText) {
            $linkText = TransVars::translate($linkText);
        }
        $calIcon = ($this->options['icon'] ?? '') ?: ICAL_CALENDAR_ICON;
        $linkText = str_replace('%icon%', $calIcon, $linkText);
        $mdp = new MarkdownPlus();
        $linkText = $mdp->compileParagraph($linkText);
        if ($asButton) {
            $link = "<button class='pfy-enlist-ical-button pfy-button pfy-button-lean' title='$tooltip' type='button'>$linkText</button>";
            $link .= "<a href='$url' download='$this->filename' class='pfy-dispno'>$linkText</a>";
        } else {
            $link = "<a href='$url' download='$this->filename' title='$tooltip'>\n$linkText\n</a>";
        }
        return $link;
    } // renderIcsLink


    /**
     * @param array $rec
     * @return string
     * @throws \Exception
     */
    private function renderICalStr(string $key, array $rec): string
    {
        if (!($rec['start'] ?? false)) {
            if ((array_keys($rec))[0] !== 0) {
                throw new \Exception("Error in Ical: renderICalStr() received unexpected data record");
            }
            $rec = reset($rec);
        }
        $this->icalObj = Calendar::create();
        $icalElements = $this->populateICalElements($key, $rec);
        $this->addICalEvent($icalElements);
        return $this->icalObj->get();
    } // renderICalStr


    /**
     * @return string
     * @throws \Exception
     */
    private function renderAllICalStr(): string
    {
        $this->icalObj = Calendar::create();
        foreach ($this->events as $k => $rec) {
            $icalElements = $this->populateICalElements($k, $rec);
            $this->addICalEvent($icalElements);
        }
        return $this->icalObj->get();
    } // renderAllICalStr


    /**
     * @param array $rec
     * @return array
     */
    private function populateICalElements(string $key, array $rec): array
    {
        $icalElements = [
            'start'         => $this->compileICalElement('start', $rec),
            'end'           => $this->compileICalElement('end', $rec),
            'title'         => $this->compileICalElement('title', $rec),
            'location'      => $this->compileICalElement('location', $rec),
            'description'   => $this->compileICalElement('description', $rec),
            'organizer'     => $this->compileICalElement('organizer', $rec),
        ];
        if ($organizer = ($rec['organizer'] ?? false)) {
            if (!isValidEmail($organizer)) {
                throw new \Exception("Source Error: iCal organizer field must be an e-mail address (given '$organizer')");
            }
        }
        if ($rec['allday'] ?? false) {
            // check and fix start:
            $icalElements['start'] = substr($icalElements['start'], 0, 10);
            $this->events[$key]['start'] = $icalElements['start'];
            // check and fix end:
            if ($icalElements['end']) {
                $icalElements['end'] = date('Y-m-d', strtotime('+1 day', strtotime($icalElements['end'])));
            } else {
                $icalElements['end'] = date('Y-m-d', strtotime('+1 day', strtotime($icalElements['start'])));
            }
            $this->events[$key]['end'] = $icalElements['end'];
        }
        $uniqueIdentifier = ($icalElements['uniqueIdentifier'] ?? false) ?: ($rec['_reckey'] ?? '');
        if ($uniqueIdentifier) {
            $icalElements['uniqueIdentifier'] = ICAL_UNIQUE_IDENTIFIER_PREFIX . $uniqueIdentifier;
        } else {
            $icalElements['uniqueIdentifier'] = ICAL_UNIQUE_IDENTIFIER_PREFIX . createHash();
        }
        $icalElements['allday'] = $rec['allday'] ?? false;
        if (($rec['cancelled'] ?? false) || ($this->options['cancelled'] ?? false)) {
            $icalElements['cancelled'] = true;
        }
        return $icalElements;
    } // populateICalElements


    /**
     * @param string $fieldName
     * @param array $rec
     * @return string
     */
    private function compileICalElement(string $fieldName, array $rec): string|bool
    {
        $fieldValue = '';
        // check whether field template is available:
        $fieldTemplates = $this->selectedFieldTemplates;
        if ($fieldTemplates[$fieldName] ?? false) {
            // field template found -> evaluate it:
            $fieldValue = $fieldName = $fieldTemplates[$fieldName];
            if (isset($rec[$fieldValue])) {
                // field template contained name of a data element -> use it:
                $fieldValue =  $rec[$fieldValue];

            } else {
                // evaluate field template for replacement patters %var% that correspond to data elements or transvars:
                while (preg_match('/%(.{2,20}?)%/', $fieldValue, $m)) {
                    $fname = $m[1];
                    if (isset($rec[$fname])) {
                        $fieldValue = str_replace("%$fname%", $rec[$fname], $fieldValue);
                    } else {
                        if ($value = TransVars::getVariable($fname)) {
                            $fieldValue = str_replace("%$fname%", $value, $fieldValue);
                        } else {
                            $fieldValue = str_replace("%$fname%", '', $fieldValue);
                        }
                    }
                }
            }

        // no field template available -> check direkt match in data:
        } elseif (isset($rec[$fieldName])) {
            $fieldValue =  $rec[$fieldName];
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
        if ($icalElements['start'] === 'start') {
            return; // error -> no value defined for 'start'
        }
        $event->startsAt(new DateTime($icalElements['start']));
        $event->endsAt(new DateTime($icalElements['end']));

        if ($description = ($icalElements['description'] ?? false)) {
            $event->description($description);
        }
        if ($organizer = ($icalElements['organizer'] ?? false)) {
            $event->organizer($organizer);
        }
        if ($location = ($icalElements['location'] ?? false)) {
            $event->address($location);
        }
        if ($icalElements['allday'] ?? false) {
            $event->fullDay();
        }
        if ($uniqueIdentifier = ($icalElements['uniqueIdentifier'] ?? false)) {
            $event->uniqueIdentifier($uniqueIdentifier);
        }
        if ($icalElements['cancelled'] ?? false) {
            $event->status(EventStatus::Cancelled);
        }

        $this->icalObj->event($event);
    } // addICalEvent


    /**
     * @param array $options
     * @return array
     */
    private function parseOptions(array $options): array
    {
        if (self::$persistentOptions) {
            $defaultOptions = self::$persistentOptions;
        } else {
            $defaultOptions = PFY_ICAL_DEFAULT_OPTIONS;
        }
        foreach ($defaultOptions as $key => $value) {
            if (!isset($options[$key]) || ($options[$key] === null)) {
                $options[$key] = $value;
            }
        }

        // === handle option fieldTemplates:
        if (!($options['fieldTemplates'] ?? false)) {
            // no fieldTemplates available, create default element:
            $options['fieldTemplates'] = [
                '_' => PFY_ICAL_DEFAULT_FIELDTEMPLATES
            ];
            $fieldTemplates = &$options['fieldTemplates'];
        } else {
            // fieldTemplates supplied:
            $fieldTemplates = &$options['fieldTemplates'];
            $k0 = array_keys($fieldTemplates);
            $k0 = $k0[0] ?? false;
            if (!($k0 === '_' || is_numeric($k0))) {
                // case where only one template supplied => save it as default template '_':
                $ft = $fieldTemplates;
                $fieldTemplates = [];
                $fieldTemplates['_'] = $ft;
            }
        }
        foreach ($fieldTemplates as $key => $value) {
            $fieldTemplates[$key] += PFY_ICAL_DEFAULT_FIELDTEMPLATES;
        }


        // === check for options that apply to field templates:
        if ($options['title'] ?? false) {
            $fieldTemplates['_']['title'] = $options['title'];
        }
        if ($options['description'] ?? false) {
            $fieldTemplates['_']['description'] = $options['description'];
        }
        if ($options['organizer'] ?? false) {
            $fieldTemplates['_']['organizer'] = $options['organizer'];
        }
        if ($options['location'] ?? false) {
            $fieldTemplates['_']['location'] = $options['location'];
        }
        if ($options['allday'] ?? false) {
            $fieldTemplates['_']['allday'] = $options['allday'];
        }

        $this->fieldTemplates = $fieldTemplates;

        // === case where event is supplied directly in options:
        if (!isset($this->events[0]['start'])) {
            if (($options['events'] ?? false) && is_array($options['events'])) {
                $this->events = $options['events'];
                if (isset($this->events['start'])) {
                    $event = $this->events;
                    $this->events = [];
                    $this->events[0] = $event;
                }
            } else {
                $this->events = [];
                $this->events[0] = [];
                $event0 = &$this->events[0];
                if ($options['start'] ?? false) {
                    $event0['start'] = $options['start'];
                    unset($options['start']);
                }
                if ($options['end'] ?? false) {
                    $event0['end'] = $options['end'];
                    unset($options['end']);
                }
                if ($options['title'] ?? false) {
                    $event0['title'] = $options['title'];
                    unset($options['title']);
                }
                if ($options['description'] ?? false) {
                    $event0['description'] = $options['description'];
                    unset($options['description']);
                }
                if ($options['organizer'] ?? false) {
                    $event0['organizer'] = $options['organizer'];
                    unset($options['organizer']);
                }
                if ($options['location'] ?? false) {
                    $event0['location'] = $options['location'];
                    unset($options['location']);
                }
                if ($options['allday'] ?? false) {
                    $event0['allday'] = $options['allday'];
                    unset($options['allday']);
                }
            }
        }
        return $options;
    } // parseOptions


    /**
     * @return void
     * @throws \Exception
     */
    private function determineTargetFile(array|false $rec = false): string
    {
        $options = $this->options;
        $prefix = $prefix_ = translateToFilename($options['prefix'] ?? '', false);
        if ($prefix) {
            $prefix_ .= '/';
            $prefix .= '_';
        }

        if (!$rec) {
            $prefix .= '_';
            $rec = reset($this->events);
        }
        $startKey = ($this->selectedFieldTemplates['start'] ?? false) ?: 'start';
        $startKey = trim($startKey,'%');
        $start = $rec[$startKey] ?? '';
        if ($rec['allday'] ?? false) {
            $date = date('Y-m-d', strtotime($start));
        } else {
            $date = date('Y-m-d\THi', strtotime($start));
        }
        $filename = $prefix . $date . '.ics';
        $this->path = '';
        $this->filename = $filename;

        if (($this->path[0] ?? '') === '~') {
            $file = $this->path . $this->filename;
        } else {
            if ($prefix_) {
                $this->path = $prefix_ . $this->path;
            }
            $file = ICAL_DOWNLOAD_PATH . $this->path . $this->filename;
        }
        $this->targetFilePath = Utils::resolvePath($file);
        $this->targetFileUrl  = Utils::resolveUrls($file, forResoucres:true);
        return $this->targetFilePath;
    } // determineTargetFile

} // Ical
