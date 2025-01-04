<?php

use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;

$text = TransVars::getVariable('pfy-login-pw-reset-mail-body');
$webmasterEmail = file_get_contents(PFY_WEBMASTER_EMAIL_CACHE);

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
  $code2 = str_replace(' ', '', $code);
  $popup = "<pre>$text</pre>";
  Page::setPopup($popup, 'Login Code E-Mail');
}

echo $text;
