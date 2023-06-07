<?php

namespace Usility\PageFactoryElements;

require_once __DIR__ . '/Data2DSet.php';

use Usility\PageFactory\PageFactory as PageFactory;
use Usility\PageFactory\Data2DSet as Data2DSet;
use Usility\PageFactory\TransVars;
use function \Usility\PageFactory\base_name;
use function \Usility\PageFactory\explodeTrim;
use function \Usility\PageFactory\translateToIdentifier;
use function \Usility\PageFactory\array_splice_assoc;
use function \Usility\PageFactory\renderIcon;
use function \Usility\PageFactory\fileExt;
use function \Usility\PageFactory\reloadAgent;


const TABLE_SUM_SYMBOL = '%sum';
const TABLE_COUNT_SYMBOL = '%count';

if (!function_exists('array_is_list')) {
    function array_is_list($array) {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }
}


class DataTable extends Data2DSet
{
    private $tableData;
    private $tableHeaders;
    private $tableClass;
    private $tableWrapperClass;
    private $dataReference;
    private $tableButtons;
    private bool $tableButtonDelete = false;
    private bool $tableButtonDownload = false;
    private bool $tableButtonEdit = false;
    private bool $tableButtonNew = false;
    private int $inx;
    private string $tableId;
    private $footers;
    private string $caption;
    private string $captionAbove;
    private string $interactive;
    private string $showRowNumbers;
    private string $showRowSelectors;
    private string $sort;
    private $minRows;
    private string $export;
    private bool $includeSystemElements;
    private $elementLabels;
    private $editRecs;
    protected $markLocked;

    /**
     * @param string $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [])
    {
        parent::__construct($file, $options);
        if (isset($_GET['delete'])) {
            if ($_POST['pfy-reckey']??false) {
                $this->handleTableRequests();
            }
        }

        if ($options['inx']??false) {
            $this->inx = $GLOBALS['tableInx'] = $options['inx'];
        } elseif (isset($GLOBALS['tableInx'])) {
            $GLOBALS['tableInx']++;
            $this->inx = $GLOBALS['tableInx'];
        } else {
            $this->inx = $GLOBALS['tableInx'] = 1;
        }
        $this->tableId = isset($options['tableId']) && $options['tableId'] ? $options['tableId'] : "pfy-table-$this->inx";
        $this->tableClass = isset($options['tableClass']) && $options['tableClass'] ? $options['tableClass'] : 'pfy-table';
        $this->tableWrapperClass = isset($options['tableWrapperClass']) && $options['tableWrapperClass'] ? $options['tableWrapperClass'] : 'pfy-table-wrapper';
        $this->dataReference = isset($options['dataReference']) && $options['dataReference'] ? $options['dataReference'] : '';
        $this->footers = isset($options['footers']) && $options['footers'] ? $options['footers'] :
                        (isset($options['footer']) && $options['footer'] ? $options['footer'] : false);
        $this->caption = isset($options['caption']) && $options['caption'] ? $options['caption'] : false;
        $captionPosition = isset($options['captionPosition']) && $options['captionPosition'] ? $options['captionPosition'] : 'b';
        $this->captionAbove = $captionPosition[0] === 'a';
        $this->interactive = $options['interactive'] ?? false;
        $this->tableButtons = $options['tableButtons'] ?? false;
        $this->downloadFilename = isset($options['downloadFilename']) && $options['downloadFilename'] ? $options['downloadFilename'] : base_name($file, false);
        $this->showRowNumbers = $options['showRowNumbers'] ?? false;
        $this->showRowSelectors = isset($options['showRowSelectors']) && $options['showRowSelectors'] ? $options['showRowSelectors'] : false;
        $this->sort = $options['sort'] ?? false;
        $this->minRows = $options['minRows'] ?? false;
        $this->export = $options['export'] ?? false;
        $this->editRecs = $options['editRecs'] ?? false;
        $this->includeSystemElements = $options['includeSystemElements'] ?? false;
        $this->tableHeaders = $options['tableHeaders'] ?? ($options['headers'] ?? false);
        $this->markLocked = $options['markLocked'] ?? false;


        if ($this->tableHeaders && !is_array($this->tableHeaders)) {
            $this->parseArrayArg('tableHeaders');
        }
        if ($this->includeSystemElements) {
            $this->tableHeaders['_timestamp'] = '_timestamp';
            $this->tableHeaders['_reckey'] = '_reckey';
        }
        if ($this->footers && !is_array($this->footers)) {
            $this->parseArrayArg('footers');
        }

        if ($this->tableButtons === true) {
            $this->tableButtonDelete = true;
            $this->tableButtonDownload = true;
            $this->dataReference = true;

        } elseif ($this->tableButtons) {
            $this->tableButtonDelete = (strpos($this->tableButtons, 'delete') !== false);
            $this->tableButtonDownload = (strpos($this->tableButtons, 'download') !== false);
            $this->tableButtonEdit = (strpos($this->tableButtons, 'edit') !== false);
            $this->tableButtonNew = (strpos($this->tableButtons, 'new') !== false);
            $this->dataReference = true;
        }

        if ($this->editRecs === 'popup') {
            $this->tableClass .= ' pfy-table-edit-popup';
        }

        if ($this->tableButtonDelete) {
            $this->showRowSelectors = true;
        }

        if ($this->sort) {
            $this->sort($this->sort);
        }

        $this->prepareTableData();
    } // __construct


    /**
     * @return void
     * @throws \Exception
     */
    private function prepareTableData(): void
    {
        $this->tableData = $this->getNormalized2D_Data($this->tableHeaders);
        PageFactory::$pg->addAssets('TABLES');
    } // prepareTableData



