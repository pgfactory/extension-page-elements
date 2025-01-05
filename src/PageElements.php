<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\Scss as Scss;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\createHash;
use function \PgFactory\PageFactory\getDir;
use function \PgFactory\PageFactory\rrmdir;

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


class PageElements
{
    public $pfy;
    public $extensionPath;
    /**
     * @param $pfy
     */
    public function __construct()
    {
        $this->loadVariables();
        $this->init();

        $this->handleCreateHashRequest();

        $this->extensionPath = dirname(dirname(__FILE__)).'/';
        $this->initMacros();
        $this->handleCssRefactor();
        $this->initTooltips();
        $this->cleanDownloadFolder();
        self::initOnboardingAid();

        $this->handleUrlRequests();
    } // __construct


    /**
     * @return void
     * @throws \Kirby\Exception\Exception
     */
    private function init()
    {
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
    } // init


    /**
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function loadVariables()
    {
        $extPath = dirname(__DIR__).'/';
        // load extension's variables:
        $files = getDir($extPath.'variables/');
        if (is_array($files)) {
            foreach ($files as $file) {
                TransVars::loadVariablesFromFile($file, doTranslate: true);
            }
        }
    } // loadVariables


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
    private function handleCssRefactor()
    {
        $file = $_GET['cssrefactor']??false;
        if ($file === false) {
            return;
        }
        if ($file === '') {
            exit("CSS-Refactoring:<br>Please supply path to CSS file(s)<br>You can use wildcards, ".
                "e.g. '?cssrefactor=site/plugins/pagefactory/assets/css/*.css'"); //???
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


    /**
     * @return void
     */
    private function cleanDownloadFolder()
    {
        $dir = glob(PFY_TEMP_DOWNLOAD_PATH.'*');
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
        Assets::addAssets($assets);
    } // addAssets


    /**
     * @return void
     * @throws \Exception
     */
    protected function initTooltips(): void
    {
        Assets::addAssets('TOOLTIPS');
        $js = <<<EOT

if (document.querySelector('.pfy-tippy')) {
    tippy('.pfy-tippy', {
      content: (reference) => reference.getAttribute('title'),
      allowHTML: true,
      delay: 200,
      theme: 'light',
    });
}

EOT;
        Page::addJsReady($js);
    } // initTooltips


    /**
     * @return void
     * @throws \Exception
     */
    private function handleCreateHashRequest(): void
    {
        if (isset($_GET['hash'])) {
            $hash = createHash();
            exit($hash);
        }
    } // handleCreateHashRequest


    /**
     * Called from Extensions::loadExtensions()
     * @return void
     * @throws \Exception
     */
    public function handleUrlRequests(): void
    {
        // handle ?login:
        //   => request later handled by Login::loginCallback()
        if (isset($_GET['login'])) {
            Login::init(['as-popup' => true]);
            $html = Login::render();
            if ($html) {
                Page::overrideContent($html);
            }
        }

        // handle ?onboardingaid:
        //   => request later handled by Login::loginCallback()
        if (isset($_GET['onboardingaid'])) {
            self::renderOnboardingAid();
        }
    } // handleUrlRequests


    /**
     * @return void
     */
    static function initOnboardingAid(): void
    {
        $str = '';
        $url = PFY_PAGE_URL;
        if (Permission::isLoggedIn()) {
            $str = <<<EOT
<a href="$url?onboardingaid" class="pfy-onboardingaid-button pfy-onboardingaid" title="{{ pfy-onboardingaid-title }}">
{{ pfy-onboardingaid-icon }}
</a>

EOT;
        }
        TransVars::setVariable('pfy-onboardingaid', $str);
    } // onboardingaid


    /**
     * @return void
     * @throws \Exception
     */
    static function renderOnboardingAid(): void
    {
        if ($user = Permission::getLoggedInUser()) {
            if (self::getAccessLink($user)) {
                $str = <<<EOT

<section class="pfy-section-wrapper">
<div class="pfy-onboardingaid">
{{ pfy-onboardingaid-text }}
</div>
</section>

EOT;
            } else {
                $str = <<<EOT

<section class="pfy-section-wrapper">
<div class="pfy-onboardingaid">
{{ pfy-onboardingaid-accesscode-missing }}
</div>
</section>

EOT;

            }
            $html = TransVars::compile($str);
            Page::overrideContent($html);
        }
    } // renderOnboardingAid


    /**
     * @param $user
     * @return bool
     */
    static function getAccessLink($user): bool
    {
        $link = '';
        if ($content = $user->content()) {
            if ($data = $content->data()) {
                $accessCode = $data['accesscode'];
                if (!$accessCode) {
                    return false;
                }
                $link = PFY_PAGE_URL."?a=$accessCode";
            }
        }
        TransVars::setVariable('pfy-user-accesslink', $link);
        return true;
    } // getAccessLink


    /**
     * @return void
     * @throws \Exception
     */
    public static function reset(): void
    {
        // delete all compiled js files:
        $files = getDir(PAGE_ELEMENTS_ASSETS_PATH.'js/*.js');
        foreach ($files as $file) {
            if (basename($file)[0] === '-') {
                unlink($file);
            }
        }

        // re-compile js files:
        compileJs(PAGE_ELEMENTS_PATH.'js/', PAGE_ELEMENTS_ASSETS_PATH.'js/');
    } // reset

} // PageElements