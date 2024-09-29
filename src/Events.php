<?php

/*
 * Events
 *
 * See https://twig.symfony.com/doc/3.x/filters/index.html for reference on Twig
 *
 * Recurring Events:
 *  -> RRULE
 * https://icalendar.org/rrule-tool.html
 *
 */


namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MarkdownPlus;
use PgFactory\PageFactory\DataSet as DataSet;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\fileTime;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\loadFile;
use RRule\RRule;


class Events extends DataSet
{
    public $filetime;
    private static array $timePlaceholders = [];

    /**
    //     * @param string $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $options['masterFileRecKeyType'] = 'index';

        $file = ($options['file']??false);

        parent::__construct($file, $options);

        if ($file) {
            if (($ftime = fileTime(resolvePath($file)))) {
                $this->filetime = date("d.F Y", $ftime);
            } else {
                $this->filetime = '{|! pfy-event-source-filetime-unknown !|}';
            }
        }
        $this->options = $options;
    } // __construct


    /**
     * @param array|null $options
     * @return string
     */
    public function render(array $options = null): string
    {
        if ($options) {
            $options = array_merge_recursive($this->options, $options);
        } else {
            $options = $this->options;
        }

        if ($options['rrule']??false || $options['timePattern']??false) {
            $events = $this->renderTimePattern();
            // special case:
            if (is_string($events)) {
                return $events;
            }
        } else {

            // get data filtered by category:
            $sortedData = $this->getData($options['category']);

            // select events according to from, till and count parameters. If from missing, time() is assumed:
            $events = $this->selectEvents($sortedData);
        }

        if (!$events) {
            return '{{ pfy-no-event-found }}';
        }

        $events = $this->handleExpections($events);

        // render by compiling data with template:
        $templateOptions = TemplateCompiler::sanitizeTemplateOption($options['template']??[]);
        $template = TemplateCompiler::getTemplate($templateOptions, $options['category']??null);
        $mdStr = TemplateCompiler::compile($template, $events, $templateOptions);

        // finalize:
        $mdStr = $this->cleanup($mdStr);

        // Work-around for HP printer - crashes on these characters:
        $mdStr = str_replace(['↗', '↘'], ['⇗', '⇘'], $mdStr);

        // Translate:
        if ($options['translate']??true) {
            $mdStr = TransVars::translate($mdStr);
        }

        // md-compile:
        if ($options['markdown']??true) {
            return markdown($mdStr);
        } else {
            return $mdStr;
        }
    } // render


    // 0 (Sun) bis 6 (Sat)
    /**
     * @return mixed
     * @throws \Exception
     */
    private function renderTimePattern(): mixed
    {
        // simple pattern, such as current year (='Y'):
        if ($this->options['timePattern']??false) {
            return resolveTimePlaceholders($this->options['timePattern'], false);
        }

        $options = $this->options;

        // parse rrule:
        if ($rrule = ($options['rrule'] ?? '')) {
            $rruleElems = explodeTrim(';', $rrule);
            $rRules = [];
            foreach ($rruleElems as $rule) {
                list($k, $v) = explode('=', $rule);
                $rRules[$k] = $v;
            }
        }

        $duration = ($options['duration']??false) ? intval($options['duration']) * 60 : 3600; // s
        $startTime = $options['startTime']??'12:00';
        if ($endTime = ($options['endTime']??false)) {
            $startT = strtotime('1970-01-01 '.$startTime);
            $endT = strtotime('1970-01-01 '.$endTime);
            $duration = $endT - $startT;
        }

        // start and end dates, resp. count (optional):
        $from = ($options['from']??false)? resolveTimePlaceholders($options['from'], false) : time();
        if ($from && !is_numeric($from)) {
            $from = strtotime($from);
        }
        $till = ($options['till']??false)? resolveTimePlaceholders($options['till'], false) : false;
        if ($till && !is_numeric($till)) {
            $till = strtotime($till);
        }

        // special case 'offset': create superset of events from which to select later:
        if ($options['offset']??false) {
            $from = strtotime('-1 year', $from);
            if (!$till) {
                $till = strtotime('+2 year');
            }
            $options['count'] = false;
            unset($rRules['COUNT']);
        }
        if ($from) {
            $rRules['DTSTART'] = self::convertDatetime($from);
        }
        if ($till) {
            $rRules['UNTIL'] = self::convertDatetime($till);
        } elseif ($count = ($options['count']??false)) {
            $rRules['COUNT'] = $count;
        }

        // compile rrule:
        try {
            $rrule = new RRule($rRules);

            $event = $options['eventValues'] ?? [];
            $events = [];

            foreach ($rrule as $occurrence) {
                $event['start'] = $occurrence->format('Y-m-d ') . $startTime;
                $event['end'] = date('Y-m-d H:i', strtotime($event['start']) + $duration);
                $events[] = $event;
            }
        } catch (\Exception $e) {
            throw new \Exception("Error: improple date/time format in Events (".$e->getMessage().")");
        }

        return $events;
    } // renderTimePattern


