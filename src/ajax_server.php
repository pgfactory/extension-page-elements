<?php

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\DataSet;


require_once __DIR__ . "/../../pagefactory/src/helper.php";


function ajaxHandler(object $result): void
{
    $pageId = $result->id();

    // handle lockRec:
    if (isset($_GET['lockRec'])) {
        lockRec($_GET['lockRec'], $pageId);
        unset($_GET['lockRec']);
    }

    // handle unlockRec:
    if (isset($_GET['unlockRec'])) {
        unlockRec($_GET['unlockRec'], $pageId);
        unset($_GET['unlockRec']);
    }

    // handle unlockAll:
    if (isset($_GET['unlockAll'])) {
        unlockAll($pageId);
        unset($_GET['unlockAll']);
    }

    // handle getRec:
    if (isset($_GET['getRec'])) {
        getRec($_GET['getRec'], $pageId);
        unset($_GET['getRec']);
    }
} // ajaxHandler


/**
 * Intercepts HTTP requests '?log', sends them to log file and exits immediately.
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



function lockRec(string $recKey, string $pageId): void
{
    $rec = findRec($recKey, $pageId);
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


function unlockAll(string $pageId): void
{
    $db = openDb($pageId);
    $db->unlockRecs();
     exit('"ok"');
} // unlockRec


function unlockRec(string $recKey, string $pageId): void
{
    $rec = findRec($recKey, $pageId);
    if ($rec) {
        $rec->unlock();
        exit('"ok"');
    } else {
        exit('"recKey unknown"');
    }
} // unlockRec


function getRec($recKey, $pageId): void
{
    $recData = [];
    $rec = findRec($recKey, $pageId);
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


function findRec($recKey, $pageId)
{
    $recKey = deObfuscateRecKeys($recKey, $pageId);
    if (!$recKey) {
        exit('"recKey unknown"');
    }

    $db = openDb($pageId);
    return $db->find($recKey);
} // findRec


function openDb($pageId)
{
    $session = kirby()->session();
    $sessKey = "form:$pageId:file";
    $file = $session->get($sessKey);
    if (!$file) {
        exit('"Error: file unknown"');
    }
    $db = new DataSet($file, [
        'masterFileRecKeyType' => 'index',
    ]);
    return $db;
} // openDb


function deObfuscateRecKeys(string|array $data, string|false $pageId = false): string|array
{
    $pageId = $pageId ?: PageFactory::$pageId;
    $sessKey = "form:$pageId:tableRecKeyTab";
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


function obfuscateRecKey(string $key, string|false $pageId = false): string
{
    $pageId = $pageId ?: PageFactory::$pageId;
    $sessKey = "pfy:$pageId:keys";
    $session = kirby()->session();
    $tableRecKeyTab = $session->get($sessKey);
    if (!$tableRecKeyTab || !($obfuscatedKey = array_search($key, $tableRecKeyTab))) {
        $obfuscatedKey = \Usility\PageFactory\createHash();
    }
    $tableRecKeyTab[$obfuscatedKey] = $key;
    $session->set($sessKey, $tableRecKeyTab);
    return $obfuscatedKey;
} // deObfuscateRecKey


function deObfuscateRecKey(string $key, string|false $pageId = false): string
{
    $pageId = $pageId ?: PageFactory::$pageId;
    $sessKey = "pfy:$pageId:keys";
    $session = kirby()->session();
    $tableRecKeyTab = $session->get($sessKey);
    if ($tableRecKeyTab && (isset($tableRecKeyTab[$key]))) {
        $key = $tableRecKeyTab[$key];
    }
    return $key;
} // deObfuscateRecKey


