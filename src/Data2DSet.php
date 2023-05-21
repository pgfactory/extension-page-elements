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

namespace Usility\PageFactory;

if (!defined('ARRAY_SUMMARY_NAME')) {
    define('ARRAY_SUMMARY_NAME', '_');
}

const UNKNOWN = '?';

class Data2DSet extends DataSet
{
    protected array $columnKeys = [];
    private string $unknownValue = UNKNOWN;

    /**
     * @param string $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [])
    {
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
        if (!$data) {
            return []; // nothing to do
        }
        list($data2D, $headerElems, $elementKeys) = $this->prepare($headerElems, $includeSystemElements);

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
                                $newRec[$value] = $this->unknownValue;
                                continue 2;
                            }
                        }
                        $newRec[$index] = is_bool($v) ? ($v?'1':'0'): $v;

                    // no matching data found -> mark as unknown
                    } else {
                        $newRec[$key] = $this->unknownValue;
                    }
                }
            }
            $data2D[$recKey] = $this->arrayCombine($elementKeys, $newRec);
        }

        $this->nRows = sizeof($data2D)-1;
        $this->columnKeys = $headerElems;
        $this->nCols = sizeof($headerElems);
        return $data2D;
    } // get2DNormalizedData


    /**
     * @param array $headerElems
     * @param bool $includeSystemElements
     * @return array
     * @throws \Exception
     */
    private function prepare(array|bool $headerElems, bool $includeSystemElements): array
    {
        $data2D = [];
        if ($headerElems) {
            if ($headerElems === true) {
                // derive headerElems from first data record:
                $rec0 = reset($this->data);
                $dataRec0 = $rec0->recData;
                $elementKeys = array_keys($dataRec0);
                if ($includeSystemElements) {
                    $elementKeys[] = DATAREC_TIMESTAMP;
                    $elementKeys[] = '_reckey';
                } else {
                    $elementKeys = array_filter($elementKeys, function ($e) {
                        return ($e[0] !== '_');
                    });
                    $elementKeys = array_values($elementKeys);
                }
                $headerElems = array_combine($elementKeys, $elementKeys);

            } else {
                $elementKeys = array_values($headerElems);
                if ($includeSystemElements) {
                    $elementKeys[] = DATAREC_TIMESTAMP;
                    $elementKeys[] = '_reckey';
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
            $data2D['_hdr'] = $this->arrayCombine($elementKeys, $elementKeys);
        }
        return [$data2D, $headerElems, $elementKeys];
    } // prepare


    /**
     * @param array $keys
     * @param array $values
     * @return array
     */
    private function arrayCombine(array $keys, array $values): array
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
                $values[$i] = str_replace('_', ' ', ($keys[$i]??''));
            }
        }
        return array_combine($keys, $values);
    } // arrayCombine
} // Data2DSet