    /**
     * @param string $str
     * @return string
     */
    public static function convertDatetime(string|int $str): string
    {
        if (is_int($str)) {
            $str = date('Y-m-d H:i:s', $str);
        }
        $date = str_replace('-', '', substr($str, 0, 10));
        $time = str_pad(str_replace(':','', substr($str, 11, 5)), 6, '0');
        return "{$date}T{$time}Z";
    } // convertDatetime


    /**
     * @param array $events
     * @return array
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function handleExpections(array $events): array
    {
        $exceptions = [];
        // get exceptions definition:
        $exceptionsStr = ($this->options['exceptions']??'');
        // get exceptions definition from file:
        if ($file = ($this->options['exceptionsFile']??false)) {
            if ($exceptionsStr) {
                $exceptionsStr .= ',';
            }
            $exceptionsStr .= str_replace("\n", ',', loadFile($file, 'c,h,e'));
        }
        $exceptionsStr = rtrim($exceptionsStr, ',');

        // parse exceptions definition:
        if ($exceptionsStr) {
            $rawExceptions = explodeTrim(',;', $exceptionsStr);
            foreach ($rawExceptions as $rawException) {
                $exception = resolveTimePlaceholders($rawException, false);
                if (str_contains($exception, ' - ')) {
                    list($from, $till) = explode(' - ', $exception);
                    $from = strtotime($from);
                    $till = strtotime($till);
                } else {
                    $from = strtotime($exception);
                    if (strlen($exception) < 10) {
                        $till = strtotime('+1month', $from);
                    } else {
                        $till = strtotime('+1day', $from);
                    }
                }
                $exceptions[] = [$from, $till];
            }
        }

        // apply exceptions:
        if ($exceptions) {
            foreach ($events as $key => $event) {
                $evFrom = strtotime($event['start']);
                $evTill = strtotime($event['end']);
                foreach ($exceptions as $exception) {
                    if ($evFrom > $exception[0] && $evTill < $exception[1]) {
                        unset($events[$key]);
                        break;
                    }
                }
            }
        }
        return $events;
    } // handleExpections


    /**
     * @param string $category
     * @return array
     * @throws \Exception
     */
    private function getData(string $category): array
    {
        $data = $this->data();

        $sortedData = [];
        $inx = 1;
        foreach ($data as $rec) {
            $start = $rec['start'] ?? '';
            if ($start) {
                if (str_contains($start, 'T')) {
                    $start = str_replace('T', ' ',$start);
                }
                if (!isset($sortedData[$start])) {
                    $sortedData[$start] = $rec;
                } else {
                    $sortedData[$start.$inx] = $rec;
                    $inx++;
                }
            }
        }

        if ($category) {
            if (str_contains($category, '|')) {
                $tmpData = [];
                $categories = explodeTrim('|', $category);
                foreach ($categories as $category) {
                    $data = array_filter($sortedData, function ($rec) use ($category) {
                        return $category === ($rec['category'] ?? false);
                    });
                    $tmpData = array_merge($tmpData, $data);
                }
                $sortedData = $tmpData;
            } else {
                $sortedData = array_filter($sortedData, function ($rec) use ($category) {
                    return $category === ($rec['category'] ?? false);
                });
            }
        }
        ksort($sortedData);
        return array_values($sortedData);
    } // getData


