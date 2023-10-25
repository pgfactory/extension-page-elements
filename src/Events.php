<?php

/*
 * Events
 *
 * See https://twig.symfony.com/doc/3.x/filters/index.html for reference on Twig
 */


namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\DataSet as DataSet;
use PgFactory\PageFactory\PageFactory;
use function PgFactory\PageFactory\fileExt;
use function PgFactory\PageFactory\fileGetContents;
use function PgFactory\PageFactory\fileTime;
use function PgFactory\PageFactory\resolvePath;
use function PgFactory\PageFactory\loadFile;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\translateToIdentifier;

 // Optional support for propre localization of dates -> requires twig/intl-extra
 //use Twig\Extra\Intl\IntlExtension; // -> composer require twig/intl-extra


class Events extends DataSet
{
    public $filetime;
    private array $timePatterns;
    private array|null $templates = null;

    public function __construct(string $file, array $options = [])
    {
        $options['masterFileRecKeyType'] = 'index';
        parent::__construct($file, $options);

        if (($ftime = fileTime(resolvePath($file)))) {
            $this->filetime = date("d.F Y", $ftime);
        } else {
            $this->filetime = '{! pfy-event-source-filetime-unknown !}';
        }

        $this->prepareTimePatterns();
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

        // get data filtered by category:
        $sortedData = $this->getData($options['category']);

        // select events according to from, till and count parameters. If from missing, time() is assumed:
        $events = $this->selectEvents($sortedData);
        if (!$events) {
            return '{{ pfy-no-event-found }}';
        }

        // find the appropriate template taking into account category and language:
        $mdStr = $this->compile($events);

        // finalize:
        $mdStr = $this->cleanup($mdStr);

        // md-compile:
        if ($options['markdown']) {
            return markdown($mdStr);
        } else {
            return $mdStr;
        }
    } // render


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
        ksort($sortedData);

        if ($category) {
            $sortedData = array_filter($sortedData, function ($rec) use ($category) {
                return $category === ($rec['category'] ?? false);
            });
        }
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
            $targetDateT = $this->parseTime($from);
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
                $till = $this->parseTime($till);
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
    private function getTemplate($category): mixed
    {
        if ($this->templates === null) {
            $this->loadTemplates();
        }
        $templates = $this->templates;

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
            $catClass = translateToIdentifier($category);
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
        // translate PageFactory Macros first:
        $template = $this->resolveVariables($template, $eventRec);
        $template = TransVars::executeMacros($template, onlyMacros:true);

        $eventRec['filetime'] = $this->filetime;
        $loader = new \Twig\Loader\ArrayLoader([
            'index' => $template,
        ]);
        $twig = new \Twig\Environment($loader);
        return $twig->render('index', $eventRec);
    } // compileTemplate


    /**
     * @param $str
     * @return false|int
     */
    private function parseTime($str) {

        $str = str_replace($this->timePatterns['patterns'], $this->timePatterns['values'], $str);
        return strtotime($str);
    } // parseTime


    /**
     * @param string $str
     * @return string
     */
    private function cleanup(string $str): string
    {
        $str = translateDateTimes($str);

        $str = str_replace(['{!', '!}'], ['{{', '}}'], $str);
        return $str;
    } // cleanup


    /**
     * @return void
     */
    private function prepareTimePatterns(): void
    {
        $patterns = [
            'Y', 'y', 'n', 'm', 'M', 'F', 'd', 'l', 'D'
        ];
        $values = [];
        foreach ($patterns as $v) {
            $values[] = date($v);
        }

        // for the last month of an year, we assume the user wants to see the coming year:
        if ($values[2] > YEAR_THRESHOLD) {
            $values[0]++;
            $values[1]++;
        }

        $this->timePatterns = ['patterns' => $patterns, 'values' => $values];
    }

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
     * @param $file
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function loadTemplates(string|false $file = false): void
    {
        $file = $file ?: ($this->options['templatesFile']??false);
        if (!$file) {
            return;
        }
        $ext = fileExt($file);
        switch ($ext) {
            case 'txt':
                $this->templates['_'] = loadFile($file);
                break;
            case 'yaml':
                $this->templates = loadFile($file);
                break;
        }
    } // loadTemplates

} // Events

