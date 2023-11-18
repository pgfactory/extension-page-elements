<?php

/*
 * Data2DSet
 * Convention for data-elements containing arrays:
 *      elemKey => [
 *          '_'  => 'summary of options',
 *          'opt1' => bool,
 *          'opt2' => bool,
 *          ...
 *      ]
 */

namespace PgFactory\PageFactory;

if (!defined('ARRAY_SUMMARY_NAME')) {
    define('ARRAY_SUMMARY_NAME', '_');
}

const UNKNOWN = '?';

class Data2DSet extends DataSet
{
    protected array $columnKeys = [];
    private string $unknownValue = UNKNOWN;
    private bool $markLocked = false;

    /**
     * @param string $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [])
    {
        $this->markLocked = $options['markLocked'] ?? false;
        parent::__construct($file, $options);

        if ($unknown = $options['unknownValue']??false) {
            $this->unknownValue = $unknown;
        }
    } // __construct


    /**
     * @param $headerElems
     * @return array
     * @throws \Exception
     */
    public function getNormalized2D_Data($headerElems = true): array
    {
        $includeSystemElements = $this->options['includeSystemElements']??false;
        $data = $this->data($includeSystemElements);
        list($headerElems, $elementKeys) = $this->prepare($headerElems, $includeSystemElements);

        $data2D = self::normalizeData($data, $headerElems, $elementKeys);

        $this->nRows = sizeof($data2D)-1;
        $this->columnKeys = $headerElems;
        $this->nCols = sizeof($elementKeys);

        if ($this->options['obfuscateRows']??false) {
            $data2D = $this->obfuscateRows($data2D);
        }
        if ($this->options['minRows']??false) {
            $data2D = $this->addRows($data2D, $elementKeys);
        }

        return $data2D;
    } // get2DNormalizedData


    public static function normalizeData($data, $headerElems, $elementKeys): array
    {
        $data2D = [];
        $data2D['_hrd'] = array_combine($elementKeys, $elementKeys);
        foreach ($data as $recKey => $rec) {
            $newRec = [];
            foreach ($headerElems as $key => $value) {
                if (isset($rec[$key])) {
                    if (is_bool($rec[$key])) {
                        $newRec[$key] = $rec[$key]? '1':'0';
                    } elseif (is_scalar($rec[$key])) {
                        $newRec[$key] = $rec[$key];
                    } elseif (is_array($rec[$key]) && isset($rec[$key]['_'])) {
                        $newRec[$key] = $rec[$key]['_'];
                    } elseif (is_array($rec[$key])) {
                        $newRec[$key] = json_encode($rec[$key]);
                    }
                } else {
                    // no elem found, check for indexed element of type 'a.b':
                    if (str_contains($key, '.')) {
                        $indexes = explode('.', $key);
                        $v = $rec;
                        foreach ($indexes as $index) {
                            if (isset($v[$index])) {
                                $v = $v[$index];
                            } elseif (is_scalar($v)) {
                                $v = ($v === $value);
                            } else {
                                $newRec[$value] = '?'; //$this->unknownValue;
                                continue 2;
                            }
                        }
                        $newRec[$index] = is_bool($v) ? ($v?'1':'0'): $v;

                    // no matching data found -> mark as unknown
                    } else {
                        $newRec[$key] = '?'; //$this->unknownValue;
                    }
                }
            }
            $data2D[$recKey] = self::arrayCombine($elementKeys, $newRec);
        }
        return $data2D;
    } // normalizeData


    /**
     * @param array $headerElems
     * @param bool $includeSystemElements
     * @return array
     * @throws \Exception
     */
    private function prepare(array|bool $headerElems, bool $includeSystemElements): array
    {
        if (!$this->data) {
            return [[[]], [], []];
        }

        $data2D = [];
        $elementKeys = [];
        if ($headerElems) {
            if ($headerElems === true) {
                // derive headerElems from first data record:
                $rec0 = reset($this->data);
                $dataRec0 = $rec0->recData;
                $elementKeys = array_keys($dataRec0);
                if ($includeSystemElements) {
                    if (!in_array(DATAREC_TIMESTAMP, $elementKeys)) {
                        $elementKeys[] = DATAREC_TIMESTAMP;
                    }
                    if (!in_array('_reckey', $elementKeys)) {
                        $elementKeys[] = '_reckey';
                    }
                } else {
                    $elementKeys = array_filter($elementKeys, function ($e) {
                        return (($e[0]??'') !== '_');
                    });
                    $elementKeys = array_values($elementKeys);
                }
                $headerElems = array_combine($elementKeys, $elementKeys);

            } else {
                $elementKeys = array_values($headerElems);
                if ($includeSystemElements) {
                    if (!in_array(DATAREC_TIMESTAMP, $elementKeys)) {
                        $elementKeys[] = DATAREC_TIMESTAMP;
                    }
                    if (!in_array('_reckey', $elementKeys)) {
                        $elementKeys[] = '_reckey';
                    }
                } else {
                    $headerElems = array_filter($headerElems, function ($e) {
                        return ($e[0] !== '_');
                    });
                    $elementKeys = array_filter($elementKeys, function ($e) {
                        return ($e[0] !== '_');
                    });
                    $elementKeys = array_values($elementKeys);
                }
            }
            if ($this->markLocked) {
                $elementKeys[] = '_locked';
            }
        } else {
            $headerElems = [];
        }
        return [$headerElems, $elementKeys];
    } // prepare


    /**
     * @param array $keys
     * @param array $values
     * @return array
     */
    private static function arrayCombine(array $keys, array $values): array
    {
        $nKeys = sizeof($keys);
        $nValues = sizeof($values);
        if ($nKeys < $nValues) {
            for ($i=$nKeys; $i<$nValues; $i++) {
                $k = str_replace('-', '_', ($values[$i]??''));
                $k = preg_replace('/\W/', '', $k);
                $keys[$i] = translateToIdentifier($k);
            }
        } elseif ($nKeys > $nValues) {
            for ($i=$nValues; $i<$nKeys; $i++) {
                $values[$i] = '';
                // originally: $values[$i] = str_replace('_', ' ', ($keys[$i]??''));
            }
        }
        return array_combine($keys, $values);
    } // arrayCombine


    private function addRows(array $data2D, array $elementKeys): array
    {
        if (($minRows = $this->options['minRows']) && ($minRows > $this->nRows)) {
            $emptyRec = self::arrayCombine($elementKeys, array_fill(0, $this->nCols, ''));
            $emptyRecs = array_fill(0, ($minRows - $this->nRows), $emptyRec);
            $data2D = array_merge_recursive($data2D, $emptyRecs);
            $this->nRows = sizeof($data2D)-1;
        }
        return $data2D;
    } // addRows


    private function obfuscateRows($data2D)
    {
        $rows = $this->options['obfuscateRows'];
        foreach ($data2D as $row => $rec) {
            if ($row === '_hdr') {
                continue;
            }
            foreach ($rec as $key => $value) {
                if (in_array($key, $rows)) {
                    $data2D[$row][$key] = '*****';
                }
            }
        }
        return $data2D;
    }
} // Data2DSet