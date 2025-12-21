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
        $widgetKey = $newDataRec['widgetKey'];
        $slotInx = $newDataRec['_reckey'];
        $this->pagePath = page()->id();
        $context = "[$widgetKey: ".PFY_HOST_URL.$this->pagePath.']';
        if ($this->isEnlistAdmin) {
            $context = rtrim($context, ']').' (as admin)]';
        }
        $widgetDescr = $enlist->db->getWidgetDescr($widgetKey);

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
            $this->handleNewEntry($newDataRec, $widgetDescr, $slotInx, $context, $widgetKey, $message);

        } else {
            $this->handleExistingEntry($mode, $widgetDescr, $slotInx, $newDataRec, $context, $widgetKey);
        }
        return ''; // don't continue with default processing
    } // callback


    /**
     * @param array $newDataRec
     * @param array $widgetDescr
     * @param string $slotInx
     * @param string $context
     * @param mixed $widgetKey
     * @param string $message
     * @return void
     * @throws \Exception
     */
    private function handleNewEntry(array $newDataRec, array $widgetDescr, string $slotInx, string $context, mixed $widgetKey, string $message): void
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
        $this->db->fillSlot($widgetKey, (int)$slotInx, $newDataRec, $context);

        $directlyToReserve = $newDataRec['directlyToReserve']? ' (to reserve)': '';
        $this->handleNotifyOwner($newDataRec, 'add', $widgetKey);

        if ($this->handleSendConfirmation($newDataRec, 'add', $widgetKey)) {
            mylog("EnList new entry$directlyToReserve & confirmation sent: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-confirmation-sent }}');
        }
        mylog("EnList new entry$directlyToReserve: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
        reloadAgent(message: $message);
    } // handleNewEntry


    /**
     * @param string $mode
     * @param array $widgetDescr
     * @param string $slotInx
     * @param string $alertMsg
     * @param array $newDataRec
     * @param string $context
     * @param mixed $widgetKey
     * @return void
     */
    private function handleExistingEntry(string $mode, array $widgetDescr, string $slotInx, array $newDataRec, string $context, mixed $widgetKey): void
    {
        if ($newDataRec['directlyToReserve']) {
            list($slots, $slotInx) = $this->moveSlot($widgetKey, $slotInx, $newDataRec, $context);
        } else {
            $slots = $this->db->getEnlistSlots($widgetKey);
        }
        $title = $newDataRec['widgetTitle']??'';
        $thisSlot = &$slots[$slotInx];
        $thisSlot['Email'] = $thisSlot['Email']??'';
        if (!$this->isEnlistAdmin && ($thisSlot['Email'] !== $newDataRec['Email'])) {
            mylog("EnList delete attempt with wrong email: {$newDataRec['Name']} {$newDataRec['Email']} (!= {$thisSlot['Email']})", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-del-error-wrong-email }}');
        }
        if ($mode === 'del') {
            $this->checkSlotFreezTime($widgetDescr, $widgetKey, $slotInx);

            $deletedRec = $slots[$slotInx];
            $becameActiveRec = $this->db->emptySlot($widgetKey, $slotInx);
            $mode = $becameActiveRec ? 'activated' : 'del';
            $becameActiveName = $becameActiveRec['Name'] ?? 'somebody';

            if ($becameActiveRec) {
                $this->sendActivatedConfirmation($becameActiveRec, $deletedRec, $widgetKey, $title);
            } else {
                $this->handleNotifyOwner($newDataRec, $mode, $widgetKey, $becameActiveName);
                $this->handleSendConfirmation($newDataRec, $mode, $widgetKey);
            }

            mylog("EnList entry deleted: {$newDataRec['Name']} {$newDataRec['Email']}", 'enlist-log.txt');
            reloadAgent(message: '{{ pfy-enlist-confirmation-banner-deleted }}');

        } else { // modify
            $this->modifyExistingEntry($newDataRec, $widgetKey, $slots, $slotInx, $thisSlot);
        }
    } // handleExistingEntry


    /**
     * @param mixed $widgetKey
     * @param string $slotInx
     * @param array $newDataRec
     * @param string $context
     * @return array
     */
    private function moveSlot(mixed $widgetKey, string $slotInx, array $newDataRec, string $context): array
    {
        $slots = $this->db->getEnlistSlots($widgetKey);
        $slotInx1 = $this->db->selectSlot($widgetKey, $slotInx, $newDataRec, $context);
        if (intval($slotInx) !== $slotInx1) {
            $tmp = $slots[$slotInx];
            foreach ($tmp as $key => $value) {
                if ($newDataRec[$key] && $newDataRec[$key] !== $value) {
                    $tmp[$key] = $newDataRec[$key];
                }
            }
            $this->db->emptySlot($widgetKey, $slotInx);
            $this->db->fillSlot($widgetKey, $slotInx1, $tmp, $context);
        }

        return [$slots, $slotInx1];
    } // moveSlot


    /**
     * @param array $newDataRec
     * @param string $slotInx
     * @param mixed $widgetKey
     * @return void
     */
    private function modifyExistingEntry(array $newDataRec, mixed $widgetKey, array $slots, string $slotInx, array $thisSlot): void
    {
        $log = '';
        foreach ($newDataRec as $key => $value) {
            if (str_contains('Email,directlyToReserve,delete_entry,widgetKey,_time', $key)) {
                continue;
            }
            $thisSlot[$key] = $value;
            $log .= is_string($value) ? "$key: $value;" : "$key: ".implode(',', $value).';';
        }
        $thisSlot['_time'] = date('Y-m-d\TH:i');
        $slots[$slotInx] = $thisSlot;
        $this->db->updateWidgetSlots($widgetKey, $slots);
        mylog("EnList entry modified: {$newDataRec['Email']} $log", 'enlist-log.txt');
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
                mylog("EnList error deadline exeeded (but executed as admin): {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
            } else {
                mylog("EnList error deadline exeeded: {$newDataRec['Name']} {$newDataRec['Email']} $context", 'enlist-log.txt');
                reloadAgent(message: '{{ pfy-enlist-error-deadline-expired }}');
            }
        }
    } // checkWidgetDeadline


    /**
     * @param array $widgetDescr
     * @param int|string $widgetKey
     * @param int $slotInx
     * @return void
     */
    private function checkSlotFreezTime(array $widgetDescr, int|string $widgetKey, int $slotInx): void
    {
        if ($this->options['isEnlistAdmin']) {
            return;
        }
        if ($freezeTime = $widgetDescr['freezeTime']??false) {
            $slots = $this->db->getWidgetSlots($widgetKey);
            $storeTime = $slots[$slotInx]['_time'];
            $freezeTime = time() - ($freezeTime * PFY_FREEZETIMIE_UNIT);
            $storeTime = strtotime($storeTime);
            if ($storeTime < $freezeTime) {
                mylog("EnList error freezeTime exeeded (widgetKey:$widgetKey, slotInx:$slotInx)");
                reloadAgent(message: '{{ pfy-enlist-del-freeze-time-expired }}');
            }
        }
    } // checkSlotFreezTime


    /**
     * @param array $rec
     * @param string $title
     * @return void
     */
    public function sendActivatedConfirmation(array $becameActiveRec, array $deletedRec, string $widgetKey, string $title): void
    {
        if (!$this->options['notifyActivatedReserve']) {
            return;
        }
        if (!($to = $this->options['notifyOwner']??false)) {
            return;
        }
        if ($deletedRec) {
            $mode = 'del&activated';
        } else {
            $mode = 'activated';
        }
        EnlistComm::sendActivatedConfirmation($becameActiveRec, $widgetKey);
        EnlistComm::sendNotification($to, $becameActiveRec, $mode, $widgetKey, $deletedRec);
    } // sendActivatedConfirmation


    /**
     * @param array $newDataRec
     * @param string $mode
     * @param string $title
     * @param string $nameActivated
     * @return void
     */
    private function handleNotifyOwner(array $newDataRec, string $mode, string $widgetKey): void
    {
        if (!($to = $this->options['notifyOwner']??false)) {
            return;
        }
        EnlistComm::sendNotification($to, $newDataRec, $mode, $widgetKey);
    } // handleNotifyOwner


    /**
     * @param array $newDataRec
     * @param string $title
     * @return bool
     */
    private function handleSendConfirmation(array $newDataRec, string $mode, string $widgetKey): bool
    {
        if (!$this->options['sendConfirmation']??false) {
            return false;
        }
        EnlistComm::sendConfirmation($newDataRec, $mode, $widgetKey);
        return true;
    } // handleSendConfirmation

} // Enlist