<?php

namespace Usility\PageFactory\PageElements;

class Popup extends PageElements
{
    public static $inx = 1;

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

            $inx = self::$inx++;
            $html = "\t\t<div id='pfy-popup-$inx' class='pfy-popup'>$msg</div>\n";
            $jq = "pfyPopup({contentFrom: '#pfy-popup-$inx', header:'&nbsp;', draggable: true})";
            $this->pg->addJq($jq);
            $this->addAssets('POPUPS');
        }
        return $html;
    } // render



    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        PageFactory::$pg->addBodyEndInjections($str);
    } // set

} // Popup