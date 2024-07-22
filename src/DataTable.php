<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\DataSet;
use PgFactory\PageFactory\PageFactory as PageFactory;
use PgFactory\PageFactory\Data2DSet as Data2DSet;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function \PgFactory\PageFactory\explodeTrim;
use function PgFactory\PageFactory\isLoggedIn;
use function PgFactory\PageFactory\translateToClassName;
use function \PgFactory\PageFactory\translateToIdentifier;
use function \PgFactory\PageFactory\array_splice_assoc;
use function \PgFactory\PageFactory\renderIcon;
use function \PgFactory\PageFactory\fileExt;
use function \PgFactory\PageFactory\reloadAgent;


const TABLE_SUM_SYMBOL = '%sum%';
const TABLE_COUNT_SYMBOL = '%count%';

if (!function_exists('array_is_list')) {
    function array_is_list($array) {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }
}


class DataTable
{
    private $tableData;
    private $tableHeaders;
    private $tableClass;
    private $tdClass;
    private $tableWrapperClass;
    private $dataReference;
    private $tableButtons;
    public int $inx;
    private string $tableId;
    private $footers;
    private string $caption;
    private string $captionAbove;
    private string $interactive;
    private string|bool $scrollable;
    private string $showRowNumbers;
    private mixed $showRowSelectors;
    private string $serviceColumns;
    private array $serviceColArray = [];
    private mixed $editMode;
    private string $sort;
    private bool $reversed;
    private $minRows;
    private string $export;
    private bool|string $includeSystemElements;
    private bool $includeTimestamp;
    private $elementLabels;
    protected $markLocked;
    protected $isTableAdmin;
    private bool $dialogInitialized = false;
    public bool $announceEmptyTable;
    private $archiveDb;
    private $data2Dset;
    private $nRows;
    private $nCols;
    private $officeFormatAvailable = false;
    private $columnKeys = [];
    private $file = false;
    private array $colClasses;
    private array $rowClasses;
    private array $rowIds;
    private string $placeholderForUndefined;
    private mixed $shieldCellContent;
    private static int $tableInx = 0;
    private static bool $interactiveInitializee = false;
    private mixed $mailFrom;
    private mixed $mailFieldName;