    /**
     * Renders the HTML table
     * @return string
     */
    public function render(): string
    {
        if (!$this->tableData || sizeof($this->tableData['_hdr']??[]) === 0) {
            return '<div class="pfy-table-wrapper">{{ pfy-no-data-available }}</div>'; // done if no data available
        }

        // inject service rows: select(delete), row-numbers, edit-buttons
        $this->prependServiceRows();

        if ($this->interactive) {
            $this->activateInteractiveTable();
        }

        $out = "\n<div class='$this->tableWrapperClass'>\n";
        $out .= $this->renderTableButtons();


        // render table header tags:
        $out .= $this->renderTableHead();

        // render data cells:
        $out .= $this->renderTableBody();

        // render table footer:
        $out .= $this->renderTableFooter();

        // render table end tags:
        $out .= "</table>\n";
        if ($this->tableButtons) {
            $out .= "  </form>\n";
        }
        $out .= "</div> <!-- table-wrapper $this->tableWrapperClass -->\n\n";
        return $out;
    } // render





    /**
     * Injects rows into data and header for delete,edit,row-numbers.
     * @return void
     */
    private function prependServiceRows(): void
    {
        // Option edit records:
        if ($this->tableButtonEdit !== false) {
            $cell = "<button class='pfy-button pfy-button-lean pfy-table-edit-button' type='button'>✎</button>";
            $hdrCell = TransVars::getVariable('pfy-row-edit-header');
            if ($hdrCell) {
                $this->injectColumn($cell, $hdrCell);
            } else {
                $this->injectColumn($cell, '{{ pfy-row-edit-header }}');
            }
        }

        // Option row numbers:
        if ($this->showRowNumbers !== false) {
            $this->injectColumn('%row-numbers', '{{^ pfy-row-number-header }}');
        }

        // Option row selectors:
        if ($this->showRowSelectors) {
            $this->injectColumn('%row-selectors');
        }
    } // prependServiceRows
    /**
     * Injects a new column of data into the array.
     * Examples:
     *     injectColumn('%row-numbers', '#')
     *     injectColumn('%row-selectors')
     *     injectColumn('const', 'hdr const', -1)
     *     injectColumn(col: 3)
     * @param int $col          target column
     * @param mixed $newElement new element (as comma-separated-list), default is checkbox
     * @return array
     */
    private function injectColumn(string $newElement = '', mixed $headElement = '', int $col = 0): void
    {
        $data = &$this->tableData;
        $newCol = [];
        $this->nRows = sizeof($this->tableData);
        $fillWith = '';
        // negative col -> count from right, -1 == last or append
        if ($col < 0) {
            $col = $this->nCols + $col + 1;
        }
        $newElemName = $headElement ?: "col-$col";

        // case row-selectors:
        if ($newElement === '%row-selectors') {
            $fillWith = '<input type="checkbox"%nameAttr>';
            $newElemName = 'row-selector';

        // case row-numbers:
        } elseif ($newElement === '%row-numbers') {
            $newElemName = 'row-number';
            $newCol = range(0, $this->nRows - 1);

        // case other content:
        } elseif (is_string($newElement)) {
            if (str_contains($newElement, ',')) {
                $newCol = explodeTrim(',', ",$newElement");
            } else {
                $fillWith = $newElement;
                $newCol = [$headElement];
            }
        }
        $newCol[0] = $headElement ?: $fillWith;
        $newCol = array_pad($newCol, $this->nRows, $fillWith);
        $i = 0;

        // fix $this->elementLabels accordingly:
        $name = translateToIdentifier($headElement, removeNonAlpha: true);
        $name = rtrim($name, '_');
        if (is_array($this->tableHeaders)) {
            array_splice_assoc($this->tableHeaders, $col, $col, [$name => $headElement]);
        }

        foreach ($data as $key => $rec) {
            $newElem = str_replace('%nameAttr'," name='pfy-reckey[]' value='$key'", $newCol[$i]);
            $newElem = [$newElemName => $newElem];
            array_splice_assoc($rec, $col, 0, $newElem);
            $data[$key] = $rec;
            $i++;
        }
        $this->nCols++;
    } // injectColumn


