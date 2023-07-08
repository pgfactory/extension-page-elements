<?php

namespace Usility\PageFactoryElements;

require_once __DIR__ . '/Data2DSet.php';

use Usility\MarkdownPlus\Permission;
use Usility\PageFactory\DataSet;
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
    private string $showRowNumbers;
    private mixed $showRowSelectors;
    private string $serviceColumns;
    private array $serviceColArray = [];
    private mixed $editMode;
    private string $sort;
    private $minRows;
    private string $export;
    private bool $includeSystemElements;
    private $elementLabels;
    protected $markLocked;
    protected $isTableAdmin;
    private bool $dialogInitialized = false;
    public bool $announceEmptyTable;
    private $archiveDb;

    /**
     * @param string $file
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string $file, array $options = [])
    {
        if (isset($GLOBALS['tableInx'])) {
            $GLOBALS['tableInx']++;
            $this->inx = $GLOBALS['tableInx'];
        } else {
            $this->inx = $GLOBALS['tableInx'] = 1;
        }
        parent::__construct($file, $options);

        if (isset($_GET['delete']) || isset($_GET['archive'])) {
            // skip, if no recKeys supplied or recKeys belong to some other table:
            if (($_POST['pfy-reckey']??false) && ($this->inx == ($_POST['tableinx']??false))) {
                $mode = isset($_GET['delete']) ? 'delete' : 'archive';
                $this->handleTableRequests($mode);
            }
        }

        $this->tableId = ($options['tableId']??false) ?: "pfy-table-$this->inx";
        $this->tableClass = ($options['tableClass']??false) ?: 'pfy-table';
        $this->tdClass = $options['tdClass']??'';
        $this->tableWrapperClass = ($options['tableWrapperClass']??false) ?: 'pfy-table-wrapper';
        $this->dataReference = $options['dataReference']??false; // whether to include data-elemkey and data-reckey
        $this->footers = ($options['footers']??false) ?: ($options['footer']??false);
        $this->caption = $options['caption']??false;
        $this->captionAbove = (($options['captionPosition']??false) ?: 'b')[0] === 'a';
        $this->obfuscateRecKeys = $options['obfuscateRecKeys'] ?? false;
        $this->interactive = $options['interactive'] ?? false;
        $tableButtons = $options['tableButtons'] ?? false;
        $serviceColumns = $options['serviceColumns'] ?? false; // num,select,edit,...
        $this->downloadFilename = ($options['downloadFilename']??false) ?: base_name($file, false);
        $this->showRowNumbers = $options['showRowNumbers'] ?? false;
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
        $this->minRows = $options['minRows'] ?? false;
        $this->export = $options['export'] ?? false;
        $this->includeSystemElements = $options['includeSystemElements'] ?? false;
        $this->markLocked = $options['markLocked'] ?? false;

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

        if ((str_contains($tableButtons, 'delete') || str_contains($tableButtons, 'archive'))
            && !str_contains($serviceColumns, 'select')) {
            $serviceColumns = "select,$serviceColumns";
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
                $this->tableHeaders['_timestamp'] = '_timestamp';
                $this->tableHeaders['_reckey'] = '_reckey';
            }
        }
        // table footers:
        if ($this->footers && !is_array($this->footers)) {
            $this->parseArrayArg('footers');
        }
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
        if (!$this->data) {
            if ($this->announceEmptyTable) {
                return '<div class="pfy-table-wrapper">{{ pfy-no-data-available }}</div>'; // done if no data available
            } else {
                $rec = array_combine(array_values($this->tableHeaders), array_pad([], sizeof($this->tableHeaders), ''));
                $this->addRec($rec, flush:false);
            }
        }
        $this->prepareTableData();

        // inject service rows: select(delete), row-numbers, edit-buttons
        $this->prependServiceColumns();

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
                $cell = "<button class='pfy-button pfy-button-lean pfy-row-edit-button' type='button' title='{{ pfy-table-edit-rec-title }}'>✎</button>";
                $hdrCell = TransVars::getVariable('pfy-row-edit-header');
                $this->injectColumn($cell, $hdrCell, isServiceCol: true);
                $serviceColumns[$i] = 'pfy-row-edit';

            } else {
                if (preg_match('/^([\w\s]+):(.*)/', $elem, $m)) {
                    $hdr = $m[1];
                    $elem = $m[2];
                    $serviceColumns[$i] = strtolower("pfy-row-$hdr");
                } else {
                    $hdr = false;
                    $serviceColumns[$i] = 'pfy-row-'.translateToIdentifier($elem, removeNonAlpha: true, toLowerCase: true);
                }
                if (!str_contains($elem, '<')) {
                    $class = translateToIdentifier($elem, false, true, true);
                    $elem = "<button class='pfy-button pfy-button-lean $class' type='button'>$elem</button>";
                }
                $this->injectColumn($elem, $hdr, isServiceCol: true);
            }
            $i--;
        }

        $this->serviceColArray = $serviceColumns;
    } // prependServiceColumns


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
                $class = translateToIdentifier($elem, removeNonAlpha: true, toLowerCase: true);
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
                $v = $rec[$k]??'';
                if (!preg_match('/^\{\{.*}}$/', $k)) {
                    $class = 'td-'.translateToIdentifier($k, removeNonAlpha: true);
                } else {
                    $class = '';
                }
                $serviceRow = $this->serviceColArray[$i]??'';
                if ($serviceRow) {
                    $class .= ' pfy-service-row';
                } else {
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
                    if (str_contains($footer[$key], TABLE_SUM_SYMBOL) || str_contains($footer[$key], TABLE_COUNT_SYMBOL)) {
                        $val = str_replace([TABLE_SUM_SYMBOL, TABLE_COUNT_SYMBOL], [$sums[$key], $counts[$key]], $footer[$key]);
                    } else {
                        $val = $footer[$key];
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

        $tableButtons = $this->tableButtons;
        $buttons = '';
        if (str_contains($tableButtons, 'archive')) {
            $icon = renderIcon('database');
            $buttons = "  <button class='pfy-button pfy-button-lean pfy-table-archive-recs-open-dialog' ".
                "type='button' title='{{ pfy-table-archive-recs-title }}'>$icon</button>\n";
            PageFactory::$pg->addAssets('POPUPS');

        } elseif (str_contains($tableButtons, 'delete')) {
            $icon = renderIcon('trash');
            $buttons = "  <button class='pfy-button pfy-button-lean pfy-table-delete-recs-open-dialog' ".
                "type='button' title='{{ pfy-table-delete-recs-title }}'>$icon</button>\n";
            PageFactory::$pg->addAssets('POPUPS');
        }

        if (str_contains($tableButtons, 'new') || str_contains($tableButtons, 'add')) {
            $icon = '+';
            $buttons .= "  <button class='pfy-button pfy-button-lean pfy-table-new-rec' ".
                "type='button' title='{{ pfy-opens-new-rec }}'>$icon</button>\n";
        }

        if (str_contains($tableButtons, 'download')) {
            $buttons .= $this->renderTableDownloadButton();
        }
        $tableButtons = str_replace(['archive', 'delete', 'add', 'new', 'download'],'', $tableButtons);

        // handle custom table buttons:
        $customTableButtons = explodeTrim(',', $tableButtons, true);
        if ($customTableButtons) {
            foreach ($customTableButtons as $button) {
                // determine whether we need to wrap it in an HTML button:
                if (str_contains($button, '<')) {
                    $buttons .= $button;
                } else {
                    $class = translateToIdentifier($button, false, true, true);
                    $buttons .= "<button class='pfy-button pfy-button-lean $class' type='button'>$button</button>";
                }
            }
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
        PageFactory::$pg->addAssets('DATATABLES');
        $this->tableWrapperClass .= ' pfy-interactive';

        $order = '';
        $paging = 'paging: false,';
        $pageLength = '';
        $orderable = '';

        $searchButtonLabel = TransVars::getVariable('pfy-datatables-filter-label');
        $pfyDatatablesRecords = TransVars::getVariable('pfy-datatables-records');

        $jq = <<<EOT

new DataTable('#$this->tableId', {
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
            foreach ($keysSelected as $key) {
                if (strlen($key) > 4) { // skip _hdr and empty records
                    if ($archiveMode) {
                        $this->archive($key);
                    } else {
                        $this->remove($key);
                    }
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
        $buttons = "  <button class='pfy-button pfy-button-lean pfy-table-download-start' " .
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

        if (!$this->dialogInitialized) {
            $this->dialogInitialized = true;
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


    private function archive($key) {
        $dataRec = $this->find($key);
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

} // DataTable