<?php

/*
 * Events
 *
 * See https://twig.symfony.com/doc/3.x/filters/index.html for reference on Twig
 */


namespace Usility\PageFactoryElements;

use Usility\PageFactory\DataSet as DataSet;
use Usility\PageFactory\PageFactory;
use function Usility\PageFactory\fileTime;
use function Usility\PageFactory\resolvePath;
use function Usility\PageFactory\loadFile;
use Usility\PageFactory\TransVars;
 // Optional support for propre localization of dates -> requires twig/intl-extra
 //use Twig\Extra\Intl\IntlExtension; // -> composer require twig/intl-extra

const TRANSLATIONS = [
   'Monday' => ['en' => 'Monday', 'de' => 'Montag',],
   'Tuesday' => ['en' => 'Tuesday', 'de' => 'Dienstag',],
   'Wednesday' => ['en' => 'Wednesday', 'de' => 'Mittwoch',],
   'Thursday' => ['en' => 'Thursday', 'de' => 'Donnerstag',],
   'Friday' => ['en' => 'Friday', 'de' => 'Freitag',],
   'Saturday' => ['en' => 'Saturday', 'de' => 'Samstag',],
   'Sunday' => ['en' => 'Sunday', 'de' => 'Sonntag',],
   'January' => ['en' => 'January', 'de' => 'Januar',],
   'Febrary' => ['en' => 'Febrary', 'de' => 'Februar',],
   'March' => ['en' => 'March', 'de' => 'März',],
   'April' => ['en' => 'April', 'de' => 'April',],
   'May' => ['en' => 'May', 'de' => 'Mai',],
   'June' => ['en' => 'June', 'de' => 'Juni',],
   'July' => ['en' => 'July', 'de' => 'Juli',],
   'September' => ['en' => 'September', 'de' => 'September',],
   'October' => ['en' => 'October', 'de' => 'Oktober',],
   'November' => ['en' => 'November', 'de' => 'November',],
   'December' => ['en' => 'December', 'de' => 'Dezember',],
//            '' => '',
];
const YEAR_THRESHOLD = 10;

class Events extends DataSet
{
    public $filetime;
    private array $timePatterns;

    public function __construct(string $file, array $options = [])
    {
        parent::__construct($file, $options);

        if (($ftime = fileTime(resolvePath($file)))) {
            $this->filetime = date("d.F Y", $ftime);
        } else {
            $this->filetime = '{! pfy-event-source-filetime-unknown !}';
        }

        $this->prepareTimePatterns();
    } // __construct


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
        $template = $this->getTemplate();
        if (!$template) {
            return '{{ pfy-no-event-template-found }}';
        }

        // compile the template using Twig:
        $mdStr = $this->compile($template, $events);

        // finalize:
        $mdStr = $this->cleanup($mdStr);

        // md-compile:
        if ($options['markdown']) {
            return markdown($mdStr);
        } else {
            return $mdStr;
        }
    } // render


    private function getData(string $category): array
    {
        $data = $this->data();

        $sortedData = [];
        foreach ($data as $rec) {
            $start = $rec['start'] ?? '';
            if ($start) {
                $sortedData[$start] = $rec;
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

    
    private function findEvent(array $sortedData): int|false
    {
        $options = $this->options;
        $offset = $options['offset'];
        $from = $options['from'];

        if ($from) {
            $targetDate = $this->parseTime($from);
        } else {
            $targetDate = time();
        }

        // find the record
        $found = false;
        foreach ($sortedData as $i => $rec) {
            $start = $rec['start'] ?? '';
            $startT = strtotime($start);
            if ($startT > $targetDate) {
                $found = $i;
                break;
            }
        }

        if ($found !== false) {
            return $found + $offset - 1;
        } else {
            return false;
        }
    } // findEvent
    

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
    
    
    private function getTemplate(): mixed
    {
        $templateName = $this->options['template'];
        $category = $this->options['category'];
        $language = PageFactory::$langCode;

        $templatesFile = $this->options['templatesFile'];
        $templates = false;
        if ($templatesFile) {
            $templates = loadFile($templatesFile);
        }

        if ($templates) { // Templates from file found:
            if ($category) {
                $templateCand = "$templateName-$category";
                $template = $templates[$templateCand]??false;

            } else { //  try language:
                $templateCand = "$templateName-$language";
                $template = $templates[$templateCand]??false;
            }
            if (!$template) { // try category-language
                $templateCand = "$templateName-$category-$language";
                $template = $templates[$templateCand]??false;
            }
            if (!$template) { // try -language-category
                $templateCand = "$templateName-$language-$category";
                $template = $templates[$templateCand]??false;
            }
            if (!$template) { // try default template:
                $template = $templates[$templateName]??false;
            }

        } else { // Templates from variables:
            if ($category) {
                $templateCand = "$templateName-$category";
                $template = TransVars::getVariable($templateCand);

            } else { //  try language:
                $templateCand = "$templateName-$language";
                $template = TransVars::getVariable($templateCand);
            }
            if (!$template) { // try category-language
                $templateCand = "$templateName-$category-$language";
                $template = TransVars::getVariable($templateCand);
            }
            if (!$template) { // try -language-category
                $templateCand = "$templateName-$language-$category";
                $template = TransVars::getVariable($templateCand);
            }
            if (!$template) { // try default template:
                $template = TransVars::getVariable($templateName);
            }
        }
        return $template;
    } // getTemplate


    private function compile($template, $events)
    {
        $category = $this->options['category'];
        $wrap1 = $wrap2 = "\n";
        if ($this->options['wrap']) {
            $wrap1 = "\n@@@ .event-$category\n\n";
            $wrap2 = "\n\n@@@\n\n";
        }
        $mdStr = '';
        foreach ($events as $eventRec) {
            $mdStr .= $wrap1;
            $mdStr .= $this->compileTemplate($template, $eventRec);
            $mdStr .= $wrap2;
        }
        return $mdStr;
    } // compile
    
    
    private function compileTemplate(string $template, array $eventRec): string
    {
        $eventRec['filetime'] = $this->filetime;
        $loader = new \Twig\Loader\ArrayLoader([
            'index' => $template,
        ]);
        $twig = new \Twig\Environment($loader);
        // $twig->addExtension(new IntlExtension());
        return $twig->render('index', $eventRec);
    } // compileTemplate


    private function parseTime($str) {

        $str = str_replace($this->timePatterns['patterns'], $this->timePatterns['values'], $str);
        return strtotime($str);
    } // parseTime

    private function cleanup(string $str): string
    {
        $lang = PageFactory::$langCode;
        $translations = TRANSLATIONS;
        $e0 = reset($translations);
        if (!isset($e0[$lang])) {
            return $str; // language not found, skip translation
        }
        $to = array_map(function ($e) use($lang) {
            return $e[$lang]??'???';
        }, array_values(TRANSLATIONS));
        $str = str_replace(array_keys($translations), $to, $str);

        $str = str_replace(['{!', '!}'], ['{{', '}}'], $str);
        return $str;
    } // cleanup


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

} // Events

