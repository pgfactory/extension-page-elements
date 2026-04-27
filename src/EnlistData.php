<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\DataStore;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\mylog;

const   WIDGET_DATA_TEMPLATE = [
        'slots'         => [],
        'title'         => '',
        'nSlots'        => 0,
        'nReserveSlots' => 0,
];

class EnlistData
{
    private $options;
    private $nSlots = 0;
    private $nReserveSlots = 0;
    private $nTotalSlots = 0;
    private $db;
    private $dataFile;
    private $deadlineExpired = false;
    private array $enlistWidgets = [];


    /**
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options)
    {
        $this->options = $options;
        $this->parseOptions($options);
        $this->openDb();
    } // __construct


    /**
     * @param int|string $widgetKey
     * @return array|false
     */
    public function getWidgetDescr(int|string $widgetKey): array|false
    {
        return $this->findWidgetDescr($widgetKey);
    } // getWidgetDescr


    /**
     * @param int|string $widgetKey
     * @return array|false
     */
    public function getWidgetSlots(int|string $widgetKey): array|false  // -> used by EnlistCallbackHandler
    {
        $widgetDescr = $this->getWidgetDescr($widgetKey);
        return $widgetDescr ? $widgetDescr['slots'] : false;
    } // getWidgetSlots


    /**
     * @param mixed $widgetKey
     * @return void
     */
    public function collapseEmptySlots(mixed $widgetKey): void
    {
        $widgetDescr = $this->enlistWidgets[$widgetKey];
        $widgetSlots = $widgetDescr['slots'];
        $entriesFound = false;
        $toNotify = [];
        for ($i = $this->nTotalSlots-1; $i>=0; $i--) {
            $slotOccupied = $widgetSlots[$i]['Name']??false;
            if ($entriesFound) {
                // remove this entry:
                if (!$slotOccupied) {
                    if ($i < $this->nSlots && ($widgetSlots[$this->nSlots]['Name']??false)) {
                        $toNotify[] = $widgetSlots[$this->nSlots];
                    }
                    unset($widgetSlots[$i]);
                    $widgetSlots[] = [];
                    $widgetSlots = array_values($widgetSlots);
                }
            } else {
                if (!$slotOccupied) {
                    continue;
                }
                $entriesFound = true;
            }
        }

        $this->enlistWidgets[$widgetKey]['slots'] = array_values($widgetSlots);
        $this->updateWidgetDescr($this->enlistWidgets[$widgetKey], recKeyToUse: $widgetKey);

        if ($toNotify) {
            $names = '';
            $nameList = '';
            foreach ($toNotify as $rec) {
                EnlistComm::sendActivatedConfirmation($rec, $widgetKey);
                $names .= '<br>- '.($rec['Name']??'');
                $nameList .= '- '.($rec['Name']??'')."\n";
            }
            if (($to = $this->options['notifyOwner']??false)) {
                $title = $widgetDescr['title'];
                EnlistComm::sendNotificationOfListCollapse($to, $widgetKey, $title, $nameList);
            }
            $msg = TransVars::getVariable('pfy-enlist-collapse-executed');
            $msg = str_replace('%names%', $names, $msg);
            reloadAgent(message: $msg);
        }
    } // collapseEmptySlots



    /**
     * @param int|string $widgetKey
     * @return array
     */
    public function getEnlistSlots(int|string $widgetKey): array
    {
        return ($this->enlistWidgets[$widgetKey]??false) ? $this->enlistWidgets[$widgetKey]['slots'] : [];
    } // getEnlistSlots


    /**
     * @return string
     */
    public function getFile(): string
    {
        return $this->dataFile;
    } // getFile


