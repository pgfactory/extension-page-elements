<?php

/**
 *
 * PageElements
 *      PageFactory-Extension for Kirby 3
 *
 */

use Kirby\Cms\App as Kirby;
use PgFactory\PageFactory\PageFactory as PageFactory;

const LOG_FOLDER = 'site/logs/';
const LOG_FILENAME = 'pagefactory.txt';

if (basename(dirname(__FILE__))[0] === '#') {
    return;
}

Kirby::plugin('pgfactory/pagefactory-pageelements', [
    'hooks' => [
        'route:before' => function (\Kirby\Http\Route $route, string $path) {
            // intercept serverLog request: ?log
            if (isset($_GET['log']) && isset($_REQUEST['ajax'])) {
                require_once __DIR__ . "/src/ajax_server.php";
                serverLog();
                unset($_GET['log']);
            }
        },

        'route:after' => function ($route, $path, $method, $result, $final) {
            // intercept requests: ?getRec and ?lockRec
            if ($final && ($_GET??false) && isset($_REQUEST['ajax'])) {
                if (!preg_match('/^(panel|media|api)/', $path)) {
                    if (!defined('PFY_CACHE_PATH')) { // available in extensions
                        define('PFY_CACHE_PATH', 'site/cache/pagefactory/'); // available in extensions
                    }
                    require_once __DIR__ . "/src/ajax_server.php";
                    ajaxHandler($result);
                }
            }
            return $result;
        }
    ], // hooks

]);

