<?php

namespace Usility\PageFactoryElements;
use Usility\PageFactory\PageFactory as PageFactory;
use Usility\PageFactory\Scss as Scss;
use function \Usility\PageFactory\getDir;



class PageElements
{
    /**
     * @param $pfy
     */
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pg = PageFactory::$pg;
        $this->assets = PageFactory::$assets;

        $this->extensionPath = dirname(dirname(__FILE__)).'/';
        $this->initMacros();
        $this->updateScss();
    } // __construct


    /**
     * @return void
     */
    private function initMacros(): void
    {
        $dir = getDir($this->extensionPath.'macros/*.php');
        foreach ($dir as $file) {
            if (basename($file[0] !== '#')) {
                require_once $file;
            }
        }
    } // initMacros


    /**
     * @return void
     */
    private function updateScss(): void
    {
        $dir = getDir($this->extensionPath.'scss/*.scss');
        $targetPath = $this->extensionPath.'assets/css/';
        foreach ($dir as $file) {
            if (basename($file[0] !== '#')) {
                Scss::updateFile($file, $targetPath);
            }
        }
    } // updateScss


    /**
     * @return array
     */
    public function getAssetDefs(): array
    {
        return PE_URL_DEFINITIONS;
    } // getAssetDefs


    /**
     * @return array
     */
    public function getAssetGroups(): array
    {
        return PE_ASSET_GROUPS;
    } // getAssetGroups


    /**
     * @param $assets
     * @return void
     */
    protected function addAssets($assets): void
    {
        $this->assets->addAssets($assets);
    } // addAssets
} // PageElements