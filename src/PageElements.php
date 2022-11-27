<?php

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory;
// use function Usility\PageFactory\preparePath;

//define('PE_FOLDER_NAME',  basename(dirname(__DIR__)).'/');
//define('PAGE_ELEMENTS_PATH', 'site/plugins/'.PE_FOLDER_NAME);
//define('PAGE_ELEMENTS_URL', 'media/plugins/usility/'.PE_FOLDER_NAME);
//define('SYSTEM_PATH',       dirname(__DIR__).'/'); //???
//define('SYSTEM_CACHE_PATH', PFY_CACHE_PATH);
//define('PATH_TO_APP_ROOT',  '');
//
//const PE_ASSET_GROUPS = [
//    PAGE_ELEMENTS_PATH.'assets/css/' => [       // $dest
//        PAGE_ELEMENTS_PATH.'scss/*',            // $sources
//    ],
//];
//
//
//const PE_URL_DEFINITIONS = [
//    'POPUPS' => [
//        PAGE_ELEMENTS_URL.'css/-popup.css',
//        PAGE_ELEMENTS_URL.'third-party/tooltipster/css/tooltipster.bundle.min.css',
//        PAGE_ELEMENTS_URL.'third-party/tooltipster/js/tooltipster.bundle.min.js',
//        PAGE_ELEMENTS_URL.'third-party/jquery.event.ue/jquery.event.ue.min.js',
//        PAGE_ELEMENTS_URL.'third-party/javascript-md5/md5.min.js',
//        PAGE_ELEMENTS_URL.'js/popup.js',
//    ],
//    'MESSAGES' => [
//        PAGE_ELEMENTS_URL.'css/-message.css',
//        PAGE_ELEMENTS_URL.'js/message.js',
//    ],
//    'OVERLAYS' => [
//        PAGE_ELEMENTS_URL.'css/-overlay.css',
//        PAGE_ELEMENTS_URL.'js/overlay.js',
//    ],
//    'TOOLTIPSTER' => [
//        PAGE_ELEMENTS_URL.'third-party/tooltipster/css/tooltipster.bundle.min.css',
//        PAGE_ELEMENTS_URL.'third-party/tooltipster/js/tooltipster.bundle.min.js',
//    ],
//];
//
//
//require_once 'site/plugins/pagefactory/src/helper.php';

class PageElements
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pg = PageFactory::$pg;
        $this->assets = PageFactory::$assets;
        $this->trans = PageFactory::$trans;
        $this->extensionPath = dirname(dirname(__FILE__)).'/';
    } // __construct

    public function getAssetDefs()
    {
        return PE_URL_DEFINITIONS;
    } // getAssetDefs


    public function getAssetGroups()
    {
        return PE_ASSET_GROUPS;
    } // getAssetGroups


    protected function addAssets($assets) {
        $this->assets->addAssets($assets);
    } // addAssets
} // PageElements