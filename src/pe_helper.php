<?php


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

