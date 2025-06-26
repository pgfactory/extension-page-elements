<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\TransVars;

class TwigLight
{
    /**
     * @param string $str
     * @return string
     */
    public static function compile(string $str): string
    {
        if (!str_contains($str, '{%')) {
            return $str;
        }

        $tokens = self::tokenize($str);
        list($out) = self::evalTokens($tokens);
        return $out;
    } // compile


    /**
     * @param string $input
     * @return array
     */
    private static function tokenize(string $input): array
    {
        // shield any '{' and '}', except '{%' and '%}':
        $input = str_replace(['{', '}'], ['&#123;', '&#125;'], $input);
        $input = str_replace(['&#123;%', '%&#125;'], ['{%', '%}'], $input);

        // Define the pattern for tokens -> separate {%...%} from any other text
        $pattern = '/(\{% ([^%}]*) %}) | ( [^[{%]* )/xms';

        // Tokenize the input string
        preg_match_all($pattern, $input, $matches);

        // remove empty elements:
        $tokens = array_filter($matches[0], function ($value) {
            return !empty($value);
        });

        // unshield {' and '}':
        $tokens = array_map(function ($value) {
            return str_replace(['&#123;&#123;', '&#125;&#125;'], ['{{', '}}'], $value);
        }, $tokens);

        return array_values($tokens);
    } // tokenize


    /**
     * @param array $tokens
     * @param int $i
     * @param $condition
     * @return array
     */
    static function evalTokens(array $tokens, int $i = -1, $condition = true): array
    {
        $output = '';
        $i++;
        $elseClause = '';
        while ($i < count($tokens)) {
            $token = $tokens[$i];
            switch (true) {
                case preg_match('/^{%\s*if\s+(.*?)\s*%}$/', $token, $matches):
                    $varname = trim($matches[1]);
                    if (!preg_match('/\W/', $varname)) {
                        // condition contains nothing but a string, i.e. a variable:
                        $condition1 = (bool)TransVars::getVariable($varname);
                    } else {
                        // condition contains special characters, so try to evaluate it as a PHP expression:
                        $expr = '';
                        $tok = strtok($varname, ' ');
                        $tok = TransVars::getVariable($tok, varNameIfNotFound:true);
                        if (!preg_match('/\W/', $tok)) {
                            $tok = "'$tok'";
                        }
                        $expr .= "$tok ";
                        while ($tok !== false) {
                            $tok = strtok(' ');
                            if ($tok !== false) {
                                $tok = TransVars::getVariable($tok, varNameIfNotFound:true);
                                if (!preg_match('/\W/', $tok)) {
                                    $tok = "'$tok'";
                                }
                                $expr .= "$tok ";
                            }
                        }
                        try {
                            $condition1 = eval('return ' . $expr . ';');
                        } catch (\Exception $e) {
                            $condition1 = false;
                        }
                    }
                    // decend into nexted if-else-endif structure:
                    list($out, $i) = self::evalTokens($tokens, $i, $condition1);
                    $output .= $out;
                    break;

                case $token === '{% else %}':
                    $elseClause = true;
                    break;

                case $token === '{% endif %}':
                    if (!$condition) {
                        $output = $elseClause;
                    }
                    return [$output, $i, $condition];

                default:
                    if ($elseClause) {
                        $elseClause = $token;
                    } else {
                        $output .= $token;
                    }
            }
            $i++;
        }
        return [$output, $i, $condition];
    } // evalTokens

} // TwigLight