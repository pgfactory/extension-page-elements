<?php

$session = kirby()->session();
$message = $session->get('pfy.message');
if ($message) {
    // show message:
    $session->remove('pfy.message');
    $msg = new \Usility\PageFactory\PageElements\Message($this->pfy);
    $msg->setMessage($message);
}