    /**
     * @param string|array $dataSrc
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string|array $dataSrc, array $options = [])
    {
        self::$tableInx++;
        $this->inx = self::$tableInx;

        if (!isset($options['headers'])) {
            $options['headers'] = true;
        }
        if (is_string($dataSrc)) {
            $this->file = $dataSrc;
            $this->data2Dset = new Data2DSet($dataSrc, $options);
        } else {
            $this->tableData = $dataSrc;
            $this->data2Dset = new Data2DSet(false, $options);
        }

        if (isset($_GET['delete']) || isset($_GET['archive'])) {
            // skip, if no recKeys supplied or recKeys belong to some other table:
            if (($_POST['pfy-reckey']??false) && ($this->inx == ($_POST['tableinx']??false))) {
                $mode = isset($_GET['delete']) ? 'delete' : 'archive';
                $this->handleTableRequests($mode);
            }
        }

        $this->tableId = ($options['tableId']??false) ?: "pfy-table-$this->inx";
        $this->tableClass = ($options['tableClass']??false) ?: 'pfy-table';
        $this->colClasses = $options['colClasses']??[];
        $this->rowClasses = $options['rowClasses']??[];
        $this->rowIds = $options['rowIds']??[];
        $this->tdClass = $options['tdClass']??'';
        $this->tableWrapperClass = ($options['tableWrapperClass']??false) ?: 'pfy-table-wrapper';
        $this->dataReference = $options['dataReference']??false; // whether to include data-elemkey and data-reckey
        $this->footers = ($options['footers']??false) ?: ($options['footer']??false);
        $this->caption = $options['caption']??false;
        $this->captionAbove = (($options['captionPosition']??false) ?: 'b')[0] === 'a';
        $this->interactive = $options['interactive'] ?? false;
        $this->scrollable = $options['scrollable'] ?? false;
        $tableButtons = $options['tableButtons'] ?? false;
        $serviceColumns = $options['serviceColumns'] ?? false; // num,select,edit,...
        $this->showRowNumbers = $options['showRowNumbers'] ?? false; //??? obsolete?
        $this->showRowSelectors = $options['showRowSelectors'] ?? false;

        $this->tableHeaders = $options['tableHeaders'] ?? ($options['headers'] ?? false);
        $this->editMode = ($options['editMode']??false) ?: 'inpage';
        $this->announceEmptyTable = $options['announceEmptyTable'] ?? true;
        if ($this->editMode === 'popup') {
            $this->announceEmptyTable = false;
            $this->tableClass .= ' pfy-table-edit-popup';
        }
        $permission = $options['permission'] ?? false;

        $this->sort = $options['sort'] ?? false;
        $this->reversed = $options['reversed'] ?? false;
        $this->minRows = $options['minRows'] ?? false;
        $this->export = $options['export'] ?? false;
        $this->includeSystemElements = $options['includeSystemElements'] ?? false;
        $this->includeTimestamp     = $options['includeTimestamp'] ?? false;
        $this->markLocked = $options['markLocked'] ?? false;
        $this->placeholderForUndefined = ($options['placeholderForUndefined']??'?');

        $this->shieldCellContent = $options['shieldCellContent'] ?? false;

        $this->mailFieldName = $options['mailFieldName'] ?? false;
        $this->mailFrom = $options['mailFrom'] ?? false;

        if ($permission === true) {
            $permission = 'localhost|loggedin';
        }
        $this->isTableAdmin = Permission::evaluate($permission);
        // precautions for non-privileged access: only allow download and num:
        if (!$this->isTableAdmin) {
            $tableButtons = str_contains($tableButtons, 'download') ? 'download' : '';
            $serviceColumns = str_replace(['edit', 'select'],'', $serviceColumns);
        } else {
            $this->dataReference = true;
        }

        if (str_contains($tableButtons, 'delete') || str_contains($tableButtons, 'archive')) {
            if (!str_contains($serviceColumns, 'select')) {
                $serviceColumns = "select,$serviceColumns";
            }
            PageFactory::$assets->addAssets('POPUPS, TABLES');
        }
        $this->serviceColumns = $serviceColumns;
        $this->tableButtons = $tableButtons;

        // table headers:
        if ($this->tableHeaders) {
            if (!is_array($this->tableHeaders)) {
                $this->parseArrayArg('tableHeaders');
            }
            if ($this->includeSystemElements) {
                $this->tableHeaders['_timestamp'] = TransVars::getVariable('pfy-table-timestamp-header');
                $this->tableHeaders['_reckey'] = TransVars::getVariable('pfy-table-reckey-header');
            }
            if ($this->includeTimestamp && !isset($this->tableHeaders['_timestamp'])) {
                $this->tableHeaders['_timestamp'] = TransVars::getVariable('pfy-table-timestamp-header');
            }
        }
        // table footers:
        if ($this->footers && !is_array($this->footers)) {
            $this->parseArrayArg('footers');
        }

        // interactive option (=> DataTables.js):
        if ($this->interactive && !self::$interactiveInitializee) {
            self::$interactiveInitializee = true;
            PageFactory::$pg->addJs('var pfyDataTable = [];');
            PageFactory::$pg->addAssets('JQUERY');
        }
    } // __construct


    /**
     * Renders the HTML table
     * @return string
     */
    public function render(): string
    {
        $this->prepareTableData();

        if (isset($_GET['sendto']) && isLoggedIn()) {
            $this->sendRec($_GET['sendto']??false, $_GET['recid']??false);
        }

        if (sizeof($this->tableData) < 2) {
            if ($this->announceEmptyTable) {
                return '<div class="pfy-table-wrapper">{{ pfy-no-data-available }}</div>'; // done if no data available
            }
        }

        // inject service rows: select(delete), row-numbers, edit-buttons
        $this->prependServiceColumns();

        if ($this->interactive) {
            $this->activateInteractiveTable();
        }

        $out = "\n<div id='pfy-table-wrapper-$this->inx' class='$this->tableWrapperClass'>\n";
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
        $out .= "</div> <!-- /$this->tableWrapperClass -->\n\n";
        return $out;
    } // render



    /**
     * @return void
     * @throws \Exception
     */
    private function prepareTableData(): void
    {
        if (!$this->tableData) {
            // fetch data from datasource:
            $this->tableData = $this->data2Dset->normalizeData(false, '?', $this->tableHeaders);

        } else {
            // data already exists in $this->tableData -> amend it for table output:
            $data = [];
            $data['_hdr'] = $this->tableHeaders;
            foreach ($this->tableData as $dataRec) {
                $rec = [];
                foreach ($this->tableHeaders as $key => $value) {
                    $rec[$key] = $this->data2Dset->normalizeDataElement($key, $dataRec[$key]??($dataRec[$value]??''));
                }
                $data[] = $rec;
            }
            $this->tableData = $data;
        }
        if ($this->sort) {
            $this->sortTableData();
        }
    } // prepareTableData


