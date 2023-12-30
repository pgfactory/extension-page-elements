<?php

use PgFactory\PageFactory\Link;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\preparePath;

/*
 *	Example:
 *		determin whether running 'onair', set ~data/ accordingly.
*/

// ONAIR:
$onair = (str_contains($_SERVER['SCRIPT_FILENAME'], 'onair'));
// direct ~data/ to cm-db/ if running onair:
if ($onair) {
    PageFactory::$dataPath = '../db/';
}

// define {{ form-mailto }} depending on dev-status:
$email = $onair? 'info@domain.net' : 'webmaster@domain.net';
TransVars::setVariable('form-mailto', $email);


