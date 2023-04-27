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

class Data2DSet extends DataSet
{
    private array $elementSubKeys = [];
    public  $elementFlattenedKeys;

    public function __construct(string $file, array $options = [])
    {
        $this->elementFlattenedKeys = [];
        parent::__construct($file, $options);
        if (is_array($options['elementSubKeys'] ?? false)) {
            foreach ($options['elementSubKeys'] as $k => $v) {
                $this->elementSubKeys[strtolower($k)] = array_keys($v);
            }
        }
    } // __construct



    public function getNormalized2D_Data($headerElems = true): array
    {
        $data = $this->data(false);
        if (!$data) {
            return []; // nothing to do
        }
        $data2D = [];
        $elementKeys = [];
        $rec0 = reset($data);
        foreach ($rec0 as $key => $value) {
            $elementKeys[] = $key;
            if (is_array($value)) {
                $subKeys = $this->elementSubKeys[$key]??[];
                $elementKeys = array_merge($elementKeys, $subKeys);
            }
        }

        if ($headerElems) {
            if ($headerElems === true) {
                $headerElems = $elementKeys;
            }
            $data2D['_hdr'] = array_combine($elementKeys, $headerElems);
        }
        foreach ($data as $recKey => $rec) {
            $newRec = [];
            foreach ($rec as $key => $value) {
                if (($key[0] === '_')) {
                    continue;
                }
                if (is_array($value)) {
                    $newRec[] = $value[ARRAY_SUMMARY_NAME];
                    foreach ($value as $k => $v) {
                        if ($k === ARRAY_SUMMARY_NAME) {
                            continue;
                        }
                        $newRec[] = $v?'1':'0';
                    }
                } else {
                    $newRec[] = $value;
                }
            }
            $data2D[$recKey] = array_combine($elementKeys, $newRec);;
        }


        $this->nCols = sizeof($elementKeys);
        $this->nRows = sizeof($data2D)-1;
        if ($headerElems) {
            $this->elementFlattenedKeys = array_combine($elementKeys, $headerElems);
        } else {
            $this->elementFlattenedKeys = $elementKeys;
        }
        return $data2D;
    } // get2DNormalizedData

} // Data2DSet