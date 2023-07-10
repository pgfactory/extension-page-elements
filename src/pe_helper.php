<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;

const TRANSLATIONS = [
    'Monday' => ['en' => 'Monday', 'de' => 'Montag',],
    'Tuesday' => ['en' => 'Tuesday', 'de' => 'Dienstag',],
    'Wednesday' => ['en' => 'Wednesday', 'de' => 'Mittwoch',],
    'Thursday' => ['en' => 'Thursday', 'de' => 'Donnerstag',],
    'Friday' => ['en' => 'Friday', 'de' => 'Freitag',],
    'Saturday' => ['en' => 'Saturday', 'de' => 'Samstag',],
    'Sunday' => ['en' => 'Sunday', 'de' => 'Sonntag',],
    'January' => ['en' => 'January', 'de' => 'Januar',],
    'February' => ['en' => 'Febrary', 'de' => 'Februar',],
    'March' => ['en' => 'March', 'de' => 'März',],
    'April' => ['en' => 'April', 'de' => 'April',],
    'May' => ['en' => 'May', 'de' => 'Mai',],
    'June' => ['en' => 'June', 'de' => 'Juni',],
    'July' => ['en' => 'July', 'de' => 'Juli',],
    'September' => ['en' => 'September', 'de' => 'September',],
    'October' => ['en' => 'October', 'de' => 'Oktober',],
    'November' => ['en' => 'November', 'de' => 'November',],
    'December' => ['en' => 'December', 'de' => 'Dezember',],
    '%%' => ['en' => 'h', 'de' => 'Uhr',],
];
const YEAR_THRESHOLD = 10;


/**
 * @param string $str
 * @return string
 */
function translateDateTimes(string $str):string
{
    $lang = PageFactory::$langCode;
    $translations = TRANSLATIONS;
    $e0 = reset($translations);
    if (!isset($e0[$lang])) {
        return $str; // language not found, skip translation
    }
    $to = array_map(function ($e) use($lang) {
        return $e[$lang]??'???';
    }, array_values(TRANSLATIONS));
    $str = str_replace(array_keys($translations), $to, $str);
    return $str;
} // translateDateTimes



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
    $target = PageFactory::$appUrl . PageFactory::$pageId;
    \Usility\PageFactory\reloadAgent($target);
} // handleUrlToken
