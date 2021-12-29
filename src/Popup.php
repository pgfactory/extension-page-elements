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
            $html = "\t\t<div id='lzy-popup-$inx' class='lzy-popup'>$msg</div>\n";
            $jq = "lzyPopup({contentFrom: '#lzy-popup-$inx', header:'&nbsp;', draggable: true})";
            $this->pg->addJq($jq);
            $this->pg->addAssets(
                [
                    $this->extensionPath.'scss/popup.scss',
                    $this->extensionPath.'third-party/tooltipster/css/tooltipster.bundle.min.css',
                    $this->extensionPath.'third-party/tooltipster/js/tooltipster.bundle.min.js',
                    $this->extensionPath.'third-party/jquery.event.ue/jquery.event.ue.min.js',
                    $this->extensionPath.'third-party/javascript-md5/md5.min.js',
                    $this->extensionPath.'js/popup.js'
                ]
            );
        }
        return $html;
    } // render



    public function set(string $str, $mdCompile = false): void
    {
        $str = $this->render($str, $mdCompile);
        $this->pfy->pg->addBodyEndInjections($str);
    } // set

} // Popup