    /**
     * @param array $sortedData
     * @return int|false
     */
    private function findEvent(array $sortedData, int|false $offset = false): int|false
    {
        $options = $this->options;
        $offset = ($offset !== false) ? $offset : ($options['offset']??0);
        $from = $options['from']??0;

        if ($from) {
            $targetDateT = resolveTimePlaceholders($from);
        } else {
            $targetDateT = strtotime(date('Y-m-d ')); // round down to last midnight
        }

        // find the record
        $found = false;
        foreach ($sortedData as $i => $rec) {
            $start = $rec['start'] ?? '';
            $startT = strtotime($start);
            if ($startT > $targetDateT) {
                $found = $i;
                break;
            }
        }

        if ($found !== false) {
            $found = $found + $offset;
            if ($found < 0 || $found >= sizeof($sortedData)) {
                return false;
            }
            return $found;
        } else {
            return false;
        }
    } // findEvent


    /**
     * @param $sortedData
     * @return array
     */
    private function selectEvents($sortedData): array
    {
        $first = $this->findEvent($sortedData);
        if ($first === false) {
            return [];
        }

        $till = $this->options['till'];
        if ($till) {
            if (is_string($till)) {
                $till = resolveTimePlaceholders($till);
            }
            $count = 999;
        } else {
            $till = PHP_INT_MAX;
            $count = $this->options['count'];
        }
        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $event = $sortedData[$first + $i]??false;
            if (!$event) {
                break;
            }
            $end = $event['end']??0;
            if (!$end) {
                $end = $event['start'] ?? 0;
            }
            $endT = strtotime($end);
            if ($endT > $till) {
                break;
            }
            $events[] = $event;
        }
        return $events;
    } // selectEvents


    /**
     * @param string $str
     * @return string
     */
    private function cleanup(string $str): string
    {
        $str = str_replace(['{|!', '!|}'], ['{{', '}}'], $str);
        return $str;
    } // cleanup


    /**
     * @param string|false $category
     * @param int $offset
     * @return array|false
     * @throws \Exception
     */
    public function getNextEvent(string|false $category = false, int $offset = 0): array|false
    {
        $events = self::getNextEvents($category, $offset, 1);
        return $events[0] ?? [];
    } // getNextEvent


    /**
     * @param string|false $category
     * @param int $offset
     * @param int|false $count
     * @return array|false
     * @throws \Kirby\Exception\InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getNextEvents(string|false $category = false, int $offset = 0, int|false $count = false): array|false
    {
        $options = ($this->options??false) ?: [];
        $category = $category?: $this->options['category']??false;

        // handle case where rrule is specified, rather than events from DB:
        if (($this->options['rrule']??false) || ($this->options['timePattern']??false)) {
            $sortedData = $this->renderTimePattern();
            if (is_string($sortedData)) {
                throw new \Exception("Error in Events: getNextEvents() with rrule returned string instead of array");
            }
        } else {
            // get events from DB:
            $sortedData = $this->getData($category);
        }


        // case multiple categories -> extract first for further processing:
        if (is_string($category) && str_contains($category, '|')) {
            $category = preg_replace('/\|.*/', '', $category);
        }

        if (isset($this->options['offset']) && $offset === 0) {
            $offset = $this->options['offset'];
        }
        $nextEventInx = $this->findEvent($sortedData, $offset);
        if ($nextEventInx === false) {
            return false;
        }

        if ($count === false) {
            $count = 1;
        }

        $nextEvents = array_splice($sortedData, $nextEventInx, $count);

        $templateOptions = TemplateCompiler::sanitizeTemplateOption($options['template']??[]);
        $template = TemplateCompiler::getTemplate($templateOptions, $category);

        foreach ($nextEvents as $i => $rec) {
            $eventBanner = TemplateCompiler::compile($template, $rec, $templateOptions);
            $nextEvents[$i]['eventBanner'] = $eventBanner;
        }

        return $nextEvents;
    } // getNextEvents


} // Events

