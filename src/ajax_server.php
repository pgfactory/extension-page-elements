<?php

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\DataSet;


require_once __DIR__ . "/../../pagefactory/src/helper.php";


function ajaxHandler(object $result): void
{
    $slug = $result->slug();

    // handle lockRec:
    if (isset($_GET['lockRec'])) {
        lockRec($_GET['lockRec'], $slug);
        unset($_GET['lockRec']);
    }

    // handle unlockRec:
    if (isset($_GET['unlockRec'])) {
        unlockRec($_GET['unlockRec'], $slug);
        unset($_GET['unlockRec']);
    }

    // handle unlockAll:
    if (isset($_GET['unlockAll'])) {
        unlockAll($slug);
        unset($_GET['unlockAll']);
    }

    // handle getRec:
    if (isset($_GET['getRec'])) {
        getRec($_GET['getRec'], $slug);
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
    if (!$text = ($_REQUEST['text']??false)) {
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
    exit('ok');
} // serverLog



function lockRec(string $recKey, string $slug): void
{
    $rec = findRec($recKey, $slug);
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


function unlockAll(string $slug): void
{
    $db = openDb($slug);
    $db->unlockRecs();
     exit('"ok"');
} // unlockRec


function unlockRec(string $recKey, string $slug): void
{
    $rec = findRec($recKey, $slug);
    if ($rec) {
        $rec->unlock();
        exit('"ok"');
    } else {
        exit('"recKey unknown"');
    }
} // unlockRec


function getRec($recKey, $slug): void
{
    $recData = [];
    $rec = findRec($recKey, $slug);
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


function findRec($recKey, $slug)
{
    $recKey = deObfuscateRecKeys($recKey, $slug);
    if (!$recKey) {
        exit('"recKey unknown"');
    }

    $db = openDb($slug);
    return $db->find($recKey);
} // findRec


function openDb($slug)
{
    $session = kirby()->session();
    $sessKey = "form:$slug:file";
    $file = $session->get($sessKey);
    if (!$file) {
        exit('"Error: file unknown"');
    }
    $db = new DataSet($file, [
        'masterFileRecKeyType' => 'index',
    ]);
    return $db;
} // openDb


function deObfuscateRecKeys(string|array $data, string|false $slug = false): string|array
{
    $slug = $slug ?: PageFactory::$slug;
    $sessKey = "form:$slug:tableRecKeyTab";
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