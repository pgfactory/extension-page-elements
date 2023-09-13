<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;

// ToDo: option to import translations table from a file
const TRANSLATIONS = [
    'Monday' => ['en' => 'Monday', 'de' => 'Montag', 'fr'=> 'Lundi', 'it'=> 'Lunedì',],
    'Tuesday' => ['en' => 'Tuesday', 'de' => 'Dienstag', 'fr'=> 'Mardi' ,'it'=> 'Martedì',],
    'Wednesday' => ['en' => 'Wednesday', 'de' => 'Mittwoch', 'fr'=> 'Mercredi' ,'it'=> 'Mercoledì',],
    'Thursday' => ['en' => 'Thursday', 'de' => 'Donnerstag', 'fr'=> 'Jeudi' ,'it'=> 'Giovedì',],
    'Friday' => ['en' => 'Friday', 'de' => 'Freitag', 'fr'=> 'Vendredi' ,'it'=> 'Venerdì',],
    'Saturday' => ['en' => 'Saturday', 'de' => 'Samstag', 'fr'=> 'Samedi' ,'it'=> 'Sabato',],
    'Sunday' => ['en' => 'Sunday', 'de' => 'Sonntag', 'fr'=> 'Dimanche' ,'it'=> 'Domenica',],

    'January' => ['en' => 'January', 'de' => 'Januar', 'fr'=> 'janvier ' ,'it'=> 'gennaio',],
    'February' => ['en' => 'February', 'de' => 'Februar', 'fr'=> 'février' ,'it'=> 'febbraio',],
    'March' => ['en' => 'March', 'de' => 'März', 'fr'=> 'mars' ,'it'=> 'marzo',],
    'April' => ['en' => 'April', 'de' => 'April', 'fr'=> 'avril' ,'it'=> 'aprile',],
    'May' => ['en' => 'May', 'de' => 'Mai', 'fr'=> 'mai' ,'it'=> 'maggio',],
    'June' => ['en' => 'June', 'de' => 'Juni', 'fr'=> 'juin' ,'it'=> 'giugno',],
    'July' => ['en' => 'July', 'de' => 'Juli', 'fr'=> 'juillet' ,'it'=> 'luglio',],
    'August' => ['en' => 'August', 'de' => 'August', 'fr'=> 'août' ,'it'=> 'agosto',],
    'September' => ['en' => 'September', 'de' => 'September', 'fr'=> 'septembre' ,'it'=> 'settembre',],
    'October' => ['en' => 'October', 'de' => 'Oktober', 'fr'=> 'octobre' ,'it'=> 'ottobre',],
    'November' => ['en' => 'November', 'de' => 'November', 'fr'=> 'novembre' ,'it'=> 'novembre',],
    'December' => ['en' => 'December', 'de' => 'Dezember', 'fr'=> 'décembre' ,'it'=> 'dicembre',],

    'OCLOCK' => ['en' => 'h', 'de' => 'Uhr', 'fr'=> 'heures' ,'it'=> 'ore',],
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
    $str = preg_replace('/([–-]) 00([.:])00/', "$1 24$2&#48;&#48;", $str);
    return $str;
} // translateDateTimes



/**
 * Entry point for handling UrlTokens, in particular for access-code-login:
 * @return bool
 */
function handleUrlToken(): bool
{
    $urlToken = PageFactory::$urlToken;
    if (!$urlToken) {
        return false;
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
    return false;
} // handleUrlToken


/**
 * @param string $url
 * @param string $arg
 * @return string
 */
function urlAppendArg(string $url, string $arg): string
{
    if (str_contains($url, '?')) {
        $url .= '&'.$arg;
    } else {
        $url .= '?'.$arg;
    }
    return $url;
} // urlAppendArg


/**
 * array_splice_assoc
 * Splice an associative array
 * Removes the elements designated by offset & length and replaces them
 * with the elements of replacement array
 * https://nimblewebdeveloper.com/blog/php-splice-associative-keyed-array
 * @param $input array
 * @param $key string
 * @param $length int
 * @param $replacement array
 */
function array_splice_associative($input, $key, $length, $replacement=array()) {
    $index = array_search($key, array_keys($input));

    if($index === false) {
        return $input;
    }

    $before_slice = array_slice($input, 0, $index);
    $after_slice = array_slice($input, $index+$length);

    return array_merge($before_slice, $replacement, $after_slice);
} // array_splice_associative