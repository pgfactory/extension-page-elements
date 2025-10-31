<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\CompileJs;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\Scss as Scss;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\createHash;
use function \PgFactory\PageFactory\getDir;
use function PgFactory\PageFactory\getDirDeep;
use function PgFactory\PageFactory\isAdmin;
use function PgFactory\PageFactory\isLocalhost;
use function \PgFactory\PageFactory\rrmdir;

define('PE_FOLDER_NAME',  basename(dirname(__DIR__)).'/');
define('PE_PATH', 'site/plugins/'.PE_FOLDER_NAME);
define('PE_ASSETS_PATH', PE_PATH . 'assets/');
define('PE_ASSETS_ROOT', PFY_KIRBY_BASE_PATH.PE_ASSETS_PATH);
define('SYSTEM_PATH',       dirname(__DIR__).'/'); //???
define('SYSTEM_CACHE_PATH', PFY_CACHE_PATH);
define('PATH_TO_APP_ROOT',  '');


define('PE_ASSET_LOCATIONS', [
    PE_PATH.'assets/css/' => PE_PATH.'scss/*',
]);

const PE_PATH_DEFINITIONS = [
    'PE' => [
        PE_ASSETS_PATH.'css/-pe.css',
        // PE_ASSETS_PATH.'js/-pe.js',
    ],
    'DIR' => [
        PE_ASSETS_PATH.'css/-dir.css',
        PE_ASSETS_PATH.'js/-dir.js',
    ],
    'POPUPS' => [
        PE_ASSETS_PATH.'css/-popup.css',
        PE_ASSETS_PATH.'js/-popup.js',
    ],
    'MESSAGES' => [
        PE_ASSETS_PATH.'css/-message.css',
        PE_ASSETS_PATH.'js/message.js',
    ],
    'TABLES' => [
        PE_ASSETS_PATH.'css/-table.css',
        PE_ASSETS_PATH.'js/-table.js',
    ],
    'FORMS' => [
        PE_ASSETS_PATH.'css/-forms.css',
        PE_ASSETS_PATH.'js/-forms.js',
    ],
    'ENLIST' => [
        PE_ASSETS_PATH.'css/-enlist.css',
        PE_ASSETS_PATH.'js/-enlist.js',
    ],
    'EVENTS' => [
        //PE_ASSETS_PATH.'css/-events.css',
        PE_ASSETS_PATH.'js/-events.js',
    ],
    'DATATABLES' => [
        PE_ASSETS_PATH.'css/datatables.min.css',
        PE_ASSETS_PATH.'js/datatables.min.js',
    ],
    'REVEAL' => [
        PE_ASSETS_PATH.'js/reveal.js',
        PE_ASSETS_PATH.'css/-reveal.css',
    ],
    'LOGIN' => [
        PE_ASSETS_PATH.'js/login.js',
        PE_ASSETS_PATH.'css/-login.css',
    ],
    'TOOLTIPS' => [
        PE_ASSETS_PATH.'css/tippy.min.css',
        PE_ASSETS_PATH.'js/popper.min.js',
        PE_ASSETS_PATH.'js/tippy-bundle.umd.min.js',
    ],
    'CALENDAR' => [
        PE_ASSETS_PATH.'js/swipe.js',
        PE_ASSETS_PATH.'js/popper.min.js',
        PE_ASSETS_PATH.'js/tippy-bundle.umd.min.js',
        PE_ASSETS_PATH.'js/fullcalendar.min.js',
        PE_ASSETS_PATH.'js/-calendar.js',
        PE_ASSETS_PATH.'css/-calendar.css',
    ],
    'WRITABLE' => [
        PE_ASSETS_PATH.'js/writable.js',
        PE_ASSETS_PATH.'css/-writable.css',
    ],
    'SAYTTS' => [
        PE_ASSETS_PATH.'js/sayTTS.js',
        PE_ASSETS_PATH.'css/-sayTTS.css',
    ],
    'HTML_MAIL' => [
        PE_ASSETS_PATH.'css/-htmlmail.css',
    ],
];


require_once __DIR__.'/pe_helper.php';


