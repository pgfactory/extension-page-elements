<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'dataSource' => ['(optional if nCols is set) Name of file containing data. Format may be .cvs or .yaml and is expected be local to page folder.', false],
        'preselectData' => ['If set, pre-selects data from dataSource before rendering (only makes sense for higher dimensional data).', false],
        'data' => ['Alternative to "dataSource": provide data directly as array. E.g. data: $array,', false],
        'id' => ['(optional) Id applied to the table tag (resp. wrapping div tag if renderAsDiv is set)', false],
        'wrapperClass' => ['(optional) Class applied to the DIV around the table tag (resp. wrapping div tag if renderAsDiv is set). (Default: "lzy-table-default" which applies default styling).',   'lzy-table-default'],
        'tableClass' => ['(optional) Class applied to the table tag (resp. wrapping div tag if renderAsDiv is set). Use "lzy-table-default" to apply default styling.', false],
        'class' => ['Synonyme for tableClass', 'lzy-table'],
        'cellClass' => ['(optional) Class applied to each table cell', false],
        'cellWrapper' => ['(optional) If true, each cell is wrapped in a DIV element; if it\'s a string, the cell is wrapped in a tag of given name.', false],
        'rowClass' => ['(optional) Class applied to each table row',  'lzy-row-*'],
        'cellIds' => ['(optional) If true, each cell gets an ID which is derived from the cellClass', false],
        'nRows' => ['(optional) Number of rows: if set the table is forced to this number of rows', false],
        'minRows' => ['(optional) Minimum Number of rows: if set the table contains at least that many rows.', false],
        'nCols' => ['(optional) Number of columns: if set the table is forced to this number of columns', false],
        'includeKeys' => ['[true|false] If true and not a .csv source: key elements will be included in data.', true],
        'interactive' => ['[true|false] If true, module "Datatables" is activated, providing for interactive features such as sorting, searching etc.', false],
        'liveData' => ['[true|false] If true, Lizzy\'s "liveData" mechanism is activated. If the dataSource is modified on the server, changes are immediately mirrored in the webpage.', false],
        'dataSelector' => ['(optional string) If defined and "liveData" is activated, this option defines how to access data elements from the DB. (Default: \'*,*\')',  '*,*', false],
        'targetSelector' => ['(optional string) If defined and "liveData" is activated, this option defines how to assign data elements to DOM-elements. (Default: \'[data-ref="*,*"]\')',  '[data-ref="*,*"]', false],
        'editable' => ['[false,true,loggedin,privileged,admins,editors] Shortcut that enables full table editing and downloading (Default: false).', false],
        'editableBy' => ['[false,true,loggedin,privileged,admins,editors] Defines who may edit data. Default: false. (only available when using option "dataSource")', null],
        'editMode' => ['[inline,form,permanent] Selects how table data shall be editable: either inline or via a form. By default the form pops up in an overlay, '.'unless "permanent" is specified, then it\' permanently visible. (Default: form).', null],
        'editFormArgs' => ['Arguments that will passed on to the forms-class.', false],
        'editFormTemplate' => ['A markdown file that will be used for rendering the form.', false],
        'tableButtons' => ['Activates a row of activity buttons related to the form. If in editMode, a "New Record" button will be added.', false],
        'activityButtons' => ['Synonyme for "tableButtons".', false],
        'multiline' => ['If true (and inline-editing is true), table cells get class "lzy-editable-multiline" thus supporting multiline editing.', false],
        'labelColons' => ['If false, trailing colon of labels in editing-forms are omitted.', true],
        'rowButtons' => ['(optional comma-separated-list) Prepends a column to each row containing custom buttons. Buttons can be defined as names of icons or HTML code. E.g. "send,trash"', null],
        'showRecViewButton' => ['[true|false] If true, a button to open a popup is added to each row. The popup presents the data record in form view.', false],
        'paging' => ['[true|false] When using "Datatables": turns paging on or off (default is on)', false],
        'initialPageLength' => ['[int] When using "Datatables": defines the initial page length (default is 10)', false],
        'excludeColumns' => ['(optional) Allows to exclude specific columns, e.g. "excludeColumns:2,4-5"', false],
        'sort' => ['(optional) Allows to sort the table on a given columns, e.g. "sort:3"', false],
        'sortExcludeHeader' => ['(optional) Allows to exclude the first row from sorting', false],
        'autoConvertLinks' => ['(optional) If true, all data is scanned for patterns of URL, mail address or telephone numbers. If found the value is wrapped in a &lt;a> tag', false],
        'autoConvertTimestamps' => ['(optional) If true, integer values that could be timestamps (= min. 10 digits) are converted to time strings.', false],
        'caption' => ['(optional) If set, a caption tag is added to the table. The caption text may contain the pattern "##" which will be replaced by a number.', false],
        'captionIndex' => ['(optional) If set, will override the automatically applied table counter', false],
        'headers' => ['(optional) Column headers may be supplied in the form [A|B|C...]', true],
        'headersTop' => ['Synonyme for "headers"', false],
        'headersLeft' => ['(optional) Row headers may be supplied in the form [A|B|C...]', false],
        'translateHeaders' => ['(optional) If true, header elements will be translated if definition exists.', false],
        'showRowNumbers' => ['(optional) Adds a left most column showing row numbers.', false],
        'injectSelectionCol' => ['(optional) Adds a column showing row selection checkboxes.', false],
        'hideMetaFields' => ['(optional) If true, system (or "meta") fields are not rendered (default: true).', true],
        'tableDataAttr' => ['(optional) ', false],
        'includeCellRefs' => ['(optional) If set, data-source and cell-coordinates are added as \'data-xy\' attributes', false],
        'process' => ['(optional) Provide name of a frontmatter variable to activate this feature. In the frontmatter area define an array containing instructions for manipulating table data. See <a href="https://getlizzy.net/macros/extensions/table/" target="_blank">Doc</a> for further details.', false],
        'processInstructionsFile' => ['The same as \'process\' except that instructions are retrieved from a .yaml file', false],
        'suppressError' => ['(optional) Suppresses the error message in case dataSource is not available.', false],
        'enableTooltips' => ['(optional) Enables tooltips, e.g. for cells containing too much text. To use, apply a class-name containing "tooltip" to the targeted cell, e.g. "tooltip1".', false],
        'export' => ['(optional) .', false],
        'exportMetaElements' => ['(optional) .', false],
        'prefill' => ['(optional) .', false],
        'cellTooltips' => ['[true,min-chars] If set, cells with large content (>min-chars) will get a tooltip showing the content. Default min-chars: 30.', false],
    ],
    'summary' => <<<EOT
Renders a table.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class Table extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args, string $argStr): string
    {
        $inx = self::$inx++;

        $tbl = new \Usility\PageFactory\PageElements\Tables($this->pfy, $args);
        $str = $tbl->render();

        return $str;
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