    /**
     * Renders table wrapper, <table> and <thead> section
     * @return string
     */
    private function renderTableHead(): string
    {
        $data = &$this->tableData;
        $out = "<table id='$this->tableId' class='$this->tableClass'>\n";

        // caption:
        if ($this->caption) {
            $style = $this->captionAbove? '': ' style="caption-side: bottom;"'; // use style to push caption below table
            $caption = str_replace('%#', $this->inx, $this->caption);
            $out .= "  <caption$style>$caption</caption>\n";
        }

        $out .= "  <thead>\n    <tr class='pfy-table-header pfy-row-0'>\n";
        $headerRow = array_shift($data);
        $this->elementLabels = $headerRow;
        $i = 0;
        foreach ($headerRow as $c => $elem) {
            if ($c === '_locked') {
                continue;
            }
            $i++;
            if (!preg_match('/^\{\{.*}}$/', $elem)) {
                $class = translateToIdentifier($elem, removeNonAlpha: true);
            } else {
                $class = false;
            }
            $class = $class? "td-$class": "td-$c";
            $out .= "      <th class='pfy-col-$i $class'>$elem</th>\n";
        }
        $out .= "    </tr>\n  </thead>\n";
        return $out;
    } // renderTableHead



    /**
     * Renders <tbody> section
     * @return string
     */
    private function renderTableBody(): string
    {
        $data = &$this->tableData;
        $elemKeys = $this->elementLabels;
        $out = "  <tbody>\n";
        $r = 0;
        foreach ($data as $key => $rec) {
            if ($this->dataReference) {
                $recKey = " data-reckey='$key'";
            } else {
                $recKey = '';
            }
            $r++;
            $rowClass = '';

            // mark record if locked:
            if ($this->markLocked) {
                if ($rec['_locked']??false) {
                    $rowClass = ' pfy-rec-locked';
                }
                unset($rec['_locked']);
            }

            $out .= "    <tr class='pfy-row-$r$rowClass'$recKey>\n";
            $i = 0;
            foreach ($elemKeys as $c => $k) {
                if ($c === '_locked') {
                    continue;
                }
                $i++;
                $v = $rec[$c]??'';
                if (!preg_match('/^\{\{.*}}$/', $k)) {
                    $class = translateToIdentifier($k, removeNonAlpha: true);
                } else {
                    $class = false;
                }
                $class = $class? "td-$class": "td-$c";
                if ($this->dataReference && ($kk = array_search($k, $this->columnKeys))) {
                    $elemid = " data-elemkey='$kk'";
                } else {
                    $elemid = '';
                }
                $out .= "      <td class='pfy-col-$i $class'$elemid>$v</td>\n";
            }
            $out .= "    </tr>\n";
        }
        $out .= "  </tbody>\n";
        return $out;
    } // renderTableBody



