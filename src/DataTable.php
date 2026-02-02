<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\DataSet;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\Data2DSet as Data2DSet;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function \PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\isLoggedIn;
use function PgFactory\PageFactory\mylog;
use function PgFactory\PageFactory\translateToClassName;
use function \PgFactory\PageFactory\translateToIdentifier;
use function \PgFactory\PageFactory\array_splice_assoc;
use function \PgFactory\PageFactory\renderIcon;
use function \PgFactory\PageFactory\fileExt;
use function \PgFactory\PageFactory\reloadAgent;
use function \PgFactory\PageFactory\parseArgumentStr;


const TABLE_SUM_SYMBOL = '%sum%';
const TABLE_COUNT_SYMBOL = '%count%';
const DEFAULT_PAGEING_LENGTH = 25;

if (!function_exists('array_is_list')) {
    function array_is_list($array) {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }
}

const PFY_TABLE_DEFAULT_OPTIONS = [
    'masterFileRecKeyType' => 'index',
    'tableName' => '',
    'tableId' => '',
    'tableClass' => '',
    'tableWrapperClass' => '',
    'tdClass' => '',
    'colClasses' => [],
    'rowIds' => [],
    'rowClasses' => [],
    'dataReference' => false,
    'caption' => false,
    'captionPosition' => 'below',
    'markLocked' => false,
    'obfuscateRecKeys' => false,
    'mailFrom' => '',
    'mailFieldName' => '',
    'permission' => false, //'localhost,loggedin',
    'tableTitle' => false,
    'tableButtons' => false,
    'serviceColumns' => false,
    'showRowNumbers' => false,
    'showRowSelectors' => false,
    'computedCells' => false,
    'translateHeaders' => false,
    'editMode' => 'inpage',
    'showData' => false,
    'placeholderForUndefined' => '',
    'cellMinHeight' => false,
    'cellMaxHeight' => false,
    'minRows' => false,
    'rowCallback' => '',
    'obfuscateCols' => false,
    'paging' => true,
    'order' => false,
    'filter' => false,
    'reversed' => false,
    'dontPrint' => false,
    'export' => false,
    'headers' => false,
    'footers' => false,
    'interactive' => false,
    'scrollable' => false,
    'scrollHints' => null,
    'includeSystemElements' => false,
    'includeSystemFields' => false,
    'includeTimestamp' => false,
    'announceEmptyTable' => true,
    'showAllFields' => false,
    'shieldCellContent' => false,
];


class DataTable
{
    private array $options;
    private $file = false;
    private array|null$tableData = null;
    private $tableHeaders;
    private bool $translateHeaders;
    private $tableClass;
    private $tdClass;
    private $tableWrapperClass;
    private $dataReference;
    private array $tableButtons;
    public int $inx;
    private string $tableId;
    private $footers;
    private string $caption;
    private string $captionAbove;
    private string|bool $interactive;
    private string|bool $scrollable;
    private string $showRowNumbers;
    private mixed $showRowSelectors;
    private string $serviceColumns;
    private mixed $editMode;
    private string $order;
    private mixed $paging = false;
    private array|bool $filter;
    private bool $reversed;
    private $minRows;
    private string|false $rowCallback;
    private string $export;
    private bool|string $includeSystemElements;
    private bool|null $includeTimestamp;
    private $elementLabels;
    protected $markLocked;
    protected $isTableAdmin;
    private bool $dialogInitialized = false;
    public bool $announceEmptyTable;
    private $archiveDb;
    private $data2Dset;
    private array $columns = [];
    private $nRows;
    private $nCols;
    private $officeFormatAvailable = false;
    private array $colClasses;
    private array $rowClasses;
    private array $rowIds;
    private string $placeholderForUndefined;
    private mixed $shieldCellContent;
    private static int $tableInx = 0;
    private static bool $interactiveInitializee = false;
    private mixed $mailFrom;
    private mixed $mailFieldName;
    private array $computedCells = [];
    private string $nameAttr = '';
    private string $viewTemplate = '';
    private mixed $dontPrint = false;

    /**
     * @param string|array $dataSrc
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string|array $dataSrc, array $options = [])
    {
        self::$tableInx++;
        $this->inx = self::$tableInx;
        $this->nameAttr = "row-sel-$this->inx";

        $this->parseOptions($dataSrc, $options);
        $this->prepareTableData();
        $this->handleDataRequests();
    } // __construct


    /**
     * Renders the HTML table
     * @return string
     */
    public function render(): string
    {
        $this->prepareColumnDefs();

        if (isset($_GET['sendto']) && isLoggedIn()) {
            $this->sendRec($_GET['sendto']??false, $_GET['recid']??false);
        }

        if (sizeof($this->tableData) === 0) {
            if ($this->options['mode']??false) {
                return <<<EOT
<div class="pfy-table-wrapper">{{ pfy-no-data-available }}</div>

<button class='pfy-button pfy-table-fill-first-row'>{{ pfy-table-fill-first-row }}</button>
EOT;

            } elseif ($this->announceEmptyTable) {
                return '<div class="pfy-table-wrapper">{{ pfy-no-data-available }}</div>'; // done if no data available
            }
        }

        if ($this->interactive) {
            $this->activateInteractiveTable();
        }

        $rowCallback = $this->rowCallback ? " data-row-callback='$this->rowCallback'" : '';


        // === Assemble Table ================================================================
        $out = "\n<div id='pfy-table-wrapper-$this->inx' class='$this->tableWrapperClass' data-tableinx='$this->inx'$rowCallback>\n";
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
            $out .= "  </form><!-- /table-form -->\n";
        }

