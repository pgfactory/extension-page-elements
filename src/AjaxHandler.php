<?php

/*
 * Handler for ajax-requests originating from PfyForm client.
 * Requires session-variable defining the data-source, which is only defined if user is FormAdmin.
 */
namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use function PgFactory\PageFactory\getFile;
use PgFactory\PageFactory\DataSet;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\translateToClassName;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\mylog;


require_once __DIR__ . "/../../pagefactory/src/helper.php";


class AjaxHandler
{
    private static string $pageId;
    private static string $dataSrcInx;
    private static string $sessDbKey;
    private static string $sessCalRecKey;
    private static array $sessRec;
    private static $templates = null;


    /**
     * @param object $result
     * @return void
     */
    public static function exec(object $result): void
    {
        $pageId = self::$pageId = str_replace('/', '_', $result->id());
        $dataSrcInx = self::$dataSrcInx = get('datasrcinx', null);
        if (!$dataSrcInx || ($dataSrcInx === 'undefined')) {
            exit('"not ok: ajaxHandler didn\'t receive datasrcinx"');
        }
        self::$sessDbKey = "db:$pageId:$dataSrcInx:file";
        self::$sessCalRecKey = "pfy.cal.$pageId:$dataSrcInx";
        self::$sessRec = kirby()->session()->get(self::$sessCalRecKey, []);

        // handle lockRec:
        if (isset($_GET['lockRec'])) {
            self::lockRec($_GET['lockRec']);
            unset($_GET['lockRec']);
        }

        // handle unlockRec:
        if (isset($_GET['unlockRec'])) {
            self::unlockRec($_GET['unlockRec']);
            unset($_GET['unlockRec']);
        }

        // handle getRec:
        if (isset($_GET['getRec'])) {
            self::getRec($_GET['getRec']);
            unset($_GET['getRec']);
        }

        // handle calendar requests:
        if (isset($_GET['calendar'])) {
            self::handleCalendarRequests();
            unset($_GET['calendar']);
        }

        exit('"not ok: command unknown"');
    } // exec


