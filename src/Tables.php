<?php

namespace Usility\PageFactory\PageElements;

//use Usility\PageFactory\PageFactory;


define('FORM_TYPES',
    ',string,text,password,email,textarea,radio,checkbox,'.
    'dropdown,button,url,date,time,datetime,month,number,range,tel,file,'.
    'fieldset,fieldset-end,reveal,hidden,literal,bypassed,');
define('SCALAR_TYPES',
    ',string,text,password,email,textarea,'.
    ',url,date,time,datetime,month,number,range,tel,');

define('DEFAULT_EDIT_FORM_TEMPLATE_FILE', '~page/-table_edit_form_template.md');
define('PFY_TABLE_SHOW_REC_ICON', "<span class='pfy-icon pfy-icon-show2'></span>");
define('DOWNLOAD_PATH_LINK_CODE', '.#download.code');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Ods;


class Tables
{
    private $errMsg = '';
    private $dataTableObj = null;
    private $strToAppend = '';
    private $srcRef = '';
    private $ticketHash;
    private $jq;
    private $headerElems;
    private $data, $data0, $dataBare; // $dataBare = data without data-ref
    private $nRows;
    private $textResourcesRendered;
    private $structure;
    private $ds;
    private $instructions;
    private $phpExpr;
    private $nCols;
    private $processingInstructions;
    private $form;
    private $tableCounter;
    private static $tableCounter0 = 1;

    public function __construct($pfy, $options)
    {
        if (is_string($options)) {
            $dataSource = $options;
            $options = [];
            $options['dataSource'] = $dataSource;
        }
        $this->options = $options;
        $this->pfy = $pfy;
        $this->page = $pfy->pg;
//        $this->pfy = $pfy;
//        $this->page = $pfy->page;
//        $this->tableCounter = &$GLOBALS['lizzy']['tableCounter'][$GLOBALS['lizzy']['pagePath']];
        $this->tableCounter = self::$tableCounter0++;
        $this->helpText = false;
        $this->ticketHash = false;
        $this->specificRowClasses = [];
        if ($options === 'help') {
            $this->helpText = [];
            $options = [];
        }
        $this->editFormRendered = false;

        foreach ($options as $key => $rec) {
            $this->$key = $rec;
        }
        $this->recViewButtonsActive = $this->showRecViewButton;
        $this->inMemoryData = $this->data;
        if ($this->class) {
            $this->tableClass = trim("$this->tableClass $this->class");
        }
        $this->nRowsReq = $this->nRows;
        $this->nColsReq = $this->nCols;

//        $this->dataSource               = $this->getOption('dataSource', '(optional if nCols is set) Name of file containing data. Format may be .cvs or .yaml and is expected be local to page folder.');
//        $this->preselectData            = $this->getOption('preselectData', 'If set, pre-selects data from dataSource before rendering (only makes sense for higher dimensional data).');
//        $this->inMemoryData             = $this->getOption('data', 'Alternative to "dataSource": provide data directly as array. E.g. data: $array,');
//        $this->id                       = $this->getOption('id', '(optional) Id applied to the table tag (resp. wrapping div tag if renderAsDiv is set)');
//        $this->wrapperClass             = $this->getOption('wrapperClass', '(optional) Class applied to the DIV around the table tag (resp. wrapping div tag if renderAsDiv is set). (Default: "pfy-table-default" which applies default styling).', 'pfy-table-default');
//        $this->tableClass               = $this->getOption('tableClass', '(optional) Class applied to the table tag (resp. wrapping div tag if renderAsDiv is set). Use "pfy-table-default" to apply default styling.');
//        $this->tableClass               = $this->getOption('class', 'Synonyme for tableClass', $this->tableClass);
//        $this->cellClass                = $this->getOption('cellClass', '(optional) Class applied to each table cell');
//        $this->cellWrapper              = $this->getOption('cellWrapper', '(optional) If true, each cell is wrapped in a DIV element; if it\'s a string, the cell is wrapped in a tag of given name.');
//        $this->rowClass                 = $this->getOption('rowClass', '(optional) Class applied to each table row', 'pfy-row-*');
//        $this->cellIds                  = $this->getOption('cellIds', '(optional) If true, each cell gets an ID which is derived from the cellClass');
//        $this->nRowsReq                 = $this->getOption('nRows', '(optional) Number of rows: if set the table is forced to this number of rows');
//        $this->minRows                  = $this->getOption('minRows', '(optional) Minimum Number of rows: if set the table contains at least that many rows.');
//        $this->nColsReq                 = $this->getOption('nCols', '(optional) Number of columns: if set the table is forced to this number of columns');
//        $this->includeKeys              = $this->getOption('includeKeys', '[true|false] If true and not a .csv source: key elements will be included in data.', true);
//        $this->interactive              = $this->getOption('interactive', '[true|false] If true, module "Datatables" is activated, providing for interactive features such as sorting, searching etc.');
//        $this->liveData                 = $this->getOption('liveData', '[true|false] If true, Lizzy\'s "liveData" mechanism is activated. If the dataSource is modified on the server, changes are immediately mirrored in the webpage.');
//        $this->dataSelector             = $this->getOption('dataSelector', '(optional string) If defined and "liveData" is activated, this option defines how to access data elements from the DB. (Default: \'*,*\')', '*,*');
//        $this->targetSelector           = $this->getOption('targetSelector', '(optional string) If defined and "liveData" is activated, this option defines how to assign data elements to DOM-elements. (Default: \'[data-ref="*,*"]\')', '[data-ref="*,*"]');
//        $this->editable                 = $this->getOption('editable', '[false,true,loggedin,privileged,admins,editors] Shortcut that enables full table editing and downloading (Default: false).');
//        $this->editableBy               = $this->getOption('editableBy', '[false,true,loggedin,privileged,admins,editors] Defines who may edit data. Default: false. (only available when using option "dataSource")', null);
//        $this->editMode                 = $this->getOption('editMode', '[inline,form,permanent] Selects how table data shall be editable: either inline or via a form. By default the form pops up in an overlay, '.
//                                                                  'unless "permanent" is specified, then it\' permanently visible. (Default: form).', null);
//        $this->editFormArgs             = $this->getOption('editFormArgs', 'Arguments that will passed on to the forms-class.', false);
//        $this->editFormTemplate         = $this->getOption('editFormTemplate', 'A markdown file that will be used for rendering the form.', false);
//        $this->tableButtons             = $this->getOption('tableButtons', 'Activates a row of activity buttons related to the form. If in editMode, a "New Record" button will be added.', false);
//        $activityButtons                = $this->getOption('activityButtons', 'Synonyme for "tableButtons".', false);
//        if ($activityButtons) {
//            $this->tableButtons = $activityButtons;
//        }
//        $this->tableButtons = str_replace('new-rec', 'add-rec', $this->tableButtons); // synonyme
//
//        $this->multiline                = $this->getOption('multiline', 'If true (and inline-editing is true), table cells get class "pfy-editable-multiline" thus supporting multiline editing.', false);
//        $this->labelColons              = $this->getOption('labelColons', 'If false, trailing colon of labels in editing-forms are omitted.', true);
//        $this->rowButtons               = $this->getOption('rowButtons', '(optional comma-separated-list) Prepends a column to each row containing custom buttons. Buttons can be defined as names of icons or HTML code. E.g. "send,trash"', null);
//        $this->recViewButtonsActive     = $this->getOption('showRecViewButton', '[true|false] If true, a button to open a popup is added to each row. The popup presents the data record in form view.', false);
//        $this->paging                   = $this->getOption('paging', '[true|false] When using "Datatables": turns paging on or off (default is on)');
//        $this->initialPageLength        = $this->getOption('initialPageLength', '[int] When using "Datatables": defines the initial page length (default is 10)');
//        $this->excludeColumns           = $this->getOption('excludeColumns', '(optional) Allows to exclude specific columns, e.g. "excludeColumns:2,4-5"');
//        $this->sort                     = $this->getOption('sort', '(optional) Allows to sort the table on a given columns, e.g. "sort:3"');
//        $this->sortExcludeHeader        = $this->getOption('sortExcludeHeader', '(optional) Allows to exclude the first row from sorting');
//        //        $this->filter               = $this->getOption('filter', '(optional) Allows to filter data. Filter value is exected to be a PHP expression. Data is referenced as "[[elemKey]]", where elemKey is column-index or header-string.');
//        $this->autoConvertLinks         = $this->getOption('autoConvertLinks', '(optional) If true, all data is scanned for patterns of URL, mail address or telephone numbers. If found the value is wrapped in a &lt;a> tag');
//        $this->autoConvertTimestamps    = $this->getOption('autoConvertTimestamps', '(optional) If true, integer values that could be timestamps (= min. 10 digits) are converted to time strings.');
//        $this->caption                  = $this->getOption('caption', '(optional) If set, a caption tag is added to the table. The caption text may contain the pattern "##" which will be replaced by a number.');
//        $this->captionIndex             = $this->getOption('captionIndex', '(optional) If set, will override the automatically applied table counter');
//        $this->headers                  = $this->getOption('headers', '(optional) Column headers may be supplied in the form [A|B|C...]', true);
//        if (!$this->headers) {
//            $this->headers = $this->getOption('headersTop');   // synonyme for 'headers'
//        }
//        $this->headersLeft              = $this->getOption('headersLeft', '(optional) Row headers may be supplied in the form [A|B|C...]');
//        $this->translateHeaders         = $this->getOption('translateHeaders', '(optional) If true, header elements will be translated if definition exists.');
//        $this->showRowNumbers           = $this->getOption('showRowNumbers', '(optional) Adds a left most column showing row numbers.');
//        $this->injectSelectionCol       = $this->getOption('injectSelectionCol', '(optional) Adds a column showing row selection checkboxes.');
//        $this->hideMetaFields           = $this->getOption('hideMetaFields', '(optional) If true, system (or "meta") fields are not rendered (default: true).', true);
//        //        $this->renderAsDiv	        = $this->getOption('renderAsDiv', '(optional) If set, the table is rendered as &lt;div> tags rather than &lt;table>');
//        $this->renderAsDiv              = false; // disabled for the time being...
//        $this->tableDataAttr            = $this->getOption('tableDataAttr', '(optional) ');
//        //        $this->renderDivRows        = $this->getOption('renderDivRows', '(optional) If set, each row is wrapped in an additional &lt;div> tag. Omitting this may be useful in conjunction with CSS grid.');
//        $this->includeCellRefs          = $this->getOption('includeCellRefs', '(optional) If set, data-source and cell-coordinates are added as \'data-xy\' attributes');
//        //        $this->cellMask 	        = $this->getOption('cellMask', '(optional) Lets you define regions that a masked and thus will not get the cellClass. Selection code: rY -> row, cX -> column, eX,Y -> cell element.');
//        //        $this->cellMaskedClass      = $this->getOption('cellMaskedClass', '(optional) Class that will be applied to masked cells');
//        $this->cellMask                 = false;
//        $this->cellMaskedClass          = false;
//        $this->process                  = $this->getOption('process', '(optional) Provide name of a frontmatter variable to activate this feature. In the frontmatter area define an array containing instructions for manipulating table data. See <a href="https://getlizzy.net/macros/extensions/table/" target="_blank">Doc</a> for further details.');
//        $this->processInstructionsFile  = $this->getOption('processInstructionsFile', 'The same as \'process\' except that instructions are retrieved from a .yaml file');
//        $this->suppressError            = $this->getOption('suppressError', '(optional) Suppresses the error message in case dataSource is not available.');
//        $this->enableTooltips           = $this->getOption('enableTooltips', '(optional) Enables tooltips, e.g. for cells containing too much text. To use, apply a class-name containing "tooltip" to the targeted cell, e.g. "tooltip1".');
//        $this->export                   = $this->getOption('export', '(optional) .');
//        $this->exportMetaElements       = $this->getOption('exportMetaElements', '(optional) .');
//        $this->prefill                  = $this->getOption('prefill', '(optional) .');
//        $this->cellTooltips             = $this->getOption('cellTooltips','[true,min-chars] If set, cells with large content (>min-chars) will get a tooltip showing the content. Default min-chars: 30.');
//        if ($this->cellTooltips === true) {
//            $this->cellTooltips = 30;
//        }

        if ($this->activityButtons) {
            $this->tableButtons = $this->activityButtons;
        }
        $this->tableButtons = str_replace('new-rec', 'add-rec', $this->tableButtons); // synonyme

        if (!$this->headers) {
            $this->headers = $this->headersTop;   // synonyme for 'headers'
        }
        if ($this->cellTooltips === true) {
            $this->cellTooltips = 30;
        }
        $this->tableButtons = str_replace('new-rec', 'add-rec', $this->tableButtons); // synonyme

        $this->checkArguments();

        $this->handleDatatableOption($this->page);
        $this->handleCaption();

        $this->formEditing = false;
        $this->inlineEditing = false;

        // determine editing permission:
        // first check explicit permission 'editableBy':
        if ($this->editableBy !== null) {
            $this->editingActive = true; //???
//            $this->editingActive = checkPermission($this->editableBy, $this->pfy) ||
//                (@$GLOBALS['_SESSION']['lizzy']['debug'] && @$_SESSION['lizzy']['isLocalhost']);

        // second implicit permission for edit, delete, add:
        } else {
            if ($this->editable) {
                $this->editingActive = $this->editable = true; //???
//                $this->editingActive = $this->editable = checkPermission($this->editable, $this->pfy);

            } elseif ((strpos($this->tableButtons, 'edit') !== false) ||
                    (strpos($this->tableButtons, 'add-rec') !== false) ||
                    (strpos($this->tableButtons, 'delete-rec') !== false) ||
                    (strpos($this->rowButtons, 'edit') === false)) {
                $this->editingActive = true; //???
//                $this->editingActive = checkPermission('loggedin', $this->pfy) || isLocalhost();
            }
        }

        if ($this->showRowNumbers) {
            $this->wrapperClass = trim("$this->wrapperClass pfy-table-row-numbers");
        }

        // short-hand 'editable: permission':
        if ($this->editable) {
                $this->tableButtons = 'delete-rec|add-rec|download'; // table buttons
            $this->rowButtons = 'edit';
        }

        if (!$this->editMode === null) {
            $this->editMode = 'form';
        } elseif (strpos($this->editMode, 'permanent') !== false) {
            $this->editMode = "form,$this->editMode";
            $this->wrapperClass .= ' pfy-table-permanent-form';
        }

        if ($this->editingActive) {
            if (strpos($this->tableButtons, 'edit') !== false) {
                $this->inlineEditing = true;
                $this->editMode = 'inline';
                $this->inlineEditingActive = false;

            } elseif (($this->editMode) && ($this->editMode[0] === 'i')) {
                $this->inlineEditing = true;
                $this->inlineEditingActive = true;
                if (strpos($this->editMode, 'inactive')) {
                    $this->inlineEditingActive = false;
                }

            } elseif (($this->editMode) && ($this->editMode[0] === 'f')) {
                $this->formEditing = true;
            }
        }

        if (($this->inlineEditing) && (!$this->editingActive)) {
            $this->tableButtons = str_replace('edit', '', $this->tableButtons);
        }
        if (strpos($this->rowButtons, 'view') !== false) {
            $this->recViewButtonsActive = true;

        } elseif ($this->recViewButtonsActive) {
            $this->rowButtons = $this->rowButtons? $this->rowButtons.',view' : 'view';
        }

        if ($this->tableButtons) {
            $buttonArray = \Usility\PageFactory\explodeTrim(',|', $this->tableButtons);
            $this->tableButtons = [];
            foreach ($buttonArray as $button) {
                if (!$button) { continue; }

                // extract optinal attributes:
                $attributes = '';
                if (preg_match('/(.*?) \s* { (.*) }/x', $button, $m)) {
                    $button = $m[1];
                    $attributes = str_replace(['&#34;','&#39;'], ['"',"'"], $m[2]);
                }
                if ((strpos($button, 'add-rec') !== false) || $this->inlineEditing) {
                    if ($this->editingActive) {
                        $this->formEditing = true;
                    }
                    $this->editMode = 'form';
                }
                $this->tableButtons[ $button ] = $attributes;
            }
        }

        if ($this->multiline && $this->inlineEditing) {
            $this->cellClass = trim("$this->cellClass pfy-editable-multiline");
        }
    } // __construct



