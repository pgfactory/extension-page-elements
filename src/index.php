<?php
namespace Usility\PageFactory\PageElements;


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
    'TOOLTIPSTER' => [
        PAGE_ELEMENTS_URL.'css/tooltipster.bundle.min.css',
        PAGE_ELEMENTS_URL.'js/tooltipster.bundle.min.js',
    ],
    'DATATABLES' => [
        PAGE_ELEMENTS_URL.'css/datatables.min.css',
        PAGE_ELEMENTS_URL.'js/datatables.min.js',
    ],
];

$extensionClassName = 'PageElements';
require_once PAGE_ELEMENTS_PATH."src/$extensionClassName.php";

// load this composer repo's autoloader:
require_once PAGE_ELEMENTS_PATH . 'third_party/vendor/autoload.php';

// load extension's variables:
\Usility\PageFactory\TransVars::loadTransVarsFromFiles(PAGE_ELEMENTS_PATH.'variables/');

//ToDo: replace with autoloader:
// load all further class files within 'src/':
$dir = \Usility\PageFactory\getDir(PAGE_ELEMENTS_PATH . 'src/*.php');
foreach ($dir as $file) {
    $filename = basename($file);
    if ($filename === 'index.php' || $filename[0] === '_') {
        continue;
    }
    require_once $file;
}

SitemapManager::updateSitemap();

return $extensionClassName;

