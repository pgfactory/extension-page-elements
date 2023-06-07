<?php

/**
 *
 * PageElements
 *      PageFactory-Extension for Kirby 3
 *
 */

use Kirby\Cms\App as Kirby;
use Usility\PageFactory\PageFactory as PageFactory;

const LOG_FOLDER = 'site/logs/';
const LOG_FILENAME = 'pagefactory.txt';

if (basename(dirname(__FILE__))[0] === '#') {
    return;
}

Kirby::plugin('pgfactory/pagefactory-pageelements', [
    'routes' => [
        [
            // catch tokens in URLs: (i.e. all capital letter or digit codes like 'p1/A1B2C3')
            'pattern' => '(:all)',
            'action'  => function ($slug) {
                // check pattern 'p1/ABCDEF':
                if (preg_match('|^(.*?) / ([A-Z]{5,15})$|x', $slug, $m)) {
                    $slug = $m[1];
                    PageFactory::$slug = $slug;
                    PageFactory::$urlToken = $m[2];

                // check pattern 'ABCDEF', i.e. page without slug:
                } elseif (preg_match('|^ ([A-Z]{5,15})$|x', $slug, $m)) {
                    PageFactory::$slug = '';
                    PageFactory::$urlToken = $m[1];
                    return site()->visit(page()); //ToDo: fix
                }
                return $this->next();
            }
        ],
    ],

    'hooks' => [
        'route:before' => function (\Kirby\Http\Route $route, string $path) {
            // intercept serverLog request: ?log
            if (isset($_GET['log'])) {
                require_once __DIR__ . "/src/ajax_server.php";
                serverLog();
                unset($_GET['log']);
            }

        },
        'route:after' => function ($route, $path, $method, $result, $final) {
            // intercept serverLog request: ?getRec and ?lockRec
            if ($final && ($_GET??false)) {
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