    public function render( $help = false)
    {
        if ($help) {
            return $this->helpText;
        }

        // for "active tables": load modules and set class:
        if ($this->editingActive || $this->tableButtons || $this->recViewButtonsActive) {
            $this->page->addAssets('POPUPS, TABLES, TOOLTIPSTER');
            $this->includeCellRefs = true;
            $this->tableClass .= ' pfy-active-table';
        }
        if ($this->formEditing || $this->recViewButtonsActive) {
            $this->form = $this->renderEditingForm();
        }

        $this->loadData();

        $this->applyHeaders();

        $this->injectRowButtons();
        $this->applyProcessingToData();

        $this->injectEditFormRef();
        $out = '';
        $this->convertLinks();
        $this->convertTimestamps();
        $this->export();

        $tableButtonCode = '';
        if ($this->tableButtons) {
            $tableButtonCode = $this->renderTableActionButtons();
        }

        if ($this->formEditing || $this->recViewButtonsActive) {
            $out .= $this->form;
        }
        if ($this->inlineEditing) {
            $this->srcRef = $this->activateInlineEditing();
        }

        $out .= $tableButtonCode;

        $out .= $this->renderHtmlTable() . $this->strToAppend;
        if ($this->liveData) {
            $this->activateLiveData();
        }

        if ($this->tableButtons && !$this->srcRef) {
            $this->srcRef = $this->renderSrcRef();
        }

        $out = <<<EOT
  <div class='pfy-table-wrapper $this->wrapperClass'{$this->srcRef}>
$out
  </div>
EOT;
        return $out;
    } // render



    private function renderHtmlTable()
    {
        $out = '';
        $data = &$this->data;
        if ($this->nCols === 0) {
            return '';
        }
        if ($this->nRows < 1) {
            return '{{ pfy-table-no-data-available }}';
        }

        $header = ($this->headers !== false);

        $tableClass = $this->tableClass ? $this->tableClass : "pfy-table-{$this->tableCounter} ";
        $tableClass = trim( "pfy-table $tableClass" );
        $thead = '';
        $tbody = '';
        $tfoot = '';
        if ($data) {
            $rec0 = reset($data);
            if (is_array($rec0)) {
                $nCols = sizeof(reset($data));
            }
        }
        $rowClass0 = $this->rowClass;

        $rec = end($data);
        foreach ($rec as $recKeyInx => $elem) {
            if (strpos($elem, '<{<') !== false) {
                break;
            }
        }

        $rInx = 1;
        $r = 0;
        $showRowNr = $this->showRowNumbers;
        foreach ($data as $recId => $rec) {
            if (!$rec || !is_array($rec)) {
                die("Error in renderHtmlTable(): empty data record");
            }
            if ($header && ($r === 0)) {
                $rowClass = 'pfy-hdr-row';
                if (isset($this->specificRowClasses[$r])) {
                    $rowClass .= " {$this->specificRowClasses[$r]}";
                }
                $thead = "\t<thead>\n\t\t<tr class='$rowClass'>\n";
                if ($this->showRowNumbers) {
                    $thead .= "\t\t\t<th class='pfy-table-row-nr'>{{^ pfy-table-row-nr-header }}</th>\n";
                }
                if ($this->injectSelectionCol) {
                    $thead .= "\t\t\t<th class='pfy-table-row-selector'>{{^ pfy-table-row-selector }}" .
                        "<input class='pfy-table-row-all-selector' type='checkbox' title='{{ pfy-table-select-all-rows }}'></th>\n";
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($recId, $c, 'th', true);
                    if ($cell !== null) {
                        $thead .= "\t\t\t$cell\n";
                    }
                }
                $thead .= "\t\t</tr>\n\t</thead>\n";

            } elseif ($recId === 'footer') {
                $tfoot = "\n\t<tfoot>\n\t\t<tr class='$rowClass'>\n";
                if ($this->showRowNumbers) {
                    $tfoot .= "\t\t\t<td></td>\n";
                }
                if ($this->injectSelectionCol) {
                    $tfoot .= "\t\t\t<td></td>\n";
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($recId, $c, 'td');
                    if ($cell !== null) {
                        $tfoot .= "\t\t\t$cell\n";
                    }
                }
                $tfoot .= "\t\t</tr>\n\t</tfoot>\n";

            } else {    // render row:
                if ($recId === '') {
                    $recId = 'new-rec';
                }
                $recHash = @$rec[ $recKeyInx ];
                $recKey = '';
                if (preg_match('/ <{< (.*?) ,/x', $recHash, $m)) {
                    $recKey = " data-reckey='{$m[1]}'";
                } elseif ($recHash) {
                    $recKey = " data-reckey='$recHash'";
                }
                $rowClass = str_replace('*', $rInx, $rowClass0);
                if (isset($this->specificRowClasses[$r])) {
                    $rowClass .= " {$this->specificRowClasses[$r]}";
                }
                $tbody .= "\t\t<tr class='$rowClass'$recKey>\n";
                $n = '';
                if ($showRowNr) {
                    if (strpos($rowClass, 'pfy-added-row') === false) {
                        if ($this->headers) {
                            $n = $r;
                        } else {
                            $n = $r + 1;
                        }
                        if ($n < 10) {
                            $n = "<span class='pfy-spacer'>0</span>$n";
                        }
                    }
                    $tbody .= "\t\t\t<td class='pfy-table-row-nr'>$n</td>\n";

                }
                if ($this->injectSelectionCol) {
                    $empty = !sizeof( array_filter($rec, function ($v) { return boolval($v); }) );
                    if ($empty) {
                        $tbody .= "\t\t\t<td class='pfy-table-row-selector'></td>\n";
                    } else {
                        $tbody .= "\t\t\t<td class='pfy-table-row-selector'><input class='pfy-table-row-selector' type='checkbox'></td>\n";
                    }
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $tag = (($c === 0) && $this->headersLeft)? 'th': 'td';
                    $cell = $this->getDataElem($recId, $c, $tag);
                    if ($cell !== null) {
                        $tbody .= "\t\t\t$cell\n";
                    }
                }
                $tbody .= "\t\t</tr>\n";
                $rInx++;
            }
            $r++;
        }

        $out .= <<<EOT

  <table id='{$this->id}' class='$tableClass'{$this->tableDataAttr} data-inx="$this->tableCounter">
{$this->caption}
$thead	<tbody>
$tbody	</tbody>$tfoot
  </table>

EOT;

        if ($this->injectSelectionCol) {
            $jq = <<<EOT

$('#{$this->id} .pfy-table-row-all-selector').change(function() {
    const state = $( this ).prop('checked');
    $('#{$this->id} .pfy-table-row-selector').prop('checked', state);
});

EOT;
            $this->page->addJq( $jq );
        }
        return $out;
    } // renderHtmlTable



