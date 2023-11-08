<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\Scss as Scss;
use function PgFactory\PageFactory\createHash;
use function \PgFactory\PageFactory\getDir;
use function \PgFactory\PageFactory\getStaticUrlArg;
use function \PgFactory\PageFactory\rrmdir;



class PageElements
{
    public $pfy;
    public $pg;
    public $assets;
    public $extensionPath;
    /**
     * @param $pfy
     */
    public function __construct($pfy = null)
    {
        $this->pfy = $pfy;
        $this->pg = PageFactory::$pg;
        $this->assets = PageFactory::$assets;

        $this->handleCreateHashRequest();

        $this->extensionPath = dirname(dirname(__FILE__)).'/';
        $this->initMacros();
        $this->updateScss();
        $this->initPresentationSupport();
        $this->handleCssRefactor();
        $this->cleanDownloadFolder();
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



    private function initPresentationSupport()
    {
        $activatePresentationSupport = kirby()->option('pgfactory.pagefactory-elements.options.activatePresentationSupport');
        if ($activatePresentationSupport && getStaticUrlArg('present')) {
            new Presentation();
        }
    } // initPresentationSupport


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


    private function handleCssRefactor()
    {
        $file = $_GET['cssrefactor']??false;
        if ($file === false) {
            return;
        }
        if ($file === '') {
            exit("CSS-Refactoring:<br>Please supply path to CSS file(s)<br>You can use wildcards, ".
                "e.g. '?cssrefactor=site/plugins/pagefactory/assets/css/*.css'");
        }

        if (file_exists($file)) {
            $files = [$file];
        } else {
            $files = getDir($file);
        }
        if ($files) {
            echo "Compiling files to SCSS:<br>\n";
            foreach ($files as $file) {
                $res = CssRefactor::exec($file);
                if (is_array($res)) {
                    $scssFile = $res[1];
                    exit("ERROR occured while compiling file '$file'<br>\n");
                }
                echo("- $file -> $res<br>\n");
            }
               }
        exit("Done <br>\n");
    } // handleCssRefactor


    private function cleanDownloadFolder()
    {
        $dir = glob(TEMP_DOWNLOAD_PATH.'*');
        if ($dir) {
            foreach ($dir as $folder) {
                if (@filemtime($folder) < (time() - 600)) { // max file age: 10 min
                    rrmdir($folder);
                }
            }
        }
    } // cleanDownloadFolder


    /**
     * @param $assets
     * @return void
     */
    protected function addAssets($assets): void
    {
        $this->assets->addAssets($assets);
    } // addAssets


    private function handleCreateHashRequest(): void
    {
        if (isset($_GET['hash'])) {
            $hash = createHash();
            exit($hash);
        }
    } // handleCreateHashRequest


    public function handleUrlRequests(): void
    {
        // handle ?login:
        if (isset($_GET['login'])) {
            Login::init(['as-popup' => true]);
            $html = Login::render();
            if ($html) {
                PageFactory::$pg->overrideContent($html);
            }
        }
    } // handleUrlRequests

} // PageElements