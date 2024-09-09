<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\Scss as Scss;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\createHash;
use function \PgFactory\PageFactory\getDir;
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

        // register PE asset definitions:
        Page::$definitions = array_merge_recursive(Page::$definitions, ['assets' => PE_URL_DEFINITIONS]);

        $this->handleCreateHashRequest();

        $this->extensionPath = dirname(dirname(__FILE__)).'/';
        $this->initMacros();
        $this->updateScss();
        $this->handleCssRefactor();
        $this->initTooltips();
        $this->cleanDownloadFolder();
        self::initOnboardingAid();
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
    public function getAssetGroups(): array
    {
        return PE_ASSET_GROUPS;
    } // getAssetGroups


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


    /**
     * @return void
     */
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


    /**
     * @return void
     * @throws \Exception
     */
    protected function initTooltips(): void
    {
        PageFactory::$pg->addAssets('TOOLTIPS');
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
        PageFactory::$pg->addJsReady($js);
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
                PageFactory::$pg->overrideContent($html);
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
        $url = PageFactory::$absPageUrl;
        if (Permission::isLoggedIn()) {
            $str = <<<EOT
<a href="$url?onboardingaid" class="pfy-login-button pfy-onboardingaid" title="{{ pfy-onboardingaid-title }}">
<svg height="21" viewBox="0 0 21 21" width="21" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" transform="translate(2 1)">
<path d="m6.5 17.5h4"></path><path d="m8.5 4c2.4852814 0 4.5 2.01471863 4.5 4.5 0 1.7663751-1.017722 3.2950485-2.4987786 4.031633l-.0012214.968367c0 1.1045695-.8954305 2-2 2s-2-.8954305-2-2l-.00021218-.9678653c-1.48160351-.7363918-2.49978782-2.2653584-2.49978782-4.0321347 0-2.48528137 2.01471863-4.5 4.5-4.5z">
</path><path d="m8.5 1.5v-1"></path><path d="m13.5 3.5 1-1"></path><path d="m2.5 3.5 1-1" transform="matrix(-1 0 0 1 6 0)"></path><path d="m13.5 13.5 1-1" transform="matrix(1 0 0 -1 0 26)"></path><path d="m2.5 13.5 1-1" transform="matrix(-1 0 0 -1 6 26)">
</path><path d="m1.5 7.5h-1"></path><path d="m16.5 7.5h-1"></path></g></svg>
</a>

EOT;
        }
        TransVars::setVariable('pfy-onboardingaid-icon', $str);
    } // onboardingaid


    /**
     * @return void
     * @throws \Exception
     */
    static function renderOnboardingAid(): void
    {
        if ($user = kirby()->user()) {
            self::getAccessLink($user);
            $str = <<<EOT

<section class="pfy-section-wrapper">
<div class="pfy-onboardingaid">
{{ pfy-onboardingaid-text }}
</div>
</section>

EOT;
            $html = TransVars::compile($str);
            PageFactory::$pg->overrideContent($html);
        }
    } // renderOnboardingAid


    /**
     * @param $user
     * @return void
     */
    static function getAccessLink($user)
    {
        $link = '';
        if ($content = $user->content()) {
            if ($data = $content->data()) {
                $accessCode = $data['accesscode'];
                $link = PageFactory::$absPageUrl."?a=$accessCode";
            }
        }
        TransVars::setVariable('pfy-user-accesslink', $link);
    } // getAccessLink

} // PageElements