    /**
     * @return void
     * @throws \Exception
     */
    public static function serverLog(): void
    {
        if (!$text = ($_REQUEST['log']??'').($_REQUEST['text']??'')) {
            return;
        }
        if ($logFile = ($_REQUEST['filename']??'')) {
            $logFile = basename($logFile);
        }

        if (!is_string($text)) {
            $text = json_encode($text);
        }
        if (!defined('PFY_LOGS_PATH')) {
            define('PFY_LOGS_PATH', 'site/logs/');
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        if (option('pgfactory.pagefactory-elements.options.debug_logIP', false)) {
            $text = "[$ip]  $text";
        }
        require_once 'site/plugins/pagefactory/src/helper.php';
        mylog($text, $logFile);
        exit('"ok"');
    } // serverLog


    /**
     * @param string $recKey
     * @return void
     */
    private static function lockRec(string $recKey): void
    {
        $rec = self::findRec($recKey);
        if ($rec) {
            try {
                $rec->lock();
                exit('"ok"');
            } catch (\Exception $e) {
                exit('"rec locked"');
            }
        } else {
            exit('"recKey unknown"');
        }
    } // lockRec


    /**
     * @param string $recKey
     * @return void
     */
    private static function unlockRec(string $recKey): void
    {
        $rec = self::findRec($recKey);
        if ($rec) {
            $rec->unlock();
            exit('"ok"');
        } else {
            exit('"recKey unknown"');
        }
    } // unlockRec


    /**
     * @param string $recKey
     * @return void
     */
    private static function getRec(string $recKey): void
    {
        $rec = self::findRec($recKey);
        if (!$rec) {
            exit('"rec not found"');
        }
        // lock record, if requested:
        if (isset($_GET['lock']) && !$rec->lock(blocking: true)) {
            exit('"locked"');
        }

        // get data rec:
        $recData = $rec->data();
        if ($rec->isLocked()) {
            $recData['_state'] = 'locked';
        }

        // avoid sending values for password fields (even though they are only a hash):
        // Note: at this point we only have the field name, not the actual type, so it's a best guess.
        array_walk($recData, function (&$v, $k){
            $v = str_contains($k, 'passwor') ? '' : $v;
        });
        exit(json_encode($recData));
    } // getRec


    /**
     * @param string $recKey
     * @return mixed
     * @throws \Exception
     */
    private static function findRec(string $recKey): mixed
    {
        if (!$recKey) {
            exit('"recKey unknown"');
        }

        $db = self::openDb();
        return $db->find($recKey);
    } // findRec


    /**
     * @return object|DataSet
     * @throws \Exception
     */
    private static function openDb(): object
    {
        $file = kirby()->session()->get(self::$sessDbKey, false);
        if (!$file) {
            exit('"Error: file unknown"');
        }
        $db = new DataSet($file, [
            'masterFileRecKeyType' => 'index',
            'obfuscateRecKeys' => true,
        ]);
        return $db;
    } // openDb


    /**
     * @return void
     */
    private static function handleCalendarRequests(): void
    {
        if (isset($_GET['get'])) {
            exit(json_encode(self::getRecs()));
        }
        if (isset($_GET['getCalRec'])) {
            exit(json_encode(self::getCalRec()));
        }
        if (isset($_GET['mode'])) {
            exit(self::saveMode());
        }
        if (isset($_GET['modifyRec'])) {
            exit(self::modifyCalRec());
        }
    } // handleCalendarRequests


    /**
     * @return array
     */
    private static function getRecs(): array
    {
        $data = self::_getRecs();
        if (!$data) {
            exit(json_encode($data));
        }
        $data1 = [];
        foreach ($data as $i => $rec) {
            $data1[$i] = self::_assembleRec($rec);
        }
        return $data1;
    } // getRecs


    /**
     * @param array $rec
     * @return array
     * @throws \Exception
     */
    private static function _assembleRec(array $rec): array
    {
        $data = [];
        $data['start'] = $rec['start'];
        $data['end']   = $rec['end'];
        $data['title'] = $rec['title']??'';
        $data['_creator'] = $rec['creator']??'';

        $template = false;
        $templates = self::getTemplates();
        if (is_string($templates)) {
            $template = $templates;
        } else {
            if ($category = $rec['category'] ?? false) {
                if (isset($templates[$category])) {
                    $template = $templates[$category];
                }
            }
            if (!$template && isset($templates['_'])) {
                $template = $templates['_'];
            } else {
                $file = self::$sessRec['template'];
                mylog("Error: no matching template found in '$file'.");
            }
        }
        if ($template) {
            $data['title'] = self::compileRec($template, $rec);
        }
        return $data;
    } // _assembleRec


    /**
     * @return array
     * @throws \Exception
     */
    private static function _getRecs(): array
    {
        $from = str_replace(' ','T', get('start'));
        $till = str_replace(' ','T', get('end'));
        self::saveInitialDate($from);

        if ($categories = self::$sessRec['categories']) {
            $categories = str_replace(' ', '', $categories);
        }

        $db = self::openDb();
        $data = $db->data(includeMetaFields:true, recKeyType: 'index');
        foreach ($data as $i => $rec) {
            if ($rec['start'] < $from || $rec['end'] > $till) {
                unset($data[$i]);
                continue;
            }
            if ($categories) {
                $cat = $rec['category']??'unknown';
                if (!str_contains(",$categories,", ",$cat,")) {
                    unset($data[$i]);
                }
            }
            if ($rec['allday']??false) {
                $data[$i]['start'] = substr($rec['start'], 0, 10);
                $data[$i]['end'] = substr($rec['end'], 0, 10);
            } else {
                if (strlen($rec['start']) < 16) {
                    $data[$i]['start'] = substr($rec['start'], 0, 10).'T09:00';
                    $data[$i]['end'] = substr($rec['end'], 0, 10).'T10:00';
                }
            }
        }
        return array_values($data);
    } // getRecs


    /**
     * @param string $from
     * @return void
     */
    private static function saveInitialDate(string $from): void
    {
        if (self::$sessRec['mode'] === 'dayGridMonth') {
            $fromT = strtotime($from);
            if (intval(date('j', $fromT)) < 15) {
                $from = date('Y-m-01', $fromT);
            } else {
                $from = date('Y-m-01', strtotime('+1 month', $fromT));
            }
        }
        self::$sessRec['date'] = substr($from,0, 10);
        kirby()->session()->set(self::$sessCalRecKey, self::$sessRec);
    } // saveInitialDate


    /**
     * @return string
     */
    private static function saveMode(): string
    {
        self::$sessRec['mode'] = $_GET['mode'];
        kirby()->session()->set(self::$sessCalRecKey, self::$sessRec);
        return '"ok"';
    } // saveMode


    /**
     * @return string
     * @throws \Exception
     */
    private static function modifyCalRec(): string
    {
        $edPerm = self::$sessRec['edit'];
        if (!$edPerm) {
            return '"no permission"';
        }

        $recKey = $_GET['modifyRec'];
        $rec = self::findRec($recKey);
        if (!$rec) {
            return '"rec not found"';
        }

        if (!$rec->isLocked()) {
            if (isset($_GET['start'])) {
                $rec->update('start', $_GET['start'], false);
            }
            if (isset($_GET['end'])) {
                $rec->update('end', $_GET['end'], false);
            }
            $rec->flush();
        }
        return '"ok"';
    } // modifyCalRec


    /**
     * @return array
     * @throws \Exception
     */
    private static function getCalRec(): array
    {
        $recKey = $_GET['getCalRec'];
        $rec = self::findRec($recKey);
        if (!$rec) {
            exit('"rec not found"');
        }
        return self::_assembleRec($rec);
    } // getCalRec


    /**
     * @return mixed|string|null
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private static function getTemplates()
    {
        if (self::$templates) {
            return self::$templates;
        }
        return self::$templates = loadFile(self::$sessRec['template']);
    } // getTemplates


    /**
     * @param string $template
     * @param array $eventRec
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private static function compileRec(string $template, array $eventRec): string
    {
        $wrapperClass = 'pfy-event-'.translateToClassName($eventRec['category']??'pfy-event-wrapper');
        $recKey = $eventRec['_reckey']??false;
        $creator = $eventRec['creator']??'';
        $fromTime = substr($eventRec['start'], -5);
        $tillTime = substr($eventRec['end'], -5);
        $timeRange = "$fromTime - $tillTime";
        $eventRec['time-range'] = $timeRange;

        // case 'allday' event:
        if (strlen($eventRec['start']) < 16) {
            if (self::$templates['allday']??false) {
                $template = self::$templates['allday'];
            }
        }

        // replace patterns %key% with value from data-rec:
        $str = self::resolveVariables($template, $eventRec);

        if (str_contains($str, '{{')) {
            // execute PageFactory Macros:
            $str = TransVars::executeMacros($str, onlyMacros: true);

            // compile templage with Twig:
            $loader = new \Twig\Loader\ArrayLoader([
                'index' => $str,
            ]);
            $twig = new \Twig\Environment($loader);
            $str = $twig->render('index', $eventRec);
            $str = markdown($str);
        }
        $str = <<<EOT
<span class="$wrapperClass" data-reckey="$recKey" data-creator="$creator">
$str
</span>

EOT;

        return $str;
    } // compileRec


    /**
     * @param string $template
     * @param array $variables
     * @return string
     */
    private static function resolveVariables(string $template, array $variables): string
    {
        // replace variables:
        foreach ($variables as $key => $value) {
            $template = str_replace("%$key%", "$value", $template);
        }
        // remove unresolved variables:
        $template = preg_replace('/(?<!\{)%.{1,15}%/', '', $template);
        return $template;
    } // resolveVariables


    /**
     * @param string|array $data
     * @return string|array
     */
    private static function deObfuscateRecKeys(string|array $data): string|array
    {
        $pageId = self::$pageId;
        $sessKey = "obfuscate:$pageId:tableRecKeyTab";
        $session = kirby()->session();
        $tableRecKeyTab = $session->get($sessKey);
        if (is_string($data)) {
            if ($tableRecKeyTab && ($realKey = array_search($data, $tableRecKeyTab))) {
                $data = $realKey;
            }
        } elseif (is_array($data)) {
            foreach ($data as $key => $val) {
                if ($realKey = array_search($val, $tableRecKeyTab)) {
                    $data[$key] = $realKey;
                }
            }
        }
        return $data;
    } // deObfuscateRecKeys


} // AjaxHandler

