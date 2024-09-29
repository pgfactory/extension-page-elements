<?php

/*
 * Handler for ajax-requests originating from PfyForm client.
 * Requires session-variable defining the data-source, which is only defined if user is FormAdmin.
 */
namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use function PgFactory\PageFactory\createHash;
use function PgFactory\PageFactory\getFile;
use PgFactory\PageFactory\DataSet;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\resolvePath;
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
    private static array $categories;


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

        if (isset($_GET['writable'])) {
            self::handleWritableWidgetRequests();
            unset($_GET['writable']);
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
    private static function openDb(string $masterFileRecKeyType = 'index'): object
    {
        $file = kirby()->session()->get(self::$sessDbKey, false);
        if (!$file) {
            exit('"Error: file unknown"');
        }
        $db = new DataSet($file, [
            'masterFileRecKeyType' => $masterFileRecKeyType,
            'obfuscateRecKeys' => true,
        ]);
        return $db;
    } // openDb


    /**
     * @return void
     */
    private static function handleCalendarRequests(): void
    {
        self::$sessRec = kirby()->session()->get(self::$sessCalRecKey, []);
        self::$categories = explode(',', self::$sessRec['categories']??[]);

        if (isset($_GET['get'])) {
            exit(json_encode(self::getCalRecs()));
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
        if (isset($_GET['delete'])) {
            exit(self::deleteRec());
        }
        if (isset($_GET['duplicate'])) {
            exit(self::duplicateRec());
        }
    } // handleCalendarRequests


    /**
     * @return string
     * @throws \Exception
     */
    private static function deleteRec(): string
    {
        $recKey = get('delete');
        $db = self::openDb();
        $dataRec = $db->find($recKey);
        $dataRec->delete(true);
        mylog("Rec $recKey deleted");
        return '"ok"';
    } // deleteRec


    /**
     * @return string
     * @throws \Exception
     */
    private static function duplicateRec(): string
    {
        $recKey = get('duplicate');
        $db = self::openDb();
        $dataSet = $db->find($recKey);
        $rec = $dataSet->data();
        $recKey = createHash();
        $db->addRec($rec, true, $recKey);
        mylog("Rec $recKey duplicated");
        return '"ok"';
    } // duplicateRec


    /**
     * @return array
     */
    private static function getCalRecs(): array
    {
        $data = self::_getCalRecs();
        if (!$data) {
            exit(json_encode($data));
        }
        require_once 'site/plugins/pagefactory-pageelements/src/TemplateCompiler.php';
        $data1 = [];
        foreach ($data as $i => $rec) {
            $data1[$i] = self::_assembleRec($rec);
        }
        return $data1;
    } // getCalRecs


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
        $data['_creator'] = $rec['creator']??'';

        $templateOptions = (self::$sessRec['template']??[]);
        $selector = $rec['category'] ?? null;

        // compile event summary:
        $template = TemplateCompiler::getTemplate($templateOptions, $selector);
        if (!$template) {
            mylog('Error: calendar template missing.');
            exit(json_encode('Error: calendar template missing.'));
        }
        $data['summary']       = self::compileRec($template, $rec, $templateOptions);

        // compile event description:
        $template = TemplateCompiler::getTemplate($templateOptions, $selector, 'description');
        $data['description'] = self::compileRec($template, $rec, $templateOptions, 'description');

        return $data;
    } // _assembleRec


    /**
     * @return array
     * @throws \Exception
     */
    private static function _getCalRecs(): array
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
    } // _getCalRecs


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
     * @return void
     * @throws \Exception
     */
    private static function handleWritableWidgetRequests()
    {
        $name = $_GET['name'] ?? null;
        $value = $_GET['value'] ?? null;
        $datasrcinx = $_GET['datasrcinx'] ?? null;
        $db = self::openDb('_origRecKey');
        $data = $db->data();
        if (isset($data[$datasrcinx])) {
            $rec = &$data[$datasrcinx];
            $rec[$name] = $value;
        } else {
            $data[$datasrcinx] = [$name => $value];
        }
        $db->write($data);

        // read back stored value:
        $data = $db->data();
        $value = $data[$datasrcinx][$name]??'';
        $res = json_encode([$name => $value]);
        exit($res);
    } // handleWritableWidgetRequests


    /**
     * @param string $template
     * @param array $eventRec
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private static function compileRec(string $template, array $eventRec, array $templateOptions, string $elemToUse = 'element'): string
    {
        $category = $eventRec['category']??'';
        $catInx = array_search($category, self::$categories);
        $wrapperClass = 'pfy-event-'.translateToClassName($eventRec['category']??'pfy-event-wrapper');
        if ($catInx) {
            $wrapperClass .= " pfy-category-$catInx";
        }

        $recKey = $eventRec['_reckey']??false;
        $creator = $eventRec['creator']??'';
        $fromTime = substr($eventRec['start'], -5);
        $tillTime = substr($eventRec['end'], -5);
        $timeRange = "<span class='pfy-cal-start-time'>$fromTime</span><span class='pfy-cal-end-time'> – $tillTime</span>";
        $eventRec['time'] = $timeRange;

        // case 'allday' event:
        if (strlen($eventRec['start']) < 16) {
            if (self::$templates['allday']??false) {
                $template = self::$templates['allday'];
            }
        }
        $str = TemplateCompiler::compile($template, $eventRec, $templateOptions);

        if ($elemToUse === 'element') {
            $str = <<<EOT
<div class="pfy-cal-summary $wrapperClass" data-reckey="$recKey" data-creator="$creator">
$str
</div>

EOT;
        } elseif ($elemToUse === 'description') {
            $str = "<div class='pfy-cal-description'>\n$str\n</div>";
        }
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

