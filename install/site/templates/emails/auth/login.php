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

$text = TransVars::getVariable('pfy-login-code-mail-body');
$webmasterEmail = PageFactory::$webmasterEmail;
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

if (PageFactory::$dev) {
    mylog("Login email prepared with code '$code'.", 'login-log.txt');
}
echo $text;
