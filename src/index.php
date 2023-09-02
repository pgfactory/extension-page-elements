<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;

define('PE_FOLDER_NAME',  basename(dirname(__DIR__)).'/');
define('PAGE_ELEMENTS_PATH', 'site/plugins/'.PE_FOLDER_NAME);
define('PAGE_ELEMENTS_URL', PAGE_ELEMENTS_PATH.'assets/');
define('SYSTEM_PATH',       dirname(__DIR__).'/'); //???
define('SYSTEM_CACHE_PATH', PFY_CACHE_PATH);
define('PATH_TO_APP_ROOT',  '');


const PE_ASSET_GROUPS = [
    PAGE_ELEMENTS_PATH.'assets/css/' => [       // $dest
        PAGE_ELEMENTS_PATH.'scss/*',            // $sources
    ],
];


const PE_URL_DEFINITIONS = [
    'POPUPS' => [
        PAGE_ELEMENTS_URL.'css/-popup.css',
        PAGE_ELEMENTS_URL.'js/-popup.js',
    ],
    'MESSAGES' => [
        PAGE_ELEMENTS_URL.'css/-message.css',
        PAGE_ELEMENTS_URL.'js/message.js',
    ],
    'TABLES' => [
        PAGE_ELEMENTS_URL.'css/-table.css',
        PAGE_ELEMENTS_URL.'js/-table.js',
    ],
    'FORMS' => [
        PAGE_ELEMENTS_URL.'css/-forms.css',
        PAGE_ELEMENTS_URL.'js/-forms.js',
    ],
    'ENLIST' => [
        PAGE_ELEMENTS_URL.'css/-enlist.css',
        PAGE_ELEMENTS_URL.'js/-enlist.js',
    ],
    'EVENTS' => [
        //PAGE_ELEMENTS_URL.'css/-events.css',
        PAGE_ELEMENTS_URL.'js/-events.js',
    ],
    'DATATABLES' => [
        PAGE_ELEMENTS_URL.'css/datatables.min.css',
        PAGE_ELEMENTS_URL.'js/datatables.min.js',
    ],
    'REVEAL' => [
        PAGE_ELEMENTS_URL.'js/reveal.js',
        PAGE_ELEMENTS_URL.'css/-reveal.css',
    ],
    'LOGIN' => [
        PAGE_ELEMENTS_URL.'js/login.js',
        PAGE_ELEMENTS_URL.'css/-login.css',
    ],
];


require_once __DIR__.'/pe_helper.php';

 // activate site-manager if requested:
$optionsFromConfigFile = kirby()->option('pgfactory.pagefactory-pageelements.options');
if ($optionsFromConfigFile['activateSitemapManager']??false) {
    require_once 'site/plugins/pagefactory-pageelements/src/SitemapManager.php';
    SitemapManager::updateSitemap();
}
handleUrlToken();
PageFactory::$pg->addAssets(PAGE_ELEMENTS_URL.'js/pe-helper.js');

return 'PageElements';



