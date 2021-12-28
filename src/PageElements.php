<?php

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory;


class PageElements
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->pg = $pfy->pg;
        $this->trans = PageFactory::$trans;
        $this->extensionPath = dirname(dirname(__FILE__)).'/';
    } // __construct


    /**
     * Renders content in an overlay.
     * @param string $str
     * @param false $mdCompile
     */
    public function setOverlay(string $str, $mdCompile = false): void
    {
        $pe = new Overlay($this->pfy);
        $str = $pe->render( $str, $mdCompile);
        $this->pfy->pg->addBodyEndInjections($str);
    } // setOverlay


    /**
     * Renders cotent in a message that appears briefly in the upper right corner.
     * @param string $str
     * @param false $mdCompile
     */
    public function setMessage(string $str, $mdCompile = false): void
    {
        $pe = new Message($this->pfy);
        $str = $pe->render($str, $mdCompile);
        $this->pfy->pg->addBodyEndInjections($str);
    } // setMessage


    /**
     * Renders content in a popup window.
     * @param string $str
     * @param false $mdCompile
     */
    public function setPopup(string $str, $mdCompile = false): void
    {
        $pe = new Popup($this->pfy);
        $str = $pe->render($str, $mdCompile);
        $this->pfy->pg->addBodyEndInjections($str);
    } // setPopup




} // PageElements