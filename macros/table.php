<?php
namespace PgFactory\PageFactory;
use PgFactory\PageFactoryElements\DataTable as DataTable;

/*
 * Macro
 */

return function ($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['File to be used as data-source. (json, yaml or cvs)', null],
            'tableId' => ['(optional) Applies an ID to the table tag.', null],
            'tableClass' => ['(optional) Applies an CLASS to the table tag.', null],
            'tdClass' => ['(optional) Applies an CLASS to TD tags.', null],
            'tableWrapperClass' => ['(optional) Applies an CLASS to the table-wrapper div.', null],
            'headers' => ['[Comma-separated-list] List of column labels which defines order and selection of columns.<br>'.
                'E.g. "name,street,zip" or "name:Name,street:Strasse,zip:PLZ"', true],
            'footers' => ['[Comma-separated-list] If set, a footer row is added. For selected columns cell content '.
                'can be specified as "%sum%" or "%count%" (any other values rendered as text). '.
                'E.g. footers: "name:%count%, amount:%sum%".', null],
            'caption' => ['(optional) If set, a caption tag is added to the table. The caption text may contain the '.
                'pattern "%#" which will be replaced by an index number.', null],
            'captionPosition' => ['(above,below) Specifies whether caption will be place above or below the table.', 'below'],
            'dataReference' => ["If true, rec-keys (as 'data-reckey') and element-names (as 'data-elemkey') are included in table. ".
                "If dataReference is a string, it is added to the table-wrapper div as 'data-ref'.", null],
            'interactive' => ['If true, module "Datatables" is activated, providing for interactive features such as sorting, searching etc.', false],
            'edit' => ['[true|{options}] Shorthand for defining table-buttons, service-columns, permissions and mode.<br>'.
                'E.g. ``edit:{tableButtons:"delete,download,custom", serviceColumns:"select,num,custom", '.
                'permission:"admin"}``. <br>``edit:true`` activates default options.', null],
            'tableButtons' => ['[list of buttons] If set, includes a row of buttons above the table to perform '.
                'various actions.<br>Available buttons: ``delete`` or ``archive``, ``download`` or `custom`.', null],
            'serviceColumns' => ['[list of service columns] Injects service columns for various purposes: '.
                '``select``, ``num``, ``edit``, ``map``, ``send``, or `custom`.', null],
            'permission' => ['[none,anyone,localhost,loggedin etc.].', null],
            'editableBy' => ['Synonyme for "permission".', null],
            'masterFileRecKeyType' => ['Controls under what kind of key data is stored.', null],
            'downloadFilename' => ['Defines the name of the download-file (if option "tableButtons" is active).', false],
            'cellMinHeight' => ['(css length) If set, adds styling instructions for cell\'s min height.', false],
            'cellMaxHeight' => ['(css length) If set, adds styling instructions for cell\'s max height.', false],
            'scrollHints' => ['(bool) If true, scroll hint shadows become visible in cells heigher than their viewport.', true],
            'includeTimestamp' => ['(bool) If true, timestamp of data record is included.', false],
            'includeSystemElements' => ['[bool] If true, system fields "_timestamp" and "_reckey" are included '.
                'in output table.', false],
            'paging' => ['If true, data is presented using paging.', true],
            'minRows' => ['If set, table will be filled with empty lines up to the given number of rows.', null],
            'computedCells' => ['(array of key:expr tuples) Cells identified by key are computed by PHP eval().<br>'.
                'Refering to cells: "$col-name" or relative: "$-1" or "$1,-2". "$$" is short for current cell.<br>'.
                'Example: "start:\'strtoupper($$)\'"', null],
            'order' => ['(element name) If set, data is sorted on given data element.'.
                'Syntax: order= &lt;col-index> | &lt;col-index>:desc | &lt;col-name> | &lt;col-name>:desc.', null],
            'sort' => ['synonum for "order"', null],
            'filter' => ['{name:xy, value:xy} Filters out data records not complying with given criteria.<br>'.
                'Supported Operands: `=\=`, `!=`, `!=\=`, `>`, `>=`, `<`, `<\=`, `contains`, `!contains`, `starts-width`, `!starts-width`, `ends-with`, `!ends-with`<br>'.
                'Example: `filter: {name: start, value:2026-09-01, op:>}`', null],
            'obfuscateCols' => ['.', null],
            'reversed' => ['If true, the table is presented in reversed order.', false],
            'placeholderForUndefined' => ['Defines the string which will be placed in cells for which a value is missing. (default:"")', null],
            'cellClickCallback' => ['(false|function-name|js-code) If set, given function is executed when user clicks on a table cell.<br>'.
                'Example: `rowCallback: myCallback`
    function myCallback(ev) {
	  const cellValue = ev.target.closest(\'td\').innerText;
	  console.log(cellValue);
    }
', null],
            'rowCallback' => ['Synonym for cellClickCallback', null],
            ],
        'summary' => <<<EOT
# table()

Renders data as an HTML table.

### Styling

- ``--pfy-table-bg-color``      16em>> sets table's background color
- ``--pfy-table-border-color``      16em>> sets table's border color
- ``--pfy-hint-shadow-color``      16em>> sets color of scroll hint shadows

### Only for special use: 
- 'masterFileRecKeyType' \=> 'index' or '_uid' or '_origRecKey' or any recData element as '.xy'
- 'masterFileRecKeySort' \=> 'asc' or 'desc' 
- 'masterFileRecKeySortOnElement' \=> name of a recData element as 'xy'
- short form, e.g. 'masterFileRecKeyType' \=> 'index,sort:name'

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $sourceCode, $inx, $funcName) = $str;
        $str = $sourceCode;
    }

    // assemble output:
    $file = $args['file'];
    if ($edit = $args['edit']) {
        if ($edit === true) {
            $args['permission'] = 'localhost,loggedin';
            $args['tableButtons'] = 'delete,download';
            $args['serviceColumns'] = 'select,num';
        } else {
            $args['permission'] = $edit['permission']??'';
            $args['tableButtons'] = $edit['tableButtons']??'';
            $args['serviceColumns'] = $edit['serviceColumns']??'';
        }
    }
    if ($args['sort']) {
        $args['order'] = $args['sort'];
    }
    if ($args['rowCallback']) {
        $args['cellClickCallback'] = $args['rowCallback'];
    }
    if ($args['editableBy']) {
        $args['permission'] = $args['editableBy'];
    }
    $args = array_filter($args, function($el) {
        return $el !== null;
    });

    $file = Utils::resolvePath($file);
    $ds = new DataTable($file, $args);
    $str .= $ds->render();

    return $str;
};

