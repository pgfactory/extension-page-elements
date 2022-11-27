<?php


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
        PAGE_ELEMENTS_URL.'js/jquery.event.ue.min.js',
        PAGE_ELEMENTS_URL.'js/md5.min.js',
        PAGE_ELEMENTS_URL.'css/tooltipster.bundle.min.css',
        PAGE_ELEMENTS_URL.'js/tooltipster.bundle.min.js',
        PAGE_ELEMENTS_URL.'css/-popup.css',
        PAGE_ELEMENTS_URL.'js/popup.js',
    ],
    'MESSAGES' => [
        PAGE_ELEMENTS_URL.'css/-message.css',
        PAGE_ELEMENTS_URL.'js/message.js',
    ],
    'OVERLAYS' => [
        PAGE_ELEMENTS_URL.'css/-overlay.css',
        PAGE_ELEMENTS_URL.'js/overlay.js',
    ],
    'TOOLTIPSTER' => [
        PAGE_ELEMENTS_URL.'css/tooltipster.bundle.min.css',
        PAGE_ELEMENTS_URL.'js/tooltipster.bundle.min.js',
    ],
];

$extensionClassName = 'PageElements';
require_once __DIR__."/$extensionClassName.php";

//require_once 'site/plugins/pagefactory/src/helper.php';

return $extensionClassName;