    /**
     * @return void
     */
    private function sortTableData(): void
    {
        $table = $this->tableData;
        $this->tableData = [];
        $this->tableData['_hdr'] = array_shift($table);
        uasort($table, function ($a,$b) {
            return strcmp($a[$this->sort], $b[$this->sort]);
        });

        if ($this->reversed) {
            $table = array_reverse($table, true);
        }
        $this->tableData = $this->tableData + $table;
    } // sortTableData


    /**
     * Injects rows into data and header for delete,edit,row-numbers.
     * @return void
     */
    private function prependServiceColumns(): void
    {
        if (!$this->serviceColumns) {
            return;
        }
        $servCols = explodeTrim(',', $this->serviceColumns, true);
        $serviceColumns = [];
        $i = sizeof($servCols);
        foreach (array_reverse($servCols) as $elem) {
            if (str_starts_with($elem, 'num')) {
                $hdrCell = TransVars::getVariable('pfy-row-number-header');
                $this->injectColumn('pfy-row-number', $hdrCell, isServiceCol: true);
                $serviceColumns[$i] = 'pfy-row-number';

            } elseif (str_starts_with($elem, 'select')) {
                $this->injectColumn('pfy-row-selector', isServiceCol: true);
                $serviceColumns[$i] = 'pfy-row-selector';

            } elseif (str_starts_with($elem, 'edit')) {
                $icon = MdPlusHelper::renderIcon('edit');
                $cell = "<button class='pfy-button pfy-row-button pfy-row-edit-button' type='button' title='{{ pfy-table-edit-rec-title }}'>$icon</button>";
                $hdrCell = TransVars::getVariable('pfy-row-edit-header');
                $hdrCell = MdPlusHelper::renderIcon($hdrCell, '{{ pfy-table-edit-rec-title }}');
                $this->injectColumn($cell, $hdrCell, isServiceCol: true);
                $serviceColumns[$i] = 'pfy-row-edit';

            } elseif (str_starts_with($elem, 'send')) {
                $icon = MdPlusHelper::renderIcon('mail_send');
                $cell = "<button class='pfy-button pfy-row-button pfy-row-send-button' type='button' title='{{ pfy-table-send-rec-title }}'>$icon</button>";
                $hdrCell = TransVars::getVariable('pfy-row-send-header');
                $hdrCell = MdPlusHelper::renderIcon($hdrCell, '{{ pfy-table-send-rec-title }}');
                $this->injectColumn($cell, $hdrCell, isServiceCol: true);
                $serviceColumns[$i] = 'pfy-row-send';

            } else {
                // check whether element is defined as "Header:TableCell"
                if (preg_match('|^([\w\s/]+):(.*)|', $elem, $m)) {
                    $hdr = $m[1];
                    $elem = $m[2];
                    $serviceColumns[$i] = strtolower("pfy-row-$hdr");
                } else {
                    $hdr = false;
                    $serviceColumns[$i] = 'pfy-row-'.translateToClassName($elem);
                }
                if (!str_contains($elem, '<')) {
                    $title = $title1 = $this->parseForIcon($elem);
                    if ($title1) {
                        $title1 = " title='$title1'";
                    }
                    $class = translateToClassName($elem);
                    $elem = "<button class='pfy-button pfy-row-button $class' type='button'$title1>$elem</button>";
                }
                if ($hdr) {
                    $hdr = MdPlusHelper::renderIcon($hdr, $title);
                }
                $this->injectColumn($elem, $hdr, isServiceCol: true);
            }
            $i--;
        }

        $this->serviceColArray = $serviceColumns;
    } // prependServiceColumns


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
    private function injectColumn(string $newElement = '', mixed $headElement = '', int $col = 0, $isServiceCol = false): void
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

        if ($isServiceCol) {
            // case row-number:
            if ($newElement === 'pfy-row-number') {
                $newCol = range(0, $this->nRows - 1);

            // case row-selector:
            } elseif ($newElement === 'pfy-row-selector') {
                $fillWith = '<input type="checkbox"%nameAttr>';
                $headElement = '';
                $newElemName = 'pfy-row-selector';

            // case other content:
            } elseif (is_string($newElement)) {
                $fillWith = $newElement;
                $newCol = [$headElement];
            }
        }

        $newCol[0] = $headElement ?: $fillWith;
        $newCol = array_pad($newCol, $this->nRows, $fillWith);