    /**
     * Renders <tfoot> section
     * @return string
     */
    private function renderTableFooter(): string
    {
        $data = &$this->tableData;
        $out = '';
        if ($this->footers) {
            $footer = $this->footers;
            $nCols = sizeof($this->elementLabels);
            $counts = $sums = array_combine(array_keys($this->elementLabels), array_fill(0, $nCols, 0));
            foreach ($data as $rec) {
                $i = 0;
                foreach ($rec as $key => $value) {
                    if ($key === '_locked') {
                        continue;
                    }
                    if (isset($footer[$key])) {
                        if (str_contains($footer[$key],TABLE_SUM_SYMBOL) && is_numeric($value)) {
                            $sums[$key] += $value;
                        } elseif (str_contains($footer[$key], TABLE_COUNT_SYMBOL) && $value) {
                            $counts[$key]++;
                        }
                    }
                    $i++;
                }
            }
            $out .= "  <tfoot>\n";
            $out .= "    <tr>\n";
            $c = 1;
            foreach (array_keys($this->elementLabels) as $key) {
                if ($key === '_locked') {
                    continue;
                }
                if (isset($footer[$key])) {
                    if (str_contains($footer[$key], TABLE_SUM_SYMBOL) || str_contains($footer[$key], TABLE_COUNT_SYMBOL)) {
                        $val = str_replace([TABLE_SUM_SYMBOL, TABLE_COUNT_SYMBOL], [$sums[$key], $counts[$key]], $footer[$key]);
                    } else {
                        $val = $footer[$key];
                    }
                } else {
                    $val = '';
                }
                $out .= "      <td class='pfy-col-$c'>$val</td>\n";
                $c++;
            }
            $out .= "    </tr>\n";
            $out .= "  </tfoot>\n";
        }
        return $out;
    } // renderTableFooter


    /**
     * Renders buttons for table's buttons row: delete,new,download etc.
     * @return string
     * @throws \Exception
     */
    private function renderTableButtons()
    {
        $out = '';
        if ($this->tableButtons) {
            $out .= "  <form method='post'>\n"; // form around table for selectors
        }
        $buttons = '';
        if ($this->tableButtonDelete) {
            $icon = renderIcon('trash');
            $buttons = "<button class='pfy-button pfy-button-lean pfy-table-delete-recs-open-dialog' ".
                "type='button' title='{{ pfy-table-delete-recs-title }}'>$icon</button>\n";
            PageFactory::$pg->addAssets('POPUPS');
        }

        if ($this->tableButtonNew) {
            $icon = '+';
            $buttons .= "<button class='pfy-button pfy-button-lean pfy-table-new-rec' ".
                "type='button' title='{{ pfy-opens-new-rec }}'>$icon</button>";
        }

        if ($this->tableButtonDownload) {
            $buttons .= $this->renderTableDownloadButton();
        }

        if ($buttons) {
            $out .= <<<EOT
<div class='pfy-table-buttons'>
$buttons
</div>
EOT;
        }
        return $out;
    } // renderTableButtons


    /**
     * Injects class and JS code to invoke DataTable library
     * @return void
     * @throws \Exception
     */
    private function activateInteractiveTable(): void
    {
        PageFactory::$pg->addAssets('DATATABLES');
        $this->tableWrapperClass .= ' pfy-interactive';

        $order = '';
        $paging = 'paging: false,';
        $pageLength = '';
        $orderable = '';

        $searchButtonLabel = TransVars::getVariable('pfy-datatables-filter-label');
        $pfyDatatablesRecords = TransVars::getVariable('pfy-datatables-records');

        $jq = <<<EOT

let pfyDatatable = new DataTable('#$this->tableId', {
  language: {
    search: '$searchButtonLabel:',
    info: '_TOTAL_ $pfyDatatablesRecords'
  },
  $order$paging$pageLength$orderable
});
EOT;
        PageFactory::$pg->addJq($jq);
    } // activateInteractiveTable


