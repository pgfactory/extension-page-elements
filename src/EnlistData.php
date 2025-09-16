<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\DataSet;
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
    protected static $session;
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
     * @param mixed $widgetInx
     * @return void
     */
    public function collapseEmptySlots(mixed $widgetInx, string $widgetTitle): void
    {
        $widgetDescr = $this->enlistWidgets[$widgetInx];
        $widgetSlots = &$widgetDescr['slots'];
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
        if ($toNotify) {
            $this->updateWidgetDescr($widgetDescr, recKeyToUse:$widgetInx);
            $widgetTitle = str_replace("\n", '', $widgetTitle);
            $names = '';
            foreach ($toNotify as $rec) {
                EnlistComm::notifyActivatedReserve($rec, $widgetTitle);
                $names .= '<br>- '.$rec['Name']??'';
            }
            $msg = TransVars::getVariable('pfy-enlist-collapse-executed');
            $msg = str_replace('%names%', $names, $msg);
            reloadAgent(message: $msg);
        }
    } // collapseEmptySlots


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


    /**
     * @param int|string $widgetInx
     * @return array|false
     */
    public function getWidgetDescr(int|string $widgetInx): array|false
    {
        $widgetDescr = $this->findWidgetDescr($widgetInx);
        if (!$widgetDescr) {
            $widgetDescr = $this->prepareWidgetDescr($widgetInx);
        }
        $this->checkAndUpdateWidgetDescr($widgetDescr, $widgetInx);
        return $widgetDescr;
    } // getWidgetDescr


    /**
     * @param int|string $widgetInx
     * @return array|false
     */
    public function getWidgetSlots(int|string $widgetInx): array|false  // -> used by EnlistCallbackHandler
    {
        $widgetDescr = $this->getWidgetDescr($widgetInx);
        return $widgetDescr['slots'];
    } // getWidgetSlots


    /**
     * @param array $widgetDescr
     * @param int|string $widgetInx
     * @return void
     */
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


    /**
     * @param int|string $widgetInx
     * @return array
     */
    public function getEnlistSlots(int|string $widgetInx): array
    {
        if ($widgetDescr = ($this->enlistWidgets[$widgetInx]??false)) {
            return $widgetDescr['slots'];
        } else {
            return [];
        }
    } // getEnlistSlots


    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->dataFile;
    } // getFile


    /**
     * @param int|string $widgetInx
     * @param int $slotInx
     * @param array $newDataRec
     * @param string $context
     * @return mixed
     */
    public function fillSlot(int|string $widgetInx, int $slotInx, array $newDataRec, string $context)  // -> used by EnlistCallbackHandler
    {
        $slotInx = $this->selectSlot($widgetInx, $slotInx, $newDataRec, $context);
        unset($newDataRec['directlyToReserve']);
        unset($newDataRec['widgetInx']);
        unset($newDataRec['widgetTitle']);
        $this->enlistWidgets[$widgetInx]['slots'][$slotInx] = $newDataRec;
        $this->updateWidgetDescr($this->enlistWidgets[$widgetInx], recKeyToUse: $widgetInx);
        return $slotInx;
    } // fillSlot


    /**
     * @param int|string $widgetInx
     * @param array $slots
     * @return void
     */
    public function updateWidgetSlots(int|string $widgetInx, array $slots): void  // -> used by Enlist and EnlistCallbackHandler
    {
        $widgetDescr = $this->enlistWidgets[$widgetInx];
        $widgetDescr['slots'] = $slots;
        $this->updateWidgetDescr($widgetDescr, recKeyToUse: $widgetInx);
    } // updateWidgetSlots


    /**
     * @param int|string $widgetInx
     * @param int $slotInx
     * @return array|false
     */
    public function emptySlot(int|string $widgetInx, int $slotInx): array|false  // -> used by Enlist and EnlistCallbackHandler
    {
        $becameActive = false;
        $slots = $this->enlistWidgets[$widgetInx]['slots'];
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

        $this->enlistWidgets[$widgetInx]['slots'] = array_values($slots);

        $this->updateWidgetDescr($this->enlistWidgets[$widgetInx], recKeyToUse: $widgetInx);
        return $becameActive;
    } // emptySlot


    /**
     * @param int|string $widgetInx
     * @return array
     */
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


    /**
     * @param int|string $widgetInx
     * @return array|false
     */
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


    /**
     * @param int|string $widgetInx
     * @param int $slotInx
     * @param array $newDataRec
     * @param string $context
     * @return mixed
     * @throws \Exception
     */
    public function selectSlot(int|string $widgetInx, int $slotInx, array $newDataRec, string $context): mixed
    {
        $slotInx0 = $slotInx;
        $widgetDescr = $this->getWidgetDescr($widgetInx);
        $slots = $this->getEnlistSlots($widgetInx);
        $nTotalSlots = $this->nTotalSlots;
        $directlyToReserve = ($newDataRec['directlyToReserve']??false) && $this->options['directlyToReserve'];
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


    /**
     * @param array $rec
     * @param bool $flush
     * @param $recKeyToUse
     * @return object|string
     */
    public function updateWidgetDescr(array $rec, bool $flush = true, $recKeyToUse = false): object|string
    {
        return $this->db->addRec($rec, $flush, $recKeyToUse);
    } // updateWidgetDescr


    /**
     * @param $options
     * @return void
     */
    private function parseOptions($options): void
    {
        $this->nSlots = $options['nSlots'] ?? 0;
        $this->nReserveSlots = $options['nReserveSlots'] ?? 0;
        $this->nTotalSlots = $options['nTotalSlots'] ?? 0;
    }  // parseOptions
} // Enlist