<?php

namespace PgFactory\PageFactoryElements;


use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\compileMarkdown;

class Popup
{
    public static $inx = 1;

    /**
     * @param string $msg
     * @param string $header
     * @param bool $mdCompile
     * @return string
     */
    public function render(string $msg, string $header = '&nbsp;', bool $mdCompile = false): string
    {
        $html = '';
        if ($msg) {
            if (strpos($msg, '{{') !== false) {
                $msg = TransVars::translate($msg);
            }
            if ($mdCompile) {
                $msg = compileMarkdown($msg);
            }

            $inx = self::$inx++;
            $html = "\t\t<div class='pfy-popup-src pfy-popup-src-$inx'><div class='pfy-popup'>$msg</div></div>\n";
            $jq = "pfyPopup({contentFrom: '.pfy-popup-src-$inx .pfy-popup', header:'$header', draggable: true})";
            Page::addJsReady($jq);
            Page::addAssets('POPUPS');
        }
        return $html;
    } // render


    /**
     * @param string $str
     * @param string $header
     * @param $mdCompile
     * @return void
     */
    public function set(string $str, string $header, $mdCompile = false): void
    {
        $str = $this->render($str, $header, $mdCompile);
        Page::addBodyEndInjections($str);
    } // set

} // Popup