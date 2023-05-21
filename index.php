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
//                    if ($locales = Kirby\Toolkit\I18n::fallbacks()) {
//                        $pat = implode('|', $locales);
//                        $slug = preg_replace("#^($pat)/#", '', $slug);
//                    }
                    PageFactory::$slug = $slug;
                    PageFactory::$urlToken = $m[2];

//                    $appUrl = dirname(substr($_SERVER['SCRIPT_FILENAME'], -strlen($_SERVER['SCRIPT_NAME']))).'/';
//                    $target = $appUrl . $slug;
//                    header("Location: $target");
//                    exit;

                    // check pattern 'ABCDEF', i.e. page without slug:
                } elseif (preg_match('|^ ([A-Z]{5,15})$|x', $slug, $m)) {
                    PageFactory::$slug = '';
                    PageFactory::$urlToken = $m[1];
                    return site()->visit(page());
                }
                return $this->next();
            }
        ],
    ],

    'hooks' => [
        'route:before' => function (\Kirby\Http\Route $route, string $path) {
            // intercept serverLog request: ?log
            if (isset($_GET['log'])) {
                require_once __DIR__ . "/src/pe_helper.php";
                serverLog();
                unset($_GET['log']);
            }
        },
    ], // hooks

]);

