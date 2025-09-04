<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\mylog;

class EnlistCallbackHandler
{
    private $options;
    private $db;
    private $isEnlistAdmin = false;
    private $pagePath;

    // === Callback: handle user response ==========================

    /**
     * @param object $enlist
     * @param array $newDataRec
     * @return string
     */
    public function callback(object $enlist, array $newDataRec): string
    {
        $this->db = $enlist->db;
        $this->options = $enlist->getOptions();
        $widgetInx = $newDataRec['widgetInx'];
        $arr = explode('/', $widgetInx);
        $widgetInx = $arr[0];
        $slotInx = $arr[1]??0;
        $this->pagePath = page()->id();
        $context = "[$widgetInx: ".PFY_HOST_URL.$this->pagePath.']';
        if ($this->isEnlistAdmin) {
            $context = rtrim($context, ']').' (as admin)]';
        }
        $widgetDescr = $enlist->db->getWidgetDescr($widgetInx);

        $message = '';
        $mode = $newDataRec['mode'];
        $newDataRec['_time'] = date('Y-m-d\TH:i');

        unset($newDataRec['mode']);
        unset($newDataRec['_dataSrcInx']);
        unset($newDataRec['_cancel']);
        unset($newDataRec['_reckey']);
        unset($newDataRec['_csrf']);
        unset($newDataRec['enlistElemInx']);
        unset($newDataRec['setname']);

        $this->checkWidgetDeadline($newDataRec, $context);

        if ($mode === 'add') {
            // new entry:
            Utils::setSessionVar('pfy.enlist.name', $newDataRec['Name']);
            Utils::setSessionVar('pfy.enlist.email', $newDataRec['Email']);
            $this->handleNewEntry($newDataRec, $widgetDescr, $slotInx, $context, $widgetInx, $message);

        } else {
            $this->handleExistingEntry($mode, $widgetDescr, $slotInx, $message, $newDataRec, $context, $widgetInx);
        }
        return ''; // don't continue with default processing
    } // callback


