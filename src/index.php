<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\PageFactory;

define('PE_FOLDER_NAME',  basename(dirname(__DIR__)).'/');
define('PAGE_ELEMENTS_PATH', 'site/plugins/'.PE_FOLDER_NAME);
define('PAGE_ELEMENTS_ASSETS_PATH', PAGE_ELEMENTS_PATH . 'assets/');
define('PAGE_ELEMENTS_ASSETS_ROOT', PFY_APP_BASE_PATH.PAGE_ELEMENTS_ASSETS_PATH);
define('PAGE_ELEMENTS_URL', PFY_APP_BASE_URL . 'site/plugins/'.PE_FOLDER_NAME . 'assets/');
define('SYSTEM_PATH',       dirname(__DIR__).'/'); //???
define('SYSTEM_CACHE_PATH', PFY_CACHE_PATH);
define('PATH_TO_APP_ROOT',  '');


define('PE_ASSET_LOCATIONS', [
    PAGE_ELEMENTS_PATH.'assets/css/' => PAGE_ELEMENTS_PATH.'scss/*',
]);


const PE_URL_DEFINITIONS = [
    'POPUPS' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/-popup.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/-popup.js',
    ],
    'MESSAGES' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/-message.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/message.js',
    ],
    'TABLES' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/-table.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/-table.js',
    ],
    'FORMS' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/-forms.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/-forms.js',
    ],
    'ENLIST' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/-enlist.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/-enlist.js',
    ],
    'EVENTS' => [
        //PAGE_ELEMENTS_ASSETS_PATH.'css/-events.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/-events.js',
    ],
    'DATATABLES' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/datatables.min.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/datatables.min.js',
    ],
    'REVEAL' => [
        PAGE_ELEMENTS_ASSETS_PATH.'js/reveal.js',
        PAGE_ELEMENTS_ASSETS_PATH.'css/-reveal.css',
    ],
    'LOGIN' => [
        PAGE_ELEMENTS_ASSETS_PATH.'js/login.js',
        PAGE_ELEMENTS_ASSETS_PATH.'css/-login.css',
    ],
    'TOOLTIPS' => [
        PAGE_ELEMENTS_ASSETS_PATH.'css/tippy.min.css',
        PAGE_ELEMENTS_ASSETS_PATH.'js/popper.min.js',
        PAGE_ELEMENTS_ASSETS_PATH.'js/tippy-bundle.umd.min.js',
    ],
    'CALENDAR' => [
        PAGE_ELEMENTS_ASSETS_PATH.'js/swipe.js',
        PAGE_ELEMENTS_ASSETS_PATH.'js/popper.min.js',
        PAGE_ELEMENTS_ASSETS_PATH.'js/tippy-bundle.umd.min.js',
        PAGE_ELEMENTS_ASSETS_PATH.'js/fullcalendar.min.js',
        PAGE_ELEMENTS_ASSETS_PATH.'js/-calendar.js',
        PAGE_ELEMENTS_ASSETS_PATH.'css/-calendar.css',
    ],
    'WRITABLE' => [
        PAGE_ELEMENTS_ASSETS_PATH.'js/writable.js',
        PAGE_ELEMENTS_ASSETS_PATH.'css/-writable.css',
    ],
    'SAYTTS' => [
        PAGE_ELEMENTS_ASSETS_PATH.'js/sayTTS.js',
        PAGE_ELEMENTS_ASSETS_PATH.'css/-sayTTS.css',
    ],
];


require_once __DIR__.'/pe_helper.php';

 // save config and data path for AjaxHandler:
session_start();
$_SESSION['pfy.dataPath'] = PageFactory::$dataPath;  // may depend on onair-state
$_SESSION['pfy.configPath'] = PageFactory::$customConfigPath;
session_write_close();

 // run init-code if requested in config.php:
if ($code = kirby()->option('pgfactory.pagefactory-elements.options.initCode')) {
    $code = PFY_APP_BASE_PATH . 'site/custom/code/'.$code;
    if (file_exists($code)) {
        require_once $code;
    }
}

 // activate site-manager if requested:
if (kirby()->option('pgfactory.pagefactory-elements.options.activateSitemapManager')) {
    require_once __DIR__ . '/SitemapManager.php';
    SitemapManager::updateSitemap();
}

Assets::addAssets(PAGE_ELEMENTS_ASSETS_PATH.'js/pe-helper.js');

Assets::addAssetGroups(PE_URL_DEFINITIONS);

Assets::addAssetLocation(PE_ASSET_LOCATIONS);

if (PageFactory::$dev || PageFactory::$forceAssetsUpdate) {
    compileJs(PAGE_ELEMENTS_PATH.'js/', PAGE_ELEMENTS_ASSETS_PATH.'js/');
}

return 'PageElements';



