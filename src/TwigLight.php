<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\TransVars;

class TwigLight
{
    /**
     * @param string $str
     * @return string
     */
    public static function compile(string $str, array $data = []): string
    {
        if (!str_contains($str, '{%')) {
            return $str;
        }
        if ($data) {
            TransVars::setTempVariables($data);
        }

        $tokens = self::tokenize($str);
        list($out) = self::evalTokens($tokens);
        if ($data) {
            TransVars::purgeTempVariables();
        }
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
        $pattern = '/(\{% (.*?) %}) | ( [^{]+ )/xs';

        // Tokenize the input string
        preg_match_all($pattern, $input, $matches);

        // remove empty elements:
        $tokens = array_filter($matches[0], function ($value) {
            return !empty($value);
        });

        // unshield {' and '}':
        $tokens = array_map(function ($value) {
            return preg_replace(['/&#123;(?! %)/', '/(?<! %)&#125;/'], ['{', '}'], $value);
        }, $tokens);

        return array_values($tokens);
    } // tokenize


    /**
     * @param array $tokens
     * @param int $i
     * @param $condition
     * @return array
     */
    static function evalTokens(array $tokens, int $i = -1, $level = 0): array
    {
        $output = '';
        $ifTrue = $ifFalse = '';
        $i++;
        while ($i < count($tokens)) {
            $token = $tokens[$i];
            switch (true) {
                case preg_match('/^{%\s*if\s+(.*?)\s*%}$/', $token, $matches):
                    $varname = trim($matches[1]);
                    if (!preg_match('/\W/', $varname)) {
                        // condition contains nothing but a string, i.e. a variable:
                        $condition = (bool)TransVars::getVariable($varname);
                    } else {
                        // condition contains special characters, so try to evaluate it as a PHP expression:
                        $condition = self::evalExpression($varname);
                    }
                    // descend into nexted if-else-endif structure:
                    list($out, $ifTrue, $ifFalse, $i) = self::evalTokens($tokens, $i, $level + 1);
                    $output .= $out;
                    if ($condition) {
                        $output .= $ifTrue;
                    } else {
                        $output .= $ifFalse;
                    }
                    break;

                case preg_match('/\{%\s*else\s*%}/', $token):
                    $ifTrue = $output;
                    list($out, $ifFalse, $dummy, $i) = self::evalTokens($tokens, $i, $level + 1);
                    return [$out, $ifTrue, $ifFalse, $i];

                case preg_match('/\{%\s*endif\s*%}/', $token):
                    return ['', $output, $ifFalse, $i];

                default:
                    $output .= $token;
            }
            $i++;
        }
        return [$output, $ifTrue, $ifFalse, $i];
    } // evalTokens


    /**
     * @param string $varname
     * @return mixed
     */
    private static function evalExpression(string $varname): mixed
    {
        $expr = '';
        $tok = strtok($varname, ' ');
        $tok = TransVars::getVariable($tok, varNameIfNotFound: true);
        if (preg_match('/[a-zA-Z_]/', $tok)) {
            $tok = "'$tok'";
        }
        $expr .= "$tok ";
        while ($tok !== false) {
            $tok = strtok(' ');
            if ($tok !== false) {
                $tok = TransVars::getVariable($tok, varNameIfNotFound: true);
                if (preg_match('/[a-zA-Z_]/', $tok)) {
                    $tok = ".'$tok'";
                }
                $expr .= "$tok ";
            }
        }
        try {
            $condition1 = eval('return ' . $expr . ';');
        } catch (\Exception $e) {
            $condition1 = false;
        }
        return $condition1;
    } // evalExpression

} // TwigLight