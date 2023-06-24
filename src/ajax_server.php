<?php

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\DataSet;


require_once __DIR__ . "/../../pagefactory/src/helper.php";


/**
 * @param object $result
 * @return void
 */
function ajaxHandler(object $result): void
{
    $pageId = $result->id();
    $dataSrcInx = get('datasrcinx', null);
    if (!$dataSrcInx || ($dataSrcInx === 'undefined')) {
        exit('"not ok: ajaxHandler didn\'t receive datasrcinx"');
    }

    // handle lockRec:
    if (isset($_GET['lockRec'])) {
        lockRec($_GET['lockRec'], $pageId, $dataSrcInx);
        unset($_GET['lockRec']);
    }

    // handle unlockRec:
    if (isset($_GET['unlockRec'])) {
        unlockRec($_GET['unlockRec'], $pageId, $dataSrcInx);
        unset($_GET['unlockRec']);
    }

    // handle unlockAll:
    if (isset($_GET['unlockAll'])) {
        unlockAll($pageId, $dataSrcInx);
        unset($_GET['unlockAll']);
    }

    // handle getRec:
    if (isset($_GET['getRec'])) {
        getRec($_GET['getRec'], $pageId, $dataSrcInx);
        unset($_GET['getRec']);
    }
    exit('"not ok: command unknown"');
} // ajaxHandler


/**
 * Intercepts HTTP requests '?log', sends them to log file and exits immediately.
 *  Test: url?ajax&log=Text
 * @return void
 * @throws Exception
 */
function serverLog(): void
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
    if (option('pgfactory.pagefactory-elements.options.log-ip', false)) {
        $text = "[$ip]  $text";
    }
    require_once 'site/plugins/pagefactory/src/helper.php';
    Usility\PageFactory\mylog($text, $logFile);
    exit('"ok"');
} // serverLog


/**
 * @param string $recKey
 * @param string $pageId
 * @param string $dataSrcInx
 * @return void
 */
function lockRec(string $recKey, string $pageId, string $dataSrcInx): void
{
    $rec = findRec($recKey, $pageId, $dataSrcInx);
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
 * @param string $pageId
 * @param string $dataSrcInx
 * @return void
 */
function unlockAll(string $pageId, string $dataSrcInx): void
{
    $db = openDb($pageId, $dataSrcInx);
    $db->unlockRecs();
     exit('"ok"');
} // unlockRec


/**
 * @param string $recKey
 * @param string $pageId
 * @param string $dataSrcInx
 * @return void
 */
function unlockRec(string $recKey, string $pageId, string $dataSrcInx): void
{
    $rec = findRec($recKey, $pageId, $dataSrcInx);
    if ($rec) {
        $rec->unlock();
        exit('"ok"');
    } else {
        exit('"recKey unknown"');
    }
} // unlockRec


/**
 * Test: url?ajax&getRec=HASH&datasrcinx=1
 * @param $recKey
 * @param $pageId
 * @param $dataSrcInx
 * @return void
 */
function getRec($recKey, $pageId, $dataSrcInx): void
{
    $recData = [];
    $rec = findRec($recKey, $pageId, $dataSrcInx);
    if ($rec) {
        // lock record, if requested:
        if (isset($_GET['lock']) && !$rec->lock(blocking: true)) {
            exit('"locked"');
        }

        // get data rec:
        $recData = $rec->data();
        if ($rec->isLocked()) {
            $recData['_state'] = 'locked';
        }
    }
    exit(json_encode($recData));
} // getRec


/**
 * @param $recKey
 * @param $pageId
 * @param $dataSrcInx
 * @return mixed|object|void
 * @throws Exception
 */
function findRec($recKey, $pageId, $dataSrcInx)
{
    if (!$recKey) {
        exit('"recKey unknown"');
    }

    $db = openDb($pageId, $dataSrcInx);
    return $db->find($recKey);
} // findRec


/**
 * @param $pageId
 * @param $dataSrcInx
 * @return DataSet|void
 * @throws Exception
 */
function openDb($pageId, $dataSrcInx)
{
    $session = kirby()->session();
    $fileSessKey = "db:$pageId:$dataSrcInx:file";
    $file = $session->get($fileSessKey);
    if (!$file) {
        exit('"Error: file unknown"');
    }
    $db = new DataSet($file, [
        'masterFileRecKeyType' => 'index',
        'obfuscateSessKey' => "obfuscate:$pageId:keys",
    ]);
    return $db;
} // openDb


/**
 * @param string|array $data
 * @param string|false $pageId
 * @return string|array
 */
function deObfuscateRecKeys(string|array $data, string|false $pageId = false): string|array
{
    $pageId = $pageId ?: PageFactory::$pageId;
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
