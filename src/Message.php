<?php

namespace PgFactory\PageFactoryElements;


use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\TransVars;

class Message extends PageElements
{
    /**
     * @param $msg
     * @param $mdCompile
     * @return string
     */
    public function render($msg, $mdCompile)
    {
        $html = '';
        if ($msg) {
            if (str_contains($msg, '{{')) {
                $msg = TransVars::translate($msg);
            }
            if ($mdCompile) {
                $msg = \PgFactory\PageFactory\compileMarkdown($msg);
            }

            $html = "\t\t<div class='pfy-msgbox'>$msg</div>\n";
            Page::addAssets('MESSAGES');
        }
        return $html;
    } // render


    /**
     * @param string $str
     * @param $mdCompile
     * @return void
     */
    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        Page::addBodyEndInjections($str);
    } // set
} // Message