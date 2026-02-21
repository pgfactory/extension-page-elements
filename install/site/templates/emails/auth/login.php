<?php
/*
 * Template for Passwordless Login Code E-Mail
 * See https://getkirby.com/docs/guide/authentication/login-methods
 */

use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\TransVars;
use function \PgFactory\PageFactory\mylog;

const MAIL_BODY = [
  'de' => <<<EOT
Hallo %user%,

Sie haben kürzlich einen Login Code angefordert für die Website %url%.
Der folgende Login Code wird für die nächsten %timeout% Minuten gültig sein:

%code%

Falls Sie keinen Login Code angefordert haben, können Sie diese E-Mail ignorieren.
Kontaktieren Sie den %webmaster%, wenn Sie Fragen haben.
Aus Sicherheitsgründen sollten Sie diese E-Mail bitte NICHT weiterleiten.
EOT,
  '_' => <<<EOT
Hi %user%,

You recently requested a login code for the website %url%.
The following login code will be valid for %timeout% minutes:

%code%

If you did not request a login code, please ignore this email or contact your administrator if you have questions.
For security, please DO NOT forward this email.

EOT,
];

$text = TransVars::getVariable('pfy-login-code-mail-body');
if (!$text) {
  // when loggin in to the panel, TransVar is not available at this point, so we short cut:
  $lang = kirby()->language() ?? kirby()->defaultLanguage();
  $text = (MAIL_BODY[$lang]??false) ?: MAIL_BODY['_'];
}
$webmasterEmail = PageFactory::$webmasterEmail;
if (!$webmasterEmail) {
  $webmasterEmail = kirby()->option('pgfactory.pagefactory.webmaster_email') ?: '';
}

$code = str_replace(' ', '', $code);

$text = str_replace(
    [
        '%user%',
        '%timeout%',
        '%code%',
        '%url%',
        '%webmaster%',
    ],
    [
        $user->nameOrEmail(),
        $timeout,
        $code,
        PFY_APP_BASE_URL,
        $webmasterEmail,
    ],
    $text);

if (Permission::isLocalhost()) {
    $popup = "<pre>$text</pre>";
    Page::setPopup($popup, 'Login Code E-Mail');
}

if (PageFactory::$dev??false) {
    mylog("Login email prepared with code '$code'.", 'login-log.txt');
}
echo $text;
