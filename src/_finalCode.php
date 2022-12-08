<?php

namespace Usility\PageFactory;

/*
 * PageElements finalCode
 *  - fetch js files from js/
 *  - compile all instances of {{var}} with translated text
 *  - write result to assets/js/-xy.js
 */

$transVars =    TransVars::$transVars;
$translated = [];
$files = getDir(PAGE_ELEMENTS_PATH.'js/*.js');
foreach ($files as $file) {
    $filename = basename($file);
    $out = "/* === Automatically created from $filename - do not modify! === */\n";
    $target = PAGE_ELEMENTS_PATH."assets/js/-$filename";
    $tSrc = fileTime($file);
    $tTarg = fileTime($target);
    if (($tTarg >= $tSrc) && !PageFactory::$debug) {
        continue;
    }
    $jsStr = getFile($file, true);

    // handle "use strict" -> keep it at top of file:
    if (str_contains($jsStr,'use strict')) {
        $jsStr = preg_replace("/\n[\"']use strict[\"'];\n/", '', $jsStr);
        $out .= "\"use Strict\";\n\n";
    }

    // find all {{ xy }} and replace them with ${xy}, also add 'var xy = translateVar();' at top of file:
    if (preg_match_all('/(\'?) \{\{ \s* (.*?) \s* }} (\'?)/xms', $jsStr, $m)) {
        foreach ($m[2] as $i => $key) {
            if (in_array($key, array_keys($translated))) {
                continue;
            }
            $translated[$key] = true;
            if (isset($transVars[$key])) {
                $rec = $transVars[$key];
                $val = json_encode($rec);
                $varName = translateToIdentifier($key, true);
                $out .= "var _$varName = translateVar($val);\n";
                $jsStr = str_replace($m[0][$i], '${_'.$varName.'}', $jsStr);
            }
        }
        $jsStr = $out.$jsStr;
        writeFile($target, $jsStr);
    }
} // _finalCode.php
