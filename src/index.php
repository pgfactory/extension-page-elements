<?php

// If reloadAgent() queued a message to be displayed, it's intercepted here and activated.
// This is because this feature is available only if PageElements extension is loaded.

$session = kirby()->session();
$message = $session->get('pfy.message');
if ($message) {
    // show message:
    $session->remove('pfy.message');
    $msg = new \Usility\PageFactory\PageElements\Message($this->pfy);
    $msg->set($message);
}

