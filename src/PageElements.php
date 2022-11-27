<?php

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory;


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