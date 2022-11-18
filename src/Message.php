<?php

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory as PageFactory;


class Message extends PageElements
{
    public function render($msg, $mdCompile)
    {
        $html = '';
        if ($msg) {
            if (strpos($msg, '{{') !== false) {
                $msg = $this->trans->translate($msg);
            }
            if ($mdCompile) {
                $msg = \Usility\PageFactory\compileMarkdown($msg);
            }

            $html = "\t\t<div class='pfy-msgbox'>$msg</div>\n";
            $this->pg->requireJQuery();
            $this->addAssets('MESSAGES');
        }
        return $html;
    } // render



    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        PageFactory::$pg->addBodyEndInjections($str);
    } // set
} // Message