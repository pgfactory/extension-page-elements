<?php
/*
 * CssRefactor
 *
 * Usage: ?cssrefactor=file
 *    or  ?cssrefactor=path/
 */

namespace Usility\PageFactoryElements;
use Usility\PageFactory\Scss;
use function Usility\PageFactory\explodeTrim;
use function Usility\PageFactory\fileExt;
use function \Usility\PageFactory\getFile;
use function \Usility\PageFactory\strPosMatching;

const CSS_UNARY_KEYWORDS = ",@charset,@import,@layer,@namespace,";
const CSS_OTHER_KEYWORDS =
    ",@font-face,@keyframes,@media,@page,@color-profile,@container,@counter-style,".
    "@font-feature-values,@font-palette-values,@layer,@property,@supports,";

class CssRefactor
{

    public static function exec($file)
    {
        $css = getFile($file, 'c');
        $array = self::toArray($css);
        $array = self::array_orderby($array, 'key', SORT_ASC, 'rule', SORT_ASC, 'inx', SORT_ASC);

        uksort($array, function ($a,$b) {
            $a = str_replace(':', '!', $a);
            $b = str_replace(':', '!', $b);
            return strcasecmp($a, $b);
        });

        $scss = self::toScss($array);
        $toFile = fileExt($file, true).'.scss';
        file_put_contents($toFile, $scss);

        if (self::check($array, $scss)) {
            return $toFile;
        } else {
            return [$file, $toFile];
        }
    } // exec


    public static function toArray($str, $assoc = true, $prefix = '')
    {
        $array = [];
        $inx = 0;
        $str = preg_replace('/\s+/ms', ' ', $str);
        [$p1, $p2] = strPosMatching($str, 0, '{', '}');
        while ($p1 !== false) {
            if ($p2 === null) {
                throw new \Exception("Error in CSS: unmachted braces");
            }
            $keyStr = substr($str, 0, $p1);

            if ($keyStr[0] === '@') {
                list($k) = explodeTrim(' ', strtolower($keyStr));
                if (str_contains(CSS_UNARY_KEYWORDS, ",$k,")) {
                    $p2 = strpos($keyStr, ';');
                    $keyStr = substr($str, 0, $p2+1);
                    $str = ltrim(substr($str, $p2+1));
                    if ($assoc) {
                        $array[$k] = ['key' => $keyStr, 'rule' => '', 'inx' => 0];
                    } else {
                        $array[] = [$keyStr, '', 0];
                    }
                    [$p1, $p2] = strPosMatching($str, 0, '{', '}');
                    continue;

                } elseif ($k === '@page') {
                    $ruleStr = substr($str, $p1+1, $p2-$p1-1);
                    $array[$k] = ['key' => $keyStr, 'rule' => $ruleStr, 'inx' => 0];
                    $str = ltrim(substr($str, $p2+1));
                    [$p1, $p2] = strPosMatching($str, 0, '{', '}');
                    continue;

                } else {
                    $str1 = substr($str, $p1+1, $p2-$p1-1);
                    $array[$keyStr] = ['key' => $keyStr, 'rule' => '', 'inx' => 0];
                    $array1 = self::toArray($str1, true, $keyStr);
                    $array = array_merge_recursive($array, $array1);
                    $str = ltrim(substr($str, $p2+1));
                    [$p1, $p2] = strPosMatching($str, 0, '{', '}');
                    continue;
                }
            }
            $keys = explodeTrim(',', $keyStr, true);

            $ruleStr = substr($str, $p1+1, $p2-$p1-1);
            $str = ltrim(substr($str, $p2+1));
            $rules = explodeTrim(';', $ruleStr, true);
            foreach ($rules as $rule) {
                foreach ($keys as $key) {
                    $inx++;
                    $key = ltrim("$prefix $key");
                    if ($assoc) {
                        $array[$key] = ['key' => $key, 'rule' => "$rule;", 'inx' => $inx];
                    } else {
                        $array[] = [$key, "$rule;", $inx];
                    }
                }
            }
            [$p1, $p2] = strPosMatching($str, 0, '{', '}');
        }
        return $array;
    } // toArray


    private static function toScss($array, $prefix = '', $level = 0)
    {
        $array = array_values($array);
        $scss = "\n";
        $indent = str_pad('', $level*4, ' ');
        for ($i=0; $i<sizeof($array); $i++) {
            $baseKey = $array[$i]['key'];
            list($k) = explode(' ', strtolower($baseKey));
            if (str_contains(CSS_UNARY_KEYWORDS, ",$k,")) {
                $scss .= "$baseKey\n\n";
                continue;
            }
            $pattern = "/^$baseKey(?![-\w])/";
            $j = $i;
            while (isset($array[$j]['key']) && preg_match($pattern, $array[$j]['key'])) {
                $j++;
            }
            $j--;
            if ($prefix) {
                $k = str_replace($prefix, '', $baseKey);
                if ($k[0] !== ' ') {
                    $k = "&$k";
                } else {
                    $k = ltrim($k);
                }
                $scss .= "$indent$k {\n";
            } else {
                $scss .= "$indent$baseKey {\n";
            }
            $arr = [];
            for (; $i<=$j; $i++) {
                $rec = $array[$i];
                if ($baseKey === $rec['key']) {
                    $scss .= "$indent    {$rec['rule']}\n";
                } else {
                    $arr[] = $array[$i];
                }
            }
            $i--;
            if ($arr) {
                $scss .= self::toScss($arr, $baseKey, $level+1);
            }
            if ($prefix) {
                $scss .= "$indent}\n\n";
            } else {
                $scss .= "$indent}// $baseKey\n\n";
            }
        }
        $scss = substr($scss, 0, strlen($scss)-1);
        return $scss;
    } // toScss



    private static function writeToCsv($file, $array)
    {
        $array = self::removeInx($array);
        $file = fileExt($file, true).'.csv';
        $fp = fopen($file, 'w');

        foreach ($array as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);
    } // writeToCsv


    private static function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    } // array_orderby


    private static function removeInx($array)
    {
        foreach ($array as $i => $rec) {
            unset($array[$i]['inx']);
        }
        return $array;
    } // removeInx


    private static function check($array, $scss)
    {
        $array = self::removeInx($array);

        $css = Scss::compileStr($scss);
        $array2 = self::toArray($css);
        $array2 = self::removeInx($array2);

        foreach ($array as $k => $rec) {
            if (!isset($array2[$k])) {
                return false;
            }
            $str1 = var_export($rec, true);
            $str1 = str_replace("\\'", '"', $str1);
            $str2 = var_export($array2[$k], true);
            $str2 = str_replace("\\'", '"', $str2);
            if ($str1 !== $str2) {
                return false;
            }
        }

        foreach ($array2 as $k => $rec) {
            if (!isset($array[$k])) {
                return false;
            }
            $str1 = var_export($rec, true);
            $str1 = str_replace("\\'", '"', $str1);
            $str2 = var_export($array[$k], true);
            $str2 = str_replace("\\'", '"', $str2);
            if ($str1 !== $str2) {
                return false;
            }
        }
        return true;
    } // check

} // CssRefactor