    /**
     * @param int|string $widgetKey
     * @param int $slotInx
     * @param array $newDataRec
     * @param string $context
     * @return mixed
     */
    public function fillSlot(int|string $widgetKey, int $slotInx, array $newDataRec, string $context): mixed  // -> used by EnlistCallbackHandler
    {
        $slotInx = $this->selectSlot($widgetKey, $slotInx, $newDataRec, $context);
        unset($newDataRec['directlyToReserve']);
        unset($newDataRec['widgetKey']);
        unset($newDataRec['widgetTitle']);
        $this->enlistWidgets[$widgetKey]['slots'][$slotInx] = $newDataRec;
        $this->updateWidgetDescr($this->enlistWidgets[$widgetKey], recKeyToUse: $widgetKey);
        return $slotInx;
    } // fillSlot


    /**
     * @param int|string $widgetKey
     * @param array $slots
     * @return void
     */
    public function updateWidgetSlots(int|string $widgetKey, array $slots): void  // -> used by Enlist and EnlistCallbackHandler
    {
        $widgetDescr = $this->enlistWidgets[$widgetKey];
        $widgetDescr['slots'] = $slots;
        $this->updateWidgetDescr($widgetDescr, recKeyToUse: $widgetKey);
    } // updateWidgetSlots


    /**
     * @param int|string $widgetKey
     * @param int $slotInx
     * @return array|false
     */
    public function emptySlot(int|string $widgetKey, int $slotInx): array|false  // -> used by Enlist and EnlistCallbackHandler
    {
        $becameActive = false;
        $slots = $this->enlistWidgets[$widgetKey]['slots'];
        $hasEmptySlots = !($slots[$this->nSlots - 1]['Name']??false);
        $hasFilledReserveSlots = $this->nReserveSlots && ($slots[$this->nSlots]['Name']??false);

        // normal case: either no reserve slots are filled or slot is in reserve, but normal slots are not filled up:
        if ($slotInx >= $this->nSlots || !$hasFilledReserveSlots) {
            unset($slots[$slotInx]);
            $slots[] = [];

        } elseif ($hasEmptySlots) {
            $tmp = array_slice($slots, 0, $this->nSlots);
            unset($tmp[$slotInx]);
            $tmp[] = [];
            array_splice($slots, 0, $this->nSlots, $tmp);

        } else {
            $becameActive = $slots[$this->nSlots];
            unset($slots[$slotInx]);
            $slots[] = [];
        }

        $this->enlistWidgets[$widgetKey]['slots'] = array_values($slots);
        $this->updateWidgetDescr($this->enlistWidgets[$widgetKey], recKeyToUse: $widgetKey);
        return $becameActive;
    } // emptySlot


    /**
     * @param int|string $widgetKey
     * @return array
     */
    public function prepareWidgetDescr(int|string $widgetKey, array $widgetOptions): array
    {
        $widgetDescr = $widgetDescr0 = $this->db->getRec($widgetKey);
        if ($widgetDescr) {
            // make sure slots are initiated:
            if (!isset($widgetDescr['slots'])) {
                $widgetDescr['slots'] = [];
            }
            // make sure option values are updated in case they have changed:
            $widgetDescr = [
                    'nSlots' => $this->nSlots,
                    'nReserveSlots' => $this->nReserveSlots,
                    'nTotalSlots' => $this->nTotalSlots,
                    'title' => $widgetOptions['title'] ?: $this->options['title'],
                    'freezeTime' => $this->options['freezeTime'],
                    'directlyToReserve' => $this->options['directlyToReserve'],
                    'widgetKey' => $widgetKey,
                ] + $widgetDescr;
            if ($this->deadlineExpired) {
                $widgetDescr['deadlineExpired'] = true;
            }
            // update only if anything has changed:
            if ($widgetDescr !== $widgetDescr0) {
                $this->updateWidgetDescr($widgetDescr, recKeyToUse:$widgetKey);
            }
            $this->enlistWidgets[$widgetKey] = $widgetDescr;
        } else {
            $widgetDescr = $widgetOptions + WIDGET_DATA_TEMPLATE;
            if ($this->deadlineExpired) {
                $widgetDescr['deadlineExpired'] = true;
            }
            $this->updateWidgetDescr($widgetDescr, recKeyToUse:$widgetKey);
            $this->enlistWidgets[$widgetKey] = $widgetDescr;
        }
        return $widgetDescr;
    } // prepareWidgetDescr