    /**
     * Handles requests to delete records, reloads page.
     * @return void
     * @throws \Exception
     */
    private function handleTableRequests(): void
    {
        $keysSelected = $_POST['pfy-reckey'];
        if ($keysSelected) {
            foreach ($keysSelected as $key) {
                if (strlen($key) > 4) { // skip _hdr and empty records
                    $this->remove($key);
                }
            }
            $this->flush();
        }
        unset($_POST['pfy-reckey']);
        $msg = TransVars::getVariable('pfy-form-rec-deleted');
        reloadAgent(message: $msg);
    } // handleTableRequests


    /**
     * Remders a download button for the table button row
     * @return string
     * @throws \Exception
     */
    private function renderTableDownloadButton(): string
    {
        $file = $this->exportDownloadDocs();
        $icon = renderIcon('cloud_download_alt');
        $buttons = "<button class='pfy-button pfy-button-lean pfy-table-download-start' " .
            "type='button' data-file='$file' title='{{ pfy-opens-download }}'>$icon</button>\n";

        $appUrl = PageFactory::$appUrl;
        if ($this->officeFormatAvailable) {
            $file = fileExt($file, true);
            $js = <<<EOT
pfyDownloadDialog[$this->inx] = '<p>{{ pfy-table-download-text }}</p><ul><li>{{ pfy-table-download-prefix }}'
        +'<a href="$appUrl{$file}.xlsx" download target="_blank">{{ pfy-table-download-excel }}</a>'
        +'{{ pfy-table-download-postfix }}:</li><li>{{ pfy-table-download-prefix }}'
        +'<a href="$appUrl{$file}.ods" download target="_blank">{{ pfy-table-download-ods }}</a>'
        +'{{ pfy-table-download-postfix }}</li></ul>';
EOT;

        } else {
            $js = <<<EOT
pfyDownloadDialog[$this->inx] = '<p>{{ pfy-table-download-text }}<br>{{ pfy-table-download-prefix }}'
        +'<a href="$appUrl{$file}" download target="_blank">{{ pfy-table-download-csv }}</a>'
        +'{{ pfy-table-download-postfix }}</p>';
EOT;
        }

        if ($this->inx === 1) {
            $js = "var pfyDownloadDialog = [];\n" . $js;
        }
        $js = TransVars::translate($js);
        PageFactory::$pg->addJs($js);
        PageFactory::$assets->addAssets('POPUPS, TABLES');
        return $buttons;
    } // renderTableDownloadButton


    /**
     * Prepares data for download -> converts and saves in temp files.
     * @return string
     * @throws \Exception
     */
    private function exportDownloadDocs(): string
    {
        $file = $this->export(fileType: true);
        return $file;
    } // exportDownloadDocs


    /**
     * Parses a comma-separated-list of scalar elements or tuples
     * @param $key
     * @return array|mixed
     */
    private function parseArrayArg($key)
    {
        $var = $this->$key ?? [];
        if (is_string($var)) {
            $var = explodeTrim(',', $var);
            $isAssoc = false;
            foreach ($var as $value) {
                if (strpos($value, ':') !== false) {
                    $isAssoc = true;
                    break;
                }
            }
            if ($isAssoc) {
                $tmp = [];
                foreach ($var as $value) {
                    if (preg_match('/(.*):\s*(.*)/', $value, $m)) {
                        $tmp[$m[1]] = trim($m[2], '\'"');
                    } else {
                        $tmp[$value] = $value;
                    }
                }
                $var = $tmp;
            }
        }
        $this->$key = $var;
        return $var;
    } // parseArrayArg

} // DataTable