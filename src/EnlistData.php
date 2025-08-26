<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\DataSet;
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
    protected static $session;
    private array $enlistWidgets = [];


    public function __construct(array $options)
    {
        $this->options = $options;
        $this->parseOptions($options);
        $this->openDb();
    } // __construct


    public function nSlots(): int
    {
        return $this->nSlots;
    } // nSlots


    public function nReserveSlots(): int
    {
        return $this->nReserveSlots;
    } // nReserveSlots


    public function nTotalSlots(): int
    {
        return $this->nTotalSlots;
    } // nTotalSlots


    public function consolidate(): void
    {

    } // consolidate


    /**
     * @return void
     * @throws \Exception
     */
    private function openDb(): void
    {
        $file = $this->options['file'];
        $this->dataFile = $file;
        $this->db = new DataSet($file, [
            'masterFileRecKeyType' => 'origKey',
            'masterFileRecKeySort' => true,
            'masterFileRecKeySortOnElement' => '_origRecKey',
            'recKeyType' => '_reckey',
        ]);

        $this->enlistWidgets = $this->db->data();
    } // openDb


    public function getWidgetDescr(int|string $widgetInx): array|false
    {
        $widgetDescr = $this->findWidgetDescr($widgetInx);
        if (!$widgetDescr) {
            $widgetDescr = $this->prepareWidgetDescr($widgetInx);
        }
        $this->checkAndUpdateWidgetDescr($widgetDescr, $widgetInx);
        return $widgetDescr;
    } // getWidgetDescr


    public function getWidgetSlots(int|string $widgetInx,): array|false  // -> used by EnlistCallbackHandler
    {
        $widgetDescr = $this->getWidgetDescr($widgetInx);
        return $widgetDescr['slots'];
    } // getWidgetSlots


    private function checkAndUpdateWidgetDescr(array $widgetDescr, int|string $widgetInx)
    {
        $needsUpdate = false;
        foreach (['nSlots', 'nReserveSlots','title', 'freezeTime', 'deadline','directlyToReserve'] as $key) {
            if ($this->options[$key] && (($widgetDescr[$key]??false) !== $this->options[$key])) {
                $needsUpdate = true;
                break;
            }
        }
        if ($needsUpdate) {
            $slots = $widgetDescr['slots'];
            $widgetDescr = [];
            $widgetDescr['slots'] = $slots;
            $widgetDescr['nSlots'] = $this->options['nSlots'];
            $widgetDescr['nReserveSlots'] = $this->options['nReserveSlots'];
            $widgetDescr['title'] = $this->options['title'];
            if ($this->options['freezeTime']) {
                $widgetDescr['freezeTime'] = $this->options['freezeTime'];
            }
            if ($this->options['deadline']) {
                $widgetDescr['deadline'] = $this->options['deadline'];
            }
            if ($this->options['directlyToReserve']) {
                $widgetDescr['directlyToReserve'] = $this->options['directlyToReserve'];
            }
            $this->updateWidgetDescr($widgetDescr, recKeyToUse:$widgetInx);
        }
    } // checkAndUpdateWidgetDescr


    public function getEnlistSlots(int|string $widgetInx): array
    {
        if ($widgetDescr = ($this->enlistWidgets[$widgetInx]??false)) {
            return $widgetDescr['slots'];
        } else {
            return [];
        }
    } // getEnlistSlots


    public function getFile()
    {
        return $this->dataFile;
    } // getFile


    public function fillSlot(int|string $widgetInx, int $slotInx, array $newDataRec, string $context)  // -> used by EnlistCallbackHandler
    {
        $slotInx = $this->selectSlot($widgetInx, $slotInx, $newDataRec, $context);
        unset($newDataRec['directlyToReserve']);
        unset($newDataRec['widgetInx']);
        $this->enlistWidgets[$widgetInx]['slots'][$slotInx] = $newDataRec;
        $this->updateWidgetDescr($this->enlistWidgets[$widgetInx], recKeyToUse: $widgetInx);
        return $slotInx;
    } // fillSlot


    public function updateWidgetSlots(int|string $widgetInx, array $slots): void  // -> used by Enlist and EnlistCallbackHandler
    {
        $widgetDescr = $this->enlistWidgets[$widgetInx];
        $widgetDescr['slots'] = $slots;
        $this->updateWidgetDescr($widgetDescr, recKeyToUse: $widgetInx);
    } // updateWidgetSlots


    public function emptySlot(int|string $widgetInx, int $slotInx): array|false  // -> used by Enlist and EnlistCallbackHandler
    {
        $becameActive = false;
        $hasEmptySlots = $hasFilledReserveSlots = false;
        $slots = $this->enlistWidgets[$widgetInx]['slots'];
        for ($i=0; $i<$this->nSlots; $i++) {
            if (!($slots[$i]['Name']??false)) {
                $hasEmptySlots = true;
                break;
            }
        }
        if ($slotInx < $this->nSlots) {
            for ($i = $this->nSlots; $i < $this->nTotalSlots; $i++) {
                if (($slots[$i]['Name'] ?? false)) {
                    $hasFilledReserveSlots = true;
                    break;
                }
            }
        }

        // normal case: either no reserve slots are filled or slot is in reserve, but normal slots are not filled up:
        if (!$hasFilledReserveSlots) {
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

        $this->enlistWidgets[$widgetInx]['slots'] = array_values($slots);

        $this->updateWidgetDescr($this->enlistWidgets[$widgetInx], recKeyToUse: $widgetInx);
        return $becameActive;
    } // emptySlot


    private function prepareWidgetDescr(int|string $widgetInx): array
    {
        $widgetDescr = $this->db->getRecData($widgetInx);
        if ($widgetDescr) {
            $this->enlistWidgets[$widgetInx] = $widgetDescr;
        } else {
            $widgetDescr = WIDGET_DATA_TEMPLATE;
            $nTotalSlots = $this->options['nSlots'] + $this->options['nReserveSlots'];
            $widgetDescr['slots'] = array_fill(0, $nTotalSlots, []);
            $widgetDescr['nSlots'] = $this->options['nSlots'];
            $widgetDescr['nReserveSlots'] = $this->options['nReserveSlots'];
            $widgetDescr['title'] = $this->options['title'];
            //ToDo: add custom fields
            if ($this->options['freezeTime']) {
                $widgetDescr['freezeTime'] = $this->options['freezeTime'];
            }
            if ($this->options['directlyToReserve']) {
                $widgetDescr['directlyToReserve'] = $this->options['directlyToReserve'];
            }
            if ($this->deadlineExpired) {
                $widgetDescr['deadlineExpired'] = true;
            }
            $this->updateWidgetDescr($widgetDescr, recKeyToUse:$widgetInx);
            $this->enlistWidgets[$widgetInx] = $widgetDescr;
        }
        return $widgetDescr;
    } // prepareWidgetDescr


    private function findWidgetDescr(int|string $widgetInx): array|false
    {
        if ($this->enlistWidgets[$widgetInx]??false) {
            return $this->enlistWidgets[$widgetInx];
        }
        foreach ($this->enlistWidgets as $widgetDescr) {
            if (($widgetDescr['_reckey']??false) === $widgetInx) {
                return $widgetDescr;
            }
        }
        return false;
    } // findWidgetDescr


    public function selectSlot(int|string $widgetInx, int $slotInx, array $newDataRec, string $context): mixed
    {
        $slotInx0 = $slotInx;
        $widgetDescr = $this->getWidgetDescr($widgetInx);
        $slots = $this->getEnlistSlots($widgetInx);
        $nTotalSlots = $this->nTotalSlots;
        $directlyToReserve = $newDataRec['directlyToReserve'] && $this->options['directlyToReserve'];
        if (!$directlyToReserve) {
            if ($slotInx > $nTotalSlots) {
                mylog("EnList: fishy data entry: max slots exeeded. $context", 'enlist-log.txt');
                reloadAgent();
            } elseif (($widgetDescr[$slotInx]??false) && ($widgetDescr[$slotInx]['Name']??false)) {
                mylog("EnList: fishy: slots not empty. $context", 'enlist-log.txt');
                reloadAgent();
            }
        } else {
            $slotInx = $nTotalSlots - $widgetDescr['nReserveSlots'];
        }
        for ($i = $slotInx; $i < $nTotalSlots; $i++) {
            if (($slots[$i]??false) && ($slots[$i]['Name']??false)) {
                continue;
            }
            return $i;
        }
        if ($newDataRec['directlyToReserve'] && !($slots[$i]['Name']??false)) {
            return $slotInx0; //
        }
        mylog("EnList: fishy data entry: max slots exeeded. $context", 'enlist-log.txt');
        reloadAgent();
        return null;
    } // selectSlot



    public function updateWidgetDescr(array $rec, bool $flush = true, $recKeyToUse = false): object|string
    {
        return $this->db->addRec($rec, $flush, $recKeyToUse);
    } // updateWidgetDescr


    private function parseOptions($options): void
    {
        $this->nSlots = $options['nSlots'] ?? 0;
        $this->nReserveSlots = $options['nReserveSlots'] ?? 0;
        $this->nTotalSlots = $options['nTotalSlots'] ?? 0;
    }  // parseOptions
} // Enlist