    /**
     * @param int|string $widgetKey
     * @return array|false
     */
    private function findWidgetDescr(int|string $widgetKey): array|false
    {
        if ($this->enlistWidgets[$widgetKey]??false) {
            return $this->enlistWidgets[$widgetKey];
        }
        foreach ($this->enlistWidgets as $widgetDescr) {
            if (($widgetDescr['_reckey']??false) === $widgetKey) {
                return $widgetDescr;
            }
        }
        return false;
    } // findWidgetDescr


    /**
     * @param int|string $widgetKey
     * @param int $slotInx
     * @param array $newDataRec
     * @param string $context
     * @return mixed
     * @throws \Exception
     */
    public function selectSlot(int|string $widgetKey, int $slotInx, array $newDataRec, string $context): mixed
    {
        $slotInx0           = $slotInx;
        $widgetDescr        = $this->getWidgetDescr($widgetKey);
        $slots              = $this->getEnlistSlots($widgetKey);
        $nSlots             = $widgetDescr['nSlots'];
        $nReserveSlots      = $widgetDescr['nReserveSlots']??0;
        $nTotalSlots        = $nSlots + $nReserveSlots;
        $directlyToReserve  = ($newDataRec['directlyToReserve']??false) && ($widgetDescr['directlyToReserve']??false);
        if (!$directlyToReserve) {
            if ($slotInx > $nTotalSlots) {
                mylog("EnList: fishy data entry: max slots exceeded. $context", 'enlist-log.txt');
                reloadAgent();
            } elseif (($slots[$slotInx]??false) && ($slots[$slotInx]['Name']??false)) {
                mylog("EnList: fishy: slots not empty. $context", 'enlist-log.txt');
                reloadAgent();
            }
        } else {
            $slotInx = $nTotalSlots - $nReserveSlots;
        }
        for ($i = $slotInx; $i < $nTotalSlots; $i++) {
            if (($slots[$i]??false) && ($slots[$i]['Name']??false)) {
                continue;
            }
            return $i;
        }
        if (($newDataRec['directlyToReserve']??false) && !($slots[$slotInx0]['Name']??false)) {
            return $slotInx0;
        }
        mylog("EnList: fishy data entry: max slots exceeded. $context", 'enlist-log.txt');
        reloadAgent();
        return null;
    } // selectSlot


    /**
     * @param array $rec
     * @param bool $flush
     * @param $recKeyToUse
     * @return bool
     */
    public function updateWidgetDescr(array $rec, bool $flush = true, $recKeyToUse = false): bool
    {
        return $this->db->addRec($rec, $flush, $recKeyToUse);
    } // updateWidgetDescr


    /**
     * @return int
     */
    public function nSlots(): int
    {
        return $this->nSlots;
    } // nSlots


    /**
     * @return int
     */
    public function nReserveSlots(): int
    {
        return $this->nReserveSlots;
    } // nReserveSlots


    /**
     * @return int
     */
    public function nTotalSlots(): int
    {
        return $this->nTotalSlots;
    } // nTotalSlots


    /**
     * @return void
     * @throws \Exception
     */
    private function openDb(): void
    {
        $file = $this->options['file'];
        $this->dataFile = $file;
        $tableOptions = $this->options['tableOptions'] ?? [];
        $tableOptions['masterFileRecKeySort'] = ($tableOptions['masterFileRecKeySort']??true);
        $this->db = new DataStore($file, $tableOptions);

        $this->enlistWidgets = $this->db->data();
    } // openDb


    /**
     * @param $options
     * @return void
     */
    private function parseOptions(array $options): void
    {
        $this->nSlots = $options['nSlots'] ?? 0;
        $this->nReserveSlots = $options['nReserveSlots'] ?? 0;
        $this->nTotalSlots = $options['nTotalSlots'] ?? 0;
    }  // parseOptions
} // Enlist