<?php

/*
 * Handler for ajax-requests originating from PfyForm client.
 * Requires session-variable defining the data-source, which is only defined if user is FormAdmin.
 */
namespace PgFactory\PageFactoryElements;


use Kirby\Data\Yaml;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\createHash;
use PgFactory\PageFactory\DataStore;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\translateToClassName;
use function PgFactory\PageFactory\mylog;


require_once __DIR__ . "/../../pagefactory/src/helper.php";


class AjaxHandler
{
    private static string $pageId;
    private static string $dataSrcInx;
    private static string $sessDbFileKey;
    private static string $sessCalRecKey;
    private static array $sessRec;
    private static bool $writePermission;
    private static array $categories;
    private static object|null $db = null;


    /**
     * @param object $result
     * @return void
     */
    public static function exec(object $result): void
    {
        if ($val = get('count')) {
            self::handleCountRequests($val);
        }

        $pageId = self::$pageId = $result->id();
        $dataSrcInx = self::$dataSrcInx = get('datasrcinx', null);
        if (!$dataSrcInx || ($dataSrcInx === 'undefined')) {
            exit('"not ok: ajaxHandler didn\'t receive datasrcinx"');
        }
        self::$sessDbFileKey = "db:$pageId:$dataSrcInx:file";
        self::$sessCalRecKey = "pfy.cal.$pageId:$dataSrcInx";

        $session = kirby()->session();
        PageFactory::$dataPath = $session->get('pfy.dataPath');
        PageFactory::$customConfigPath = $session->get('pfy.configPath');
        $accessPermission = (string)$session->get("pfy.$pageId.accessPermission", '');
        if (!$accessPermission) {
            exit('"access denied"');
        }
        self::$writePermission = str_contains($accessPermission, 'write');


        // handle lockRec:
        if ($val = get('lockRec')) {
            self::lockRec($val);
        }

        // handle unlockRec:
        if ($val = get('unlockRec')) {
            self::unlockRec($val);
        }

        // handle unlockAll:
        if (get('unlockAll') !== null) {
            self::unlockAllRecs();
        }

        // handle getRec:
        if ($val = get('getRec')) {
            self::getRec($val);
        }

        // handle calendar requests:
        if (get('calendar') !== null) {
            self::handleCalendarRequests();
        }

        if (get('writable') !== null) {
            self::handleWritableWidgetRequests();
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
            define('PFY_LOGS_PATH', PFY_KIRBY_BASE_PATH . '/site/logs/');
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        if (option('pgfactory.pagefactory-elements.debug_logIP', false)) {
            $text = "[$ip]  $text";
        }
        require_once PFY_KIRBY_BASE_PATH . 'site/plugins/pagefactory/src/helper.php';
        mylog($text, $logFile);
        exit('"ok"');
    } // serverLog


    /**
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private static function handleCountRequests(string $countRequest): void
    {
        $counterFile = 'clicks/click-counter.txt';
        if (!defined('PFY_LOGS_PATH')) {
            define('PFY_LOGS_PATH', PFY_KIRBY_BASE_PATH . '/site/logs/');
        }
        $file = PFY_LOGS_PATH . $counterFile;

        if ($countRequest === 'true') {
            $key = PFY_PAGE_ID;
        } else {
            $key = $countRequest;
        }

        require_once PFY_KIRBY_BASE_PATH . 'site/plugins/pagefactory/src/helper.php';
        if (!is_file($file)) {
            preparePath($file);
            $header = 'since: ' . date('Y-m-d H:i:s') . "\n";
            file_put_contents($file, $header);
        }

        $yaml = file_get_contents($file);
        $data = Yaml::decode($yaml);
        if (isset($data[$key])) {
            $data[$key] = intval($data[$key]) + 1;
        } else {
            $data[$key] = 1;
        }
        $yaml = Yaml::encode($data);
        file_put_contents($file, $yaml);
        exit('"ok"');
    } // handleCountRequests


    /**
     * @param string $recKey
     * @return void
     */
    private static function lockRec(string $recKey): void
    {
        if (!self::$writePermission) {
            exit('"request denied"');
        }
        $db = self::openDb();
        $res = $db->lockRec($recKey);
        if (!$res) {
            exit("\"failed to lock rec '$recKey'\"");
        }
    } // lockRec


    /**
     * @param string|bool $recKey
     * @return void
     */
    private static function unlockRec(string|bool $recKey): void
    {
        if (!self::$writePermission) {
            exit('"request denied"');
        }
        $db = self::openDb();
        $res = $db->unlockRec($recKey);
        if (!$res) {
            exit("\"failed to unlock rec '$recKey'\"");
        }
    } // unlockRec


    /**
     * @return void
     * @throws \Exception
     */
    private static function unlockAllRecs(): void
    {
        if (!self::$writePermission) {
            exit('"request denied"');
        }
        (self::openDb())->unlockAllRecs();
        exit('"ok"');
    } // unlockAllRecs


    /**
     * @param string $recKey
     * @return void
     */
    private static function getRec(string $recKey): void
    {
        $rec = self::getDataRec($recKey);
        if (!$rec) {
            exit('"rec not found"');
        }
        if ($formInx = get('retainData')) {
            Utils::setSessionVar("form-$formInx", $rec);
        }

        unset($rec[PFY_DB_METAREC_KEY]);
        // avoid sending values for password fields (even though they are only a hash):
        // Note: at this point we only have the field name, not the actual type, so it's a best guess.
        array_walk($rec, function (&$v, $k){
            $v = str_contains($k, 'passwor') ? '' : $v;
        });
        exit(json_encode($rec));
    } // getRec


    /**
     * @param string $recKey
     * @param $includeMeta
     * @return mixed
     * @throws \Exception
     */
    private static function getDataRec(string $recKey, $includeMeta = true): mixed
    {
        if (!$recKey) {
            exit('"recKey unknown"');
        }

        $db = self::openDb();

        // lock record, if requested:
        if (get('lock') !== null) {
            if (!$db->lockRec($recKey)) {
                // rec is locked -> report back:
                exit('"locked"');
            }
        }

        $recKey = $db->find($recKey);
        $rec = $db->getRec($recKey, includeMeta: $includeMeta);
        if (!$rec) {
            exit('"rec not found"');
        }
        return $rec;
    } // getDataRec


    /**
     * @return object|DataStore
     * @throws \Exception
     */
    private static function openDb(string $masterFileRecKeyType = 'index'): object
    {
        if (self::$db !== null) {
            return self::$db;
        }
        $file = kirby()->session()->get(self::$sessDbFileKey, false);
        if (!$file) {
            exit('"Error: file unknown"');
        }
        $db = new DataStore($file, [
            'masterFileRecKeyType' => $masterFileRecKeyType,
        ]);
        self::$db = $db;
        return $db;
    } // openDb


    /**
     * @return void
     */
    private static function handleCalendarRequests(): void
    {
        self::$sessRec = kirby()->session()->get(self::$sessCalRecKey, []);
        self::$categories = explode(',', self::$sessRec['categories']??'');

        if (get('get') !== null) {
            exit(json_encode(self::getCalRecs()));
        }
        if (get('getCalRec') !== null) {
            exit(json_encode(self::getCalRec()));
        }
        if (get('mode') !== null) {
            exit(self::saveCalMode());
        }
        if (get('modifyRec') !== null) {
            exit(self::modifyCalRec());
        }
        if (get('delete') !== null) {
            exit(self::deleteCalRec());
        }
        if (get('duplicate') !== null) {
            exit(self::duplicateCalRec());
        }
    } // handleCalendarRequests


    /**
     * @return string
     * @throws \Exception
     */
    private static function deleteCalRec(): string
    {
        if (!self::$writePermission) {
            exit("request denied");
        }
        $recKey = get('delete');
        if ($groupId = get('group')) {
            return self::deleteCalGroup($recKey, $groupId);
        }

        $db = self::openDb();
        if (self::$sessRec['freezePast']) {
            $rec = $db->getRec($recKey);
            $start = $rec['start']??'';
            if ($start < date('Y-m-d\TH:i')) {
                mylog("Rec $recKey not deleted because in the past");
                return '"Event in the past not deleted"';
            }
        }
        $db->deleteRec($recKey, flush:true);
        mylog("Rec $recKey deleted");
        return '"ok"';
    } // deleteCalRec


    /**
     * If a calendar event was created by an RRule, it carries a _ev_group element.
     * Deleting the entire group means finding all events with that _ev_group
     * Event group members prior to the currently selected one are not deleted.
     * @param string $thisRecKey
     * @param string $groupId
     * @return string
     * @throws \Exception
     */
    private static function deleteCalGroup(string $thisRecKey, string $groupId): string
    {
        $result = '"ok"';
        $db = self::openDb();
        $data = $db->data(includeMetaFields: true);
        usort($data, function($a, $b) {
            return strcmp($a['start'], $b['start']);
        });
        $now = '99999999999';
        $frozen = self::$sessRec['freezePast'];
        foreach ($data as $rec) {
            $recKey = $rec['_reckey'];
            if (($parentId = ($rec['_ev_group']??false)) && ($parentId === $groupId)) {
                if (($now === '99999999999') && $thisRecKey === $recKey) {
                    if ($frozen) {
                        $now = date('Y-m-d\TH:i');
                    } else {
                        $now = $rec['start'];
                    }
                }
                if ($rec['end'] >= $now) {
                    $db->deleteRec($recKey);
                    mylog("Rec $recKey deleted");
                } elseif ($frozen) {
                    $result = '"Some event in the past not deleted"';
                }
            }
        }
        $db->flush();
        mylog("Group $groupId deleted");
        return $result;
    } // deleteCalGroup


    /**
     * @return string
     * @throws \Exception
     */
    private static function duplicateCalRec(): string
    {
        if (!self::$writePermission) {
            exit("request denied");
        }
        $recKey = get('duplicate');
        $rec = self::getDataRec($recKey);
        if (self::$sessRec['freezePast']) {
            $start = $rec['start']??'';
            if ($start < date('Y-m-d\TH:i')) {
                mylog("Rec $recKey not duplicated because in the past");
                return '"Event in the past not duplicated"';
            }
        }
        $recKey = createHash();
        $db = self::openDb();
        $db->addRec($rec, true, $recKey);
        mylog("Event $recKey duplicated");
        return '"ok"';
    } // duplicateCalRec


    /**
     * @return array
     */
    private static function getCalRecs(): array
    {
        $data = self::_getCalRecs();
        if (!$data) {
            exit(json_encode($data));
        }
        require_once __DIR__ . '/TemplateCompiler.php';
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
        $data['_ev_group'] = $rec['_ev_group']??''; // in case of recurring events
        if ($rec['allday']??false) {
            // fix allday event -> add 1 day to end to conform with user logic:
            $data['end'] = date('Y-m-d', strtotime('+1 day', strtotime($data['end'])));
        }

        $templateOptions = (self::$sessRec['template']??[]);

        // compile event summary:
        $data['summary'] = self::compileRec($rec, $templateOptions);

        // compile event description:
        $data['description'] = self::compileRec($rec, $templateOptions, 'description');

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
        $middle = date('Y-m-d', (strtotime($from) + strtotime($till)) / 2);
        self::saveInitialDate($middle);
        //mylog("Cal: $from – $till, middleDate: $middle", 'calendar-log.txt');

        if ($categories = self::$sessRec['categories']) {
            $categories = str_replace(' ', '', $categories);
        }

        $db = self::openDb();
        $data = $db->data(includeMetaFields:true, recKeyType: 'index');
        foreach ($data as $i => $rec) {
            if ($rec['allday']??false) {
                $start = $rec['start'];
                $end   = $rec['end'];
                if (!(
                    ($start > $from && $end < $till) ||     //   | -- |
                    ($start < $from && $end > $till) ||     // --|----|--
                    ($start < $from && $end > $from) ||     // --|--  |
                    ($start < $till && $end > $till)        //   |  --|--
                    )) {
                    unset($data[$i]);
                    continue;
                }

            } else {
                if ($rec['start'] < $from || $rec['end'] > $till) {
                    unset($data[$i]);
                    continue;
                }
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
        self::$sessRec['initialDate'] = substr($from,0, 10);
        kirby()->session()->set(self::$sessCalRecKey, self::$sessRec);
    } // saveInitialDate


    /**
     * @return string
     */
    private static function saveCalMode(): string
    {
        if (get('catfilter') !== null) {
            self::$sessRec['catfilter'] = get('mode');
        } else {
            self::$sessRec['mode'] = get('mode');
        }
        kirby()->session()->set(self::$sessCalRecKey, self::$sessRec);
        return '"ok"';
    } // saveCalMode


    /**
     * @return string
     * @throws \Exception
     */
    private static function modifyCalRec(): string
    {
        if (!self::$writePermission) {
            exit("request denied");
        }
        $edPerm = self::$sessRec['edit'];
        if (!$edPerm) {
            return '"no permission"';
        }

        $recKey = get('modifyRec');
        $db = self::openDb();
        if ($db->isRecLocked($recKey)) {
            return '"record is locked"';
        }

        $rec = self::getDataRec($recKey);

        if (($start = get('start')) !== null) {
            $rec['start'] = $start;
        }
        if (($end = get('end')) !== null) {
            if (strlen($end) < 16) {
                // case allday event -> need to fix end date:
                $end = date('Y-m-d', strtotime('-1 day', strtotime($end)));
            }
            $rec['end'] = $end;
        }
        $db->updateRec($rec, $recKey, true);
        return '"ok"';
    } // modifyCalRec


    /**
     * @return array
     * @throws \Exception
     */
    private static function getCalRec(): array
    {
        $recKey = get('getCalRec');
        $rec = self::getDataRec($recKey);
        if (!$rec) {
            exit('"rec not found"');
        }
        return self::_assembleRec($rec);
    } // getCalRec


    /**
     * @return void
     * @throws \Exception
     */
    private static function handleWritableWidgetRequests(): void
    {
        $name = get('name');
        $value = get('value');
        $datasrcinx = get('datasrcinx');
        $datasrcinx = preg_replace('/\W/', '_', $datasrcinx);

        if (!defined('PFY_LOGS_PATH')) {
            define('PFY_LOGS_PATH', PFY_KIRBY_BASE_PATH . '/site/logs/');
        }
        mylog("Writable update: '$datasrcinx:$name' <= '$value'", 'writable-log.txt');
        $db = self::openDb();

        $data = $db->data();
        $rec = $data[$datasrcinx] ?? [];
        $rec[$name] = $value;
        $db->updateRec($rec, $datasrcinx, flush: true);
        $res = json_encode([$name => $value]);
        exit($res);
    } // handleWritableWidgetRequests


    /**
     * @param string $template
     * @param array $eventRec
     * @return string
     */
    private static function compileRec(array $eventRec, array $templateOptions, string $elemToUse = 'element'): string
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

        if (isset($templateOptions['templates'][$category][$elemToUse])) {
            // requested template found:
            $templateOptions['templates'][$category] = $templateOptions['templates'][$category][$elemToUse];
            // requested template not found, but default found:
        } elseif (isset($templateOptions['templates']['_'][$elemToUse])) {
            $templateOptions['templates'][$category] = $templateOptions['templates']['_'][$elemToUse];
        } else {
            // no matching template found, falling back to standard template:
            $templateOptions['templates'][$category] = self::getDefaultEventTemplate($eventRec);
        }
        $str = TemplateCompiler::compile($eventRec, $templateOptions);
        if ($eventRec['_rrule']??false) {
            $wrapperClass .= ' pfy-cal-repeating';
        }

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
     * @param array $vars
     * @return string
     */
    private static function getDefaultEventTemplate(array $vars): string
    {
        $template = '';
        foreach (array_keys($vars) as $key) {
            if ((($key[0]??'') === '_') || str_contains('allday,category,rrule,maxCount,creator', $key)) {
                continue;
            }
            $template .= "<div><span>$key:</span> <span>{{ $key }}</span></div>\n";
        }

        return $template;
    } // getDefaultEventTemplate


} // AjaxHandler

