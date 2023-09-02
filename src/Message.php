<?php

namespace Usility\PageFactoryElements;


use Usility\PageFactory\PageFactory;
use Usility\PageFactory\TransVars;

class Message extends PageElements
{
    public function render($msg, $mdCompile)
    {
        $html = '';
        if ($msg) {
            if (str_contains($msg, '{{')) {
                $msg = TransVars::translate($msg);
            }
            if ($mdCompile) {
                $msg = \Usility\PageFactory\compileMarkdown($msg);
            }

            $html = "\t\t<div class='pfy-msgbox'>$msg</div>\n";
            $this->addAssets('MESSAGES');
        }
        return $html;
    } // render



    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        $this->pg->addBodyEndInjections($str);
    } // set
} // Message