    /**
     * @param array $newDataRec
     * @param array $widgetDescr
     * @param string $slotInx
     * @param string $context
     * @param mixed $widgetInx
     * @param string $message
     * @return void
     * @throws \Exception
     */
    private function handleNewEntry(array $newDataRec, array $widgetDescr, string $slotInx, string $context, mixed $widgetInx, string $message): void
    {
        $name = $newDataRec['Name'] ?? '#####';
        $exists = array_filter($widgetDescr['slots'], function ($e) use ($name) {
            return ($e['Name'] ?? '') === $name;
        });
        if ($exists) {
            $prevRec = reset($exists);
            $email = $newDataRec['Email'] ?? '#####';
            $email0 = $prevRec['Email']??false;
            if ($email0 === $email) {
                mylog("EnList error Rec exists: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-rec-exists }}');
            }
        } else {
            $newDataRec['_time'] = date('Y-m-d\TH:i');
        }

        $this->db->fillSlot($widgetInx, $slotInx, $newDataRec, $context);

        $this->handleNotifyOwner($newDataRec, 'add', $widgetDescr['title']??'');

        if ($this->handleSendConfirmation($newDataRec, $widgetDescr['title']??'')) {
            mylog("EnList new entry & confirmation sent: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-confirmation-sent }}');
        }
        mylog("EnList new entry: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
        reloadAgent(message: $message);
    } // handleNewEntry


    /**
     * @param string $mode
     * @param array $widgetDescr
     * @param string $slotInx
     * @param string $alertMsg
     * @param array $newDataRec
     * @param string $context
     * @param mixed $widgetInx
     * @return void
     */
    private function handleExistingEntry(string $mode, array $widgetDescr, string $slotInx, string $alertMsg, array $newDataRec, string $context, mixed $widgetInx): void
    {
        $slots = $this->db->getEnlistSlots($widgetInx);
        $thisSlot = &$slots[$slotInx];
        if (!$this->isEnlistAdmin && (($thisSlot['Email']??false) !== $newDataRec['Email'])) {
            reloadAgent(message: '{{ pfy-enlist-del-error-wrong-email }}');
        }
        if ($mode === 'del') {
            $this->checkSlotFreezTime($widgetDescr, $widgetInx, $slotInx);

            $becameActive = $this->db->emptySlot($widgetInx, $slotInx);
            $mode = $becameActive ? 'activated' : 'del';
            $becameActiveName = $becameActive['Name'] ?? 'somebody';
            $this->handleNotifyOwner($newDataRec, $mode, ($widgetDescr['title']??''), $becameActiveName);

            if ($becameActive) {
                $this->notifyActivatedReserve($becameActive, $widgetDescr['title']);
            }
            reloadAgent(message: '{{ pfy-enlist-confirmation-banner-deleted }}');

        } else { // modify
            $this->modifyExistingEntry($newDataRec, $slotInx, $widgetInx, $slots, $thisSlot);
        }
    } // handleExistingEntry


    /**
     * @param array $newDataRec
     * @param string $slotInx
     * @param mixed $widgetInx
     * @return void
     */
    private function modifyExistingEntry(array $newDataRec, string $slotInx, mixed $widgetInx, array $slots, array $thisSlot): void
    {
        foreach ($newDataRec as $key => $value) {
            if (str_contains('Email,directlyToReserve,delete_entry,widgetInx,_time', $key)) {
                continue;
            }
            $thisSlot[$key] = $value;
        }
        $thisSlot['_time'] = date('Y-m-d\TH:i');
        $this->db->updateWidgetSlots($widgetInx, $slots);
        reloadAgent(message: '{{ pfy-enlist-modified }}');
    } // modifyExistingEntry


    /**
     * @param array $newDataRec
     * @param string $context
     * @return void
     * @throws \Exception
     */
    private function checkWidgetDeadline(array $newDataRec, string $context): void
    {
        if ($widgetDescr['deadlineExpired']??false) {
            if ($this->isEnlistAdmin) {
                $message = '{{ pfy-enlist-error-deadline-was-expired }}';
            } else {
                mylog("EnList error deadline exeeded: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-deadline-expired }}');
            }
        }
    } // checkWidgetDeadline


    /**
     * @param array $widgetDescr
     * @param int|string $widgetInx
     * @param int $slotInx
     * @return void
     */
    private function checkSlotFreezTime(array $widgetDescr, int|string $widgetInx, int $slotInx): void
    {
        if ($this->options['isEnlistAdmin']) {
            return;
        }
        if ($freezeTime = $widgetDescr['freezeTime']??false) {
            $slots = $this->db->getWidgetSlots($widgetInx);
            $storeTime = $slots[$slotInx]['_time'];
            $freezeTime = time() - ($freezeTime * PFY_FREEZETIMIE_UNIT);
            $storeTime = strtotime($storeTime);
            if ($storeTime < $freezeTime) {
                reloadAgent(message: '{{ pfy-enlist-del-freeze-time-expired }}');
            }
        }
    } // checkSlotFreezTime


    /**
     * @param array $rec
     * @param string $title
     * @return void
     */
    public function notifyActivatedReserve(array $rec, string $title): void
    {
        if (!$this->options['notifyActivatedReserve']) {
            return;
        }
        EnlistComm::notifyActivatedReserve($rec, $title);
    } // notifyActivatedReserve


    /**
     * @param array $newDataRec
     * @param string $mode
     * @param string $title
     * @param string $nameActivated
     * @return void
     */
    private function handleNotifyOwner(array $newDataRec, string $mode, string $title, string $nameActivated = ''): void
    {
        if (!($to = $this->options['notifyOwner']??false)) {
            return;
        }
        EnlistComm::notifyOwner($to, $newDataRec, $mode, $title, $nameActivated);
    } // handleNotifyOwner


    /**
     * @param array $newDataRec
     * @param string $title
     * @return bool
     */
    private function handleSendConfirmation(array $newDataRec, string $title): bool
    {
        if (!$this->options['sendConfirmation']??false) {
            return false;
        }
        EnlistComm::sendConfirmation($newDataRec, $title);
        return true;
    } // handleSendConfirmation

} // Enlist