        $out .= $this->viewTemplate;

        $out .= "</div> <!-- /$this->tableWrapperClass -->\n\n";

        // inject JS for properly printing table -> expand paging, hide unwanted elements:
        $this->injectJsBeforePrint();

        return $out;
    } // render


    // === Render Table =============================================================================
    /**
     * Renders table wrapper, <table> and <thead> section
     * @return string
     */
    private function renderTableHead(): string
    {
        $out = "<table id='$this->tableId' class='$this->tableClass'>\n";
        // caption:
        if ($this->caption) {
            $style = $this->captionAbove? '': ' style="caption-side: bottom;"'; // use style to push caption below table
            $caption = str_replace('%#', $this->inx, $this->caption);
            $out .= "  <caption$style>$caption</caption>\n";
        }

        $out .= "  <thead>\n    <tr class='pfy-table-header pfy-row-0'>\n";
        foreach ($this->columns as $rec) {
            $out .= "      <th {$rec['hdrAttrib']}>{$rec['hdrContent']}</th>\n";
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
        $data = $this->tableData;

        $out = "  <tbody>\n";
        $rowClass = '';
        $r = 1;
        foreach ($data as $recKey => $dataRec) {
            $out .= $this->renderTableRow($r++, $recKey);
        }

        if ($this->minRows && $r < $this->minRows) {
            for ($r++; $r <= $this->minRows; $r++) {
                $out .= "    <tr class='pfy-row-$r $rowClass pfy-empty-row'>\n";
                foreach ($this->columns as $def) {
                    $cell = $def['cellContent'];
                    if ($cell === '%num') {
                        $cell = $r;
                    } else {
                        $cell = '<div>&nbsp;</div>';
                    }
                    $out .= "      <td {$def['cellAttrib']}>$cell</td>\n";
                }
            }
        }
        $out .= "  </tbody>\n";
        return $out;
    } // renderTableBody


    /**
     * @param int $r
     * @param int|string $recKey
     * @return string
     */
    private function renderTableRow(int $r, int|string $recKey): string
    {
        $rowClass = $this->rowClasses[$r-1] ?? '';
        $locked = $this->data2Dset->isLocked($recKey);
        $rowClass .= $locked ? ' pfy-rec-locked' : '';
        $out = "    <tr class='pfy-row-$r $rowClass' data-reckey='$recKey'>\n";

        foreach ($this->columns as $c => $def) {
            $out .= $this->renderTableCell($r, $recKey, $c, $def);
        }

        $out .= "    </tr>\n";
        return $out;
    } // renderTableRow


    /**
     * @param int $r
     * @param int|string $recKey
     * @param int $c
     * @param array $def
     * @return string
     */
    private function renderTableCell(int $r, int|string $recKey, int $c, array $def): string
    {
        $cell = $def['cellContent'];
        if (($cell[0] ?? '') === '=') {
            $cell = $this->renderComputedCells($recKey, $r, $c, substr($cell, 1));

        } elseif (($cell[0] ?? '') === '$') {
            $elemKey = substr($cell, 1);
            if ($cell === '$_timestamp') {
                $cell = date('d-m-Y, H:i', strtotime($this->tableData[$recKey][$elemKey]));
            } else {
                $cell = $this->tableData[$recKey][$elemKey] ?? $this->placeholderForUndefined;
            }
            if ($this->shieldCellContent) {
                $cell = htmlspecialchars($cell, ENT_QUOTES);
            }

        } elseif ($cell === '%num') {
            $cell = $r;
        }

        if (str_contains($cell, '%reckey')) {
            $cell = str_replace('%reckey', $recKey, $cell);
        }
        $cell = "<div>$cell</div>";
        $out = "      <td {$def['cellAttrib']}>$cell</td>\n";
        return $out;
    } // renderTableCell



    /**
     * @param mixed $k
     * @param mixed $rec
     * @param mixed $v
     * @param array $data
     * @param mixed $key
     * @return array|void
     */
    private function renderComputedCells(string  $recKey, int $r, int $c, string $cell): string
    {
        $rec = $this->tableData[$recKey];
        while (preg_match_all('/\$([\w.]+)/', $cell, $m)) {
            foreach ($m[1] as $ii => $vv) {
                $x = $rec[$vv] ?? '';
                $cell = str_replace($m[0][$ii], $x, $cell);
            }
        }
        if (str_starts_with($cell, 'PHP:')) {
            $cell = substr($cell, 4);
            try {
                $cell = "return $cell;";
                $cell = eval($cell);
            } catch (\Exception $e) {
                exit("Error: $e");
            }
        }
        return $cell;
    } // renderComputedCells


    /**
     * Renders <tfoot> section
     * @return string
     */
    private function renderTableFooter(): string
    {
        $data = &$this->tableData;
        $out = '';
        if ($this->footers) {
            $dataKeys = [];
            foreach ($this->columns as $rec) {
                $dataKeys[] = $rec['key']??'';
            }
            $footer = $this->footers;
            $nCols = sizeof($dataKeys);
            $counts = $sums = array_combine($dataKeys, array_fill(0, $nCols, 0));
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
            $out .= "    <tr class='pfy-table-footer-row'>\n";
            $c = 0;
            foreach ($dataKeys as $key) {

                if ($key === '_locked') {
                    continue;
                }
                if (isset($footer[$key])) {
                    $val = $footer[$key];
                    if (str_contains($val, TABLE_SUM_SYMBOL) || str_contains($val, TABLE_COUNT_SYMBOL)) {
                        $val = str_replace([TABLE_SUM_SYMBOL, TABLE_COUNT_SYMBOL], [$sums[$key], $counts[$key]], $val);
                    }
                    if ($val[0] === '=') {
                        try {
                            $val = substr($val,1);
                            $val = eval("return $val;");
                        } catch (\Exception $e) {
                            exit($e);
                        }
                    }
                } else {
                    $val = '&nbsp;';
                }
                $colClass = $this->colClasses[$c]??'';
                $out .= "      <td class='$colClass'><div>$val</div></td>\n";
                $c++;
            }
            $out .= "    </tr>\n";
            $out .= "  </tfoot>\n";
        }
        return $out;
    } // renderTableFooter




    // === Aux Methods ================================================================================

    /**
     * @return void
     * @throws \Exception
     */
    private function prepareTableData(): void
    {
        if ($this->file) {
            $this->data2Dset = new Data2DSet($this->file, $this->options);
            $this->tableData = $this->data2Dset->data();

        } elseif ($this->tableData !== null) {
            $this->options['tableName'] = ($this->options['tableName'] ?? false) ?: "table-$this->inx";
            $this->data2Dset = new Data2DSet($this->tableData, $this->options);
            $this->tableData = $this->data2Dset->normalizeData();
        } else {
            throw new \Exception("DataTable: either file or array required as input");
        }
    } // prepareTableData


    /**
     * @return void
     */
    private function prepareColumnDefs(): void
    {
        // inject service rows: select(delete), row-numbers, edit-buttons
        $c = $this->prepareServiceColumns();

        $tdClass = $this->tdClass? " $this->tdClass": '';
        if (!$this->tableHeaders || $this->tableHeaders === true) {
            $colHeaders = $this->data2Dset->getColHeaders();
        } else {
            $colHeaders = $this->tableHeaders;
        }

        // skip system columns if requested:
        if (!$this->options['includeSystemElements'] ?? false) {
            if (isset($colHeaders[DATAREC_RECKEY])) {
                unset($colHeaders[DATAREC_RECKEY]);
            }
            if (isset($colHeaders[DATAREC_TIMESTAMP]) && !$this->includeTimestamp) {
                unset($colHeaders[DATAREC_TIMESTAMP]);
            }
        }

        $i = sizeof($this->columns) + 1;
        foreach ($colHeaders as $key => $value) {
            if ($this->translateHeaders) {
                if ($v = TransVars::getVariable($value)) {
                    $value = $v;
                }
            }
            $dataElemName = "data-elemname='$key'";
            $class = 'pfy-col-'.translateToClassName($value);
            if ($value !== $key) {
                $class = 'pfy-col-'.translateToClassName(ltrim($key, '_'));
            }
            if ($this->colClasses[$c]??'') {
                $class .= ' ' . $this->colClasses[$c];
            }
            $class = "pfy-col-$i $class$tdClass";

            $cell = "\$$key";
            if ($this->computedCells[$key]?? false) {
                $cell = '='.$this->computedCells[$key];
            }

            $this->columns[] = [
                'hdrContent' => $value, // -> attributes for header elements
                'hdrAttrib' => "class='$class$tdClass' $dataElemName",
                'cellContent' => $cell, // -> means to be replaced by data value
                'cellAttrib' => "class='$class'",
                'key' => $key,
            ];
            $this->colClasses[$c] = $class;
            $i++;
            $c++;
        }
    } // prepareColumnDefs


    /**
     * Injects rows into data and header for delete,edit,row-numbers.
     * @return void
     */
    private function prepareServiceColumns(): int
    {
        if (!$this->serviceColumns) {
            return 0;
        }
        $tdClass = $this->tdClass? " $this->tdClass": '';
        $servCols = explodeTrim(',', $this->serviceColumns, true);
        $serviceColumns = [];
        $i = 1;
        foreach ($servCols as $elem) {
            if (str_starts_with($elem, 'select')) {
                $cell = '<input type="checkbox" name="reckey[]" value="%reckey">';
                $hdr = '<input type="checkbox">';
                $class = 'pfy-row-selector';

            } elseif (str_starts_with($elem, 'num')) {
                $cell = '%num';
                $hdr = TransVars::getVariable('pfy-row-number-header');
                $class = 'pfy-row-number';

            } elseif (str_starts_with($elem, 'edit')) {
                $icon = MdPlusHelper::renderIcon('edit');
                $cell = "<button class='pfy-button pfy-row-button pfy-row-edit-button' type='button' title='{{ pfy-table-edit-rec-title }}'>$icon</button>";
                $hdrCell = TransVars::getVariable('pfy-row-edit-header', varNameIfNotFound:true);
                if (preg_match('/:\w{3,20}:/', $hdrCell)) {
                    $hdrCell = MdPlusHelper::renderIcon($hdrCell, '{{ pfy-table-edit-rec-title }}');
                }
                $hdr = $hdrCell;
                $class = 'pfy-row-edit';

            } elseif (str_starts_with($elem, 'view')) {
                $icon = MdPlusHelper::renderIcon('eye');
                $cell = "<button class='pfy-button pfy-row-button pfy-row-view-button' type='button' title='{{ pfy-table-view-rec-title }}'>$icon</button>";
                $hdrCell = TransVars::getVariable('pfy-row-view-header', varNameIfNotFound:true);
                if (preg_match('/:\w{3,20}:/', $hdrCell)) {
                    $hdrCell = MdPlusHelper::renderIcon($hdrCell, '{{ pfy-table-view-rec-title }}');
                }
                $hdr = $hdrCell;
                $class = 'pfy-row-view';
                $this->renderViewTemplate();

            } elseif (str_starts_with($elem, 'send')) {
                $icon = MdPlusHelper::renderIcon('mail_send');
                $cell = "<button class='pfy-button pfy-row-button pfy-row-send-button' type='button' title='{{ pfy-table-send-rec-title }}'>$icon</button>";
                $hdrCell = TransVars::getVariable('pfy-row-send-header');
                $hdrCell = MdPlusHelper::renderIcon($hdrCell, '{{ pfy-table-send-rec-title }}');
                $hdr = $hdrCell;
                $class = 'pfy-row-send';

            } elseif (str_starts_with($elem, 'duplicate')) {
                $icon = MdPlusHelper::renderIcon('duplicate');
                $cell = "<button class='pfy-button pfy-row-button pfy-row-duplicate-button' type='button' title='{{ pfy-table-duplicate-rec-title }}'>$icon</button>";
                $hdrCell = TransVars::getVariable('pfy-row-duplicate-header');
                $hdrCell = MdPlusHelper::renderIcon($hdrCell, '{{ pfy-table-duplicate-rec-title }}');
                $hdr = $hdrCell;
                $class = 'pfy-row-duplicate';

            } else {
                // check whether element is defined as "Header:TableCell",
                //   where TableCell may contain an ":icon:"
                if (preg_match('|^([\w\s/]+):(.*)|', $elem, $m)) {
                    $hdrCell = $m[1];
                    $cell = $m[2];
                    $class = strtolower("pfy-row-$hdrCell");
                } else {
                    $hdrCell = $elem;
                    $cell = $elem;
                    $class = 'pfy-row-'.translateToClassName($elem);
                }
                if (!str_contains($cell, '<')) {
                    $title = $title1 = $this->parseForIcon($cell);
                    if ($title1) {
                        $title1 = " title='$title1'";
                    }
                    $class = 'pfy-col-'.translateToClassName($cell);
                    $cell = "<button class='pfy-button pfy-row-button $class' type='button'$title1>$cell</button>";
                }
                if ($hdrCell) {
                    $hdrCell = MdPlusHelper::renderIcon($hdrCell, $title);
                }
                $hdr = $hdrCell;
            }
            $cellClass = "pfy-col-$i pfy-service-col $class$tdClass";
            $serviceColumns[] = [
                'hdrContent' => $hdr, // -> attributes for header elements
                'hdrAttrib' => "class='pfy-col-$i pfy-service-col $class'",
                'cellContent' => $cell, // -> means to be replaced by data value
                'cellAttrib' => "class='$cellClass'",
            ];
            $this->colClasses[] = $cellClass;
            $i++;
        }

        $this->columns = $serviceColumns;
        return sizeof($this->colClasses);
    } // prepareServiceColumns


    /**
     * Icon-name may contain title attrib as "icon_name/title text..."
     * @param string $str
     * @return string
     * @throws \Exception
     */
    private function parseForIcon(string &$str): string
    {
        $title = '';
        if (str_contains($str, '/')) {
            list($str, $title) = explode('/', $str, 2);
        }
        $str = MdPlusHelper::renderIcon($str);
        return $title;
    } // parseForIcon


    /**
     * Renders buttons for table's buttons row: delete,new,download etc.
     * @return string
     * @throws \Exception
     */
    private function renderTableButtons()
    {
        $out = '';
        if (!$this->tableButtons) {
            return '';
        }

        $out .= "  <form method='post'>\n"; // form around table for selectors
        $out .= "    <input type='hidden' name='dataSrcInx' value='$this->inx'>\n"; // form around table for selectors

        $buttons = $type = $label = '';
        $i = -1;
        foreach ($this->tableButtons as $key => $tableButton) {
            if (!$tableButton) {
                continue;
            }
            $i++;
            if (is_string($tableButton)) {
                $type = $tableButton;
                $label = $tableButton;
            } elseif (is_array($tableButton)) {
                $type = ($tableButton['type']??false) ? $tableButton['type'] : $key;
                $label = ($tableButton['label']??false) ? $tableButton['label'] : $key;
            }
            switch ($type) {
                case 'archive':
                    $icon = renderIcon('database');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-archive-recs-open-dialog' ".
                        "type='button' title='{{ pfy-table-archive-recs-title }}'>$icon</button>\n";
                    Assets::addAssets('POPUPS');
                    break;

                case 'new':
                case 'add':
                    $icon = renderIcon('plus');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-new-rec' ".
                        "type='button' title='{{ pfy-opens-new-rec }}'>$icon</button>\n";
                    break;

                case 'delete':
                    $icon = renderIcon('trash');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-delete-recs-open-dialog' ".
                        "type='button' title='{{ pfy-table-delete-recs-title }}'>$icon</button>\n";
                    Assets::addAssets('POPUPS');
                    break;

                case 'email':
                case 'mail':
                    $icon = renderIcon('mail');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-mail-open-dialog' ".
                        "type='button' title='{{ pfy-table-create-mail-title }}'>$icon</button>\n";
                    Assets::addAssets('POPUPS');
                    $mailFieldSelector = 'td-'.translateToClassName($this->mailFieldName);
                    Page::addJs("const formOwnerEmail = '$this->mailFrom';\nconst mailFieldSelector = '$mailFieldSelector';");
                    break;

                case 'download':
                    $button = $this->renderTableDownloadButton();
                    break;

                case 'dropdown':
                    $button = $this->renderTableDropdownButton($tableButton, $label);
                    break;

                default:
                    if (str_contains($label, '<')) {
                        $button = $label;
                    } else {
                        $class = translateToClassName($label);
                        $callback = ($tableButton['callback']??false) ? " data-callback='{$tableButton['callback']}'" : '';
                        $button = "<button id='pfy-table-button-$this->inx-$i' class='pfy-button pfy-button-lean $class' type='button'$callback>$label</button>";
                    }
            }
            $buttons .= $button."\n";
        }

        if ($buttons) {
            $out .= <<<EOT
<div class='pfy-table-buttons'>
$buttons</div>


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
        Assets::addAssets('DATATABLES');
        $this->tableWrapperClass .= ' pfy-interactive';
        // layout:
        $layout = [
            'topStart' => '',
            'bottomStart' => 'info',
            'bottomEnd' => '',
        ];

        // paging:
        $paging = $entriesPerPageLabel = '';
        $pagingLength = DEFAULT_PAGEING_LENGTH;
        if ($this->paging) {
            $layout = [
                'topStart' => 'info',
                'bottomStart' => 'pageLength',
                'bottomEnd' => 'paging',
            ];
            if (is_numeric($this->paging)) {
                $pagingLength = str_contains(',10,25,50,100,', ",$this->paging,") ? $this->paging : $pagingLength;
                $paging = "lengthMenu: [10, 25, 50, 100],\n  pageLength: $pagingLength,";
            } elseif ($this->paging === true) {
                $paging = "lengthMenu: [10, 25, 50, 100],\n  pageLength: $pagingLength,";
            } else {
                $paging = "$this->paging,";
            }

            $entriesPerPageLabel = TransVars::getVariable('pfy-table-entries-per-page-label');
            if ($entriesPerPageLabel) {
                $entriesPerPageLabel = "lengthMenu: '$entriesPerPageLabel',";
            }
        } else {
            $paging = "pageLength: -1,";
        }
        $orderable = '';
        $scrollable = '';
        $order = '';
        $dir = 'asc';
        // order = <col-index> | <col-index>:desc | <col-name> | <col-name>:desc
        if ($this->order) {
            if (is_string($this->order)) {
                if (str_contains($this->order, ':')) {
                    list($elem, $dir) = explodeTrim(':', $this->order);
                } else {
                    $elem = $this->order;
                }
            } elseif (is_array($this->order)) {
                list($elem, $dir) = $this->order;
            }
            $dir = $dir ?: 'asc';
            if (!is_numeric($elem)) {
                foreach ($this->columns as $i => $column) {
                    if ($column['hdrContent'] === $elem) {
                        $elem = $i;
                        break;
                    }
                }
            }
            $order = "order: { idx: '$elem', dir: '$dir' },\n";
        }
        /* $scrollable not working, header widths unequal to body col widths
                if ($this->scrollable) {
                    $scrollable = <<<EOT
        scrollCollapse: true, scrollY: '$this->scrollable',

        EOT;
                }
        */
        $searchButtonLabel = TransVars::getVariable('pfy-datatables-filter-label');
        $pfyDatatablesRecords = TransVars::getVariable('pfy-datatables-records');

        $js = <<<EOT

pfyDataTable[$this->inx] = new DataTable('#$this->tableId', {
  language: {
    search: '$searchButtonLabel:',
    info: '_TOTAL_ $pfyDatatablesRecords',
    $entriesPerPageLabel
  },
  layout: {
    topStart:    '{$layout['topStart']}',
    bottomStart: '{$layout['bottomStart']}',
    bottomEnd:   '{$layout['bottomEnd']}',
  },
  $paging
  $scrollable$order$orderable
});
EOT;
        Page::addJsReady($js);
    } // activateInteractiveTable


    /**
     * Remders a download button for the table button row
     * @return string
     * @throws \Exception
     */
    private function renderTableDownloadButton(): string
    {
        $button = '';
        if (Data2DSet::checkOfficeFormatIsAvailable()) {
            $url = $this->exportDownloadDocs();
            if (!$url) {
                return '';
            }
            $filename = basename($url);
            $icon = renderIcon('cloud_download_alt');
            $button = "<button class='pfy-button pfy-button-lean pfy-table-download-start' role='button'>$icon</button>";
            $button .= "<a class='pfy-dispno' href='$url' download='$filename'>$icon</a>";
        }
        return $button;
    } // renderTableDownloadButton


    /**
     * @param array $tableButton
     * @param string $label
     * @return string
     */
    private function renderTableDropdownButton(array $tableButton, string $label): string
    {
        $dropdown = '';
        $callback = ($tableButton['callback']??false) ? " data-callback='{$tableButton['callback']}'" : '';
        $options = ($tableButton['options']??false) ? $tableButton['options'] : [];
        $title = ($tableButton['title']??false) ? " title='{$tableButton['title']}'" : '';
        $id = ($tableButton['id']??false) ? " id='{$tableButton['id']}'" : '';
        if (is_string($options)) {
            $options = explodeTrim(',', $options);
        }
        foreach ($options as $option) {
            if (str_contains($option, ':')) {
                list($value, $option) = explode(':', $option);
            } else {
                $value = $option;
            }
            $dropdown .= "  <option value='$value'>$option</option>\n";
        }

        $dropdown = <<<EOT
<select$id class="pfy-table-buttons-select-widget" $callback$title>
$dropdown
</select>

EOT;
        if ($label) {
            $dropdown = <<<EOT
<div  class="pfy-table-buttons-select-widget">$label
$dropdown
</div>
EOT;

        }

        return $dropdown;
    } // renderTableDownloadButton


    /**
     * Prepares data for download -> converts and saves in temp files.
     * @return string
     * @throws \Exception
     */
    private function exportDownloadDocs(): string
    {
        $includeMeta = $this->includeTimestamp ? 'timestamp' : '';
        return $this->data2Dset->export(fileType: 'office', includeMeta:$includeMeta);
    } // exportDownloadDocs


    /**
     * @param $key
     * @return void
     * @throws \Exception
     */
    private function archive($key) {
        $dataRec = $this->data2Dset->find($key);
        // in case data was empty and we added an empty rec, remove it again here:
        if ($dataRec) {
            $rec = $dataRec->data();
            $dataRec->remove();
            $this->archiveDb->addRec($rec);
        }
    } // archive


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
     * @return int|false
     * @throws \Exception
     */
    public function getSize(): int|false
    {
        if ($this->data2Dset) {
            return $this->data2Dset->getSize();
        } else {
            throw new \Exception("Error: DataTable operating in array-, not file-mode");
        }
    } // getSize


    /**
     * @return void
     * @throws \Exception
     */
    public function purge(): void
    {
        if ($this->data2Dset) {
            $this->data2Dset->purge();
        } else {
            throw new \Exception("Error: DataTable operating in array-, not file-mode");
        }
    } // purge


    /**
     * @param array $rec
     * @param bool $flush
     * @param $recKeyToUse
     * @return void
     * @throws \Exception
     */
    public function addRec(array $rec, bool $flush = true, $recKeyToUse = false)
    {
        if ($this->data2Dset) {
            $this->data2Dset->addRec($rec, $flush, $recKeyToUse);
        } else {
            throw new \Exception("Error: DataTable operating in array-, not file-mode");
        }
    } // addRec


    /**
     * @param string $email
     * @param string $recKey
     * @return void
     */
    private function sendRec(string $email, string $recKey): void
    {
        if (!($this->tableData[$recKey]??false) || !isLoggedIn()) {
            return;
        }

        $data = $this->tableData[$recKey];
        $str = '';
        foreach ($data as $key => $value) {
            $str .= str_pad("$key: ", 25, '. ') . "$value\n";
        }
        $subject = TransVars::getVariable('pfy-table-send-rec-subject');
        $template = TransVars::getVariable('pfy-table-send-rec-mail-template');
        $body = str_replace('%_data_%', $str, $template);

        $props = [
            'to' => $email,
            'subject' => $subject,
            'body' => $body,
        ];
        Utils::sendMail($props);
        $message = TransVars::getVariable('pfy-table-send-rec-confirmation');
        $message = str_replace('%email%', $email, $message);
        reloadAgent('', message: $message);
    } // sendRec


    /**
     * @return void
     * @throws \Exception
     */
    private function handleDataRequests(): void
    {
        if (!(isset($_GET['delete']) || isset($_GET['archive']))) {
            return;
        }
        // skip, if no recKeys supplied or recKeys belong to some other table:
        $keysSelected = $_POST['reckey'] ?? false;
        $dataSrcInx = $_POST['dataSrcInx'] ?? false;
        if (!$keysSelected || !$dataSrcInx || (self::$tableInx != $dataSrcInx)) {
            return;
        }
        $msg = '{{ pfy-form-rec-deleted }}';
        $mode = isset($_GET['delete']) ? 'delete' : 'archive';

        $archiveMode = ($mode === 'archive');
        if ($archiveMode) {
            $archiveFile = $this->file;
            $archiveFile = fileExt($archiveFile, true).'.archive.'.fileExt($archiveFile);
            $this->archiveDb = new DataSet($archiveFile);
            $msg = '{{ pfy-form-rec-archived }}';
        }
        if ($keysSelected) {
            if ($this->data2Dset) {
                foreach ($keysSelected as $key) {
                    if (strlen($key) > 4) { // skip _hdr and empty records
                        if ($archiveMode) {
                            $this->archive($key);
                        } else {
                            $this->data2Dset->remove($key);
                        }
                    }
                }
                $this->data2Dset->flush();
                reloadAgent(message: $msg);
            } else {
                throw new \Exception("Error: DataTable operating in array-, not file-mode");
            }
        }
        unset($_POST['reckey']);
        mylog("Error: DataTable request to delete record(s) failed");
    } // handleDataRequests


    /**
     * @return void
     */
    private function injectJsBeforePrint(): void
    {
        $js = "console.log('hiding from print:');\n";
        if (!$this->dontPrint) {
            $dontPrint = $this->dontPrint;
            if (!is_array($dontPrint)) {
                $dontPrint = explodeTrim(',', (string)$dontPrint);
            }
            foreach ($dontPrint as $elem) {
                $js .= <<<EOT
        domForAll('$elem', el => {
            el.classList.add('pfy-dont-print');
            console.log(el);
        });

EOT;
            }
        }
        $js = <<<EOT
if (typeof pfyDataTable !== 'undefined') {
    window.addEventListener('beforeprint', () => {
        console.log('expanding paged tables:');
        pfyDataTable.forEach(dt => {
            dt.page.len(1000).draw();
        });
$js
    });
}
EOT;

        Page::addJsReady($js);
    } // injectJsBeforePrint


    /**
     * @return void
     */
    private function renderViewTemplate(): void
    {
        $viewTemplate = '';
        foreach ($this->tableHeaders as $name => $label) {
            $viewTemplate .= <<<EOT
    <tr><td>$label:</td><td>\%$name\%</td></tr>

EOT;
        }
        $viewTemplate = <<<EOT

<div class="pfy-dispno">
<div class="pfy-table-view-template pfy-table-view-template-$this->inx">
<table>
$viewTemplate
</table>
</div><!-- /pfy-table-view-template -->
</div><!-- /pfy-dispno -->

EOT;

        $this->viewTemplate = $viewTemplate;
    } // renderViewTemplate


    /**
     * @return string
     */
    public function getTableId(): string
    {
        return "pfy-table-wrapper-$this->inx";
    } // getTableId



    // === Parse Options ===================================================================
    /**
     * @param array $options
     * @param array|string $dataSrc
     * @return void
     * @throws \Kirby\Exception\Exception
     */
    private function parseOptions( array|string $dataSrc, array $options): void
    {
        foreach (PFY_TABLE_DEFAULT_OPTIONS as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        if ($options['tableHeaders'] ?? false) {
            throw new \Exception("Error: DataTable: arg 'tableHeaders' is deprecated");
        }
        $this->tableHeaders = $options['headers'] = $options['headers'] ?? true;

        if (is_string($dataSrc)) {
            $this->file = $dataSrc;
        } elseif (is_array($dataSrc)) {
            $this->tableData = $dataSrc;
            $options['tableName'] = $options['tableName'] ?: "table-$this->inx";
        }

        $this->tableId = $options['tableId'] ?: "pfy-table-$this->inx";
        $this->tableClass = $options['tableClass'] ?: "pfy-table pfy-table-$this->inx";
        $this->colClasses = $options['colClasses'];
        $this->rowClasses = $options['rowClasses'];
        $this->rowIds = $options['rowIds'];
        $this->tdClass = $options['tdClass'];
        $this->tableWrapperClass = 'pfy-table-wrapper ' . $options['tableWrapperClass'] ?: (($options['wrapperClass'] ?? false) ?: '');
        $this->dataReference = $options['dataReference']; // whether to include data-elemkey and data-reckey
        $this->footers = $options['footers'] ?: ($options['footer'] ?? (($options['tableFooters']??false) ?: ($options['tableFooters'] ?? false)));
        $this->caption = $options['caption'];
        $this->captionAbove = ($options['captionPosition'][0] === 'a');
        $this->interactive = $options['interactive'];
        $this->scrollable = $options['scrollable'];
        if ($options['scrollHints'] === null) {
            $scrollHints = false;
        } else {
            $scrollHints = $options['scrollHints'];
        }
        $tableButtons = $options['tableButtons'];
        if (is_string($tableButtons)) {
            $tableButtons = parseArgumentStr($tableButtons);
        } else {
            $tableButtons = (array) $tableButtons;
        }

        $serviceColumns = $options['serviceColumns']; // num,select,edit,...
        $this->showRowNumbers = $options['showRowNumbers']; //??? obsolete?
        $this->showRowSelectors = $options['showRowSelectors'];
        if ($computedCells = $options['computedCells']) {
            if (!is_array($computedCells)) {
                $computedCells = explodeTrim(',', $computedCells);
                foreach ($computedCells as $col) {
                    list($key, $value) = preg_split('/=/', $col, 2);
                    $this->computedCells[$key] = $value;
                }
            } else {
                $this->computedCells = $computedCells;
            }
        }

        $this->translateHeaders = $options['translateHeaders'];
        $this->announceEmptyTable = $options['announceEmptyTable'];
        $this->editMode = $options['editMode'];
        if ($this->editMode === 'popup') {
            $this->announceEmptyTable = false;
            $this->tableClass .= ' pfy-table-edit-popup';
        }
        $this->order = $options['order'];
        $this->paging = $options['paging'];
        $this->filter = $options['filter'];
        $this->reversed = $options['reversed'];
        $this->minRows = $options['minRows'];
        $this->rowCallback = $options['rowCallback'];
        if ($this->rowCallback === true) {
            $this->rowCallback = 'true';
        }
        $this->dontPrint = $options['dontPrint'];
        $this->export = $options['export'];
        $this->includeSystemElements = $options['includeSystemElements'];
        $this->includeTimestamp = $options['includeTimestamp'];
        $this->markLocked = $options['markLocked'];
        $this->placeholderForUndefined = $options['placeholderForUndefined'];

        $this->shieldCellContent = $options['shieldCellContent'];

        $this->mailFieldName = $options['mailFieldName'];
        $this->mailFrom = $options['mailFrom'];

        $permission = $options['permission'];
        if ($permission === true) {
            $permission = 'localhost|loggedin';
        }
        $this->isTableAdmin = Permission::evaluate($permission);
        if (!$this->isTableAdmin) {
            if (in_array('download', $tableButtons)) {
                $tableButtons = ['download' => 'download'];
            }
            $serviceColumns = str_replace(['edit', 'select'], '', $serviceColumns);
        } else {
            $this->dataReference = true;
        }

        if (self::$tableInx === 1) {
            Assets::addAssets('TABLES');
        }

        if (in_array('delete', $tableButtons) || in_array('archive', $tableButtons)) {
            if (!str_contains($serviceColumns, 'select')) {
                $serviceColumns = "select,$serviceColumns";
            }
            Assets::addAssets('POPUPS');
        } elseif ($this->interactive) {
            Assets::addAssets('POPUPS');
        }

        $this->serviceColumns = $serviceColumns;
        $this->tableButtons = $tableButtons;

        // table headers:
        if ($this->tableHeaders && ($this->tableHeaders !== true)) {
            if (!is_array($this->tableHeaders)) {
                $this->tableHeaders = $this->parseArrayArg('tableHeaders');
            }
            if (is_numeric(array_keys($this->tableHeaders)[0])) {
//??? => check with Forms
//                $tableHeaders = [];
//                foreach ($this->tableHeaders as $str) {
//                    $tableHeaders[str_replace('-', '_', $str)] = $str;
//                }
//                $this->tableHeaders = $tableHeaders;
                $this->tableHeaders = array_combine($this->tableHeaders, $this->tableHeaders);
            }

            if ($this->includeSystemElements) {
                $this->tableHeaders['_timestamp'] = TransVars::getVariable('pfy-table-timestamp-header');
                $this->tableHeaders['_reckey'] = TransVars::getVariable('pfy-table-reckey-header');
            }
            if ($this->includeTimestamp && !isset($this->tableHeaders['_timestamp'])) {
                $this->tableHeaders['_timestamp'] = TransVars::getVariable('pfy-table-timestamp-header');
            }
            $options['headers'] = $this->tableHeaders;
        }
        // table footers:
        if ($this->footers && !is_array($this->footers)) {
            $this->parseArrayArg('footers');
        }

        // interactive option (=> DataTables.js):
        if ($this->interactive && !self::$interactiveInitializee) {
            self::$interactiveInitializee = true;
            Page::addJs('var pfyDataTable = [];');
            Assets::addAssets('JQUERY');
        }

        // misc options:
        if ($options['cellMinHeight']) {
            $css = <<<EOT
.pfy-table-$this->inx td > div {
    min-height: {$options['cellMinHeight']};
}
EOT;
            Page::addCss($css);
            if ($options['scrollHints'] === null) {
                $scrollHints = true;
            }
        }
        if ($options['cellMaxHeight']) {
            $css = <<<EOT
.pfy-table-$this->inx td > div {
    max-height: {$options['cellMaxHeight']};
    overflow-y: auto;
}
EOT;
            Page::addCss($css);
            if ($options['scrollHints'] === null) {
                $scrollHints = true;
            }
        }
        if ($scrollHints) {
            $this->tdClass .= ' pfy-scroll-hints';
        }

        $this->options = $options;
    } // parseOptions


} // DataTable