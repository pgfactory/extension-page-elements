<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;

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
    'FORMS' => [
        PAGE_ELEMENTS_URL.'css/-forms.css',
        PAGE_ELEMENTS_URL.'js/-forms.js',
    ],
    'TOOLTIPSTER' => [
        PAGE_ELEMENTS_URL.'css/tooltipster.bundle.min.css',
        PAGE_ELEMENTS_URL.'js/tooltipster.bundle.min.js',
    ],
    'DATATABLES' => [
        PAGE_ELEMENTS_URL.'css/datatables.min.css',
        PAGE_ELEMENTS_URL.'js/datatables.min.js',
    ],
    'REVEAL' => [
        PAGE_ELEMENTS_URL.'js/jq-focusable.js',
        PAGE_ELEMENTS_URL.'js/reveal.js',
        PAGE_ELEMENTS_URL.'css/-reveal.css',
    ],
];

 // activate site-manager if requested:
$optionsFromConfigFile = kirby()->option('pgfactory.pagefactory-pageelements.options');
if ($optionsFromConfigFile['activateSitemapManager']??false) {
    require_once 'site/plugins/pagefactory-pageelements/src/SitemapManager.php';
    SitemapManager::updateSitemap();
}
handleUrlToken();
PageFactory::$pg->addAssets(PAGE_ELEMENTS_URL.'js/pe-helper.js');

return 'PageElements';



/**
 * Entry point for handling UrlTokens, in particular for access-code-login:
 * @return void
 */
function handleUrlToken()
{
    $urlToken = PageFactory::$urlToken;
    if (!$urlToken) {
        return;
    }

    // do something with $urlToken...
    $found = kirby()->users()->filter(function($p) {
        $data = $p->content()->data();
        $ac = $data['accesscode']??'';
        $urlToken = PageFactory::$urlToken;
        if ($ac === $urlToken) {
            return true;
        }
        return false;
    });
    $user = $found->first();
    if ($user) {
        $user->loginPasswordless();
    }

    // remove the urlToken:
//        $target = page()->url();
    $target = PageFactory::$appUrl . PageFactory::$slug;
    \Usility\PageFactory\reloadAgent($target);
} // handleUrlToken