    private function renderSrcRef()
    {
        $tickRec = [];
        $setId = "set$this->tableCounter";
        $tickRec[ $setId ]['_dataSource'] = $this->dataSource;

        $tck = new Ticketing(['defaultType' => 'htmltable', 'defaultMaxConsumptionCount' => false]);
        if ($this->ticketHash && $tck->ticketExists($this->ticketHash)) {
            $ticketHash = $tck->createHash(true);
            $tck->updateTicket($this->ticketHash, $tickRec);
        } else {
            $ticketHash = $tck->createTicket($tickRec, false, 86400);
        }
        $dataSrcRef = "$ticketHash:$setId";

        $str = " data-datasrc-ref='$dataSrcRef'";

        return $str;
    } // renderSrcRef()



    private function export()
    {
        if (!$this->export) {
            return;
        }

        $file = $this->getDownloadFilename();
        $path = dir_name( $file );
        $file = basename( $file );
        $file = str_replace(['.xlsx','.ods','.csv', '.'], '', $file) . '.';
        $path = (!$path || ($path !== '.')) ? $path : 'download/';
        preparePath( $path );
        $file = substr( $file , 0, -1);
        $file = "$path$file";

        // handle export of meta keys:
        if ($this->exportMetaElements) {
            $includeTS = ($this->exportMetaElements === true) || (strpos($this->exportMetaElements, 'time') !== false);
            $includeKey = ($this->exportMetaElements === true) ||
                (strpos($this->exportMetaElements, 'key') !== false) ||
                (strpos($this->exportMetaElements, 'hash') !== false);
            foreach ($this->data as $key => $rec) {
                if ($key === 'hdr') {
                    if ($includeTS) {
                        $this->data[$key][] = TIMESTAMP_KEY_ID;
                    }
                    if ($includeKey) {
                        $this->data[$key][] = REC_KEY_ID;
                    }
                } else {
                    if ($includeTS) {
                        $this->data[$key][] = @$this->data0[$key][TIMESTAMP_KEY_ID];
                    }
                    if ($includeKey) {
                        $this->data[$key][] = @$this->data0[$key][REC_KEY_ID];
                    }
                }
            }
        } else {
            $header = &$this->dataBare[0];
            $inx = array_search(REC_KEY_ID, $header);
            if ($inx !== false) {
                unset($header[ $inx ]);
            }
            $inx = array_search(TIMESTAMP_KEY_ID, $header);
            if ($inx !== false) {
                unset($header[ $inx ]);
            }
        }

        if (strpos($this->export, '.csv') !== false) {
            $this->exportToCsv( $file );

        } elseif (strpos($this->export, '.xlsx') !== false) {
            $this->exportToOfficeFormats( $file, '.xlsx' );

        } elseif (strpos($this->export, '.ods') !== false) {
            $this->exportToOfficeFormats( $file, '.ods' );

        } else {
            $this->exportToOfficeFormats( $file );
        }
    } // export



    private function exportToCsv( $file )
    {
        // define temporary $structure to control export by datastorage:
        $structure['key'] = 'index';
        foreach ($this->data['hdr'] as $elemKey) {
            $structure['elements'][$elemKey] = [ 'type' => 'string', 'name' => $elemKey ];
        }

        $dbExport = new DataStorage2([
            'dataSource' => $file.'.csv',
            'structureDef' => $structure,
            'includeKeys' => false,
            'includeTimestamp' => false,
        ]);

        $data = $this->dataBare;
        // remove header row (it's already handled in $structure)
        array_shift($data);
        foreach ($data as $key => $rec) {
            foreach ($rec as $k => $v) {
                $data[$key][$k] = preg_replace('/<{(.*?)}>/', '', $v);
            }
        }
        $dbExport->write( $data );
    } // exportToCsv



