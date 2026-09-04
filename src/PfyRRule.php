<?php

namespace PgFactory\PageFactoryElements;
use PgFactory\PageFactory\DataStore;
use RRule\RRule;
use function PgFactory\PageFactory\createHash;

class PfyRRule
{
    const ALLOWED_FIELD_NAMES = ['DTSTART','DTEND','FREQ','UNTIL','COUNT','INTERVAL','WKST','BYDAY','BYMONTH'];
    const MAX_COUNT = 100;

    /**
     * @param string $file
     * @param array $rRules
     * @param array $dataRec
     * @param string $recKey
     * @return void
     * @throws \Exception
     */
    public static function save(string $file, array $rRules, array $dataRec, string $recKey = ''): void
    {
        $db = new DataStore($file);
        $events = self::compile($rRules, $dataRec, $recKey);
        foreach ($events as $recKey => $event) {
            $db->addRec($event, flush:false, recKeyToUse: $recKey);
        }
        $db->flush();
    } // save


    /**
     * @param array $rRules
     * @param array $dataRec
     * @param string $recKey
     * @return array
     * @throws \Exception
     */
    public static function compile(array $rRules, array $dataRec, string $recKey = ''): array
    {
        list($rRuleElems, $maxCount) = self::sanitizeRrules($rRules);
        return self::_compile($rRuleElems, $dataRec, $recKey, $maxCount);
    } // compile


    /**
     * @param array $rRuleElems
     * @param array $dataRec
     * @param string $recKey
     * @param int $maxCount
     * @return array
     * @throws \Exception
     */
    private static function _compile(array $rRuleElems, array $dataRec, string $recKey = '', int $maxCount = self::MAX_COUNT): array
    {
        $events = [];
        $from = $dataRec['start'] ?: '';
        $till = $dataRec['end'] ?: '';
        if (!$from || !$till) {
            throw new \Exception("RRule: start or end missing in datarec");
        }
        $startTime = (!$dataRec['allday']) ? 'T'.substr($from, 11, 5) : '';
        $duration = strtotime($till) - strtotime($from);

        if ($from) {
            $rRuleElems['DTSTART'] = Events::convertDatetime($from);
        }

        if (!$recKey) {
            $recKey = createHash();
        }

        // compile rrule:
        $rrule = new RRule($rRuleElems);
        foreach ($rrule as $i => $occurrence ) {
            if ($i >= $maxCount) {
                break;
            }
            $fromStr = $occurrence->format('Y-m-d').$startTime;
            $from = strtotime($fromStr);
            $till = $from + $duration;
            if ($dataRec['allday']) {
                $tillStr = date('Y-m-d', $till);
            } else {
                $tillStr = date('Y-m-d\TH:i', $till);
            }
            $event = $dataRec;
            $event['start'] = $fromStr;
            $event['end'] = $tillStr;
            $events[$recKey] = $event;
            $recKey = createHash();
        }
        return $events;
    } // _compile


    /**
     * @param array $dataRec
     * @return array
     */
    private static function sanitizeRrules(array $dataRec): array
    {
        $rRuleElems = [];
        if ($dataRec['_rruleFormula']??false) {
            foreach (explode(';', $dataRec['_rruleFormula']) as $rec) {
                list($k,$v) = explode('=', $rec);
                $rRuleElems[$k] = $v;
            }
            if (!($rRuleElems['COUNT']??false) || intval($rRuleElems['COUNT']) > self::MAX_COUNT) {
                $rRuleElems['COUNT'] = self::MAX_COUNT;
            }

        } else {
            foreach ($dataRec as $key => $value) {
                $key = strtoupper($key);
                if (str_starts_with($key, '_')) {
                    $key = substr($key, 1);
                }
                if (!$value || $key === 'RRULE') {
                        continue;
                }
                $key = ($key === 'BYWEEKDAY') ? 'BYDAY' : $key; // allow as synonym
                if (in_array($key, self::ALLOWED_FIELD_NAMES)) {
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }
                    $rRuleElems[$key] = strtoupper($value);
                }
            }
        }

        $maxCount = self::MAX_COUNT;
        if (($rRuleElems['COUNT']??false) && ($rRuleElems['COUNT'] > 1)) {
            $maxCount = $rRuleElems['COUNT'];
        }
        // enforce rule that UNTIL and COUNT are not allowed together -> drop COUNT
        if (isset($rRuleElems['UNTIL']) && isset($rRuleElems['COUNT'])) {
            unset($rRuleElems['COUNT']);
        }
        return [$rRuleElems, $maxCount];
    } // sanitizeRrules
} // RRule
