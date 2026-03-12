<?php

namespace PgFactory\PageFactoryElements;


use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\compileMarkdown;

class Message extends PageElements
{
    /**
     * @param string $msg
     * @param bool $mdCompile
     * @return string
     */
    public function render(string $msg, bool $mdCompile = false): string
    {
        $html = '';
        if ($msg) {
            if (str_contains($msg, '{{')) {
                $msg = TransVars::translate($msg);
            }
            if ($mdCompile) {
                $msg = compileMarkdown($msg);
            }

            $html = "\t\t<div class='pfy-msgbox'>$msg</div>\n";
            Page::addAssets('MESSAGES');
        }
        return $html;
    } // render


    /**
     * @param string $str
     * @param bool $mdCompile
     * @return void
     */
    public function set(string $str, bool $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        if ($str) {
            Page::addBodyEndInjections($str);
        }
    } // set
} // Message