<?php
namespace Usility\PageFactory;
use Usility\PageFactoryElements\DataTable as DataTable;

/*
 * Twig function
 */

function table($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['File to be used as data-source.', false],
            'tableId' => ['(optional) Applies an ID to the table tag.', false],
            'tableClass' => ['(optional) Applies an CLASS to the table tag.', false],
            'tableWrapperClass' => ['(optional) Applies an CLASS to the table-wrapper div.', false],
            'headers' => ['[Comma-separated-list] List of column labels to define order and selection of columns.'.
                'E.g. "name,street,zip" or "name:Name,street:Strasse,zip:PLZ"', true],
//                'E.g. "name,street,zip" or "name:Name,street:Strasse,zip:PLZ"', false],
            'footers' => ['[Comma-separated-list] If set, a footer row is added. For selected columns cell content '.
                'can be specified as "%sum" or "%count" (any other values rendered as text). '.
                'E.g. footers: "name:%count, amount:%sum"', false],
            'showRowNumbers' => ['(optional) Prepends a column showing row numbers. Use variable "pfy-row-number-header" '.
                'to define the row header', false],
            'caption' => ['(optional) If set, a caption tag is added to the table. The caption text may contain the '.
                'pattern "%#" which will be replaced by an index number.', false],
            'captionPosition' => ['(above,below) Specifies whether caption will be place above or below the table.', 'below'],
            'dataReference' => ["If true, rec-keys (as 'data-reckey') and element-names (as 'data-elemkey') are included in table. ".
                "If dataReference is a string, it is added to the table-wrapper div as 'data-ref'.", false],
            'interactive' => ['If true, module "Datatables" is activated, providing for interactive features such as sorting, searching etc.', false],
            'tableButtons' => ['(true|list of buttons) If set, includes a row of buttons above the table to perform '.
                'various actions. Available buttons: \'delete\', \'download\'.', false],
//            'various actions. Available buttons: \'delete\', \'download\', \'add\'.', false],
//        'editableBy' => ['(role) If set, restricts availability of tableButtons to specified user roles.', false],
            'downloadFilename' => ['Defines the name of the download-file (if option "tableButtons" is active).', false],
            'showRowSelectors' => ['If true, prepends a column with checkboxes to select rows.', false],
            'sort' => ['(element name) If set, data is sorted on given data element.', false],
            'export' => ['(true|filename) If set, the table data is exported to files in Xslx and Ods format.', false],        ],
        'summary' => <<<EOT
# table()

Renders data as an HTML table.

Only for special use: 
- 'masterFileRecKeys' \=> 'index' or '_uid' or '_origRecKey' or any recData element as '.xy'
- 'masterFileRecKeySort' \=> 'asc' or 'desc' 
- 'masterFileRecKeySortOnElement' \=> name of a recData element as 'xy'
- short form, e.g. 'masterFileRecKeys' \=> 'index,sort:name'

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

    $file = resolvePath($file, relativeToPage: true);
    $ds = new DataTable($file, $args);
    $str .= $ds->render();
    $str = shieldStr($str); // shield from further processing if necessary

    return $str;
}

