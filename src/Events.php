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


use PgFactory\PageFactory\DataSet as DataSet;
use PgFactory\PageFactory\PageFactory;
use function PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\fileTime;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\loadFile;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\translateToClassName;
use RRule\RRule;
use IntlDateFormatter;


class Events extends DataSet
{
    public $filetime;
    private static array $timePlaceholders = [];
    private mixed $templates = null;

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
                $this->filetime = '{! pfy-event-source-filetime-unknown !}';
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

        // find the appropriate template taking into account category and language:
        $mdStr = $this->compile($events);

        // finalize:
        $mdStr = $this->cleanup($mdStr);

        // md-compile:
        if ($options['markdown']??true) {
            return markdown($mdStr);
        } else {
            return $mdStr;
        }
    } // render


    // 0 (Sun) bis 6 (Sat)
    private function renderTimePattern(): mixed
    {
        // simple pattern, such as current year (='Y'):
        if ($this->options['timePattern']??false) {
            return $this->resolveTimePlaceholders($this->options['timePattern'], false);
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
        $from = ($options['from']??false)? $this->resolveTimePlaceholders($options['from'], false) : strtotime('Y-m-d');
        $till = ($options['till']??false)? $this->resolveTimePlaceholders($options['till'], false) : false;

        if ($from) {
            $rRules['DTSTART'] = $this->convertDatetime($from);
        }
        if ($till) {
            $rRules['UNTIL'] = $this->convertDatetime($till);
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


    private function convertDatetime(string $str): string
    {
        $date = str_replace('-', '', substr($str, 0, 10));
        $time = str_pad(str_replace(':','', substr($str, 11, 5)), 6, '0');
        return "{$date}T{$time}Z";
    } // convertDatetime


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
                $exception = $this->resolveTimePlaceholders($rawException, false);
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
            $targetDateT = $this->resolveTimePlaceholders($from);
        } else {
            $targetDateT = strtotime(date('Y-m-d ')); // round down to last midnight
        }
        //$targetDateStr = date('Y-m-d H:i', $targetDateT);

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

        $till = $this->options['till'];
        if ($till) {
            if (is_string($till)) {
                $till = $this->resolveTimePlaceholders($till);
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
     * @param $category
     * @return mixed
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function getTemplate(string|false $category): mixed
    {
        if ($this->templates === null) {
            $this->loadTemplates();
        }
        $templates = $this->templates;
        if (is_string($templates)) {
            return $templates;
        }

        $templateName = $this->options['templateBasename'] ?? '';
        if ($templateName) {
            $templateName .= '-';
        }
        $language = PageFactory::$langCode;

        if ($templates) { // Templates from file found:
            if ($category) {
                $templateCand = "$templateName$category";
                $template = $templates[$templateCand]??false;

            } else { //  try language:
                $templateCand = "$templateName$language";
                $template = $templates[$templateCand]??false;
            }
            if (!$template) { // try category-language
                $templateCand = "$templateName$category-$language";
                $template = $templates[$templateCand]??false;
            }
            if (!$template) { // try -language-category
                $templateCand = "$templateName$language-$category";
                $template = $templates[$templateCand]??false;
            }
            if (!$template) { // try default template:
                $template = $templates[$templateName]??false;
            }
            if (!$template) { // try default template:
                $template = $templates['_']??false;
            }

        } else { // Templates from variables:
            if ($category) {
                $templateCand = "$templateName$category";
                $template = TransVars::getVariable($templateCand);

            } else { //  try language:
                $templateCand = "$templateName$language";
                $template = TransVars::getVariable($templateCand);
            }
            if (!$template) { // try category-language
                $templateCand = "$templateName$category-$language";
                $template = TransVars::getVariable($templateCand);
            }
            if (!$template) { // try -language-category
                $templateCand = "$templateName$language-$category";
                $template = TransVars::getVariable($templateCand);
            }
            if (!$template) { // try default template:
                $template = TransVars::getVariable($templateName);
            }
            if (!$template) { // try default template:
                $template = $templates['_']??false;
            }
        }
        return $template;
    } // getTemplate


    /**
     * @param $events
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function compile($events)
    {
        $wrap1 = $wrap2 = "\n";
        $mdStr = '';
        foreach ($events as $i => $eventRec) {
            $category = $eventRec['category']??false;
            $eventRec['index'] = $i + 1;
            $catClass = translateToClassName($category);
            if ($this->options['wrap']) {
                $wrap1 = "\n%%%%%% .pfy-event-wrapper.event-$catClass\n\n";
                $wrap2 = "\n\n%%%%%%\n\n";
            }
            $template = $this->getTemplate($category);
            $template = str_replace('%%', $i, $template);
            $mdStr .= $wrap1;
            $mdStr .= $this->compileTemplate($template, $eventRec);
            $mdStr .= $wrap2;
        }
        return $mdStr;
    } // compile


    /**
     * @param string $template
     * @param array $eventRec
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function compileTemplate(string $template, array $eventRec): string
    {
        // immediately replace patterns %key% with value from data-rec:
        $template = $this->resolveVariables($template, $eventRec);

        // execute PageFactory Macros:
        $template = TransVars::executeMacros($template, onlyMacros:true);

        //
        $eventRec['filetime'] = $this->filetime;
        $vars = array_merge(\PgFactory\PageFactory\TransVars::$variables, $eventRec);

        // compile templage with Twig:
        $loader = new \Twig\Loader\ArrayLoader([
            'index' => $template,
        ]);
        $twig = new \Twig\Environment($loader);
        $twig->addFilter(new \Twig\TwigFilter('intlDate', 'PgFactory\PageFactoryElements\twigIntlDateFilter'));
        $twig->addFilter(new \Twig\TwigFilter('intlDateFormat', 'PgFactory\PageFactoryElements\twigIntlDateFormatFilter'));
        return $twig->render('index', $vars);
    } // compileTemplate


    /**
     * Resolves a format string to current values.
     *   Supports date() type arguments (e.g. 'Y-m-d' or 'Y2-m-d').
     *   Special case: 'Yn' (where n=number) -> flips year to next year when month is greater than 12-n.
     *   Example: Y2 returns next year when called in November or December, otherwise the current year.
     *   Values M (=Jan), F (=January), D (=Mon), l (=Monday) are translated to local language
     * @param $str
     * @return false|int
     */
    private function resolveTimePlaceholders(string $str, $strtotime = true): string
    {
        if (preg_match('/Y(\d+)/', $str, $m)) {
            $y = date('Y');
            $d = intval($m[1]);
            if ($d) {
                $dayOfYear = intval(date('z'));
                if ($dayOfYear > (365 - $d)) {
                    $y += 1;
                }
            }
            $str = str_replace($m[0], (string)$y, $str);
        }
        $str = intlDate($str);

        if ($strtotime) {
            return strtotime($str);
        } else {
            return $str;
        }
    } // resolveTimePlaceholders


    /**
     * @param string $str
     * @return string
     */
    private function cleanup(string $str): string
    {
        $str = str_replace(['{!', '!}'], ['{{', '}}'], $str);
        return $str;
    } // cleanup


    /**
     * @param string $template
     * @param array $variables
     * @return string
     */
    private function resolveVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace("%$key%", "$value", $template);
        }
        return $template;
    } // resolveVariables


    /**
     * @param $category
     * @return false|mixed
     * @throws \Kirby\Exception\InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getNextEvent(string|false $category = false, int $offset = 0): array|false
    {
        $category = $category?: $this->options['category']??false;
        $templatesFile = $this->options['templatesFile']??false;
        $sortedData = $this->getData($category);
        if (isset($this->options['offset']) && $offset === 0) {
            $offset = $this->options['offset'];
        }
        $nextEventInx = $this->findEvent($sortedData, $offset);
        if ($nextEventInx === false) {
            return false;
        }
        $nextEventRec = $sortedData[$nextEventInx];
        $eventBanner = '';

        // prepare event banner:
        if ($templatesFile) {
            $this->loadTemplates($templatesFile);
            $template = $this->getTemplate($category);
            $eventBanner = $this->compileTemplate($template, $nextEventRec);
            $eventBanner = markdown($eventBanner);
            $eventBanner = $this->cleanup($eventBanner);
        }
        $nextEventRec['eventBanner'] = $eventBanner;

        return $nextEventRec;
    } // getNextEvent


    /**
     * @param string|false $category
     * @param int $offset
     * @return array|false
     * @throws \Kirby\Exception\InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getNextEvents(string|false $category = false, int $offset = 0, int|false $count = false): array|false
    {
        $category = $category?: $this->options['category']??false;
        $templatesFile = $this->options['templatesFile']??false;
        if ($templatesFile) {
            $this->loadTemplates($templatesFile);
        }
        $sortedData = $this->getData($category);

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

        $nextEvents = [];
        $count = $count ?: sizeof($sortedData);
        for ($i = $nextEventInx; $i < $count; $i++) {
            $nextEventRec = $sortedData[$i];
            $eventBanner = '';

            // prepare event banner:
            if ($templatesFile) {
                $template = $this->getTemplate($category);
                $eventBanner = $this->compileTemplate($template, $nextEventRec);
                $eventBanner = markdown($eventBanner);
                $eventBanner = $this->cleanup($eventBanner);
            }
            $nextEventRec['eventBanner'] = $eventBanner;
            $nextEvents[] = $nextEventRec;
        }

        return $nextEvents;
    } // getNextEvents


    /**
     * @param $file
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function loadTemplates(string|false $file = false): void
    {
        if ($template = ($this->options['template']??false)) {
            $this->templates = $template;
            return;
        }
        $file = $file ?: ($this->options['templatesFile']??false);
        if (!$file) {
            throw new \Exception("Error: events file '$file' not found.");
        }
        $this->templates = loadFile($file);
    } // loadTemplates

} // Events

