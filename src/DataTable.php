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
    private $tableButtonDelete = false;
    private $tableButtonDownload = false;
    private int $inx;
    private string $tableId;
    private $footers;
    private string $caption;
    private string $captionAbove;
    private string $interactive;
    private string $showRowNumbers;
    private string $showRowSelectors;
    private string $sort;
    private string $export;
    private $elementLabels;

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

        $this->inx = $options['inx'] ?? '1';
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
        $this->export = $options['export'] ?? false;
        $this->tableHeaders = $options['tableHeaders'] ?? ($options['headers'] ?? false);
        $this->dataStructure = $options['dataStructure'] ?? false;


        if ($this->tableHeaders && !is_array($this->tableHeaders)) {
            $this->parseArrayArg('tableHeaders');
        }
        if ($this->footers && !is_array($this->footers)) {
            $this->parseArrayArg('footers');
        }

        if ($this->tableButtons === true) {
            $this->tableButtonDelete = true;
            $this->tableButtonDownload = true;
            $this->dataReference = true;

        } elseif ($this->tableButtons) {
            $this->tableButtonDelete = (strpos('delete', $this->tableButtons) !== false);
            $this->tableButtonDownload = (strpos('download', $this->tableButtonDownload) !== false);
            $this->dataReference = true;
        }

        if ($this->tableButtonDelete) {
            $this->showRowSelectors = true;
        }


        if ($this->sort) {
            $this->sort($this->sort);
        }

        $this->prepareTableData();
    } // __construct


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
            return '<div class="pfy-table-wrapper">{{ no-data-available }}</div>'; // done if no data available
        }

        // Option row numbers:
        if ($this->showRowNumbers !== false) {
            $this->injectColumn('%row-numbers', '{{^ pfy-row-number-header }}');
        }

        // Option row selectors:
        if ($this->showRowSelectors) {
            $this->injectColumn('%row-selectors');
        }

        if ($this->interactive) {
            $this->activateInteractiveTable();
        }

        // render table header tags:
        $out = $this->renderTableHead();

        // render data cells:
        $out .= $this->renderTableBody();

        // render table footer:
        $out .= $this->renderTableFooter();

        // render table end tags:
        $out .= $this->renderTableTail();
        return $out;
    } // render



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
        if ($newElement === '%row-selectors') {
            $fillWith = '<input type="checkbox"%nameAttr>';
            $newElemName = 'row-selector';

        } elseif ($newElement === '%row-numbers') {
            $newElemName = 'row-number';
            $newCol = range(0, $this->nRows - 1);

        } elseif (is_string($newElement)) {
            $newCol = explodeTrim(',', ",$newElement");
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
        $out = "\n<div class='$this->tableWrapperClass'>\n";
        $out .= $this->renderTableButtons();

        $out .= "<table id='$this->tableId' class='$this->tableClass'>\n";

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
            $out .= "    <tr class='pfy-row-$r'$recKey>\n";
            $i = 0;
            foreach ($elemKeys as $c => $k) {
                $i++;
                $v = $rec[$c]??'';
                if (!preg_match('/^\{\{.*}}$/', $k)) {
                    $class = translateToIdentifier($k, removeNonAlpha: true);
                } else {
                    $class = false;
                }
                $class = $class? "td-$class": "td-$c";
                if ($this->dataReference && in_array($k, $this->elementFlattenedKeys)) {
                    $elemid = " data-elemkey='$c'";
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
     * Renders closing tags
     * @return string
     */
    private function renderTableTail(): string
    {
        $out = '';
        $out .= "</table>\n";
        if ($this->tableButtons) {
            $out .= "  </form>\n";
        }
        $out .= "</div> <!-- table-wrapper $this->tableWrapperClass -->\n\n";
        return $out;
    } // renderTableTail



    private function renderTableButtons()
    {
        $out = '';
        if ($this->tableButtons) {
            $out .= "  <form method='post'>\n";
        }

        if ($this->tableButtonDelete) {
            $icon = renderIcon('trash');
            $out .= "<button class='pfy-button pfy-button-lean pfy-table-delete-recs-open-dialog' ".
                "type='button' title='{{ pfy-table-delete-recs-title }}'>$icon</button>\n";
            PageFactory::$pg->addAssets('POPUPS');
        }

        if ($this->tableButtonDownload) {
            $file = $this->exportDownloadDocs();
            $icon = renderIcon('cloud_download_alt');
            $out .= "<button class='pfy-button pfy-button-lean pfy-table-download-start' ".
                "type='button' data-file='$file' title='{{ pfy-opens-download }}'>$icon</button>\n";

            $appUrl = PageFactory::$appUrl;
            if ($this->officeFormatAvailable) {
                $file = fileExt($file, true);
                $js = <<<EOT
    const pfyDownloadDialog = '<p>{{ pfy-table-download-text }}:</p><ul><li>{{^ pfy-table-download-prefix }}'
        +'<a href="$appUrl{$file}.xlsx" download target="_blank">{{ pfy-table-download-excel }}</a>'
        +'{{^ pfy-table-download-postfix }}:</li><li>{{^ pfy-table-download-prefix }}'
        +'<a href="$appUrl{$file}.ods" download target="_blank">{{ pfy-table-download-ods }}</a>'
        +'{{^ pfy-table-download-postfix }}</li></ul>';
EOT;

            } else {
                $js = <<<EOT
    const pfyDownloadDialog = '<p>{{ pfy-table-download-text }}<br>{{^ pfy-table-download-prefix }}'
        +'<a href="$appUrl{$file}" download target="_blank">{{ pfy-table-download-csv }}</a>'
        +'{{^ pfy-table-download-postfix }}</p>';
EOT;
            }

            PageFactory::$pg->addJs($js);
            PageFactory::$assets->addAssets('POPUPS, TABLES');
        }
        return $out;
    } // renderTableButtons



    private function activateInteractiveTable()
    {
        PageFactory::$pg->addAssets('DATATABLES');
        $this->tableClass .= ' pfy-interactive';

        $order = '';
        $paging = 'paging: false,';
        $pageLength = '';
        $orderable = '';

        $jq = <<<EOT

var pfyDatatable = $('#{$this->tableId}').DataTable({
'language':{'search':'{{ pfy-datatables-search-button }}:', 'info': '_TOTAL_ {{ pfy-datatables-records }}'},
$order$paging$pageLength$orderable
});

EOT;
        PageFactory::$pg->addJq($jq);
    } // activateInteractiveTable


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


    /**
     * Handles requests to delete records
     * @return void
     * @throws \Exception
     */
    private function handleTableRequests()
    {
        $keysSelected = $_POST['pfy-reckey'];
        if ($keysSelected) {
            foreach ($keysSelected as $key) {
                $this->remove($key);
            }
            $this->flush();
        }
        unset($_POST['pfy-reckey']);
        $msg = TransVars::getVariable('pfy-form-rec-deleted');
        reloadAgent(message: $msg);
    } // handleTableRequests


    private function exportDownloadDocs(): string
    {
        $file = $this->export(fileType: true);
        return $file;
    } // exportDownloadDocs

} // DataTable