        // fix $this->elementLabels accordingly:
        $name = translateToIdentifier($headElement);
        if (is_array($this->tableHeaders)) {
            array_splice_assoc($this->tableHeaders, $col, $col, [$name => $headElement]);
        }

        $i = 0;
        foreach ($data as $key => $rec) {
            $newElem = str_replace('%nameAttr'," name='pfy-reckey[]' value='$key'", $newCol[$i]);

            // prevent injection for empty rows:
            if (is_numeric($key) && ($newElement !== 'pfy-row-number')) {
                $newElem = '&nbsp;';
            }

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
        $out = "<table id='$this->tableId' class='$this->tableClass' data-tableinx='$this->inx'>\n";

        // caption:
        if ($this->caption) {
            $style = $this->captionAbove? '': ' style="caption-side: bottom;"'; // use style to push caption below table
            $caption = str_replace('%#', $this->inx, $this->caption);
            $out .= "  <caption$style>$caption</caption>\n";
        }

        $out .= "  <thead>\n    <tr class='pfy-table-header pfy-row-0'>\n";
        $headerRow = array_shift($data);
        $this->elementLabels = [];
        $i = 0;
        foreach ($headerRow as $c => $elem) {
            if ($c === '_locked') {
                continue;
            }
            $i++;
            if (isset($this->serviceColArray[$i])) {
                $class = "pfy-service-row {$this->serviceColArray[$i]}";
            } else {
                if (!($class = ($this->colClasses[$i-1]??false))) {
                    $class = translateToClassName($elem);
                }
            }
            $this->elementLabels[] = $c;
            if (!preg_match('/[^-\w\s]/', $elem)) {
                if ($e = TransVars::getVariable($elem)) {
                    $elem = $e;
                }
            }
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
        $tdClass = $this->tdClass? " class='$this->tdClass'": '';
        $out = "  <tbody>\n";
        $r = 0;
        foreach ($data as $key => $rec) {
            if ($this->dataReference) {
                if ($this->rowIds[$r]??false) {
                    $key = $this->rowIds[$r];
                }
                $recKey = " data-reckey='$key'";
            } else {
                $recKey = '';
            }
            $rowClass = $this->rowClasses[$r]??'';
            $r++;

            $emptyRowClass = '';
            // for first row: check whether data rec contains but empty elements:
            if ($r === 1 && $this->data2Dset) {
                $rawRec = $this->data2Dset->getRec($key);
                if ($rawRec && is_array($rawRec)) {
                    $isNotEmpty = (bool)array_filter($rawRec, function ($e) {
                        if (is_string($e)) {
                            return (bool)$e;
                        } elseif (is_array($e)) {
                            return array_filter($e, function ($el) {
                                if (is_string($el)) {
                                    return (bool)$el;
                                } else {
                                    return true;
                                }
                            });
                        } else {
                            return true;
                        }
                    });
                    if (!$isNotEmpty) {
                        $emptyRowClass = ' pfy-empty-row';
                    }
                }
            }

            // mark record if locked:
            if ($this->markLocked) {
                if ($rec['_locked']??false) {
                    $rowClass = ' pfy-rec-locked';
                }
                unset($rec['_locked']);
            }

            $out .= "    <tr class='pfy-row-$r $rowClass$emptyRowClass'$recKey>\n";
            $i = 0;
            foreach ($elemKeys as $c => $k) {
                if ($c === '_locked') {
                    continue;
                }
                $i++;
                $v = $rec[$k]??'';
                if (is_array($v)) {
                    if (isset($v['_'])) {
                        $v = $v['_'];
                    } else {
                        $v = implode(',', $v);
                    }
                }
                if ($this->colClasses[$i-1]??false) {
                    $class = $this->colClasses[$i-1];
                } elseif (!preg_match('/^\{\{.*}}$/', $k)) {
                    $class = 'td-'.translateToClassName($k);
                } else {
                    $class = '';
                }
                $serviceRow = $this->serviceColArray[$i]??'';
                if ($serviceRow) {
                    $class .= ' pfy-service-row';
                } else {
                    if ($this->shieldCellContent) {
                        $v = htmlspecialchars($v);
                    }
                    $v = "<div$tdClass>$v</div>";
                }
                $class = $class? "$class $serviceRow": $serviceRow;
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
            $counts = $sums = array_combine($this->elementLabels, array_fill(0, $nCols, 0));
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
            foreach ($this->elementLabels as $key) {
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
        if (!$this->tableButtons) {
            return '';
        }

        $out .= "  <form method='post'>\n"; // form around table for selectors
        $out .= "    <input type='hidden' name='tableinx' value='$this->inx'>\n"; // form around table for selectors

        $tableButtons = explodeTrim(',', $this->tableButtons);
        $buttons = '';
        foreach ($tableButtons as $i => $tableButton) {
            switch ($tableButton) {
                case 'archive':
                    $icon = renderIcon('database');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-archive-recs-open-dialog' ".
                        "type='button' title='{{ pfy-table-archive-recs-title }}'>$icon</button>\n";
                    PageFactory::$pg->addAssets('POPUPS');
                    break;

                case 'new':
                case 'add':
                $icon = '+';
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-new-rec' ".
                        "type='button' title='{{ pfy-opens-new-rec }}'>$icon</button>\n";
                    break;

                case 'delete':
                    $icon = renderIcon('trash');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-delete-recs-open-dialog' ".
                        "type='button' title='{{ pfy-table-delete-recs-title }}'>$icon</button>\n";
                    PageFactory::$pg->addAssets('POPUPS');
                    break;

                case 'email':
                case 'mail':
                    $icon = renderIcon('mail');
                    $button = "  <button class='pfy-button pfy-button-lean pfy-table-mail-open-dialog' ".
                        "type='button' title='{{ pfy-table-create-mail-title }}'>$icon</button>\n";
                    PageFactory::$pg->addAssets('POPUPS');
                    $mailFieldSelector = 'td-'.translateToClassName($this->mailFieldName);
                    PageFactory::$pg->addJs("const formOwnerEmail = '$this->mailFrom';\nconst mailFieldSelector = '$mailFieldSelector';");
                    break;

                case 'download':
                    $button = $this->renderTableDownloadButton();
                    break;

                default:
                    if (str_contains($tableButton, '<')) {
                        $button = $tableButton;
                    } else {
                        $class = translateToClassName($tableButton);
                        $button = "<button id='pfy-table-button-$this->inx-$i' class='pfy-button pfy-button-lean $class' type='button'>$tableButton</button>";
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
        PageFactory::$pg->addAssets('TABLES,DATATABLES');
        $this->tableWrapperClass .= ' pfy-interactive';

        $order = '';
        $paging = 'paging: false,';
        $pageLength = '';
        $orderable = '';
        $scrollable = '';
        if ($this->scrollable) {
            $scrollable = <<<EOT
scrollCollapse: true, scrollY: '$this->scrollable',

EOT;
        }

        $searchButtonLabel = TransVars::getVariable('pfy-datatables-filter-label');
        $pfyDatatablesRecords = TransVars::getVariable('pfy-datatables-records');

        $jq = <<<EOT

pfyDataTable[$this->inx] = new DataTable('#$this->tableId', {
  language: {
    search: '$searchButtonLabel:',
    info: '_TOTAL_ $pfyDatatablesRecords'
  },
  $scrollable$order$paging$pageLength$orderable
});
EOT;
        PageFactory::$pg->addJq($jq);
    } // activateInteractiveTable


    /**
     * Handles requests to delete records, reloads page.
     * @return void
     * @throws \Exception
     */
    private function handleTableRequests($mode): void
    {
        $archiveMode = ($mode === 'archive');
        if ($archiveMode) {
            $archiveFile = $this->file;
            $archiveFile = fileExt($archiveFile, true).'.archive.'.fileExt($archiveFile);
            $this->archiveDb = new DataSet($archiveFile);
        }
        $keysSelected = $_POST['pfy-reckey'];
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
            } else {
                throw new \Exception("Error: DataTable operating in array-, not file-mode");
            }
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
        $button = '';
        $appUrl = PageFactory::$appUrl;
        if (DataSet::$officeFormatAvailable) {
            $file = $this->exportDownloadDocs();
            $filename = basename($file);
            $icon = renderIcon('cloud_download_alt');
            $button = "<a class='pfy-button pfy-button-lean pfy-table-download-start' href='$appUrl$file' title='Download $filename' download>$icon</a>";
        }
        return $button;
    } // renderTableDownloadButton


    /**
     * Prepares data for download -> converts and saves in temp files.
     * @return string
     * @throws \Exception
     */
    private function exportDownloadDocs(): string
    {
        $file = $this->data2Dset->export(fileType: 'office');
        return $file;
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
        $body = str_replace('%data%', $str, $template);

        Utils::sendMail($email, $subject, $body);
        $message = TransVars::getVariable('pfy-table-send-rec-confirmation');
        $message = str_replace('%email%', $email, $message);
        reloadAgent('', message: $message);
    } // sendRec

} // DataTable