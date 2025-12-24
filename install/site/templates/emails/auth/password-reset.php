<?php
/*
 * Template for Password Reset E-Mail
 * See https://getkirby.com/docs/guide/authentication/login-methods
 */

use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\TransVars;

$text = TransVars::getVariable('pfy-login-pw-reset-mail-body');
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
  Page::setPopup($popup, 'Password Reset E-Mail');
}

echo $text;