class PageElements
{
    public $pfy;
    public $extensionPath;
    private static $iconsLoaded = false;
    /**
     * @param $pfy
     */
    public function __construct()
    {
        $this->loadVariables();
        $this->init();

        $this->handleAdminRequests();

        $this->extensionPath = dirname(dirname(__FILE__)).'/';
        $this->initMacros();
        $this->handleCssRefactor();
        $this->initTooltips();
        $this->cleanDownloadFolder();
        $this->initOnboardingAid();

        $this->handleUrlRequests();
        Assets::addAssets('PE');
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
        if ($code = kirby()->option('pgfactory.pagefactory-elements.initCode')) {
            $code = PFY_KIRBY_BASE_PATH . 'site/custom/code/'.$code;
            if (file_exists($code)) {
                require_once $code;
            }
        }

        // activate site-manager if requested:
        if (kirby()->option('pgfactory.pagefactory-elements.activateSitemapManager')) {
            require_once __DIR__ . '/SitemapManager.php';
            SitemapManager::updateSitemap();
        }

        Assets::addAssets(PE_ASSETS_PATH.'js/pe-helper.js');

        Assets::addAssetGroups(PE_PATH_DEFINITIONS);

        Assets::addAssetLocation(PE_ASSET_LOCATIONS);

        if (PageFactory::$dev || PageFactory::$forceAssetsUpdate) {
            CompileJs::compileAll(PE_PATH.'js/', PE_ASSETS_PATH.'js/');
        }

        self::handleCoop(); // Cross-Origin-Opener-Policy: same-origin
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
     * @return string
     */
    public function showHelp(): string
    {
        $str = <<<EOT
@@@ .pfy-general-help
### PageElements

[?hash](./?hash)       12em>> creates new hash code 
[?reset&data](./?reset&data)       12em>> resets app and copies data from production DB to site/custom/data/ 
[?purge](./?purge)      >> purges ".history/" folders used by data storage
[?purge-old](./?purge-old)      >> purges old apps in root directory (e.g. '/＃dev')

@@@
EOT;
        return $str;
    } // showHelp


    /**
     * @return void
     * @throws \Exception
     */
    private function handleAdminRequests(): void
    {
        $this->handleCreateHashRequest();
        $this->handleCleanupRequest();

    } // handleAdminRequests


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
     * @return void
     */
    private function handleCleanupRequest(): void
    {
        if (isset($_GET['purge'])) {
            $this->purgeHistoryFolders();
        }

        if (isset($_GET['purge-old'])) {
            $this->purgeOldVersionFolders();
        }
    } // handleCleanupRequest


    /**
     * @return void
     * @throws \Exception
     */
    private function purgeHistoryFolders()
    {
        if (!isAdmin() && !isLocalhost()) {
            exit('You need admin privileges to perform "?purge".');
        }
        echo 'Deleting ".history/" folders:<br>';
        $historyDirs = getDirDeep(PFY_KIRBY_BASE_PATH.'*.history', onlyDir:true);
        foreach ($historyDirs as $dir) {
            if (is_dir($dir)) {
                echo($dir.'<br>');
                rrmdir($dir);
            }
        }

        if ($dataPath = kirby()->option('pgfactory.pagefactory.productionModeDataPath')) {
            echo 'Deleting ".history/" folders in "$dataPath":<br>';
            $path = Utils::normalizePath(PFY_KIRBY_BASE_PATH . $dataPath);
            $historyDirs = getDirDeep($path.'*.history', onlyDir:true);
            foreach ($historyDirs as $dir) {
                if (is_dir($dir)) {
                    echo($dir.'<br>');
                    rrmdir($dir);
                }
            }

        }
        Utils::resetAll();
        Utils::handleDevDataUpdate();
        Utils::setInstallationCheckFile();
        exit('done');
    } // purgeHistoryFolders


    /**
     * @return void
     */
    private function purgeOldVersionFolders()
    {
        if (!isAdmin()) {
            exit('You need admin privileges to perform "?purge-old".');
        }
        echo 'Deleting old versions:<br>';
        $oldAppDirs = glob(dirname(PFY_KIRBY_BASE_PATH).'/#*');
        foreach ($oldAppDirs as $dir) {
            if (is_dir($dir)) {
                echo($dir.'<br>');
                rrmdir($dir);
            }
        }
        exit('done');
    } // purgeOldVersionFolders



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
            $this->renderOnboardingAid();
        }
    } // handleUrlRequests


    /**
     * @return void
     */
    private function initOnboardingAid(): void
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
    private function renderOnboardingAid(): void
    {
        if ($user = Permission::getLoggedInUser()) {
            if ($this->getAccessLink($user)) {
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
    private function getAccessLink($user): bool
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
     */
    private static function handleCoop(): void
    {
        if (option('pgfactory.pagefactory-elements.enableCoop')) {
            header('Cross-Origin-Opener-Policy: same-origin');
        }
    } // handleCoop


    /**
     * @return void
     * @throws \Exception
     */
    public static function reset(): void
    {
        // delete all compiled js files:
        $files = getDir(PE_ASSETS_PATH.'js/*.js');
        foreach ($files as $file) {
            if (basename($file)[0] === '-') {
                unlink($file);
            }
        }

        // re-compile js files:
        CompileJs::compileAll(PE_PATH.'js/', PE_ASSETS_PATH.'js/');
    } // reset


    /**
     * @return void
     */
    public static function loadIcons(): void
    {
        if (!self::$iconsLoaded) {
            self::$iconsLoaded = true;
            $pfyIcons = svg(PFY_KIRBY_BASE_PATH.'site/plugins/pagefactory-pageelements/assets/icons/_pfy-icons.svg');
            Page::addBodyEndInjections($pfyIcons);
        }
    } // loadIcons()

} // PageElements