    private function exportToOfficeFormats( $file, $type = '.xlsx.ods' )
    {
        $fileXlsx = $fileOds = false;
        $upToDate = true;
        $ts0 = filemtime($this->dataSource);
        if (strpos($type, 'xlsx') !== false) {
            $fileXlsx = "$file.xlsx";
            $ts = @filemtime($fileXlsx);
            if ($ts0 > $ts) {
                $upToDate = false;
            }
        }
        if (strpos($type, 'ods') !== false) {
            $fileOds = "$file.ods";
            $ts = @filemtime($fileOds);
            if ($ts0 > $ts) {
                $upToDate = false;
            }
        }
        if ($upToDate) {
            return;
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $data = $this->dataBare;
        $r = 1;
        foreach ($data as $rec) {
            $c = 0;
            if (is_array($rec)) {
                foreach ($rec as $v) {
                    $cell = preg_replace('/<{(.*?)}>/', '', $v);
                    $c1 = intval($c / 26);
                    $c1 = $c1 ? chr(65 + $c1) : '';
                    $c2 = chr(65 + $c % 26);
                    $cellId = "$c1$c2$r";
                    $c++;
                    $sheet->setCellValue($cellId, $cell);
                }
            }
            $r++;
        }

        if ($fileXlsx) {
            $writer = new Xlsx($spreadsheet);
            $writer->save($fileXlsx);
        }

        if ($fileOds) {
            $writer = new Ods($spreadsheet);
            $writer->save($fileOds);
        }
    } // exportToOfficeFormats



    private function activateInlineEditing()
    {
        $file = SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
        if (!file_exists($file)) {
            die("Error: HTMLtables with activated EditableBy option requires Lizzy Extensions to be installed.");
        }
        require_once $file;

        $page = $this->page;
        require_once SYSTEM_PATH.'extensions/editable/code/editable.class.php';

        $this->pfy->trans->readTransvarsFromFile( SYSTEM_PATH.'extensions/editable/locales/vars.yaml', false, true);

        $GLOBALS['lizzy']['editableLiveDataInitialized'] = false;
        $page->addModules('EDITABLE');

        // show buttons:
        if (isset( $this->options['showButtons'] )) {
            $this->options['showButton'] = $this->options['showButtons'];
        }
        if (isset( $this->options['showButton'] )) {
            if (($this->options['showButton'] === 'auto')) {
                $this->tableClass .= " pfy-editable-show-buttons";

            } elseif ($this->options['showButton'] !== false) {
                $this->tableClass .= " pfy-editable-show-buttons";
            }
        } else {
            $this->tableClass .= " pfy-editable-auto-show-button";
        }

        if ( $this->inlineEditingActive ) {
            $this->cellClass .= " pfy-editable";
            $this->wrapperClass .= ' pfy-table-editable';
        } else {
            $this->cellClass .= " pfy-editable-inactive";
            $this->wrapperClass .= ' pfy-table-editable-inactive';
        }

        $this->edbl = new Editable( $this->pfy, [
            'dataSource' => '~/'. $this->dataSource,
            'dataSelector' => '*,*',
            'targetSelector' => $this->targetSelector,
            'output' => false,
            'init' => $this->inlineEditingActive,
            'editableBy' => $this->editableBy,
            'liveData' => $this->liveData,
            'execInitialDataUpload' => false,
        ] );

        $jq = "$('.pfy-table-editable').closest('.dataTables_wrapper').addClass('pfy-datatable-editable');\n";
        $this->page->addJq($jq);

        if ($this->liveData) {
            $this->wrapperClass .= ' pfy-table-livedata';
        }

        $out = $this->edbl->render();
        if (preg_match("/'(.*):/", $out, $m)) {
            $this->ticketHash = $m[1];
        }
        return $out;
    } // activateInlineEditing



    private function activateLiveData()
    {
        if (!@$this->edbl) {
            $file = SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
            if (!file_exists($file)) {
                die("Error: HTMLtables with activated liveData option requires Lizzy Extensions to be installed.");
            }
            require_once $file;
            $this->page->addModules('~ext/livedata/js/live_data.js');
        }

        $js = '';
        if ($this->dataTableObj) {
            foreach ($this->dataTableObj as $dataTableObj) {
                $js .= "\t$dataTableObj.draw();\n";
            }
            $js = <<<EOT

function redrawTables() {
$js}

EOT;
            $this->page->addJs($js);
        }

        $args = [
            'dataSource' => '~/'.$this->dataSource,
            'manual' => 'silent',
            'initJs' => true,
            'watchdog' => true,
            'ticketHash' => @$this->ticketHash,
        ];

        // if dataTables are active, make sure they are redrawn when new data arrives:
        if ($this->interactive) {
            $args['postUpdateCallback'] = 'redrawTables';
        }
        $this->edbl = new LiveData($this->pfy, $args);
        $this->srcRef = $this->edbl->render();
    } // activateLiveData



    private function applyHeaders()
    {
        if (!$this->headers && !$this->headersLeft) {
            return;
        }

        $data = &$this->data;

        // translate header elements:
        if ($this->translateHeaders && is_array($this->headerElems)) {
            foreach ($this->headerElems as $i => $hdr) {
                if (@$hdr[0] === '-') {
                    $hdr = substr($hdr, 1);
                }
                $this->headerElems[$i] = $this->pfy->trans->translateVariable($hdr, true);
            }
        }

        if ($this->headers === true) {
            $data = array_merge(['hdr' => $this->headerElems], $data);

        } elseif (($this->headers) && ($this->headers !== true)) {
            $headers = $this->extractList($this->headers, true);
            $headers = array_pad ( $headers , sizeof(reset( $data )) , '' );

           $data = array_merge(['hdr' => $headers], $data);
            $this->nRows = sizeof($data);
        }

        if ($this->headersLeft) {
            $headers = $this->extractList($this->headersLeft, true);
            if ($this->headers) {
                $key0 = array_keys($data)[0];
                array_splice($data[ $key0 ], 0, 0, ['']);
            }
            if ($this->headers === true) {
                $r = 1;
                $r1 = 0;
                $rEnd = $this->nRows + 1;
            } elseif (!$this->headers) {
                $r = 0;
                $r1 = 0;
                $rEnd = $this->nRows;
            } else {
                $r = 1;
                $r1 = 0;
                $rEnd = $this->nRows;
            }
            for (; $r < $rEnd; $r++) {
                if ($this->headersLeft === true) {
                    array_splice($data[$r], 0, 0, [$r1++ + 1]);
                } else {
                    array_splice($data[$r], 0, 0, [$headers[$r1++]]);
                }
            }
        }
    } // applyHeaders



    private function applyProcessingToData()
    {
        $this->loadProcessingInstructions();
        if (!$this->processingInstructions) {
            return;
        }
        foreach ($this->processingInstructions as $type => $cellInstructions) {
            if (is_int($type) && isset($cellInstructions['action'])) {
                $type = $cellInstructions['action'];
            }
            switch ($type) {
                case 'addCol':
                    $this->addCol($cellInstructions);
                    break;
                case 'removeCols':
                    $this->removeCols($cellInstructions);
                    break;
                case 'modifyCol':
                    $this->modifyCol($cellInstructions);
                    break;
                case 'addRow':
                    $this->addRow($cellInstructions);
                    break;
                case 'modifyCells':
                    $this->modifyCells($cellInstructions);
                    break;
            }
        }
    } // applyProcessingToData



    private function addCol($cellInstructions)
    {
        $data = &$this->data;
        if (!$data) {
            return;
        }
        $this->instructions = $cellInstructions;

        $newCol = $this->getArg('column'); // starting at 1
        if (!$newCol) {
            $newCol = sizeof($data)+1;
        } else {
            $newCol = min(sizeof(reset( $data ))+1, max(1, $newCol));
        }
        $_newCol = $newCol - 1;     // starting at 0
        $condition = $this->getArg('condition');
        $content = $this->getArg('content');
        $condNotEmptyExceptFirst = ($condition === 'not-empty-except-first');
        $condNotEmpty = ($condition === 'not-empty') || $condNotEmptyExceptFirst;
        $header = $this->getArg('header');
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');

        $header1 = '';
        if ($class) {
            $content .= " @@$class@@";
            $header1 = "$header @@$class@@";
        }

        foreach ($data as $i => $row) {
            $content1 = $content;
            $empty = !sizeof( array_filter($row, function ($v) { return boolval($v); }) );
            if ($condNotEmpty && $empty) {
                if ($condNotEmptyExceptFirst) {
                    $condNotEmptyExceptFirst = false;
                } else {
                    $content1 = '';
                }
            }
            if (($i === 'hdr') && $header) {
                $content1 = $header;
            }
            array_splice($data[$i], $_newCol, 0, $content1);
        }
        $this->nCols++;

        if ($phpExpr) {
            $this->applyCellInstructionsToColumn($_newCol, $phpExpr, !$header, $class);
        }
        if ($header1) {
            $key0 = array_keys( $data )[0];
            $data[ $key0 ][$_newCol] = $header1;
        }
    } // addCol



    private function addRow($cellInstructions)
    {
        $data = &$this->data;
        $this->instructions = $cellInstructions;

        $content = $this->getArg('content');
        $class = $this->getArg('class');

        $rowClass = trim($this->getArg('rowClass').' pfy-added-row');
        if ($rowClass) {
            $n = sizeof($data);
            $this->specificRowClasses[$n] = $rowClass;
        }

        $phpExpr = $this->getArg('phpExpr');

        $contents = false;
        if (is_array($content)) {
            $contents = $content;
            $content = '';
        }
        if ($class) {
            $content .= " @@$class@@";
        }

        $row = [];

        for ($_c = 0; $_c < sizeof(reset( $data )); $_c++) {
            $newCellVal = '';
            if ($this->phpExpr[$_c]) {
                $phpExpr1 = $this->precompilePhpExpr($this->phpExpr[$_c], $_c);
                try {
                    $newCellVal = eval($phpExpr1);
                } catch (Throwable $t) {
                    print_r($t);
                    exit;
                }
            } elseif ($phpExpr) {
                $phpExpr1 = $this->precompilePhpExpr($phpExpr, $_c);
                try {
                    $newCellVal = eval($phpExpr1);
                } catch (Throwable $t) {
                    print_r($t);
                    exit;
                }
            }
            if ($contents && isset($contents[$_c])) {
                $row[$_c] = $content.$contents[$_c].$newCellVal;

            } else {
                $row[$_c] = $content . $newCellVal;
            }
        }
        $data['footer'] = $row;
    } // addRow



    private function removeCols($instructions)
    {
        $data = &$this->data;
        $this->instructions = $instructions;

        $colSpec = $this->getArg('columns');
        $columns = parseNumbersetDescriptor($colSpec);
        $columns = array_reverse($columns);

        foreach ($data as $r => $row) {
            foreach ($columns as $column) {
                array_splice($row, $column-1, 1);
            }
            $data[$r] = $row;
        }
    } // removeCols



    private function modifyCol($instructions)
    {
        $data = &$this->data;
        $this->instructions = $instructions;

        $col = $this->getArg('column');
        if (!$col) {
            die("Error: modifyCol() requires 'column' argument to be set.");
        }
        $col = min(sizeof(reset( $data )), max(1, $col));
        $_col = $col - 1;     // starting at 0
        $content = $this->getArg('content');
        $header = $this->getArg('header');
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');
        $inclHead = !(isset($this->headers) && $this->headers);

        if ($content) {
            $this->applyContentToColumn($_col, $content, $inclHead);
        }
        if ($phpExpr) {
            $this->applyCellInstructionsToColumn($_col, $phpExpr, $inclHead, $class);
        }
        if ($class) {
            $this->applyClassToColumn($_col, $class, $inclHead);
        }
        if ($header) {
            $key0 = array_keys( $data )[0];
            $data[ $key0 ][$_col] = $header;
        }
    } // modifyCol



    private function modifyCells($cellInstructions)
    {
        $data = &$this->data;
        $this->instructions = $cellInstructions;

        $content = $this->getArg('content');
        if (!$header = $this->getArg('header')) {
            $header = $this->getArg('headers');
        }
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');
        $inclHead = !(isset($this->headers) && $this->headers);

        $nCols = sizeof(reset( $data ));
        $_col = (isset($this->headersLeft) && $this->headersLeft) ? 1 : 0;
        for (; $_col < $nCols; $_col++) {
            if ($content) {
                $this->applyContentToColumn($_col, $content, $inclHead);
            }
            if ($phpExpr) {
                $this->applyCellInstructionsToColumn($_col, $phpExpr, $inclHead, $class);
            }
            if ($class) {
                $this->applyClassToColumn($_col, $class, $inclHead);
            }
            if ($header && isset($header[$_col])) {
                $key0 = array_keys( $data )[0];
                $data[ $key0 ][$_col] = trim($header[$_col]);
            }
        }
    } // modifyCells



    private function applyClassToColumn($column, $class, $inclHead = false)
    {
        $c = $column;
        $data = &$this->data;
        $nCols = sizeof(reset( $data ));
        $class = $class ? " @@$class@@" : '';

        foreach ($data as $r => $row) {
            if (!$inclHead && ($r === 0)) {
                continue;
            }
            $data[$r][$c] .= $class;
        }
    } // applyClassToColumn



    private function applyContentToColumn($column, $content, $inclHead = false)
    {
        $data = &$this->data;

        foreach ($data as $r => $row) {
            if (!$inclHead && ($r === 0)) {
                continue;
            }
            if (is_array($content)) {
                $data[$r][$column] = (isset($content[$r]) ? $content[$r] : '');

            } else {
                $data[$r][$column] = $content;
            }
        }
    } // applyContentToColumn



    private function applyCellInstructionsToColumn($column, $phpExpr, $inclHead = false, $class = '')
    {
        if (!$phpExpr) {
            return;
        }
        if ($class) {
            $class = "@@$class@@";
        }
        $c = $column;
        $data = &$this->data;

        $phpExpr = $this->precompilePhpExpr($phpExpr);

        // iterate over rows and apply cell-instructions:
        foreach ($data as $r => $row) {
            if (!$inclHead && ($r === 'hdr')) {
                continue;
            }
            if ($r === 'footer') {
                continue;
            }
            try {
                $newCellVal = eval( $phpExpr );
            } catch (Throwable $t) {
                print_r($t);
                exit;
            }
            $data[$r][$c] = $newCellVal.$class;
        }
    } // applyCellInstructionsToColumn



    private function precompilePhpExpr($phpExpr, $_col = false)
    {
        if (!$phpExpr) {
            return '';
        }
        $data = &$this->data;
        $headers = reset( $data );

        $phpExpr = str_replace(['ʺ', 'ʹ'], ['"', "'"], $phpExpr);
        if (preg_match_all('/( (?<!\\\) \[\[ [^]]* ]] )/x', $phpExpr, $m)) {
            foreach ($m[1] as $cellRef) {
                $cellRef0 = $cellRef;
                $cellRef = trim(str_replace(['[[', ']]'], '', $cellRef));
                $cellVal = false;
                $ch1 = ($cellRef !== '') ? $cellRef[0] : false;

                if (($ch1 === '"') || ($ch1 === "'")) {
                    $cellRef = preg_replace('/^ [\'"]? (.*?) [\'"]? $/x', "$1", $cellRef);
                    if (($i = array_search($cellRef, $headers)) !== false) { // column name
                        $c = $i;

                    } else {
                        $cellVal = $cellRef;    // literal content
                    }
                } elseif (($cellRef === '') || ($cellRef === '0')) { // this cell
                    $c = '$c';

                } elseif (($ch1 === '-') || ($ch1 === '+')) { // relative index
                    $c = '$c + ' . $cellRef;

                } elseif (($i = array_search($cellRef, $headers)) !== false) { // column name
                    $c = $i;

                } elseif (intval($cellRef)) { // numerical index
                    $c = min(intval($cellRef) - 1, sizeof(reset($this->data)) - 1);

                } else {
                    $cellVal = $cellRef;    // literal content
                }

                $cellVal = $cellVal ? $cellVal : "{\$data[\$r][$c]}";
                $phpExpr = str_replace($cellRef0, $cellVal, $phpExpr);
            }
        }
        $phpExpr = preg_replace('/^ \\\ \[ \[/x', '[[', $phpExpr);

        if ($_col !== false) {
            $skipHdr = (isset($this->headers) && $this->headers);
            if (strpos($phpExpr, 'sum()') !== false) {
                $sum = 0;
                foreach ($data as $r => $rec) {
                    if ($skipHdr) {
                        $skipHdr = false;
                        continue;
                    }
                    $val = @$rec[$_col];
                    if (preg_match('/^([\d.]+)/', $val, $m)) {
                        $val = floatval($m[1]);
                        $sum += $val;
                    }
                }
                $phpExpr = str_replace('sum()', $sum, $phpExpr);

            }
            if (strpos($phpExpr, 'count()') !== false) {
                $count = 0;
                foreach ($data as $r => $rec) {
                    if ($skipHdr) {
                        $skipHdr = false;
                        continue;
                    }
                    $val = @$rec[$_col];
                    $val = preg_replace('/(<{<([^>]*)>}>)/', '', $val); // ignore data-ref
                    if (preg_match('/\S/', $val)) {
                        $count++;
                    }
                }
                $phpExpr = str_replace('count()', $count, $phpExpr);
            }
        }

        if (strpos($phpExpr, 'return') === false) {
            $phpExpr = "return $phpExpr;";
        }
        return $phpExpr;
    } // precompilePhpExpr



    private function getOption( $name, $helpText = '', $default = false )
    {
        $value = isset($this->options[$name]) ? $this->options[$name] : $default;

        if ($value === 'false') {
            $value = false;
        } elseif ($value === 'true') {
            $value = true;
        }
        if ($helpText) {
            $this->helpText[] = ['option' => $name, 'text' => $helpText];
        }
        return $value;
    } // getOption



    private function getArg( $name )
    {
        if (!$this->data || !is_array($this->data)) {
            return '';
        }
        if ($name === 'phpExpr') {
            $this->phpExpr = [];
            for ($c = 1; $c <= sizeof(reset($this->data)); $c++) {
                if (isset($this->instructions["phpExpr[$c]"])) {
                    $this->phpExpr[$c-1] = $this->instructions["phpExpr[$c]"];
                } else {
                    $this->phpExpr[$c-1] = false;
                }
            }
        }

        if (!isset($this->instructions[$name])) {
            if (($name === 'columns') && isset($this->instructions['column'])) {
                $name = 'column';
            } else {
                $this->errMsg .= "Argument '$name' missing\n";
                return '';
            }
        }
        $value = $this->instructions[$name];
        $value = $this->extractList($value);
        return $value;
    } // getArg



    private function getDataElem($row, $col, $tag = 'td', $hdrElem = false)
    {
        $cell = @$this->data[$row][$col];

        $class = $this->cellClass;

        if ($this->inlineEditing && strpos($cell, '<br>') !== false) {
            $class = trim("$class pfy-editable-multiline");
        }

        $col1 = $col + 1;
        if ($hdrElem) {
            $tdClass = $class ? $class . '-hdr' : 'pfy-div-table-hdr';
        } else {
            $tdClass = $class;
        }
        $tdId = '';
        $ref = '';
        if (preg_match('/(@@ ([\w\- ]*) @@)/x', $cell, $m)) { // extract tdClass
            $tdClass = trim($m[2]);
            $cell = trim(str_replace($m[1], '', $cell));
        }
        if (preg_match('/(<{<([^>]*)>}>)/', $cell, $m)) {    // extract ref
            $ref = trim($m[2]);
            $cell = trim(str_replace($m[1], '', $cell));
            $ref = " data-ref='$ref'";
        }
        if ($this->cellIds) {
            $tdId = " id='{$class}_{$col}_{$row}'";
        }
//        if ($this->cellMask && $this->cellMask[$row][$col]) {
//            $tdClass = $this->cellMaskedClass;
//        }

        $title = '';
        if ($this->enableTooltips && (strpos($tdClass, 'tooltip') !== false)) {
            $title = $cell;
            $title = preg_replace('|<br/?>|', "\n", $title);
            $title = strip_tags($title);
            $title = " title='$title'";
        }
        $tdClass = trim(str_replace('  ', ' ', "$tdClass pfy-col-$col1"));
        $tdClass = " class='$tdClass'";

        // handle option 'hide:true' from structure file:
        if (strpos($cell, '%%!off$$') !== false) {
            $cell = str_replace('%%!off$$', '', $cell);
            $tdClass .= " style='display:none;'";
        }

        if ($this->cellWrapper) {
            if (is_string( $this->cellWrapper)) {
                $cell = "<$this->cellWrapper>$cell</$this->cellWrapper>";
            } else {
                $cell = "<div>$cell</div>";
            }
        }
        $tooltip = '';
        if (!$hdrElem && $this->cellTooltips) {
            $tooltip = strip_tags($cell);
            if (strlen($tooltip) > $this->cellTooltips) {
                $tooltip = " class='tooltipster' title='$tooltip'";
            } else {
                $tooltip = '';
            }
        }
        return "<$tag$tdId$tdClass$ref$title><div$tooltip>$cell</div></$tag>";
    } // getDataElem



    private function handleCaption()
    {
        if ($this->caption) {
            if ($this->captionIndex) {
                $this->tableCounter = $this->captionIndex;
            }
            if (preg_match('/(.*)##=(\d+)(.*)/', $this->caption, $m)) {
                $this->tableCounter = intval($m[2]);
                $this->caption = $m[1] . '##' . $m[3];
            }
            $this->caption = str_replace('##', $this->tableCounter, $this->caption);

            if ($this->renderAsDiv) {
                $this->caption = "\t\t<div class='caption'>$this->caption</div>\n";
            } else {
                $this->caption = "\t\t<caption>$this->caption</caption>\n";
            }
        }
    } // handleCaption



    private function handleDatatableOption($page)
    {
        if (!$this->interactive) {
            return;
        }
        $page->addModules('DATATABLES');
        $this->tableClass = trim($this->tableClass.' pfy-datatable');
        $order = '';
        if ($this->sort) {
            $sortCols = csv_to_array($this->sort);
            $headers = reset($this->data);
            foreach ($sortCols as $sortCol) {
                $sortCol = alphaIndexToInt($sortCol, $headers) - 1;
                $order .= "[ $sortCol, 'asc' ],";
            }
            $order = rtrim($order, ',');
            $order = " 'order': [$order],";
        }
        $paging = '';
        if (!$this->paging) {
            $paging = ' paging: false,';
        }
        $pageLength = '';
        if ($this->initialPageLength) {
            $pageLength = " pageLength: {$this->initialPageLength},";
        }

        // if select-row or row-button columns exist, make them non-orderable:
        $orderable = '';
        if ($this->tableButtons) {
            $orderable .= '{ orderable: false, targets: 1 },';
        }
        if (stripos($this->tableButtons, 'delete-rec') !== false) {
            $orderable .= '{ orderable: false, targets: 0 },';
        }
        if ($orderable) {
            $orderable = "columnDefs: [$orderable],";
        }

        $dataTableObj = $this->dataTableObj[$this->tableCounter] = "pfyTable[{$this->tableCounter}]";

        // launch init code:
        $jq = <<<EOT

$dataTableObj = $('#{$this->id}').DataTable({
'language':{'search':'{{ pfy-datatables-search-button }}:', 'info': '_TOTAL_ {{ pfy-datatables-records }}'},
$order$paging$pageLength$orderable
});

EOT;
        $page->addJq($jq);
        $page->addJs("\nvar pfyTable = [];");

        if (!$this->headers) {
            $this->headers = true;
        }
        $this->tableClass .= ' display';
    } // handleDatatableOption



    private function loadData()
    {
        $this->data = false;
        if ($this->inMemoryData && is_array($this->inMemoryData)) {
            $this->data = &$this->inMemoryData;
            $this->dataSource = false;
        } elseif (is_array($this->dataSource)) {    // for backward compatibility
            $this->data = $this->dataSource;
            $this->dataSource = false;

        }

        if ($this->dataSource) {
            if (@$this->dataSource[0] === '~') {
                $this->dataSource = \Usility\PageFactory\resolvePath($this->dataSource);
            }
            if (!file_exists($this->dataSource)) {
                $this->dataSource = false;
            }
        }

        $this->loadDataFromFile();

        $this->determineHeaders();

    //        $this->applyFilter();

        $this->applySort();

        $this->insertCellAddressAttributes();
        $this->adjustTableSize();

        $this->excludeColumns();

        // copy data at this stage for later export:
        $this->dataBare = array_merge([$this->headerElems], $this->data);

        $this->nCols = sizeof(reset($this->data));
        $this->nRows = sizeof($this->data);
    } // loadData



    private function renderEditingForm()
    {
        if ($this->inMemoryData) {
            die("Error: table->editableBy not possible when data supplied in-memory. Please use argument 'dataSource'.");
        }
        $this->renderTextResources();

        $out = '';
        if (!$this->editFormRendered) {
            $this->editFormRendered = true;
            if ($this->editFormTemplate) {
                $out = $this->importForm();
            } else {
                $out = $this->renderForm();
            }
        }
        return $out;
    } // renderEditingForm



    private function renderForm()
    {
        $recStructure = $this->ds->getStructure();
        $form = new Forms($this->pfy);

        $splitChoiceElemsInDb = isset($this->options['splitChoiceElemsInDb'])? $this->options['splitChoiceElemsInDb']: false;

        // Form Head:
        $args = [
            'type' => 'form-head',
            'id' => 'pfy-edit-data-form-' . $this->tableCounter,
            'class' => 'pfy-form pfy-edit-data-form',
            'file' => '~/'.$this->dataSource,
            'ticketHash' => $this->ticketHash,
            'cancelButtonCallback' => false,
            'validate' => true,
            'labelColons' => $this->labelColons,
            'dynamicFormSupport' => true,
            'splitChoiceElemsInDb' => $splitChoiceElemsInDb,
            'lockRecWhileFormOpen' => @$this->options['lockRecWhileFormOpen'],
            'translateLabels' => true,
            'responseViaSideChannels' => true,
        ];
        if ($this->editFormArgs) {
            $args = array_merge($args, $this->editFormArgs);
        }
        $out = $form->render( $args );

        // Placeholder for rec-key:
        $out .= $form->render( [
            'type' => 'hidden',
            'name' => '_rec-key',
            'value' => '',
        ] );

        // Form Fields:
        $inx = 1;
        foreach ($recStructure['elements'] as $elemKey => $rec) {
            // ignore all data elems starting with '_':
            if (@$elemKey[0] === '_') {
                continue;
            }
            if (isset($rec['formLabel'])) {
                $label = $rec['formLabel'];
            } elseif (isset($this->headerElems[ $inx - 1 ])) {
                $label = $this->headerElems[ $inx - 1 ];
            } else {
                $label = $elemKey;
            }
            $rec['label'] = $label;
            $rec['dataKey'] = $elemKey;
            $rec['name'] = \Usility\PageFactory\translateToIdentifier($elemKey, false, true, false);
            $rec['fieldWrapperAttr'] = "data-elem-inx='$inx'";
            $out .= $form->render($rec);
            $inx++;
        }

        // Delete:
        $out .= $form->render( [
            'type' => 'checkbox',
            'wrapperId' => "pfy-edit-rec-delete-checkbox-$this->tableCounter",
            'wrapperClass' => "pfy-edit-rec-delete-checkbox",
            'class' => "pfy-edit-rec-delete-checkbox",
            'label' => 'Delete',
            'name' => '_pfy-delete-rec',
            'options' => '{{ pfy-edit-rec-delete-option }}',
        ] );

        // Form Buttons:
        $out .= $form->render( [
            'type' => 'button',
            'label' => '-pfy-edit-form-cancel | -pfy-edit-form-submit',
            'value' => 'cancel|submit',
        ] );

        // Form Tail:
        $out .= $form->render( ['type' => 'form-tail'] );
        $out = rtrim($out);
        $out = <<<EOT

  <div id='pfy-edit-rec-form-{$this->tableCounter}' class='pfy-edit-rec-form-wrapper' style='display:none'>
    <div class="pfy-edit-rec-form pfy-edit-rec-form-{$this->tableCounter}">
$out
    </div>
  </div><!-- /pfy-edit-rec-form -->

EOT;
        if ($this->prefill) {
            $prefill = json_encode($this->prefill);
            $this->page->addJs("var pfyTableFormPrefill = JSON.parse('$prefill');");
        }
        $js = <<<EOT
var pfyEditRecDeleteBtn = '{{ pfy-edit-rec-delete-btn }}';
var pfyEditFormSubmit = '{{ pfy-edit-form-submit }}';
EOT;
        $this->page->addJs( $js );

        return $out;
    } // renderForm



    private function exportForm()
    {
        $exportFile = getUrlArg('exportForm', true);
        if (!$exportFile) {
            $exportFile = "~/$this->dataSource.form-template.md";
        }

        $recStructure = $this->ds->getStructure();

        $out = "// Form-Template for $this->dataSource\n\n";

        $formArgs = '';
        if (is_array($this->editFormArgs)) {
            foreach ($this->editFormArgs as $k => $v) {
                $formArgs .= "\n	$k: '$v',";
            }
        }
        $labelColons = $this->labelColons? 'true': 'false';

        // form head:
        $out .=<<<EOT

{{ formelem(
	type: "form-head", 
	id: 'pfy-edit-data-form-#tableCounter#',
	class: 'pfy-form pfy-edit-data-form',
	file: '\~/$this->dataSource',
	cancelButtonCallback: false,
	validate: true,
	labelColons: $labelColons,$formArgs
	)
}}

EOT;
        // Placeholder for rec-key:
        $out .= <<<EOT


{{ formelem(
	type: "hidden", 
	name: '_rec-key',
	value: '',
	)
}}

EOT;

        // Form Fields:
        foreach ($recStructure['elements'] as $elemKey => $rec) {

            // ignore all data elems starting with '_':
            if (@$elemKey[0] === '_') {
                continue;
            }
            $elems = '';
            foreach ($rec as $k => $v) {
                $elems .= "	$k: '$v',\n";
            }
            $label = isset($rec['formLabel']) ? $rec['formLabel'] : $elemKey;
            $out .= <<<EOT


{{ formelem(
	label: '$label',
$elems	dataKey: $elemKey,	
	)
}}

EOT;
        }


        // Delete:
        $out .= <<<EOT


{{ formelem(
	'type': 'checkbox',
	'wrapperId': "pfy-edit-rec-delete-checkbox-$this->tableCounter",
	'wrapperClass': "pfy-edit-rec-delete-checkbox",
	'class': "pfy-edit-rec-delete-checkbox",
	'label': 'Delete',
	'name': '_pfy-delete-rec',
	'options': '{{ pfy-edit-rec-delete-option }}',
	)
}}

EOT;


        // Form Buttons:
        $out .= <<<EOT


{{ formelem(
	'type': 'button',
	'label': '-pfy-edit-form-cancel | -pfy-edit-form-submit',
	'value': 'cancel|submit',
	)
}}

EOT;

        // Form Tail:
        $out .= <<<EOT


{{ formelem( type: 'form-tail' ) }}

EOT;

        $writtenTo = '';
        if ($exportFile) {
            $exportFile = resolvePath($exportFile, true);
            file_put_contents($exportFile, $out);
            $this->page->addPopup("Form-Template written to '$exportFile'.");
            $writtenTo = "<p><br><em>Written to '$exportFile'</em></p>";
        }

        $out = str_replace(['{{','<'], ['&#123;{','&lt;'], $out);
        $this->page->addOverlay("<pre id='pfy-form-export'>$out</pre>$writtenTo");
        $this->page->addJq("$('#pfy-form-export').selText();");
    } // exportForm



    private function importForm()
    {
        if (is_string( $this->editFormTemplate )) {
            $file = $this->editFormTemplate;
        } else {
            $file = DEFAULT_EDIT_FORM_TEMPLATE_FILE;
        }
        $file = resolvePath($file, true);
        $out = getFile( $file );
        if ($out) {
            $out = removeCStyleComments( $out );
            $out = str_replace(['#file#','#tableCounter#'], ['~/'.$this->dataSource, $this->tableCounter], $out);
            $md = new LizzyMarkdown( $this->pfy );
            $out = $md->compileStr($out);
            $out = $this->pfy->trans->translate( $out );
            $out = <<<EOT

<div class='pfy-edit-rec-form-wrapper' style='display:none'>
    <div class='pfy-edit-rec-form pfy-edit-rec-form-{$this->tableCounter}'>
$out
    </div>
</div><!-- /pfy-edit-rec-form -->


EOT;
            return $out;
        } else {
            die("Error in htmltable.class.php::renderForm() file '$file' not found.");
        }
    } // importForm



    private function injectRowButtons()
    {
        if (!$this->rowButtons || !is_string( $this->rowButtons )) {
            return;
        }
        $this->rowButtons = str_replace([' ','|'], ['',','], $this->rowButtons);
        $rowButtons = ",$this->rowButtons,";

        if (strpos(",$rowButtons,", ',view,') !== false) {
            $this->tableClass .= ' pfy-rec-preview';
        }

        $cellContent = '';
        $customButtons = \Usility\PageFactory\explodeTrim(',', $this->rowButtons);
        foreach ($customButtons as $name) {
            if (!$name) { continue; }
            if ($name === 'view') {
                $icon = PFY_TABLE_SHOW_REC_ICON;
            } else {
                $icon = "<span class='pfy-icon pfy-icon-$name'></span>";
            }
            if (strpos($name, '<') !== false) {
                $cellContent .= $name;
            } else {
                $cellContent .= "\n\t\t\t\t<button class='pfy-table-control-btn pfy-table-$name-btn' title='{{ pfy-table-$name-btn-title }}'>$icon</span></button>";
            }
        }

        $cellInstructions = [
            'column' => 1,
            'header' => '{{^ pfy-table-custom-row-header }}&nbsp;',
            'content' => $cellContent,
            'condition' => 'not-empty-except-first',
            'class' => 'pfy-table-btn-col',
        ];
        $this->addCol($cellInstructions);
    } // injectRowButtons



    private function loadProcessingInstructions()
    {
        if ($this->process && isset($this->page->frontmatter[$this->process])) {
            $this->processingInstructions = $this->page->frontmatter[$this->process];
        } elseif ($this->processInstructionsFile) {
            $file = resolvePath($this->processInstructionsFile, true);
            $this->processingInstructions = getYamlFile($file);
        } else {
            $this->processingInstructions = false;
        }
    } // loadProcessingInstructions



    private function checkArguments()
    {
        if (!$this->id) {
            $this->id = 'pfy-table-' . $this->tableCounter;
        }
        if ($this->tableDataAttr) {
            list($name, $value) = \Usility\PageFactory\explodeTrim('=', $this->tableDataAttr);
            if (strpos($name, 'data-') !== 0) {
                $name = "data-$name";
            }
            $value = str_replace(['"', "'"], '', $value);
            $this->tableDataAttr = " $name='$value'";
        }
    } // checkArguments



    private function extractList($value, $bracketsOptional = false)
    {
        if (!$value || is_array($value)) {
            return $value;
        }
        if (is_string($value) && ($bracketsOptional && ($value[0] !== '['))) {
            $value = "[$value]";
        }
        if (is_string($value) && preg_match('/^(?<!\\\) \[ (?!\[) (.*) ] $/x', "$value", $m)) {
            $value = $m[1];
            $ch1 = isset($value[1]) ? $value[1] : '';
            if (!($ch1 === ',') && !($ch1 === '|')) {
                $ch1  = false;
                $comma = substr_count($value, ',');
                $bar = substr_count($value, '|');
            }
            if ($ch1 || $comma || $bar) {
                if ($ch1) {
                    $value = explode($ch1, $value);
                } elseif ($comma > $bar) {
                    $value = explode(',', $value);
                } else {
                    $value = explode('|', $value);
                }
            }
        }
        if (is_array($value)) {
            foreach ($value as $i => $val) {
                $value[$i] = preg_replace('/^ \\\ \[/x', '[', $val);
            }
        }
        return $value;
    } // extractList



    private function adjustTableSize()
    {
        $data = &$this->data;
        if (!isset($data) || !$data) {
            $data['new-rec'] = [];
        }

        $nColsReq = $this->nColsReq;
        $nCols = $data? sizeof( reset($data)) : 0;
        $nRows = $this->nRowsReq ? $this->nRowsReq : sizeof($data);
        if ($this->minRows) {
            $nRows = max(intval($this->minRows), $nRows);
        }

        if ($nColsReq) {
            if ($nColsReq > $nCols) { // increase size
                foreach ($data as $key => $rec) {
                    $data[$key] = array_pad($rec, $nColsReq, '');
                }
                if (is_array($this->headerElems)) {
                    $this->headerElems = array_pad($this->headerElems, $nColsReq, '');
                }
            } elseif ($nColsReq < $nCols) { // reduce size
                foreach ($data as $key => $rec) {
                    $data[$key] = array_slice($rec, 0, $nColsReq);
                }
                if (is_array($this->headerElems)) {
                    $this->headerElems = array_slice($this->headerElems, 0, $nColsReq);
                }
            }
        }

        if ($nRows < sizeof($data)) { // reduce size
            $data = array_slice($data, 0, $nRows);

        } elseif ($nRows > sizeof($data)) { // increase size
            $emptyRow = array_pad([], $nCols, '');
            $data = array_pad($data, $nRows, $emptyRow);
        }
    } // adjustTableSize



    private function applySort()
    {
        if ($this->sort) {
            $sortInx = $this->sort;
            if (is_numeric($sortInx)) {
                $sortInx = intval($sortInx) - 1;
            } elseif (is_array($this->headerElems)) {
                $sortInx = array_search($sortInx, $this->headerElems);
            }
            if ($sortInx === false) {
                return;
            }

            $data = &$this->data;
            if ($data && ($sortInx >= 0) && ($sortInx < sizeof($data))) {
                if ($this->sortExcludeHeader) {
                    $row0 = array_shift($data);
                }
                $columns = array_column($data, $sortInx);
                array_multisort($columns, SORT_ASC, $data);
                if ($this->sortExcludeHeader) {
                    array_unshift($data, $row0);
                }
            }
        }
    } // applySort



    //    private function applyFilter()
    //    {
    //        if (!$this->filter) {
    //            return;
    //        }
    //        $phpExpr = $this->filter;
    //        if (preg_match_all('/ \[\[ (.*?) ]] /x', $phpExpr, $m)) {
    //            foreach ($m[1] as $i => $value) {
    //                if (is_numeric($value)) {
    //                    $inx = intval($value) - 1;
    //                } else {
    //                    $inx = array_search($value, $this->headerElems);
    //                    if ($inx === false) {
    //                        continue;
    //                    }
    //                }
    //                $phpExpr = str_replace($m[0][$i], '@$'."rec[$inx]", $phpExpr);
    //            }
    //        }
    //
    //        $out = [];
    //        foreach ($this->data as $r => $rec) {
    //            try {
    //                $res = eval( "return $phpExpr;" );
    //            } catch (Throwable $t) {
    //                print_r($t);
    //                exit;
    //            }
    //            if ($res) {
    //                $out[] = $rec;
    //            }
    //        }
    //        $this->data = $out;
    //    } // applyFilter



    private function insertCellAddressAttributes()
    {
        if (!$this->data0) {
            return;
        }
        if ($this->liveData) {
            $this->includeCellRefs = true;
        }
        $nCols = sizeof( reset($this->data) );
        $recKeyInx = array_search(REC_KEY_ID, $this->tblStruct);
        if ($this->includeCellRefs) {
            $r = 0;
            foreach ($this->data as $rKey => $rec) {
                $recKey = @$rec[ $recKeyInx ];
                $ic = 0;
                for ($c = 0; $c < $nCols; $c++) {
                    if ($this->includeKeys && (@$this->headerElems[$c] === REC_KEY_ID)) {
                        if (!isset($this->data[$rKey][$c])) {
                            $this->data[$rKey][$c] = "<{<$r,#>}>";
                        } else {
                            $this->data[$rKey][$c] .= "<{<$r,#>}>";
                        }
                    } else {
                        if (!isset($this->data[$rKey][$c])) {
                            $this->data[$rKey][$c] = "<{<$recKey,$ic>}>";
                        } else {
                            $this->data[$rKey][$c] .= "<{<$recKey,$ic>}>";
                        }
                        $ic++;
                    }
                }
                $r++;
            }
        }
    } // insertCellAddressAttributes



    private function excludeColumns( $data = null )
    {
        if (!$this->data0) {
            return;
        }
        if (!$this->excludeColumns && !$this->hideMetaFields) {
            return;
        }
        if ($data === null) {
            $data = &$this->data;
        }

        // parse excludeColumns directive, e.g. '1,3-5':
        $exclColumns = \Usility\PageFactory\explodeTrim(',', $this->excludeColumns);
        $colsToExclude = [];
        if ($exclColumns) {
            foreach ($exclColumns as $descr) {
                $descr = str_replace(' ', '', $descr);
                if (preg_match('/(.*)-(.*)/', $descr, $m)) {
                    $c = $m[1] ? intval($m[1]) - 1 : 0;
                    $to = $m[2] ? intval($m[2]) : 9999;
                    for ($i = $c; $i < $to; $i++) {
                        $colsToExclude[] = $i;
                    }
                } else {
                    $c = intval($descr) - 1;
                    $colsToExclude[] = $c;
                }
            }
        }

        // add meta-fields to excludes if requested:
        if ($this->hideMetaFields) {
            if ($this->headerElems) {
                $keys = $this->headerElems;
            } else {
                $keys = array_keys($this->structure['elements']);
            }
            $keyInx = array_search(REC_KEY_ID, $keys);
            if (!in_array($keyInx, $colsToExclude)) {
                $colsToExclude[] = $keyInx;
            }
            $tsInx = array_search(TIMESTAMP_KEY_ID, $keys);
            if (!in_array($tsInx, $colsToExclude)) {
                $colsToExclude[] = $tsInx;
            }
        }
        // delete columns in reverse order:
        rsort($colsToExclude);
        foreach ($colsToExclude as $c) {
            if ($this->headers === true) {
                array_splice($this->headerElems, $c, 1);
            }
            foreach ($data as $r => $row) {
                array_splice($data[$r], $c, 1);
            }
        }
    } // excludeColumns



    private function convertLinks()
    {
        if ($this->autoConvertLinks) {
            $data = &$this->data;
            foreach ($data as $r => $row) {
                foreach ($data[$r] as $c => $col) {
                    $d = trim($data[$r][$c]);

                    // email address:
                    if (preg_match_all('/ ([\w\-.]*?) @ ([\w\-.]*?\.\w{2,6}) /x', $d, $m)) {
                        foreach ($m[0] as $addr) {
                            $d = str_replace($addr, "<a href='mailto:$addr'>$addr</a>@@pfy-td-email@@", $d);
                        }

                    // phone number:
                    } elseif (preg_match('/^( \+? [\d\-\s()]* )$/x', $d, $m)) {
                        $tel = preg_replace('/[^\d+]/', '', $d);
                        if (strlen($tel) > 7) {
                            $d = "<a href='tel:$tel'>$d</a>@@pfy-td-tel@@";
                        }

                    // url:
                    } elseif (preg_match('|^( (https?://)? ([\w\-.]+ \. [\w\-]{1,6}))$|xi', $d, $m)) {
                        if (!$m[2]) {
                            $url = "https://".$m[3];
                        } else {
                            $url = $m[1];
                        }
                        if (strlen($url) > 7) {
                            $d = "<a href='$url'>$d</a>@@pfy-td-url@@";
                        }

                    // image:
                    } elseif (preg_match('/ img: (([\w\-~\/]+) \. (jpg|jpeg|png|gif)) /ix', $d, $m)) {
                        if (strlen($m[1]) > 7) {
                            $img = "{$m[2]}[x48].{$m[3]}";
                            $d = "{{ img( src:'$img') }}@@pfy-td-img@@";
                        }
                    }
                    $data[$r][$c] = $d;
                }
            }
        }
    } // convertLinks



    private function convertTimestamps()
    {
        if (!$this->autoConvertTimestamps) {
            return;
        }
        $data = &$this->data;

        $autoConvertTimestamps = $this->autoConvertTimestamps;
        if ($autoConvertTimestamps === true) {
            foreach ($data as $r => $row) {
                foreach ($data[$r] as $c => $col) {
                    $d = trim($data[$r][$c]);
                    if (preg_match('/^\d{9,}$/', $d)) {
                        $d = date('Y-m-d H:i:s', intval($d));
                        $data[$r][$c] = $d;
                    }
                }
            }

        } elseif (is_int($autoConvertTimestamps)) {
            $cInx = $autoConvertTimestamps;
            foreach ($data as $r => $row) {
                $d = trim($data[$r][$cInx]);
                if (preg_match('/^\d{9,}$/', $d)) {
                    $d = date('Y-m-d H:i:s', intval($d));
                    $data[$r][$cInx] = $d;
                }
            }

        } elseif (is_string($autoConvertTimestamps)) {
            $cInx = false;
            $rec0 = reset($data);
            foreach ($rec0 as $c => $col) {
                if ($col === $autoConvertTimestamps) {
                    $cInx = $c;
                    break;
                }
            }
            if ($cInx !== false) {
                foreach ($data as $r => $row) {
                    $d = trim($data[$r][$cInx]);
                    if (preg_match('/^\d{9,}$/', $d)) {
                        $d = date('Y-m-d H:i:s', intval($d));
                        $data[$r][$cInx] = $d;
                    }
                }
            }
        }
    } // convertTimestamps



    private function loadDataFromFile()
    {
        if (!$this->dataSource) {
            $this->data0 = [];
            $this->data = [];
            return;
        }

        $this->options['includeKeys'] = $this->includeKeys;
        if ($this->editableBy) {
            $this->options['includeTimestamp'] = true;
            $this->options['includeKeys'] = true;
        }
        $this->options['includeTimestamp'] = $this->options['includeKeys'];
        $ds = new DataStorage($this->options, $this->pfy);
        $this->ds = $ds;

        if ($this->preselectData) {
            $data0 = $ds->readElement( $this->preselectData );
            $rec0 = reset( $data0 );
            $descrs = [];
            foreach ($rec0 as $k => $item) {
                $descrs[$k] = ['type' => 'string', 'name' => $k, 'formLabel' => $k];
            }
            $this->structure = $structure = [
                'key' => 'index',
                'elements' => $descrs,
            ];
        } else {
            $data0 = $ds->read();
            $this->structure = $structure = $ds->getStructure();
        }

        if ($this->includeKeys === 'hash') {
            $found = false;
            foreach ($data0 as $key => $rec) {
                if (!isset($rec[REC_KEY_ID]) || !preg_match('/^[A-Z0-9]{4,20}$/', $rec[REC_KEY_ID])) {
                    $rec[REC_KEY_ID] = createHash();
                    $data0[$key] = $rec;
                    $found = true;
                }
            }
            if ($found) {
                $ds->write($data0);
            }
        }
        $this->data0 = $data0;

        // if structure is known -> apply structure-info:
        $fields = [];
        if (isset($structure['key'])) {
            // special case: recKey is linked to a rec-element -> defined as "key => '=fieldname'":
            if (is_string($structure['key']) && $structure['key'] && $structure['key'][0] === '=') {
                $keyKey = substr($structure['key'], 1);
                foreach ($data0 as $k => $rec) {
                    $data0[$k][$keyKey] = $k;
                }
            }

            $fields = $structure['elements'];
            if ($this->headers) {
                $this->getHeaders($fields, $data0);
            }
            $this->applyHideOmitColumns($fields); //??? -> before getHeaders?
        }

        $data = $this->prepareData($data0, $fields);

        if (!$data && $this->structure) {
            // add empty row:
            foreach (array_keys($structure['elements']) as $c => $label) {
                $data['new-rec'][$c] = '';
            }
        }

        $this->data = $data;
    } // loadDataFromFile



    private function getHeaders($elements, $data0)
    {
        if ($this->headers === true) {
            $elemKeys = array_keys($elements);
            if (isset($elemKeys[0]) && is_int($elemKeys[0])) {
                $this->headerElems = array_shift($data0);
            } else {
                $this->headerElems = $elemKeys;
            }
            $rec0 = reset($data0);
            $ic = 0;
            if ($rec0) {
                foreach ($rec0 as $k => $item) {
                    if (is_array($item)) {
                        // handle splitOutput of composite elements if directive is embedded in field description:
                        $splitOutput = @$this->structure['elements'][$k]['splitOutput'] || @$item['splitOutput'];
                        if ($splitOutput) {
                            foreach ($item as $k => $v) {
                                if (($k === 0) || ($k[0] === '_')) { // skip special elems
                                    unset($item[$k]);
                                }
                            }
                            $newCols = array_keys($item);
                            array_splice($this->headerElems, $ic, 1, $newCols);
                            $ic += sizeof($newCols) - 1;
                        }
                    }
                    $ic++;
                }
            }
        } elseif (is_string($this->headers)) {
            $this->headerElems = \Usility\PageFactory\explodeTrim(',|', $this->headers);
        } else {
            die("Error in getHeaders(): unknown type of argument 'headers'.");
        }
        if ($this->includeKeys) {
            if (!in_array(REC_KEY_ID, $this->headerElems)) {
                $this->headerElems[] = REC_KEY_ID;
            }
            if (!in_array(TIMESTAMP_KEY_ID, $this->headerElems)) {
                $this->headerElems[] = TIMESTAMP_KEY_ID;
            }
        }
    } // getHeaders



    private function applyHideOmitColumns($fields)
    {
        $i = 0;
        foreach ($fields as $desc) {
            // handle option 'omit:true' from structure file:
            if (@$desc['omit']) {
                unset($this->headerElems[$i]);
            }
            // handle option 'hide:true' from structure file:
            if (@$desc['hide']) {
                $this->headerElems[$i] .= '%%!off$$';
            }
            $i++;
        }
    } // applyHideOmitColumns



    private function prepareData($data0, $fields)
    {
        $data = [];
        $this->data = [];
        $ir = 0;
        foreach ($data0 as $r => $rec) {
            $this->tblStruct = [];
            $ic = 0;
            // generally ignore all data keys starting with '_':
            if (!is_array($rec) || (@$r[0] === '_')) {
                continue;
            }
            foreach ($fields as $c => $desc) {

                // handle option 'omit:true' from structure file:
                if (@$desc['omit']) {
                    continue;
                }
                $item = isset($rec[$c]) ? $rec[$c] : (isset($rec[$ic]) ? $rec[$ic] : '');
                if (is_array($item)) {
                    // handle splitOutput of composite elements if directive is embedded in field description:
                    $splitOutput = @$this->structure['elements'][$c]['splitOutput'] || @$item['splitOutput'];
                    if ($splitOutput) {
                        foreach ($item as $k => $v) {
                            if (($k === 0) || ($k[0] === '_')) {
                                continue;
                            }
                            $data[$r][$ic++] = $v ? 1 : 0;
                            $this->tblStruct[] = $k;
                        }
                        continue;

                    } else {
                        if (isset($item[0])) {
                            $item = $item[0];
                        } else {
                            $item = '<span class="pfy-array-elem">' . implode('</span><span class="pfy-array-elem">', $item) . '</span>';
                        }
                    }
                }
                if (@$desc['type'] === 'bool') {
                    $item = $item ? 'pfy-value-true' : 'pfy-value-false';
                    $item = $this->pfy->trans->translateVariable($item, true);
                } elseif (@$desc['type'] === 'password') {
                    $item = $item ? PASSWORD_PLACEHOLDER : '';
                } else {
                    $item = trim($item, '"\'');
                    $item = str_replace("\n", '<br>', $item);
                }
                $item = trim($item, '"\'');

                // handle option 'hide:true' from structure file:
                if (@$desc['hide']) {
                    $item .= '%%!off$$';
                }
                $data[$r][$ic++] = $item;
                $this->tblStruct[] = $c;
            }
            $ir++;
        }
        return $data;
    } // prepareData



    private function renderTableActionButtons()
    {
        $out = $buttons = $class = '';
        $this->jq = '';

        if ($this->tableButtons) {
            foreach ($this->tableButtons as $button => $attributes) {
                // Handle special button names "add-rec" and "delete-rec":
                if (strcasecmp($button, 'add-rec') === 0) {
                    if ($this->editingActive) {
                        $this->formEditing = true;
                        list($button, $class, $attributes) = $this->appendNewRecButton($attributes);
                    } else {
                        $button = false;
                    }

                } elseif (strcasecmp($button, 'delete-rec') === 0) {
                    if ($this->editingActive) {
                        list($button, $class, $attributes) = $this->appendDeleteButton($attributes);
                    } else {
                        $button = false;
                    }

                } elseif (strcasecmp($button, 'edit') === 0) {
                    if ($this->editingActive) {
                        list($button, $class, $attributes) = $this->appendEditButton($attributes);
                    }

                } elseif (stripos($button, 'download') === 0) {
                    $this->export = true;
                    list($button, $class, $attributes) = $this->appendDownloadButton($attributes);
                    $this->export();

                } else {
                    $class = translateToClassName($button);
                }

                if ($button) {
                    $buttons .= <<<EOT
    <button id='$class-{$this->id}' class='pfy-button pfy-button-lean $class' $attributes type="button"><span class="pfy-table-activity-btn">{{ $button }}</span></button>

EOT;
                }
            }
        }

        $out .= <<<EOT
    
  <div class="pfy-table-action-btns">
$buttons  </div>

EOT;
            $this->page->addJq($this->jq);
            return $out;
    } // renderTableActionButtons



    private function renderTextResources()
    {
        if (!@$this->textResourcesRendered) {
            $this->strToAppend = <<<EOT

    <div style='display:none;'> <!-- text resources: -->
        <div id="pfy-edit-form-rec">{{ pfy-edit-user-form-header }}</div>
        <div id="pfy-edit-form-new-rec">{{ pfy-edit-new-user-form-header }}</div>
        <div id="pfy-edit-form-submit">{{ pfy-edit-form-submit }}</div>
        <div id="pfy-edit-form-close">{{ pfy-edit-form-close }}</div>
        <div id="pfy-recview-header">{{ pfy-recview-header }}</div>
    </div>

EOT;
            $this->textResourcesRendered = true;
        }
    } // renderTextResources



    private function appendNewRecButton($attributes)
    {
        $button = 'pfy-table-new-rec-btn';
        $this->jq .= <<<EOT

$('.pfy-table-new-rec-btn').click(function() {
    mylog('add rec');
    const \$tableWrapper = $(this).closest('.pfy-table-wrapper');
    const \$table = $('.pfy-table', \$tableWrapper);
    const tableInx = \$table.data('inx');
    pfyActiveTables[tableInx].openFormPopup( \$table );
    return;
});
EOT;
        $class = 'pfy-table-new-rec-btn';
        $attributes = "$attributes title='{{ pfy-table-new-rec-title }}'";
        return [$button, $class, $attributes];
    } // appendNewRecButton



    private function appendDeleteButton($attributes)
    {
        $this->injectSelectionCol = true;
        $button = 'pfy-table-delete-rec-btn';
        $this->jq .= <<<'EOT'
$('.pfy-table-trash-btn, .pfy-table-delete-rec-btn').click(function() {
    const $table = $('.pfy-table', $(this).closest('.pfy-table-wrapper'));
    const ds = $table.closest('[data-datasrc-ref]').attr('data-datasrc-ref');
    if (typeof ds === 'undefined') {
        pfyPopup('Error: "data-datasrc-ref" is not defined');
        return;
    }
    var recs = '';
    $('.pfy-table-row-selector:checked', $table).each(function() {
        const recKey = $(this).closest('tr').attr('data-reckey');
        mylog('Delete: ' + recKey, false);
        recs += recKey + ',';
    });
    recs = recs.slice(0, -1);
    pfyConfirm('{{ pfy-table-delete-recs-popup }}'). then(function() {
        execAjaxPromise('del-rec', {ds: ds, recKeys: recs})
        .then(function() {
            pfyReload();
        });
    });
});

EOT;
        $class = 'pfy-table-delete-rec-btn';
        $attributes = "$attributes title='{{ pfy-table-delete-rec-title }}'";
        return [$button, $class, $attributes];
    } // appendDeleteButton



    private function appendEditButton($attributes)
    {
        $this->jq .= <<<EOT

$('.pfy-table-edit-btn').click(function() {
    mylog('activating inline editing table');
    const \$tableWrapper = $(this).closest('.pfy-table-wrapper');
    const \$table = $('.pfy-table', \$tableWrapper);
    const tableInx = \$table.data('inx');
    let \$this = $(this);
    if (\$this.hasClass('pfy-button-active')) {
        \$this.removeClass('pfy-button-active').attr('aria-pressed', 'false');
        pfyActiveTables[ tableInx ].activateInlineEditing( this );
    } else {
        \$this.addClass('pfy-button-active').attr('aria-pressed', 'true');
        pfyActiveTables[ tableInx ].activateInlineEditing( this );
    }  
});
EOT;
        $button = 'pfy-table-edit-btn';
        $class = 'pfy-table-edit-btn';
        $attributes = "$attributes title='{{ pfy-table-edit-title }}'";
        return [$button, $class, $attributes];
    } // appendEditButton



    private function appendDownloadButton( $attributes )
    {
        $file = $this->getDownloadFilename();
        $button = 'pfy-table-download-btn';
        $appRoot = $GLOBALS['lizzy']['appRoot'];
        $jq = <<<EOT

$('.pfy-table-download-btn').click(function() {
    mylog('open table download popup', false);
    const \$tableWrapper = $(this).closest('.pfy-table-wrapper');
    const \$table = $('.pfy-table', \$tableWrapper);
    const tableInx = \$table.data('inx');
    const popup = '<p>{{ pfy-table-download-text }}</p><ul><li>{{^ pfy-table-download-prefix }}'
        +'<a href="$appRoot{$file}xlsx" download target="_blank">{{ pfy-table-download-excel }}</a>'
        +'{{^ pfy-table-download-postfix }}</li><li>{{^ pfy-table-download-prefix }}'
        +'<a href="$appRoot{$file}ods" download target="_blank">{{ pfy-table-download-ods }}</a>'
        +'{{^ pfy-table-download-postfix }}</li></ul>';
    pfyPopup({
        text: popup,
        header: '{{ pfy-table-download-header }}',
        wrapperClass: 'pfy-table-download',
    });
});
EOT;
        $this->jq .= $this->pfy->trans->translate($jq);
        $class = 'pfy-table-download-btn';
        $attributes = "$attributes title='{{ pfy-table-download-title }}'";
        return [$button, $class, $attributes];
    } // appendDownloadButton



    private function determineHeaders(): void
    {
        if (!@$this->headerElems) {
            $this->headerElems = null;
            if ($this->data && ($this->headers === true)) {
                $this->headerElems = array_shift($this->data);
            } elseif (is_string($this->headers) && $this->headers) {
                $this->headerElems = \Usility\PageFactory\explodeTrim(',|', $this->headers);
            }
        }
        if ($this->data) {
            $this->nCols = sizeof( reset($this->data) );
            $this->nRows = sizeof( $this->data );
        } else {
            $this->nCols = 0;
            $this->nRows = 0;
        }
    } // determineHeaders



    private function injectEditFormRef(): void
    {
        // for "active tables": create ticket and set data-field:
        if ($this->formEditing || $this->inlineEditing || $this->tableButtons || $this->recViewButtonsActive) {
            $this->page->addModules('MD5');
            $this->tableDataAttr .= " data-form-id='#pfy-edit-data-form-{$this->tableCounter}'";

            // utility feature to export form template based on data structure:
            if ($this->pfy->localHost && (getUrlArg('exportForm'))) {
                $this->exportForm();
            }
        }
    } // injectEditFormRef



    private function getDownloadFilename()
    {
        $dlCodeFile = resolvePath('~page/'.DOWNLOAD_PATH_LINK_CODE);
        if (file_exists($dlCodeFile)) {
            $dlHash = file_get_contents($dlCodeFile);
        } else {
            $dlHash = createHash(8, false, true);
            file_put_contents($dlCodeFile, $dlHash);
        }
        $ts = filemtime($this->dataSource);
        $ts = date('Ymd_Hi_', $ts);
        if ($this->export === true) {
            $file = "download/$dlHash/$ts".base_name($this->dataSource, false).'.';
        } else {
            $file = "download/$dlHash/$ts".$this->export.'.';
        }
        $dlLinkFile = fileExt($dlCodeFile, true).'.link';
        file_put_contents($dlLinkFile, $file);
        return $file;
    } // getDownloadFilename
} // HtmlTable
