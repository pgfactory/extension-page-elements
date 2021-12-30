<?php

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory;
use function Usility\PageFactory\preparePath;

define('PAGE_ELEMENTS_PATH', dirname(__DIR__).'/');
define('SYSTEM_PATH',       PAGE_ELEMENTS_PATH);
define('SYSTEM_CACHE_PATH', PFY_CACHE_PATH);
define('PATH_TO_APP_ROOT',  '');
define('REC_KEY_ID', 	        '_key');
define('TIMESTAMP_KEY_ID', 	    '_timestamp');

require_once 'site/plugins/pagefactory/src/helper.php';

class PageElements
{
    private $assetDefs;
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pg = $pfy->pg;
        $this->trans = PageFactory::$trans;
        $this->extensionPath = dirname(dirname(__FILE__)).'/';
        $this->assetDefs = [
            'POPUPS' =>
                PAGE_ELEMENTS_PATH.'scss/popup.scss,'.
                PAGE_ELEMENTS_PATH.'third-party/tooltipster/css/tooltipster.bundle.min.css,'.
                PAGE_ELEMENTS_PATH.'third-party/tooltipster/js/tooltipster.bundle.min.js,'.
                PAGE_ELEMENTS_PATH.'third-party/jquery.event.ue/jquery.event.ue.min.js,'.
                PAGE_ELEMENTS_PATH.'third-party/javascript-md5/md5.min.js,'.
                PAGE_ELEMENTS_PATH.'js/popup.js',
            'MESSAGES' =>
                PAGE_ELEMENTS_PATH.'scss/message.scss,'.
                PAGE_ELEMENTS_PATH.'js/message.js',
            'OVERLAYS' =>
                PAGE_ELEMENTS_PATH.'scss/overlay.scss,'.
                PAGE_ELEMENTS_PATH.'js/overlay.js',
            'TABLES' =>
                PAGE_ELEMENTS_PATH.'scss/tables.scss,'.
                PAGE_ELEMENTS_PATH.'js/tables.js',
            'TOOLTIPSTER' =>
                PAGE_ELEMENTS_PATH.'third-party/tooltipster/css/tooltipster.bundle.min.css,'.
                PAGE_ELEMENTS_PATH.'third-party/tooltipster/js/tooltipster.bundle.min.js',
            ];
    } // __construct
//        $this->loadModules['HTMLTABLE']             = array('module' => 'js/htmltable.js,css/_htmltables.css', 'weight' => 123);

    public function getAssetDefs()
    {
        return $this->assetDefs;
    } // getAssetDefs


    protected function addAssets($assets) {
        if (!is_array($assets)) {
            $assets = \Usility\PageFactory\explodeTrim(',', $assets, true);
        }
        $assetDefKeys = array_keys($this->assetDefs);
        foreach ($assets as $key => $asset) {
            if (in_array($asset, $assetDefKeys)) {
                $assetsToAdd = \Usility\PageFactory\explodeTrim(',', $this->assetDefs[$asset], true);
                array_splice($assets, $key, 1, $assetsToAdd);
            }
        }

        $this->pg->addAssets($assets);
    } // addAssets
} // PageElements