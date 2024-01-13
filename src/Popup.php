<?php

namespace PgFactory\PageFactoryElements;


class Popup extends PageElements
{
    public static $inx = 1;

    public function render(string $msg, string $header = '&nbsp;', bool $mdCompile = false): string
    {
        $html = '';
        if ($msg) {
            if (strpos($msg, '{{') !== false) {
                $msg = $this->trans->translate($msg);
            }
            if ($mdCompile) {
                $msg = \PgFactory\PageFactory\compileMarkdown($msg);
            }

            $inx = self::$inx++;
            $html = "\t\t<div class='pfy-popup-src pfy-popup-src-$inx'><div class='pfy-popup'>$msg</div></div>\n";
            $jq = "pfyPopup({contentFrom: '.pfy-popup-src-$inx .pfy-popup', header:'$header', draggable: true})";
            $this->pg->addJq($jq);
            $this->addAssets('POPUPS');
        }
        return $html;
    } // render



    public function set(string $str, string $header, $mdCompile = false): void
    {
        $str = $this->render($str, $header, $mdCompile);
        $this->pg->addBodyEndInjections($str);
    } // set

} // Popup